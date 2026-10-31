<?php
// Doctor deletes their own account (Settings > Delete account)
ob_start();
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'd') {
    header("location: ../login.php");
    exit;
}

include("../connection.php");
include("require-2fa.php");
require_once __DIR__ . '/../lib/accounts.php';

// use the logged in doctor, not an id from the URL
$stmt = $database->prepare("SELECT docid FROM doctor WHERE docemail = ?");
$stmt->bind_param("s", $_SESSION['user']);
$stmt->execute();
$docid = (int)($stmt->get_result()->fetch_assoc()['docid'] ?? 0);

if ($docid) {
    edoc_delete_doctor($database, $docid);
}
header("location: ../logout.php");
exit;
