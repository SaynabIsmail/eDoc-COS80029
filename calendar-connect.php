<?php
ob_start();
// Connect (GET ?provider=google|microsoft) or disconnect (POST) a calendar for the logged in user.
session_start();
require __DIR__ . '/connection.php';
require __DIR__ . '/lib/auth.php';

if (empty($_SESSION['user']) || !in_array($_SESSION['usertype'] ?? '', ['p', 'd'], true)) {
    redirect('login.php');
}
$back = $_SESSION['usertype'] === 'd' ? 'doctor/index.php' : 'patient/appointment.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST['disconnect'] ?? '';
    if (csrf_check() && in_array($p, ['google', 'microsoft'], true)) {
        calendar_disconnect($database, $_SESSION['user'], $p);
        redirect($back . '?calendar=disconnected');
    }
    redirect($back);
}

$p = $_GET['provider'] ?? '';
if (!in_array($p, ['google', 'microsoft'], true)) {
    redirect($back);
}
oauth_begin($p, 'connect', $p === 'google');
