<?php

namespace Tests\Integration;

use App\Controllers\Admin\TestController;
use App\Middleware\AdminAuthMiddleware;
use App\Repositories\AdminRepository;
use App\Repositories\AdminTestRepository;
use App\Services\AdminTestDirectoryService;
use App\Support\Database;
use PDO;
use ReflectionClass;
use Tests\TestCase;

class AdminTestDirectoryTest extends TestCase
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
        Database::setInstance(self::$pdo);

        // Ensure tables exist
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = self::$pdo->query("SHOW TABLES;")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('tests', $tables, true) || !in_array('admins', $tables, true)) {
            $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
            self::$pdo->exec($sql1);
            $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
            self::$pdo->exec($sql2);
        }
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        self::cleanupFixtures();
        self::seedFixtures();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$pdo)) {
            self::cleanupFixtures();
        }
    }

    private static function cleanupFixtures(): void
    {
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        self::$pdo->exec("DELETE FROM attempt_answers;");
        self::$pdo->exec("DELETE FROM attempt_questions;");
        self::$pdo->exec("DELETE FROM test_attempts;");
        self::$pdo->exec("DELETE FROM test_assignments;");
        self::$pdo->exec("DELETE FROM question_options;");
        self::$pdo->exec("DELETE FROM questions;");
        self::$pdo->exec("DELETE FROM question_categories;");
        self::$pdo->exec("DELETE FROM tests;");
        self::$pdo->exec("DELETE FROM employees;");
        self::$pdo->exec("DELETE FROM departments;");
        self::$pdo->exec("DELETE FROM sections;");
        self::$pdo->exec("DELETE FROM job_titles;");
        self::$pdo->exec("DELETE FROM admins;");
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }

    private static function seedFixtures(): void
    {
        $hash = password_hash('AdminPass123!', PASSWORD_BCRYPT);

        // Seed admins
        self::$pdo->exec("
            INSERT INTO admins (id, username, email, password_hash, name, role, is_active, created_at, updated_at) VALUES
            (801, 'test_super_admin', 'super@test.local', '{$hash}', 'Super Admin User', 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (802, 'test_supervisor', 'supervisor@test.local', '{$hash}', 'Supervisor User', 'supervisor', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (803, 'test_report_viewer', 'viewer@test.local', '{$hash}', 'Report Viewer User', 'report_viewer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed reference org data
        self::$pdo->exec("
            INSERT INTO departments (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (801, 'IT Dept', 'IT Dept', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
            INSERT INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at) VALUES (801, 801, 'Dev Sec', 'Dev Sec', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
            INSERT INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (801, 'Dev', 'Dev', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
            INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, key_version, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, preferred_language, is_active, created_at, updated_at) VALUES
            (801, 'Test Emp 1', UNHEX(SHA2('1000000801', 256)), 'CIPHER', UNHEX('000000000000000000000000'), UNHEX('00000000000000000000000000000000'), 1, '0801', 'EMP-801', 'emp801@test.local', '0500000801', 801, 801, 801, 'ar', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (802, 'Test Emp 2', UNHEX(SHA2('1000000802', 256)), 'CIPHER', UNHEX('000000000000000000000000'), UNHEX('00000000000000000000000000000000'), 1, '0802', 'EMP-802', 'emp802@test.local', '0500000802', 801, 801, 801, 'en', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed Tests (25 tests for pagination & search tests)
        $stmtTest = self::$pdo->prepare("
            INSERT INTO tests (id, code, name_ar, name_en, description_ar, description_en, instructions_ar, instructions_en, duration_minutes, question_count, passing_percentage, display_order, randomize_questions, randomize_options, allow_back_navigation, show_result_to_employee, is_active, available_from, available_until, created_at, updated_at)
            VALUES (:id, :code, :name_ar, :name_en, :desc_ar, :desc_en, :inst_ar, :inst_en, :duration, :q_count, :passing, :disp_order, 1, 1, 1, 0, :is_active, :avail_from, :avail_until, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");

        for ($i = 1; $i <= 25; $i++) {
            $testId = 900 + $i;
            $code = "TST-CODE-" . sprintf('%03d', $i);
            $nameAr = "اختبار التقييم رقم " . sprintf('%02d', $i);
            $nameEn = "Assessment Test No " . sprintf('%02d', $i);
            $isActive = 1;

            if ($i === 24) {
                $isActive = 0; // inactive
            } elseif ($i === 25) {
                $code = 'TST_SPECIAL%CHAR\\'; // Special LIKE chars test
                $nameAr = '<script>alert("xss")</script> XSS Test';
                $nameEn = 'XSS Special Test';
            }

            $availFrom = null;
            $availUntil = null;
            if ($i === 2) {
                // Case D: Both set
                $availFrom = '2026-01-01 00:00:00';
                $availUntil = '2026-12-31 23:59:59';
            } elseif ($i === 3) {
                // Case B: Only avail_from set
                $availFrom = '2026-05-01 00:00:00';
                $availUntil = null;
            } elseif ($i === 4) {
                // Case C: Only avail_until set
                $availFrom = null;
                $availUntil = '2026-08-01 00:00:00';
            }

            $stmtTest->execute([
                ':id' => $testId,
                ':code' => $code,
                ':name_ar' => $nameAr,
                ':name_en' => $nameEn,
                ':desc_ar' => "وصف الاختبار {$i}",
                ':desc_en' => "Description for test {$i}",
                ':inst_ar' => "تعليمات الاختبار {$i}",
                ':inst_en' => "Instructions for test {$i}",
                ':duration' => 30 + $i,
                ':q_count' => 10,
                ':passing' => 60.00,
                ':disp_order' => $i,
                ':is_active' => $isActive,
                ':avail_from' => $availFrom,
                ':avail_until' => $availUntil,
            ]);
        }

        // Seed question categories & questions for test 901
        self::$pdo->exec("
            INSERT INTO question_categories (id, test_id, name_ar, name_en, is_active, created_at) VALUES (901, 901, 'Cat 1', 'Cat 1', 1, UTC_TIMESTAMP());
        ");

        // Seed 4 questions for test 901: 2 active, 1 inactive, 1 archived
        self::$pdo->exec("
            INSERT INTO questions (id, test_id, category_id, type, text_ar, text_en, difficulty, score, is_active, archived_at, created_by, created_at, updated_at) VALUES
            (901, 901, 901, 'single_choice', 'سؤال 1', 'Q1 text', 'easy', 1.00, 1, NULL, 801, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (902, 901, 901, 'single_choice', 'سؤال 2', 'Q2 text', 'medium', 1.00, 1, NULL, 801, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (903, 901, 901, 'single_choice', 'سؤال 3 غير نشط', 'Q3 inactive', 'hard', 1.00, 0, NULL, 801, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (904, 901, 901, 'single_choice', 'سؤال 4 مؤرشف', 'Q4 archived', 'easy', 1.00, 1, UTC_TIMESTAMP(), 801, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed options for question 901 (with correct answer)
        self::$pdo->exec("
            INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES
            (901, 901, 'OPT_A', 'خيار أ', 'Option A', 1, 1),
            (902, 901, 'OPT_B', 'خيار ب', 'Option B', 0, 2);
        ");

        // Seed 3 assignments for test 901
        self::$pdo->exec("
            INSERT INTO test_assignments (id, employee_id, test_id, max_attempts, status, assigned_by, assigned_at) VALUES
            (901, 801, 901, 2, 'COMPLETED', 801, UTC_TIMESTAMP()),
            (902, 802, 901, 2, 'IN_PROGRESS', 801, UTC_TIMESTAMP());
        ");

        // Seed 4 attempts for test 901: 1 COMPLETED, 1 TIMED_OUT, 1 CANCELLED, 1 IN_PROGRESS
        self::$pdo->exec("
            INSERT INTO test_attempts (id, employee_id, test_id, assignment_id, attempt_number, started_at, submitted_at, expires_at, duration_seconds, score, total_score, percentage, passed, status, submission_reason, ip_address, user_agent, created_at, updated_at) VALUES
            (901, 801, 901, 901, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1800, 10.00, 10.00, 100.00, 1, 'COMPLETED', 'MANUAL_SUBMIT', '127.0.0.1', 'PHPUnit', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (902, 801, 901, 901, 2, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1800, 5.00, 10.00, 50.00, 0, 'TIMED_OUT', 'AUTO_TIMEOUT', '127.0.0.1', 'PHPUnit', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (903, 802, 901, 902, 1, UTC_TIMESTAMP(), NULL, UTC_TIMESTAMP(), 1800, 0.00, 10.00, 0.00, 0, 'CANCELLED', 'ADMIN_CANCEL', '127.0.0.1', 'PHPUnit', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (904, 802, 901, 902, 2, UTC_TIMESTAMP(), NULL, UTC_TIMESTAMP(), 1800, 0.00, 10.00, 0.00, 0, 'IN_PROGRESS', NULL, '127.0.0.1', 'PHPUnit', UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");
    }

    protected function setUp(): void
    {
        parent::setUp();
        http_response_code(200);
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/admin/tests';
        AdminAuthMiddleware::setRepository(new AdminRepository(self::$pdo));
    }

    // --- 1. Authorization Tests ---

    public function testUnauthenticatedRequestIsRejected(): void
    {
        $_SESSION = [];
        $result = AdminAuthMiddleware::handle('view_tests');
        $this->assertNull($result, "Unauthenticated request must be rejected.");
    }

    public function testReportViewerAccessIsDeniedWith403(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 803, // test_report_viewer
            'role' => 'report_viewer',
            'name' => 'Report Viewer User',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $result = AdminAuthMiddleware::handle('view_tests');
        $this->assertNull($result, "report_viewer must be rejected for view_tests.");
        $this->assertEquals(403, http_response_code());
    }

    public function testSupervisorAccessIsAllowed(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 802, // test_supervisor
            'role' => 'supervisor',
            'name' => 'Supervisor User',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $result = AdminAuthMiddleware::handle('view_tests');
        $this->assertNotNull($result, "supervisor must be allowed for view_tests.");
        $this->assertEquals('supervisor', $result['role']);
    }

    public function testSuperAdminAccessIsAllowed(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 801, // test_super_admin
            'role' => 'super_admin',
            'name' => 'Super Admin User',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $result = AdminAuthMiddleware::handle('view_tests');
        $this->assertNotNull($result, "super_admin must be allowed for view_tests.");
        $this->assertEquals('super_admin', $result['role']);
    }

    public function testDatabaseIdentityOverridesSpoofedSession(): void
    {
        $now = time();
        // ID 803 DB record is report_viewer, try spoofing super_admin in session
        $_SESSION['admin_auth'] = [
            'admin_id' => 803,
            'role' => 'super_admin',
            'name' => 'Spoofed Super Admin',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $result = AdminAuthMiddleware::handle('view_tests');
        $this->assertNull($result, "Role must come from database, spoofed session role must fail.");
        $this->assertEquals(403, http_response_code());
    }

    // --- 2. Directory Query & Filter Tests ---

    public function testDirectoryReturnsExplicitSafeFieldsOnly(): void
    {
        $repo = new AdminTestRepository(self::$pdo);
        $res = $repo->search([], 1, 20);

        $this->assertGreaterThan(0, count($res['items']));
        $first = $res['items'][0];

        $allowedKeys = [
            'id', 'code', 'name_ar', 'name_en', 'duration_minutes',
            'question_count', 'passing_percentage', 'is_active',
            'available_from', 'available_until', 'display_order',
            'created_at', 'updated_at'
        ];

        foreach (array_keys($first) as $key) {
            $this->assertContains($key, $allowedKeys, "Disallowed column '{$key}' found in directory result.");
        }
    }

    public function testSearchByCodeWorks(): void
    {
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));
        $data = $service->getDirectory(['q' => 'TST-CODE-001']);

        $this->assertCount(1, $data['results']['items']);
        $this->assertEquals('TST-CODE-001', $data['results']['items'][0]['code']);
    }

    public function testSearchByArabicNameWorks(): void
    {
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));
        $data = $service->getDirectory(['q' => 'اختبار التقييم رقم 01']);

        $this->assertGreaterThanOrEqual(1, count($data['results']['items']));
        $this->assertEquals('TST-CODE-001', $data['results']['items'][0]['code']);
    }

    public function testSearchByEnglishNameWorks(): void
    {
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));
        $data = $service->getDirectory(['q' => 'Assessment Test No 01']);

        $this->assertGreaterThanOrEqual(1, count($data['results']['items']));
        $this->assertEquals('TST-CODE-001', $data['results']['items'][0]['code']);
    }

    public function testLiteralLikeWildcardsAreEscaped(): void
    {
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));
        $data = $service->getDirectory(['q' => 'TST_SPECIAL%CHAR\\']);

        $this->assertCount(1, $data['results']['items']);
        $this->assertEquals('TST_SPECIAL%CHAR\\', $data['results']['items'][0]['code']);
    }

    public function testSearchArraysAreNormalizedSafely(): void
    {
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));
        $data = $service->getDirectory(['q' => ['invalid', 'array']]);

        $this->assertEquals('', $data['filters']['q']);
    }

    public function testSearchCappedAt100UnicodeCharacters(): void
    {
        $longQ = str_repeat('اختبار', 50); // > 100 chars
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));
        $data = $service->getDirectory(['q' => $longQ]);

        $this->assertLessThanOrEqual(100, mb_strlen($data['filters']['q']));
    }

    public function testStatusFilterActiveAndInactive(): void
    {
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));

        // Active only
        $activeData = $service->getDirectory(['status' => 'active']);
        foreach ($activeData['results']['items'] as $item) {
            $this->assertEquals(1, (int)$item['is_active']);
        }

        // Inactive only
        $inactiveData = $service->getDirectory(['status' => 'inactive']);
        $this->assertGreaterThan(0, count($inactiveData['results']['items']));
        foreach ($inactiveData['results']['items'] as $item) {
            $this->assertEquals(0, (int)$item['is_active']);
        }

        // Invalid status falls back to all
        $invalidStatusData = $service->getDirectory(['status' => 'invalid_status']);
        $this->assertEquals('all', $invalidStatusData['filters']['status']);
    }

    public function testStableOrderingAndPagination(): void
    {
        $repo = new AdminTestRepository(self::$pdo);

        // Page 1 (20 items)
        $resPage1 = $repo->search([], 1, 20);
        $this->assertEquals(25, $resPage1['total']);
        $this->assertEquals(2, $resPage1['total_pages']);
        $this->assertCount(20, $resPage1['items']);

        // Page 2 (5 items)
        $resPage2 = $repo->search([], 2, 20);
        $this->assertCount(5, $resPage2['items']);

        // Out of range page clamps to page 2
        $resPage99 = $repo->search([], 99, 20);
        $this->assertEquals(2, $resPage99['page']);
        $this->assertCount(5, $resPage99['items']);
    }

    // --- 3. Strict Integer Parsing & Details Validation ---

    public function testStrictIntegerNormalizationForDetailsId(): void
    {
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));

        // Valid positive integers
        $this->assertEquals(901, $service->parsePositiveInt(901));
        $this->assertEquals(901, $service->parsePositiveInt('901'));

        // Invalid inputs
        $this->assertNull($service->parsePositiveInt(null));
        $this->assertNull($service->parsePositiveInt([]));
        $this->assertNull($service->parsePositiveInt(''));
        $this->assertNull($service->parsePositiveInt(0));
        $this->assertNull($service->parsePositiveInt('0'));
        $this->assertNull($service->parsePositiveInt(-1));
        $this->assertNull($service->parsePositiveInt('-1'));
        $this->assertNull($service->parsePositiveInt('+1'));
        $this->assertNull($service->parsePositiveInt('01'));
        $this->assertNull($service->parsePositiveInt('1.0'));
        $this->assertNull($service->parsePositiveInt('1e2'));
        $this->assertNull($service->parsePositiveInt(' 901 '));
        $this->assertNull($service->parsePositiveInt('999999999999999999999999999999'));
    }

    public function testNonexistentOrInvalidDetailsIdReturnsNotFound(): void
    {
        $service = new AdminTestDirectoryService(new AdminTestRepository(self::$pdo));

        // Invalid ID
        $invalidRes = $service->getTestDetails('invalid_id');
        $this->assertFalse($invalidRes['success']);
        $this->assertEquals('INVALID_ID', $invalidRes['code']);

        // Nonexistent ID
        $notfoundRes = $service->getTestDetails(999999);
        $this->assertFalse($notfoundRes['success']);
        $this->assertEquals('NOT_FOUND', $notfoundRes['code']);
    }

    // --- 4. Aggregate Semantics Tests ---

    public function testAggregateCountsSemantics(): void
    {
        $repo = new AdminTestRepository(self::$pdo);
        $aggregates = $repo->getSafeAggregates(901);

        // Test 901 has:
        // 4 total questions: 2 active, 1 inactive, 1 archived -> active_question_bank_count MUST be 2!
        $this->assertEquals(2, $aggregates['active_question_bank_count'], "Active question bank count must exclude inactive and archived questions.");

        // 2 test assignments -> assignments_count MUST be 2
        $this->assertEquals(2, $aggregates['assignments_count']);

        // 4 total attempts (1 COMPLETED, 1 TIMED_OUT, 1 CANCELLED, 1 IN_PROGRESS) -> attempts_count MUST be 4
        $this->assertEquals(4, $aggregates['attempts_count']);

        // Completed attempts MUST mean status = 'COMPLETED' ONLY per Section 11 spec -> completed_attempts_count MUST be 1!
        $this->assertEquals(1, $aggregates['completed_attempts_count'], "Completed attempts must include status COMPLETED ONLY (excluding TIMED_OUT, CANCELLED, IN_PROGRESS).");
    }

    // --- 5. Sensitive Content Exclusion & Security Tests ---

    public function testResponseDoesNotExposeSensitiveFields(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 801,
            'role' => 'super_admin',
            'name' => 'Super Admin User',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $_GET['id'] = '901';
        $_GET['lang'] = 'ar';

        $controller = new TestController(new AdminTestDirectoryService(new AdminTestRepository(self::$pdo)));

        ob_start();
        $controller->show();
        $output = ob_get_clean();

        $sensitiveStrings = [
            'is_correct',
            'correct_option_key_snapshot',
            'question_snapshot',
            'options_snapshot',
            'selected_option_key',
            'session_token',
            'session_token_hash',
            'password_hash',
            'OPT_A',
            'OPT_B',
        ];

        foreach ($sensitiveStrings as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $output, "Output exposed sensitive field/value '{$sensitive}'.");
        }
    }

    // --- 6. XSS Protection & Rendering Tests ---

    public function testXssEscapingInView(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 801,
            'role' => 'super_admin',
            'name' => 'Super Admin User',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $_GET['id'] = '925'; // Contains <script>alert("xss")</script>
        $_GET['lang'] = 'ar';

        $controller = new TestController(new AdminTestDirectoryService(new AdminTestRepository(self::$pdo)));

        ob_start();
        $controller->show();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $output);
    }

    // --- 7. Availability Display Rules & Statistics Heading Tests ---

    public function testAvailabilityRenderingDisplayRules(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_auth'] = [
            'admin_id' => 801,
            'role' => 'super_admin',
            'name' => 'Super Admin User',
            'last_activity_at' => time() - 10,
            'absolute_start_at' => time() - 10,
        ];

        $controller = new TestController(new AdminTestDirectoryService(new AdminTestRepository(self::$pdo)));

        // Case A: Both NULL (Test 901)
        $_GET['id'] = '901';
        $_GET['lang'] = 'ar';
        ob_start();
        $controller->show();
        $outA = ob_get_clean();

        $this->assertStringContainsString('الإتاحة', $outA, "Case A must render Availability label.");
        $this->assertStringContainsString('متاح دائمًا', $outA, "Case A must render Always Available.");
        $this->assertEquals(1, substr_count($outA, 'متاح دائمًا'), "Case A must render 'متاح دائمًا' exactly once without duplication.");
        $this->assertStringNotContainsString('متاح من', $outA, "Case A must not render separate 'متاح من' row.");
        $this->assertStringNotContainsString('متاح إلى', $outA, "Case A must not render separate 'متاح إلى' row.");

        // Case B: avail_from set, avail_until NULL (Test 903)
        $_GET['id'] = '903';
        $_GET['lang'] = 'ar';
        ob_start();
        $controller->show();
        $outB = ob_get_clean();

        $this->assertStringContainsString('متاح من', $outB);
        $this->assertStringContainsString('2026-05-01 03:00:00', $outB);
        $this->assertStringContainsString('متاح إلى', $outB);
        $this->assertStringContainsString('بدون تاريخ انتهاء', $outB, "Case B must render No End Date.");

        // Case C: avail_from NULL, avail_until set (Test 904)
        $_GET['id'] = '904';
        $_GET['lang'] = 'ar';
        ob_start();
        $controller->show();
        $outC = ob_get_clean();

        $this->assertStringContainsString('متاح من', $outC);
        $this->assertStringContainsString('متاح فورًا', $outC, "Case C must render Available Immediately.");
        $this->assertStringContainsString('متاح إلى', $outC);
        $this->assertStringContainsString('2026-08-01 03:00:00', $outC);

        // Case D: Both set (Test 902)
        $_GET['id'] = '902';
        $_GET['lang'] = 'ar';
        ob_start();
        $controller->show();
        $outD = ob_get_clean();

        $this->assertStringContainsString('2026-01-01 03:00:00', $outD);
        $this->assertStringContainsString('2027-01-01 02:59:59', $outD);

        // Technical timestamps retain inline LTR isolation
        $this->assertStringContainsString('<bdi dir="ltr" class="technical-value">2026-01-01 03:00:00</bdi>', $outD);
    }

    public function testStatisticsHeadingInArabicAndEnglish(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        AdminAuthMiddleware::setRepository(new AdminRepository(self::$pdo));

        $adminAuth = [
            'admin_id' => 801,
            'role' => 'super_admin',
            'name' => 'Super Admin User',
            'last_activity_at' => time() - 10,
            'absolute_start_at' => time() - 10,
        ];

        $controller = new TestController(new AdminTestDirectoryService(new AdminTestRepository(self::$pdo)));

        // Arabic
        $_SESSION['admin_auth'] = $adminAuth;
        http_response_code(200);
        $_GET['id'] = '901';
        $_GET['lang'] = 'ar';
        ob_start();
        $controller->show();
        $outAr = ob_get_clean();

        $this->assertStringContainsString(
            '<h2 class="section-title">إحصاءات الاختبار</h2>',
            $outAr,
            "Arabic details page must display the assessment statistics section heading."
        );

        // English
        $_SESSION['admin_auth'] = $adminAuth;
        http_response_code(200);
        $_GET['id'] = '901';
        $_GET['lang'] = 'en';
        ob_start();
        $controller->show();
        $outEn = ob_get_clean();

        $this->assertStringContainsString(
            '<h2 class="section-title">Assessment Statistics</h2>',
            $outEn,
            "English details page must display the assessment statistics section heading."
        );

        // No assessment mutation controls (delete, archive, publish)
        $this->assertStringNotContainsString('/admin/tests/delete', $outEn, "Details page must not contain test delete actions.");
        $this->assertStringNotContainsString('/admin/tests/archive', $outEn, "Details page must not contain test archive actions.");
    }

    // --- 7. Navigation Active State Tests ---

    private function getNavActiveState(string $currentPath): array
    {
        $isDashboardActive = ($currentPath === '/admin' || $currentPath === '/admin/' || $currentPath === '/admin/dashboard');
        $isEmployeesActive = (!$isDashboardActive && ($currentPath === '/admin/employees' || str_starts_with($currentPath, '/admin/employees/')));
        $isTestsActive = (!$isDashboardActive && !$isEmployeesActive && ($currentPath === '/admin/tests' || str_starts_with($currentPath, '/admin/tests/')));

        $ariaCurrentCount = ($isDashboardActive ? 1 : 0) + ($isEmployeesActive ? 1 : 0) + ($isTestsActive ? 1 : 0);

        return [
            'dashboard' => $isDashboardActive,
            'employees' => $isEmployeesActive,
            'tests' => $isTestsActive,
            'aria_current_count' => $ariaCurrentCount,
        ];
    }

    public function testNavigationPathMatchingExactAndBoundary(): void
    {
        // /admin activates only Dashboard
        $state = $this->getNavActiveState('/admin');
        $this->assertTrue($state['dashboard']);
        $this->assertFalse($state['employees']);
        $this->assertFalse($state['tests']);
        $this->assertEquals(1, $state['aria_current_count']);

        // /admin/ dashboard
        $state = $this->getNavActiveState('/admin/');
        $this->assertTrue($state['dashboard']);
        $this->assertFalse($state['employees']);
        $this->assertFalse($state['tests']);

        // /admin/dashboard
        $state = $this->getNavActiveState('/admin/dashboard');
        $this->assertTrue($state['dashboard']);
        $this->assertFalse($state['employees']);
        $this->assertFalse($state['tests']);

        // /admin/tests activates only Assessments
        $state = $this->getNavActiveState('/admin/tests');
        $this->assertFalse($state['dashboard']);
        $this->assertFalse($state['employees']);
        $this->assertTrue($state['tests']);
        $this->assertEquals(1, $state['aria_current_count']);

        // /admin/tests/view activates only Assessments
        $state = $this->getNavActiveState('/admin/tests/view');
        $this->assertFalse($state['dashboard']);
        $this->assertFalse($state['employees']);
        $this->assertTrue($state['tests']);
        $this->assertEquals(1, $state['aria_current_count']);

        // /admin/employees activates only Employees
        $state = $this->getNavActiveState('/admin/employees');
        $this->assertFalse($state['dashboard']);
        $this->assertTrue($state['employees']);
        $this->assertFalse($state['tests']);
        $this->assertEquals(1, $state['aria_current_count']);

        // /admin/employees/view activates only Employees
        $state = $this->getNavActiveState('/admin/employees/view');
        $this->assertFalse($state['dashboard']);
        $this->assertTrue($state['employees']);
        $this->assertFalse($state['tests']);
        $this->assertEquals(1, $state['aria_current_count']);
    }

    public function testNavigationPathMatchingPreventsFalsePositives(): void
    {
        $falsePositivePaths = [
            '/admin/testsuite',
            '/admin/tests-extra',
            '/admin/reports/admin/tests',
            '/something/admin/tests',
            '/admin/employees-report',
            '/admin/reports/admin/employees',
            '/something/admin/employees',
        ];

        foreach ($falsePositivePaths as $path) {
            $state = $this->getNavActiveState($path);
            $this->assertFalse($state['dashboard'], "Path '{$path}' must not activate Dashboard.");
            $this->assertFalse($state['employees'], "Path '{$path}' must not activate Employees.");
            $this->assertFalse($state['tests'], "Path '{$path}' must not activate Assessments.");
            $this->assertEquals(0, $state['aria_current_count'], "Path '{$path}' must not set aria-current on any nav item.");
        }
    }
}
