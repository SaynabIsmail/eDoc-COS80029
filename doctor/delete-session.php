<?php
ob_start();
// Doctor cancels one of their sessions, bookings are cancelled and removed from calendars
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'd') {
    header("location: ../login.php");
    exit;
}

if (isset($_GET["id"])) {
    include("../connection.php");
    include("require-2fa.php");
    require_once __DIR__ . '/../lib/calendar.php';
    $id = (int)$_GET["id"];

    $stmt = $database->prepare("SELECT s.scheduleid FROM schedule s JOIN doctor d ON d.docid = s.docid WHERE s.scheduleid = ? AND d.docemail = ?");
    $stmt->bind_param("is", $id, $_SESSION['user']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 1) {
        $stmt = $database->prepare("SELECT appoid FROM appointment WHERE scheduleid = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            calendar_remove_appointment($database, (int)$row['appoid']);
        }
        $stmt = $database->prepare("DELETE FROM appointment WHERE scheduleid = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt = $database->prepare("DELETE FROM schedule WHERE scheduleid = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}
header("location: schedule.php");
exit;
