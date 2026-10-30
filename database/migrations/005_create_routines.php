<?php
/**
 * Migration 005 — routines (saved workout templates)
 * ------------------------------------------------------------
 * routines        one row per routine. user_id NULL = built-in template,
 *                 visible to everyone and owned by no one, so it can never
 *                 be edited or deleted through the API (every write is
 *                 filtered by "user_id = <you>", and NULL never matches).
 * routine_items   the exercises of a routine, in order, with planned
 *                 sets/reps/weight/RPE. CHECKs mirror RoutineValidator.
 *
 * Also inserts three starter templates (Push / Pull / Legs) so new users
 * see how routines work without building one first.
 */

return function (PDO $db): void {
    $db->exec(
        'CREATE TABLE IF NOT EXISTS routines (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NULL,
            name        VARCHAR(100) NOT NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_routines_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uq_routines_user_name (user_id, name)
        ) ENGINE=InnoDB'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS routine_items (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            routine_id   INT NOT NULL,
            position     SMALLINT NOT NULL,
            exercise_id  INT NOT NULL,
            sets         TINYINT NOT NULL,
            reps         SMALLINT NOT NULL,
            weight_kg    DECIMAL(6,2) NULL,
            rpe          DECIMAL(3,1) NULL,
            CONSTRAINT fk_items_routine  FOREIGN KEY (routine_id)  REFERENCES routines(id)  ON DELETE CASCADE,
            CONSTRAINT fk_items_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE RESTRICT,
            CONSTRAINT chk_items_sets   CHECK (sets BETWEEN 1 AND 20),
            CONSTRAINT chk_items_reps   CHECK (reps BETWEEN 1 AND 200),
            CONSTRAINT chk_items_weight CHECK (weight_kg IS NULL OR weight_kg BETWEEN 0 AND 1000),
            CONSTRAINT chk_items_rpe    CHECK (rpe IS NULL OR rpe BETWEEN 1 AND 10),
            INDEX idx_items_routine (routine_id, position)
        ) ENGINE=InnoDB'
    );

    // ---------- starter templates (user_id NULL) ----------
    // Weight/RPE left empty on purpose: those are personal; the user fills them in.
    $templates = [
        'Push day' => [
            ['Bench Press', 4, 8], ['Overhead Press', 3, 8], ['Incline Bench Press', 3, 10],
            ['Lateral Raise', 3, 15], ['Tricep Pushdown', 3, 12],
        ],
        'Pull day' => [
            ['Pull-ups', 4, 8], ['Barbell Row', 4, 8], ['Lat Pulldown', 3, 10],
            ['Face Pull', 3, 15], ['Bicep Curl', 3, 12],
        ],
        'Leg day' => [
            ['Squat', 4, 6], ['Romanian Deadlift', 3, 8], ['Leg Press', 3, 10],
            ['Leg Curl', 3, 12], ['Calf Raise', 4, 12],
        ],
    ];

    $findExercise = $db->prepare('SELECT id FROM exercises WHERE name = ?');
    $templateExists = $db->prepare('SELECT COUNT(*) FROM routines WHERE user_id IS NULL AND name = ?');
    $insertRoutine = $db->prepare('INSERT INTO routines (user_id, name) VALUES (NULL, ?)');
    $insertItem = $db->prepare(
        'INSERT INTO routine_items (routine_id, position, exercise_id, sets, reps) VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($templates as $name => $items) {
        // Re-running must not duplicate (UNIQUE doesn't apply when user_id is NULL)
        $templateExists->execute([$name]);
        if ((int) $templateExists->fetchColumn() > 0) {
            continue;
        }

        $db->beginTransaction();
        $insertRoutine->execute([$name]);
        $routineId = (int) $db->lastInsertId();

        $position = 0;
        foreach ($items as [$exerciseName, $sets, $reps]) {
            $findExercise->execute([$exerciseName]);
            $exerciseId = $findExercise->fetchColumn();
            if ($exerciseId === false) {
                echo "\n    (skipped '{$exerciseName}' in '{$name}': exercise not found)";
                continue;
            }
            $insertItem->execute([$routineId, ++$position, (int) $exerciseId, $sets, $reps]);
        }
        $db->commit();
    }
};
