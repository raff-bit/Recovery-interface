<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Persistence for auth_attempts. Pure data access — the rules
 * (how many, how long) live in bll/AuthThrottle.php.
 *
 * All time math uses MySQL's NOW() on both insert and compare,
 * so PHP/MySQL timezone differences can't skew the windows.
 */
class AuthAttemptRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function record(string $action, string $ip, ?string $emailHash): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO auth_attempts (action, ip, email_hash, created_at) VALUES (:action, :ip, :email_hash, NOW())'
        );
        $stmt->execute([':action' => $action, ':ip' => $ip, ':email_hash' => $emailHash]);
    }

    public function countByIp(string $action, string $ip, int $windowMinutes): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM auth_attempts
             WHERE action = :action AND ip = :ip AND created_at >= (NOW() - INTERVAL :mins MINUTE)'
        );
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':mins', $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countByEmail(string $action, string $emailHash, int $windowMinutes): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM auth_attempts
             WHERE action = :action AND email_hash = :email_hash AND created_at >= (NOW() - INTERVAL :mins MINUTE)'
        );
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':email_hash', $emailHash);
        $stmt->bindValue(':mins', $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** After a successful login, that account's failure counter starts over. */
    public function clearForEmail(string $action, string $emailHash): void
    {
        $stmt = $this->db->prepare('DELETE FROM auth_attempts WHERE action = :action AND email_hash = :email_hash');
        $stmt->execute([':action' => $action, ':email_hash' => $emailHash]);
    }

    /** Housekeeping so the table never grows forever. */
    public function purgeOlderThan(int $hours): void
    {
        $stmt = $this->db->prepare('DELETE FROM auth_attempts WHERE created_at < (NOW() - INTERVAL :hours HOUR)');
        $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
        $stmt->execute();
    }
}