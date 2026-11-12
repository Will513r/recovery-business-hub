-- RBH v2: second-chance jobs table.
-- Run once in phpMyAdmin on the live RBH database (Will runs it, 2026-11).
-- Safe to run before the v2 code deploys: nothing reads this table yet.

CREATE TABLE IF NOT EXISTS jobs (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(140) NOT NULL,
  business_id     INT NULL,                -- set on approval when the employer email matches a listed business
  employer_name   VARCHAR(100) NOT NULL,
  employer_email  VARCHAR(190) NOT NULL,
  employer_phone  VARCHAR(30)  NOT NULL DEFAULT '',
  title           VARCHAR(100) NOT NULL,
  area            ENUM('cincinnati','dayton','nky') NOT NULL,
  town            VARCHAR(100) NOT NULL DEFAULT '',
  kind            VARCHAR(60)  NOT NULL,
  pay_min         DECIMAL(9,2) NOT NULL,
  pay_max         DECIMAL(9,2) NULL,
  pay_unit        ENUM('hour','year') NOT NULL DEFAULT 'hour',
  shift           VARCHAR(100) NOT NULL DEFAULT '',
  policy          ENUM('no_check','case_by_case') NOT NULL,
  policy_note     VARCHAR(300) NOT NULL DEFAULT '',
  description     TEXT NOT NULL,
  status          ENUM('pending','approved','rejected','closed') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at     DATETIME NULL,
  expires_at      DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jobs_slug (slug),
  KEY idx_jobs_status_area (status, area),
  KEY idx_jobs_business (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
