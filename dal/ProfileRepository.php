<?php
require_once __DIR__ . '/../config/Database.php';

/**
 * ProfileRepository
 * ------------------------------------------------------------
 * The app has exactly one user, so this behaves as a singleton:
 * there's ever only one row in user_profile. getProfile() returns
 * null until the person has actually filled one in.
 */
class ProfileRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getProfile(): ?array
    {
        $stmt = $this->db->query(
            'SELECT id, sex, height_cm, body_weight_kg, activity_level
             FROM user_profile ORDER BY id LIMIT 1'
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveProfile(string $sex, ?float $heightCm, float $bodyWeightKg, string $activityLevel): void
    {
        $existing = $this->getProfile();

        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE user_profile
                 SET sex = :sex, height_cm = :height, body_weight_kg = :weight, activity_level = :activity
                 WHERE id = :id'
            );
            $stmt->execute([
                ':sex'      => $sex,
                ':height'   => $heightCm,
                ':weight'   => $bodyWeightKg,
                ':activity' => $activityLevel,
                ':id'       => $existing['id'],
            ]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO user_profile (sex, height_cm, body_weight_kg, activity_level)
                 VALUES (:sex, :height, :weight, :activity)'
            );
            $stmt->execute([
                ':sex'      => $sex,
                ':height'   => $heightCm,
                ':weight'   => $bodyWeightKg,
                ':activity' => $activityLevel,
            ]);
        }
    }
}