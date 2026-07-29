<?php

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO connection instance.
     *
     * @return PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $dbname = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'employee_assessment');
            $username = $_ENV['DB_USERNAME'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            try {
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // Three separate statements to prevent ambiguity
                $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("SET time_zone = '+00:00'");
                $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

                self::$instance = $pdo;
                self::verifyConnectionState($pdo);

            } catch (PDOException $e) {
                throw new RuntimeException("Database Connection Failure: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        return self::$instance;
    }

    /**
     * Verify connection state (Strict SQL mode, UTC timezone, utf8mb4 collation).
     *
     * @param PDO $pdo
     * @return bool
     */
    public static function verifyConnectionState(PDO $pdo): bool
    {
        $stmt = $pdo->query("SELECT @@sql_mode AS sql_mode, @@time_zone AS time_zone, @@character_set_connection AS charset, @@collation_connection AS collation");
        $state = $stmt->fetch();

        if (!$state) {
            throw new RuntimeException("Failed to verify PDO connection state.");
        }

        if (!str_contains($state['sql_mode'], 'STRICT_TRANS_TABLES')) {
            throw new RuntimeException("PDO session sql_mode does not contain STRICT_TRANS_TABLES. Actual: {$state['sql_mode']}");
        }

        if ($state['time_zone'] !== '+00:00') {
            throw new RuntimeException("PDO session time_zone is not +00:00. Actual: {$state['time_zone']}");
        }

        return true;
    }

    /**
     * Set singleton instance (useful for testing).
     */
    public static function setInstance(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Reset connection (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
