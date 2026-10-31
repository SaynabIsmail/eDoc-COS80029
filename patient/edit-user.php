<?php
// Saves the logged in patient's details (Settings > Edit).
// Uses the id from the session, not the form. Error codes for settings.php:
// 1 = email already used, 2 = passwords don't match, 3 = invalid input, 4 = saved
ob_start();
session_start();

if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'p') {
    header("location: ../login.php");
    exit;
}
$useremail = $_SESSION['user'];

include("../connection.php");

$stmt = $database->prepare("SELECT pid FROM patient WHERE pemail = ?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$id = (int)($stmt->get_result()->fetch_assoc()['pid'] ?? 0);

$error = '3';
if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim((string)($_POST['name'] ?? ''));
    $nic       = trim((string)($_POST['nic'] ?? ''));
    $address   = trim((string)($_POST['address'] ?? ''));
    $email     = strtolower(trim((string)($_POST['email'] ?? '')));
    $tele      = trim((string)($_POST['Tele'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $cpassword = (string)($_POST['cpassword'] ?? '');

    if ($password !== $cpassword) {
        $error = '2';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '3';
    } else {
        // email already used by someone else?
        $stmt = $database->prepare("SELECT 1 FROM webuser WHERE email = ? AND email <> ?");
        $stmt->bind_param("ss", $email, $useremail);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = '1';
        } else {
            $stmt = $database->prepare(
                "UPDATE patient SET pemail = ?, pname = ?, ppassword = ?, pnic = ?, ptel = ?, paddress = ? WHERE pid = ?"
            );
            $stmt->bind_param("ssssssi", $email, $name, $password, $nic, $tele, $address, $id);
            $stmt->execute();

            if ($email !== $useremail) {
                foreach ([
                    "UPDATE webuser SET email = ? WHERE email = ?",
                    "UPDATE calendar_connections SET email = ? WHERE email = ?",
                    "UPDATE appointment_calendar_events SET owner_email = ? WHERE owner_email = ?",
                ] as $sql) {
                    if ($stmt = $database->prepare($sql)) { // calendar tables exist only after setup.php
                        $stmt->bind_param("ss", $email, $useremail);
                        $stmt->execute();
                    }
                }
                $_SESSION['user'] = $email;
            }
            $error = '4';
        }
    }
}

header("location: settings.php?action=edit&error=" . $error . "&id=" . $id);
exit;
