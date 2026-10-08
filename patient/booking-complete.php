<?php
ob_start();
// Saves the booking and adds it to the patient's calendar (Google or Outlook).
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'p') {
    header('location: ../login.php');
    exit;
}
$useremail = $_SESSION['user'];

include("../connection.php");
require_once __DIR__ . '/../lib/calendar.php';

$stmt = $database->prepare("SELECT pid, pname FROM patient WHERE pemail = ?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userfetch = $stmt->get_result()->fetch_assoc();
$userid = (int)$userfetch["pid"];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["booknow"])) {
    $scheduleid = (int)$_POST["scheduleid"];

    date_default_timezone_set('Australia/Melbourne');
    $date = date('Y-m-d');

    $stmt = $database->prepare("SELECT nop, scheduledate FROM schedule WHERE scheduleid = ?");
    $stmt->bind_param("i", $scheduleid);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    if (!$session || $session['scheduledate'] < $date) { // unknown or past session
        header("location: schedule.php");
        exit;
    }

    $stmt = $database->prepare("SELECT COUNT(*) AS c FROM appointment WHERE scheduleid = ?");
    $stmt->bind_param("i", $scheduleid);
    $stmt->execute();
    $taken = (int)$stmt->get_result()->fetch_assoc()['c'];
    if ($session['nop'] !== null && $taken >= (int)$session['nop']) {
        header("location: appointment.php?action=session-full&id=0");
        exit;
    }
    // One booking per patient per session.
    $stmt = $database->prepare("SELECT 1 FROM appointment WHERE scheduleid = ? AND pid = ?");
    $stmt->bind_param("ii", $scheduleid, $userid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        header("location: appointment.php?action=already-booked&id=0");
        exit;
    }
    $apponum = $taken + 1;

    $stmt = $database->prepare("INSERT INTO appointment (pid, apponum, scheduleid, appodate) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $userid, $apponum, $scheduleid, $date);
    $stmt->execute();
    $appoid = (int)$database->insert_id;

    // add to calendar (if this fails the booking is still saved)
    $sync = calendar_sync_appointment($database, $appoid);

    header("location: appointment.php?action=booking-added&id=" . $apponum . "&appoid=" . $appoid
        . "&sync=" . $sync['status'] . "&provider=" . ($sync['provider'] ?? '') . "&titleget=none");
    exit;
}
header("location: appointment.php");
exit;
