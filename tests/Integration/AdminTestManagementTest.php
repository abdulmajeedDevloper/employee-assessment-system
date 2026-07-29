<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Support\Database;
use App\Services\AuthorizationService;
use App\Services\AdminTestManagementService;
use App\Validators\AdminTestFormValidator;
use App\Repositories\AdminTestRepository;
use App\Controllers\Admin\TestController;
use PDO;

class AdminTestManagementTest extends TestCase
{
    private PDO $pdo;
    private AdminTestRepository $repository;
    private AdminTestManagementService $managementService;
    private AdminTestFormValidator $validator;
    private TestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Database::getInstance();
        $this->repository = new AdminTestRepository($this->pdo);
        $this->validator = new AdminTestFormValidator();
        $this->managementService = new AdminTestManagementService($this->repository, $this->validator, $this->pdo);
        $this->controller = new TestController(null, $this->managementService, $this->repository);

        $_SESSION = [];
        $_POST = [];
        $_GET = [];

        // Clean up test data safely
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->pdo->exec("DELETE FROM audit_logs WHERE entity_type = 'TEST'");
        $this->pdo->exec("DELETE FROM test_attempts WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-%')");
        $this->pdo->exec("DELETE FROM test_assignments WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-%')");
        $this->pdo->exec("DELETE FROM tests WHERE code LIKE 'UNIT-%'");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    private function getValidAdminId(): int
    {
        $adminId = $this->pdo->query("SELECT id FROM admins LIMIT 1")->fetchColumn();
        if ($adminId) {
            return (int)$adminId;
        }

        $hash = password_hash('AdminPass123!', PASSWORD_BCRYPT);
        $this->pdo->exec("
            INSERT INTO admins (username, email, password_hash, name, role, is_active, created_at, updated_at)
            VALUES ('unit_admin', 'unit@test.local', '{$hash}', 'Unit Admin', 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        return (int)$this->pdo->lastInsertId();
    }

    private function getValidEmployeeId(): int
    {
        $empId = $this->pdo->query("SELECT id FROM employees LIMIT 1")->fetchColumn();
        if ($empId) {
            return (int)$empId;
        }

        $this->pdo->exec("INSERT IGNORE INTO departments (id, name_ar, name_en, created_at, updated_at) VALUES (1, 'قسم', 'Dept', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $this->pdo->exec("INSERT IGNORE INTO sections (id, department_id, name_ar, name_en, created_at, updated_at) VALUES (1, 1, 'شعبة', 'Sec', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $this->pdo->exec("INSERT IGNORE INTO job_titles (id, name_ar, name_en, created_at, updated_at) VALUES (1, 'مسمى', 'Title', UTC_TIMESTAMP(), UTC_TIMESTAMP())");

        $hash = random_bytes(32);
        $iv = random_bytes(12);
        $tag = random_bytes(16);

        $stmt = $this->pdo->prepare("
            INSERT INTO employees (
                full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag,
                key_version, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, created_at, updated_at
            ) VALUES (
                'Unit Employee', :hash, 'cipher', :iv, :tag,
                1, '1234', 'EMP-UNIT-1', 'unit@emp.local', '0500000000', 1, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )
        ");
        $stmt->bindValue(':hash', $hash);
        $stmt->bindValue(':iv', $iv);
        $stmt->bindValue(':tag', $tag);
        $stmt->execute();

        return (int)$this->pdo->lastInsertId();
    }

    private function createAdminSession(int $id = 1, string $role = 'super_admin'): void
    {
        $_SESSION['admin_auth'] = [
            'id' => $id,
            'username' => 'admin_test',
            'role' => $role,
            'is_active' => 1,
            'auth_time' => time(),
        ];
    }

    // ==================================================
    // 1. AUTHORIZATION & ROLES
    // ==================================================

    public function testAuthorizationPermissionsContract(): void
    {
        $superAdmin = ['role' => 'super_admin', 'is_active' => 1];
        $supervisor = ['role' => 'supervisor', 'is_active' => 1];
        $reportViewer = ['role' => 'report_viewer', 'is_active' => 1];

        $this->assertTrue(AuthorizationService::can($superAdmin, 'manage_tests'));
        $this->assertTrue(AuthorizationService::can($supervisor, 'manage_tests'));
        $this->assertFalse(AuthorizationService::can($reportViewer, 'manage_tests'));

        // view_tests alone does not grant manage_tests
        $this->assertTrue(AuthorizationService::can($supervisor, 'view_tests'));
        $this->assertFalse(AuthorizationService::can($reportViewer, 'manage_tests'));
    }

    // ==================================================
    // 2. VALIDATOR TESTS
    // ==================================================

    public function testValidatorCodeNormalizationAndValidation(): void
    {
        // Lowercase normalized to uppercase
        $input = [
            'code' => 'unit-test-01',
            'name_ar' => 'اختبار تجريبي',
            'name_en' => 'Unit Test 01',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60',
            'display_order' => '1',
        ];
        $res = $this->validator->validate($input);
        $this->assertTrue($res['valid']);
        $this->assertEquals('UNIT-TEST-01', $res['data']['code']);

        // Invalid codes
        $invalidCodes = [
            '', // empty
            'AB', // under length (<3)
            str_repeat('A', 65), // over length (>64)
            'TEST CODE', // space
            'اختبار', // Arabic
            '<script>', // HTML
            'TEST@CODE', // invalid symbol @
            "TEST\x202ACODE", // bidi control
        ];

        foreach ($invalidCodes as $badCode) {
            $input['code'] = $badCode;
            $res = $this->validator->validate($input);
            $this->assertFalse($res['valid'], "Code '$badCode' should fail validation");
            $this->assertArrayHasKey('code', $res['errors']);
        }
    }

    public function testValidatorNameAndContentValidation(): void
    {
        $base = [
            'code' => 'UNIT-VALNAME-01',
            'name_ar' => 'اختبار تجريبي',
            'name_en' => 'Unit Test 01',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60',
            'display_order' => '1',
        ];

        // Missing names
        $invalidAr = $base;
        $invalidAr['name_ar'] = 'A'; // under length (<2)
        $res = $this->validator->validate($invalidAr);
        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('name_ar', $res['errors']);

        // Descriptions & Instructions optional conversion
        $withOptional = array_merge($base, [
            'description_ar' => '  ',
            'description_en' => 'Description text',
            'instructions_ar' => "Line 1\nLine 2",
            'instructions_en' => null,
        ]);
        $res = $this->validator->validate($withOptional);
        $this->assertTrue($res['valid']);
        $this->assertNull($res['data']['description_ar']);
        $this->assertEquals('Description text', $res['data']['description_en']);
        $this->assertEquals("Line 1\nLine 2", $res['data']['instructions_ar']);
        $this->assertNull($res['data']['instructions_en']);
    }

    public function testValidatorStrictNumericValidation(): void
    {
        $base = [
            'code' => 'UNIT-VALNUM-01',
            'name_ar' => 'اختبار تجريبي',
            'name_en' => 'Unit Test 01',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];

        // Invalid numbers
        $invalidNumbers = [
            'duration_minutes' => ['0', '1441', '-10', '+60', '60.5', 'abc', [60]],
            'question_count' => ['0', '501', '10.5', 'xyz'],
            'display_order' => ['0', '10000', '1.5'],
            'passing_percentage' => ['-1', '100.001', '101', 'NaN', 'INF', '60.50.1', '60%'],
        ];

        foreach ($invalidNumbers as $field => $badValues) {
            foreach ($badValues as $badVal) {
                $input = $base;
                $input[$field] = $badVal;
                $res = $this->validator->validate($input);
                $valStr = is_array($badVal) ? 'array' : (string)$badVal;
                $this->assertFalse($res['valid'], "Field '$field' with value '$valStr' should fail");
                $this->assertArrayHasKey($field, $res['errors']);
            }
        }

        // Passing percentage two-decimal string normalization
        $validPercentages = [
            '0' => '0.00',
            '0.0' => '0.00',
            '25.5' => '25.50',
            '100' => '100.00',
            '100.00' => '100.00',
        ];
        foreach ($validPercentages as $raw => $expected) {
            $input = $base;
            $input['passing_percentage'] = $raw;
            $res = $this->validator->validate($input);
            $this->assertTrue($res['valid']);
            $this->assertEquals($expected, $res['data']['passing_percentage']);
        }
    }

    public function testValidatorBooleanValidation(): void
    {
        $base = [
            'code' => 'UNIT-VALBOOL-01',
            'name_ar' => 'اختبار تجريبي',
            'name_en' => 'Unit Test 01',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];

        // Missing checkbox maps to 0
        $res = $this->validator->validate($base);
        $this->assertTrue($res['valid']);
        $this->assertEquals(0, $res['data']['randomize_questions']);
        $this->assertEquals(0, $res['data']['randomize_options']);

        // "1" maps to 1
        $base['randomize_questions'] = '1';
        $res = $this->validator->validate($base);
        $this->assertTrue($res['valid']);
        $this->assertEquals(1, $res['data']['randomize_questions']);

        // Invalid values
        $base['randomize_questions'] = 'yes';
        $res = $this->validator->validate($base);
        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('randomize_questions', $res['errors']);
    }

    // ==================================================
    // 3. ASSESSMENT CREATION & FORCED INACTIVE DEFAULTS
    // ==================================================

    public function testCreateTestForcesInactiveLifecycle(): void
    {
        $adminId = $this->getValidAdminId();
        $this->createAdminSession($adminId, 'super_admin');

        $input = [
            'code' => 'UNIT-CREAT-01',
            'name_ar' => 'اختبار التنسيق الجديد',
            'name_en' => 'New Assessment Test',
            'description_ar' => 'وصف الاختبار',
            'description_en' => 'Assessment Description',
            'instructions_ar' => 'تعليمات',
            'instructions_en' => 'Instructions',
            'duration_minutes' => '45',
            'question_count' => '15',
            'passing_percentage' => '75.5',
            'display_order' => '2',
            'randomize_questions' => '1',
            'randomize_options' => '1',
            'allow_back_navigation' => '1',
            'show_result_to_employee' => '1',
            // Attempt to override lifecycle defaults:
            'is_active' => '1',
            'available_from' => '2026-01-01 00:00:00',
            'available_until' => '2026-12-31 23:59:59',
        ];

        $res = $this->managementService->createTest($input, $adminId);
        $this->assertEquals('SUCCESS', $res['code']);
        $newId = $res['test_id'];

        // Verify row directly in DB
        $row = $this->repository->findSafeById($newId);
        $this->assertNotNull($row);
        $this->assertEquals(0, (int)$row['is_active'], 'is_active must be forced to 0');
        $this->assertNull($row['available_from'], 'available_from must be forced to NULL');
        $this->assertNull($row['available_until'], 'available_until must be forced to NULL');
        $this->assertEquals('UNIT-CREAT-01', $row['code']);
        $this->assertEquals('75.50', $row['passing_percentage']);

        // Verify CREATE_TEST Audit log entry
        $stmt = $this->pdo->prepare("SELECT * FROM audit_logs WHERE entity_type = 'TEST' AND entity_id = :id AND action = 'CREATE_TEST'");
        $stmt->bindValue(':id', $newId, PDO::PARAM_INT);
        $stmt->execute();
        $audit = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($audit, 'CREATE_TEST audit log must exist');

        $newValues = json_decode($audit['new_values'], true);
        $this->assertSame(['created_field_names'], array_keys($newValues));
        $this->assertIsArray($newValues['created_field_names']);
        $this->assertContains('code', $newValues['created_field_names']);
        $this->assertArrayNotHasKey('test_id', $newValues);
        $this->assertArrayNotHasKey('code', $newValues);
        $this->assertArrayNotHasKey('is_active', $newValues);
        $this->assertNull($audit['old_values']);
    }

    public function testDuplicateCodeCaseInsensitiveRejection(): void
    {
        $adminId = $this->getValidAdminId();
        $this->createAdminSession($adminId, 'super_admin');

        $input = [
            'code' => 'UNIT-DUP-01',
            'name_ar' => 'اختبار كود مكرر 1',
            'name_en' => 'Duplicate Test 1',
            'duration_minutes' => '30',
            'question_count' => '5',
            'passing_percentage' => '50.00',
            'display_order' => '1',
        ];

        $res1 = $this->managementService->createTest($input, $adminId);
        $this->assertEquals('SUCCESS', $res1['code']);

        // Attempt creation with lowercase version of same code
        $input2 = $input;
        $input2['code'] = 'unit-dup-01';
        $res2 = $this->managementService->createTest($input2, $adminId);
        $this->assertEquals('DUPLICATE_CODE', $res2['code']);
        $this->assertArrayHasKey('code', $res2['errors']);
    }

    // ==================================================
    // 4. CONCURRENCY EDIT TOKENS & LIFECYCLE
    // ==================================================

    public function testEditTokenIssueValidateAndConsume(): void
    {
        $adminId = $this->getValidAdminId();
        $this->createAdminSession($adminId, 'super_admin');

        $input = [
            'code' => 'UNIT-EDIT-01',
            'name_ar' => 'اختبار التعديل',
            'name_en' => 'Edit Assessment Test',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];

        $createRes = $this->managementService->createTest($input, $adminId);
        $testId = $createRes['test_id'];
        $row = $this->repository->findSafeById($testId);

        // Issue token
        $token = $this->managementService->issueEditToken($adminId, $testId, $row);
        $this->assertEquals(64, strlen($token));

        // Validate token
        $tokenData = $this->managementService->validateEditToken($token, $adminId, $testId);
        $this->assertNotNull($tokenData);
        $this->assertEquals($adminId, $tokenData['admin_id']);
        $this->assertEquals($testId, $tokenData['test_id']);

        // Perform valid edit using token
        $editInput = [
            'code' => 'UNIT-EDIT-01',
            'name_ar' => 'اختبار التعديل المحدث',
            'name_en' => 'Edit Assessment Test Updated',
            'description_ar' => 'وصف جديد',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '2',
        ];

        $updateRes = $this->managementService->updateTest($testId, $token, $editInput, $adminId);
        $this->assertEquals('SUCCESS', $updateRes['code']);

        // Token must be consumed after successful POST
        $this->assertNull($this->managementService->validateEditToken($token, $adminId, $testId));

        // Replaying consumed token must return CONFLICT
        $replayRes = $this->managementService->updateTest($testId, $token, $editInput, $adminId);
        $this->assertEquals('CONFLICT', $replayRes['code']);
    }

    public function testConcurrentConflictDetectionFingerprintMismatch(): void
    {
        $adminId = $this->getValidAdminId();
        $this->createAdminSession($adminId, 'super_admin');

        $input = [
            'code' => 'UNIT-CONC-01',
            'name_ar' => 'اختبار التزامن',
            'name_en' => 'Concurrency Test',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];

        $createRes = $this->managementService->createTest($input, $adminId);
        $testId = $createRes['test_id'];
        $row = $this->repository->findSafeById($testId);

        // Tab 1 opens edit form -> gets token 1
        $token1 = $this->managementService->issueEditToken($adminId, $testId, $row);

        // Tab 2 opens edit form -> gets token 2
        $token2 = $this->managementService->issueEditToken($adminId, $testId, $row);

        // Tab 1 submits update
        $update1 = [
            'code' => 'UNIT-CONC-01',
            'name_ar' => 'تعديل من تبويب 1',
            'name_en' => 'Edit Tab 1',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];
        $res1 = $this->managementService->updateTest($testId, $token1, $update1, $adminId);
        $this->assertEquals('SUCCESS', $res1['code']);

        // Tab 2 now submits update with old token 2 (which had original row fingerprint)
        $update2 = [
            'code' => 'UNIT-CONC-01',
            'name_ar' => 'تعديل من تبويب 2',
            'name_en' => 'Edit Tab 2',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];
        $res2 = $this->managementService->updateTest($testId, $token2, $update2, $adminId);
        $this->assertEquals('CONFLICT', $res2['code'], 'Tab 2 must encounter CONFLICT because DB row was modified by Tab 1');
    }

    // ==================================================
    // 5. FIELD-LOCK ENFORCEMENT & ATTEMPTS
    // ==================================================

    public function testFieldLockingWhenAttemptsExist(): void
    {
        $adminId = $this->getValidAdminId();
        $empId = $this->getValidEmployeeId();
        $this->createAdminSession($adminId, 'super_admin');

        $input = [
            'code' => 'UNIT-LOCK-01',
            'name_ar' => 'اختبار القفل',
            'name_en' => 'Locked Fields Test',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
            'allow_back_navigation' => '1',
        ];

        $createRes = $this->managementService->createTest($input, $adminId);
        $testId = $createRes['test_id'];

        // Insert assignment first
        $assignStmt = $this->pdo->prepare("
            INSERT INTO test_assignments (employee_id, test_id, assigned_by, max_attempts, assigned_at)
            VALUES (:emp_id, :test_id, :admin_id, 3, UTC_TIMESTAMP())
        ");
        $assignStmt->bindValue(':emp_id', $empId, PDO::PARAM_INT);
        $assignStmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $assignStmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $assignStmt->execute();
        $assignmentId = (int)$this->pdo->lastInsertId();

        // Insert a completed attempt
        $attStmt = $this->pdo->prepare("
            INSERT INTO test_attempts (
                employee_id, test_id, assignment_id, attempt_number, started_at, submitted_at, expires_at,
                duration_seconds, score, total_score, percentage, passed, status, ip_address, user_agent, created_at, updated_at
            ) VALUES (
                :emp_id, :test_id, :assignment_id, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(),
                3600, 80.00, 100.00, 80.00, 1, 'COMPLETED', '127.0.0.1', 'CLI', UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )
        ");
        $attStmt->bindValue(':emp_id', $empId, PDO::PARAM_INT);
        $attStmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $attStmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
        $attStmt->execute();

        $row = $this->repository->findSafeById($testId);
        $token = $this->managementService->issueEditToken($adminId, $testId, $row);

        // Attempting to change locked core field 'code'
        $editAttempt = [
            'code' => 'UNIT-LOCK-MODIFIED', // CHANGED!
            'name_ar' => 'تعديل اسم مسموح',
            'name_en' => 'Edit Name Allowed',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
            'allow_back_navigation' => '1',
        ];

        $res = $this->managementService->updateTest($testId, $token, $editAttempt, $adminId);
        $this->assertEquals('FIELD_LOCKED', $res['code']);
        $this->assertArrayHasKey('code', $res['errors']);

        // Verify DB row remains completely unchanged
        $rowAfter = $this->repository->findSafeById($testId);
        $this->assertEquals('UNIT-LOCK-01', $rowAfter['code']);
        $this->assertEquals('اختبار القفل', $rowAfter['name_ar']);
    }

    public function testNavigationLockWhenInProgressAttemptExists(): void
    {
        $adminId = $this->getValidAdminId();
        $empId = $this->getValidEmployeeId();
        $this->createAdminSession($adminId, 'super_admin');

        $input = [
            'code' => 'UNIT-NAV-01',
            'name_ar' => 'اختبار التنقل',
            'name_en' => 'Nav Lock Test',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
            'allow_back_navigation' => '1',
        ];

        $createRes = $this->managementService->createTest($input, $adminId);
        $testId = $createRes['test_id'];

        // Insert assignment first
        $assignStmt = $this->pdo->prepare("
            INSERT INTO test_assignments (employee_id, test_id, assigned_by, max_attempts, assigned_at)
            VALUES (:emp_id, :test_id, :admin_id, 3, UTC_TIMESTAMP())
        ");
        $assignStmt->bindValue(':emp_id', $empId, PDO::PARAM_INT);
        $assignStmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $assignStmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $assignStmt->execute();
        $assignmentId = (int)$this->pdo->lastInsertId();

        // Insert an IN_PROGRESS attempt
        $attStmt = $this->pdo->prepare("
            INSERT INTO test_attempts (
                employee_id, test_id, assignment_id, attempt_number, started_at, expires_at,
                duration_seconds, status, ip_address, user_agent, created_at, updated_at
            ) VALUES (
                :emp_id, :test_id, :assignment_id, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(),
                3600, 'IN_PROGRESS', '127.0.0.1', 'CLI', UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )
        ");
        $attStmt->bindValue(':emp_id', $empId, PDO::PARAM_INT);
        $attStmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $attStmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
        $attStmt->execute();

        $row = $this->repository->findSafeById($testId);
        $token = $this->managementService->issueEditToken($adminId, $testId, $row);

        // Attempting to change allow_back_navigation from 1 to 0
        $editAttempt = [
            'code' => 'UNIT-NAV-01',
            'name_ar' => 'اختبار التنقل',
            'name_en' => 'Nav Lock Test',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
            'allow_back_navigation' => '0', // CHANGED while IN_PROGRESS attempt exists!
        ];

        $res = $this->managementService->updateTest($testId, $token, $editAttempt, $adminId);
        $this->assertEquals('FIELD_LOCKED', $res['code']);
        $this->assertArrayHasKey('allow_back_navigation', $res['errors']);

        // Verify DB row remains unchanged
        $rowAfter = $this->repository->findSafeById($testId);
        $this->assertEquals(1, (int)$rowAfter['allow_back_navigation']);
    }

    // ==================================================
    // 6. NO-OP UPDATE HANDLING
    // ==================================================

    public function testNoOpUpdateBehavior(): void
    {
        $adminId = $this->getValidAdminId();
        $this->createAdminSession($adminId, 'super_admin');

        $input = [
            'code' => 'UNIT-NOOP-01',
            'name_ar' => 'اختبار بدون تغيير',
            'name_en' => 'No Op Test',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];

        $createRes = $this->managementService->createTest($input, $adminId);
        $testId = $createRes['test_id'];

        $rowBefore = $this->repository->findSafeById($testId);
        $token = $this->managementService->issueEditToken($adminId, $testId, $rowBefore);

        // Submit identical data matching DB row
        $res = $this->managementService->updateTest($testId, $token, $input, $adminId);
        $this->assertEquals('NO_CHANGES', $res['code']);

        $rowAfter = $this->repository->findSafeById($testId);
        $this->assertEquals($rowBefore['updated_at'], $rowAfter['updated_at'], 'updated_at must remain unchanged on no-op');

        // Verify NO UPDATE_TEST audit log entry was written
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'TEST' AND entity_id = :id AND action = 'UPDATE_TEST'");
        $stmt->bindValue(':id', $testId, PDO::PARAM_INT);
        $stmt->execute();
        $this->assertEquals(0, (int)$stmt->fetchColumn());
    }

    // ==================================================
    // 7. AUDIT LOG CONTRACT & BOUNDARIES
    // ==================================================

    public function testAuditLogContractOnUpdate(): void
    {
        $adminId = $this->getValidAdminId();
        $this->createAdminSession($adminId, 'super_admin');

        $input = [
            'code' => 'UNIT-AUDIT-01',
            'name_ar' => 'اختبار التدقيق الأصلي',
            'name_en' => 'Original Audit Test Name',
            'description_ar' => 'سر للغاية - يجب عدم تخزينه في التدقيق',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];

        $createRes = $this->managementService->createTest($input, $adminId);
        $testId = $createRes['test_id'];

        $row = $this->repository->findSafeById($testId);
        $token = $this->managementService->issueEditToken($adminId, $testId, $row);

        $updateInput = [
            'code' => 'UNIT-AUDIT-02', // Code changed
            'name_ar' => 'اختبار التدقيق المعدل', // Name changed
            'name_en' => 'Original Audit Test Name',
            'description_ar' => 'سر للغاية - يجب عدم تخزينه في التدقيق',
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ];

        $res = $this->managementService->updateTest($testId, $token, $updateInput, $adminId);
        $this->assertEquals('SUCCESS', $res['code']);

        // Inspect UPDATE_TEST Audit entry
        $stmt = $this->pdo->prepare("SELECT * FROM audit_logs WHERE entity_type = 'TEST' AND entity_id = :id AND action = 'UPDATE_TEST'");
        $stmt->bindValue(':id', $testId, PDO::PARAM_INT);
        $stmt->execute();
        $audit = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($audit);

        $oldValues = json_decode($audit['old_values'], true);
        $newValues = json_decode($audit['new_values'], true);

        $this->assertSame(['changed_field_names'], array_keys($oldValues));
        $this->assertSame(['changed_field_names'], array_keys($newValues));

        // Verify changed_field_names order without recording field values
        $this->assertEquals(['code', 'name_ar'], $oldValues['changed_field_names']);
        $this->assertEquals(['code', 'name_ar'], $newValues['changed_field_names']);
        $this->assertArrayNotHasKey('test_id', $oldValues);
        $this->assertArrayNotHasKey('previous_code', $oldValues);
        $this->assertArrayNotHasKey('new_code', $newValues);

        // Verify descriptions, names, durations, tokens, credentials are NOT in Audit JSON
        $jsonStr = $audit['old_values'] . $audit['new_values'];
        $this->assertStringNotContainsString('اختبار التدقيق', $jsonStr);
        $this->assertStringNotContainsString('سر للغاية', $jsonStr);
    }
}
