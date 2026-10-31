<?php
// Common helpers used by the sign-in and calendar code (config, encryption, HTTP).

if (!defined('EDOC_ROOT')) {
    define('EDOC_ROOT', dirname(__DIR__));
}

function app_config(?string $key = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $file = EDOC_ROOT . '/config.php';
        if (!is_file($file)) {
            $file = EDOC_ROOT . '/config.example.php';
        }
        $cfg = require $file;
    }
    if ($key === null) {
        return $cfg;
    }
    return $cfg[$key] ?? $default;
}

// set the timezone here as well for pages that don't include connection.php
date_default_timezone_set((string)app_config('clinic_timezone', 'Australia/Melbourne'));

function app_url(string $path = ''): string
{
    return rtrim((string)app_config('app_url', ''), '/') . '/' . ltrim($path, '/');
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function edoc_log(string $message): void
{
    $dir = EDOC_ROOT . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    @file_put_contents($dir . '/app.log', '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

/** Show a simple message page (used for sign-in errors) and stop. */
function render_notice_page(string $title, string $messageHtml, string $buttonHref = 'login.php',
                            string $buttonLabel = 'Return to sign in', int $status = 400): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<link rel="stylesheet" href="css/animations.css"><link rel="stylesheet" href="css/main.css">'
        . '<link rel="stylesheet" href="css/login.css"><title>' . e($title) . ' | eDoc</title></head><body><center>'
        . '<div class="container" style="max-width:520px;margin-top:80px">'
        . '<p class="header-text">' . e($title) . '</p>'
        . '<p class="sub-text" style="line-height:1.6">' . $messageHtml . '</p><br>'
        . '<a href="' . e($buttonHref) . '" class="non-style-link">'
        . '<button class="login-btn btn-primary btn" style="width:100%">' . e($buttonLabel) . '</button></a>'
        . '</div></center></body></html>';
    exit;
}

/** Does a column exist? (so pages still work before setup.php has been run) */
function db_has_column(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function db_has_table(mysqli $db, string $table): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// ---------------------------------------------------------------------------
// Encrypting the stored OAuth tokens (AES-256-GCM)
// ---------------------------------------------------------------------------

function crypto_key(): string
{
    $hex = (string)app_config('encryption_key', '');
    if (!preg_match('/^[0-9a-f]{64}$/i', $hex)) {
        throw new RuntimeException('encryption_key missing or invalid in config.php, run setup.php');
    }
    return hex2bin($hex);
}

function encrypt_secret(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return null;
    }
    $iv  = random_bytes(12);
    $tag = '';
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $ct);
}

function decrypt_secret(?string $stored): ?string
{
    if ($stored === null || $stored === '' || strncmp($stored, 'v1:', 3) !== 0) {
        return null;
    }
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', crypto_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? null : $plain;
}

// ---------------------------------------------------------------------------
// Small curl wrapper for the Google / Microsoft APIs
// ---------------------------------------------------------------------------

/**
 * @return array{status:int, body:string, json:mixed}
 */
function http_request(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10, // calendar sync must never hang a booking
    ]);
    if (isset($opts['form'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['form']));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    } elseif (isset($opts['json'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $headers[] = 'Content-Type: application/json';
    }
    if (isset($opts['bearer'])) {
        $headers[] = 'Authorization: Bearer ' . $opts['bearer'];
    }
    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => 0, 'body' => $err, 'json' => null];
    }
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
}
