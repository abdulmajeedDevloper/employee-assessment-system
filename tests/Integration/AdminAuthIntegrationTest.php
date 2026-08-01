<?php

namespace Tests\Integration;

use App\Repositories\AdminRepository;
use App\Services\AdminAuthService;
use App\Support\Database;
use PDO;
use Tests\TestCase;

class AdminAuthIntegrationTest extends TestCase
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

        // 1. Verify or create baseline schema first
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = self::$pdo->query("SHOW TABLES;")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('admins', $tables, true) || !in_array('audit_logs', $tables, true)) {
            $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
            self::$pdo->exec($sql1);
            $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
            self::$pdo->exec($sql2);
        }

        // 2. Enable foreign keys
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // 3. FK-safe fixture cleanup AFTER table creation
        self::cleanupFixtures();

        // 4. Seed test admin accounts
        $hashCost12 = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
        $hashCost10 = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 10]);

        $stmt = self::$pdo->prepare("
            INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at)
            VALUES (:id, :name, :email, :username, :hash, :role, :active, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");

        $stmt->execute([':id' => 10, ':name' => 'Super Admin', ':email' => 'super@test.local', ':username' => 'superadmin', ':hash' => $hashCost12, ':role' => 'super_admin', ':active' => 1]);
        $stmt->execute([':id' => 11, ':name' => 'Supervisor One', ':email' => 'supervisor@test.local', ':username' => 'supervisor1', ':hash' => $hashCost12, ':role' => 'supervisor', ':active' => 1]);
        $stmt->execute([':id' => 12, ':name' => 'Report Viewer', ':email' => 'viewer@test.local', ':username' => 'viewer1', ':hash' => $hashCost12, ':role' => 'report_viewer', ':active' => 1]);
        $stmt->execute([':id' => 13, ':name' => 'Inactive Admin', ':email' => 'inactive@test.local', ':username' => 'inactive_admin', ':hash' => $hashCost12, ':role' => 'supervisor', ':active' => 0]);
        $stmt->execute([':id' => 14, ':name' => 'Rehash Admin', ':email' => 'rehash@test.local', ':username' => 'rehash_admin', ':hash' => $hashCost10, ':role' => 'supervisor', ':active' => 1]);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$pdo)) {
            self::cleanupFixtures();
        }
    }

    private static function cleanupFixtures(): void
    {
        $fixtureIds = '10, 11, 12, 13, 14';
        self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id IN ({$fixtureIds});");
        self::$pdo->exec("DELETE FROM security_events;");
        self::$pdo->exec("DELETE FROM rate_limits;");
        self::$pdo->exec("DELETE FROM admins WHERE id IN ({$fixtureIds});");
    }

    protected function setUp(): void
    {
        parent::setUp();

        $admin10Count = (int) self::$pdo->query("SELECT COUNT(*) FROM admins WHERE id = 10")->fetchColumn();
        if ($admin10Count === 0) {
            self::setUpBeforeClass();
        }

        $hashCost10 = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 10]);
        self::$pdo->exec("UPDATE admins SET password_hash = '{$hashCost10}' WHERE id = 14;");
        self::$pdo->exec("DELETE FROM rate_limits;");
        self::$pdo->exec("DELETE FROM audit_logs;");
        self::$pdo->exec("DELETE FROM security_events;");
        self::$pdo->exec("UPDATE admins SET last_login_at = NULL WHERE id IN (10, 11, 12, 13, 14);");

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    public function testUsernameLoginSuccess(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('superadmin', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success']);
        $this->assertEquals(10, $res['admin']['id']);
        $this->assertArrayHasKey('admin_auth', $_SESSION);
        $this->assertEquals(10, $_SESSION['admin_auth']['admin_id']);
    }

    public function testEmailLoginSuccess(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('supervisor@test.local', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success']);
        $this->assertEquals(11, $res['admin']['id']);
        $this->assertArrayHasKey('admin_auth', $_SESSION);
        $this->assertEquals(11, $_SESSION['admin_auth']['admin_id']);
    }

    public function testAuthenticateReturnsSafePayloadWithoutPasswordHash(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('superadmin', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success']);

        $this->assertArrayNotHasKey('password_hash', $res);
        $this->assertArrayNotHasKey('session_id', $res);
        $this->assertArrayNotHasKey('csrf_token', $res);

        $safeAdmin = $res['admin'];
        $this->assertArrayNotHasKey('password_hash', $safeAdmin);

        $keys = array_keys($safeAdmin);
        sort($keys);
        $expectedKeys = ['id', 'is_active', 'name', 'role'];
        $this->assertEquals($expectedKeys, $keys);
    }

    public function testInvalidPasswordRejection(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('superadmin', 'WrongPassword!', '127.0.0.1', 'PHPUnit');
        $this->assertFalse($res['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $res['code']);
    }

    public function testNonexistentAccountRejection(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('nonexistent_user', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertFalse($res['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $res['code']);
    }

    public function testInactiveAccountRejection(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('inactive_admin', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertFalse($res['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $res['code']);
    }

    public function testAdminRepositoryLookupFailureReturnsInternalErrorAndNoAudit(): void
    {
        $failingRepo = new class(self::$pdo) extends AdminRepository {
            public function findForAuthenticationByIdentifier(string $normalizedIdentifier): ?array {
                throw new \PDOException("Simulated database connection failure");
            }
        };

        $authService = new AdminAuthService($failingRepo, self::$pdo);
        $res = $authService->authenticate('superadmin', 'Password123!', '127.0.0.1', 'PHPUnit');

        $this->assertFalse($res['success']);
        $this->assertEquals('INTERNAL_ERROR', $res['code']);
        $this->assertEquals('An unexpected error occurred.', $res['message']);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);

        $count = (int)self::$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'ADMIN_LOGIN_SUCCESS'")->fetchColumn();
        $this->assertEquals(0, $count);
    }

    public function testIdenticalNeutralFailureResult(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res1 = $authService->authenticate('nonexistent_user', 'Password123!', '127.0.0.1', 'PHPUnit');
        $res2 = $authService->authenticate('superadmin', 'WrongPassword!', '127.0.0.1', 'PHPUnit');
        $res3 = $authService->authenticate('inactive_admin', 'Password123!', '127.0.0.1', 'PHPUnit');

        $expected = [
            'success' => false,
            'message' => 'Invalid username/email or password.',
            'code' => 'INVALID_CREDENTIALS'
        ];

        $this->assertEquals($expected, $res1);
        $this->assertEquals($expected, $res2);
        $this->assertEquals($expected, $res3);
    }

    public function testLastLoginAtUpdatedOnSuccess(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $before = $adminRepo->findById(10);
        $this->assertNull($before['last_login_at']);

        $res = $authService->authenticate('superadmin', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success']);

        $after = $adminRepo->findById(10);
        $this->assertNotNull($after['last_login_at']);
    }

    public function testNoLastLoginAtUpdateOnFailure(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('superadmin', 'WrongPassword!', '127.0.0.1', 'PHPUnit');
        $this->assertFalse($res['success']);

        $after = $adminRepo->findById(10);
        $this->assertNull($after['last_login_at']);
    }

    public function testPasswordRehashOnValidSuccessfulLogin(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $adminBefore = $adminRepo->findForAuthenticationByIdentifier('rehash_admin');
        $infoBefore = password_get_info($adminBefore['password_hash']);
        $this->assertEquals(10, $infoBefore['options']['cost']);

        $res = $authService->authenticate('rehash_admin', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success']);

        $adminAfter = $adminRepo->findForAuthenticationByIdentifier('rehash_admin');
        $infoAfter = password_get_info($adminAfter['password_hash']);
        $this->assertEquals(12, $infoAfter['options']['cost']);
    }

    public function testNoRehashAfterRejectedLogin(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('rehash_admin', 'WrongPassword!', '127.0.0.1', 'PHPUnit');
        $this->assertFalse($res['success']);

        $adminAfter = $adminRepo->findForAuthenticationByIdentifier('rehash_admin');
        $infoAfter = password_get_info($adminAfter['password_hash']);
        $this->assertEquals(10, $infoAfter['options']['cost']);
    }

    public function testInvalidRoleRepositoryResultRejectedNeutrallyAndNoRehash(): void
    {
        $invalidRoleRepo = new class(self::$pdo) extends AdminRepository {
            public function findForAuthenticationByIdentifier(string $normalizedIdentifier): ?array {
                return [
                    'id' => 9999,
                    'name' => 'Invalid Role Admin',
                    'email' => 'invalidrole@test.local',
                    'username' => 'invalidrole',
                    'password_hash' => password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 10]),
                    'role' => 'invalid_role_enum',
                    'is_active' => 1,
                    'last_login_at' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        };

        $authService = new AdminAuthService($invalidRoleRepo, self::$pdo);
        $res = $authService->authenticate('invalidrole', 'Password123!', '127.0.0.1', 'PHPUnit');

        $this->assertFalse($res['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $res['code']);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
    }

    public function testMinimalAdminAuthPayload(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $res = $authService->authenticate('superadmin', 'Password123!', '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success']);

        $authPayload = $_SESSION['admin_auth'];
        $keys = array_keys($authPayload);
        sort($keys);

        $expectedKeys = ['absolute_start_at', 'admin_id', 'auth_version', 'authenticated_at', 'display_name', 'last_activity_at', 'name', 'role'];
        $this->assertEquals($expectedKeys, $keys);
        $this->assertEquals(10, $authPayload['admin_id']);
        $this->assertEquals('super_admin', $authPayload['role']);
        $this->assertEquals('Super Admin', $authPayload['name']);
    }

    public function testNonexistentIdentifierRateLimitingLockout(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        $target = 'ghost_user_' . uniqid('', true);
        $ip = '10.0.0.199';

        for ($i = 0; $i < 5; $i++) {
            $res = $authService->authenticate($target, 'Password123!', $ip, 'PHPUnit');
            $this->assertFalse($res['success']);
            $this->assertEquals('INVALID_CREDENTIALS', $res['code']);
        }

        // 6th call must return RATE_LIMIT_EXCEEDED
        $res6 = $authService->authenticate($target, 'Password123!', $ip, 'PHPUnit');
        $this->assertFalse($res6['success']);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $res6['code']);
    }

    public function testLogoutRegeneratesSessionIdAndMultipleLogoutsCreateOnlyOneAuditRecord(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_auth'] = ['admin_id' => 11, 'role' => 'supervisor'];
        $_SESSION['employee_auth'] = ['employee_id' => 888];

        $oldSessionId = session_id();

        $adminRepo = new AdminRepository(self::$pdo);
        $authService = new AdminAuthService($adminRepo, self::$pdo);

        // First logout
        $authService->logout('127.0.0.1', 'PHPUnit');

        $this->assertNotEquals($oldSessionId, session_id());
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
        $this->assertEquals(888, $_SESSION['employee_auth']['employee_id']);

        // Second logout (replayed)
        $authService->logout('127.0.0.1', 'PHPUnit');

        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
        $this->assertEquals(888, $_SESSION['employee_auth']['employee_id']);

        $count = (int)self::$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'ADMIN_LOGOUT' AND admin_id = 11")->fetchColumn();
        $this->assertEquals(1, $count);
    }
}
