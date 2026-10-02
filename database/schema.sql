-- ============================================================
-- MUSCLE RECOVERY TRACKER — Database Schema
-- Engine: MySQL / MariaDB (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS muscle_recovery_v2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE muscle_recovery_v2;

-- ------------------------------------------------------------
-- MUSCLES
-- The 17 tracked muscle groups. recovery_hours is the amount of
-- time (from a hard session) after which the muscle is treated
-- as ~95% recovered. Bigger/more complex muscles recover slower.
-- ------------------------------------------------------------
CREATE TABLE muscles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    muscle_key      VARCHAR(40)  NOT NULL UNIQUE,   -- machine key, matches SVG element ids
    display_name    VARCHAR(60)  NOT NULL,
    body_region     ENUM('chest','shoulders','arms','back','core','legs') NOT NULL,
    view_side       ENUM('front','back','both') NOT NULL DEFAULT 'front',
    recovery_hours  INT NOT NULL DEFAULT 48,
    sort_order      INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- EXERCISES
-- A library of exercises. Each exercise can hit several muscles
-- at different activation levels (see exercise_muscles).
-- ------------------------------------------------------------
CREATE TABLE exercises (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL UNIQUE,
    equipment       VARCHAR(60)  DEFAULT NULL,      -- e.g. 'Barbell', 'Bodyweight', 'Dumbbell'
    is_bodyweight   TINYINT(1)   NOT NULL DEFAULT 0,
    created_by_user TINYINT(1)   NOT NULL DEFAULT 0  -- 0 = seeded, 1 = user-added
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- EXERCISE_MUSCLES
-- Junction table: how much a given exercise activates a muscle.
-- activation_weight 0.0–1.0 (1.0 = prime mover, ~0.3-0.6 = assisting)
-- ------------------------------------------------------------
CREATE TABLE exercise_muscles (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    exercise_id         INT NOT NULL,
    muscle_id           INT NOT NULL,
    activation_weight   DECIMAL(3,2) NOT NULL DEFAULT 0.50,
    role                ENUM('primary','secondary') NOT NULL DEFAULT 'secondary',
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    FOREIGN KEY (muscle_id)   REFERENCES muscles(id)   ON DELETE CASCADE,
    UNIQUE KEY unique_exercise_muscle (exercise_id, muscle_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- WORKOUTS
-- One row per logged session.
-- ------------------------------------------------------------
CREATE TABLE workouts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    workout_date    DATETIME NOT NULL,
    label           VARCHAR(100) DEFAULT NULL,       -- e.g. "Push Day", "Leg Day"
    notes           TEXT DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- WORKOUT_SETS
-- Full exercise log: one row per set performed.
-- ------------------------------------------------------------
CREATE TABLE workout_sets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    workout_id      INT NOT NULL,
    exercise_id     INT NOT NULL,
    set_number      INT NOT NULL DEFAULT 1,
    reps            INT NOT NULL,
    weight_kg       DECIMAL(6,2) NOT NULL DEFAULT 0,  -- 0 for bodyweight-only sets
    rpe             DECIMAL(3,1) DEFAULT NULL,         -- perceived effort 1-10, optional
    FOREIGN KEY (workout_id)  REFERENCES workouts(id)  ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_workout_sets_workout ON workout_sets(workout_id);
CREATE INDEX idx_workout_sets_exercise ON workout_sets(exercise_id);
CREATE INDEX idx_workouts_date ON workouts(workout_date);