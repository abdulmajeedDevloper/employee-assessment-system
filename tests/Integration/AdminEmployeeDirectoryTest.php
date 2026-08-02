<?php

namespace Tests\Integration;

use App\Controllers\Admin\EmployeeController;
use App\Middleware\AdminAuthMiddleware;
use App\Repositories\AdminEmployeeRepository;
use App\Repositories\AdminRepository;
use App\Services\AdminEmployeeDirectoryService;
use App\Support\Database;
use PDO;
use Exception;
use ReflectionClass;
use Tests\TestCase;

class AdminEmployeeDirectoryTest extends TestCase
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
        if (!in_array('employees', $tables, true) || !in_array('departments', $tables, true)) {
            $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
            self::$pdo->exec($sql1);
            $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
            self::$pdo->exec($sql2);
        }
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        self::cleanupFixtures();

        // Seed active and inactive departments
        self::$pdo->exec("
            INSERT INTO departments (id, name_ar, name_en, is_active, created_at, updated_at) VALUES
            (201, 'الموارد البشرية', 'Human Resources', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (202, 'تقنية المعلومات', 'Information Technology', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (203, 'إدارة ملغاة', 'Inactive Department', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed sections
        self::$pdo->exec("
            INSERT INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at) VALUES
            (201, 201, 'قسم التوظيف', 'Recruitment Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (202, 202, 'قسم التطوير', 'Development Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed job titles
        self::$pdo->exec("
            INSERT INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at) VALUES
            (201, 'أخصائي موارد بشرية', 'HR Specialist', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
            (202, 'مطور برمجيات', 'Software Engineer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");

        // Seed test admin accounts
        $hash = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmtAdmin = self::$pdo->prepare("
            INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at)
            VALUES (:id, :name, :email, :username, :hash, :role, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $stmtAdmin->execute([':id' => 301, ':name' => 'Directory Super', ':email' => 'dir_super@test.local', ':username' => 'dir_super', ':hash' => $hash, ':role' => 'super_admin']);
        $stmtAdmin->execute([':id' => 302, ':name' => 'Directory Supervisor', ':email' => 'dir_supervisor@test.local', ':username' => 'dir_supervisor', ':hash' => $hash, ':role' => 'supervisor']);
        $stmtAdmin->execute([':id' => 303, ':name' => 'Directory Viewer', ':email' => 'dir_viewer@test.local', ':username' => 'dir_viewer', ':hash' => $hash, ':role' => 'report_viewer']);

        // Seed 25 employees for search, filter & pagination testing (IDs 501 - 525)
        $stmtEmp = self::$pdo->prepare("
            INSERT INTO employees (
                id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag,
                key_version, national_id_last4, employee_number, email, phone, department_id, section_id,
                job_title_id, preferred_language, is_active, profile_completed_at, created_at, updated_at, archived_at
            ) VALUES (
                :id, :full_name, UNHEX(SHA2(:nid, 256)), 'CIPHERTEXT_DATA', UNHEX('000000000000000000000000'), UNHEX('00000000000000000000000000000000'),
                1, :last4, :emp_num, :email, :phone, :dept_id, :sec_id,
                :job_id, :lang, :active, :profile_completed, UTC_TIMESTAMP(), UTC_TIMESTAMP(), :archived_at
            )
        ");

        for ($i = 1; $i <= 25; $i++) {
            $empId = 500 + $i;
            $nid = sprintf('99988800%02d', $i);
            $last4 = sprintf('88%02d', $i);
            $empNum = "EMP-{$empId}";
            $email = "employee{$i}@test.local";
            $fullName = "Employee Name " . sprintf('%02d', $i);
            $deptId = ($i % 2 === 0) ? 202 : 201;
            $secId = ($i % 2 === 0) ? 202 : 201;
            $jobId = ($i % 2 === 0) ? 202 : 201;
            $active = 1;
            $archivedAt = null;

            if ($i === 24) {
                $active = 0; // inactive
            } elseif ($i === 25) {
                $archivedAt = date('Y-m-d H:i:s'); // archived
            }

            // XSS test employee for ID 501
            if ($i === 1) {
                $fullName = '<script>alert("xss")</script> Ahmed';
                $email = 'xss_emp@test.local';
                $empNum = 'EMP-XSS-100';
            }

            $stmtEmp->execute([
                ':id' => $empId,
                ':full_name' => $fullName,
                ':nid' => $nid,
                ':last4' => $last4,
                ':emp_num' => $empNum,
                ':email' => $email,
                ':phone' => '0501234567',
                ':dept_id' => $deptId,
                ':sec_id' => $secId,
                ':job_id' => $jobId,
                ':lang' => ($i % 2 === 0) ? 'en' : 'ar',
                ':active' => $active,
                ':profile_completed' => date('Y-m-d H:i:s'),
                ':archived_at' => $archivedAt,
            ]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$pdo)) {
            self::cleanupFixtures();
            AdminAuthMiddleware::setRepository(null);
        }
    }

    private static function cleanupFixtures(): void
    {
        self::$pdo->exec("DELETE FROM employees WHERE id BETWEEN 501 AND 530;");
        self::$pdo->exec("DELETE FROM admins WHERE id IN (301, 302, 303);");
        self::$pdo->exec("DELETE FROM job_titles WHERE id IN (201, 202);");
        self::$pdo->exec("DELETE FROM sections WHERE id IN (201, 202);");
        self::$pdo->exec("DELETE FROM departments WHERE id IN (201, 202, 203);");
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        } else {
            session_start();
            $_SESSION = [];
        }
        AdminAuthMiddleware::setRepository(new AdminRepository(self::$pdo));
    }

    // --- 1. Session Protection Tests ---

    public function test403DoesNotExposeSessionProvidedAdminNameOrRole(): void
    {
        $_SESSION['admin_auth'] = [
            'admin_id' => 999,
            'role' => 'super_admin',
            'name' => 'Spoofed Session Admin',
            'last_activity_at' => time(),
            'absolute_start_at' => time(),
        ];

        $controller = new EmployeeController(new AdminEmployeeDirectoryService(new AdminEmployeeRepository(self::$pdo)));
        $reflector = new ReflectionClass($controller);
        $method = $reflector->getMethod('render403');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($controller);
        $output = ob_get_clean();

        $this->assertEquals(403, http_response_code());
        $this->assertStringNotContainsString('Spoofed Session Admin', $output);
        $this->assertStringNotContainsString('super_admin', $output);
    }

    public function testProveFreshDatabaseAdministratorDataOverridesSpoofedSession(): void
    {
        $now = time();
        // ID 303 database record has role 'report_viewer' and name 'Directory Viewer'
        $_SESSION['admin_auth'] = [
            'admin_id' => 303,
            'role' => 'super_admin', // Spoofed role
            'name' => 'Spoofed Session Name', // Spoofed name
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $admin = AdminAuthMiddleware::handle('view_employees');
        $this->assertNotNull($admin);
        $this->assertEquals('report_viewer', $admin['role'], "Role must come from DB, not spoofed session.");
        $this->assertEquals('Directory Viewer', $admin['name'], "Name must come from DB, not spoofed session.");

        // Session variables must be updated with fresh DB data
        $this->assertEquals('report_viewer', $_SESSION['admin_auth']['role']);
        $this->assertEquals('Directory Viewer', $_SESSION['admin_auth']['name']);
    }

    // --- 2. Strict Integer Normalization Tests ---

    public function testStrictPositiveIntegerNormalization(): void
    {
        $service = new AdminEmployeeDirectoryService(new AdminEmployeeRepository(self::$pdo));

        // Invalid department_id inputs -> normalized to null
        $this->assertNull($service->getDirectory(['department_id' => '201.9'])['filters']['department_id']);
        $this->assertNull($service->getDirectory(['department_id' => '2.01e2'])['filters']['department_id']);
        $this->assertNull($service->getDirectory(['department_id' => '+201'])['filters']['department_id']);
        $this->assertNull($service->getDirectory(['department_id' => '0'])['filters']['department_id']);
        $this->assertNull($service->getDirectory(['department_id' => '-201'])['filters']['department_id']);
        $this->assertNull($service->getDirectory(['department_id' => ['201']])['filters']['department_id']);

        // Invalid page inputs -> normalized to 1
        $this->assertEquals(1, $service->getDirectory(['page' => '1.9'])['filters']['page']);
        $this->assertEquals(1, $service->getDirectory(['page' => '1e2'])['filters']['page']);
        $this->assertEquals(1, $service->getDirectory(['page' => '+2'])['filters']['page']);
        $this->assertEquals(1, $service->getDirectory(['page' => '0'])['filters']['page']);
        $this->assertEquals(1, $service->getDirectory(['page' => '-5'])['filters']['page']);

        // Valid positive integer string inputs
        $this->assertEquals(201, $service->getDirectory(['department_id' => '201'])['filters']['department_id']);
        $this->assertEquals(2, $service->getDirectory(['page' => '2'])['filters']['page']);
    }

    // --- 3. Repository Security Tests ---

    public function testRepositoryListResultsContainNoNationalIdFieldsAndDetailContainsOnlyLast4(): void
    {
        $repo = new AdminEmployeeRepository(self::$pdo);

        $searchResult = $repo->search([], 1, 20);
        $this->assertNotEmpty($searchResult['items']);
        $firstItem = $searchResult['items'][0];

        $nationalIdFields = [
            'national_id_hash',
            'national_id_ciphertext',
            'national_id_iv',
            'national_id_tag',
            'key_version',
            'national_id_last4',
        ];

        foreach ($nationalIdFields as $field) {
            $this->assertArrayNotHasKey($field, $firstItem, "Search list item exposes national ID field '{$field}'");
        }

        $detailItem = $repo->findSafeById(502);
        $this->assertNotNull($detailItem);

        $encryptedFields = [
            'national_id_hash',
            'national_id_ciphertext',
            'national_id_iv',
            'national_id_tag',
            'key_version',
        ];

        foreach ($encryptedFields as $field) {
            $this->assertArrayNotHasKey($field, $detailItem, "Single item detail exposes sensitive encrypted field '{$field}'");
        }

        $this->assertArrayHasKey('national_id_last4', $detailItem);
        $this->assertEquals('8802', $detailItem['national_id_last4']);
    }

    // --- 4. Routes Tests ---

    public function testRoutesExistAndNoPostRoutesAdded(): void
    {
        $routes = require __DIR__ . '/../../config/routes.php';

        $this->assertArrayHasKey('/admin/employees', $routes['GET']);
        $this->assertArrayHasKey('/admin/employees/view', $routes['GET']);
        $this->assertArrayNotHasKey('/admin/employees', $routes['POST'] ?? []);
        $this->assertArrayNotHasKey('/admin/employees/view', $routes['POST'] ?? []);
    }

    // --- 5. Authorization Tests ---

    public function testDirectoryAccessWithoutAuthRedirectsToLogin(): void
    {
        unset($_SESSION['admin_auth']);
        $res = AdminAuthMiddleware::handle('view_employees');
        $this->assertNull($res);
    }

    // --- 6. Search and Empty Metadata Tests ---

    public function testSearchWorksWithSeparatePlaceholdersAndEmptyMetadata(): void
    {
        $repo = new AdminEmployeeRepository(self::$pdo);

        // Search by name
        $resName = $repo->search(['q' => 'Employee Name 02'], 1, 20);
        $this->assertGreaterThanOrEqual(1, $resName['total']);

        // Search by employee number
        $resNum = $repo->search(['q' => 'EMP-503'], 1, 20);
        $this->assertEquals(1, $resNum['total']);
        $this->assertEquals(503, $resNum['items'][0]['id']);

        // Search by email
        $resEmail = $repo->search(['q' => 'employee4@test.local'], 1, 20);
        $this->assertEquals(1, $resEmail['total']);
        $this->assertEquals(504, $resEmail['items'][0]['id']);

        // Empty search result metadata verification
        $resEmpty = $repo->search(['q' => 'NON_EXISTENT_PATTERN_XYZ_999'], 1, 20);
        $this->assertEquals(0, $resEmpty['total']);
        $this->assertEquals(1, $resEmpty['page']);
        $this->assertEquals(0, $resEmpty['total_pages']);
        $this->assertEmpty($resEmpty['items']);
    }

    // --- 7. Department Whitelist & Status Filters Tests ---

    public function testDepartmentValidationAndStatusFilters(): void
    {
        $repo = new AdminEmployeeRepository(self::$pdo);
        $service = new AdminEmployeeDirectoryService($repo);

        // Existing active department 201 is accepted
        $dataValid = $service->getDirectory(['department_id' => '201']);
        $this->assertEquals(201, $dataValid['filters']['department_id']);
        $this->assertNotEmpty($dataValid['results']['items']);
        foreach ($dataValid['results']['items'] as $item) {
            $this->assertEquals(201, (int)$item['department_id']);
        }

        // Nonexistent positive department ID is normalized to null
        $dataNonExistent = $service->getDirectory(['department_id' => '9999']);
        $this->assertNull($dataNonExistent['filters']['department_id']);

        // Inactive department ID 203 is normalized to null
        $dataInactiveDept = $service->getDirectory(['department_id' => '203']);
        $this->assertNull($dataInactiveDept['filters']['department_id']);

        // Active status filter
        $resActive = $repo->search(['status' => 'active'], 1, 30);
        $this->assertNotEmpty($resActive['items']);
        foreach ($resActive['items'] as $item) {
            $this->assertEquals(1, (int)$item['is_active']);
            $this->assertNull($item['archived_at']);
        }

        // Inactive status filter
        $resInactive = $repo->search(['status' => 'inactive'], 1, 30);
        $this->assertNotEmpty($resInactive['items']);
        $this->assertEquals(524, $resInactive['items'][0]['id']);

        // Archived status filter
        $resArchived = $repo->search(['status' => 'archived'], 1, 30);
        $this->assertNotEmpty($resArchived['items']);
        $this->assertEquals(525, $resArchived['items'][0]['id']);
    }

    // --- 8. Pagination URL Preservation and XSS Tests ---

    public function testPaginationUrlPreservationAndXssEscaping(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 301,
            'role' => 'super_admin',
            'name' => 'Super Admin',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        // Query that returns more than 20 items (all employees, total 25)
        $_GET = [
            'q' => '<script>alert(1)</script> Employee',
            'department_id' => '201',
            'status' => 'active',
            'page' => '1',
        ];

        // Service test double to guarantee >= 2 total_pages with active filters
        $mockRepo = new class(self::$pdo) extends AdminEmployeeRepository {
            public function search(array $filters, int $page = 1, int $perPage = 20): array {
                return [
                    'items' => array_fill(0, 20, [
                        'id' => 501,
                        'full_name' => 'Test Employee',
                        'employee_number' => 'EMP-501',
                        'email' => 'test@test.local',
                        'phone' => '0500000000',
                        'department_id' => 201,
                        'section_id' => 201,
                        'job_title_id' => 201,
                        'preferred_language' => 'ar',
                        'is_active' => 1,
                        'profile_completed_at' => null,
                        'created_at' => '2026-01-01 00:00:00',
                        'updated_at' => '2026-01-01 00:00:00',
                        'archived_at' => null,
                        'department_name_ar' => 'الموارد البشرية',
                        'department_name_en' => 'Human Resources',
                        'section_name_ar' => 'قسم التوظيف',
                        'section_name_en' => 'Recruitment Section',
                        'job_title_name_ar' => 'أخصائي resources',
                        'job_title_name_en' => 'HR Specialist',
                    ]),
                    'total' => 25,
                    'page' => 1,
                    'per_page' => 20,
                    'total_pages' => 2,
                ];
            }
        };

        $controller = new EmployeeController(new AdminEmployeeDirectoryService($mockRepo));

        ob_start();
        $controller->index();
        $output = ob_get_clean();

        // Must render next page link with page=2
        $this->assertStringContainsString('href="/admin/employees?', $output);
        $this->assertStringContainsString('page=2', $output);
        $this->assertStringContainsString('department_id=201', $output);
        $this->assertStringContainsString('status=active', $output);

        // Unsafe search text must be URL-encoded and HTML-escaped in the href
        $this->assertStringNotContainsString('<script>alert(1)</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
    }

    // --- 9. Localization & XSS Tests ---

    public function testLocalizationLookupNamesAndXssInListAndDetails(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 301,
            'role' => 'super_admin',
            'name' => 'Super Admin',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $controller = new EmployeeController(new AdminEmployeeDirectoryService(new AdminEmployeeRepository(self::$pdo)));

        // Arabic Mode: Verify Arabic department, section, and job title names render
        $_GET = ['lang' => 'ar'];
        $_SERVER['REQUEST_URI'] = '/admin/employees?lang=ar';
        ob_start();
        $controller->index();
        $outputAr = ob_get_clean();

        $this->assertStringContainsString('الموارد البشرية', $outputAr);
        $this->assertStringContainsString('قسم التوظيف', $outputAr);
        $this->assertStringContainsString('أخصائي موارد بشرية', $outputAr);

        // English Mode: Verify English department, section, and job title names render
        $_GET = ['lang' => 'en'];
        $_SERVER['REQUEST_URI'] = '/admin/employees?lang=en';
        ob_start();
        $controller->index();
        $outputEn = ob_get_clean();

        $this->assertStringContainsString('Human Resources', $outputEn);
        $this->assertStringContainsString('Recruitment Section', $outputEn);
        $this->assertStringContainsString('HR Specialist', $outputEn);

        // Employee List XSS Escaping Verification (ID 501 name contains <script>alert("xss")</script> Ahmed)
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $outputAr);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $outputAr);

        // Language Switch link tests
        $_GET = [
            'id' => '501',
            'lang' => 'en',
            'array_param' => ['ignored_array_value'],
        ];
        $_SERVER['REQUEST_URI'] = '/admin/employees/view?id=501&lang=en&array_param%5B0%5D=ignored_array_value';

        ob_start();
        $controller->show();
        $outputShow = ob_get_clean();

        // Language switch link preserves employee detail id=501 and toggles to lang=ar
        $this->assertStringContainsString('/admin/employees/view?', $outputShow);
        $this->assertStringContainsString('id=501', $outputShow);
        $this->assertStringContainsString('lang=ar', $outputShow);
        // Array GET value must be ignored in language switch link
        $this->assertStringNotContainsString('array_param', $outputShow);
    }

    public function testEmployeeDetailsLtrBidiIsolationAndMasking(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 301,
            'role' => 'super_admin',
            'name' => 'Super Admin',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $_GET = ['id' => '501', 'lang' => 'ar'];
        $_SERVER['REQUEST_URI'] = '/admin/employees/view?id=501&lang=ar';

        $controller = new EmployeeController(new AdminEmployeeDirectoryService(new AdminEmployeeRepository(self::$pdo)));
        ob_start();
        $controller->show();
        $output = ob_get_clean();

        // 1. Masked national ID output contains LTR bdi isolation element and exact masked value
        $this->assertStringContainsString('******8801', $output);
        $this->assertStringContainsString('<bdi dir="ltr" class="employee-technical-value">', $output);

        $adminCssPath = dirname(__DIR__, 2) . '/public/assets/css/admin.css';
        $this->assertFileIsReadable($adminCssPath, 'Centralized admin.css stylesheet must exist and be readable.');
        $adminCssContent = (string) file_get_contents($adminCssPath);
        $this->assertStringContainsString('body.admin-view-employees-show .employee-technical-value', $adminCssContent);
        $this->assertStringContainsString('display: inline-block;', $adminCssContent);
        $this->assertStringContainsString('width: auto;', $adminCssContent);

        // 2. Sensitive encrypted identity fields remain absent
        $encryptedFields = [
            'national_id_hash',
            'national_id_ciphertext',
            'national_id_iv',
            'national_id_tag',
            'key_version',
            'CIPHERTEXT_DATA',
        ];
        foreach ($encryptedFields as $field) {
            $this->assertStringNotContainsString($field, $output, "Output must not expose sensitive field '{$field}'");
        }

        // 3. Technical fields (employee_number, email, phone, created_at) use LTR bdi isolation element
        $this->assertStringContainsString('<bdi dir="ltr" class="employee-technical-value">EMP-XSS-100</bdi>', $output);
        $this->assertStringContainsString('<bdi dir="ltr" class="employee-technical-value">xss_emp@test.local</bdi>', $output);
        $this->assertStringContainsString('<bdi dir="ltr" class="employee-technical-value">0501234567</bdi>', $output);

        // 4. XSS escaping remains intact
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $output);
    }

    // --- 10. Error Handling Tests ---

    public function testErrorHandlingForInvalidMissingAndRepositoryException(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 301,
            'role' => 'super_admin',
            'name' => 'Super Admin',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $controller = new EmployeeController(new AdminEmployeeDirectoryService(new AdminEmployeeRepository(self::$pdo)));

        // 1. Invalid non-numeric ID -> 404
        $_GET = ['id' => 'abc_invalid'];
        ob_start();
        $controller->show();
        $outputInvalid = ob_get_clean();
        $this->assertEquals(404, http_response_code());
        $this->assertStringContainsString(trans('employee_not_found'), $outputInvalid);

        // 2. Missing employee -> 404
        $_GET = ['id' => '99999'];
        ob_start();
        $controller->show();
        $outputMissing = ob_get_clean();
        $this->assertEquals(404, http_response_code());
        $this->assertStringContainsString(trans('employee_not_found'), $outputMissing);

        // 3. Simulated repository exception -> 500
        $failingRepo = new class(self::$pdo) extends AdminEmployeeRepository {
            public function findSafeById(int $id): ?array {
                throw new Exception("Simulated DB Connection Crash");
            }
        };

        $failingService = new AdminEmployeeDirectoryService($failingRepo);
        $failingController = new EmployeeController($failingService);

        $_GET = ['id' => '501'];
        ob_start();
        $failingController->show();
        $outputException = ob_get_clean();

        $this->assertEquals(500, http_response_code());
        $this->assertStringContainsString(trans('temporary_error'), $outputException);
        $this->assertStringNotContainsString('Simulated DB Connection Crash', $outputException);
        $this->assertStringNotContainsString('Exception', $outputException);
    }

    public function testRequiredTranslationsExistInBothFiles(): void
    {
        $ar = require __DIR__ . '/../../lang/ar.php';
        $en = require __DIR__ . '/../../lang/en.php';

        $requiredKeys = [
            'employees',
            'employee_directory',
            'search',
            'search_employees',
            'department',
            'all_departments',
            'status',
            'all_statuses',
            'active',
            'inactive',
            'archived',
            'filter',
            'reset_filters',
            'employee_name',
            'employee_number',
            'section',
            'job_title',
            'email',
            'phone',
            'details',
            'no_employees_found',
            'employee_details',
            'national_id_last4',
            'preferred_language',
            'profile_status',
            'profile_complete',
            'profile_incomplete',
            'created_at',
            'back_to_employees',
            'employee_not_found',
            'previous',
            'next',
            'page_of',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $ar, "Missing Arabic key: {$key}");
            $this->assertArrayHasKey($key, $en, "Missing English key: {$key}");
        }
    }
}
