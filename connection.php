<?php
// Database connection. Settings come from config.php, otherwise MAMP defaults are used.

$__cfgFile = __DIR__ . '/config.php';
$__cfg = is_file($__cfgFile) ? (require $__cfgFile) : [];
$__db = ($__cfg['db'] ?? []) + [
    'host' => '127.0.0.1', 'port' => 8889, 'user' => 'root', 'password' => 'root', 'name' => 'edoc',
];

// use the clinic's timezone everywhere
date_default_timezone_set($__cfg['clinic_timezone'] ?? 'Australia/Melbourne');

mysqli_report(MYSQLI_REPORT_OFF);
$database = @new mysqli($__db['host'], $__db['user'], $__db['password'], $__db['name'], (int)$__db['port']);
if ($database->connect_error) {
    error_log('eDoc database connection failed: ' . $database->connect_error);
    http_response_code(503);
    die('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Service unavailable</title></head>'
        . '<body style="font-family:Inter,system-ui,sans-serif;background:#f6f8fb;margin:0">'
        . '<div style="max-width:520px;margin:12vh auto;background:#fff;border:1px solid #e6e9ef;border-radius:10px;padding:32px 36px">'
        . '<h2 style="margin:0 0 10px">Service temporarily unavailable</h2>'
        . '<p style="color:#555;line-height:1.6">We are unable to connect to the eDoc database at the moment. '
        . 'Please try again shortly. If the problem persists, the system administrator can run the '
        . '<a href="' . htmlspecialchars(rtrim($__cfg['app_url'] ?? '/edoc', '/')) . '/setup.php">setup check</a> to review the configuration.</p>'
        . '</div></body></html>');
}
$database->set_charset('utf8mb4');
unset($__cfgFile, $__cfg, $__db);
