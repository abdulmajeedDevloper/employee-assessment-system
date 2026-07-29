<?php

namespace Tests\Integration;

use App\Controllers\Admin\EmployeeController;
use App\Middleware\AdminAuthMiddleware;
use App\Middleware\EmployeeAuthMiddleware;
use App\Repositories\AdminEmployeeRepository;
use App\Repositories\EmployeeRepository;
use App\Security\CsrfTokenManager;
use App\Security\EncryptionService;
use App\Services\AdminEmployeeDirectoryService;
use App\Services\AdminEmployeeManagementService;
use App\Services\EmployeeAuthService;
use PDO;
use Tests\TestCase;

class AdminEmployeeArchiveRestoreTest extends TestCase
{
    private static ?PDO $pdo = null;
    private AdminEmployeeRepository $repository;
    private AdminEmployeeManagementService $managementService;
    private AdminEmployeeDirectoryService $directoryService;
    private EmployeeAuthService $employeeAuthService;
    private EncryptionService $encryptionService;

    public static function setUpBeforeClass(): void
    {
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

        \App\Support\Database::setInstance(self::$pdo);

        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $cols = self::$pdo->query("SHOW COLUMNS FROM audit_logs LIKE 'actor_type';")->fetchAll();
        if (empty($cols)) {
            $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
            self::$pdo->exec($sql1);
            $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
            self::$pdo->exec($sql2);
        }
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        self::$pdo->exec("TRUNCATE TABLE audit_logs;");
        self::$pdo->exec("TRUNCATE TABLE rate_limits;");
        self::$pdo->exec("TRUNCATE TABLE employees;");
        self::$pdo->exec("DELETE FROM job_titles WHERE id IN (901, 902);");

        self::$pdo->exec("DELETE FROM sections WHERE id IN (901, 902);");
        self::$pdo->exec("DELETE FROM departments WHERE id IN (901, 902);");
        self::$pdo->exec("DELETE FROM admins WHERE id IN (901, 902, 903);");
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Seed Admins
        $hash = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
        self::$pdo->exec("
            INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at)
            VALUES
            (901, 'Super Admin', 'super9@test.local', 'superadmin9', '{$hash}', 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (902, 'Supervisor Admin', 'superv9@test.local', 'supervisor9', '{$hash}', 'supervisor', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (903, 'Report Viewer', 'viewer9@test.local', 'viewer9', '{$hash}', 'report_viewer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed Departments, Sections, Job Titles
        self::$pdo->exec("
            INSERT INTO departments (id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES (901, 'تقنية المعلومات', 'IT Dept', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");
        self::$pdo->exec("
            INSERT INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES (901, 901, 'تطوير النظام', 'System Dev', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");
        self::$pdo->exec("
            INSERT INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES (901, 'مطور', 'Developer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        AdminAuthMiddleware::setRepository(new \App\Repositories\AdminRepository(self::$pdo));
        $this->repository = new AdminEmployeeRepository(self::$pdo);
        $this->encryptionService = new EncryptionService();
        $this->managementService = new AdminEmployeeManagementService($this->repository, $this->encryptionService, self::$pdo);
        $this->directoryService = new AdminEmployeeDirectoryService($this->repository);
        $this->employeeAuthService = new EmployeeAuthService(new EmployeeRepository(self::$pdo));

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo !== null) {
            self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            self::$pdo->exec("DELETE FROM employees;");
            self::$pdo->exec("DELETE FROM job_titles WHERE id IN (901, 902);");
            self::$pdo->exec("DELETE FROM sections WHERE id IN (901, 902);");
            self::$pdo->exec("DELETE FROM departments WHERE id IN (901, 902);");
            self::$pdo->exec("DELETE FROM admins WHERE id IN (901, 902, 903);");
            self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        }
    }

    private function createSampleEmployee(string $nationalId = '1099887766', bool $active = true, bool $archived = false): int
    {
        $uniqueSuffix = strtolower(bin2hex(random_bytes(6)));

        $data = [
            'full_name' => 'خالد العتيبي',
            'national_id' => $nationalId,
            'employee_number' => 'EMP-' . strtoupper($uniqueSuffix),
            'email' => 'khalid.' . $uniqueSuffix . '@test.com',
            'phone' => '0598765432',
            'department_id' => 901,
            'section_id' => 901,
            'job_title_id' => 901,
            'preferred_language' => 'ar',
            'is_active' => $active ? 1 : 0,
        ];

        $admin = ['id' => 901, 'role' => 'super_admin'];
        $res = $this->managementService->createEmployee($data, $admin);

        $this->assertTrue(
            $res['success'] ?? false,
            'Failed to create sample employee: ' .
            json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->assertArrayHasKey('employee_id', $res);

        $empId = (int) $res['employee_id'];

        $this->assertGreaterThan(
            0,
            $empId,
            'Created employee ID must be a positive integer.'
        );

        if ($archived) {
            $stmt = self::$pdo->prepare(
                'UPDATE employees
                 SET archived_at = UTC_TIMESTAMP(), is_active = 0
                 WHERE id = :id'
            );

            $stmt->execute([':id' => $empId]);

            $this->assertSame(
                1,
                $stmt->rowCount(),
                'Exactly one employee must be archived by the test fixture.'
            );
        }

        return $empId;
    }

    public function testAuthorizationForArchiveAndRestore(): void
    {
        // Unauthenticated -> Rejection
        $_SESSION = [];
        $adminRes = AdminAuthMiddleware::handle('edit_employee');
        $this->assertNull($adminRes);

        // Report Viewer -> Forbidden (403)
        $_SESSION['admin_auth'] = [
            'admin_id' => 903,
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];
        $adminRes = AdminAuthMiddleware::handle('edit_employee');
        $this->assertNull($adminRes);
        $this->assertEquals(403, http_response_code());

        // Supervisor -> Allowed
        $_SESSION['admin_auth'] = [
            'admin_id' => 902,
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];
        $adminRes = AdminAuthMiddleware::handle('edit_employee');
        $this->assertNotNull($adminRes);

        // Super Admin -> Allowed
        $_SESSION['admin_auth'] = [
            'admin_id' => 901,
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];
        $adminRes = AdminAuthMiddleware::handle('edit_employee');
        $this->assertNotNull($adminRes);
    }

    public function testGetMethodDoesNotMutate(): void
    {
        $empId = $this->createSampleEmployee('1099887711');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION['admin_auth'] = [
            'admin_id' => 901,
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];

        $controller = new EmployeeController();
        ob_start();
        $controller->archive();
        ob_end_clean();

        $this->assertEquals(405, http_response_code());

        $emp = $this->repository->findSafeById($empId);
        $this->assertNull($emp['archived_at']);
    }

    public function testCsrfValidation(): void
    {
        $validToken = CsrfTokenManager::getToken();
        $this->assertTrue(CsrfTokenManager::validate($validToken));
        $this->assertFalse(CsrfTokenManager::validate('invalid_csrf_token'));
        $this->assertFalse(CsrfTokenManager::validate(''));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = $validToken;
        $this->assertTrue(\App\Middleware\CsrfMiddleware::handle());
    }


    public function testStrictIdValidation(): void
    {
        $admin = ['id' => 901, 'role' => 'super_admin'];

        $invalidIds = [
            null,
            '',
            [],
            0,
            -5,
            '+10',
            '10.5',
            '1e2',
            '  10  ',
            'abc',
        ];

        foreach ($invalidIds as $invalidId) {
            $res = $this->managementService->archiveEmployee($invalidId, $admin);
            $this->assertFalse($res['success']);
            $this->assertEquals('INVALID_ID', $res['code']);

            $resRestore = $this->managementService->restoreEmployee($invalidId, $admin);
            $this->assertFalse($resRestore['success']);
            $this->assertEquals('INVALID_ID', $resRestore['code']);
        }

        // Nonexistent ID returns NOT_FOUND
        $resNotFound = $this->managementService->archiveEmployee(999999, $admin);
        $this->assertFalse($resNotFound['success']);
        $this->assertEquals('NOT_FOUND', $resNotFound['code']);
    }

    public function testArchiveEmployeeSuccess(): void
    {
        $nationalId = '1099887733';
        $empId = $this->createSampleEmployee($nationalId, true, false);
        $admin = ['id' => 901, 'role' => 'super_admin'];

        // 1. Employee authentication prior to archive
        $authBefore = $this->employeeAuthService->authenticate($nationalId, '127.0.0.1');
        $this->assertTrue($authBefore['success']);


        // 2. Perform archive
        $res = $this->managementService->archiveEmployee($empId, $admin);
        $this->assertTrue($res['success']);
        $this->assertEquals($empId, $res['employee_id']);

        // 3. Verify DB state: archived_at is non-null, is_active is 0
        $emp = $this->repository->findSafeById($empId);
        $this->assertNotNull($emp['archived_at']);
        $this->assertEquals(0, (int)$emp['is_active']);

        // 4. Verify future employee login is blocked
        $authAfter = $this->employeeAuthService->authenticate($nationalId, '127.0.0.1');
        $this->assertFalse($authAfter['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $authAfter['code']);



        // The redirecting Middleware cannot be invoked inside the current PHPUnit process because its normal HTTP redirect path intentionally exits. Future login blocking and database eligibility are verified automatically, while next-request session revocation must be verified through manual browser QA.

        // 5. Verify employee details remain viewable by Admin

        $details = $this->directoryService->getEmployeeDetails($empId);
        $this->assertTrue($details['success']);
        $this->assertNotNull($details['employee']);

        // 6. Verify employee edit update is rejected according to service policy
        $updateRes = $this->managementService->updateEmployee($empId, ['full_name' => 'Test Edit'], $admin);
        $this->assertFalse($updateRes['success']);
        $this->assertEquals('NOT_FOUND', $updateRes['code']);
        $editForm = $this->managementService->getFormData($empId);
        $this->assertNotEmpty($editForm['employee']['archived_at']);



        // 8. Verify ARCHIVE_EMPLOYEE audit log payload
        $stmtAudit = self::$pdo->prepare("SELECT * FROM audit_logs WHERE entity_id = :id AND action = 'ARCHIVE_EMPLOYEE'");
        $stmtAudit->execute([':id' => $empId]);
        $auditLogs = $stmtAudit->fetchAll();

        $this->assertCount(1, $auditLogs);
        $audit = $auditLogs[0];
        $this->assertEquals('ADMIN', $audit['actor_type']);
        $this->assertEquals(901, (int)$audit['admin_id']);
        $this->assertNull($audit['employee_id']);
        $this->assertEquals('EMPLOYEE', $audit['entity_type']);
        $this->assertEquals($empId, (int)$audit['entity_id']);

        $oldVals = json_decode($audit['old_values'], true);
        $newVals = json_decode($audit['new_values'], true);

        $this->assertEquals(1, $oldVals['is_active']);
        $this->assertEquals('not_archived', $oldVals['archive_status']);
        $this->assertEquals(0, $newVals['is_active']);
        $this->assertEquals('archived', $newVals['archive_status']);

        // Ensure safe payload (no national_id, ciphertext, tokens, etc.)
        $auditJsonString = json_encode($audit);
        $this->assertStringNotContainsString('national_id', $auditJsonString);
        $this->assertStringNotContainsString('ciphertext', $auditJsonString);
        $this->assertStringNotContainsString('password', $auditJsonString);
    }

    public function testArchiveIdempotency(): void
    {
        $empId = $this->createSampleEmployee('1099887744', true, true);
        $admin = ['id' => 901, 'role' => 'super_admin'];

        $empBefore = $this->repository->findSafeById($empId);
        $archivedAtBefore = $empBefore['archived_at'];

        $res = $this->managementService->archiveEmployee($empId, $admin);
        $this->assertFalse($res['success']);
        $this->assertEquals('ALREADY_ARCHIVED', $res['code']);

        $empAfter = $this->repository->findSafeById($empId);
        $this->assertEquals($archivedAtBefore, $empAfter['archived_at']);

        // Verify no extra ARCHIVE_EMPLOYEE audit entry inserted
        $stmtAudit = self::$pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_id = :id AND action = 'ARCHIVE_EMPLOYEE'");
        $stmtAudit->execute([':id' => $empId]);
        $this->assertEquals(0, (int)$stmtAudit->fetchColumn());
    }

    public function testRestoreEmployeeSuccess(): void
    {
        $nationalId = '1099887755';
        $empId = $this->createSampleEmployee($nationalId, true, true);
        $admin = ['id' => 901, 'role' => 'super_admin'];

        // Verify starting state
        $empBefore = $this->repository->findSafeById($empId);
        $this->assertNotNull($empBefore['archived_at']);

        // Perform restore
        $res = $this->managementService->restoreEmployee($empId, $admin);
        $this->assertTrue($res['success']);
        $this->assertEquals($empId, $res['employee_id']);

        // Verify DB state: archived_at is NULL, is_active remains 0 under Policy B
        $empAfter = $this->repository->findSafeById($empId);
        $this->assertNull($empAfter['archived_at']);
        $this->assertEquals(0, (int)$empAfter['is_active']);

        // Verify employee login remains blocked because account is inactive
        $authAfter = $this->employeeAuthService->authenticate($nationalId, '127.0.0.1');
        $this->assertFalse($authAfter['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $authAfter['code']);

        // Verify employee edit form lookup becomes available again
        $editForm = $this->managementService->getFormData($empId);
        $this->assertNotNull($editForm['employee']);

        // Verify RESTORE_EMPLOYEE audit log
        $stmtAudit = self::$pdo->prepare("SELECT * FROM audit_logs WHERE entity_id = :id AND action = 'RESTORE_EMPLOYEE'");
        $stmtAudit->execute([':id' => $empId]);
        $auditLogs = $stmtAudit->fetchAll();

        $this->assertCount(1, $auditLogs);
        $audit = $auditLogs[0];
        $this->assertEquals('ADMIN', $audit['actor_type']);
        $this->assertEquals(901, (int)$audit['admin_id']);
        $this->assertNull($audit['employee_id']);
        $this->assertEquals('EMPLOYEE', $audit['entity_type']);
        $this->assertEquals($empId, (int)$audit['entity_id']);

        $oldVals = json_decode($audit['old_values'], true);
        $newVals = json_decode($audit['new_values'], true);

        $this->assertEquals(0, $oldVals['is_active']);
        $this->assertEquals('archived', $oldVals['archive_status']);
        $this->assertEquals(0, $newVals['is_active']);
        $this->assertEquals('restored_inactive', $newVals['archive_status']);
    }

    public function testRestoreIdempotency(): void
    {
        $empId = $this->createSampleEmployee('1099887766', true, false);
        $admin = ['id' => 901, 'role' => 'super_admin'];

        $res = $this->managementService->restoreEmployee($empId, $admin);
        $this->assertFalse($res['success']);
        $this->assertEquals('NOT_ARCHIVED', $res['code']);

        // Verify no RESTORE_EMPLOYEE audit entry inserted
        $stmtAudit = self::$pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_id = :id AND action = 'RESTORE_EMPLOYEE'");
        $stmtAudit->execute([':id' => $empId]);
        $this->assertEquals(0, (int)$stmtAudit->fetchColumn());
    }


    public function testDirectoryArchivedFilter(): void
    {
        $activeEmpId = $this->createSampleEmployee('1099887777', true, false);
        $archivedEmpId = $this->createSampleEmployee('1099887788', false, true);

        // Filter status=archived
        $dirArchived = $this->directoryService->getDirectory(['status' => 'archived']);
        $itemsArchived = $dirArchived['results']['items'];
        $archivedIds = array_column($itemsArchived, 'id');

        $this->assertContains($archivedEmpId, $archivedIds);
        $this->assertNotContains($activeEmpId, $archivedIds);

        // Filter status=active
        $dirActive = $this->directoryService->getDirectory(['status' => 'active']);
        $itemsActive = $dirActive['results']['items'];
        $activeIds = array_column($itemsActive, 'id');

        $this->assertContains($activeEmpId, $activeIds);
        $this->assertNotContains($archivedEmpId, $activeIds);
    }

    public function testDirectoryActionLabelsAndPostFormsRendering(): void
    {
        $activeEmpId = $this->createSampleEmployee('1099887701', true, false);
        $archivedEmpId = $this->createSampleEmployee('1099887702', false, true);

        $_SESSION['admin_auth'] = [
            'admin_id' => 901,
            'role' => 'super_admin',
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];

        // 1. Render Arabic directory view
        $_GET = ['status' => 'all', 'lang' => 'ar'];
        $_SESSION['lang'] = 'ar';
        $controller = new EmployeeController();

        ob_start();
        $controller->index();
        $arOutput = ob_get_clean();

        // Assert Arabic labels
        $this->assertStringContainsString('التفاصيل', $arOutput);
        $this->assertStringContainsString('تعديل', $arOutput);
        $this->assertStringContainsString('أرشفة', $arOutput);
        $this->assertStringContainsString('استعادة', $arOutput);
        $this->assertStringNotContainsString('>edit<', $arOutput);

        // Assert POST forms for Archive and Restore in Arabic
        $this->assertStringContainsString('action="/admin/employees/archive"', $arOutput);
        $this->assertStringContainsString('action="/admin/employees/restore"', $arOutput);
        $this->assertStringContainsString('name="_csrf_token"', $arOutput);
        $this->assertStringNotContainsString('href="/admin/employees/archive', $arOutput);
        $this->assertStringNotContainsString('href="/admin/employees/restore', $arOutput);

        // 2. Render English directory view
        $_GET = ['status' => 'all', 'lang' => 'en'];
        $_SESSION['lang'] = 'en';

        ob_start();
        $controller->index();
        $enOutput = ob_get_clean();

        // Assert English labels
        $this->assertStringContainsString('Details', $enOutput);
        $this->assertStringContainsString('Edit', $enOutput);
        $this->assertStringContainsString('Archive', $enOutput);
        $this->assertStringContainsString('Restore', $enOutput);
        $this->assertStringContainsString('action="/admin/employees/archive"', $enOutput);
        $this->assertStringContainsString('action="/admin/employees/restore"', $enOutput);

        // Clean up global state
        unset($_SESSION['admin_auth'], $_SESSION['lang']);
        $_GET = [];
    }
}