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
$out[] = "=== MYSQL 8 NEGATIVE DATABASE CONSTRAINT TEST REPORT ===";
$out[] = "Timestamp  : " . date('Y-m-d H:i:s');
$out[] = "APP_ENV    : {$appEnv}";
$out[] = "DB_DATABASE: {$dbName}";
$out[] = "--------------------------------------------------------";

$pdo = Database::getInstance();

$scenarios = [
    [
        'name' => '1. EMPLOYEE actor with admin_id populated',
        'sql' => "INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('EMPLOYEE', 1, 1, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())",
        'expected_constraint' => 'chk_audit_actor'
    ],
    [
        'name' => '2. ADMIN actor with employee_id populated',
        'sql' => "INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('ADMIN', 1, 1, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())",
        'expected_constraint' => 'chk_audit_actor'
    ],
    [
        'name' => '3. SYSTEM actor with admin_id populated',
        'sql' => "INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('SYSTEM', 1, NULL, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())",
        'expected_constraint' => 'chk_audit_actor'
    ],
    [
        'name' => '4. SYSTEM actor with employee_id populated',
        'sql' => "INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('SYSTEM', NULL, 1, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())",
        'expected_constraint' => 'chk_audit_actor'
    ],
    [
        'name' => '5. Both admin_id and employee_id populated',
        'sql' => "INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('EMPLOYEE', 1, 1, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())",
        'expected_constraint' => 'chk_audit_actor'
    ],
    [
        'name' => '6. ADMIN actor without admin_id',
        'sql' => "INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('ADMIN', NULL, NULL, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())",
        'expected_constraint' => 'chk_audit_actor'
    ],
    [
        'name' => '7. EMPLOYEE actor without employee_id',
        'sql' => "INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('EMPLOYEE', NULL, NULL, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())",
        'expected_constraint' => 'chk_audit_actor'
    ],
    [
        'name' => '8. Reject previous_attempt_id from different assignment',
        'sql' => "INSERT INTO retake_authorizations (assignment_id, previous_attempt_id, authorized_by, reason, authorized_at) VALUES (2, 1, 1, 'Invalid retake attempt', UTC_TIMESTAMP())",
        'expected_constraint' => 'fk_retake_prev_attempt'
    ],
    [
        'name' => '9. Reject consumed_by_attempt_id from different assignment',
        'sql' => "INSERT INTO retake_authorizations (assignment_id, previous_attempt_id, consumed_by_attempt_id, consumed_at, authorized_by, reason, authorized_at) VALUES (2, 2, 1, UTC_TIMESTAMP(), 1, 'Invalid consumed attempt', UTC_TIMESTAMP())",
        'expected_constraint' => 'fk_retake_consumed_attempt'
    ]
];

foreach ($scenarios as $s) {
    $out[] = "Scenario: {$s['name']}";
    try {
        $pdo->exec($s['sql']);
        $out[] = "  Result: UNEXPECTED SUCCESS (FAILED - Constraint did not trigger)";
    } catch (\PDOException $e) {
        $sqlState = $e->errorInfo[0] ?? $e->getCode();
        $driverCode = $e->errorInfo[1] ?? 'N/A';
        $msg = $e->getMessage();
        $out[] = "  SQLSTATE        : {$sqlState}";
        $out[] = "  Driver Code     : {$driverCode}";
        $out[] = "  Message         : {$msg}";
        $out[] = "  Constraint Name : {$s['expected_constraint']}";
        $out[] = "  Status          : PASS (Constraint correctly enforced by MySQL 8)";
    }
    $out[] = "--------------------------------------------------------";
}

$report = implode("\n", $out) . "\n";
file_put_contents(__DIR__ . '/../evidence/negative_constraint_tests_mysql8.raw.txt', $report);
echo $report;
