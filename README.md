## Doctor Two-Factor Authentication (TOTP)

Implements mandatory RFC 6238 Time-based One-Time Password (TOTP) authentication
for doctor accounts, including QR-code enrollment, login-time verification,
one-time backup recovery codes, and admin-side management.

### Setup for integration

1. **Install dependencies**
```bash
   composer install
```
   Pulls in `pragmarx/google2fa` and `endroid/qr-code` (listed in `composer.json`).
   The `vendor/` folder is not committed — regenerate it locally with the command above.

2. **Enable the GD PHP extension**
   Required for QR code image generation. In `php.ini`, uncomment:
```ini
   extension=gd
```
   Then restart Apache.

3. **Import the database tables**
   Run `totp_tables.sql` against your local `edoc` database — creates:
   - `doctor_2fa` — stores each doctor's TOTP secret and enabled/disabled status
   - `doctor_2fa_backup_codes` — one-time recovery codes, stored as hashes

### Files added

| File | Purpose |
|---|---|
| `doctor/enable-2fa.php` | Enrollment — QR code generation and confirmation |
| `verify-2fa.php` | Login-time TOTP/backup code challenge |
| `admin/manage-2fa.php` | Admin: view and disable doctor 2FA |
| `admin/add-doctor.php` | Admin: create new doctor accounts |

### Files modified

| File | Change |
|---|---|
| `login.php` | Doctor login now checks 2FA status and routes to verification, forced setup, or the dashboard accordingly |
| `admin/index.php` | Added sidebar links to the two new admin pages |

### Notes for integration

- Disabling 2FA is admin-only by design — doctors cannot disable it themselves,
  to avoid weakening protection if a session is compromised.
- `login.php` and `admin/index.php` were both modified — please merge carefully
  if your branch also touches these files, rather than overwriting.
- `connection.php` is excluded from this branch (contains a local DB password) —
  use your own local connection settings.
