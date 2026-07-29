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
$out[] = "=== DIRECT MYSQL CLI DOWN SQL GUARD VERIFICATION REPORT ===";
$out[] = "Timestamp  : " . date('Y-m-d H:i:s');
$out[] = "APP_ENV    : {$appEnv}";
$out[] = "DB_DATABASE: {$dbName}";
$out[] = "-------------------------------------------------------------";

$pdo = Database::getInstance();

// 1. Setup Phase 1 and Phase 2 Schema
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

$sql2 = file_get_contents(__DIR__ . '/../database/migrations/002_phase2_employee_auth.up.sql');
$ck2 = hash('sha256', $sql2);
$pdo->exec($sql2);
$pdo->exec("INSERT INTO schema_migrations (migration_file, checksum, batch, executed_at) VALUES ('002_phase2_employee_auth.up.sql', '{$ck2}', 2, UTC_TIMESTAMP())");

// Seed reference data & employee
$pdo->exec("INSERT INTO departments (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 'إدارة التقنية', 'IT Dept', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
$pdo->exec("INSERT INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 1, 'قسم التطوير', 'Software Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
$pdo->exec("INSERT INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 'مهندس برمجيات', 'Software Engineer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");

$hash1 = \App\Security\HashManager::hashNationalId('1000000001');
$stmtEmp = $pdo->prepare("INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, preferred_language, is_active, profile_completed_at, created_at, updated_at) VALUES (1, 'أحمد علي', :hash, 'cipher1', random_bytes(12), random_bytes(16), '0001', 'EMP001', 'ahmed@test.com', '+966512345678', 1, 1, 1, 'ar', 1, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
$stmtEmp->bindValue(':hash', $hash1, PDO::PARAM_STR);
$stmtEmp->execute();

// Insert EMPLOYEE audit row
$pdo->exec("INSERT INTO audit_logs (id, actor_type, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (1, 'EMPLOYEE', 1, 'PROFILE_CONFIRMATION_UPDATED', 'employee', 1, '127.0.0.1', 'CLI Test', UTC_TIMESTAMP());");

$countBefore = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$out[] = "Pre-Rollback Audit Log Count: {$countBefore}";
$out[] = "Pre-Rollback Employee Audit Row: id=1, actor_type='EMPLOYEE', employee_id=1";
$out[] = "-------------------------------------------------------------";

// 2. Direct Execution of 002_phase2_employee_auth.down.sql via mysql.exe CLI
$mysqlPath = 'C:\mysql8\bin\mysql.exe';
$downFile = realpath(__DIR__ . '/../database/migrations/002_phase2_employee_auth.down.sql');
$cmd = sprintf('"%s" -u root -h 127.0.0.1 -P 3307 %s < "%s" 2>&1', $mysqlPath, $dbName, $downFile);

exec($cmd, $cmdOutput, $returnCode);

$out[] = "DIRECT MYSQL CLI EXECUTION OF DOWN MIGRATION:";
$out[] = "  Command Exit Code: {$returnCode} (Non-Zero expected)";
$out[] = "  CLI Output       :";
foreach ($cmdOutput as $line) {
    $out[] = "    " . $line;
}
$out[] = "-------------------------------------------------------------";

// 3. Post-Failure Evidence & Schema Inspection
$out[] = "POST-FAILURE SCHEMA & DATA INTEGRITY EVIDENCE:";

// Check columns in audit_logs
$stmtCols = $pdo->query("DESCRIBE audit_logs");
$cols = array_column($stmtCols->fetchAll(), 'Field');
$hasActorType = in_array('actor_type', $cols, true);
$hasEmployeeId = in_array('employee_id', $cols, true);
$out[] = sprintf("  1. actor_type column exists : %s", $hasActorType ? 'YES (PASS)' : 'NO (FAIL)');
$out[] = sprintf("  2. employee_id column exists: %s", $hasEmployeeId ? 'YES (PASS)' : 'NO (FAIL)');

// Check constraints in INFORMATION_SCHEMA
$stmtFk = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'audit_logs' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_audit_employee'");
$hasFk = ($stmtFk->rowCount() > 0);
$out[] = sprintf("  3. fk_audit_employee FK exists: %s", $hasFk ? 'YES (PASS)' : 'NO (FAIL)');

$stmtChk = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'audit_logs' AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'chk_audit_actor'");
$hasChk = ($stmtChk->rowCount() > 0);
$out[] = sprintf("  4. chk_audit_actor CHECK exists: %s", $hasChk ? 'YES (PASS)' : 'NO (FAIL)');

// Check schema_migrations row
$stmtMig = $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration_file = '002_phase2_employee_auth.up.sql'");
$hasMig = ((int)$stmtMig->fetchColumn() > 0);
$out[] = sprintf("  5. Migration 002 recorded: %s", $hasMig ? 'YES (PASS)' : 'NO (FAIL)');

// Check audit log row count & content
$countAfter = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$out[] = sprintf("  6. Audit Log Row Count     : %d (Unchanged: %s)", $countAfter, ($countBefore === $countAfter) ? 'YES (PASS)' : 'NO (FAIL)');

$stmtRow = $pdo->query("SELECT id, actor_type, employee_id, action FROM audit_logs WHERE id = 1");
$row = $stmtRow->fetch(PDO::FETCH_ASSOC);
$out[] = sprintf("  7. Employee Audit Row Content: id=%d, actor_type='%s', employee_id=%d (Unchanged: %s)",
    $row['id'], $row['actor_type'], $row['employee_id'],
    ($row['actor_type'] === 'EMPLOYEE' && (int)$row['employee_id'] === 1) ? 'YES (PASS)' : 'NO (FAIL)'
);

$report = implode("\n", $out) . "\n";
file_put_contents(__DIR__ . '/../evidence/direct_mysql_down_guard_verification.raw.txt', $report);
echo $report;
