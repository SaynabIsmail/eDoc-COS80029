<?php
/**
 * Copy this file to config.microsoft.php and fill in real values.
 * config.microsoft.php is gitignored — never commit real credentials.
 *
 * Get clientId / clientSecret from:
 * Azure Portal -> App registrations -> your app -> Overview / Certificates & secrets
 */
return [
    'clientId'     => 'YOUR_AZURE_APPLICATION_CLIENT_ID',
    'clientSecret' => 'YOUR_AZURE_CLIENT_SECRET',
    'redirectUri'  => 'http://localhost/letsbookit/microsoft-callback.php',
    // 'common' allows both personal Microsoft accounts (outlook.com/hotmail)
    // and work/school (org) accounts to sign in — required for patients + doctors
    'tenant'       => 'common',
];
