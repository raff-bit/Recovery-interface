-- ============================================================
-- MUSCLE RECOVERY TRACKER — Master Database Schema
-- Multi-User Architecture with Age-Adjusted Biometric Model
-- Engine: MySQL / MariaDB (InnoDB)
-- ============================================================

CREATE DATABASE IF NOT EXISTS muscle_recovery_v2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE muscle_recovery_v2;

-- ------------------------------------------------------------
-- 1. USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    name                     VARCHAR(100) NOT NULL,
    email                    VARCHAR(191) NOT NULL UNIQUE,
    password_hash            VARCHAR(255) NOT NULL,
    email_verification_token VARCHAR(100) DEFAULT NULL,
    email_verified_at        DATETIME DEFAULT NULL,
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. USER PROFILE (Biometrics)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_profile (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE,
    sex             ENUM('male','female','unspecified') NOT NULL DEFAULT 'unspecified',
    height_cm       DECIMAL(5,1) DEFAULT NULL,
    body_weight_kg  DECIMAL(5,1) NOT NULL,
    age             INT DEFAULT NULL,
    activity_level  ENUM('sedentary','lightly_active','moderately_active','very_active','athlete') NOT NULL DEFAULT 'moderately_active',
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. MUSCLES (17 Tracked Anatomical Groups)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS muscles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    muscle_key      VARCHAR(40)  NOT NULL UNIQUE,
    display_name    VARCHAR(60)  NOT NULL,
    body_region     ENUM('chest','shoulders','arms','back','core','legs') NOT NULL,
    view_side       ENUM('front','back','both') NOT NULL DEFAULT 'front',
    recovery_hours  INT NOT NULL DEFAULT 48,
    sort_order      INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. EXERCISES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exercises (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL UNIQUE,
    equipment       VARCHAR(60)  DEFAULT NULL,
    is_bodyweight   TINYINT(1)   NOT NULL DEFAULT 0,
    created_by_user TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. EXERCISE_MUSCLES (Activation Mapping)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exercise_muscles (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    exercise_id         INT NOT NULL,
    muscle_id           INT NOT NULL,
    activation_weight   DECIMAL(3,2) NOT NULL DEFAULT 0.50,
    role                ENUM('primary','secondary') NOT NULL DEFAULT 'secondary',
    CONSTRAINT fk_em_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_em_muscle   FOREIGN KEY (muscle_id)   REFERENCES muscles(id)   ON DELETE CASCADE,
    UNIQUE KEY unique_exercise_muscle (exercise_id, muscle_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. WORKOUTS (User-Isolated Sessions)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS workouts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    workout_date    DATETIME NOT NULL,
    label           VARCHAR(100) DEFAULT NULL,
    notes           TEXT DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_workouts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_workouts_user (user_id),
    INDEX idx_workouts_date (workout_date)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. WORKOUT_SETS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS workout_sets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    workout_id      INT NOT NULL,
    exercise_id     INT NOT NULL,
    set_number      INT NOT NULL DEFAULT 1,
    reps            INT NOT NULL,
    weight_kg       DECIMAL(6,2) NOT NULL DEFAULT 0,
    rpe             DECIMAL(3,1) DEFAULT NULL,
    CONSTRAINT fk_sets_workout  FOREIGN KEY (workout_id)  REFERENCES workouts(id)  ON DELETE CASCADE,
    CONSTRAINT fk_sets_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE RESTRICT,
    INDEX idx_workout_sets_workout (workout_id),
    INDEX idx_workout_sets_exercise (exercise_id)
) ENGINE=InnoDB;