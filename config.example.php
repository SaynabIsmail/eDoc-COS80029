<?php
/**
 * eDoc local settings.
 * setup.php copies this to config.php and generates the encryption key.
 * Put the Google and Microsoft client IDs/secrets in config.php (it is git-ignored).
 */
return [

    // Where the app lives in the browser (no trailing slash).
    // MAMP default:   http://localhost:8888/edoc
    // XAMPP default:  http://localhost/edoc
    'app_url' => 'http://localhost:8888/edoc',

    // ---- Database (MAMP defaults) ----------------------------------------
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 8889,          // MAMP = 8889, XAMPP = 3306
        'user'     => 'root',
        'password' => 'root',        // MAMP = 'root', XAMPP = ''
        'name'     => 'edoc',
    ],

    // ---- Google ------------------------------------------------------------
    // Google Cloud Console -> APIs & Services -> Credentials -> OAuth client (Web application)
    // Authorised redirect URI must be EXACTLY: <app_url>/oauth-callback.php
    // Also: APIs & Services -> Library -> enable "Google Calendar API".
    'google_client_id'     => 'PASTE_GOOGLE_CLIENT_ID',
    'google_client_secret' => 'PASTE_GOOGLE_CLIENT_SECRET',

    // ---- Microsoft ---------------------------------------------------------
    // Azure Portal -> App registrations -> your app.
    // Authentication -> Web -> Redirect URI: <app_url>/microsoft-callback.php
    // API permissions -> Microsoft Graph -> Delegated: openid, profile, email,
    //   offline_access, User.Read, Calendars.ReadWrite
    'microsoft_client_id'     => 'PASTE_AZURE_APPLICATION_CLIENT_ID',
    'microsoft_client_secret' => 'PASTE_AZURE_CLIENT_SECRET_VALUE',
    'microsoft_tenant'        => 'common', // personal + work/school accounts

    // ---- Calendar sync -----------------------------------------------------
    'clinic_name'          => 'eDoc Clinic',
    'clinic_address'       => 'eDoc Clinic, Melbourne VIC',
    'clinic_timezone'      => 'Australia/Melbourne',
    'appointment_minutes'  => 30,   // length of the calendar event
    'reminder_minutes'     => 60,   // pop-up reminder before the appointment
    'sync_doctor_calendar' => true, // also put bookings in the doctor's calendar if connected

    // 64 hex chars (32 bytes). setup.php generates this. Changing it later makes
    // stored calendar tokens unreadable (users just sign in again).
    'encryption_key' => '',
];
