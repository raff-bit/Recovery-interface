<?php
require_once __DIR__ . '/../config/database.php';

class ProfileRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getProfile(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, sex, height_cm, body_weight_kg, age, activity_level
             FROM user_profile WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveProfile(int $userId, string $sex, ?float $heightCm, float $bodyWeightKg, ?int $age, string $activityLevel): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_profile (user_id, sex, height_cm, body_weight_kg, age, activity_level)
             VALUES (:user_id, :sex, :height, :weight, :age, :activity)
             ON DUPLICATE KEY UPDATE
                sex            = VALUES(sex),
                height_cm      = VALUES(height_cm),
                body_weight_kg = VALUES(body_weight_kg),
                age            = VALUES(age),
                activity_level = VALUES(activity_level)'
        );
        $stmt->execute([
            ':user_id'  => $userId,
            ':sex'      => $sex,
            ':height'   => $heightCm,
            ':weight'   => $bodyWeightKg,
            ':age'      => $age,
            ':activity' => $activityLevel,
        ]);
    }
}