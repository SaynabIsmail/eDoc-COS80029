<?php
ob_start();
// Sign in with Microsoft (personal and work/school accounts), also asks for Outlook calendar access.
// ?connect=1 when the user is already logged in and only wants to connect the calendar.
session_start();
require __DIR__ . '/lib/oauth.php';

$connect = isset($_GET['connect']) && !empty($_SESSION['user']);
oauth_begin('microsoft', $connect ? 'connect' : 'login');
