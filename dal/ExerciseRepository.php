<?php
require_once __DIR__ . '/../config/database.php';

class ExerciseRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, equipment, is_bodyweight FROM exercises ORDER BY name'
        );
        return $stmt->fetchAll();
    }

    public function getAllMuscleActivations(): array
    {
        $stmt = $this->db->query(
            'SELECT em.exercise_id, m.muscle_key, m.display_name, m.body_region, em.activation_weight, em.role
             FROM exercise_muscles em
             JOIN muscles m ON m.id = em.muscle_id'
        );
        return $stmt->fetchAll();
    }

    /**
     * Muscle activation rows for a specific set of exercise IDs — used
     * by the workout planner to figure out which muscles a *hypothetical*
     * (not-yet-logged) set of exercises would actually hit.
     */
    public function getMuscleActivationsForExercises(array $exerciseIds): array
    {
        if (empty($exerciseIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($exerciseIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT em.exercise_id, em.muscle_id, em.activation_weight, e.is_bodyweight
             FROM exercise_muscles em
             JOIN exercises e ON e.id = em.exercise_id
             WHERE em.exercise_id IN ($placeholders)"
        );
        $stmt->execute($exerciseIds);
        return $stmt->fetchAll();
    }
}