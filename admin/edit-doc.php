<?php
// Admin edits a doctor's details.
// Error codes: 1 = email already used, 2 = passwords don't match, 3 = invalid input, 4 = saved
ob_start();
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'a') {
    header("location: ../login.php");
    exit;
}

include("../connection.php");

$id = (int)($_POST['id00'] ?? 0);

$error = '3';
$oldemail = '';
if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $database->prepare("SELECT docemail FROM doctor WHERE docid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $oldemail = (string)($stmt->get_result()->fetch_assoc()['docemail'] ?? '');

    $name      = trim((string)($_POST['name'] ?? ''));
    $nic       = trim((string)($_POST['nic'] ?? ''));
    $spec      = (int)($_POST['spec'] ?? 0);
    $email     = strtolower(trim((string)($_POST['email'] ?? '')));
    $tele      = trim((string)($_POST['Tele'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $cpassword = (string)($_POST['cpassword'] ?? '');

    if ($oldemail === '') {
        $error = '3';
    } elseif ($password !== $cpassword) {
        $error = '2';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '3';
    } else {
        $stmt = $database->prepare("SELECT 1 FROM webuser WHERE email = ? AND email <> ?");
        $stmt->bind_param("ss", $email, $oldemail);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = '1';
        } else {
            $stmt = $database->prepare(
                "UPDATE doctor SET docemail = ?, docname = ?, docpassword = ?, docnic = ?, doctel = ?, specialties = ? WHERE docid = ?"
            );
            $stmt->bind_param("sssssii", $email, $name, $password, $nic, $tele, $spec, $id);
            $stmt->execute();

            if ($email !== $oldemail) {
                foreach ([
                    "UPDATE webuser SET email = ? WHERE email = ?",
                    "UPDATE calendar_connections SET email = ? WHERE email = ?",
                    "UPDATE appointment_calendar_events SET owner_email = ? WHERE owner_email = ?",
                ] as $sql) {
                    if ($stmt = $database->prepare($sql)) {
                        $stmt->bind_param("ss", $email, $oldemail);
                        $stmt->execute();
                    }
                }
            }
            $error = '4';
        }
    }
}

header("location: doctors.php?action=edit&error=" . $error . "&id=" . $id);
exit;
