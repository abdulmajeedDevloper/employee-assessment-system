<?php

namespace Tests\Security;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SessionMiddleware;
use App\Repositories\AdminRepository;
use App\Services\AdminAuthService;
use App\Security\HashManager;
use App\Support\Database;
use PDO;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
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
        Database::setInstance(self::$pdo);

        // 1. Verify or create baseline schema first
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = self::$pdo->query("SHOW TABLES;")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('admins', $tables, true) || !in_array('audit_logs', $tables, true)) {
            $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
            self::$pdo->exec($sql1);
        }

        $auditCols = self::$pdo->query("DESCRIBE audit_logs;")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('actor_type', $auditCols, true)) {
            $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
            self::$pdo->exec($sql2);
        }

        // 2. Enable foreign keys
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // 3. FK-safe fixture cleanup AFTER table creation
        self::cleanupFixtures();

        // 4. Seed test admin accounts
        $hashCost12 = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = self::$pdo->prepare("
            INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at)
            VALUES (:id, :name, :email, :username, :hash, :role, :active, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");

        $stmt->execute([':id' => 20, ':name' => 'Sec Super', ':email' => 'sec_super@test.local', ':username' => 'sec_super', ':hash' => $hashCost12, ':role' => 'super_admin', ':active' => 1]);
        $stmt->execute([':id' => 21, ':name' => 'Sec Supervisor', ':email' => 'sec_supervisor@test.local', ':username' => 'sec_supervisor', ':hash' => $hashCost12, ':role' => 'supervisor', ':active' => 1]);
        $stmt->execute([':id' => 22, ':name' => 'Sec Viewer', ':email' => 'sec_viewer@test.local', ':username' => 'sec_viewer', ':hash' => $hashCost12, ':role' => 'report_viewer', ':active' => 1]);
        $stmt->execute([':id' => 23, ':name' => 'Sec Inactive', ':email' => 'sec_inactive@test.local', ':username' => 'sec_inactive', ':hash' => $hashCost12, ':role' => 'supervisor', ':active' => 0]);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$pdo)) {
            self::$pdo->exec("DROP TRIGGER IF EXISTS trg_test_sec_event_fail;");
            self::cleanupFixtures();
            AdminAuthMiddleware::setRepository(null);
        }
    }

    private static function cleanupFixtures(): void
    {
        $fixtureIds = '20, 21, 22, 23';
        self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id IN ({$fixtureIds});");
        self::$pdo->exec("DELETE FROM security_events;");
        self::$pdo->exec("DELETE FROM rate_limits;");
        self::$pdo->exec("DELETE FROM admins WHERE id IN ({$fixtureIds});");
    }

    protected function setUp(): void
    {
        parent::setUp();

        $admin20Count = (int) self::$pdo->query("SELECT COUNT(*) FROM admins WHERE id = 20")->fetchColumn();
        if ($admin20Count === 0) {
            self::setUpBeforeClass();
        }

        self::$pdo->exec("DELETE FROM rate_limits;");
        self::$pdo->exec("DELETE FROM audit_logs;");
        self::$pdo->exec("DELETE FROM security_events;");

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }

        AdminAuthMiddleware::setRepository(new AdminRepository(self::$pdo));
    }

    public function testAdminLoginFailedSecurityEventRecorded(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $authService->authenticate('sec_super', 'WrongPassword!', '127.0.0.1', 'PHPUnit');

        $stmt = self::$pdo->query("SELECT * FROM security_events WHERE event_type = 'ADMIN_LOGIN_FAILED'");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertNull($event['employee_id']);
        $this->assertNull($event['attempt_id']);
        $this->assertEquals(HashManager::hashRateLimitIdentifier('sec_super'), $event['identifier_hash']);

        $metadata = json_decode($event['metadata'], true);
        $this->assertEquals('admin_login', $metadata['action_key']);
        $this->assertEquals('invalid_credentials', $metadata['failure_class']);
    }

    public function testAdminLoginRateLimitedSecurityEventRecorded(): void
    {
        $action = 'admin_login';
        $target = 'sec_super';
        $ip = '10.0.0.50';

        // Lock out target
        for ($i = 0; $i < 5; $i++) {
            RateLimitMiddleware::recordAttempt($action, $target, $ip, false);
        }

        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('sec_super', 'Password123!', $ip, 'PHPUnit');
        $this->assertFalse($res['success']);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $res['code']);

        $stmt = self::$pdo->query("SELECT * FROM security_events WHERE event_type = 'ADMIN_LOGIN_RATE_LIMITED'");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $events);
    }

    public function testNoRawIdentifierPasswordHashInSecurityEventsOrAuditLogs(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        // --- 1. Failed-login security event verification ---
        $failedIdentifier = 'security-leak-probe@example.invalid';
        $failedPassword = 'LeakProbePassword!147';
        $failedIp = '192.0.2.147';
        $failedUa = 'PHPUnit-LeakProbe-UA-Failed';

        $failedRes = $authService->authenticate($failedIdentifier, $failedPassword, $failedIp, $failedUa);

        $this->assertFalse($failedRes['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $failedRes['code']);

        $secStmt = self::$pdo->prepare("
            SELECT * FROM security_events
            WHERE ip_address = :ip AND user_agent = :ua AND event_type = 'ADMIN_LOGIN_FAILED'
            ORDER BY id DESC LIMIT 1
        ");
        $secStmt->execute([':ip' => $failedIp, ':ua' => $failedUa]);
        $secRow = $secStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($secRow, "Matching ADMIN_LOGIN_FAILED security event must exist.");
        $this->assertNotEmpty($secRow['identifier_hash']);
        $this->assertNotEquals($failedIdentifier, $secRow['identifier_hash']);

        $secMetadataStr = (string)($secRow['metadata'] ?? '');
        $this->assertNotEmpty($secMetadataStr);
        $secMetadata = json_decode($secMetadataStr, true);
        $this->assertIsArray($secMetadata, "Metadata must be valid JSON.");

        $this->assertStringNotContainsString($failedIdentifier, $secMetadataStr);
        $this->assertStringNotContainsString($failedPassword, $secMetadataStr);
        $this->assertStringNotContainsString('password_hash', $secMetadataStr);
        $this->assertStringNotContainsString('$2y$', $secMetadataStr);

        // --- 2. Successful-login audit log verification ---
        $successIdentifier = 'sec_super';
        $successPassword = 'Password123!';
        $successIp = '192.0.2.148';
        $successUa = 'PHPUnit-LeakProbe-UA-Success';

        $successRes = $authService->authenticate($successIdentifier, $successPassword, $successIp, $successUa);

        $this->assertTrue($successRes['success']);
        $this->assertArrayHasKey('admin', $successRes);
        $this->assertArrayNotHasKey('password_hash', $successRes['admin']);

        $auditStmt = self::$pdo->prepare("
            SELECT * FROM audit_logs
            WHERE ip_address = :ip AND user_agent = :ua AND action = 'ADMIN_LOGIN_SUCCESS'
            ORDER BY id DESC LIMIT 1
        ");
        $auditStmt->execute([':ip' => $successIp, ':ua' => $successUa]);
        $auditRow = $auditStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($auditRow, "Matching ADMIN_LOGIN_SUCCESS audit log must exist.");
        $this->assertNull($auditRow['old_values'], "old_values must be null.");

        $newValuesStr = (string)($auditRow['new_values'] ?? '');
        $this->assertNotEmpty($newValuesStr);
        $newValues = json_decode($newValuesStr, true);
        $this->assertIsArray($newValues, "new_values must be valid JSON.");
        $this->assertArrayHasKey('role', $newValues);
        $this->assertArrayHasKey('login_method', $newValues);

        $this->assertStringNotContainsString($successIdentifier, $newValuesStr);
        $this->assertStringNotContainsString($successPassword, $newValuesStr);
        $this->assertStringNotContainsString('password_hash', $newValuesStr);
        $this->assertStringNotContainsString('$2y$', $newValuesStr);

        $serializedAuditRow = (string)json_encode($auditRow);
        $this->assertStringNotContainsString($successPassword, $serializedAuditRow);
        $this->assertStringNotContainsString('password_hash', $serializedAuditRow);

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['admin_auth']);
        }
    }

    public function testAdminLoginRateLimitingIsIndependentFromEmpLogin(): void
    {
        $target = 'shared_target';
        $ip = '10.0.0.70';

        // Lock emp_login
        for ($i = 0; $i < 5; $i++) {
            RateLimitMiddleware::recordAttempt('emp_login', $target, $ip, false);
        }

        $this->assertTrue(RateLimitMiddleware::isLocked('emp_login', $target, $ip));
        // admin_login must NOT be locked
        $this->assertFalse(RateLimitMiddleware::isLocked('admin_login', $target, $ip));
    }

    public function testSecurityEventFailureDoesNotExposeException(): void
    {
        try {
            self::$pdo->exec("
                CREATE TRIGGER trg_test_sec_event_fail BEFORE INSERT ON security_events
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Simulated security event insert failure';
                END;
            ");

            $adminRepo = new AdminRepository(self::$pdo);
            $authService = new AdminAuthService($adminRepo, self::$pdo);

            $res = $authService->authenticate('sec_super', 'WrongPassword!', '127.0.0.1', 'PHPUnit');
            $this->assertFalse($res['success']);
            $this->assertEquals('INVALID_CREDENTIALS', $res['code']);
        } finally {
            self::$pdo->exec("DROP TRIGGER IF EXISTS trg_test_sec_event_fail;");
        }
    }

    public function testMiddlewareMissingAdminAuthRedirects(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['admin_auth']);

        $res = AdminAuthMiddleware::handle();
        $this->assertNull($res);
    }

    public function testMiddlewareInvalidAdminIdRejects(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_auth'] = ['admin_id' => -1];
        $_SESSION['employee_auth'] = ['employee_id' => 55];

        $res = AdminAuthMiddleware::handle();
        $this->assertNull($res);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
    }

    public function testMiddlewareDeletedAdministratorRejects(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_auth'] = ['admin_id' => 99999];
        $_SESSION['employee_auth'] = ['employee_id' => 55];

        $res = AdminAuthMiddleware::handle();
        $this->assertNull($res);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
    }

    public function testMiddlewareInactiveAdministratorRejects(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_auth'] = ['admin_id' => 23]; // sec_inactive
        $_SESSION['employee_auth'] = ['employee_id' => 55];

        $res = AdminAuthMiddleware::handle();
        $this->assertNull($res);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
    }

    public function testMiddlewareRejectsMissingInvalidOrFutureTimestampsAndPreservesEmployeeAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();

        $invalidTimestamps = [
            'missing_last_activity' => ['admin_id' => 20, 'absolute_start_at' => $now],
            'missing_absolute_start' => ['admin_id' => 20, 'last_activity_at' => $now],
            'zero_last_activity' => ['admin_id' => 20, 'last_activity_at' => 0, 'absolute_start_at' => $now],
            'negative_last_activity' => ['admin_id' => 20, 'last_activity_at' => -100, 'absolute_start_at' => $now],
            'string_timestamp' => ['admin_id' => 20, 'last_activity_at' => 'invalid_time', 'absolute_start_at' => $now],
            'future_last_activity' => ['admin_id' => 20, 'last_activity_at' => $now + 3600, 'absolute_start_at' => $now],
            'future_absolute_start' => ['admin_id' => 20, 'last_activity_at' => $now, 'absolute_start_at' => $now + 3600],
        ];

        foreach ($invalidTimestamps as $caseName => $authPayload) {
            $_SESSION['admin_auth'] = $authPayload;
            $_SESSION['employee_auth'] = ['employee_id' => 77];

            $res = AdminAuthMiddleware::handle();

            $this->assertNull($res, "Failed for case: {$caseName}");
            $this->assertArrayNotHasKey('admin_auth', $_SESSION, "admin_auth must be unset for case: {$caseName}");
            $this->assertArrayHasKey('employee_auth', $_SESSION, "employee_auth must be preserved for case: {$caseName}");
            $this->assertEquals(77, $_SESSION['employee_auth']['employee_id']);
        }
    }

    public function testMiddlewareNeverTrustsForgedSessionRole(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 21,
            'role' => 'super_admin',
            'name' => 'Forged Admin',
            'last_activity_at' => $now,
            'absolute_start_at' => $now
        ];

        $res = AdminAuthMiddleware::handle();
        $this->assertNotNull($res);
        $this->assertEquals('supervisor', $res['role']);
        $this->assertEquals('supervisor', $_SESSION['admin_auth']['role']);
    }

    public function testMiddlewareDatabaseRoleAndNameRefresh(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 20,
            'role' => 'old_role',
            'name' => 'Old Name',
            'last_activity_at' => $now,
            'absolute_start_at' => $now
        ];

        $res = AdminAuthMiddleware::handle();
        $this->assertNotNull($res);
        $this->assertEquals('super_admin', $_SESSION['admin_auth']['role']);
        $this->assertEquals('Sec Super', $_SESSION['admin_auth']['name']);
    }

    public function testMiddlewareIdleTimeout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 20,
            'role' => 'super_admin',
            'name' => 'Sec Super',
            'last_activity_at' => $now - (31 * 60),
            'absolute_start_at' => $now - 600
        ];
        $_SESSION['employee_auth'] = ['employee_id' => 55];

        $res = AdminAuthMiddleware::handle();
        $this->assertNull($res);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
    }

    public function testMiddlewareAbsoluteTimeout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 20,
            'role' => 'super_admin',
            'name' => 'Sec Super',
            'last_activity_at' => $now - 60,
            'absolute_start_at' => $now - (8.5 * 3600)
        ];
        $_SESSION['employee_auth'] = ['employee_id' => 55];

        $res = AdminAuthMiddleware::handle();
        $this->assertNull($res);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
    }

    public function testMiddlewareLastActivityAtUpdate(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $oldActivity = time() - 300;
        $_SESSION['admin_auth'] = [
            'admin_id' => 20,
            'role' => 'super_admin',
            'name' => 'Sec Super',
            'last_activity_at' => $oldActivity,
            'absolute_start_at' => time() - 300
        ];

        $res = AdminAuthMiddleware::handle();
        $this->assertNotNull($res);
        $this->assertGreaterThan($oldActivity, $_SESSION['admin_auth']['last_activity_at']);
    }

    public function testMiddlewareViewDashboardAllowedForAllThreeValidRoles(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();

        foreach ([20 => 'super_admin', 21 => 'supervisor', 22 => 'report_viewer'] as $id => $role) {
            $_SESSION['admin_auth'] = [
                'admin_id' => $id,
                'role' => $role,
                'name' => 'Test',
                'last_activity_at' => $now,
                'absolute_start_at' => $now
            ];

            $res = AdminAuthMiddleware::handle('view_dashboard');
            $this->assertNotNull($res, "view_dashboard should be allowed for admin ID {$id} with role {$role}");
        }
    }

    public function testMiddlewareUnauthorizedPermissionRejected(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => 21,
            'role' => 'supervisor',
            'name' => 'Sec Supervisor',
            'last_activity_at' => $now,
            'absolute_start_at' => $now
        ];

        $res = AdminAuthMiddleware::handle('manage_admins');
        $this->assertNull($res);
    }

    public function testSessionMiddlewareGlobalTimeoutDoesNotDestroyBothAuthenticationContexts(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['employee_auth'] = ['employee_id' => 1];
        $_SESSION['admin_auth'] = ['admin_id' => 20];

        SessionMiddleware::handle();

        $this->assertArrayHasKey('employee_auth', $_SESSION);
        $this->assertArrayHasKey('admin_auth', $_SESSION);
    }
}
