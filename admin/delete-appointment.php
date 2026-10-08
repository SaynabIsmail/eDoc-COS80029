<?php
ob_start();
// Admin deletes an appointment (also removed from the patient's and doctor's calendar)
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'a') {
    header("location: ../login.php");
    exit;
}

if (isset($_GET["id"])) {
    include("../connection.php");
    require_once __DIR__ . '/../lib/calendar.php';
    $id = (int)$_GET["id"];
    calendar_remove_appointment($database, $id);
    $stmt = $database->prepare("DELETE FROM appointment WHERE appoid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}
header("location: appointment.php");
exit;
