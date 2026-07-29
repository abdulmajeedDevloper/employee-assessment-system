<?php

namespace Tests;

use PDO;
use PDOException;

class NegativeDatabaseConstraintsTest extends TestCase
{
    private static ?PDO $testPdo = null;

    public static function setUpBeforeClass(): void
    {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $testDb = 'employee_assessment_test';

        $dsn = "mysql:host={$host};port={$port};dbname={$testDb};charset=utf8mb4";
        self::$testPdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Reset test database schema
        self::$testPdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $stmt = self::$testPdo->query("SHOW TABLES;");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            self::$testPdo->exec("DROP TABLE IF EXISTS `{$t}`;");
        }
        self::$testPdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        $schemaSql = file_get_contents(__DIR__ . '/../database/migrations/001_initial_schema.up.sql');
        self::$testPdo->exec($schemaSql);

        // Seed base references
        self::$testPdo->exec("INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at) VALUES (1, 'Admin', 'admin@test.local', 'admin', 'hash', 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$testPdo->exec("INSERT INTO departments (id, name_ar, name_en, created_at, updated_at) VALUES (1, 'التقنية', 'Engineering', UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$testPdo->exec("INSERT INTO sections (id, department_id, name_ar, name_en, created_at, updated_at) VALUES (1, 1, 'التطوير', 'Development', UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$testPdo->exec("INSERT INTO job_titles (id, name_ar, name_en, created_at, updated_at) VALUES (1, 'مطور', 'Developer', UTC_TIMESTAMP(), UTC_TIMESTAMP());");

        $hash1 = hex2bin(hash('sha256', '1000000001'));
        $hash2 = hex2bin(hash('sha256', '1000000002'));

        $stmtEmp = self::$testPdo->prepare("INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmtEmp->execute([1, 'Emp One', $hash1, 'cipher1', random_bytes(12), random_bytes(16), '0001', 'EMP001', 'emp1@test.com', '123456789', 1, 1, 1]);
        $stmtEmp->execute([2, 'Emp Two', $hash2, 'cipher2', random_bytes(12), random_bytes(16), '0002', 'EMP002', 'emp2@test.com', '987654321', 1, 1, 1]);

        self::$testPdo->exec("INSERT INTO tests (id, code, name_ar, name_en, duration_minutes, question_count, passing_percentage, created_at, updated_at) VALUES (1, 'TST-A', 'اختبار أ', 'Test A', 60, 10, 70.00, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$testPdo->exec("INSERT INTO tests (id, code, name_ar, name_en, duration_minutes, question_count, passing_percentage, created_at, updated_at) VALUES (2, 'TST-B', 'اختبار ب', 'Test B', 60, 10, 70.00, UTC_TIMESTAMP(), UTC_TIMESTAMP());");

        self::$testPdo->exec("INSERT INTO question_categories (id, test_id, name_ar, name_en, created_at) VALUES (1, 1, 'فئة أ1', 'Category A1', UTC_TIMESTAMP());");
        self::$testPdo->exec("INSERT INTO question_categories (id, test_id, name_ar, name_en, created_at) VALUES (2, 2, 'فئة ب1', 'Category B1', UTC_TIMESTAMP());");

        self::$testPdo->exec("INSERT INTO questions (id, test_id, category_id, text_ar, text_en, created_by, created_at, updated_at) VALUES (1, 1, 1, 'سؤال 1', 'Question 1', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");

        self::$testPdo->exec("INSERT INTO test_assignments (id, employee_id, test_id, max_attempts, assigned_by, assigned_at) VALUES (1, 1, 1, 3, 1, UTC_TIMESTAMP());");
        self::$testPdo->exec("INSERT INTO test_assignments (id, employee_id, test_id, max_attempts, assigned_by, assigned_at) VALUES (2, 2, 2, 3, 1, UTC_TIMESTAMP());");

        self::$testPdo->exec("INSERT INTO test_attempts (id, employee_id, test_id, assignment_id, attempt_number, started_at, expires_at, duration_seconds, ip_address, user_agent, created_at, updated_at) VALUES (1, 1, 1, 1, 1, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), 3600, '127.0.0.1', 'UA', UTC_TIMESTAMP(), UTC_TIMESTAMP());");
    }

    public function testMismatchedAssignmentEmployeeIdentityRejection(): void
    {
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO test_attempts (employee_id, test_id, assignment_id, attempt_number, started_at, expires_at, duration_seconds, ip_address, user_agent, created_at, updated_at) VALUES (2, 1, 1, 2, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), 3600, '127.0.0.1', 'UA', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testMismatchedQuestionCategoryTestIdentityRejection(): void
    {
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO questions (test_id, category_id, text_ar, text_en, created_by, created_at, updated_at) VALUES (1, 2, 'خطأ', 'Error', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testMismatchedSessionEmployeeAttemptOwnershipRejection(): void
    {
        $this->expectException(PDOException::class);
        $token = hash('sha256', 'mismatch_token');
        $stmt = self::$testPdo->prepare("INSERT INTO test_sessions (employee_id, attempt_id, session_token_hash, ip_address, started_at, last_activity_at, status) VALUES (2, 1, '{$token}', '127.0.0.1', UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'ACTIVE')");
        $stmt->execute();
    }

    public function testDuplicateActiveEmployeeSessionRejection(): void
    {
        $token1 = hash('sha256', 'token_active_1');
        $token2 = hash('sha256', 'token_active_2');
        self::$testPdo->exec("INSERT INTO test_sessions (employee_id, attempt_id, session_token_hash, ip_address, started_at, last_activity_at, status) VALUES (1, 1, '{$token1}', '127.0.0.1', UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'ACTIVE')");

        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO test_sessions (employee_id, attempt_id, session_token_hash, ip_address, started_at, last_activity_at, status) VALUES (1, 1, '{$token2}', '127.0.0.1', UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'ACTIVE')");
        $stmt->execute();
    }

    public function testDuplicatePendingRetakeAuthorizationRejection(): void
    {
        self::$testPdo->exec("INSERT INTO retake_authorizations (assignment_id, previous_attempt_id, authorized_by, reason, authorized_at) VALUES (1, 1, 1, 'Retake 1', UTC_TIMESTAMP())");

        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO retake_authorizations (assignment_id, previous_attempt_id, authorized_by, reason, authorized_at) VALUES (1, 1, 1, 'Retake 2', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testInvalidQuestionMediaOwnerCombinationRejection(): void
    {
        self::$testPdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, display_order) VALUES (1, 1, 'opt_a', 'خيار أ', 'Opt A', 1)");

        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO question_media (media_type, question_id, option_id, public_token, storage_path, original_filename, mime_type, file_extension, file_size_bytes, created_by, created_at) VALUES ('QUESTION_IMAGE', 1, 1, 'token_both', '/path/img.jpg', 'img.jpg', 'image/jpeg', 'jpg', 1024, 1, UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testDuplicateOptionDisplayOrderRejection(): void
    {
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO question_options (question_id, option_key, text_ar, text_en, display_order) VALUES (1, 'opt_b', 'خيار ب', 'Opt B', 1)");
        $stmt->execute();
    }

    public function testDuplicateAttemptNumberRejection(): void
    {
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO test_attempts (employee_id, test_id, assignment_id, attempt_number, started_at, expires_at, duration_seconds, ip_address, user_agent, created_at, updated_at) VALUES (1, 1, 1, 1, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), 3600, '127.0.0.1', 'UA', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testDuplicateAttemptQuestionRejection(): void
    {
        self::$testPdo->exec("INSERT INTO attempt_questions (attempt_id, original_question_id, question_snapshot, options_snapshot, correct_option_key_snapshot, score_snapshot, display_order, created_at) VALUES (1, 1, '{}', '[]', 'opt_a', 1.00, 1, UTC_TIMESTAMP())");

        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO attempt_questions (attempt_id, original_question_id, question_snapshot, options_snapshot, correct_option_key_snapshot, score_snapshot, display_order, created_at) VALUES (1, 1, '{}', '[]', 'opt_a', 1.00, 2, UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testPercentageOutsideCheckRangeRejection(): void
    {
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO tests (code, name_ar, name_en, duration_minutes, question_count, passing_percentage, created_at, updated_at) VALUES ('INV-PCT', 'خطأ', 'Error', 60, 10, 150.00, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testRetakePreviousAttemptDifferentAssignmentRejection(): void
    {
        // Attempt id 1 belongs to assignment_id 1. Attempting retake authorization for assignment_id 2 referencing previous_attempt_id 1.
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO retake_authorizations (assignment_id, previous_attempt_id, authorized_by, reason, authorized_at) VALUES (2, 1, 1, 'Invalid retake attempt', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testRetakeConsumedAttemptDifferentAssignmentRejection(): void
    {
        // Attempt id 1 belongs to assignment_id 1. Attempt id 2 created for assignment_id 2.
        self::$testPdo->exec("INSERT INTO test_attempts (id, employee_id, test_id, assignment_id, attempt_number, started_at, expires_at, duration_seconds, ip_address, user_agent, created_at, updated_at) VALUES (2, 2, 2, 2, 1, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), 3600, '127.0.0.1', 'UA', UTC_TIMESTAMP(), UTC_TIMESTAMP());");

        // Retake authorization for assignment_id 2, previous_attempt_id 2, but consumed_by_attempt_id 1 (which belongs to assignment_id 1)
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO retake_authorizations (assignment_id, previous_attempt_id, consumed_by_attempt_id, consumed_at, authorized_by, reason, authorized_at) VALUES (2, 2, 1, UTC_TIMESTAMP(), 1, 'Invalid consumed attempt assignment', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testAuditLogsEmployeeActorWithAdminIdPopulatedRejection(): void
    {
        // EMPLOYEE actor with admin_id populated -> violates chk_audit_actor
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('EMPLOYEE', 1, 1, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testAuditLogsAdminActorWithEmployeeIdPopulatedRejection(): void
    {
        // ADMIN actor with employee_id populated -> violates chk_audit_actor
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('ADMIN', 1, 1, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testAuditLogsSystemActorWithAdminIdPopulatedRejection(): void
    {
        // SYSTEM actor with admin_id populated -> violates chk_audit_actor
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('SYSTEM', 1, NULL, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testAuditLogsSystemActorWithEmployeeIdPopulatedRejection(): void
    {
        // SYSTEM actor with employee_id populated -> violates chk_audit_actor
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('SYSTEM', NULL, 1, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testAuditLogsBothAdminIdAndEmployeeIdPopulatedRejection(): void
    {
        // Both admin_id and employee_id populated -> violates chk_audit_actor
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('EMPLOYEE', 1, 1, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testAuditLogsAdminActorWithoutAdminIdRejection(): void
    {
        // ADMIN actor without admin_id -> violates chk_audit_actor
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('ADMIN', NULL, NULL, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())");
        $stmt->execute();
    }

    public function testAuditLogsEmployeeActorWithoutEmployeeIdRejection(): void
    {
        // EMPLOYEE actor without employee_id -> violates chk_audit_actor
        $this->expectException(PDOException::class);
        $stmt = self::$testPdo->prepare("INSERT INTO audit_logs (actor_type, admin_id, employee_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES ('EMPLOYEE', NULL, NULL, 'TEST', 'test', 1, '127.0.0.1', 'UA', UTC_TIMESTAMP())");
        $stmt->execute();
    }
}
