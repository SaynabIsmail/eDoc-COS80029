-- ============================================================
-- SH1 Schema Extensions: OAuth, TOTP 2FA, Calendar Sync Tokens
-- Author: Saynab (DB/Infra)
-- ============================================================
-- Design notes:
-- - Existing tables (webuser, doctor, appointment, patient) use
--   MyISAM, which does NOT support foreign keys at all. InnoDB
--   tables cannot enforce a FK against a MyISAM table (MySQL
--   error 1824), so these new tables use plain indexed columns
--   (KEY) instead of enforced FOREIGN KEY constraints -- same
--   pattern the existing schema already uses.
-- - Referential integrity for these links is enforced at the
--   application layer instead.
-- - `webuser` (email, usertype) is the existing unified identity
--   table -- 'p' = patient, 'd' = doctor (assumed), 'a' = admin.
--   OAuth accounts link primarily via email against webuser,
--   then resolve to patient.pid or doctor.docid as needed.
-- ============================================================

-- --------------------------------------------------------------
-- 1. OAuth account linking
-- --------------------------------------------------------------
CREATE TABLE `oauth_accounts` (
  `oauth_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,              -- matches webuser.email
  `usertype` char(1) NOT NULL,                -- 'p' or 'd', matches webuser.usertype
  `provider` varchar(20) NOT NULL,            -- e.g. 'google'
  `provider_user_id` varchar(255) NOT NULL,   -- Google's unique account ID (the 'sub' claim)
  `access_token` text DEFAULT NULL,           -- short-lived, encrypted at rest
  `refresh_token` text DEFAULT NULL,          -- long-lived, encrypted at rest
  `token_expiry` datetime DEFAULT NULL,
  `linked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`oauth_id`),
  UNIQUE KEY `provider_account` (`provider`, `provider_user_id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------
-- 2. TOTP secrets (Doctor 2FA)
-- --------------------------------------------------------------
CREATE TABLE `totp_secrets` (
  `totp_id` int NOT NULL AUTO_INCREMENT,
  `docid` int NOT NULL,                       -- references doctor.docid (app-enforced)
  `secret` varchar(255) NOT NULL,             -- encrypted at rest, NEVER logged
  `enrolled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verified` tinyint(1) NOT NULL DEFAULT 0,   -- set true after doctor confirms first code
  PRIMARY KEY (`totp_id`),
  UNIQUE KEY `docid` (`docid`)                -- one secret per doctor
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------
-- 3. Calendar sync tokens
-- --------------------------------------------------------------
CREATE TABLE `calendar_tokens` (
  `token_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,              -- references webuser.email (app-enforced)
  `usertype` char(1) NOT NULL,                -- 'p' or 'd'
  `access_token` text NOT NULL,               -- encrypted at rest, short-lived
  `refresh_token` text NOT NULL,              -- encrypted at rest, long-lived
  `token_expiry` datetime NOT NULL,
  `calendar_id` varchar(255) DEFAULT 'primary',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `email` (`email`)                -- one calendar link per user for now
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------
-- 4. Link appointments to calendar events
-- --------------------------------------------------------------
CREATE TABLE `appointment_calendar_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appoid` int NOT NULL,                      -- references appointment.appoid (app-enforced)
  `google_event_id` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appoid` (`appoid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;