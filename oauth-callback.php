<?php require 'vendor/autoload.php';
session_start();
include 'connection.php';
$config = require 'config.php';
$provider = new League\OAuth2\Client\Provider\Google(['clientId' => $config['google_client_id'], 'clientSecret' => $config['google_client_secret'], 'redirectUri' => $config['google_redirect_uri'], 'pkceMethod' => 'S256',]);
$provider->setPkceCode($_SESSION['oauth2pkceCode'] ?? '');
if (isset($_GET['error'])) {
    header('Location: login.php');
    exit;
}
if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth2state'] ?? null)) {
    unset($_SESSION['oauth2state']);
    exit('Invalid state, possible CSRF attack.');
}
$token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code'],]);
$googleUser = $provider->getResourceOwner($token);
/** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */ $email = $googleUser->getEmail();
$stmt = $database->prepare("SELECT * FROM webuser WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $usertype = $row['usertype'];
    if ($usertype === 'p') {
        $pstmt = $database->prepare("SELECT google_id, ppassword FROM patient WHERE pemail = ?");
        $pstmt->bind_param("s", $email);
        $pstmt->execute();
        $patientRow = $pstmt->get_result()->fetch_assoc();
        $alreadyLinked = !empty($patientRow['google_id']);
        if (!$alreadyLinked && !empty($patientRow['ppassword'])) {
            $_SESSION['pending_link_email'] = $email;
            $_SESSION['pending_google_id'] = $googleUser->getId();
            header('Location: link-confirm.php');
            exit;
        }
    }
    $_SESSION['user'] = $email;
    $_SESSION['usertype'] = $usertype;
    if ($usertype === 'p') {
        header('Location: patient/index.php');
    } elseif ($usertype === 'd') {
        header('Location: doctor/index.php');
    } elseif ($usertype === 'a') {
        header('Location: admin/index.php');
    }
    exit;
} else {
    $name = $googleUser->getName();
    $googleId = $googleUser->getId();
    $stmt = $database->prepare("INSERT INTO patient (pemail, pname, google_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $name, $googleId);
    $stmt->execute();
    $stmt = $database->prepare("INSERT INTO webuser (email, usertype) VALUES (?, 'p')");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $_SESSION['user'] = $email;
    $_SESSION['usertype'] = 'p';
    header('Location: patient/index.php');
    exit;
}
