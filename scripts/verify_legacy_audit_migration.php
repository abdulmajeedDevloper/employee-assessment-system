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
$out[] = "=== PHASE 2 MIGRATION 002 LEGACY DATA BACKFILL TEST REPORT ===";
$out[] = "Timestamp  : " . date('Y-m-d H:i:s');
$out[] = "APP_ENV    : {$appEnv}";
$out[] = "DB_DATABASE: {$dbName}";
$out[] = "---------------------------------------------------------------";

$pdo = Database::getInstance();

// 1. Setup Phase 1 Baseline Schema
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$stmt = $pdo->query("SHOW TABLES;");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `{$t}`;");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$sql1 = file_get_contents(__DIR__ . '/../database/migrations/001_initial_schema.up.sql');
$pdo->exec($sql1);

// Seed admin for legacy FK
$pdo->exec("INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at) VALUES (1, 'Legacy Admin', 'admin@test.local', 'admin', 'hash', 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");

// Seed legacy Phase 1 audit rows (Before Migration 002)
$pdo->exec("INSERT INTO audit_logs (id, admin_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (1, 1, 'LEGACY_ADMIN_ACTION', 'system', 1, '127.0.0.1', 'CLI', UTC_TIMESTAMP());");
$pdo->exec("INSERT INTO audit_logs (id, admin_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (2, NULL, 'LEGACY_SYSTEM_ACTION', 'system', 2, '127.0.0.1', 'CLI', UTC_TIMESTAMP());");

$out[] = "PHASE 1 BASELINE AUDIT ROWS (BEFORE MIGRATION 002):";
$stmtBefore = $pdo->query("SELECT id, admin_id, action FROM audit_logs ORDER BY id ASC");
$rowsBefore = $stmtBefore->fetchAll(PDO::FETCH_ASSOC);
foreach ($rowsBefore as $r) {
    $out[] = sprintf("  [Row #%d] admin_id: %-4s | Action: %s", $r['id'], $r['admin_id'] ?? 'NULL', $r['action']);
}
$countBefore = count($rowsBefore);
$out[] = "Total Pre-Migration Row Count: {$countBefore}";
$out[] = "---------------------------------------------------------------";

// 2. Execute Migration 002 UP (Staged Backfill)
$out[] = "EXECUTING MIGRATION 002 UP (Staged Backfill)...";
$upSql = file_get_contents(__DIR__ . '/../database/migrations/002_phase2_employee_auth.up.sql');
$pdo->exec($upSql);

$stmtAfter = $pdo->query("SELECT id, actor_type, admin_id, employee_id, action FROM audit_logs ORDER BY id ASC");
$rowsAfter = $stmtAfter->fetchAll(PDO::FETCH_ASSOC);
$countAfter = count($rowsAfter);

$out[] = "PHASE 2 AUDIT ROWS (AFTER MIGRATION 002):";
foreach ($rowsAfter as $r) {
    $out[] = sprintf("  [Row #%d] actor_type: %-8s | admin_id: %-4s | employee_id: %-4s | Action: %s",
        $r['id'], $r['actor_type'], $r['admin_id'] ?? 'NULL', $r['employee_id'] ?? 'NULL', $r['action']);
}
$out[] = "Total Post-Migration Row Count: {$countAfter}";
$out[] = "---------------------------------------------------------------";

// 3. Verification Checks
$out[] = "BACKFILL VERIFICATION RESULTS:";
if ($countBefore === $countAfter) {
    $out[] = "  [PASS] Row Count Equality Verified (100% rows preserved).";
} else {
    $out[] = "  [FAIL] Row count mismatch!";
}

if ($rowsAfter[0]['actor_type'] === 'ADMIN' && $rowsAfter[1]['actor_type'] === 'SYSTEM') {
    $out[] = "  [PASS] Backfill Accuracy Verified (admin_id 1 -> ADMIN, admin_id NULL -> SYSTEM).";
} else {
    $out[] = "  [FAIL] Backfill actor_type mapping error!";
}

$stmtNullCheck = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE actor_type IS NULL");
$nullCount = $stmtNullCheck->fetchColumn();
if ((int)$nullCount === 0) {
    $out[] = "  [PASS] Zero NULL actor_type rows remaining.";
} else {
    $out[] = "  [FAIL] Found {$nullCount} NULL actor_type rows!";
}

$report = implode("\n", $out) . "\n";
file_put_contents(__DIR__ . '/../evidence/legacy_audit_migration_verification.raw.txt', $report);
echo $report;
