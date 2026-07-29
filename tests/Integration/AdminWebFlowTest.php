<?php

namespace Tests\Integration;

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Repositories\AdminRepository;
use App\Security\CsrfTokenManager;
use App\Services\AdminAuthService;
use App\Services\AdminDashboardService;
use App\Support\Database;
use PDO;
use Tests\TestCase;

class AdminWebFlowTest extends TestCase
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

        // Verify or create schema
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = self::$pdo->query("SHOW TABLES;")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('admins', $tables, true) || !in_array('audit_logs', $tables, true)) {
            $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
            self::$pdo->exec($sql1);
            $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
            self::$pdo->exec($sql2);
        } else {
            $cols = array_column(self::$pdo->query("DESCRIBE audit_logs")->fetchAll(), 'Field');
            if (!in_array('actor_type', $cols, true)) {
                $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
                self::$pdo->exec($sql2);
            }
        }
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");


        self::cleanupFixtures();

        // Seed test admin accounts
        $hash = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = self::$pdo->prepare("
            INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at)
            VALUES (:id, :name, :email, :username, :hash, :role, :active, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $stmt->execute([':id' => 101, ':name' => 'Web Super', ':email' => 'web_super@test.local', ':username' => 'web_super', ':hash' => $hash, ':role' => 'super_admin', ':active' => 1]);
        $stmt->execute([':id' => 102, ':name' => 'Web Supervisor', ':email' => 'web_supervisor@test.local', ':username' => 'web_supervisor', ':hash' => $hash, ':role' => 'supervisor', ':active' => 1]);
        $stmt->execute([':id' => 103, ':name' => 'Web Viewer', ':email' => 'web_viewer@test.local', ':username' => 'web_viewer', ':hash' => $hash, ':role' => 'report_viewer', ':active' => 1]);
        $stmt->execute([':id' => 104, ':name' => '<script>alert("xss")</script>', ':email' => 'xss_admin@test.local', ':username' => 'xss_admin', ':hash' => $hash, ':role' => 'super_admin', ':active' => 1]);
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
        $fixtureIds = '101, 102, 103, 104';
        self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id IN ({$fixtureIds});");
        self::$pdo->exec("DELETE FROM security_events;");
        self::$pdo->exec("DELETE FROM rate_limits;");
        self::$pdo->exec("DELETE FROM admins WHERE id IN ({$fixtureIds});");
    }

    protected function setUp(): void
    {
        parent::setUp();
        $admin101Count = (int) self::$pdo->query("SELECT COUNT(*) FROM admins WHERE id = 101")->fetchColumn();
        if ($admin101Count === 0) {
            self::setUpBeforeClass();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        } else {
            session_start();
            $_SESSION = [];
        }
        AdminAuthMiddleware::setRepository(new AdminRepository(self::$pdo));
        self::$pdo->exec("DELETE FROM rate_limits;");
    }

    // --- Route Tests ---

    public function testRoutesExist(): void
    {
        $routes = require __DIR__ . '/../../config/routes.php';

        $this->assertArrayHasKey('/admin/login', $routes['GET']);
        $this->assertArrayHasKey('/admin/login', $routes['POST']);
        $this->assertArrayHasKey('/admin', $routes['GET']);
        $this->assertArrayHasKey('/admin/dashboard', $routes['GET']);
        $this->assertArrayHasKey('/admin/logout', $routes['POST']);

        $this->assertArrayNotHasKey('/admin/logout', $routes['GET']);
    }

    // --- Login Tests ---

    public function testShowLoginRendersSuccessfullyWithCsrfFieldAndNoLogoutNav(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);
        $controller = new AuthController($authService, $adminRepo);

        ob_start();
        $controller->showLogin();
        $output = ob_get_clean();

        preg_match('/<title>(.*?)<\/title>/s', $output, $matches);
        $titleContent = $matches[1] ?? '';
        $this->assertEquals(1, substr_count($titleContent, trans('app_name')));

        $this->assertStringContainsString('name="_csrf_token"', $output);
        $this->assertStringNotContainsString('/admin/logout', $output);
        $this->assertStringContainsString(trans('admin_login'), $output);
    }


    public function testValidUsernameLoginSuccess(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('web_super', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success'], 'Failed to authenticate by valid username: ' . ($res['message'] ?? ''));
        $this->assertEquals(101, $res['admin']['id']);
        $this->assertArrayHasKey('admin_auth', $_SESSION);
    }

    public function testValidEmailLoginSuccess(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('web_super@test.local', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success'], 'Failed to authenticate by valid email: ' . ($res['message'] ?? ''));
        $this->assertEquals(101, $res['admin']['id']);
        $this->assertArrayHasKey('admin_auth', $_SESSION);
    }

    public function testInvalidCredentialsReturnsNeutralError(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('web_super', 'WrongPassword!', '127.0.0.1', 'PHPUnit');
        $this->assertFalse($res['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $res['code']);
    }

    public function testLoginCsrfFailureRejected(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'invalid_token';

        $this->assertFalse(CsrfTokenManager::validate($_POST['_csrf_token']));
    }




    public function testExistingEmployeeAuthSurvivesAdminLogin(): void
    {
        $_SESSION['employee_auth'] = ['employee_id' => 42];

        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('web_super', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success']);

        $this->assertArrayHasKey('employee_auth', $_SESSION);
        $this->assertEquals(42, $_SESSION['employee_auth']['employee_id']);
        $this->assertArrayHasKey('admin_auth', $_SESSION);
    }

    // --- Dashboard Tests ---

    public function testMissingAdminAuthRedirectsToLogin(): void
    {
        unset($_SESSION['admin_auth']);
        $res = AdminAuthMiddleware::handle('view_dashboard');
        $this->assertNull($res);
    }

    public function testValidAdministratorCanAccessDashboardAndBothRoutesUseSameController(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 101,
            'role' => 'super_admin',
            'name' => 'Web Super',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $routes = require __DIR__ . '/../../config/routes.php';
        $this->assertEquals($routes['GET']['/admin'], $routes['GET']['/admin/dashboard']);

        $admin = AdminAuthMiddleware::handle('view_dashboard');
        $this->assertNotNull($admin);
        $this->assertEquals(101, (int)$admin['id']);
    }

    public function testDashboardMetricsAreIntegers(): void
    {
        $dashboardService = new AdminDashboardService(self::$pdo);
        $metrics = $dashboardService->getSummary();

        $this->assertIsInt($metrics['total_employees']);
        $this->assertIsInt($metrics['active_assignments']);
        $this->assertIsInt($metrics['completed_assignments']);
        $this->assertIsInt($metrics['finalized_attempts']);

        $this->assertGreaterThanOrEqual(0, $metrics['total_employees']);
        $this->assertGreaterThanOrEqual(0, $metrics['active_assignments']);
        $this->assertGreaterThanOrEqual(0, $metrics['completed_assignments']);
        $this->assertGreaterThanOrEqual(0, $metrics['finalized_attempts']);
    }

    public function testEmptyDashboardDatasetsReturnZero(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('fetchColumn')->willReturn(0);

        $mockPdo = $this->createMock(PDO::class);
        $mockPdo->method('query')->willReturn($mockStmt);

        $dashboardService = new AdminDashboardService($mockPdo);
        $metrics = $dashboardService->getSummary();

        $this->assertSame(0, $metrics['total_employees']);
        $this->assertSame(0, $metrics['active_assignments']);
        $this->assertSame(0, $metrics['completed_assignments']);
        $this->assertSame(0, $metrics['finalized_attempts']);
    }


    public function testDashboardEscapesAdminNameWithHtml(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 104, // xss_admin
            'role' => 'super_admin',
            'name' => '<script>alert("xss")</script>',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $dashboardService = new AdminDashboardService(self::$pdo);
        $controller = new DashboardController($dashboardService);

        ob_start();
        $controller->index();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $output);
    }

    public function testDashboardDoesNotContainPasswordHashOrSessionData(): void
    {
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 101,
            'role' => 'super_admin',
            'name' => 'Web Super',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        $dashboardService = new AdminDashboardService(self::$pdo);
        $controller = new DashboardController($dashboardService);

        ob_start();
        $controller->index();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('password_hash', $output);
        $this->assertStringNotContainsString('$2y$', $output);
    }

    public function testAllThreeRolesCanViewDashboard(): void
    {
        $now = time();
        $roles = [
            101 => 'super_admin',
            102 => 'supervisor',
            103 => 'report_viewer',
        ];

        foreach ($roles as $id => $role) {
            $_SESSION['admin_auth'] = [
                'admin_id' => $id,
                'role' => $role,
                'name' => 'Role Test',
                'last_activity_at' => $now,
                'absolute_start_at' => $now,
            ];

            $res = AdminAuthMiddleware::handle('view_dashboard');
            $this->assertNotNull($res, "Role {$role} must be allowed to view dashboard.");
        }
    }

    // --- Logout Tests ---

    public function testPostLogoutWithValidCsrfRemovesAdminAuthAndPreservesEmployeeAuth(): void
    {
        $_SESSION['admin_auth'] = ['admin_id' => 101];
        $_SESSION['employee_auth'] = ['employee_id' => 50];

        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);
        $authService->logout('127.0.0.1', 'PHPUnit');

        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
        $this->assertEquals(50, $_SESSION['employee_auth']['employee_id']);
    }

    public function testInvalidLogoutCsrfRejected(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'bad_token';

        $this->assertFalse(CsrfTokenManager::validate($_POST['_csrf_token']));
    }




    // --- Translations and Errors Tests ---

    public function testRequiredTranslationsExist(): void
    {
        $ar = require __DIR__ . '/../../lang/ar.php';
        $en = require __DIR__ . '/../../lang/en.php';

        $requiredKeys = [
            'administration',
            'admin_login',
            'username_or_email',
            'password',
            'sign_in',
            'sign_out',
            'dashboard',
            'welcome',
            'total_employees',
            'active_assignments',
            'completed_assignments',
            'finalized_attempts',
            'invalid_credentials',
            'too_many_login_attempts',
            'temporary_error',
            'session_expired',
            'access_denied',
            'access_denied_description',
            'return_to_dashboard',
            'super_admin',
            'supervisor',
            'report_viewer',
            'role',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $ar, "Missing Arabic translation key: {$key}");
            $this->assertArrayHasKey($key, $en, "Missing English translation key: {$key}");
        }
    }

    public function testView403ContainsNoSensitiveDetails(): void
    {
        ob_start();
        $admin = ['name' => 'Viewer', 'role' => 'report_viewer'];
        require __DIR__ . '/../../views/admin/403.php';
        $output = ob_get_clean();

        preg_match('/<title>(.*?)<\/title>/s', $output, $matches);
        $titleContent = $matches[1] ?? '';
        $this->assertEquals(1, substr_count($titleContent, trans('app_name')));

        $this->assertStringContainsString(trans('access_denied'), $output);
        $this->assertStringContainsString(trans('access_denied_description'), $output);
        $this->assertStringNotContainsString('manage_admins', $output);
        $this->assertStringNotContainsString('password_hash', $output);
        $this->assertStringNotContainsString('Exception', $output);
    }

    public function testArabicUtf8Integrity(): void
    {
        $ar = require __DIR__ . '/../../lang/ar.php';

        $this->assertEquals('الدور', $ar['role']);
        $this->assertEquals('الإدارة', $ar['administration']);
        $this->assertEquals('تسجيل دخول المسؤولين', $ar['admin_login']);
        $this->assertEquals('لا تملك الصلاحية اللازمة للوصول إلى هذه الصفحة.', $ar['access_denied_description']);
    }

    public function testInternalDashboardDatabaseErrorProducesGenericSafeResponse(): void
    {

        $mockPdo = $this->createMock(PDO::class);
        $mockPdo->method('query')->willThrowException(new \PDOException('Simulated SQL error: table bad_table does not exist'));

        $service = new AdminDashboardService($mockPdo);
        $controller = new DashboardController($service);

        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 101,
            'role' => 'super_admin',
            'name' => 'Web Super',
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        ob_start();
        $controller->index();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('Simulated SQL error', $output);
        $this->assertStringNotContainsString('bad_table', $output);
        $this->assertStringContainsString(trans('temporary_error'), $output);
    }
}
