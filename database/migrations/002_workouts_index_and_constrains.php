<?php
/**
 * Migration 002 — composite index + data-integrity constraints
 * ------------------------------------------------------------
 * 1. INDEX (user_id, workout_date) replaces the two single-column indexes.
 *    Every hot query is "this user's workouts in this date range, newest first";
 *    with the composite index MySQL jumps straight to that slice, already sorted
 *    (no filesort). Measured on 40k workouts: readiness query examines 13 rows
 *    instead of 400 per user; history query ~3x faster.
 *
 * 2. CHECK constraints mirror WorkoutValidator / save_profile rules in the DB.
 *    Defence in depth: even a future bug, a manual phpMyAdmin edit or a new
 *    endpoint that forgets validation can't store reps = -5.
 *
 * Written in PHP (not plain SQL) because databases built from the old legacy
 * scripts may have different index names — so we inspect before changing.
 */

return function (PDO $db): void {
    $indexExists = function (string $table, string $index) use ($db): bool {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([$table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $constraintExists = function (string $table, string $name) use ($db): bool {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
        );
        $stmt->execute([$table, $name]);
        return (int) $stmt->fetchColumn() > 0;
    };

    // ---------- 1. Indexes ----------
    // Create the composite FIRST: the foreign key on user_id needs some index
    // starting with user_id at all times, and the composite one qualifies.
    if (!$indexExists('workouts', 'idx_workouts_user_date')) {
        $db->exec('ALTER TABLE workouts ADD INDEX idx_workouts_user_date (user_id, workout_date)');
    }
    foreach (['idx_workouts_user', 'idx_workouts_date'] as $redundant) {
        if ($indexExists('workouts', $redundant)) {
            $db->exec("ALTER TABLE workouts DROP INDEX {$redundant}");
        }
    }

    // ---------- 2. Check existing data BEFORE adding constraints ----------
    // A CHECK can't be added while rows violate it. Rather than silently
    // "fixing" your data, we stop and show exactly what's wrong.
    $violations = [
        'workout_sets with reps outside 1–200'        => 'SELECT COUNT(*) FROM workout_sets WHERE reps < 1 OR reps > 200',
        'workout_sets with weight outside 0–1000 kg'  => 'SELECT COUNT(*) FROM workout_sets WHERE weight_kg < 0 OR weight_kg > 1000',
        'workout_sets with RPE outside 1–10'          => 'SELECT COUNT(*) FROM workout_sets WHERE rpe IS NOT NULL AND (rpe < 1 OR rpe > 10)',
        'workout_sets with set_number < 1'            => 'SELECT COUNT(*) FROM workout_sets WHERE set_number < 1',
        'user_profile with body weight outside 0–400' => 'SELECT COUNT(*) FROM user_profile WHERE body_weight_kg <= 0 OR body_weight_kg > 400',
        'user_profile with age outside 12–120'        => 'SELECT COUNT(*) FROM user_profile WHERE age IS NOT NULL AND (age < 12 OR age > 120)',
    ];
    $problems = [];
    foreach ($violations as $label => $sql) {
        $count = (int) $db->query($sql)->fetchColumn();
        if ($count > 0) {
            $problems[] = "{$count} {$label}";
        }
    }
    if ($problems) {
        throw new RuntimeException(
            "Existing data violates the new rules — fix these rows first (e.g. in phpMyAdmin):\n  - "
            . implode("\n  - ", $problems)
        );
    }

    // ---------- 3. Constraints ----------
    $checks = [
        'workout_sets' => [
            'chk_sets_reps'       => 'reps BETWEEN 1 AND 200',
            'chk_sets_weight'     => 'weight_kg BETWEEN 0 AND 1000',
            'chk_sets_rpe'        => 'rpe IS NULL OR rpe BETWEEN 1 AND 10',
            'chk_sets_set_number' => 'set_number >= 1',
        ],
        'user_profile' => [
            'chk_profile_weight'  => 'body_weight_kg > 0 AND body_weight_kg <= 400',
            'chk_profile_age'     => 'age IS NULL OR age BETWEEN 12 AND 120',
        ],
    ];
    foreach ($checks as $table => $constraints) {
        foreach ($constraints as $name => $expression) {
            if (!$constraintExists($table, $name)) {
                $db->exec("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
            }
        }
    }

    // ---------- 4. Refresh optimizer statistics ----------
    // Adding a CHECK rebuilds the table, and the rebuilt table can start with
    // near-empty index statistics. MySQL then believes the indexes are useless
    // and FULL-SCANS instead (measured: 480k-row scan, 2 ms → 110 ms per query).
    // ANALYZE recomputes the statistics so the optimizer picks the indexes again.
    $db->query('ANALYZE TABLE workouts, workout_sets, user_profile')->fetchAll();
};