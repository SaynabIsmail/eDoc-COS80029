<?php
/**
 * Calendar sync for appointments.
 *
 * Google account    -> Google Calendar (Calendar API)
 * Microsoft account -> Outlook calendar (Microsoft Graph)
 * Anyone else       -> .ics download, or the subscription feed (calendar-feed.php)
 *
 * book = create event, reschedule = move the same event, cancel = delete event.
 * If the calendar API fails the booking still goes through, we just log it.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/oauth.php';

function calendar_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = db_has_table($db, 'calendar_connections') && db_has_table($db, 'appointment_calendar_events');
    }
    return $ready;
}

// ---------------------------------------------------------------------------
// Token storage (encrypted at rest)
// ---------------------------------------------------------------------------

function calendar_scope_ok(string $provider, string $scope): bool
{
    return $provider === 'google'
        ? strpos($scope, GOOGLE_CALENDAR_SCOPE) !== false
        : stripos($scope, 'Calendars.ReadWrite') !== false;
}

function calendar_store_connection(mysqli $db, string $email, string $provider, string $providerEmail, array $tokens): void
{
    if (!calendar_ready($db)) {
        return;
    }
    $access  = encrypt_secret($tokens['access_token']);
    $refresh = encrypt_secret($tokens['refresh_token'] ?? null);
    $expires = (int)$tokens['expires_at'];
    $scope   = (string)$tokens['scope'];
    $status  = calendar_scope_ok($provider, $scope) ? 'active' : 'no_calendar_permission';

    // Keep the previously stored refresh token if the provider didn't send a new one (Google).
    $stmt = $db->prepare(
        "INSERT INTO calendar_connections
            (email, provider, provider_email, access_token_enc, refresh_token_enc, expires_at, scope, status, last_login_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            provider_email    = VALUES(provider_email),
            access_token_enc  = VALUES(access_token_enc),
            refresh_token_enc = COALESCE(VALUES(refresh_token_enc), refresh_token_enc),
            expires_at        = VALUES(expires_at),
            scope             = VALUES(scope),
            status            = VALUES(status),
            last_error        = NULL,
            last_login_at     = NOW()"
    );
    $stmt->bind_param('sssssiss', $email, $provider, $providerEmail, $access, $refresh, $expires, $scope, $status);
    $stmt->execute();
}

function calendar_has_refresh_token(mysqli $db, string $email, string $provider): bool
{
    if (!calendar_ready($db)) {
        return true; // nothing to retry for
    }
    $stmt = $db->prepare("SELECT 1 FROM calendar_connections WHERE email = ? AND provider = ? AND refresh_token_enc IS NOT NULL");
    $stmt->bind_param('ss', $email, $provider);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function calendar_connection(mysqli $db, string $email, ?string $provider = null): ?array
{
    if (!calendar_ready($db)) {
        return null;
    }
    if ($provider) {
        $stmt = $db->prepare("SELECT * FROM calendar_connections WHERE email = ? AND provider = ? AND status = 'active'");
        $stmt->bind_param('ss', $email, $provider);
    } else {
        // The calendar of the provider the user signed in with most recently.
        $stmt = $db->prepare("SELECT * FROM calendar_connections WHERE email = ? AND status = 'active' ORDER BY last_login_at DESC LIMIT 1");
        $stmt->bind_param('s', $email);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function calendar_mark_error(mysqli $db, array $conn, string $status, string $error): void
{
    $stmt = $db->prepare("UPDATE calendar_connections SET status = ?, last_error = ? WHERE id = ?");
    $stmt->bind_param('ssi', $status, $error, $conn['id']);
    $stmt->execute();
}

/** A valid access token for this connection, refreshing it when expired. */
function calendar_access_token(mysqli $db, array $conn): ?string
{
    if ((int)$conn['expires_at'] > time()) {
        $tok = decrypt_secret($conn['access_token_enc']);
        if ($tok) {
            return $tok;
        }
    }
    $refresh = decrypt_secret($conn['refresh_token_enc']);
    if (!$refresh) {
        calendar_mark_error($db, $conn, 'expired', 'Access expired and no refresh token, user needs to sign in again');
        return null;
    }
    $new = oauth_refresh($conn['provider'], $refresh);
    if (!$new) {
        // revoked, or the 7 day expiry google uses for apps in testing mode
        calendar_mark_error($db, $conn, 'expired', 'Refresh failed, user needs to sign in again');
        return null;
    }
    $acc = encrypt_secret($new['access_token']);
    $ref = encrypt_secret($new['refresh_token']);
    $stmt = $db->prepare("UPDATE calendar_connections SET access_token_enc = ?, refresh_token_enc = ?, expires_at = ? WHERE id = ?");
    $stmt->bind_param('ssii', $acc, $ref, $new['expires_at'], $conn['id']);
    $stmt->execute();
    return $new['access_token'];
}

function calendar_disconnect(mysqli $db, string $email, string $provider): void
{
    $conn = calendar_connection($db, $email, $provider);
    if ($conn && $provider === 'google') {
        $tok = decrypt_secret($conn['refresh_token_enc']) ?: decrypt_secret($conn['access_token_enc']);
        if ($tok) {
            http_request('POST', 'https://oauth2.googleapis.com/revoke', ['form' => ['token' => $tok]]);
        }
    }
    $stmt = $db->prepare("DELETE FROM calendar_connections WHERE email = ? AND provider = ?");
    $stmt->bind_param('ss', $email, $provider);
    $stmt->execute();
}

// ---------------------------------------------------------------------------
// Appointment details -> event
// ---------------------------------------------------------------------------

function calendar_appointment(mysqli $db, int $appoid): ?array
{
    $stmt = $db->prepare(
        "SELECT a.appoid, a.apponum, a.appodate, a.pid, p.pemail, p.pname,
                s.scheduleid, s.title, s.scheduledate, s.scheduletime,
                d.docid, d.docname, d.docemail, sp.sname AS specialty
           FROM appointment a
           JOIN schedule s ON s.scheduleid = a.scheduleid
           JOIN patient  p ON p.pid = a.pid
           JOIN doctor   d ON d.docid = s.docid
      LEFT JOIN specialties sp ON sp.id = d.specialties
          WHERE a.appoid = ?"
    );
    $stmt->bind_param('i', $appoid);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** @return array{0:DateTimeImmutable,1:DateTimeImmutable} start/end in the clinic's timezone */
function calendar_times(array $a): array
{
    $tz = new DateTimeZone(app_config('clinic_timezone', 'Australia/Melbourne'));
    $start = new DateTimeImmutable($a['scheduledate'] . ' ' . ($a['scheduletime'] ?: '09:00:00'), $tz);
    $end = $start->modify('+' . (int)app_config('appointment_minutes', 30) . ' minutes');
    return [$start, $end];
}

function calendar_texts(array $a, bool $forDoctor): array
{
    $ref = 'OC-000-' . $a['appoid'];
    $num = sprintf('%02d', (int)$a['apponum']);
    $clinic = (string)app_config('clinic_name', 'eDoc');
    if ($forDoctor) {
        $title = 'Patient appointment: ' . $a['pname'] . ' (No. ' . $num . ')';
        $desc = "Session: {$a['title']}\nPatient: {$a['pname']}\nAppointment number: {$num}\nReference: {$ref}";
    } else {
        $title = 'Appointment with ' . $a['docname'] . ' - ' . $clinic;
        $desc = "Session: {$a['title']}\nDoctor: {$a['docname']}" . ($a['specialty'] ? " ({$a['specialty']})" : '')
            . "\nAppointment number: {$num}\nReference: {$ref}";
    }
    $desc .= "\n\nBooked through {$clinic}. If this appointment is rescheduled or cancelled in eDoc, "
        . "this calendar event is updated automatically.\nManage your appointments: " . app_url('login.php');
    return [$title, $desc];
}

function google_event_body(array $a, bool $forDoctor): array
{
    [$start, $end] = calendar_times($a);
    [$title, $desc] = calendar_texts($a, $forDoctor);
    $tz = app_config('clinic_timezone', 'Australia/Melbourne');
    return [
        'summary'     => $title,
        'description' => $desc,
        'location'    => app_config('clinic_address', ''),
        'start'       => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => $tz],
        'end'         => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => $tz],
        'reminders'   => ['useDefault' => false, 'overrides' => [
            ['method' => 'popup', 'minutes' => (int)app_config('reminder_minutes', 60)],
        ]],
        'extendedProperties' => ['private' => ['edoc_appoid' => (string)$a['appoid']]],
    ];
}

function graph_event_body(array $a, bool $forDoctor): array
{
    [$start, $end] = calendar_times($a);
    [$title, $desc] = calendar_texts($a, $forDoctor);
    $utc = new DateTimeZone('UTC'); // UTC avoids Windows-vs-IANA timezone name differences in Graph
    return [
        'subject'  => $title,
        'body'     => ['contentType' => 'text', 'content' => $desc],
        'start'    => ['dateTime' => $start->setTimezone($utc)->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
        'end'      => ['dateTime' => $end->setTimezone($utc)->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
        'location' => ['displayName' => app_config('clinic_address', '')],
        'isReminderOn' => true,
        'reminderMinutesBeforeStart' => (int)app_config('reminder_minutes', 60),
        'showAs'   => 'busy',
        'categories' => ['eDoc'],
    ];
}

/**
 * Create (eventId null) or move (eventId set) the event in the provider's calendar.
 * @return array{ok:bool, event_id:?string, error:?string}
 */
function provider_upsert_event(mysqli $db, array $conn, ?string $eventId, array $a, bool $forDoctor): array
{
    $token = calendar_access_token($db, $conn);
    if (!$token) {
        return ['ok' => false, 'event_id' => $eventId, 'error' => 'calendar access expired'];
    }
    if ($conn['provider'] === 'google') {
        $base = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
        $body = google_event_body($a, $forDoctor);
    } else {
        $base = 'https://graph.microsoft.com/v1.0/me/events';
        $body = graph_event_body($a, $forDoctor);
    }

    if ($eventId) {
        $res = http_request('PATCH', $base . '/' . rawurlencode($eventId), ['bearer' => $token, 'json' => $body]);
        if ($res['status'] === 200) {
            return ['ok' => true, 'event_id' => $eventId, 'error' => null];
        }
        if (!in_array($res['status'], [404, 410], true)) {
            return ['ok' => false, 'event_id' => $eventId, 'error' => "update HTTP {$res['status']}: " . substr($res['body'], 0, 200)];
        }
        // The user deleted the event in their calendar app -> create it again below.
    }
    $res = http_request('POST', $base, ['bearer' => $token, 'json' => $body]);
    if (in_array($res['status'], [200, 201], true) && !empty($res['json']['id'])) {
        return ['ok' => true, 'event_id' => $res['json']['id'], 'error' => null];
    }
    if ($res['status'] === 401 || $res['status'] === 403) {
        calendar_mark_error($db, $conn, 'expired', "HTTP {$res['status']} from calendar API");
    }
    return ['ok' => false, 'event_id' => null, 'error' => "create HTTP {$res['status']}: " . substr($res['body'], 0, 200)];
}

function provider_delete_event(mysqli $db, array $conn, string $eventId): bool
{
    $token = calendar_access_token($db, $conn);
    if (!$token) {
        return false;
    }
    $url = $conn['provider'] === 'google'
        ? 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($eventId) . '?sendUpdates=none'
        : 'https://graph.microsoft.com/v1.0/me/events/' . rawurlencode($eventId);
    $res = http_request('DELETE', $url, ['bearer' => $token]);
    return in_array($res['status'], [200, 204, 404, 410], true); // already gone counts as deleted
}

// ---------------------------------------------------------------------------
// Public API used by booking / reschedule / cancel pages
// ---------------------------------------------------------------------------

/**
 * Put the appointment into (or move it within) the patient's calendar, and the
 * doctor's calendar if they've connected one. Call after INSERT or UPDATE.
 * @return array{status:string, provider:?string} for the patient's calendar:
 *         status = synced | not_connected | error | disabled
 */
function calendar_sync_appointment(mysqli $db, int $appoid): array
{
    if (!calendar_ready($db)) {
        return ['status' => 'disabled', 'provider' => null];
    }
    $a = calendar_appointment($db, $appoid);
    if (!$a) {
        return ['status' => 'error', 'provider' => null];
    }

    $owners = [[$a['pemail'], false]];
    if (app_config('sync_doctor_calendar', true) && $a['docemail']) {
        $owners[] = [$a['docemail'], true];
    }

    $patientResult = ['status' => 'not_connected', 'provider' => null];
    foreach ($owners as [$owner, $forDoctor]) {
        try {
            $result = sync_one_owner($db, $a, $owner, $forDoctor);
        } catch (Throwable $ex) {
            edoc_log("calendar sync appoid=$appoid owner=$owner: " . $ex->getMessage());
            $result = ['status' => 'error', 'provider' => null];
        }
        if (!$forDoctor) {
            $patientResult = $result;
        }
    }
    return $patientResult;
}

function sync_one_owner(mysqli $db, array $a, string $owner, bool $forDoctor): array
{
    $appoid = (int)$a['appoid'];
    $stmt = $db->prepare("SELECT * FROM appointment_calendar_events WHERE appoid = ? AND owner_email = ?");
    $stmt->bind_param('is', $appoid, $owner);
    $stmt->execute();
    $map = $stmt->get_result()->fetch_assoc();

    // Existing event -> keep updating it in the calendar it already lives in.
    $conn = $map ? calendar_connection($db, $owner, $map['provider']) : null;
    $eventId = $conn ? $map['event_id'] : null;
    if (!$conn) {
        $conn = calendar_connection($db, $owner); // most recent sign-in provider
    }
    if (!$conn) {
        return ['status' => 'not_connected', 'provider' => null];
    }

    $r = provider_upsert_event($db, $conn, $eventId, $a, $forDoctor);
    if ($r['ok']) {
        $stmt = $db->prepare(
            "INSERT INTO appointment_calendar_events (appoid, owner_email, provider, event_id, synced_at, last_error)
             VALUES (?, ?, ?, ?, NOW(), NULL)
             ON DUPLICATE KEY UPDATE provider = VALUES(provider), event_id = VALUES(event_id), synced_at = NOW(), last_error = NULL"
        );
        $stmt->bind_param('isss', $appoid, $owner, $conn['provider'], $r['event_id']);
        $stmt->execute();
        return ['status' => 'synced', 'provider' => $conn['provider']];
    }

    edoc_log("calendar sync appoid=$appoid owner=$owner provider={$conn['provider']}: {$r['error']}");
    if ($map) {
        $stmt = $db->prepare("UPDATE appointment_calendar_events SET last_error = ? WHERE id = ?");
        $stmt->bind_param('si', $r['error'], $map['id']);
        $stmt->execute();
    }
    return ['status' => 'error', 'provider' => $conn['provider']];
}

/** Remove the appointment's events from every calendar. Call before or after the DELETE. */
function calendar_remove_appointment(mysqli $db, int $appoid): void
{
    if (!calendar_ready($db)) {
        return;
    }
    $stmt = $db->prepare("SELECT * FROM appointment_calendar_events WHERE appoid = ?");
    $stmt->bind_param('i', $appoid);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $map) {
        try {
            $conn = calendar_connection($db, $map['owner_email'], $map['provider']);
            if ($conn && !provider_delete_event($db, $conn, $map['event_id'])) {
                edoc_log("calendar delete failed appoid=$appoid owner={$map['owner_email']}");
            }
        } catch (Throwable $ex) {
            edoc_log("calendar delete appoid=$appoid: " . $ex->getMessage());
        }
    }
    $stmt = $db->prepare("DELETE FROM appointment_calendar_events WHERE appoid = ?");
    $stmt->bind_param('i', $appoid);
    $stmt->execute();
}

/** appoid => provider, used to show the sync status on My Appointments */
function calendar_synced_map(mysqli $db, string $owner): array
{
    if (!calendar_ready($db)) {
        return [];
    }
    $stmt = $db->prepare("SELECT appoid, provider FROM appointment_calendar_events WHERE owner_email = ? AND last_error IS NULL");
    $stmt->bind_param('s', $owner);
    $stmt->execute();
    $out = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $out[(int)$r['appoid']] = $r['provider'];
    }
    return $out;
}

function provider_label(?string $provider): string
{
    return $provider === 'google' ? 'Google Calendar' : ($provider === 'microsoft' ? 'Outlook Calendar' : 'calendar');
}

// ---------------------------------------------------------------------------
// .ics download and subscription feed
// ---------------------------------------------------------------------------

function ics_escape(string $s): string
{
    return str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\\;", "\\,", "\\n", "\\n"], $s);
}

function ics_fold(string $line): string
{
    $out = '';
    while (strlen($line) > 75) {
        $cut = 75;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--; // don't split a UTF-8 character
        }
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
    }
    return $out . $line . "\r\n";
}

function ics_vevent(array $a, bool $forDoctor = false): string
{
    [$start, $end] = calendar_times($a);
    [$title, $desc] = calendar_texts($a, $forDoctor);
    $utc = new DateTimeZone('UTC');
    $host = parse_url(app_url(), PHP_URL_HOST) ?: 'edoc.local';
    $lines = [
        'BEGIN:VEVENT',
        'UID:edoc-appt-' . $a['appoid'] . '@' . $host,        // stable: same appointment = same event
        'SEQUENCE:' . max(0, time() - 1767225600),            // grows on each change (reschedule wins)
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z'),
        'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z'),
        'SUMMARY:' . ics_escape($title),
        'DESCRIPTION:' . ics_escape($desc),
        'LOCATION:' . ics_escape((string)app_config('clinic_address', '')),
        'STATUS:CONFIRMED',
        'BEGIN:VALARM',
        'ACTION:DISPLAY',
        'DESCRIPTION:' . ics_escape($title),
        'TRIGGER:-PT' . (int)app_config('reminder_minutes', 60) . 'M',
        'END:VALARM',
        'END:VEVENT',
    ];
    return implode('', array_map('ics_fold', $lines));
}

function ics_calendar(array $events, string $name): string
{
    $head = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//eDoc//Appointments//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
        'X-WR-CALNAME:' . ics_escape($name), 'X-WR-TIMEZONE:' . app_config('clinic_timezone', 'Australia/Melbourne'),
        'REFRESH-INTERVAL;VALUE=DURATION:PT15M', 'X-PUBLISHED-TTL:PT15M'];
    return implode('', array_map('ics_fold', $head)) . implode('', $events) . "END:VCALENDAR\r\n";
}

/** Secret per-user token for the subscription feed URL. */
function feed_token(string $email): string
{
    return substr(hash_hmac('sha256', 'feed|' . strtolower($email), crypto_key()), 0, 40);
}

function feed_url(string $email, bool $webcal = false): string
{
    $url = app_url('calendar-feed.php') . '?u=' . rtrim(strtr(base64_encode($email), '+/', '-_'), '=') . '&t=' . feed_token($email);
    return $webcal ? preg_replace('#^https?://#', 'webcal://', $url) : $url;
}
