<?php
require_once __DIR__ . '/env.php';

/**
 * Single connection point to MySQL (Singleton Pattern).
 * Reads configuration from environment variables / .env.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            loadEnv(__DIR__ . '/../.env');

            $host    = env('DB_HOST', 'localhost');
            $name    = env('DB_NAME', 'muscle_recovery_v2');
            $user    = env('DB_USER', 'root');
            $pass    = env('DB_PASS', '');
            $port    = env('DB_PORT', '3306');
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$connection = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Log the real reason, show the user a generic one (never leak host/user)
                error_log('Database connection error: ' . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Database connection failed. Please try again later.']);
                exit;
            }
        }

        return self::$connection;
    }
}