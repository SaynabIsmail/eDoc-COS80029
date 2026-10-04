<?php
// calendar_tokens.php
// Functions for saving, retrieving, and refreshing Google Calendar
// API tokens. Works with the calendar_tokens table.

require_once 'connection.php';
require_once 'encryption.php';

// Save or update a user's calendar tokens (INSERT ... ON DUPLICATE KEY)
function saveCalendarTokens($database, $email, $usertype, $accessToken, $refreshToken, $expiry) {
    $encryptedAccess = encryptData($accessToken);
    $encryptedRefresh = encryptData($refreshToken);

    $stmt = $database->prepare(
        "INSERT INTO calendar_tokens (email, usertype, access_token, refresh_token, token_expiry)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            access_token = VALUES(access_token),
            refresh_token = VALUES(refresh_token),
            token_expiry = VALUES(token_expiry)"
    );
    $stmt->bind_param("sssss", $email, $usertype, $encryptedAccess, $encryptedRefresh, $expiry);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Get a user's calendar tokens (decrypted, ready to use)
function getCalendarTokens($database, $email) {
    $stmt = $database->prepare(
        "SELECT access_token, refresh_token, token_expiry, calendar_id
         FROM calendar_tokens WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null; // user hasn't connected their calendar
    }

    return [
        'access_token' => decryptData($row['access_token']),
        'refresh_token' => decryptData($row['refresh_token']),
        'token_expiry' => $row['token_expiry'],
        'calendar_id' => $row['calendar_id'],
    ];
}

// Check if the stored access token has expired
function isTokenExpired($tokenExpiry) {
    return strtotime($tokenExpiry) <= time();
}

// Update just the access token + expiry after a refresh
function updateAccessToken($database, $email, $newAccessToken, $newExpiry) {
    $encryptedAccess = encryptData($newAccessToken);
    $stmt = $database->prepare(
        "UPDATE calendar_tokens SET access_token = ?, token_expiry = ? WHERE email = ?"
    );
    $stmt->bind_param("sss", $encryptedAccess, $newExpiry, $email);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
?>
