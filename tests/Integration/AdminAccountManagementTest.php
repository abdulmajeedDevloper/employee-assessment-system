<?php

namespace Tests\Integration;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Repositories\AdminRepository;
use App\Repositories\AdminUserRepository;
use App\Services\AdminAuthService;
use App\Services\AdminUserManagementService;
use App\Services\AuthorizationService;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminAccountManagementTest extends TestCase
{
    private static PDO $pdo;
    private AdminUserRepository $userRepo;
    private AdminUserManagementService $userService;
    private AdminAuthService $authService;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::getInstance();
        $upFiles = glob(__DIR__ . '/../../database/migrations/*.up.sql');
        sort($upFiles);
        foreach ($upFiles as $file) {
            try {
                self::$pdo->exec(file_get_contents($file));
            } catch (\Throwable $e) {
                // Ignore idempotent errors if table/column already exists
            }
        }
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit-Test';

        $this->userRepo = new AdminUserRepository(self::$pdo);
        $this->authService = new AdminAuthService(null, self::$pdo);
        $this->userService = new AdminUserManagementService($this->userRepo, null, $this->authService, self::$pdo);

        // Clean up test fixture accounts and audit logs safely
        self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id IN (SELECT id FROM admins WHERE username LIKE 'test_mgr_%' OR email LIKE 'test_mgr_%@test.local')");
        self::$pdo->exec("DELETE FROM admins WHERE username LIKE 'test_mgr_%' OR email LIKE 'test_mgr_%@test.local'");
    }

    protected function tearDown(): void
    {
        self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id IN (SELECT id FROM admins WHERE username LIKE 'test_mgr_%' OR email LIKE 'test_mgr_%@test.local')");
        self::$pdo->exec("DELETE FROM admins WHERE username LIKE 'test_mgr_%' OR email LIKE 'test_mgr_%@test.local'");
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    private function createTestAdmin(array $overrides = []): array
    {
        $unique = bin2hex(random_bytes(4));
        $defaults = [
            'display_name' => 'Test Manager ' . $unique,
            'name' => 'Test Manager ' . $unique,
            'username' => 'test_mgr_' . $unique,
            'email' => 'test_mgr_' . $unique . '@test.local',
            'password_hash' => password_hash('StrongPass123!', PASSWORD_DEFAULT),
            'role' => 'admin',
            'is_active' => 1,
            'is_primary' => 0,
            'auth_version' => 1,
        ];

        $data = array_merge($defaults, $overrides);

        $stmt = self::$pdo->prepare("
            INSERT INTO admins (name, display_name, username, email, password_hash, role, is_active, is_primary, auth_version, created_at, updated_at)
            VALUES (:name, :display_name, :username, :email, :password_hash, :role, :is_active, :is_primary, :auth_version, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':display_name' => $data['display_name'],
            ':username' => strtolower($data['username']),
            ':email' => strtolower($data['email']),
            ':password_hash' => $data['password_hash'],
            ':role' => $data['role'],
            ':is_active' => (int)$data['is_active'],
            ':is_primary' => (int)$data['is_primary'],
            ':auth_version' => (int)$data['auth_version'],
        ]);

        $id = (int)self::$pdo->lastInsertId();
        return $this->userRepo->findById($id);
    }

    private function setupSession(array $admin): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => (int)$admin['id'],
            'role' => $admin['role'],
            'name' => $admin['name'],
            'display_name' => $admin['display_name'] ?? $admin['name'],
            'auth_version' => (int)($admin['auth_version'] ?? 1),
            'authenticated_at' => $now,
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];
    }

    /** 1. Super Admin can open account list */
    public function testSuperAdminCanAccessAccountList(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin']);
        $this->setupSession($superAdmin);

        $result = AdminAuthMiddleware::handle('manage_admins');
        $this->assertNotNull($result);
        $this->assertEquals($superAdmin['id'], $result['id']);
    }

    /** 2. Non-super_admin roles receive 403 / null from middleware */
    public function testNonSuperAdminAccessIsForbidden(): void
    {
        foreach (['admin', 'supervisor', 'report_viewer'] as $role) {
            $admin = $this->createTestAdmin(['role' => $role]);
            $this->setupSession($admin);

            $result = AdminAuthMiddleware::handle('manage_admins');
            $this->assertNull($result);
        }
    }

    /** 3. AuthorizationService respects permission matrix */
    public function testRolePermissionMatrix(): void
    {
        $superAdmin = ['role' => 'super_admin', 'is_active' => 1];
        $admin = ['role' => 'admin', 'is_active' => 1];
        $supervisor = ['role' => 'supervisor', 'is_active' => 1];
        $reportViewer = ['role' => 'report_viewer', 'is_active' => 1];

        $this->assertTrue(AuthorizationService::can($superAdmin, 'manage_admins'));
        $this->assertFalse(AuthorizationService::can($admin, 'manage_admins'));
        $this->assertFalse(AuthorizationService::can($supervisor, 'manage_admins'));
        $this->assertFalse(AuthorizationService::can($reportViewer, 'manage_admins'));

        $this->assertTrue(AuthorizationService::can($admin, 'view_dashboard'));
        $this->assertTrue(AuthorizationService::can($admin, 'view_employees'));
        $this->assertTrue(AuthorizationService::can($admin, 'view_tests'));
        $this->assertTrue(AuthorizationService::can($admin, 'view_reports'));

        $this->assertTrue(AuthorizationService::can($supervisor, 'view_dashboard'));
        $this->assertTrue(AuthorizationService::can($supervisor, 'view_employees'));
        $this->assertTrue(AuthorizationService::can($supervisor, 'view_tests'));

        $this->assertTrue(AuthorizationService::can($reportViewer, 'view_dashboard'));
        $this->assertFalse(AuthorizationService::can($reportViewer, 'view_tests'));
    }

    /** 4. Super Admin can create secondary admin */
    public function testSuperAdminCanCreateSecondaryAdmin(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin']);
        $unique = bin2hex(random_bytes(4));
        $input = [
            'display_name' => 'Secondary Admin ' . $unique,
            'username' => 'test_mgr_sec_' . $unique,
            'email' => 'test_mgr_sec_' . $unique . '@test.local',
            'password' => 'SecureP@ss123!',
            'password_confirm' => 'SecureP@ss123!',
            'role' => 'admin',
            'is_active' => '1',
        ];

        $res = $this->userService->createAdmin($input, $superAdmin, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success'], $res['message'] ?? '');
        $this->assertGreaterThan(0, $res['admin_id']);

        $created = $this->userRepo->findById($res['admin_id']);
        $this->assertEquals('Secondary Admin ' . $unique, $created['display_name']);
        $this->assertEquals('test_mgr_sec_' . $unique, $created['username']);
        $this->assertEquals('admin', $created['role']);
    }

    /** 5 & 6. Username and Email case-insensitive uniqueness */
    public function testCaseInsensitiveUniquenessEnforced(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin']);
        $existing = $this->createTestAdmin(['username' => 'test_mgr_unique', 'email' => 'test_mgr_unique@test.local']);

        // Attempt duplicate username with mixed case
        $inputUser = [
            'display_name' => 'Dup User',
            'username' => 'TEST_MGR_UNIQUE',
            'email' => 'different@test.local',
            'password' => 'SecureP@ss123!',
            'password_confirm' => 'SecureP@ss123!',
            'role' => 'admin',
            'is_active' => '1',
        ];
        $resUser = $this->userService->createAdmin($inputUser, $superAdmin);
        $this->assertFalse($resUser['success']);
        $this->assertArrayHasKey('username', $resUser['errors']);

        // Attempt duplicate email with mixed case
        $inputEmail = [
            'display_name' => 'Dup Email',
            'username' => 'test_mgr_diff',
            'email' => 'TEST_MGR_UNIQUE@TEST.LOCAL',
            'password' => 'SecureP@ss123!',
            'password_confirm' => 'SecureP@ss123!',
            'role' => 'admin',
            'is_active' => '1',
        ];
        $resEmail = $this->userService->createAdmin($inputEmail, $superAdmin);
        $this->assertFalse($resEmail['success']);
        $this->assertArrayHasKey('email', $resEmail['errors']);
    }

    /** 7 & 15. Passwords stored using PASSWORD_DEFAULT & password_verify succeeds */
    public function testPasswordStorageAndGenericVerification(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin']);
        $unique = bin2hex(random_bytes(4));
        $rawPass = 'ComplexP@ssw0rd99';

        $res = $this->userService->createAdmin([
            'display_name' => 'Hash Test',
            'username' => 'test_mgr_hash_' . $unique,
            'email' => 'test_mgr_hash_' . $unique . '@test.local',
            'password' => $rawPass,
            'password_confirm' => $rawPass,
            'role' => 'admin',
            'is_active' => '1',
        ], $superAdmin);

        $this->assertTrue($res['success']);
        $stmt = self::$pdo->prepare("SELECT password_hash FROM admins WHERE id = :id");
        $stmt->execute([':id' => $res['admin_id']]);
        $storedHash = $stmt->fetchColumn();

        $this->assertNotEquals($rawPass, $storedHash);
        $this->assertTrue(password_verify($rawPass, $storedHash));

        $info = password_get_info($storedHash);
        $this->assertNotEmpty($info['algoName']);
    }

    /** 8. Password confirmation validation */
    public function testPasswordConfirmationMismatch(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin']);
        $res = $this->userService->createAdmin([
            'display_name' => 'Mismatch Test',
            'username' => 'test_mgr_mismatch',
            'email' => 'test_mgr_mismatch@test.local',
            'password' => 'SecureP@ss123!',
            'password_confirm' => 'DifferentP@ss123!',
            'role' => 'admin',
            'is_active' => '1',
        ], $superAdmin);

        $this->assertFalse($res['success']);
        $this->assertArrayHasKey('password_confirm', $res['errors']);
    }

    /** 9. Inactive admin cannot log in */
    public function testInactiveAdminCannotLogin(): void
    {
        $password = 'SecretP@ss123!';
        $inactive = $this->createTestAdmin([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => 0,
        ]);

        $res = $this->authService->authenticate($inactive['username'], $password, '127.0.0.1', 'PHPUnit');
        $this->assertFalse($res['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $res['code']);
    }

    /** 10, 11, 12, 13, 14, 15. Primary Admin & Acting Admin Protection Rules */
    public function testProtectionRulesEnforced(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin', 'is_primary' => 0]);
        $primaryAdmin = $this->createTestAdmin(['role' => 'super_admin', 'is_primary' => 1]);

        // Acting super admin cannot deactivate self
        $resDeactSelf = $this->userService->updateAdmin($superAdmin['id'], [
            'display_name' => $superAdmin['display_name'],
            'username' => $superAdmin['username'],
            'email' => $superAdmin['email'],
            'role' => 'super_admin',
            'is_active' => 0,
        ], $superAdmin);
        $this->assertFalse($resDeactSelf['success']);

        // Acting super admin cannot demote self
        $resDemoteSelf = $this->userService->updateAdmin($superAdmin['id'], [
            'display_name' => $superAdmin['display_name'],
            'username' => $superAdmin['username'],
            'email' => $superAdmin['email'],
            'role' => 'admin',
            'is_active' => 1,
        ], $superAdmin);
        $this->assertFalse($resDemoteSelf['success']);

        // Primary admin cannot be deactivated by another super admin
        $resDeactPrimary = $this->userService->updateAdmin($primaryAdmin['id'], [
            'display_name' => $primaryAdmin['display_name'],
            'username' => $primaryAdmin['username'],
            'email' => $primaryAdmin['email'],
            'role' => 'super_admin',
            'is_active' => 0,
        ], $superAdmin);
        $this->assertFalse($resDeactPrimary['success']);

        // Primary admin cannot be demoted by another super admin
        $resDemotePrimary = $this->userService->updateAdmin($primaryAdmin['id'], [
            'display_name' => $primaryAdmin['display_name'],
            'username' => $primaryAdmin['username'],
            'email' => $primaryAdmin['email'],
            'role' => 'admin',
            'is_active' => 1,
        ], $superAdmin);
        $this->assertFalse($resDemotePrimary['success']);
    }

    /** 19, 21, 22, 23. Auth Version Increment & Session Revocation */
    public function testAuthVersionIncrementAndSessionRevocation(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin']);
        $targetAdmin = $this->createTestAdmin(['role' => 'admin', 'auth_version' => 1]);

        // Target session with initial auth_version = 1
        $this->setupSession($targetAdmin);
        $validCheck = AdminAuthMiddleware::handle();
        $this->assertNotNull($validCheck);

        // Deactivate target account -> increments auth_version in DB
        $this->userRepo->incrementAuthVersion($targetAdmin['id']);

        // Session check with old auth_version = 1 should now be rejected
        $_SESSION['admin_auth']['auth_version'] = 1;
        $rejectedCheck = AdminAuthMiddleware::handle();
        $this->assertNull($rejectedCheck);
    }

    /** 20, 24, 25. Super Admin Self-Edit Rules */
    public function testSuperAdminSelfEditRules(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin', 'auth_version' => 1]);
        $this->setupSession($superAdmin);

        $newDisplayName = 'Updated Display Name ' . bin2hex(random_bytes(2));
        $resUpdate = $this->userService->updateAdmin($superAdmin['id'], [
            'display_name' => $newDisplayName,
            'username' => $superAdmin['username'],
            'email' => $superAdmin['email'],
            'role' => 'super_admin',
            'is_active' => 1,
        ], $superAdmin);

        $this->assertTrue($resUpdate['success']);
        $this->assertEquals($newDisplayName, $_SESSION['admin_auth']['display_name']);
    }

    /** 27. Concurrency-safe row locking method exists */
    public function testLockActiveSuperAdmins(): void
    {
        self::$pdo->beginTransaction();
        $locked = $this->userRepo->lockActiveSuperAdmins();
        self::$pdo->commit();

        $this->assertIsArray($locked);
        $this->assertGreaterThanOrEqual(1, count($locked));
    }

    /** 28. Audit logging does not leak passwords or hashes */
    public function testAuditLogSecurity(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin']);
        $unique = bin2hex(random_bytes(4));
        $rawPassword = 'SuperSecretP@ssword123!';

        $res = $this->userService->createAdmin([
            'display_name' => 'Audit Test',
            'username' => 'test_mgr_audit_' . $unique,
            'email' => 'test_mgr_audit_' . $unique . '@test.local',
            'password' => $rawPassword,
            'password_confirm' => $rawPassword,
            'role' => 'admin',
            'is_active' => '1',
        ], $superAdmin);

        $stmt = self::$pdo->prepare("SELECT old_values, new_values FROM audit_logs WHERE entity_id = :id ORDER BY id DESC LIMIT 1");
        $stmt->execute([':id' => $res['admin_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $oldStr = (string)$row['old_values'];
        $newStr = (string)$row['new_values'];

        $this->assertStringNotContainsString($rawPassword, $oldStr);
        $this->assertStringNotContainsString($rawPassword, $newStr);
        $this->assertStringNotContainsString('password', $oldStr);
        $this->assertStringNotContainsString('password', $newStr);
    }

    /** 29. Legacy session missing auth_version is strictly rejected */
    public function testLegacySessionWithoutAuthVersionIsRejected(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin', 'auth_version' => 2]);
        $this->setupSession($superAdmin);

        // Remove auth_version from session to simulate pre-migration legacy session
        unset($_SESSION['admin_auth']['auth_version']);

        $res = AdminAuthMiddleware::handle();
        $this->assertNull($res);
        $this->assertArrayNotHasKey('admin_auth', $_SESSION);
    }

    /** 30. Secondary admin full authentication and role authorization lifecycle */
    public function testSecondaryAdminFullAuthenticationLifecycle(): void
    {
        $superAdmin = $this->createTestAdmin(['role' => 'super_admin']);
        $unique = bin2hex(random_bytes(4));
        $rawPassword = 'SecondaryP@ss123!';
        $username = 'test_mgr_sec_auth_' . $unique;
        $email = 'test_mgr_sec_auth_' . $unique . '@test.local';

        // 1. Create secondary admin
        $createRes = $this->userService->createAdmin([
            'display_name' => 'Secondary Admin Auth Test',
            'username' => $username,
            'email' => $email,
            'password' => $rawPassword,
            'password_confirm' => $rawPassword,
            'role' => 'admin',
            'is_active' => '1',
        ], $superAdmin);

        $this->assertTrue($createRes['success']);
        $secAdminId = $createRes['admin_id'];

        // 2. Inspect stored password_hash
        $stmt = self::$pdo->prepare("SELECT password_hash, role, is_active FROM admins WHERE id = :id");
        $stmt->execute([':id' => $secAdminId]);
        $secDbRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($secDbRow['password_hash']);
        $this->assertTrue(password_verify($rawPassword, $secDbRow['password_hash']));

        // 3. Authenticate using username
        $authUsernameRes = $this->authService->authenticate($username, $rawPassword, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($authUsernameRes['success']);
        $this->assertEquals('admin', $_SESSION['admin_auth']['role']);
        $this->assertEquals(1, $_SESSION['admin_auth']['auth_version']);

        // 4. Authenticate using email
        $authEmailRes = $this->authService->authenticate($email, $rawPassword, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($authEmailRes['success']);

        // 5. Role 'admin' is denied access to manage_admins (/admin/admin-users)
        $middlewareResult = AdminAuthMiddleware::handle('manage_admins');
        $this->assertNull($middlewareResult);

        // 6. Role 'admin' is granted access to view_dashboard
        $dashboardResult = AdminAuthMiddleware::handle('view_dashboard');
        $this->assertNotNull($dashboardResult);
        $this->assertEquals($secAdminId, $dashboardResult['id']);
    }
}
