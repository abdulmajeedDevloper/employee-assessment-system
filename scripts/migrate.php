<?php

if (PHP_SAPI !== 'cli') {
    die("CLI execution required.\n");
}

require_once __DIR__ . '/../bootstrap/init.php';

use App\Support\Database;
use App\Support\Logger;

$action = $argv[1] ?? 'up';

try {
    $pdo = Database::getInstance();
} catch (\Exception $e) {
    // Attempt to connect to MySQL server root to create database if missing
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $dbname = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'employee_assessment');
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $rootDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $rootPdo = new PDO($rootDsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo = Database::getInstance();
}

$migrationsDir = __DIR__ . '/../database/migrations';

if ($action === 'rollback') {
    echo "=== Running Migration Rollback ===" . PHP_EOL;
    $stmt = $pdo->query("SELECT * FROM schema_migrations ORDER BY batch DESC, id DESC");
    $applied = $stmt->fetchAll();

    if (empty($applied)) {
        echo "No applied migrations to rollback." . PHP_EOL;
        exit(0);
    }

    $lastBatch = $applied[0]['batch'];
    echo "Rolling back batch {$lastBatch}..." . PHP_EOL;

    // Protection check: abort rollback if employee audit records exist
    try {
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE actor_type = 'EMPLOYEE'");
        $empAuditCount = (int)$stmtCount->fetchColumn();
        if ($empAuditCount > 0) {
            echo "CRITICAL ERROR: Rollback aborted! audit_logs table contains {$empAuditCount} employee audit records. Rolling back Migration 002 would permanently destroy employee audit attribution history. Please restore from a verified pre-deployment database snapshot." . PHP_EOL;
            exit(1);
        }
    } catch (\Exception $e) {
        // Table or column may not exist yet in baseline schema
    }

    foreach ($applied as $row) {
        if ((int)$row['batch'] !== (int)$lastBatch) {
            continue;
        }

        $fileBase = str_replace('.up.sql', '', $row['migration_file']);
        $downFile = $migrationsDir . '/' . $fileBase . '.down.sql';

        if (!file_exists($downFile)) {
            echo "Error: Down migration file missing: {$downFile}" . PHP_EOL;
            exit(1);
        }

        echo "Reversing {$fileBase}.down.sql..." . PHP_EOL;
        $sql = file_get_contents($downFile);
        $cleanSql = preg_replace('/--.*$/m', '', $sql);
        $cleanSql = preg_replace('/DELIMITER\s+\S+/i', '', $cleanSql);
        $cleanSql = str_replace('$$', ';', $cleanSql);
        $downStatements = array_filter(array_map('trim', explode(';', $cleanSql)));
        foreach ($downStatements as $sqlStmt) {
            if (!empty($sqlStmt)) {
                $pdo->exec($sqlStmt);
            }
        }

        $delStmt = $pdo->prepare("DELETE FROM schema_migrations WHERE id = :id");
        $delStmt->execute([':id' => $row['id']]);
    }

    echo "Rollback completed successfully." . PHP_EOL;
    exit(0);
}

// Run Migrations UP
echo "=== Running Database Migrations (UP) ===" . PHP_EOL;

// Ensure schema_migrations table exists
$pdo->exec("
CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_file VARCHAR(255) NOT NULL UNIQUE,
    checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch INT NOT NULL,
    executed_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$upFiles = glob($migrationsDir . '/*.up.sql');
sort($upFiles);

$stmt = $pdo->query("SELECT migration_file, checksum FROM schema_migrations");
$appliedMap = [];
foreach ($stmt->fetchAll() as $r) {
    $appliedMap[$r['migration_file']] = $r['checksum'];
}

$stmtBatch = $pdo->query("SELECT MAX(batch) FROM schema_migrations");
$currentBatch = ((int)$stmtBatch->fetchColumn()) + 1;

$executedCount = 0;

foreach ($upFiles as $filePath) {
    $fileName = basename($filePath);
    $checksum = hash_file('sha256', $filePath);

    if (isset($appliedMap[$fileName])) {
        // Verify checksum match
        if ($appliedMap[$fileName] !== $checksum) {
            echo "CRITICAL ERROR: Migration file {$fileName} has been modified after execution!" . PHP_EOL;
            echo "Recorded Checksum: {$appliedMap[$fileName]}" . PHP_EOL;
            echo "Current Checksum:  {$checksum}" . PHP_EOL;
            exit(1);
        }
        continue;
    }

    echo "Executing {$fileName} (Batch {$currentBatch})..." . PHP_EOL;
    $sql = file_get_contents($filePath);

    try {
        $pdo->exec($sql);
        $insStmt = $pdo->prepare("
            INSERT INTO schema_migrations (migration_file, checksum, batch, executed_at)
            VALUES (:file, :checksum, :batch, UTC_TIMESTAMP())
        ");
        $insStmt->execute([
            ':file' => $fileName,
            ':checksum' => $checksum,
            ':batch' => $currentBatch
        ]);
        $executedCount++;
    } catch (\PDOException $e) {
        echo "Migration Failure in {$fileName}: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

if ($executedCount === 0) {
    echo "Nothing to migrate. Database schema is up to date." . PHP_EOL;
} else {
    echo "Migrated {$executedCount} file(s) successfully (Batch {$currentBatch})." . PHP_EOL;
}
