<?php require 'vendor/autoload.php';
session_start();
$config = require 'config.php';
$provider = new League\OAuth2\Client\Provider\Google(['clientId' => $config['google_client_id'], 'clientSecret' => $config['google_client_secret'], 'redirectUri' => $config['google_redirect_uri'], 'pkceMethod' => 'S256',]);
$authUrl = $provider->getAuthorizationUrl(['scope' => ['email', 'profile'],]);
$_SESSION['oauth2state'] = $provider->getState();
$_SESSION['oauth2pkceCode'] = $provider->getPkceCode();
header('Location: ' . $authUrl);
exit;
