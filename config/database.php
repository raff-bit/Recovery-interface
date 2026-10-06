<?php
/**
 * Single connection point to MySQL (Singleton Pattern).
 * Reads configuration from .env if present, otherwise defaults to local XAMPP.
 */
class Database
{
    private static ?PDO $connection = null;

    /**
     * Minimal native parser for .env files without external dependencies.
     */
    private static function loadEnv(): void
    {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                        putenv("{$key}={$value}");
                    }
                }
            }
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::loadEnv();

            $host    = getenv('DB_HOST') ?: 'localhost';
            $name    = getenv('DB_NAME') ?: 'muscle_recovery_v2';
            $user    = getenv('DB_USER') ?: 'root';
            $pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
            $port    = getenv('DB_PORT') ?: '3306';
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
                error_log("Database connection error: " . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Database connection failed. Verify database server and credentials.',
                ]);
                exit;
            }
        }

        return self::$connection;
    }
}