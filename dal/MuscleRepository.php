<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Everything needed to READ muscle rows. The muscles table is
 * mostly static reference data, so this repository stays small.
 */
class MuscleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** All 17 muscles, in the display order we set in seed.sql. */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, muscle_key, display_name, body_region, view_side, recovery_hours
             FROM muscles ORDER BY sort_order'
        );
        return $stmt->fetchAll();
    }
}