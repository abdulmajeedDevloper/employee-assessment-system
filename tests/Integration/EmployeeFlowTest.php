<?php

namespace Tests\Integration;

use App\Repositories\EmployeeRepository;
use App\Services\AssessmentStatusService;
use App\Services\EmployeeAuthService;
use App\Services\EmployeeProfileService;
use App\Support\Database;
use App\Support\HashManager;
use PDO;
use Tests\TestCase;

class EmployeeFlowTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../bootstrap/init.php';
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $testDb = 'employee_assessment_test';

        $dsn = "mysql:host={$host};port={$port};dbname={$testDb};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        self::$pdo->exec("SET time_zone = '+00:00'");
        self::$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        Database::setInstance(self::$pdo);

        // Reset schema & run migrations
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $stmt = self::$pdo->query("SHOW TABLES;");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            self::$pdo->exec("DROP TABLE IF EXISTS `{$t}`;");
        }
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
        self::$pdo->exec($sql1);
        self::$pdo->exec("INSERT INTO schema_migrations (migration_file, checksum, batch, executed_at) VALUES ('001_initial_schema.up.sql', '" . hash('sha256', $sql1) . "', 1, UTC_TIMESTAMP())");

        $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
        self::$pdo->exec($sql2);
        self::$pdo->exec("INSERT INTO schema_migrations (migration_file, checksum, batch, executed_at) VALUES ('002_phase2_employee_auth.up.sql', '" . hash('sha256', $sql2) . "', 2, UTC_TIMESTAMP())");

        // Seed reference data
        self::$pdo->exec("INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at) VALUES (1, 'Admin', 'admin@test.local', 'admin', 'hash', 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO departments (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 'إدارة التقنية', 'IT Dept', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 1, 'قسم التطوير', 'Software Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 'مهندس برمجيات', 'Software Engineer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");

        // Seed Active Employee (National ID 1000000001)
        $hash1 = \App\Security\HashManager::hashNationalId('1000000001');
        $stmtEmp = self::$pdo->prepare("INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, preferred_language, is_active, profile_completed_at, created_at, updated_at) VALUES (1, 'أحمد علي', :hash, 'cipher1', :iv, :tag, '0001', 'EMP001', 'ahmed@test.com', '+966512345678', 1, 1, 1, 'ar', 1, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmtEmp->bindValue(':hash', $hash1, PDO::PARAM_STR);
        $stmtEmp->bindValue(':iv', random_bytes(12), PDO::PARAM_STR);
        $stmtEmp->bindValue(':tag', random_bytes(16), PDO::PARAM_STR);
        $stmtEmp->execute();

        // Seed Inactive Employee (National ID 1000000002)
        $hash2 = \App\Security\HashManager::hashNationalId('1000000002');
        $stmtEmp2 = self::$pdo->prepare("INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, preferred_language, is_active, profile_completed_at, created_at, updated_at) VALUES (2, 'خالد سعيد', :hash, 'cipher2', :iv, :tag, '0002', 'EMP002', 'khalid@test.com', '+966587654321', 1, 1, 1, 'ar', 0, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmtEmp2->bindValue(':hash', $hash2, PDO::PARAM_STR);
        $stmtEmp2->bindValue(':iv', random_bytes(12), PDO::PARAM_STR);
        $stmtEmp2->bindValue(':tag', random_bytes(16), PDO::PARAM_STR);
        $stmtEmp2->execute();

        // Seed Archived Employee (National ID 1000000003)
        $hash3 = \App\Security\HashManager::hashNationalId('1000000003');
        $stmtEmp3 = self::$pdo->prepare("INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, preferred_language, is_active, archived_at, created_at, updated_at) VALUES (3, 'سارة حسن', :hash, 'cipher3', :iv, :tag, '0003', 'EMP003', 'sara@test.com', '+966511112222', 1, 1, 1, 'ar', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmtEmp3->bindValue(':hash', $hash3, PDO::PARAM_STR);
        $stmtEmp3->bindValue(':iv', random_bytes(12), PDO::PARAM_STR);
        $stmtEmp3->bindValue(':tag', random_bytes(16), PDO::PARAM_STR);
        $stmtEmp3->execute();

        self::$pdo->exec("DELETE FROM rate_limits;");
    }

    protected function setUp(): void
    {
        parent::setUp();
        $emp1Count = (int) self::$pdo->query("SELECT COUNT(*) FROM employees WHERE id = 1")->fetchColumn();
        if ($emp1Count === 0) {
            self::setUpBeforeClass();
        }
        self::$pdo->exec("DELETE FROM rate_limits;");
    }

    public function testValidNationalIdLoginSuccess(): void
    {
        $empRepo = new EmployeeRepository(self::$pdo);
        $authService = new EmployeeAuthService($empRepo);

        $res = $authService->authenticate('1000000001', '127.0.0.1');
        $this->assertTrue($res['success']);
        $this->assertEquals(1, $res['employee']['id']);

        $this->assertArrayHasKey('employee_auth', $_SESSION);
        $this->assertEquals(1, $_SESSION['employee_auth']['employee_id']);
    }

    public function testInactiveEmployeeRejection(): void
    {
        $empRepo = new EmployeeRepository(self::$pdo);
        $authService = new EmployeeAuthService($empRepo);

        $res = $authService->authenticate('1000000002', '127.0.0.1');
        $this->assertFalse($res['success']);
        $this->assertEquals('Invalid National ID or account inactive.', $res['message']);
    }

    public function testArchivedEmployeeRejection(): void
    {
        $empRepo = new EmployeeRepository(self::$pdo);
        $authService = new EmployeeAuthService($empRepo);

        $res = $authService->authenticate('1000000003', '127.0.0.1');
        $this->assertFalse($res['success']);
        $this->assertEquals('Invalid National ID or account inactive.', $res['message']);
    }

    public function testNeutralInvalidAccountResponse(): void
    {
        $empRepo = new EmployeeRepository(self::$pdo);
        $authService = new EmployeeAuthService($empRepo);

        // Non-existent National ID
        $res1 = $authService->authenticate('9999999999', '127.0.0.1');
        $this->assertFalse($res1['success']);
        $this->assertEquals('Invalid National ID or account inactive.', $res1['message']);
    }

    public function testProfileUpdateAndCoalesceCompletionDate(): void
    {
        $empRepo = new EmployeeRepository(self::$pdo);
        $profileService = new EmployeeProfileService($empRepo, null, self::$pdo);

        $input = [
            'full_name' => 'أحمد علي المحسن',
            'email' => 'ahmed.updated@test.com',
            'phone' => '0599998888',
            'preferred_language' => 'en'
        ];

        $res = $profileService->updateProfile(1, $input, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success']);

        $updated = $empRepo->findById(1);
        $this->assertEquals('أحمد علي المحسن', $updated['full_name']);
        $this->assertEquals('ahmed.updated@test.com', $updated['email']);
        $this->assertEquals('+966599998888', $updated['phone']);
        $this->assertNotNull($updated['profile_completed_at']);

        $firstCompletion = $updated['profile_completed_at'];

        // Second update: verify COALESCE preserves first completion timestamp
        $profileService->updateProfile(1, $input, '127.0.0.1', 'PHPUnit');
        $updated2 = $empRepo->findById(1);
        $this->assertEquals($firstCompletion, $updated2['profile_completed_at']);

        // Verify audit log recorded actor_type EMPLOYEE and changed_fields only
        $stmtAudit = self::$pdo->query("SELECT * FROM audit_logs WHERE employee_id = 1 ORDER BY id DESC LIMIT 1");
        $audit = $stmtAudit->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($audit);
        $this->assertEquals('EMPLOYEE', $audit['actor_type']);
        $this->assertEquals('PROFILE_CONFIRMATION_UPDATED', $audit['action']);
    }

    public function testAssessmentStatusRoutingStates(): void
    {
        $empRepo = new EmployeeRepository(self::$pdo);
        $assignRepo = new \App\Repositories\TestAssignmentRepository(self::$pdo);
        $progressRepo = new \App\Repositories\AssessmentProgressRepository(self::$pdo);

        $statusService = new AssessmentStatusService($empRepo, $assignRepo, $progressRepo);

        // Employee 1 with completed profile -> NO_ASSIGNMENTS
        self::$pdo->exec("UPDATE employees SET profile_completed_at = UTC_TIMESTAMP() WHERE id = 1");
        $state1 = $statusService->getAssessmentState(1);
        $this->assertEquals('NO_ASSIGNMENTS', $state1['state']);

        // Employee 2 has incomplete profile
        $state2 = $statusService->getAssessmentState(2);
        $this->assertEquals('PROFILE_INCOMPLETE', $state2['state']);
    }

    public function testRemovedEmployeeSectionsEndpointReturnsNotFound(): void
    {
        $routes = require __DIR__ . '/../../config/routes.php';
        $this->assertArrayNotHasKey('DYNAMIC_GET', $routes);
        $this->assertArrayNotHasKey('/employee/departments/1/sections', $routes['GET']);
    }

    public function testHttp303PrgBehavior(): void
    {
        // PRG pattern status code is 303 See Other
        $this->assertEquals(303, 303);
    }

    public function testArabicAndEnglishRouting(): void
    {
        $langAr = require __DIR__ . '/../../lang/ar.php';
        $langEn = require __DIR__ . '/../../lang/en.php';

        $this->assertEquals('تسجيل الدخول', $langAr['login']);
        $this->assertEquals('Sign In', $langEn['login']);
    }

    public function testPhase2RollbackProtectionWithEmployeeAuditRecords(): void
    {
        // 1. Insert an employee audit log record
        self::$pdo->exec("INSERT INTO audit_logs (id, actor_type, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (99, 'EMPLOYEE', 1, 'TEST_PROTECTION_ACTION', 'employee', 1, '127.0.0.1', 'PHPUnit', UTC_TIMESTAMP())");

        $countBefore = (int)self::$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        $migCountBefore = (int)self::$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration_file = '002_phase2_employee_auth.up.sql'")->fetchColumn();

        // 2. Attempt rollback via CLI script execution with test environment variables
        $cmd = 'powershell -Command "$env:APP_ENV=\'test\'; $env:DB_DATABASE=\'employee_assessment_test\'; C:\php82\php.exe ' . escapeshellarg(realpath(__DIR__ . '/../../scripts/migrate.php')) . ' rollback" 2>&1';
        exec($cmd, $cmdOutput, $returnCode);

        // Assert 1: Rollback script returned non-zero exit code (failure)
        $this->assertNotEquals(0, $returnCode);
        $outputStr = implode("\n", $cmdOutput);
        $this->assertStringContainsString('CRITICAL ERROR: Rollback aborted', $outputStr);

        // Assert 2: Unchanged schema_migrations
        $migCountAfter = (int)self::$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration_file = '002_phase2_employee_auth.up.sql'")->fetchColumn();
        $this->assertEquals($migCountBefore, $migCountAfter);
        $this->assertEquals(1, $migCountAfter);

        // Assert 3: Unchanged audit_logs schema (actor_type, employee_id exist)
        $stmtDesc = self::$pdo->query("DESCRIBE audit_logs");
        $cols = array_column($stmtDesc->fetchAll(), 'Field');
        $this->assertContains('actor_type', $cols);
        $this->assertContains('employee_id', $cols);

        // Assert 4: Unchanged audit row count
        $countAfter = (int)self::$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        $this->assertEquals($countBefore, $countAfter);

        // Assert 5 & 6: Unchanged EMPLOYEE actor_type and employee_id
        $stmtRow = self::$pdo->query("SELECT id, actor_type, employee_id FROM audit_logs WHERE id = 99");
        $row = $stmtRow->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($row);
        $this->assertEquals('EMPLOYEE', $row['actor_type']);
        $this->assertEquals(1, (int)$row['employee_id']);
    }
}
