<?php
// Calendar subscription feed for users who didn't connect Google/Outlook.
// Apple Calendar etc. re-download it regularly, so changes show up automatically.
// The link includes a token (HMAC of the email) so only that user can open it.
require __DIR__ . '/connection.php';
require __DIR__ . '/lib/calendar.php';

$u = (string)($_GET['u'] ?? '');
$t = (string)($_GET['t'] ?? '');
$email = base64_decode(strtr($u, '-_', '+/'), true);
if (!$email || !hash_equals(feed_token($email), $t)) {
    http_response_code(404);
    exit('This calendar link is invalid or no longer active.');
}

$events = [];
$isDoctor = false;
$stmt = $database->prepare("SELECT usertype FROM webuser WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$isDoctor = (($stmt->get_result()->fetch_assoc()['usertype'] ?? '') === 'd');

$sql = $isDoctor
    ? "SELECT a.appoid FROM appointment a JOIN schedule s ON s.scheduleid = a.scheduleid JOIN doctor d ON d.docid = s.docid
        WHERE d.docemail = ? AND s.scheduledate >= CURDATE() - INTERVAL 60 DAY"
    : "SELECT a.appoid FROM appointment a JOIN patient p ON p.pid = a.pid JOIN schedule s ON s.scheduleid = a.scheduleid
        WHERE p.pemail = ? AND s.scheduledate >= CURDATE() - INTERVAL 60 DAY";
$stmt = $database->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    if ($a = calendar_appointment($database, (int)$r['appoid'])) {
        $events[] = ics_vevent($a, $isDoctor);
    }
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="edoc-appointments.ics"');
header('Cache-Control: no-cache, must-revalidate');
echo ics_calendar($events, app_config('clinic_name', 'eDoc') . ' appointments');
