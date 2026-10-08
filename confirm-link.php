<?php
ob_start();
// Shown when a Google/Microsoft email already has an eDoc account that isn't linked.
// The user has to enter their existing password before we link the two.
session_start();
require __DIR__ . '/connection.php';
require __DIR__ . '/lib/auth.php';

$pending = $_SESSION['pending_link'] ?? null;
if (!$pending || time() - $pending['created'] > 600) {
    unset($_SESSION['pending_link']);
    redirect('login.php');
}

$providerName = oauth_provider($pending['provider'])['name'];

// Identifiers come from a server-side whitelist keyed on the DB usertype, not from input.
$tableMap = [
    'p' => ['table' => 'patient', 'emailCol' => 'pemail',   'passCol' => 'ppassword'],
    'd' => ['table' => 'doctor',  'emailCol' => 'docemail', 'passCol' => 'docpassword'],
];
if (!isset($tableMap[$pending['usertype']])) {
    unset($_SESSION['pending_link']);
    oauth_fail('This type of account cannot be linked to an external sign-in provider.');
}
['table' => $table, 'emailCol' => $emailCol, 'passCol' => $passCol] = $tableMap[$pending['usertype']];

// Accounts created with the other provider have no password to confirm with.
$stmt = $database->prepare("SELECT {$passCol} AS pw FROM {$table} WHERE {$emailCol} = ?");
$stmt->bind_param('s', $pending['email']);
$stmt->execute();
$hasPassword = (string)($stmt->get_result()->fetch_assoc()['pw'] ?? '') !== '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel'])) {
        unset($_SESSION['pending_link']);
        redirect('login.php');
    }
    if (!csrf_check()) {
        $error = 'Your session has expired. Please try again.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        // passwords are still stored as plain text in the original schema
        $stmt = $database->prepare("SELECT 1 FROM {$table} WHERE {$emailCol} = ? AND {$passCol} = ? AND {$passCol} <> ''");
        $stmt->bind_param('ss', $pending['email'], $password);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 1) {
            $col = provider_sub_column($pending['provider']);
            $prov = $pending['provider'];
            $stmt = $database->prepare(
                "UPDATE webuser SET {$col} = ?,
                        auth_provider = IF(FIND_IN_SET(?, auth_provider), auth_provider, CONCAT_WS(',', NULLIF(auth_provider, ''), ?))
                  WHERE email = ?"
            );
            $stmt->bind_param('ssss', $pending['sub'], $prov, $prov, $pending['email']);
            $stmt->execute();

            unset($_SESSION['pending_link']);
            complete_login($database, $pending['email'], $pending['usertype'], $pending['provider'], $pending['identity']);
        }
        $error = 'The password you entered is incorrect. Please try again, or contact the clinic if you no longer have access to your account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/animations.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/login.css">
    <title>Link your <?php echo e($providerName); ?> account | eDoc</title>
</head>
<body>
<center>
    <div class="container" style="max-width:520px;margin-top:80px">
        <p class="header-text">Link your <?php echo e($providerName); ?> account</p>
        <p class="sub-text">
            An eDoc account is already registered to <b><?php echo e($pending['email']); ?></b>.
            <?php if ($hasPassword): ?>
                To protect your account, please enter your current eDoc password to confirm your identity.
                Once linked, you can sign in with either your password or <?php echo e($providerName); ?>,
                and your appointments will be added to your <?php echo e(provider_label($pending['provider'])); ?> automatically.
            <?php else: ?>
                This account was created using a different sign-in provider and does not have a password.
                Please sign in using the method you originally registered with. You can then connect your calendar
                from <b>My Appointments</b>.
            <?php endif; ?>
        </p>
        <?php if ($error): ?>
            <p class="form-label" style="color:rgb(255,62,62)"><?php echo e($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <?php if ($hasPassword): ?>
                <input type="password" name="password" class="input-text" placeholder="Current eDoc password" required autofocus style="width:100%"><br><br>
                <input type="submit" value="Confirm and link account" class="login-btn btn-primary btn" style="width:100%;margin-bottom:10px">
            <?php endif; ?>
        </form>
        <form method="POST" action="">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="submit" name="cancel" value="Cancel" class="login-btn btn-primary-soft btn" style="width:100%">
        </form>
    </div>
</center>
</body>
</html>
