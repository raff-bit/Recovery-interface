-- ============================================================
-- Migration 001 — auth_attempts (brute-force / spam throttling)
-- ------------------------------------------------------------
-- Records failed logins and registrations so the API can slow
-- down password guessing and mass sign-ups.
-- The email is stored as a SHA-256 hash: we can count attempts
-- per account without keeping a list of typed emails.
-- ============================================================

USE muscle_recovery_v2;

CREATE TABLE IF NOT EXISTS auth_attempts (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    action      ENUM('login_failed','register') NOT NULL,
    ip          VARCHAR(45) NOT NULL,          -- 45 chars fits a full IPv6 address
    email_hash  CHAR(64) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Composite indexes match the exact WHERE clauses used by the throttle
    INDEX idx_attempts_ip    (action, ip, created_at),
    INDEX idx_attempts_email (action, email_hash, created_at)
) ENGINE=InnoDB;