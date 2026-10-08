<?php
ob_start();
// Google redirects back here after sign-in.
session_start();
require __DIR__ . '/connection.php';
require __DIR__ . '/lib/auth.php';

federated_signin($database, 'google');
