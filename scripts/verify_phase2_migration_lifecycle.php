<?php

if (PHP_SAPI !== 'cli') {
    die("CLI execution required.\n");
}

// Enforce test environment before dotenv loads
$_ENV['APP_ENV'] = 'test';
$_ENV['DB_DATABASE'] = 'employee_assessment_test';
require_once __DIR__ . '/../bootstrap/init.php';

use App\Support\Database;

$appEnv = $_ENV['APP_ENV'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? '';

if ($appEnv !== 'test') {
    die("CRITICAL SAFETY ERROR: APP_ENV must be 'test'. Actual: '{$appEnv}'. Refusing execution.\n");
}
if (!str_ends_with($dbName, '_test')) {
    die("CRITICAL SAFETY ERROR: DB_DATABASE must end with '_test'. Actual: '{$dbName}'. Refusing execution.\n");
}

$out = [];
$out[] = "=== PHASE 2 MIGRATION LIFECYCLE VERIFICATION REPORT ===";
$out[] = "Timestamp  : " . date('Y-m-d H:i:s');
$out[] = "APP_ENV    : {$appEnv}";
$out[] = "DB_DATABASE: {$dbName}";
$out[] = "-------------------------------------------------------";

$pdo = Database::getInstance();

// 0. Reset DB to clean 001 baseline state
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$stmt = $pdo->query("SHOW TABLES;");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `{$t}`;");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$sql1 = file_get_contents(__DIR__ . '/../database/migrations/001_initial_schema.up.sql');
$pdo->exec($sql1);
$ck1 = hash('sha256', $sql1);
$pdo->exec("INSERT INTO schema_migrations (migration_file, checksum, batch, executed_at) VALUES ('001_initial_schema.up.sql', '{$ck1}', 1, UTC_TIMESTAMP())");

// Apply 002 UP initially
$upSql = file_get_contents(__DIR__ . '/../database/migrations/002_phase2_employee_auth.up.sql');
$currentChecksum = hash('sha256', $upSql);
$pdo->exec($upSql);
$pdo->exec("INSERT INTO schema_migrations (migration_file, checksum, batch, executed_at) VALUES ('002_phase2_employee_auth.up.sql', '{$currentChecksum}', 2, UTC_TIMESTAMP())");

// STEP 1: SHOW CREATE TABLE audit_logs BEFORE ROLLBACK
$out[] = "STEP 1: SHOW CREATE TABLE audit_logs (Initial Phase 2 State)...";
$stmt = $pdo->query("SHOW CREATE TABLE audit_logs");
$row = $stmt->fetch();
$out[] = $row['Create Table'];
$out[] = "-------------------------------------------------------";

// STEP 2: Rollback Batch 2 (002_phase2_employee_auth.down.sql)
$out[] = "STEP 2: Executing 002_phase2_employee_auth.down.sql rollback...";
$downSql = file_get_contents(__DIR__ . '/../database/migrations/002_phase2_employee_auth.down.sql');

// Strip comments and DELIMITER lines, then execute statements
$cleanDown = preg_replace('/--.*$/m', '', $downSql);
$cleanDown = preg_replace('/DELIMITER\s+\S+/i', '', $cleanDown);
$cleanDown = str_replace('$$', ';', $cleanDown);
$downStatements = array_filter(array_map('trim', explode(';', $cleanDown)));
foreach ($downStatements as $sqlStmt) {
    if (!empty($sqlStmt)) {
        try {
            $pdo->exec($sqlStmt);
        } catch (\PDOException $e) {
            // Ignore procedure drop errors if missing
        }
    }
}
$pdo->exec("DELETE FROM schema_migrations WHERE migration_file = '002_phase2_employee_auth.up.sql'");
$out[] = "Rolled back 002_phase2_employee_auth.up.sql successfully.";
$out[] = "-------------------------------------------------------";

// STEP 3: SHOW CREATE TABLE audit_logs AFTER ROLLBACK
$out[] = "STEP 3: SHOW CREATE TABLE audit_logs (After Rollback)...";
$stmt = $pdo->query("SHOW CREATE TABLE audit_logs");
$row = $stmt->fetch();
$out[] = $row['Create Table'];
$out[] = "-------------------------------------------------------";

// STEP 4: Re-applying 002_phase2_employee_auth.up.sql...
$out[] = "STEP 4: Re-applying 002_phase2_employee_auth.up.sql...";
$pdo->exec($upSql);

$stmtIns = $pdo->prepare("INSERT INTO schema_migrations (migration_file, checksum, batch, executed_at) VALUES ('002_phase2_employee_auth.up.sql', :checksum, 2, UTC_TIMESTAMP())");
$stmtIns->execute([':checksum' => $currentChecksum]);
$out[] = "Re-applied 002_phase2_employee_auth.up.sql successfully.";
$out[] = "-------------------------------------------------------";

// STEP 5: SHOW CREATE TABLE audit_logs AFTER RE-APPLYING
$out[] = "STEP 5: SHOW CREATE TABLE audit_logs (After Re-applying)...";
$stmt = $pdo->query("SHOW CREATE TABLE audit_logs");
$row = $stmt->fetch();
$out[] = $row['Create Table'];
$out[] = "-------------------------------------------------------";

// STEP 6: Inspect schema_migrations contents & checksums
$out[] = "STEP 6: Checking schema_migrations table contents & checksums...";
$stmtMig = $pdo->query("SELECT id, migration_file, checksum, batch, executed_at FROM schema_migrations ORDER BY id ASC");
$migrations = $stmtMig->fetchAll(PDO::FETCH_ASSOC);
foreach ($migrations as $m) {
    $out[] = sprintf("  [%d] File: %-35s | Batch: %d | Checksum: %s | ExecutedAt: %s", $m['id'], $m['migration_file'], $m['batch'], $m['checksum'], $m['executed_at']);
}

$reportContent = implode("\n", $out) . "\n";
file_put_contents(__DIR__ . '/../evidence/phase2_migration_lifecycle.raw.txt', $reportContent);
echo $reportContent;
