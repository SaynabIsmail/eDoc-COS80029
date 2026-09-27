<?php
/**
 * Microsoft redirects back here after the user approves/denies sign-in.
 * Three outcomes are handled:
 *   1. Account already linked to this Microsoft identity -> normal login
 *   2. Email exists but isn't linked yet -> send to confirm-link.php
 *      (self-confirmation via existing password, per client requirement)
 *   3. No account exists -> create a new PATIENT account
 *      (doctors are provisioned by admin, not self-registered, so a new
 *      Microsoft sign-in never creates a doctor account)
 */

session_start();

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/connection.php'; // provides $database (mysqli)
$msConfig = require __DIR__ . '/config.microsoft.php';

$provider = new TheNetworg\OAuth2\Client\Provider\Azure([
    'clientId'               => $msConfig['clientId'],
    'clientSecret'           => $msConfig['clientSecret'],
    'redirectUri'            => $msConfig['redirectUri'],
    'defaultEndPointVersion' => '2.0',
    'tenant'                 => $msConfig['tenant'],
    'pkceMethod'             => 'S256',
]);

$provider->setPkceCode($_SESSION['ms_oauth_pkce'] ?? '');

// --- CSRF / state check -----------------------------------------------
if (empty($_GET['state']) || empty($_SESSION['ms_oauth_state']) || $_GET['state'] !== $_SESSION['ms_oauth_state']) {
    unset($_SESSION['ms_oauth_state']);
    http_response_code(400);
    die('Invalid or missing OAuth state. Please return to the login page and try again.');
}
unset($_SESSION['ms_oauth_state']);
unset($_SESSION['ms_oauth_pkce']);

if (empty($_GET['code'])) {
    http_response_code(400);
    die('Microsoft did not return an authorization code. Login was likely cancelled.');
}

// --- Exchange code for token, then fetch profile -----------------------
try {
    $token  = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    $msUser = $provider->get('me', $token);
} catch (Exception $e) {
    http_response_code(502);
    die('Could not complete sign-in with Microsoft. Please try again.');
}

$email  = strtolower(trim($msUser['mail'] ?? $msUser['userPrincipalName'] ?? ''));
$name   = trim($msUser['displayName'] ?? '');
$msSub  = $msUser['id'] ?? '';

if ($email === '' || $msSub === '') {
    http_response_code(502);
    die('Microsoft did not return the required profile information (email/id).');
}

// --- Look up existing account by email ---------------------------------
$stmt = $database->prepare("SELECT email, usertype, microsoft_sub FROM webuser WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

function send_to_dashboard(string $usertype): void
{
    // Doctors must clear the TOTP gate before reaching their dashboard —
    // this hands off to Manoj's 2FA verification page, not the dashboard
    // directly.
    if ($usertype === 'd') {
        header('Location: doctor/verify-2fa.php');
    } else {
        header('Location: patient/index.php');
    }
    exit; // deliberate — see the app-wide missing-exit bug found in audit
}

if ($row) {
    // Admin accounts are explicitly excluded from OAuth (client requirement —
    // risk of admin lockout). This must be checked BEFORE the linked/unlinked
    // branches below, or an admin's email could otherwise be silently linked
    // or logged in via Microsoft. Admins keep using the existing login only.
    if ($row['usertype'] === 'a') {
        http_response_code(403);
        die('Admin accounts cannot sign in via Microsoft. Please use the standard login page.');
    }

    if (!empty($row['microsoft_sub']) && hash_equals($row['microsoft_sub'], $msSub)) {
        // Case 1: already linked -> normal login
        $_SESSION['user']     = $email;
        $_SESSION['usertype'] = $row['usertype'];
        send_to_dashboard($row['usertype']);
    }

    // Case 2: account exists but this Microsoft identity isn't linked yet.
    // Do NOT log them in automatically — require self-confirmation first,
    // per client's answer on account merging.
    $_SESSION['pending_link'] = [
        'email'         => $email,
        'microsoft_sub' => $msSub,
        'usertype'      => $row['usertype'],
    ];
    header('Location: confirm-link.php');
    exit;
}

// Case 3: no account at all -> create a new patient account.
// usertype is hardcoded to 'p' here deliberately; there is no path for a
// brand-new Microsoft sign-in to become a doctor or admin account.
$stmtInsertUser = $database->prepare(
    "INSERT INTO webuser (email, usertype, auth_provider, microsoft_sub) VALUES (?, 'p', 'microsoft', ?)"
);
$stmtInsertUser->bind_param('ss', $email, $msSub);
$stmtInsertUser->execute();

$stmtInsertPatient = $database->prepare(
    "INSERT INTO patient (pemail, pname, ppassword) VALUES (?, ?, NULL)"
);
$stmtInsertPatient->bind_param('ss', $email, $name);
$stmtInsertPatient->execute();
// ppassword is left NULL for OAuth-only accounts — they have no legacy
// password to compare against. Confirm login.php's legacy path treats a
// NULL ppassword as "cannot log in via password", not as a wildcard match.

$_SESSION['user']     = $email;
$_SESSION['usertype'] = 'p';
send_to_dashboard('p');
