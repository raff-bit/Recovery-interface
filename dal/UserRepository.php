<?php
require_once __DIR__ . '/../config/database.php';

class UserRepository
{
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
        if (!$user) {
            return null;
        }

        if (!password_verify($plainPassword, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }
}