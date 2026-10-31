<?php
ob_start();
// Sign in with Google (also asks for calendar access).
// ?connect=1 when the user is already logged in and only wants to connect the calendar.
session_start();
require __DIR__ . '/lib/oauth.php';

$connect = isset($_GET['connect']) && !empty($_SESSION['user']);
oauth_begin('google', $connect ? 'connect' : 'login', $connect);
