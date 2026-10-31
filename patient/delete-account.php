<?php
// Patient deletes their own account (Settings > Delete account)
ob_start();
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'p') {
    header("location: ../login.php");
    exit;
}

include("../connection.php");
require_once __DIR__ . '/../lib/accounts.php';

// use the logged in patient, not an id from the URL
$stmt = $database->prepare("SELECT pid FROM patient WHERE pemail = ?");
$stmt->bind_param("s", $_SESSION['user']);
$stmt->execute();
$pid = (int)($stmt->get_result()->fetch_assoc()['pid'] ?? 0);

if ($pid) {
    edoc_delete_patient($database, $pid);
}
header("location: ../logout.php");
exit;
