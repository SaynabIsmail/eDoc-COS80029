<?php
// encryption.php
// Simple AES-256-CBC encryption helper for storing tokens/secrets at rest.
//
// IMPORTANT: The key below is a placeholder for local dev only.
// In a real deployment, this should come from an environment variable
// or a gitignored config file — never hardcoded/committed.

define('ENCRYPTION_KEY', 'change_this_to_a_real_32_char_key'); // must be 32 bytes for AES-256
define('ENCRYPTION_METHOD', 'AES-256-CBC');

function encryptData($plaintext) {
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $encrypted = openssl_encrypt($plaintext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    // Store IV alongside the encrypted data (needed to decrypt later)
    return base64_encode($iv . $encrypted);
}

function decryptData($encodedData) {
    $data = base64_decode($encodedData);
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}
?>
