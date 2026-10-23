<?php
require_once __DIR__ . '/../config/database.php';

class UserRepository
{
        /**
     * bcrypt hash of a random throwaway value (same cost as real hashes).
     * Verifying against it takes the same time as a real check — and nothing matches it.
     */
    private const DUMMY_HASH = '$2y$10$38YuiS8a2WD2EXXkeguhgeGq0yJLIpnZ1s6BvYI/WcN6BVQYxxDS2';
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash, email_verified_at, created_at
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, email_verified_at, created_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createUser(string $name, string $email, string $plainPassword): int
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $verificationToken = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, email_verification_token)
             VALUES (:name, :email, :hash, :token)'
        );
        $stmt->execute([
            ':name'  => trim($name),
            ':email' => strtolower(trim($email)),
            ':hash'  => $hash,
            ':token' => $verificationToken,
        ]);

        return (int) $this->db->lastInsertId();
    }

        public function authenticate(string $email, string $plainPassword): ?array
    {
        $user = $this->findByEmail($email);

        // Always run password_verify, even for unknown emails. Otherwise "no such user"
        // answers in ~1 ms and "wrong password" in ~80 ms — an attacker could time
        // the responses to discover which emails have accounts.
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($plainPassword, $hash);

        if (!$user || !$valid) {
            return null;
        }

        // Transparently upgrade old hashes if PHP's default algorithm/cost changes
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $stmt->execute([':hash' => password_hash($plainPassword, PASSWORD_DEFAULT), ':id' => $user['id']]);
        }

        unset($user['password_hash']);
        return $user;
    }
}