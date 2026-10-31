<?php
// Download one appointment as an .ics file.
// The UID stays the same for an appointment, so re-importing after a reschedule updates the event.
session_start();
if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'p') {
    header('location: ../login.php');
    exit;
}
require __DIR__ . '/../connection.php';
require __DIR__ . '/../lib/calendar.php';

$a = calendar_appointment($database, (int)($_GET['id'] ?? 0));
if (!$a || strcasecmp($a['pemail'], $_SESSION['user']) !== 0) {
    header('location: appointment.php');
    exit;
}
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="appointment-OC-000-' . (int)$a['appoid'] . '.ics"');
echo ics_calendar([ics_vevent($a)], app_config('clinic_name', 'eDoc') . ' appointment');
