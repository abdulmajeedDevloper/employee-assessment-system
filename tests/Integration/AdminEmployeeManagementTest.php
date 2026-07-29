<?php

namespace Tests\Integration;

use App\Middleware\AdminAuthMiddleware;
use App\Repositories\AdminEmployeeRepository;
use App\Security\CsrfTokenManager;
use App\Security\EncryptionService;
use App\Services\AdminEmployeeManagementService;
use App\Services\EmployeeAuthService;
use PDO;
use Tests\TestCase;

class AdminEmployeeManagementTest extends TestCase
{
    private static ?PDO $pdo = null;
    private AdminEmployeeRepository $repository;
    private AdminEmployeeManagementService $managementService;
    private EncryptionService $encryptionService;
    private EmployeeAuthService $authService;

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

        // Clear tables
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        self::$pdo->exec("TRUNCATE TABLE audit_logs;");
        self::$pdo->exec("TRUNCATE TABLE employees;");
        self::$pdo->exec("DELETE FROM job_titles WHERE id IN (801, 802);");
        self::$pdo->exec("DELETE FROM sections WHERE id IN (801, 802, 803);");
        self::$pdo->exec("DELETE FROM departments WHERE id IN (801, 802);");
        self::$pdo->exec("DELETE FROM admins WHERE id IN (801, 802, 803);");
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Seed Admins
        $hash = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
        self::$pdo->exec("
            INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at)
            VALUES
            (801, 'Super Admin', 'super@test.local', 'superadmin', '{$hash}', 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (802, 'Supervisor Admin', 'superv@test.local', 'supervisor1', '{$hash}', 'supervisor', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (803, 'Report Viewer', 'viewer@test.local', 'viewer1', '{$hash}', 'report_viewer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed Departments
        self::$pdo->exec("
            INSERT INTO departments (id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES
            (801, 'الهندسة والتقنية', 'Engineering & IT', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (802, 'العمليات', 'Operations', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed Sections
        self::$pdo->exec("
            INSERT INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES
            (801, 801, 'تطوير البرمجيات', 'Software Development', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (802, 801, 'ضمان الجودة', 'Quality Assurance', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (803, 802, 'الدعم الفني', 'Technical Support', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed Job Titles
        self::$pdo->exec("
            INSERT INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES
            (801, 'مهندس برمجيات', 'Software Engineer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (802, 'محلل جودة', 'QA Analyst', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        AdminAuthMiddleware::setRepository(new \App\Repositories\AdminRepository(self::$pdo));
        $this->repository = new AdminEmployeeRepository(self::$pdo);
        $this->encryptionService = new EncryptionService();
        $this->managementService = new AdminEmployeeManagementService($this->repository, $this->encryptionService, self::$pdo);
        $this->authService = new EmployeeAuthService(new \App\Repositories\EmployeeRepository(self::$pdo));

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
            self::$pdo->exec("DELETE FROM job_titles WHERE id IN (801, 802);");
            self::$pdo->exec("DELETE FROM sections WHERE id IN (801, 802, 803);");
            self::$pdo->exec("DELETE FROM departments WHERE id IN (801, 802);");
            self::$pdo->exec("DELETE FROM admins WHERE id IN (801, 802, 803);");
            self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        }
    }

    public function testAuthorizationRestrictionsForEmployeeManagement(): void
    {
        // 1. Unauthenticated -> Redirects to login
        $_SESSION = [];
        $res = AdminAuthMiddleware::handle('edit_employee');
        $this->assertNull($res);

        // 2. Report Viewer -> Forbidden HTTP 403
        $_SESSION['admin_auth'] = [
            'admin_id' => 803,
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];
        $res = AdminAuthMiddleware::handle('edit_employee');
        $this->assertNull($res);
        $this->assertEquals(403, http_response_code());

        // 3. Supervisor -> Allowed
        $_SESSION['admin_auth'] = [
            'admin_id' => 802,
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];
        $res = AdminAuthMiddleware::handle('edit_employee');
        $this->assertNotNull($res);

        // 4. Super Admin -> Allowed
        $_SESSION['admin_auth'] = [
            'admin_id' => 801,
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];
        $res = AdminAuthMiddleware::handle('edit_employee');
        $this->assertNotNull($res);
    }

    public function testSuccessfulEmployeeCreationAndAuthentication(): void
    {
        $admin = ['id' => 801, 'name' => 'Super Admin', 'role' => 'super_admin'];

        $input = [
            'full_name' => 'أحمد محمود العلي',
            'national_id' => '1029384756',
            'employee_number' => 'EMP-1001',
            'email' => 'ahmed.ali@example.com',
            'phone' => '0512345678',
            'department_id' => '801',
            'section_id' => '801',
            'job_title_id' => '801',
            'preferred_language' => 'ar',
            'is_active' => '1',
        ];

        $res = $this->managementService->createEmployee($input, $admin);

        $this->assertTrue($res['success']);
        $this->assertArrayHasKey('employee_id', $res);
        $newId = (int)$res['employee_id'];
        $this->assertGreaterThan(0, $newId);

        // Verify stored safe fields
        $emp = $this->repository->findSafeById($newId);
        $this->assertNotNull($emp);
        $this->assertEquals('أحمد محمود العلي', $emp['full_name']);
        $this->assertEquals('EMP-1001', $emp['employee_number']);
        $this->assertEquals('ahmed.ali@example.com', $emp['email']);
        $this->assertEquals('+966512345678', $emp['phone']);
        $this->assertEquals('4756', $emp['national_id_last4']);
        $this->assertEquals(1, (int)$emp['is_active']);

        // Verify Employee can authenticate with raw National ID
        $authRes = $this->authService->authenticate('1029384756', '127.0.0.1');
        $this->assertTrue($authRes['success']);
        $this->assertEquals($newId, (int)$authRes['employee']['id']);

        // Verify Audit Log
        $stmt = self::$pdo->query("SELECT * FROM audit_logs WHERE action = 'CREATE_EMPLOYEE' AND entity_id = {$newId}");
        $audit = $stmt->fetch();
        $this->assertNotFalse($audit);
        $this->assertEquals('ADMIN', $audit['actor_type']);
        $this->assertEquals(801, (int)$audit['admin_id']);
        $this->assertNull($audit['employee_id']);
        $this->assertEquals('EMPLOYEE', $audit['entity_type']);

        $newValues = json_decode($audit['new_values'], true);
        $this->assertIsArray($newValues);
        $this->assertEquals('أحمد محمود العلي', $newValues['full_name']);
        $this->assertArrayNotHasKey('national_id', $newValues);
        $this->assertArrayNotHasKey('national_id_hash', $newValues);
        $this->assertArrayNotHasKey('national_id_ciphertext', $newValues);
    }

    public function testInactiveEmployeeCreationBlocksLogin(): void
    {
        $admin = ['id' => 801, 'name' => 'Super Admin'];

        $input = [
            'full_name' => 'خالد سعيد',
            'national_id' => '1099887766',
            'employee_number' => 'EMP-1002',
            'email' => 'khalid@example.com',
            'phone' => '0555555555',
            'department_id' => '801',
            'section_id' => '802',
            'job_title_id' => '802',
            'preferred_language' => 'ar',
            'is_active' => '0', // Inactive
        ];

        $res = $this->managementService->createEmployee($input, $admin);
        $this->assertTrue($res['success']);

        // Authenticate attempt must fail
        $authRes = $this->authService->authenticate('1099887766', '127.0.0.1');
        $this->assertFalse($authRes['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $authRes['code']);
    }

    public function testCreationRejectsDuplicateNationalIdAndEmployeeNumber(): void
    {
        $admin = ['id' => 801, 'name' => 'Super Admin'];

        $input1 = [
            'full_name' => 'موظف أول',
            'national_id' => '1011111111',
            'employee_number' => 'EMP-DUP',
            'email' => 'emp1@example.com',
            'phone' => '0511111111',
            'department_id' => '801',
            'section_id' => '801',
            'job_title_id' => '801',
            'preferred_language' => 'ar',
            'is_active' => '1',
        ];

        $res1 = $this->managementService->createEmployee($input1, $admin);
        $this->assertTrue($res1['success']);

        // Duplicate National ID
        $inputDupId = $input1;
        $inputDupId['employee_number'] = 'EMP-NEW';
        $inputDupId['email'] = 'emp2@example.com';
        $resDupId = $this->managementService->createEmployee($inputDupId, $admin);
        $this->assertFalse($resDupId['success']);
        $this->assertEquals('VALIDATION_FAILED', $resDupId['code']);
        $this->assertArrayHasKey('national_id', $resDupId['errors']);

        // Duplicate Employee Number
        $inputDupNum = $input1;
        $inputDupNum['national_id'] = '1022222222';
        $inputDupNum['email'] = 'emp3@example.com';
        $resDupNum = $this->managementService->createEmployee($inputDupNum, $admin);
        $this->assertFalse($resDupNum['success']);
        $this->assertEquals('VALIDATION_FAILED', $resDupNum['code']);
        $this->assertArrayHasKey('employee_number', $resDupNum['errors']);
    }

    public function testCreationRejectsSectionToDepartmentMismatch(): void
    {
        $admin = ['id' => 801, 'name' => 'Super Admin'];

        // Section 803 belongs to Department 802, but we pass Department 801
        $input = [
            'full_name' => 'اختبار عدم تطابق القسم',
            'national_id' => '1033333333',
            'employee_number' => 'EMP-MISMATCH',
            'email' => 'mismatch@example.com',
            'phone' => '0533333333',
            'department_id' => '801',
            'section_id' => '803', // Department 802
            'job_title_id' => '801',
            'preferred_language' => 'ar',
            'is_active' => '1',
        ];

        $res = $this->managementService->createEmployee($input, $admin);
        $this->assertFalse($res['success']);
        $this->assertEquals('VALIDATION_FAILED', $res['code']);
        $this->assertArrayHasKey('section_id', $res['errors']);
    }

    public function testEmployeeEditingWithoutNationalIdReplacementPreservesCredentials(): void
    {
        $admin = ['id' => 801, 'name' => 'Super Admin'];

        $inputCreate = [
            'full_name' => 'موظف للتعديل',
            'national_id' => '1044444444',
            'employee_number' => 'EMP-EDIT',
            'email' => 'edit.orig@example.com',
            'phone' => '0544444444',
            'department_id' => '801',
            'section_id' => '801',
            'job_title_id' => '801',
            'preferred_language' => 'ar',
            'is_active' => '1',
        ];

        $createRes = $this->managementService->createEmployee($inputCreate, $admin);
        $empId = (int)$createRes['employee_id'];

        // Fetch raw byte values before edit
        $stmtBefore = self::$pdo->query("SELECT national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4 FROM employees WHERE id = {$empId}");
        $bytesBefore = $stmtBefore->fetch();

        // Edit employee without replacing national_id
        $inputEdit = [
            'full_name' => 'موظف معدل الاسم',
            'employee_number' => 'EMP-EDIT-UPDATED',
            'email' => 'edit.updated@example.com',
            'phone' => '0544444499',
            'department_id' => '801',
            'section_id' => '802',
            'job_title_id' => '802',
            'preferred_language' => 'en',
            'is_active' => '1',
            'replace_national_id' => '0',
        ];

        $editRes = $this->managementService->updateEmployee($empId, $inputEdit, $admin);
        $this->assertTrue($editRes['success']);

        // Verify updated safe fields
        $empAfter = $this->repository->findSafeById($empId);
        $this->assertEquals('موظف معدل الاسم', $empAfter['full_name']);
        $this->assertEquals('EMP-EDIT-UPDATED', $empAfter['employee_number']);
        $this->assertEquals('edit.updated@example.com', $empAfter['email']);
        $this->assertEquals('en', $empAfter['preferred_language']);

        // Fetch raw byte values after edit -> MUST BE BYTE-FOR-BYTE IDENTICAL
        $stmtAfter = self::$pdo->query("SELECT national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4 FROM employees WHERE id = {$empId}");
        $bytesAfter = $stmtAfter->fetch();

        $this->assertEquals($bytesBefore['national_id_hash'], $bytesAfter['national_id_hash']);
        $this->assertEquals($bytesBefore['national_id_ciphertext'], $bytesAfter['national_id_ciphertext']);
        $this->assertEquals($bytesBefore['national_id_iv'], $bytesAfter['national_id_iv']);
        $this->assertEquals($bytesBefore['national_id_tag'], $bytesAfter['national_id_tag']);
        $this->assertEquals($bytesBefore['national_id_last4'], $bytesAfter['national_id_last4']);

        // Verify login still works with original National ID
        $authRes = $this->authService->authenticate('1044444444', '127.0.0.1');
        $this->assertTrue($authRes['success']);
    }

    public function testEmployeeEditingWithNationalIdReplacementUpdatesCredentialsAtomically(): void
    {
        $admin = ['id' => 801, 'name' => 'Super Admin'];

        $inputCreate = [
            'full_name' => 'موظف استبدال الهوية',
            'national_id' => '1055555555',
            'employee_number' => 'EMP-REPLACE',
            'email' => 'replace@example.com',
            'phone' => '0555555555',
            'department_id' => '801',
            'section_id' => '801',
            'job_title_id' => '801',
            'preferred_language' => 'ar',
            'is_active' => '1',
        ];

        $createRes = $this->managementService->createEmployee($inputCreate, $admin);
        $empId = (int)$createRes['employee_id'];

        // Edit employee WITH national_id replacement
        $inputEdit = [
            'full_name' => 'موظف استبدال الهوية',
            'employee_number' => 'EMP-REPLACE',
            'email' => 'replace@example.com',
            'phone' => '0555555555',
            'department_id' => '801',
            'section_id' => '801',
            'job_title_id' => '801',
            'preferred_language' => 'ar',
            'is_active' => '1',
            'replace_national_id' => '1',
            'national_id' => '1099999999', // New National ID
        ];

        $editRes = $this->managementService->updateEmployee($empId, $inputEdit, $admin);
        $this->assertTrue($editRes['success']);

        // Old National ID login MUST FAIL
        $oldAuth = $this->authService->authenticate('1055555555', '127.0.0.1');
        $this->assertFalse($oldAuth['success']);

        // New National ID login MUST SUCCEED
        $newAuth = $this->authService->authenticate('1099999999', '127.0.0.1');
        $this->assertTrue($newAuth['success']);
        $this->assertEquals($empId, (int)$newAuth['employee']['id']);

        // Verify last4 updated
        $empAfter = $this->repository->findSafeById($empId);
        $this->assertEquals('9999', $empAfter['national_id_last4']);
    }

    public function testCsrfProtectionRejectsInvalidToken(): void
    {
        $token = CsrfTokenManager::getToken();
        $this->assertTrue(CsrfTokenManager::validate($token));
        $this->assertFalse(CsrfTokenManager::validate('invalid_csrf_token'));
    }

    public function testNewNationalIdLocalizationKeys(): void
    {
        $_SESSION['lang'] = 'ar';
        $this->assertEquals('رقم الهوية الوطنية الجديد', trans('new_national_id'));

        $_SESSION['lang'] = 'en';
        $this->assertEquals('New National ID Number', trans('new_national_id'));
    }
}
