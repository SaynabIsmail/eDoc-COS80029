<?php session_start();
include 'connection.php';
if (empty($_SESSION['pending_link_email']) || empty($_SESSION['pending_google_id'])) {
    header('Location: login.php');
    exit;
}
$email = $_SESSION['pending_link_email'];
$googleId = $_SESSION['pending_google_id'];
if (($_GET['confirm'] ?? '') === 'yes') {
    $stmt = $database->prepare("UPDATE patient SET google_id = ? WHERE pemail = ?");
    $stmt->bind_param("ss", $googleId, $email);
    $stmt->execute();
    unset($_SESSION['pending_link_email'], $_SESSION['pending_google_id']);
    $_SESSION['user'] = $email;
    $_SESSION['usertype'] = 'p';
    header('Location: patient/index.php');
    exit;
} else {
    unset($_SESSION['pending_link_email'], $_SESSION['pending_google_id']);
    header('Location: login.php');
    exit;
}
