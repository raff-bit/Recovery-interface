<?php
require_once __DIR__ . '/../config/Database.php';

class WorkoutRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function createWorkout(string $workoutDate, ?string $label, ?string $notes): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workouts (workout_date, label, notes) VALUES (:date, :label, :notes)'
        );
        $stmt->execute([':date' => $workoutDate, ':label' => $label, ':notes' => $notes]);
        return (int) $this->db->lastInsertId();
    }

    public function addSet(int $workoutId, int $exerciseId, int $setNumber, int $reps, float $weightKg, ?float $rpe): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workout_sets (workout_id, exercise_id, set_number, reps, weight_kg, rpe)
             VALUES (:workout_id, :exercise_id, :set_number, :reps, :weight_kg, :rpe)'
        );
        $stmt->execute([
            ':workout_id'  => $workoutId,
            ':exercise_id' => $exerciseId,
            ':set_number'  => $setNumber,
            ':reps'        => $reps,
            ':weight_kg'   => $weightKg,
            ':rpe'         => $rpe,
        ]);
    }

    public function beginTransaction(): void { $this->db->beginTransaction(); }
    public function commit(): void { $this->db->commit(); }
    public function rollBack(): void { $this->db->rollBack(); }

    public function getRecentSetsWithActivation(int $days): array
    {
        $stmt = $this->db->prepare(
            'SELECT em.muscle_id, em.activation_weight, ws.reps, ws.weight_kg, ws.rpe, w.workout_date, e.is_bodyweight
             FROM workout_sets ws
             JOIN workouts w ON w.id = ws.workout_id
             JOIN exercise_muscles em ON em.exercise_id = ws.exercise_id
             JOIN exercises e ON e.id = ws.exercise_id
             WHERE w.workout_date >= (NOW() - INTERVAL :days DAY)
             ORDER BY w.workout_date DESC'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRecentWorkouts(int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query(
            "SELECT id, workout_date, label, notes FROM workouts ORDER BY workout_date DESC LIMIT $limit"
        );
        return $stmt->fetchAll();
    }

    public function getSetsForWorkouts(array $workoutIds): array
    {
        if (empty($workoutIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($workoutIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT ws.workout_id, ws.set_number, ws.exercise_id, ws.reps, ws.weight_kg, ws.rpe, e.name AS exercise_name
             FROM workout_sets ws
             JOIN exercises e ON e.id = ws.exercise_id
             WHERE ws.workout_id IN ($placeholders)
             ORDER BY ws.workout_id, e.name, ws.set_number"
        );
        $stmt->execute($workoutIds);
        return $stmt->fetchAll();
    }

    /** A single workout's own row (date/label/notes) — used to pre-fill the edit form. */
    public function getWorkoutById(int $workoutId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, workout_date, label, notes FROM workouts WHERE id = :id'
        );
        $stmt->execute([':id' => $workoutId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Deletes a workout. workout_sets rows for it are removed automatically
     * by the ON DELETE CASCADE foreign key set up in schema.sql — no need
     * to delete sets manually here.
     */
    public function deleteWorkout(int $workoutId): void
    {
        $stmt = $this->db->prepare('DELETE FROM workouts WHERE id = :id');
        $stmt->execute([':id' => $workoutId]);
    }

    public function updateWorkoutMeta(int $workoutId, string $workoutDate, ?string $label, ?string $notes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE workouts SET workout_date = :date, label = :label, notes = :notes WHERE id = :id'
        );
        $stmt->execute([
            ':date'  => $workoutDate,
            ':label' => $label,
            ':notes' => $notes,
            ':id'    => $workoutId,
        ]);
    }

    /** Wipes all sets for a workout so an edit can re-insert a fresh list. */
    public function deleteSetsForWorkout(int $workoutId): void
    {
        $stmt = $this->db->prepare('DELETE FROM workout_sets WHERE workout_id = :id');
        $stmt->execute([':id' => $workoutId]);
    }

    /** Every workout ever logged, oldest first — used for full data export/backup. No LIMIT. */
    public function getAllWorkoutsForExport(): array
    {
        $stmt = $this->db->query(
            'SELECT id, workout_date, label, notes FROM workouts ORDER BY workout_date ASC'
        );
        return $stmt->fetchAll();
    }

    /** Total sets performed per calendar week (Monday-start), over the last N weeks. */
    public function getWeeklyVolume(int $weeksBack): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(DATE_SUB(w.workout_date, INTERVAL WEEKDAY(w.workout_date) DAY)) AS week_start,
                    COUNT(ws.id) AS total_sets
             FROM workout_sets ws
             JOIN workouts w ON w.id = ws.workout_id
             WHERE w.workout_date >= (NOW() - INTERVAL :weeks WEEK)
             GROUP BY week_start
             ORDER BY week_start ASC"
        );
        $stmt->bindValue(':weeks', $weeksBack, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Total sets performed per body region over the last N days — reveals training imbalances. */
    public function getMuscleGroupSetCounts(int $daysBack): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.body_region, COUNT(*) AS total_sets
             FROM workout_sets ws
             JOIN workouts w ON w.id = ws.workout_id
             JOIN exercise_muscles em ON em.exercise_id = ws.exercise_id
             JOIN muscles m ON m.id = em.muscle_id
             WHERE w.workout_date >= (NOW() - INTERVAL :days DAY)
             GROUP BY m.body_region
             ORDER BY total_sets DESC"
        );
        $stmt->bindValue(':days', $daysBack, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Most-logged exercises over the last N days. $limit is clamped and inlined (safe: it's an int, not user SQL). */
    public function getTopExercises(int $daysBack, int $limit): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->db->prepare(
            "SELECT e.name AS exercise_name, COUNT(*) AS total_sets
             FROM workout_sets ws
             JOIN workouts w ON w.id = ws.workout_id
             JOIN exercises e ON e.id = ws.exercise_id
             WHERE w.workout_date >= (NOW() - INTERVAL :days DAY)
             GROUP BY e.name
             ORDER BY total_sets DESC
             LIMIT $limit"
        );
        $stmt->bindValue(':days', $daysBack, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}