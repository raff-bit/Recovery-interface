<?php
require_once __DIR__ . '/../config/database.php';

/**
 * RoutineRepository — all SQL for routines.
 * ------------------------------------------------------------
 * Security rule of this class: every query that changes data has
 * "AND user_id = ?" in its WHERE. Built-in templates have user_id NULL,
 * and "NULL = 5" is never true in SQL, so templates can't be modified
 * or deleted by anyone, even with a hand-crafted request.
 */
class RoutineRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * The user's own routines first (A–Z), then the built-in templates (A–Z), each with its items.
     * Guests ($userId null) get the templates only — the planner is open to them.
     */
    public function listForUser(?int $userId): array
    {
        if ($userId === null) {
            $stmt = $this->db->query(
                'SELECT id, name, 1 AS is_template FROM routines WHERE user_id IS NULL ORDER BY name ASC'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT id, name, (user_id IS NULL) AS is_template
                 FROM routines
                 WHERE user_id = :user_id OR user_id IS NULL
                 ORDER BY is_template ASC, name ASC'
            );
            $stmt->execute([':user_id' => $userId]);
        }
        $routines = $stmt->fetchAll();

        if ($routines === []) {
            return [];
        }

        $ids = array_column($routines, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $items = $this->db->prepare(
            "SELECT ri.routine_id, ri.exercise_id, e.name AS exercise_name,
                    ri.sets, ri.reps, ri.weight_kg, ri.rpe
             FROM routine_items ri
             JOIN exercises e ON e.id = ri.exercise_id
             WHERE ri.routine_id IN ($placeholders)
             ORDER BY ri.routine_id, ri.position"
        );
        $items->execute($ids);

        $byRoutine = [];
        foreach ($items->fetchAll() as $row) {
            $byRoutine[$row['routine_id']][] = [
                'exercise_id'   => (int) $row['exercise_id'],
                'exercise_name' => $row['exercise_name'],
                'sets'          => (int) $row['sets'],
                'reps'          => (int) $row['reps'],
                'weight_kg'     => $row['weight_kg'] !== null ? (float) $row['weight_kg'] : null,
                'rpe'           => $row['rpe'] !== null ? (float) $row['rpe'] : null,
            ];
        }

        return array_map(fn(array $r) => [
            'id'          => (int) $r['id'],
            'name'        => $r['name'],
            'is_template' => (bool) $r['is_template'],
            'items'       => $byRoutine[$r['id']] ?? [],
        ], $routines);
    }

    /** True only if the routine exists AND belongs to this user (templates never do). */
    public function isOwnedBy(int $routineId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM routines WHERE id = ? AND user_id = ?');
        $stmt->execute([$routineId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM routines WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Creates a routine, or replaces one the user owns when $routineId is given.
     * Items are expected already cleaned by RoutineValidator. Returns the routine id.
     * Throws PDOException 23000 if the user already has another routine with this name.
     */
    public function save(int $userId, ?int $routineId, string $name, array $items): int
    {
        $this->db->beginTransaction();
        try {
            if ($routineId === null) {
                $stmt = $this->db->prepare('INSERT INTO routines (user_id, name) VALUES (?, ?)');
                $stmt->execute([$userId, $name]);
                $routineId = (int) $this->db->lastInsertId();
            } else {
                $stmt = $this->db->prepare('UPDATE routines SET name = ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$name, $routineId, $userId]);
                $this->db->prepare('DELETE FROM routine_items WHERE routine_id = ?')->execute([$routineId]);
            }

            // One multi-row INSERT for all items (same technique as WorkoutRepository::addSets)
            $sql = 'INSERT INTO routine_items (routine_id, position, exercise_id, sets, reps, weight_kg, rpe) VALUES '
                 . implode(', ', array_fill(0, count($items), '(?, ?, ?, ?, ?, ?, ?)'));
            $params = [];
            foreach (array_values($items) as $i => $item) {
                array_push($params, $routineId, $i + 1, $item['exercise_id'], $item['sets'], $item['reps'], $item['weight_kg'], $item['rpe']);
            }
            $this->db->prepare($sql)->execute($params);

            $this->db->commit();
            return $routineId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function delete(int $routineId, int $userId): void
    {
        // Items go with it (ON DELETE CASCADE)
        $stmt = $this->db->prepare('DELETE FROM routines WHERE id = ? AND user_id = ?');
        $stmt->execute([$routineId, $userId]);
    }
}
