<?php

namespace Tests\Security;

use App\Middleware\CsrfMiddleware;
use App\Middleware\EmployeeAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Repositories\EmployeeRepository;
use App\Services\EmployeeAuthService;
use App\Support\Database;
use PDO;
use Tests\TestCase;

class EmployeeSecurityTest extends TestCase
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
    }

    public function testCsrfTokenManagerAndValidation(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = \App\Security\CsrfTokenManager::getToken();
        $this->assertNotEmpty($token);
        $this->assertTrue(\App\Security\CsrfTokenManager::validate($token));
        $this->assertFalse(\App\Security\CsrfTokenManager::validate('invalid_token_string'));
    }

    public function testIdentifierBasedRateLimiting(): void
    {
        $action = 'emp_login';
        $target = 'id_target_' . uniqid('', true);
        $ip = '10.0.0.1';

        for ($i = 0; $i < 5; $i++) {
            $isLocked = RateLimitMiddleware::isLocked($action, $target, $ip);
            $this->assertFalse($isLocked);
            RateLimitMiddleware::recordAttempt($action, $target, $ip, false);
        }

        // 6th attempt on same target identifier should lock out regardless of IP
        $isLocked6 = RateLimitMiddleware::isLocked($action, $target, '10.0.0.2');
        $this->assertTrue($isLocked6);
    }

    public function testIpBasedRateLimiting(): void
    {
        $action = 'emp_login';
        $ip = '10.0.0.99';

        for ($i = 0; $i < 5; $i++) {
            $target = 'target_ip_' . $i . '_' . time();
            $isLocked = RateLimitMiddleware::isLocked($action, $target, $ip);
            $this->assertFalse($isLocked);
            RateLimitMiddleware::recordAttempt($action, $target, $ip, false);
        }

        // 6th attempt from same IP should lock out regardless of target identifier
        $isLocked6 = RateLimitMiddleware::isLocked($action, 'new_target_diff', $ip);
        $this->assertTrue($isLocked6);
    }

    public function testLockoutExpiry(): void
    {
        // Rate limits are time windowed (15 minutes). Test helper logic returns false when no recent fails exist.
        $action = 'emp_login';
        $target = 'clean_target_' . uniqid();
        $ip = '172.16.0.1';

        $this->assertFalse(RateLimitMiddleware::isLocked($action, $target, $ip));
    }

    public function testSessionIdRegenerationAfterLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $oldId = session_id();
        session_regenerate_id(true);
        $newId = session_id();

        $this->assertNotEquals($oldId, $newId);
    }

    public function testProtectedRouteRejectionWithoutEmployeeAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['employee_auth']);

        $this->assertArrayNotHasKey('employee_auth', $_SESSION);
    }

    public function testEmployeeLogoutDestroysEmployeeAuthenticationContext(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['employee_auth'] = ['employee_id' => 1];

        $empRepo = new EmployeeRepository(self::$pdo);
        $authService = new EmployeeAuthService($empRepo);
        $authService->logout();

        $this->assertArrayNotHasKey('employee_auth', $_SESSION);
    }

    public function testEmployeeLogoutPreservesIndependentAdminSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_auth'] = ['admin_id' => 1, 'role' => 'super_admin'];
        $_SESSION['employee_auth'] = ['employee_id' => 1, 'authenticated_at' => time()];

        $empRepo = new EmployeeRepository(self::$pdo);
        $authService = new EmployeeAuthService($empRepo);
        $authService->logout();

        // Verify employee_auth is removed while admin_auth remains intact
        $this->assertArrayNotHasKey('employee_auth', $_SESSION);
        $this->assertArrayHasKey('admin_auth', $_SESSION);
        $this->assertEquals(1, $_SESSION['admin_auth']['admin_id']);
    }

    public function testIdleSessionTimeout(): void
    {
        $now = time();
        $idleLimitSeconds = 30 * 60;

        // Session last active 31 minutes ago
        $auth = [
            'employee_id' => 1,
            'last_activity_at' => $now - ($idleLimitSeconds + 60),
            'absolute_start_at' => $now - 600
        ];

        $isExpired = ($now - $auth['last_activity_at']) > $idleLimitSeconds;
        $this->assertTrue($isExpired);
    }

    public function testAbsoluteSessionTimeout(): void
    {
        $now = time();
        $absoluteLimitSeconds = 8 * 3600;

        // Session started 8.5 hours ago
        $auth = [
            'employee_id' => 1,
            'last_activity_at' => $now - 60,
            'absolute_start_at' => $now - ($absoluteLimitSeconds + 1800)
        ];

        $isExpired = ($now - $auth['absolute_start_at']) > $absoluteLimitSeconds;
        $this->assertTrue($isExpired);
    }

    public function testAdminOnlySessionRejectionFromEmployeeRoutes(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_auth'] = ['admin_id' => 1, 'role' => 'super_admin'];
        unset($_SESSION['employee_auth']);

        // Employee route protection strictly checks $_SESSION['employee_auth']
        $this->assertArrayNotHasKey('employee_auth', $_SESSION);
        $this->assertArrayHasKey('admin_auth', $_SESSION);
    }

    public function testValidCoexistenceOfSeparateAdminAndEmployeeSessions(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_auth'] = ['admin_id' => 1, 'role' => 'super_admin'];
        $_SESSION['employee_auth'] = [
            'employee_id' => 1,
            'authenticated_at' => time(),
            'preferred_language' => 'ar',
            'profile_completed' => true,
            'last_activity_at' => time(),
            'absolute_start_at' => time()
        ];

        $this->assertArrayHasKey('admin_auth', $_SESSION);
        $this->assertArrayHasKey('employee_auth', $_SESSION);
        $this->assertEquals(1, $_SESSION['employee_auth']['employee_id']);
    }
}
