<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'd') {
    header("location: ../login.php");
    exit;
}

require '../vendor/autoload.php';
include("../connection.php");

use PragmaRX\Google2FA\Google2FA;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$google2fa = new Google2FA();
$useremail = $_SESSION["user"];

$stmt = $database->prepare("SELECT docid FROM doctor WHERE docemail=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$docrow = $stmt->get_result()->fetch_assoc();
$docid = $docrow["docid"];

$error = "";
$success = "";
$backup_codes_plain = [];

if ($_POST && isset($_POST['confirm_code'])) {
    $check = $database->prepare("SELECT secret FROM doctor_2fa WHERE docid=?");
    $check->bind_param("i", $docid);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();

    if ($row && $google2fa->verifyKey($row['secret'], $_POST['confirm_code'])) {
        $update = $database->prepare("UPDATE doctor_2fa SET enabled=1 WHERE docid=?");
        $update->bind_param("i", $docid);
        $update->execute();

        $del = $database->prepare("DELETE FROM doctor_2fa_backup_codes WHERE docid=?");
        $del->bind_param("i", $docid);
        $del->execute();

        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(bin2hex(random_bytes(4)));
            $backup_codes_plain[] = $plain;
            $hash = password_hash($plain, PASSWORD_DEFAULT);
            $ins = $database->prepare("INSERT INTO doctor_2fa_backup_codes (docid, code_hash, used) VALUES (?, ?, 0)");
            $ins->bind_param("is", $docid, $hash);
            $ins->execute();
        }

        $success = "Two-factor authentication is now enabled.";
    } else {
        $error = "That code didn't match. Please try again.";
    }
}

$check = $database->prepare("SELECT secret, enabled FROM doctor_2fa WHERE docid=?");
$check->bind_param("i", $docid);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if (!$existing) {
    $secret = $google2fa->generateSecretKey();
    $insert = $database->prepare("INSERT INTO doctor_2fa (docid, secret, enabled) VALUES (?, ?, 0)");
    $insert->bind_param("is", $docid, $secret);
    $insert->execute();
} else {
    $secret = $existing['secret'];
}

$already_enabled = $existing && $existing['enabled'] == 1;

$qrCodeUrl = $google2fa->getQRCodeUrl('EDoc', $useremail, $secret);
$qrCode = QrCode::create($qrCodeUrl)->setSize(220);
$writer = new PngWriter();
$result = $writer->write($qrCode);
$qrDataUri = $result->getDataUri();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enable Two-Factor Authentication</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        body { background: #f5f7fa; font-family: inherit; }
        .totp-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .totp-card {
            background: #fff;
            max-width: 460px;
            width: 100%;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(10,118,216,0.10);
            text-align: center;
        }
        .totp-badge {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #eaf3fd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }
        .totp-badge img { width: 34px; height: 34px; }
        .totp-card h2 { color: var(--primarycolor); margin-bottom: 6px; font-size: 26px; }
        .totp-sub { color: #666; font-size: 15px; margin-bottom: 24px; }
        .qr-frame {
            display: inline-block;
            padding: 14px;
            background: #fff;
            border: 2px solid #eaf3fd;
            border-radius: 14px;
            margin: 4px 0 20px;
            position: relative;
        }
        .qr-frame::before, .qr-frame::after {
            content: "";
            position: absolute;
            width: 18px;
            height: 18px;
            border-color: var(--primarycolor);
        }
        .qr-frame::before { top: -3px; left: -3px; border-top: 3px solid; border-left: 3px solid; border-radius: 6px 0 0 0; }
        .qr-frame::after { bottom: -3px; right: -3px; border-bottom: 3px solid; border-right: 3px solid; border-radius: 0 0 6px 0; }
        .qr-frame img { display: block; border-radius: 6px; }
        .totp-manual { background: #f5f9fe; border: 1px dashed #b9d8f5; border-radius: 8px; padding: 10px; font-family: monospace; font-size: 15px; color: #333; margin-bottom: 20px; }
        .totp-input {
            padding: 14px;
            font-size: 20px;
            text-align: center;
            letter-spacing: 6px;
            width: 220px;
            border: 1px solid #ccc;
            border-radius: 8px;
            outline: none;
        }
        .totp-input:focus { border-color: var(--primarycolor); }
        .totp-submit {
            width: 220px;
            margin-top: 18px;
            border-radius: 8px;
            background-color: var(--primarycolor);
            border: none;
            padding: 12px;
            color: #fff;
            font-size: 15px;
            cursor: pointer;
        }
        .totp-submit:hover { background-color: var(--primarycolorhover); }
        .error-text { color: #e02424; font-size: 14px; margin-bottom: 12px; }
        .success-text { color: #2f9e44; font-size: 16px; font-weight: 600; margin-bottom: 16px; }
        .backup-codes-box {
            background: #f5f9fe;
            border-radius: 10px;
            padding: 18px;
            font-family: monospace;
            font-size: 16px;
            text-align: left;
            display: inline-block;
            margin: 12px 0;
            letter-spacing: 1px;
            line-height: 1.8;
        }
        .totp-link { display: inline-block; margin-top: 18px; color: var(--primarycolor); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="totp-wrapper">
    <div class="totp-card">
        <div class="totp-badge">
            <img src="../img/icons/doctors-hover.svg" alt="">
        </div>
        <h2>Two-Factor Authentication</h2>

        <?php if ($already_enabled && $success == ""): ?>
            <p class="success-text">2FA is already enabled on your account.</p>
            <a href="index.php" class="totp-link">Return to dashboard</a>
        <?php elseif ($success != ""): ?>
            <p class="success-text"><?php echo $success; ?></p>
            <?php if (!empty($backup_codes_plain)): ?>
                <p class="totp-sub"><b>Save these backup codes somewhere safe.</b><br>Each one can be used once. They will not be shown again.</p>
                <div class="backup-codes-box">
                    <?php foreach ($backup_codes_plain as $code): ?>
                        <?php echo $code; ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <br>
            <a href="index.php" class="totp-link">Return to dashboard</a>
        <?php else: ?>
            <p class="totp-sub">Scan this code with Google Authenticator, Microsoft Authenticator, or Authy</p>
            <div class="qr-frame">
                <img src="<?php echo $qrDataUri; ?>" alt="2FA QR code" width="200">
            </div>
            <div class="totp-manual">Can't scan? Enter manually:<br><?php echo chunk_split($secret, 4, ' '); ?></div>
            <?php if ($error != ""): ?><p class="error-text"><?php echo $error; ?></p><?php endif; ?>
            <form method="POST">
                <p class="totp-sub" style="margin-bottom:10px;">Enter the 6-digit code from your app</p>
                <input type="text" name="confirm_code" class="totp-input" maxlength="6" required autofocus>
                <br>
                <input type="submit" value="Confirm & Enable" class="totp-submit">
            </form>
        <?php endif; ?>
    </div>
    </div>
</body>
</html>