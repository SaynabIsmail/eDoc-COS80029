-- Tables/columns for Google + Microsoft sign-in and calendar sync.
-- setup.php runs this for you. Only adds things, nothing existing is changed.

-- linked Google / Microsoft accounts (matched on the provider's sub, not email)
ALTER TABLE webuser ADD COLUMN auth_provider VARCHAR(100) NOT NULL DEFAULT 'password';
ALTER TABLE webuser ADD COLUMN google_sub VARCHAR(255) NULL;
ALTER TABLE webuser ADD COLUMN microsoft_sub VARCHAR(255) NULL;
ALTER TABLE webuser ADD UNIQUE KEY uq_google_sub (google_sub);
ALTER TABLE webuser ADD UNIQUE KEY uq_microsoft_sub (microsoft_sub);

-- patients created through Google/Microsoft don't have a password
ALTER TABLE patient MODIFY ppassword VARCHAR(255) NULL;

-- encrypted OAuth tokens per user and provider, used to write calendar events
CREATE TABLE IF NOT EXISTS calendar_connections (
  id                INT NOT NULL AUTO_INCREMENT,
  email             VARCHAR(255) NOT NULL,          -- webuser.email (patient or doctor)
  provider          VARCHAR(20)  NOT NULL,          -- 'google' | 'microsoft'
  provider_email    VARCHAR(255) NULL,              -- the Google/Microsoft account it writes to
  access_token_enc  TEXT NULL,
  refresh_token_enc TEXT NULL,
  expires_at        INT NOT NULL DEFAULT 0,         -- unix time
  scope             TEXT NULL,                      -- scopes actually granted
  status            VARCHAR(32) NOT NULL DEFAULT 'active', -- active | expired | no_calendar_permission
  last_error        VARCHAR(500) NULL,
  last_login_at     DATETIME NULL,                  -- most recent sign-in decides which calendar new bookings go to
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_provider (email, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- which calendar event belongs to which appointment (so we can move/delete it later)
CREATE TABLE IF NOT EXISTS appointment_calendar_events (
  id          INT NOT NULL AUTO_INCREMENT,
  appoid      INT NOT NULL,                         -- appointment.appoid
  owner_email VARCHAR(255) NOT NULL,                -- whose calendar (patient or doctor)
  provider    VARCHAR(20)  NOT NULL,
  event_id    VARCHAR(1024) NOT NULL,               -- Google event id / Graph event id
  synced_at   DATETIME NULL,
  last_error  VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_appo_owner (appoid, owner_email),
  KEY idx_owner (owner_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
