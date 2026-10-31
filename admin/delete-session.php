<?php
ob_start();
// Admin deletes a session, its bookings are cancelled and removed from calendars
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'a') {
    header("location: ../login.php");
    exit;
}

if (isset($_GET["id"])) {
    include("../connection.php");
    require_once __DIR__ . '/../lib/calendar.php';
    $id = (int)$_GET["id"];

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
header("location: schedule.php");
exit;
