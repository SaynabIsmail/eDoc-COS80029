<?php
ob_start();
// Move a booking to another session with the same doctor.
// We update the same appointment row, so the existing calendar event gets moved too.
session_start();
if (empty($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'p') {
    header("location: ../login.php");
    exit;
}
$useremail = $_SESSION['user'];

include("../connection.php");
require_once __DIR__ . '/../lib/auth.php';

$stmt = $database->prepare("SELECT pid, pname FROM patient WHERE pemail = ?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userfetch = $stmt->get_result()->fetch_assoc();
$userid = (int)$userfetch["pid"];
$username = $userfetch["pname"];

$appoid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$appt = calendar_appointment($database, $appoid);
if (!$appt || (int)$appt['pid'] !== $userid) {
    header("location: appointment.php");
    exit;
}

// upcoming sessions for the same doctor that still have space
function free_sessions(mysqli $db, array $appt): array
{
    $stmt = $db->prepare(
        "SELECT s.scheduleid, s.title, s.scheduledate, s.scheduletime, s.nop,
                (SELECT COUNT(*) FROM appointment x WHERE x.scheduleid = s.scheduleid) AS booked
           FROM schedule s
          WHERE s.docid = ? AND s.scheduleid <> ? AND s.scheduledate >= CURDATE()
          ORDER BY s.scheduledate, s.scheduletime"
    );
    $docid = (string)$appt['docid'];
    $current = (int)$appt['scheduleid'];
    $stmt->bind_param("si", $docid, $current);
    $stmt->execute();
    return array_values(array_filter($stmt->get_result()->fetch_all(MYSQLI_ASSOC),
        fn($s) => $s['nop'] === null || (int)$s['booked'] < (int)$s['nop']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newId = (int)($_POST['scheduleid'] ?? 0);
    $allowed = array_column(free_sessions($database, $appt), null, 'scheduleid');
    if (!csrf_check()) {
        $error = 'Your session has expired. Please try again.';
    } elseif (!isset($allowed[$newId])) {
        $error = 'The selected session is no longer available. Please choose another session.';
    } else {
        $apponum = (int)$allowed[$newId]['booked'] + 1;
        $stmt = $database->prepare("UPDATE appointment SET scheduleid = ?, apponum = ? WHERE appoid = ? AND pid = ?");
        $stmt->bind_param("iiii", $newId, $apponum, $appoid, $userid);
        $stmt->execute();

        $sync = calendar_sync_appointment($database, $appoid); // moves the same event
        header("location: appointment.php?action=rescheduled&id=" . $apponum . "&appoid=" . $appoid
            . "&sync=" . $sync['status'] . "&provider=" . ($sync['provider'] ?? ''));
        exit;
    }
}
$sessions = free_sessions($database, $appt);
$synced = calendar_synced_map($database, $useremail);
date_default_timezone_set('Australia/Melbourne');
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <title>Reschedule Appointment | eDoc</title>
    <style>
        .sub-table{ animation: transitionIn-Y-bottom 0.5s; }
        .resched-card{ border:1px solid #ebebeb; border-radius:8px; padding:18px 22px; margin:10px 0; display:flex; justify-content:space-between; align-items:center; }
        .resched-current{ background:#f5f9ff; border-color:#cfe3fb; }
        .muted{ color:rgb(119,119,119); font-size:14px; }
    </style>
</head>
<body>
<div class="container">
    <div class="menu">
        <table class="menu-container" border="0">
            <tr>
                <td style="padding:10px" colspan="2">
                    <table border="0" class="profile-container">
                        <tr>
                            <td width="30%" style="padding-left:20px"><img src="../img/user.png" alt="" width="100%" style="border-radius:50%"></td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title"><?php echo e((string)$username); ?></p>
                                <p class="profile-subtitle"><?php echo e(substr((string)$useremail, 0, 22)); ?></p>
                            </td>
                        </tr>
                        <tr><td colspan="2"><a href="../logout.php"><input type="button" value="Sign out" class="logout-btn btn-primary-soft btn"></a></td></tr>
                    </table>
                </td>
            </tr>
            <tr class="menu-row"><td class="menu-btn menu-icon-home"><a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></div></a></td></tr>
            <tr class="menu-row"><td class="menu-btn menu-icon-doctor"><a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Find a Doctor</p></div></a></td></tr>
            <tr class="menu-row"><td class="menu-btn menu-icon-session"><a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Available Sessions</p></div></a></td></tr>
            <tr class="menu-row"><td class="menu-btn menu-icon-appoinment menu-active menu-icon-appoinment-active"><a href="appointment.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">My Appointments</p></div></a></td></tr>
            <tr class="menu-row"><td class="menu-btn menu-icon-settings"><a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Settings</p></div></a></td></tr>
        </table>
    </div>
    <div class="dash-body">
        <table border="0" width="100%" style="border-spacing:0;margin:0;padding:0;margin-top:25px;">
            <tr>
                <td width="13%"><a href="appointment.php"><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a></td>
                <td><p style="font-size:23px;padding-left:12px;font-weight:600;">Reschedule appointment</p></td>
                <td width="15%">
                    <p style="font-size:14px;color:rgb(119,119,119);padding:0;margin:0;text-align:right;">Today's date</p>
                    <p class="heading-sub12" style="padding:0;margin:0;"><?php echo $today; ?></p>
                </td>
                <td width="10%"><button class="btn-label" style="display:flex;justify-content:center;align-items:center;"><img src="../img/calendar.svg" width="100%"></button></td>
            </tr>
            <tr>
                <td colspan="4">
                    <center>
                    <div style="width:93%;text-align:left;margin-top:20px" class="sub-table">
                        <div class="resched-card resched-current">
                            <div>
                                <div class="muted">Current appointment &middot; Reference OC-000-<?php echo (int)$appt['appoid']; ?></div>
                                <div class="h1-search" style="margin:4px 0"><?php echo e($appt['title']); ?></div>
                                <div><?php echo e($appt['docname']); ?> &middot; <?php echo e($appt['scheduledate']); ?> at <b><?php echo e(substr((string)$appt['scheduletime'], 0, 5)); ?></b> &middot; Appointment no. <?php echo sprintf('%02d', (int)$appt['apponum']); ?></div>
                            </div>
                            <div class="muted" style="text-align:right">
                                <?php if (isset($synced[(int)$appt['appoid']])): ?>
                                    Synced to your <?php echo e(provider_label($synced[(int)$appt['appoid']])); ?>.<br>The calendar event will be updated automatically.
                                <?php else: ?>
                                    Not synced to a calendar
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($error): ?><p style="color:rgb(255,62,62)"><?php echo e($error); ?></p><?php endif; ?>

                        <p class="heading-main12" style="font-size:18px;color:rgb(49,49,49);margin-top:25px">Select a new session with <?php echo e($appt['docname']); ?></p>

                        <?php if (!$sessions): ?>
                            <p class="muted">There are currently no other upcoming sessions with availability for this doctor.
                                You can <a href="schedule.php">browse all available sessions</a> and make a new booking instead.</p>
                        <?php endif; ?>

                        <?php foreach ($sessions as $s): ?>
                            <form method="POST" class="resched-card">
                                <div>
                                    <div class="h3-search" style="margin:0"><?php echo e($s['title']); ?></div>
                                    <div><?php echo e($s['scheduledate']); ?> at <b><?php echo e(substr((string)$s['scheduletime'], 0, 5)); ?></b>
                                        <span class="muted">&middot; Your appointment number would be <?php echo sprintf('%02d', (int)$s['booked'] + 1); ?></span></div>
                                </div>
                                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$appoid; ?>">
                                <input type="hidden" name="scheduleid" value="<?php echo (int)$s['scheduleid']; ?>">
                                <input type="submit" value="Select this session" class="login-btn btn-primary btn" style="padding:10px 25px">
                            </form>
                        <?php endforeach; ?>
                    </div>
                    </center>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
