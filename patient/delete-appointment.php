<?php
ob_start();
// Patient cancels a booking, also removes it from their calendar.
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'p') {
    header("location: ../login.php");
    exit;
}

if (isset($_GET["id"])) {
    include("../connection.php");
    require_once __DIR__ . '/../lib/calendar.php';

    $id = (int)$_GET["id"];
    // only allow cancelling your own appointment
    $stmt = $database->prepare("SELECT a.appoid FROM appointment a JOIN patient p ON p.pid = a.pid WHERE a.appoid = ? AND p.pemail = ?");
    $stmt->bind_param("is", $id, $_SESSION['user']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 1) {
        calendar_remove_appointment($database, $id);
        $stmt = $database->prepare("DELETE FROM appointment WHERE appoid = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("location: appointment.php?action=cancelled&id=" . $id);
        exit;
    }
}
header("location: appointment.php");
exit;
