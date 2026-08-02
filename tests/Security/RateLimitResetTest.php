<?php

namespace Tests\Security;

use App\Middleware\RateLimitMiddleware;
use App\Repositories\AdminRepository;
use App\Repositories\EmployeeRepository;
use App\Security\HashManager;
use App\Services\AdminAuthService;
use App\Services\EmployeeAuthService;
use App\Support\Database;
use PDO;
use Tests\TestCase;

class RateLimitResetTest extends TestCase
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

        // Ensure baseline tables exist & migration 002 is applied
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = self::$pdo->query("SHOW TABLES;")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('admins', $tables, true) || !in_array('rate_limits', $tables, true)) {
            $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
            self::$pdo->exec($sql1);
        }

        $auditCols = self::$pdo->query("DESCRIBE audit_logs;")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('actor_type', $auditCols, true) || !in_array('employees', $tables, true)) {
            $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
            self::$pdo->exec($sql2);
        }
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        self::cleanupFixtures();

        // Seed test admin (ID 50)
        $hashCost12 = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmtAdmin = self::$pdo->prepare("
            INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at)
            VALUES (50, 'Reset Marker Admin', 'reset_marker_admin@test.local', 'reset_marker_admin', :hash, 'admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $stmtAdmin->execute([':hash' => $hashCost12]);

        // Seed reference data & employee for EmployeeAuthService tests
        self::$pdo->exec("INSERT IGNORE INTO departments (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (50, 'قسم الاختبار', 'Test Dept', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT IGNORE INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at) VALUES (50, 50, 'شعبة الاختبار', 'Test Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT IGNORE INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (50, 'مختبر', 'Tester', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");

        $empIdHash = HashManager::hashNationalId('1099999999');
        $stmtEmp = self::$pdo->prepare("
            INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, preferred_language, is_active, profile_completed_at, created_at, updated_at)
            VALUES (50, 'مختبر معدل', :hash, 'cipher50', :iv, :tag, '9999', 'EMP050', 'emp50@test.com', '+966599999999', 50, 50, 50, 'ar', 1, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $stmtEmp->execute([
            ':hash' => $empIdHash,
            ':iv' => random_bytes(12),
            ':tag' => random_bytes(16),
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$pdo)) {
            self::cleanupFixtures();
        }
    }

    private static function cleanupFixtures(): void
    {
        $auditCols = self::$pdo->query("DESCRIBE audit_logs;")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('employee_id', $auditCols, true)) {
            self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id = 50 OR employee_id = 50;");
        } else {
            self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id = 50;");
        }
        self::$pdo->exec("DELETE FROM security_events;");
        self::$pdo->exec("DELETE FROM rate_limits;");
        self::$pdo->exec("DELETE FROM admins WHERE id = 50;");
        self::$pdo->exec("DELETE FROM employees WHERE id = 50;");
        self::$pdo->exec("DELETE FROM job_titles WHERE id = 50;");
        self::$pdo->exec("DELETE FROM sections WHERE id = 50;");
        self::$pdo->exec("DELETE FROM departments WHERE id = 50;");
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::$pdo->exec("DELETE FROM rate_limits;");
        $auditCols = self::$pdo->query("DESCRIBE audit_logs;")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('employee_id', $auditCols, true)) {
            self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id = 50 OR employee_id = 50;");
        } else {
            self::$pdo->exec("DELETE FROM audit_logs WHERE admin_id = 50;");
        }
        self::$pdo->exec("DELETE FROM security_events;");
        self::$pdo->exec("DELETE FROM security_events;");

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['admin_auth'], $_SESSION['employee_auth']);
        }
    }

    /**
     * Requirement 4.A: Identifier reset via success marker
     * - Record 4 failed attempts for identifier A.
     * - Record one successful attempt for identifier A.
     * - Record one new failed attempt for identifier A.
     * - Confirm identifier A is open when checked from a different clean IP.
     */
    public function testIdentifierResetViaSuccessMarker(): void
    {
        $action = 'admin_login';
        $targetA = 'identifier_a_' . bin2hex(random_bytes(4));
        $ip = '198.51.100.10';
        $cleanIp = '198.51.100.99';

        // 4 failed attempts
        for ($i = 0; $i < 4; $i++) {
            RateLimitMiddleware::recordAttempt($action, $targetA, $ip, false);
        }

        // Record 1 successful attempt (acts as reset marker)
        RateLimitMiddleware::recordAttempt($action, $targetA, $ip, true);

        // Record 1 new failed attempt
        RateLimitMiddleware::recordAttempt($action, $targetA, $ip, false);

        // Confirm identifier A is open from a clean IP (only 1 post-success failure)
        $this->assertFalse(
            RateLimitMiddleware::isLocked($action, $targetA, $cleanIp),
            'Identifier A should remain open when checked from clean IP after success marker + 1 failure.'
        );
    }

    /**
     * Requirement 4.B: IP history preservation
     * - Record 4 failures for identifier A from one IP.
     * - Record success for identifier A from the same IP.
     * - Record 1 failure for identifier B from the same IP.
     * - Confirm the IP is locked for a brand-new identifier.
     * - Confirm identifier A itself is not locked when checked from another clean IP.
     */
    public function testIpHistoryPreservation(): void
    {
        $action = 'admin_login';
        $ip = '198.51.100.20';
        $cleanIp = '198.51.100.99';

        $targetA = 'target_a_' . bin2hex(random_bytes(4));
        $targetB = 'target_b_' . bin2hex(random_bytes(4));
        $brandNewTarget = 'target_new_' . bin2hex(random_bytes(4));

        // 4 failures for target A from IP
        for ($i = 0; $i < 4; $i++) {
            RateLimitMiddleware::recordAttempt($action, $targetA, $ip, false);
        }

        // Success for target A from same IP (records is_success = 1 without deleting failed rows)
        RateLimitMiddleware::recordAttempt($action, $targetA, $ip, true);

        // 1 failure for target B from same IP
        RateLimitMiddleware::recordAttempt($action, $targetB, $ip, false);

        // Confirm the IP is locked for a brand-new identifier (5 failures total on IP)
        $this->assertTrue(
            RateLimitMiddleware::isLocked($action, $brandNewTarget, $ip),
            'IP should be locked because all 5 failures remain in failure history.'
        );

        // Confirm identifier A itself is NOT locked when checked from clean IP
        $this->assertFalse(
            RateLimitMiddleware::isLocked($action, $targetA, $cleanIp),
            'Identifier A should remain open on clean IP as previous failures occurred prior to success marker.'
        );
    }

    /**
     * Requirement 4.C: Action isolation
     * - A success marker for admin_login must not reset emp_login failures.
     */
    public function testActionIsolationForSuccessMarkers(): void
    {
        $target = 'shared_target_' . bin2hex(random_bytes(4));
        $failureIp = '198.51.100.80';
        $cleanIp = '198.51.100.99';

        // 5 failures for emp_login from failureIp
        for ($i = 0; $i < 5; $i++) {
            RateLimitMiddleware::recordAttempt('emp_login', $target, $failureIp, false);
        }

        // Success marker for admin_login
        RateLimitMiddleware::recordAttempt('admin_login', $target, $failureIp, true);

        // emp_login must remain locked for target when checked from a clean IP
        $this->assertTrue(
            RateLimitMiddleware::isLocked('emp_login', $target, $cleanIp),
            'emp_login failures must remain locked despite admin_login success marker.'
        );
    }

    /**
     * Requirement 4.D: Identifier isolation
     * - A success marker for identifier B must not reset identifier A.
     */
    public function testIdentifierIsolationForSuccessMarkers(): void
    {
        $targetA = 'target_iso_a_' . bin2hex(random_bytes(4));
        $targetB = 'target_iso_b_' . bin2hex(random_bytes(4));
        $failureIp = '198.51.100.81';
        $cleanIp = '198.51.100.99';

        // 5 failures for identifier A from failureIp
        for ($i = 0; $i < 5; $i++) {
            RateLimitMiddleware::recordAttempt('admin_login', $targetA, $failureIp, false);
        }

        // Success marker for identifier B
        RateLimitMiddleware::recordAttempt('admin_login', $targetB, $failureIp, true);

        // Identifier A must remain locked when checked from clean IP
        $this->assertTrue(
            RateLimitMiddleware::isLocked('admin_login', $targetA, $cleanIp),
            'Identifier A must remain locked when Identifier B records a success marker.'
        );
    }

    /**
     * Requirement 4.E: Failed authentication behavior
     * - A failed authentication must not create a reset marker.
     * - Previous failure counts must remain and the new failure must be added.
     */
    public function testFailedAuthenticationDoesNotCreateResetMarker(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $adminService = new AdminAuthService($adminRepo, self::$pdo);

        $target = 'reset_marker_admin@test.local';
        $ip = '198.51.100.30';

        // 4 failed attempts recorded directly
        for ($i = 0; $i < 4; $i++) {
            RateLimitMiddleware::recordAttempt('admin_login', $target, $ip, false);
        }

        // 5th failed attempt via AdminAuthService
        $res = $adminService->authenticate($target, 'WrongPassword!', $ip, 'PHPUnit-Test');
        $this->assertFalse($res['success']);

        // Confirm identifier is now locked (4 + 1 = 5 failures)
        $this->assertTrue(
            RateLimitMiddleware::isLocked('admin_login', $target, '198.51.100.99'),
            'Failed authentication must add to failure count and lock out identifier.'
        );
    }

    /**
     * Requirement 4.F: Service integration
     * - Successful admin authentication creates a success marker and resets only its identifier bucket.
     * - Successful employee authentication creates a success marker and resets only its identifier bucket.
     * - IP-based failure history remains intact after both successful authentications.
     */
    public function testServiceIntegrationWithSuccessMarkers(): void
    {
        $adminRepo = new AdminRepository(self::$pdo);
        $adminService = new AdminAuthService($adminRepo, self::$pdo);

        $empRepo = new EmployeeRepository(self::$pdo);
        $empService = new EmployeeAuthService($empRepo);

        $adminIdentifier = 'reset_marker_admin@test.local';
        $empNationalId = '1099999999';

        $ipAdmin = '198.51.100.40';
        $ipEmp = '198.51.100.41';
        $cleanIp = '198.51.100.99';

        // 1. Admin Integration: Record 4 failures, then login successfully
        for ($i = 0; $i < 4; $i++) {
            RateLimitMiddleware::recordAttempt('admin_login', $adminIdentifier, $ipAdmin, false);
        }
        $adminRes = $adminService->authenticate($adminIdentifier, 'Password123!', $ipAdmin, 'PHPUnit-Test');
        $this->assertTrue($adminRes['success']);

        // Record 1 new failure for admin identifier
        RateLimitMiddleware::recordAttempt('admin_login', $adminIdentifier, $ipAdmin, false);
        $this->assertFalse(
            RateLimitMiddleware::isLocked('admin_login', $adminIdentifier, $cleanIp),
            'Admin identifier should remain open on clean IP (1 post-success failure).'
        );

        // Confirm the 4 failure rows still exist in database for IP history
        $hashAdmin = HashManager::hashRateLimitIdentifier($adminIdentifier);
        $stmtAdmin = self::$pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier_hash = :hash AND action_key = 'admin_login' AND is_success = 0");
        $stmtAdmin->execute([':hash' => $hashAdmin]);
        $this->assertEquals(5, (int)$stmtAdmin->fetchColumn(), 'All 5 failure rows (4 before success + 1 after) must remain in database.');

        // 2. Employee Integration: Record 4 failures, then login successfully
        for ($i = 0; $i < 4; $i++) {
            RateLimitMiddleware::recordAttempt('emp_login', $empNationalId, $ipEmp, false);
        }
        $empRes = $empService->authenticate($empNationalId, $ipEmp);
        $this->assertTrue($empRes['success']);

        // Record 1 new failure for employee identifier
        RateLimitMiddleware::recordAttempt('emp_login', $empNationalId, $ipEmp, false);
        $this->assertFalse(
            RateLimitMiddleware::isLocked('emp_login', $empNationalId, $cleanIp),
            'Employee identifier should remain open on clean IP (1 post-success failure).'
        );

        // Confirm the 4 failure rows still exist in database for IP history
        $hashEmp = HashManager::hashRateLimitIdentifier($empNationalId);
        $stmtEmp = self::$pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier_hash = :hash AND action_key = 'emp_login' AND is_success = 0");
        $stmtEmp->execute([':hash' => $hashEmp]);
        $this->assertEquals(5, (int)$stmtEmp->fetchColumn(), 'All 5 failure rows (4 before success + 1 after) must remain in database.');

        // 3. Confirm IP history remains intact on ipAdmin and ipEmp (4 failures + 1 failure = 5 failures on each IP)
        $this->assertTrue(
            RateLimitMiddleware::isLocked('admin_login', 'brand_new_admin', $ipAdmin),
            'IP history on ipAdmin must remain intact (5 failures).'
        );

        $this->assertTrue(
            RateLimitMiddleware::isLocked('emp_login', 'brand_new_employee', $ipEmp),
            'IP history on ipEmp must remain intact (5 failures).'
        );
    }
}
