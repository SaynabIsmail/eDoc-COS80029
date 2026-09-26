# Patient Google OAuth Login

Implements Google OAuth 2.0 (Authorization Code Flow with PKCE) as an additional
login/signup method for patient accounts, alongside the existing username/password
login, which remains unchanged.

### Setup for integration

1. **Install dependencies**
```bash
composer install
```
Pulls in `league/oauth2-google` (listed in `composer.json`).
The `vendor/` folder is not committed — regenerate it locally with the command above.

2. **Create your own `config.php`**
Not committed (contains credentials). Create it locally with your own Google OAuth
Client ID/Secret from Google Cloud Console:
```php
<?php
return [
    'google_client_id' => 'YOUR_CLIENT_ID',
    'google_client_secret' => 'YOUR_CLIENT_SECRET',
    'google_redirect_uri' => 'http://localhost/<your-folder>/oauth-callback.php',
];
```

3. **Import the database changes**
Run against your local `edoc` database:
```sql
ALTER TABLE patient ADD COLUMN google_id VARCHAR(255) NULL;
ALTER TABLE patient MODIFY ppassword VARCHAR(255) NULL;
```

### Files added

| File | Purpose |
|---|---|
| `google-login.php` | Builds the Google authorization URL (with `state` and PKCE) and redirects |
| `oauth-callback.php` | Receives Google's response, exchanges the code for a token, looks up/creates the patient |
| `link-confirm.php` | Confirmation screen shown when linking Google to an existing password account |
| `link-confirm-action.php` | Handles the Yes/No decision from the confirmation screen |

### Files modified

| File | Change |
|---|---|
| `login.php` | Added a "Sign in with Google" button. No other logic in this file was changed. |

### Notes for integration
- Account linking requires self-confirmation from the user — this was a specific
  client requirement, not an automatic merge.
- `connection.php` and `config.php` are both excluded from this branch (local
  credentials) — use your own local settings for each.
- Known limitation: `patient.pemail` has no unique constraint at the database level,
  and the `patient`/`webuser` inserts aren't wrapped in a transaction — a rare
  interruption between the two could theoretically create a duplicate row. Not yet fixed.
