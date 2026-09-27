<?php
/**
 * Entry point for "Sign in with Microsoft".
 * Link to this from login.php, e.g.:
 *   <a href="login-microsoft.php">Sign in with Microsoft</a>
 */

session_start();

require __DIR__ . '/vendor/autoload.php';
$msConfig = require __DIR__ . '/config.microsoft.php';

$provider = new TheNetworg\OAuth2\Client\Provider\Azure([
    'clientId'              => $msConfig['clientId'],
    'clientSecret'          => $msConfig['clientSecret'],
    'redirectUri'           => $msConfig['redirectUri'],
    'defaultEndPointVersion' => '2.0',
    'tenant'                => $msConfig['tenant'],
    'pkceMethod'            => 'S256',
]);

$authUrl = $provider->getAuthorizationUrl([
    // openid+profile+email = identity (OIDC). User.Read = basic profile
    // via Microsoft Graph, used for the profile-enrichment requirement.
    'scope' => ['openid', 'profile', 'email', 'User.Read'],
]);

// CSRF protection: the state value is checked against this on callback.
// Without this check, an attacker could trick a victim's browser into
// completing a login the attacker initiated (session fixation via OAuth).
$_SESSION['ms_oauth_state'] = $provider->getState();

// PKCE: the verifier stays server-side; only its SHA-256 hash (the
// "challenge") is sent to Microsoft in the authorization URL above.
// Protects the authorization code from being replayed if intercepted.
$_SESSION['ms_oauth_pkce'] = $provider->getPkceCode();

header('Location: ' . $authUrl);
exit;
