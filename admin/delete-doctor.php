<?php
// Admin removes a doctor along with their sessions, bookings and calendar events
ob_start();
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'a') {
    header("location: ../login.php");
    exit;
}

if (isset($_GET["id"])) {
    include("../connection.php");
    require_once __DIR__ . '/../lib/accounts.php';
    edoc_delete_doctor($database, (int)$_GET["id"]);
}
header("location: doctors.php");
exit;
