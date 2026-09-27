<?php
/**
 * Shown when a Microsoft sign-in's email matches an existing account that
 * isn't linked yet. The client confirmed self-confirmation (re-entering
 * the existing password) is sufficient for both patients and doctors —
 * no admin approval step required.
 */

session_start();
require __DIR__ . '/connection.php';

if (!isset($_SESSION['pending_link'])) {
    header('Location: login.php');
    exit;
}

$pending = $_SESSION['pending_link'];
$error   = '';

// Whitelisted, not user-controlled — derived server-side from $pending['usertype'],
// which itself came from a DB lookup, not from request input. Safe to use in
// query text; still cannot be bound as a mysqli parameter (identifiers can't be).
$tableMap = [
    'p' => ['table' => 'patient', 'emailCol' => 'pemail', 'passCol' => 'ppassword'],
    'd' => ['table' => 'doctor',  'emailCol' => 'docemail', 'passCol' => 'docpassword'],
    'a' => ['table' => 'admin',   'emailCol' => 'aemail', 'passCol' => 'apassword'],
];

if (!isset($tableMap[$pending['usertype']])) {
    http_response_code(400);
    die('Unrecognised account type.');
}
['table' => $table, 'emailCol' => $emailCol, 'passCol' => $passCol] = $tableMap[$pending['usertype']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    // NOTE: compares against the legacy plaintext password column, matching
    // the existing (pre-audit) storage format. This is a known limitation
    // inherited from the legacy system, not introduced here — password
    // hashing is tracked separately as part of the broader hardening pass.
    $stmt = $database->prepare("SELECT 1 FROM {$table} WHERE {$emailCol} = ? AND {$passCol} = ?");
    $stmt->bind_param('ss', $pending['email'], $password);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 1) {
        $stmtUpdate = $database->prepare(
            "UPDATE webuser SET microsoft_sub = ?, auth_provider = CONCAT(auth_provider, ',microsoft') WHERE email = ?"
        );
        $stmtUpdate->bind_param('ss', $pending['microsoft_sub'], $pending['email']);
        $stmtUpdate->execute();

        $_SESSION['user']     = $pending['email'];
        $_SESSION['usertype'] = $pending['usertype'];
        unset($_SESSION['pending_link']);

        if ($pending['usertype'] === 'd') {
            header('Location: doctor/verify-2fa.php');
        } else {
            header('Location: patient/index.php');
        }
        exit;
    }

    $error = 'Incorrect password. Please try again, or contact support if you no longer have access.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Link your Microsoft account</title>
</head>
<body>
    <h2>Link your Microsoft account</h2>
    <p>An account already exists for <?php echo htmlspecialchars($pending['email'], ENT_QUOTES, 'UTF-8'); ?>.</p>
    <p>Enter your existing password to confirm this is you and link Microsoft sign-in to this account.</p>

    <?php if ($error): ?>
        <p style="color:#9C3B2E;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="password">Existing password:</label><br>
        <input type="password" id="password" name="password" required autofocus><br><br>
        <button type="submit">Link account</button>
    </form>
</body>
</html>
