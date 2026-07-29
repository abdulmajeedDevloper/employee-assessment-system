<?php

declare(strict_types=1);

// Global PHPUnit Bootstrap Safety Guard
// Runs BEFORE any test class, setUpBeforeClass(), fixture seeding, migration, or truncation.

require_once __DIR__ . '/../vendor/autoload.php';

$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_DATABASE'] = 'employee_assessment_test';

require_once __DIR__ . '/../bootstrap/init.php';

$env = strtolower((string) ($_ENV['APP_ENV'] ?? 'development'));
if (!in_array($env, ['test', 'testing'], true)) {
    fwrite(STDERR, "TEST DATABASE SAFETY GUARD FATAL ERROR: APP_ENV must be 'test' or 'testing', found '{$env}'." . PHP_EOL);
    exit(1);
}

try {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    $testDb = 'employee_assessment_test';

    $dsn = "mysql:host={$host};port={$port};dbname={$testDb};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("SET time_zone = '+00:00'");
$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    $activeDb = (string) $pdo->query("SELECT DATABASE()")->fetchColumn();

    if ($activeDb === 'employee_assessment') {
        fwrite(STDERR, "TEST DATABASE SAFETY GUARD FATAL ERROR: Attempted to run test suite against PRODUCTION / DEVELOPMENT database 'employee_assessment'! Execution aborted." . PHP_EOL);
        exit(1);
    }

    if (!str_ends_with($activeDb, '_test')) {
        fwrite(STDERR, "TEST DATABASE SAFETY GUARD FATAL ERROR: Connected database '{$activeDb}' does not end with '_test'! Execution aborted." . PHP_EOL);
        exit(1);
    }

    \App\Support\Database::setInstance($pdo);

    fwrite(STDOUT, "PHPUnit Global Bootstrap: Connected database '{$activeDb}' verified safe (APP_ENV={$env})." . PHP_EOL);

} catch (Throwable $e) {
    fwrite(STDERR, "TEST DATABASE SAFETY GUARD FATAL ERROR: Failed database safety check: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
