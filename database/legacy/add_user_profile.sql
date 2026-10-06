-- ============================================================
-- ADD USER PROFILE — Safe to import any time, no existing data touched
-- ============================================================

USE muscle_recovery_v2;

CREATE TABLE IF NOT EXISTS user_profile (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sex             ENUM('male','female','unspecified') NOT NULL DEFAULT 'unspecified',
    height_cm       DECIMAL(5,1) DEFAULT NULL,
    body_weight_kg  DECIMAL(5,1) NOT NULL,
    activity_level  ENUM('sedentary','lightly_active','moderately_active','very_active','athlete') NOT NULL DEFAULT 'moderately_active',
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;