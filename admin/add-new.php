<?php
// Admin adds a new doctor.
// Error codes for doctors.php: 1 = email already registered, 2 = passwords don't match, 3 = invalid input, 4 = saved
ob_start();
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'a') {
    header("location: ../login.php");
    exit;
}

include("../connection.php");

$error = '3';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim((string)($_POST['name'] ?? ''));
    $nic       = trim((string)($_POST['nic'] ?? ''));
    $spec      = (int)($_POST['spec'] ?? 0);
    $email     = strtolower(trim((string)($_POST['email'] ?? '')));
    $tele      = trim((string)($_POST['Tele'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $cpassword = (string)($_POST['cpassword'] ?? '');

    if ($password !== $cpassword) {
        $error = '2';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '3';
    } else {
        $stmt = $database->prepare("SELECT 1 FROM webuser WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = '1';
        } else {
            $stmt = $database->prepare(
                "INSERT INTO doctor (docemail, docname, docpassword, docnic, doctel, specialties) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssssi", $email, $name, $password, $nic, $tele, $spec);
            $stmt->execute();
            $stmt = $database->prepare("INSERT INTO webuser (email, usertype) VALUES (?, 'd')");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $error = '4';
        }
    }
}

header("location: doctors.php?action=add&error=" . $error);
exit;
