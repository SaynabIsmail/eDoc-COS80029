<?php
// Second step of the doctor login: ask for the code from the authenticator app (or a backup code).
ob_start();
session_start();

if (empty($_SESSION['pending_2fa_email'])) {
    header("location: login.php");
    exit;
}

if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    exit('Two-factor authentication is not installed yet. Run "composer install" in the edoc folder (see setup.php).');
}
require 'vendor/autoload.php';
include("connection.php");

use PragmaRX\Google2FA\Google2FA;

$email = $_SESSION['pending_2fa_email'];

$stmt = $database->prepare("SELECT docid FROM doctor WHERE docemail=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$docid = (int)($stmt->get_result()->fetch_assoc()['docid'] ?? 0);

if (!$docid) {
    unset($_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_docid']);
    header("location: login.php");
    exit;
}

$stmt = $database->prepare("SELECT secret, enabled FROM doctor_2fa WHERE docid=?");
$stmt->bind_param("i", $docid);
$stmt->execute();
$tfa = $stmt->get_result()->fetch_assoc();

function finish_doctor_login(string $email, bool $needsSetup): void
{
    session_regenerate_id(true);
    unset($_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_docid'], $_SESSION['2fa_attempts']);
    $_SESSION['user'] = $email;
    $_SESSION['usertype'] = 'd';
    if ($needsSetup) {
        $_SESSION['2fa_setup_required'] = true;
        header("location: doctor/enable-2fa.php");
    } else {
        unset($_SESSION['2fa_setup_required']);
        header("location: doctor/index.php");
    }
    exit;
}

// doctor hasn't set up 2FA yet -> they have to do it now
if (!$tfa || (int)$tfa['enabled'] !== 1) {
    finish_doctor_login($email, true);
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;
    if ($_SESSION['2fa_attempts'] > 5) {
        unset($_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_docid'], $_SESSION['2fa_attempts']);
        header("location: login.php");
        exit;
    }

    $code = strtoupper(preg_replace('/\s+/', '', (string)($_POST['code'] ?? '')));

    if (preg_match('/^\d{6}$/', $code)) {
        $google2fa = new Google2FA();
        if ($google2fa->verifyKey($tfa['secret'], $code, 1)) {
            finish_doctor_login($email, false);
        }
    } elseif (preg_match('/^[0-9A-F]{8}$/', $code)) {
        // backup code, each one works once
        $stmt = $database->prepare("SELECT id, code_hash FROM doctor_2fa_backup_codes WHERE docid=? AND used=0");
        $stmt->bind_param("i", $docid);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            if (password_verify($code, $row['code_hash'])) {
                $upd = $database->prepare("UPDATE doctor_2fa_backup_codes SET used=1 WHERE id=?");
                $upd->bind_param("i", $row['id']);
                $upd->execute();
                finish_doctor_login($email, false);
            }
        }
    }
    $error = "That code didn't work. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Sign In | eDoc</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { background: #f5f7fa; font-family: inherit; }
        .totp-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .totp-card { background: #fff; max-width: 440px; width: 100%; padding: 40px; border-radius: 16px; box-shadow: 0 4px 24px rgba(10,118,216,0.10); text-align: center; }
        .totp-card h2 { color: var(--primarycolor); margin-bottom: 6px; font-size: 26px; }
        .totp-sub { color: #666; font-size: 15px; margin-bottom: 22px; }
        .totp-input { padding: 14px; font-size: 20px; text-align: center; letter-spacing: 6px; width: 220px; border: 1px solid #ccc; border-radius: 8px; outline: none; }
        .totp-input:focus { border-color: var(--primarycolor); }
        .totp-submit { width: 220px; margin-top: 18px; border-radius: 8px; background-color: var(--primarycolor); border: none; padding: 12px; color: #fff; font-size: 15px; cursor: pointer; }
        .totp-submit:hover { background-color: var(--primarycolorhover); }
        .error-text { color: #e02424; font-size: 14px; margin-bottom: 12px; }
        .totp-link { display: inline-block; margin-top: 18px; color: var(--primarycolor); font-weight: 600; text-decoration: none; }
        .totp-help { color: #888; font-size: 13px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="totp-wrapper">
    <div class="totp-card">
        <h2>Two-Factor Authentication</h2>
        <p class="totp-sub">Enter the 6-digit code from your authenticator app for<br><b><?php echo htmlspecialchars($email); ?></b></p>
        <?php if ($error != ""): ?><p class="error-text"><?php echo $error; ?></p><?php endif; ?>
        <form method="POST">
            <input type="text" name="code" class="totp-input" maxlength="9" autocomplete="one-time-code" inputmode="text" required autofocus>
            <br>
            <input type="submit" value="Verify" class="totp-submit">
        </form>
        <p class="totp-help">Lost your phone? Enter one of your 8-character backup codes instead.</p>
        <a href="login.php" class="totp-link">Cancel</a>
    </div>
    </div>
</body>
</html>
