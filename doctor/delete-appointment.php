<?php
ob_start();
// Doctor cancels an appointment in one of their sessions (also removed from calendars).
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'd') {
    header("location: ../login.php");
    exit;
}

if (isset($_GET["id"])) {
    include("../connection.php");
    require_once __DIR__ . '/../lib/calendar.php';
    $id = (int)$_GET["id"];
    $stmt = $database->prepare(
        "SELECT a.appoid FROM appointment a JOIN schedule s ON s.scheduleid = a.scheduleid
           JOIN doctor d ON d.docid = s.docid WHERE a.appoid = ? AND d.docemail = ?"
    );
    $stmt->bind_param("is", $id, $_SESSION['user']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 1) {
        calendar_remove_appointment($database, $id);
        $stmt = $database->prepare("DELETE FROM appointment WHERE appoid = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}
header("location: appointment.php");
exit;
