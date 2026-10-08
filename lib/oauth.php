<?php
/**
 * Google and Microsoft OAuth (authorization code + PKCE, OpenID Connect).
 * Written in plain PHP so we don't need composer packages on MAMP.
 * The calendar scope is requested at sign-in so bookings can go straight into
 * the user's calendar.
 */

require_once __DIR__ . '/bootstrap.php';

const GOOGLE_CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.events';
const MS_SCOPES = 'openid profile email offline_access User.Read Calendars.ReadWrite';

function oauth_provider(string $provider): array
{
    if ($provider === 'google') {
        return [
            'name'          => 'Google',
            'client_id'     => app_config('google_client_id'),
            'client_secret' => app_config('google_client_secret'),
            'redirect_uri'  => app_url('oauth-callback.php'),
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'scope'         => 'openid email profile ' . GOOGLE_CALENDAR_SCOPE,
            'issuers'       => ['https://accounts.google.com', 'accounts.google.com'],
        ];
    }
    if ($provider === 'microsoft') {
        $tenant = app_config('microsoft_tenant', 'common');
        return [
            'name'          => 'Microsoft',
            'client_id'     => app_config('microsoft_client_id'),
            'client_secret' => app_config('microsoft_client_secret'),
            'redirect_uri'  => app_url('microsoft-callback.php'),
            'authorize_url' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize",
            'token_url'     => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            'scope'         => MS_SCOPES,
            'issuers'       => null, // multi-tenant: issuer contains the user's tenant id, checked by pattern
        ];
    }
    throw new InvalidArgumentException('Unknown provider');
}

function oauth_is_configured(string $provider): bool
{
    $p = oauth_provider($provider);
    return $p['client_id'] && strpos((string)$p['client_id'], 'PASTE_') !== 0;
}

function b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/**
 * Start the redirect to the provider.
 * $mode = 'login'   -> sign in / sign up with this provider
 *         'connect' -> already signed in; only connect this provider's calendar
 */
function oauth_begin(string $provider, string $mode = 'login', bool $forceConsent = false): void
{
    if (!oauth_is_configured($provider)) {
        $name = oauth_provider($provider)['name'];
        $signedIn = !empty($_SESSION['user']);
        $back = !$signedIn ? 'login.php'
            : (($_SESSION['usertype'] ?? '') === 'd' ? 'doctor/index.php' : 'patient/appointment.php');
        // details go to the log, the user just gets a simple message
        edoc_log("$provider sign-in requested but client ID/secret are not set in config.php");
        render_notice_page(
            $name . ' ' . ($mode === 'connect' ? 'calendar' : 'sign-in') . ' unavailable',
            e($name) . ($mode === 'connect' ? ' calendar connection' : ' sign-in')
                . ' is not available at the moment because it has not yet been enabled for this site.<br><br>'
                . ($signedIn ? 'Your account and appointments are not affected.'
                             : 'Please sign in with your email address and password instead.'),
            $back,
            $signedIn ? 'Return to eDoc' : 'Return to sign in',
            503
        );
    }
    $p = oauth_provider($provider);

    $state    = b64url(random_bytes(24));
    $nonce    = b64url(random_bytes(24));
    $verifier = b64url(random_bytes(48));               // PKCE verifier
    $challenge = b64url(hash('sha256', $verifier, true));

    $_SESSION['oauth'][$provider] = [
        'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier,
        'mode' => $mode, 'started' => time(),
    ];

    $params = [
        'client_id'             => $p['client_id'],
        'redirect_uri'          => $p['redirect_uri'],
        'response_type'         => 'code',
        'scope'                 => $p['scope'],
        'state'                 => $state,
        'nonce'                 => $nonce,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ];
    if ($provider === 'google') {
        $params['access_type'] = 'offline';           // ask for a refresh token
        $params['include_granted_scopes'] = 'true';   // incremental authorization
        $params['prompt'] = $forceConsent ? 'consent' : 'select_account';
    } else {
        $params['response_mode'] = 'query';
        $params['prompt'] = $forceConsent ? 'consent' : 'select_account';
    }
    redirect($p['authorize_url'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

/**
 * Handle the provider's redirect back. Returns the verified identity + tokens.
 * Throws RuntimeException with a user-safe message on any failure.
 */
function oauth_complete(string $provider): array
{
    $p = oauth_provider($provider);
    $saved = $_SESSION['oauth'][$provider] ?? null;
    unset($_SESSION['oauth'][$provider]); // single use

    if (isset($_GET['error'])) {
        edoc_log("$provider returned error: " . substr((string)$_GET['error'], 0, 100));
        throw new RuntimeException('Sign-in was cancelled before it was completed. You can try again, or sign in with your email address and password.');
    }
    // state check (CSRF)
    if (!$saved || empty($_GET['state']) || !hash_equals($saved['state'], (string)$_GET['state'])) {
        throw new RuntimeException('This sign-in request is invalid or has expired. Please return to the sign-in page and try again.');
    }
    if (time() - $saved['started'] > 600) {
        throw new RuntimeException('This sign-in request has expired. Please try again.');
    }
    if (empty($_GET['code'])) {
        throw new RuntimeException($p['name'] . ' did not complete the sign-in. Please try again.');
    }

    $res = http_request('POST', $p['token_url'], ['form' => [
        'grant_type'    => 'authorization_code',
        'code'          => $_GET['code'],
        'redirect_uri'  => $p['redirect_uri'],
        'client_id'     => $p['client_id'],
        'client_secret' => $p['client_secret'],
        'code_verifier' => $saved['verifier'],
    ]]);
    $tok = $res['json'];
    if ($res['status'] !== 200 || empty($tok['access_token']) || empty($tok['id_token'])) {
        edoc_log("$provider token exchange failed: HTTP {$res['status']} " . substr($res['body'], 0, 300));
        throw new RuntimeException('We were unable to complete sign-in with ' . $p['name'] . '. Please try again.');
    }

    // Check the ID token claims. We got it directly from the token endpoint over HTTPS,
    // so we don't verify the signature here.
    $claims = oidc_decode($tok['id_token']);
    $issOk = $p['issuers']
        ? in_array($claims['iss'] ?? '', $p['issuers'], true)
        : (bool)preg_match('#^https://login\.microsoftonline\.com/[0-9a-f-]{36}/v2\.0$#', $claims['iss'] ?? '');
    $aud = (array)($claims['aud'] ?? []);
    if (!$issOk || !in_array($p['client_id'], $aud, true) || ($claims['exp'] ?? 0) < time() - 60
        || !hash_equals($saved['nonce'], (string)($claims['nonce'] ?? ''))) {
        edoc_log("$provider ID token rejected: " . json_encode(['iss' => $claims['iss'] ?? null, 'aud' => $aud]));
        throw new RuntimeException('The sign-in response could not be verified. Please try again.');
    }

    $tokens = [
        'access_token'  => $tok['access_token'],
        'refresh_token' => $tok['refresh_token'] ?? null,
        'expires_at'    => time() + (int)($tok['expires_in'] ?? 3600) - 60,
        'scope'         => $tok['scope'] ?? $p['scope'],
    ];

    if ($provider === 'google') {
        return [
            'provider'       => 'google',
            'sub'            => (string)$claims['sub'],
            'email'          => strtolower(trim($claims['email'] ?? '')),
            'email_verified' => !empty($claims['email_verified']) && $claims['email_verified'] !== 'false',
            'name'           => trim($claims['name'] ?? ''),
            'phone'          => null, // google's profile scope doesn't include a phone number
            'tokens'         => $tokens,
            'mode'           => $saved['mode'],
        ];
    }

    // Microsoft: get the profile (incl. phone) from Graph /me
    $me = http_request('GET', 'https://graph.microsoft.com/v1.0/me', ['bearer' => $tok['access_token']]);
    if ($me['status'] !== 200) {
        edoc_log("Graph /me failed: HTTP {$me['status']} " . substr($me['body'], 0, 300));
        throw new RuntimeException('Microsoft did not provide the profile information required to sign you in. Please try again.');
    }
    $u = $me['json'];
    $email = strtolower(trim($u['mail'] ?? $claims['email'] ?? $u['userPrincipalName'] ?? ''));
    return [
        'provider'       => 'microsoft',
        'sub'            => (string)($u['id'] ?? ''),
        'email'          => $email,
        // Microsoft doesn't send email_verified, so treat the Graph mailbox address as verified
        'email_verified' => !empty($u['mail']) || !empty($claims['email']),
        'name'           => trim($u['displayName'] ?? ''),
        'phone'          => $u['mobilePhone'] ?? ($u['businessPhones'][0] ?? null),
        'tokens'         => $tokens,
        'mode'           => $saved['mode'],
    ];
}

function oidc_decode(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return [];
    }
    $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
    return $json ? (json_decode($json, true) ?: []) : [];
}

/** Exchange a refresh token for a new access token. Returns null if revoked/expired. */
function oauth_refresh(string $provider, string $refreshToken): ?array
{
    $p = oauth_provider($provider);
    $form = [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refreshToken,
        'client_id'     => $p['client_id'],
        'client_secret' => $p['client_secret'],
    ];
    if ($provider === 'microsoft') {
        $form['scope'] = MS_SCOPES;
    }
    $res = http_request('POST', $p['token_url'], ['form' => $form]);
    if ($res['status'] !== 200 || empty($res['json']['access_token'])) {
        edoc_log("$provider refresh failed: HTTP {$res['status']} " . substr($res['body'], 0, 300));
        return null;
    }
    return [
        'access_token'  => $res['json']['access_token'],
        // Microsoft rotates refresh tokens; Google usually doesn't return a new one.
        'refresh_token' => $res['json']['refresh_token'] ?? $refreshToken,
        'expires_at'    => time() + (int)($res['json']['expires_in'] ?? 3600) - 60,
    ];
}
