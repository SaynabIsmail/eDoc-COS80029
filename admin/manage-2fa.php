<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit;
}

include("../connection.php");

$success = "";

if ($_POST && isset($_POST['disable_docid'])) {
    $docid = intval($_POST['disable_docid']);

    $update = $database->prepare("UPDATE doctor_2fa SET enabled=0 WHERE docid=?");
    $update->bind_param("i", $docid);
    $update->execute();

    $del = $database->prepare("DELETE FROM doctor_2fa_backup_codes WHERE docid=?");
    $del->bind_param("i", $docid);
    $del->execute();

    $success = "2FA has been disabled for that doctor.";
}

$list = $database->query("
    SELECT doctor.docid, doctor.docname, doctor.docemail,
           COALESCE(doctor_2fa.enabled, 0) AS tfa_enabled
    FROM doctor
    LEFT JOIN doctor_2fa ON doctor.docid = doctor_2fa.docid
    ORDER BY doctor.docname
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Doctor 2FA</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .tfa-table { width: 90%; margin: 40px auto; border-collapse: collapse; }
        .tfa-table th, .tfa-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .status-on { color: #2f9e44; font-weight: bold; }
        .status-off { color: #888; }
        .disable-btn { background: #e02424; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; }
        .success-text { color: #2f9e44; text-align: center; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Doctor Two-Factor Authentication Status</h2>
    <?php if ($success != ""): ?><p class="success-text"><?php echo $success; ?></p><?php endif; ?>

    <table class="tfa-table">
        <tr>
            <th>Doctor</th>
            <th>Email</th>
            <th>2FA Status</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $list->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['docname']); ?></td>
            <td><?php echo htmlspecialchars($row['docemail']); ?></td>
            <td>
                <?php if ($row['tfa_enabled'] == 1): ?>
                    <span class="status-on">Enabled</span>
                <?php else: ?>
                    <span class="status-off">Not enabled</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($row['tfa_enabled'] == 1): ?>
                <form method="POST" onsubmit="return confirm('Disable 2FA for this doctor?');">
                    <input type="hidden" name="disable_docid" value="<?php echo $row['docid']; ?>">
                    <button type="submit" class="disable-btn">Disable</button>
                </form>
                <?php else: ?>
                    &mdash;
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>