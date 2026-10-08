<?php
ob_start();
// Microsoft redirects back here after sign-in (same rules as Google, see lib/auth.php).
session_start();
require __DIR__ . '/connection.php';
require __DIR__ . '/lib/auth.php';

federated_signin($database, 'microsoft');
