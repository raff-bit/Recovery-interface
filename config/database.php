<?php
/**
 * Single connection point to MySQL. Everything else in the DAL
 * goes through Database::getConnection() instead of opening its
 * own connection — that's what makes this a "singleton".
 */
class Database
{
    private const HOST    = 'localhost';
    private const NAME    = 'muscle_recovery_v2';
    private const USER    = 'root';   // default XAMPP user
    private const PASS    = '';       // default XAMPP password (empty)
    private const CHARSET = 'utf8mb4';

    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dsn = 'mysql:host=' . self::HOST . ';dbname=' . self::NAME . ';charset=' . self::CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$connection = new PDO($dsn, self::USER, self::PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'error'  => 'Database connection failed. Is XAMPP running and has "muscle_recovery" been imported?',
                    'detail' => $e->getMessage(),
                ]);
                exit;
            }
        }

        return self::$connection;
    }
}