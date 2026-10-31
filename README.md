# eDoc-COS80029

eDoc doctor appointment system with Google / Microsoft sign-in and calendar sync.

Based on [eDoc](https://github.com/HashenUdara/edoc-doctor-appointment-system) by Hashen Udara (MIT License, see `LICENSE`). The sign-in, 2FA and calendar features were added by the COS80029 SH1 team.

## Google / Microsoft sign-in (Ajula, Abby)

Patients can sign up or sign in with Google or Microsoft. The original email and password login still works.

- OAuth 2.0 Authorization Code flow with PKCE (S256), a `state` check (CSRF) and OpenID Connect ID token checks (issuer, audience, expiry, nonce)
- New user: a patient account is created from the provider's name and email
- Returning user: matched on the provider's account ID (`webuser.google_sub` / `microsoft_sub`), not on email
- Email already registered with a password: `confirm-link.php` asks for the current eDoc password before linking (5 attempts max)
- Admin accounts can't use Google / Microsoft sign-in; doctors go through 2FA after signing in
- The phone number is filled from the provider's profile when available
- Sign-in errors are shown as a simple message and logged in `logs/app.log`

Main files:

| File | |
|---|---|
| `lib/oauth.php` | authorization request, token exchange, ID token checks, profile lookup |
| `lib/auth.php` | log in / link / create account, then complete the login |
| `google-login.php`, `oauth-callback.php` | Google start and callback |
| `login-microsoft.php`, `microsoft-callback.php` | Microsoft start and callback |
| `confirm-link.php` | password confirmation before linking an existing account |
| `sql/oauth-calendar-migration.sql` | `webuser.auth_provider`, `google_sub`, `microsoft_sub` |

## Calendar sync (Amar)

When a patient books, reschedules or cancels an appointment, the event is added, moved or
removed in their calendar automatically.

- Signed in with Google: the event goes into their Google Calendar
- Signed in with Microsoft: the event goes into their Outlook calendar
- Password users: can connect Google / Outlook from My Appointments, download an `.ics` file,
  or subscribe to their personal calendar feed (Apple Calendar etc.)
- Doctors who signed in with Google or Microsoft also get the bookings in their calendar
- Admin / doctor deletes (appointments, sessions, accounts) remove the events too
- If Google or Microsoft can't be reached, the booking is still saved and the error is logged in `logs/app.log`

Main files:

| File | |
|---|---|
| `lib/calendar.php` | sync logic (Google Calendar API, Microsoft Graph, .ics) |
| `calendar-connect.php` | connect / disconnect a calendar |
| `calendar-feed.php` | subscription feed |
| `patient/calendar-ics.php` | .ics download |
| `patient/booking-complete.php`, `patient/reschedule.php`, `patient/delete-appointment.php` | book / reschedule / cancel + sync |
| `sql/oauth-calendar-migration.sql` | extra tables (`calendar_connections`, `appointment_calendar_events`) |

## Doctor two-factor authentication (Manoj)

Doctors have to use an authenticator app (Google Authenticator, Microsoft Authenticator, Authy).

- First login: the doctor scans a QR code and confirms a 6-digit code, then gets 8 one-time backup codes
- Every login after that (password, Google or Microsoft): `verify-2fa.php` asks for the 6-digit code or a backup code
- Until 2FA is set up, every doctor page sends them back to `doctor/enable-2fa.php`
- Admin: `admin/manage-2fa.php` shows who has 2FA and can reset it, `admin/add-doctor.php` creates a doctor with a temporary password
- Needs the composer packages (`composer install`) and the PHP `gd` extension

## Running it locally (MAMP)

1. Put the project in `/Applications/MAMP/htdocs/edoc` and start MAMP.
2. Run `composer install` in the folder, then open `http://localhost:8888/edoc/setup.php`. It creates `config.php`, the database and the extra tables.
3. Add the Google and Microsoft client ID / secret to `config.php`.
4. Redirect URIs to register:
   - Google: `http://localhost:8888/edoc/oauth-callback.php` (also enable Google Calendar API and add test users)
   - Microsoft: `http://localhost:8888/edoc/microsoft-callback.php` (Web platform, with `offline_access` and `Calendars.ReadWrite`)
5. Log in at `http://localhost:8888/edoc/login.php` (demo accounts: `patient@edoc.com`, `doctor@edoc.com`, `admin@edoc.com`, password `123`).

`config.php` is in `.gitignore`, don't commit it.

## Running it locally (XAMPP)

Same steps as MAMP, with these differences:

1. Put the project in `C:\xampp\htdocs\edoc` and start Apache and MySQL in XAMPP.
2. Enable `extension=gd` in `php.ini` (XAMPP Control Panel → Apache → Config → PHP (php.ini)) and restart Apache.
3. Run `composer install`, then open `http://localhost/edoc/setup.php`.
4. In `config.php` set the database to port `3306`, user `root`, password `''` (empty).
5. Redirect URIs to register:
   - Google: `http://localhost/edoc/oauth-callback.php`
   - Microsoft: `http://localhost/edoc/microsoft-callback.php`
6. In Google Cloud also enable the **Google People API** (used for the profile phone number).
