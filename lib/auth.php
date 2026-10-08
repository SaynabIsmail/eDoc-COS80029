<?php
/**
 * After Google / Microsoft sign-in:
 *  1. account already linked (matched on the provider's sub, not email) -> log in
 *  2. email exists but isn't linked -> confirm-link.php, user must enter their password first
 *  3. no account -> new patient account
 * Admin accounts can't use Google/Microsoft sign-in.
 * The provider tokens are saved (encrypted) so bookings can be synced to the calendar.
 */

require_once __DIR__ . '/oauth.php';
require_once __DIR__ . '/calendar.php';

function provider_sub_column(string $provider): string
{
    return $provider === 'google' ? 'google_sub' : 'microsoft_sub'; // whitelisted identifiers
}

function oauth_fail(string $message): void
{
    render_notice_page('We could not sign you in', $message);
}

/** Called from oauth-callback.php and microsoft-callback.php */
function federated_signin(mysqli $db, string $provider): void
{
    try {
        $id = oauth_complete($provider);
    } catch (RuntimeException $ex) {
        oauth_fail($ex->getMessage());
    }
    if ($id['email'] === '' || $id['sub'] === '') {
        oauth_fail('Your account provider did not share an email address for this account. Please use a different account or sign in with your email and password.');
    }

    // ---- "Connect calendar" mode: user is already signed in to eDoc --------------
    if ($id['mode'] === 'connect') {
        $me = $_SESSION['user'] ?? '';
        if ($me === '') {
            oauth_fail('Please sign in to eDoc first, then connect your calendar from the My Appointments page.');
        }
        calendar_store_connection($db, $me, $provider, $id['email'], $id['tokens']);
        $_SESSION['login_provider'] = $provider;
        $back = ($_SESSION['usertype'] ?? '') === 'd' ? 'doctor/index.php' : 'patient/appointment.php';
        redirect($back . '?calendar=connected&provider=' . $provider);
    }

    $col = provider_sub_column($provider);

    // ---- 1. Already linked? (keyed on sub) -------------------------------------
    $stmt = $db->prepare("SELECT email, usertype FROM webuser WHERE {$col} = ?");
    $stmt->bind_param('s', $id['sub']);
    $stmt->execute();
    $linked = $stmt->get_result()->fetch_assoc();
    if ($linked) {
        if ($linked['usertype'] === 'a') {
            oauth_fail('Administrator accounts cannot sign in with ' . e(oauth_provider($provider)['name']) . '. Please sign in with your email address and password.');
        }
        complete_login($db, $linked['email'], $linked['usertype'], $provider, $id);
    }

    // ---- 2. Email already registered? --------------------------------------------
    $stmt = $db->prepare("SELECT email, usertype FROM webuser WHERE email = ?");
    $stmt->bind_param('s', $id['email']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        if ($row['usertype'] === 'a') {
            oauth_fail('Administrator accounts cannot sign in with ' . e(oauth_provider($provider)['name']) . '. Please sign in with your email address and password.');
        }
        if (!$id['email_verified']) {
            oauth_fail('The email address on this ' . e(oauth_provider($provider)['name']) . ' account has not been verified, so it cannot be linked to your eDoc account.');
        }
        // they have to confirm with their existing password before we link the accounts
        $_SESSION['pending_link'] = [
            'email'    => $row['email'],
            'usertype' => $row['usertype'],
            'provider' => $provider,
            'sub'      => $id['sub'],
            'identity' => $id, // includes tokens; saved only after the password is confirmed
            'created'  => time(),
        ];
        redirect('confirm-link.php');
    }

    // ---- 3. Brand-new user -> patient account ---------------------------------
    if (!$id['email_verified']) {
        oauth_fail('The email address on this account has not been verified by your account provider, so an eDoc account cannot be created with it.');
    }
    $name  = $id['name'] !== '' ? $id['name'] : strstr($id['email'], '@', true);
    $phone = $id['phone'] ? substr(preg_replace('/[^0-9+]/', '', $id['phone']), 0, 15) : null;

    $stmt = $db->prepare("INSERT INTO webuser (email, usertype, auth_provider, {$col}) VALUES (?, 'p', ?, ?)");
    $stmt->bind_param('sss', $id['email'], $provider, $id['sub']);
    if (!$stmt->execute()) {
        edoc_log('webuser insert failed: ' . $db->error);
        oauth_fail('We were unable to create your account at this time. Please try again later or contact the clinic.');
    }
    $stmt = $db->prepare("INSERT INTO patient (pemail, pname, ppassword, ptel) VALUES (?, ?, NULL, ?)");
    $stmt->bind_param('sss', $id['email'], $name, $phone);
    $stmt->execute();

    complete_login($db, $id['email'], 'p', $provider, $id);
}

/** Log the user in after Google/Microsoft sign-in (redirects, doesn't return). */
function complete_login(mysqli $db, string $email, string $usertype, string $provider, array $id): void
{
    session_regenerate_id(true); // new session id at privilege change (session fixation)

    enrich_profile($db, $email, $usertype, $id);
    calendar_store_connection($db, $email, $provider, $id['email'], $id['tokens']);

    // Google only gives a refresh token the first time the user consents. If we don't have one,
    // ask again with prompt=consent so calendar sync keeps working (patients only, doctors need 2FA first).
    if ($provider === 'google' && $usertype === 'p' && empty($_SESSION['google_consent_retry'])
        && !calendar_has_refresh_token($db, $email, 'google')
        && strpos($id['tokens']['scope'], GOOGLE_CALENDAR_SCOPE) !== false) {
        $_SESSION['google_consent_retry'] = 1;
        $_SESSION['user'] = $email;
        $_SESSION['usertype'] = $usertype;
        $_SESSION['login_provider'] = $provider;
        oauth_begin('google', 'connect', true);
    }

    $_SESSION['login_provider'] = $provider;
    $_SESSION['date'] = date('Y-m-d');

    if ($usertype === 'd') {
        // doctors go through the 2FA check first (verify-2fa.php)
        if (is_file(EDOC_ROOT . '/verify-2fa.php')) {
            $stmt = $db->prepare("SELECT docid FROM doctor WHERE docemail = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $doc = $stmt->get_result()->fetch_assoc();
            $_SESSION['user'] = '';
            $_SESSION['usertype'] = '';
            $_SESSION['pending_2fa_email'] = $email;
            $_SESSION['pending_2fa_docid'] = $doc['docid'] ?? null;
            redirect('verify-2fa.php');
        }
        $_SESSION['user'] = $email;
        $_SESSION['usertype'] = 'd';
        redirect('doctor/index.php');
    }

    $_SESSION['user'] = $email;
    $_SESSION['usertype'] = 'p';
    redirect('patient/index.php');
}

/** Fill in a missing name / phone number from the Google or Microsoft profile. */
function enrich_profile(mysqli $db, string $email, string $usertype, array $id): void
{
    $phone = $id['phone'] ? substr(preg_replace('/[^0-9+]/', '', $id['phone']), 0, 15) : '';
    if ($usertype === 'p') {
        if ($id['name'] !== '') {
            $stmt = $db->prepare("UPDATE patient SET pname = ? WHERE pemail = ? AND (pname IS NULL OR pname = '')");
            $stmt->bind_param('ss', $id['name'], $email);
            $stmt->execute();
        }
        if ($phone !== '') {
            $stmt = $db->prepare("UPDATE patient SET ptel = ? WHERE pemail = ? AND (ptel IS NULL OR ptel = '')");
            $stmt->bind_param('ss', $phone, $email);
            $stmt->execute();
        }
    } elseif ($usertype === 'd' && $phone !== '') {
        $stmt = $db->prepare("UPDATE doctor SET doctel = ? WHERE docemail = ? AND (doctel IS NULL OR doctel = '')");
        $stmt->bind_param('ss', $phone, $email);
        $stmt->execute();
    }
}

/** CSRF token for our forms. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}
