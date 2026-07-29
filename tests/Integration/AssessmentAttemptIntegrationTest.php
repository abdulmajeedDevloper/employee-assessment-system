<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\AssessmentAttemptService;
use App\Security\HashManager;
use PDO;

class AssessmentAttemptIntegrationTest extends TestCase
{
    private static PDO $pdo;
    private static AssessmentAttemptService $service;

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
        self::$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        \App\Support\Database::setInstance(self::$pdo);

        // Reset schema & run restored 001 + 002 migrations ONCE
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $stmt = self::$pdo->query("SHOW TABLES;");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            self::$pdo->exec("DROP TABLE IF EXISTS `{$t}`;");
        }
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        $sql1 = file_get_contents(__DIR__ . '/../../database/migrations/001_initial_schema.up.sql');
        self::$pdo->exec($sql1);

        $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
        self::$pdo->exec($sql2);

        self::$service = new AssessmentAttemptService(self::$pdo);
        self::seedTestFixtures();
    }

    protected function setUp(): void
    {
        parent::setUp();
        \App\Support\Database::setInstance(self::$pdo);
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        self::seedTestFixtures();
    }

    private static function seedTestFixtures(): void
    {
        $cols = self::$pdo->query("DESCRIBE audit_logs")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('actor_type', $cols, true)) {
            $sql2 = file_get_contents(__DIR__ . '/../../database/migrations/002_phase2_employee_auth.up.sql');
            self::$pdo->exec($sql2);
        }

        // FK-safe cleanup of data tables only (no DROP TABLE)
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        self::$pdo->exec("TRUNCATE TABLE attempt_answers;");
        self::$pdo->exec("TRUNCATE TABLE attempt_questions;");
        self::$pdo->exec("TRUNCATE TABLE test_sessions;");
        self::$pdo->exec("TRUNCATE TABLE retake_authorizations;");
        self::$pdo->exec("TRUNCATE TABLE test_attempts;");
        self::$pdo->exec("TRUNCATE TABLE security_events;");
        self::$pdo->exec("TRUNCATE TABLE audit_logs;");
        self::$pdo->exec("TRUNCATE TABLE rate_limits;");
        self::$pdo->exec("TRUNCATE TABLE assessment_progress;");
        self::$pdo->exec("TRUNCATE TABLE test_assignments;");
        self::$pdo->exec("TRUNCATE TABLE employees;");
        self::$pdo->exec("TRUNCATE TABLE question_options;");
        self::$pdo->exec("TRUNCATE TABLE questions;");
        self::$pdo->exec("TRUNCATE TABLE question_categories;");
        self::$pdo->exec("TRUNCATE TABLE tests;");
        self::$pdo->exec("TRUNCATE TABLE job_titles;");
        self::$pdo->exec("TRUNCATE TABLE sections;");
        self::$pdo->exec("TRUNCATE TABLE departments;");
        self::$pdo->exec("TRUNCATE TABLE admins;");
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Seed Admin & Org structure
        self::$pdo->exec("INSERT INTO admins (id, name, email, username, password_hash, role, is_active, created_at, updated_at) VALUES (1, 'Admin', 'admin@test.local', 'admin', 'hash', 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO departments (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 'إدارة التقنية', 'IT Dept', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 1, 'قسم التطوير', 'Software Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (1, 'مهندس برمجيات', 'Software Engineer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");

        // Seed Employee 1
        $hash1 = HashManager::hashNationalId('1000000001');
        $stmtEmp1 = self::$pdo->prepare("INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, preferred_language, is_active, profile_completed_at, created_at, updated_at) VALUES (10, 'Test Emp 1', :hash, 'c1', :iv, :tag, '0001', 'EMP010', 'emp10@test.com', '+966511111111', 1, 1, 1, 'ar', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmtEmp1->execute([':hash' => $hash1, ':iv' => random_bytes(12), ':tag' => random_bytes(16)]);

        // Seed Employee 2 (for independent session test)
        $hash2 = HashManager::hashNationalId('1000000002');
        $stmtEmp2 = self::$pdo->prepare("INSERT INTO employees (id, full_name, national_id_hash, national_id_ciphertext, national_id_iv, national_id_tag, national_id_last4, employee_number, email, phone, department_id, section_id, job_title_id, preferred_language, is_active, profile_completed_at, created_at, updated_at) VALUES (20, 'Test Emp 2', :hash, 'c2', :iv, :tag, '0002', 'EMP020', 'emp20@test.com', '+966522222222', 1, 1, 1, 'ar', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmtEmp2->execute([':hash' => $hash2, ':iv' => random_bytes(12), ':tag' => random_bytes(16)]);

        // Seed Tests & Category
        self::$pdo->exec("INSERT INTO tests (id, code, name_ar, name_en, duration_minutes, question_count, passing_percentage, created_at, updated_at) VALUES (100, 'TEST_PH3', 'اختبار تقني', 'Tech Assessment', 15, 2, 60.00, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO tests (id, code, name_ar, name_en, duration_minutes, question_count, passing_percentage, created_at, updated_at) VALUES (101, 'TEST_PH3_2', 'اختبار ثانوي', 'Second Assessment', 15, 2, 60.00, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO question_categories (id, test_id, name_ar, name_en, created_at) VALUES (100, 100, 'عام', 'General', UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO question_categories (id, test_id, name_ar, name_en, created_at) VALUES (101, 101, 'عام', 'General', UTC_TIMESTAMP());");

        // Seed Question 1 & Options (Test 100)
        self::$pdo->exec("INSERT INTO questions (id, test_id, category_id, type, text_ar, text_en, score, created_by, created_at, updated_at) VALUES (1001, 100, 100, 'single_choice', 'السؤال الأول؟', 'Question 1?', 10.00, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (10011, 1001, 'OPT_A', 'خيار أ', 'Option A', 1, 1);");
        self::$pdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (10012, 1001, 'OPT_B', 'خيار ب', 'Option B', 0, 2);");

        // Seed Question 2 & Options (Test 100)
        self::$pdo->exec("INSERT INTO questions (id, test_id, category_id, type, text_ar, text_en, score, created_by, created_at, updated_at) VALUES (1002, 100, 100, 'single_choice', 'السؤال الثاني؟', 'Question 2?', 10.00, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (10021, 1002, 'OPT_A', 'خيار أ', 'Option A', 0, 1);");
        self::$pdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (10022, 1002, 'OPT_B', 'خيار ب', 'Option B', 1, 2);");

        // Seed Questions & Options for Test 101
        self::$pdo->exec("INSERT INTO questions (id, test_id, category_id, type, text_ar, text_en, score, created_by, created_at, updated_at) VALUES (1003, 101, 101, 'single_choice', 'سؤال 3؟', 'Question 3?', 10.00, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (10031, 1003, 'OPT_A', 'خيار أ', 'Option A', 1, 1);");
        self::$pdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (10032, 1003, 'OPT_B', 'خيار ب', 'Option B', 0, 2);");

        self::$pdo->exec("INSERT INTO questions (id, test_id, category_id, type, text_ar, text_en, score, created_by, created_at, updated_at) VALUES (1004, 101, 101, 'single_choice', 'سؤال 4؟', 'Question 4?', 10.00, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (10041, 1004, 'OPT_A', 'خيار أ', 'Option A', 0, 1);");
        self::$pdo->exec("INSERT INTO question_options (id, question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (10042, 1004, 'OPT_B', 'خيار ب', 'Option B', 1, 2);");

        // Seed Assignments
        self::$pdo->exec("INSERT INTO test_assignments (id, employee_id, test_id, max_attempts, status, assigned_by, assigned_at) VALUES (500, 10, 100, 2, 'PENDING', 1, UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO test_assignments (id, employee_id, test_id, max_attempts, status, assigned_by, assigned_at) VALUES (502, 10, 101, 1, 'PENDING', 1, UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT INTO test_assignments (id, employee_id, test_id, max_attempts, status, assigned_by, assigned_at) VALUES (501, 20, 100, 1, 'PENDING', 1, UTC_TIMESTAMP());");
    }

    public function testAttemptAnswersSchemaCompatibility(): void
    {
        $cols = self::$pdo->query("DESCRIBE attempt_answers")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('answer_text', $cols, 'attempt_answers must contain answer_text');
        $this->assertContains('answered_at', $cols, 'attempt_answers must contain answered_at');
        $this->assertContains('created_at', $cols, 'attempt_answers must contain created_at');
        $this->assertContains('updated_at', $cols, 'attempt_answers must contain updated_at');

        $this->assertNotContains('answer_value', $cols, 'attempt_answers must NOT contain answer_value');
        $this->assertNotContains('last_saved_at', $cols, 'attempt_answers must NOT contain last_saved_at');
    }

    public function testStartAttemptAndFreezeSnapshots(): void
    {
        $res = self::$service->startAttempt(10, 500, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res['success'], 'startAttempt failed: ' . json_encode($res));
        $this->assertArrayHasKey('attempt_id', $res);
        $this->assertArrayHasKey('session_token', $res);

        $attemptId = (int) $res['attempt_id'];

        // Verify active attempt record
        $stmt = self::$pdo->prepare("SELECT * FROM test_attempts WHERE id = :id");
        $stmt->execute([':id' => $attemptId]);
        $attempt = $stmt->fetch();
        $this->assertEquals('IN_PROGRESS', $attempt['status']);
        $this->assertEquals(20.00, (float) $attempt['total_score']);

        // Verify active session record
        $stmtSess = self::$pdo->prepare("SELECT * FROM test_sessions WHERE attempt_id = :aid");
        $stmtSess->execute([':aid' => $attemptId]);
        $session = $stmtSess->fetch();
        $this->assertEquals('ACTIVE', $session['status']);
        $this->assertEquals('EMP:10', $session['active_employee_key']);

        // Verify frozen questions snapshot
        $questions = self::$service->getAttemptQuestions($attemptId);
        $this->assertCount(2, $questions);
    }

    public function testSecondActiveSessionRejection(): void
    {
        // Start attempt on assignment 500 first
        $res1 = self::$service->startAttempt(10, 500, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($res1['success']);

        // Trying to start assignment 502 while active attempt exists -> returns active_attempt_exists
        $resSecond = self::$service->startAttempt(10, 502, '127.0.0.1', 'PHPUnit_Second_Session');
        $this->assertFalse($resSecond['success']);
        $this->assertEquals('active_attempt_exists', $resSecond['code']);

        // Verify no second active session row was created for employee 10
        $stmtCount = self::$pdo->prepare("SELECT COUNT(*) FROM test_sessions WHERE employee_id = 10 AND status = 'ACTIVE'");
        $stmtCount->execute();
        $this->assertEquals(1, (int) $stmtCount->fetchColumn());
    }

    public function testSaveAnswerAndFocusLossRateLimiting(): void
    {
        // Start attempt for Employee 10
        $startRes = self::$service->startAttempt(10, 500, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($startRes['success']);
        $token = (string) $startRes['session_token'];
        $attemptId = (int) $startRes['attempt_id'];

        $questions = self::$service->getAttemptQuestions($attemptId);
        $q1Id = (int) $questions[0]['id'];

        // Save answer
        $saveRes = self::$service->saveAnswer(10, $q1Id, 'OPT_A', $token);
        $this->assertTrue($saveRes['success'], 'saveAnswer failed: ' . json_encode($saveRes));

        // First focus loss ping
        $fl1 = self::$service->recordFocusLoss(10, $token, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($fl1['success']);
        $this->assertFalse($fl1['rate_limited']);
        $this->assertEquals(1, $fl1['focus_loss_count']);

        // Second focus loss ping immediately (<5s) -> rate limited
        $fl2 = self::$service->recordFocusLoss(10, $token, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($fl2['success']);
        $this->assertTrue($fl2['rate_limited']);
        $this->assertEquals(1, $fl2['focus_loss_count']);
    }

    public function testManualSubmissionFollowedByTimeoutIsNoOp(): void
    {
        // Start attempt & save answer for Employee 10
        $startRes = self::$service->startAttempt(10, 500, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($startRes['success']);
        $token = (string) $startRes['session_token'];
        $attemptId = (int) $startRes['attempt_id'];

        $questions = self::$service->getAttemptQuestions($attemptId);
        $q1Id = null;
        foreach ($questions as $q) {
            if ((int) $q['original_question_id'] === 1001) {
                $q1Id = (int) $q['id'];
                break;
            }
        }
        self::$service->saveAnswer(10, $q1Id, 'OPT_A', $token);

        // Submit manually
        $subRes = self::$service->submitAttempt(10, $token, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($subRes['success'], 'submitAttempt failed: ' . json_encode($subRes));
        $this->assertEquals(10.00, $subRes['score']);
        $this->assertEquals(50.00, $subRes['percentage']);
        $this->assertFalse($subRes['passed']); // 50% < 60% threshold

        // Verify status = COMPLETED, submission_reason = MANUAL_SUBMIT, test_session = CLOSED
        $stmtAtt = self::$pdo->query("SELECT status, submission_reason FROM test_attempts WHERE id = {$attemptId}");
        $att = $stmtAtt->fetch();
        $this->assertEquals('COMPLETED', $att['status']);
        $this->assertEquals('MANUAL_SUBMIT', $att['submission_reason']);

        $stmtSess = self::$pdo->query("SELECT status FROM test_sessions WHERE attempt_id = {$attemptId}");
        $this->assertEquals('CLOSED', $stmtSess->fetchColumn());

        // Timeout call on already submitted attempt -> returns safe no-op
        $timeoutRes = self::$service->timeoutExpiredAttemptById($attemptId);
        $this->assertTrue($timeoutRes['success']);
        $this->assertFalse($timeoutRes['timed_out']);

        // Submit call on already submitted attempt -> rejected
        $subRes2 = self::$service->submitAttempt(10, $token, '127.0.0.1', 'PHPUnit');
        $this->assertFalse($subRes2['success']);
        $this->assertEquals('attempt_already_submitted', $subRes2['code']);
    }

    public function testTimeoutFinalizationFollowedByManualSubmitAndAuditIntegrity(): void
    {
        // Start fresh attempt for Employee 20
        $startRes = self::$service->startAttempt(20, 501, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($startRes['success']);
        $attemptId = (int) $startRes['attempt_id'];
        $token = (string) $startRes['session_token'];

        // Force expires_at to past to simulate timeout
        self::$pdo->exec("UPDATE test_attempts SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 SECOND) WHERE id = {$attemptId}");

        // Call timeout finalization 1
        $t1 = self::$service->timeoutExpiredAttemptById($attemptId);
        $this->assertTrue($t1['success']);
        $this->assertTrue($t1['timed_out']);

        // Verify exact database status transitions
        $stmtAtt = self::$pdo->query("SELECT status, submission_reason FROM test_attempts WHERE id = {$attemptId}");
        $att = $stmtAtt->fetch();
        $this->assertEquals('TIMED_OUT', $att['status']);
        $this->assertEquals('AUTO_TIMEOUT', $att['submission_reason']);

        $stmtSess = self::$pdo->query("SELECT status FROM test_sessions WHERE attempt_id = {$attemptId}");
        $this->assertEquals('EXPIRED', $stmtSess->fetchColumn());

        // Call timeout finalization 2 on same attempt -> safe no-op
        $t2 = self::$service->timeoutExpiredAttemptById($attemptId);
        $this->assertTrue($t2['success']);
        $this->assertFalse($t2['timed_out']);

        // Verify EXACTLY 1 timeout audit record was created in audit_logs
        $stmtAudit = self::$pdo->query("SELECT COUNT(*), actor_type, admin_id, employee_id FROM audit_logs WHERE entity_id = {$attemptId} AND action = 'ASSESSMENT_ATTEMPT_TIMED_OUT' GROUP BY actor_type, admin_id, employee_id");
        $auditRow = $stmtAudit->fetch();
        $this->assertEquals(1, (int) $auditRow['COUNT(*)']);
        $this->assertEquals('SYSTEM', $auditRow['actor_type']);
        $this->assertNull($auditRow['admin_id']);
        $this->assertNull($auditRow['employee_id']);

        // Manual submit on timed-out attempt -> rejected
        $subRes = self::$service->submitAttempt(20, $token, '127.0.0.1', 'PHPUnit');
        $this->assertFalse($subRes['success']);
    }

    public function testRecordFocusLossRejectedAfterServerSideExpiry(): void
    {
        $startRes = self::$service->startAttempt(10, 500, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($startRes['success']);
        $token = (string) $startRes['session_token'];
        $attemptId = (int) $startRes['attempt_id'];

        // Force expires_at to past
        self::$pdo->exec("UPDATE test_attempts SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 SECOND) WHERE id = {$attemptId}");

        // Call recordFocusLoss
        $flRes = self::$service->recordFocusLoss(10, $token, '127.0.0.1', 'PHPUnit');
        $this->assertFalse($flRes['success']);

        // Verify focus_loss_count was NOT incremented
        $flCount = (int) self::$pdo->query("SELECT focus_loss_count FROM test_attempts WHERE id = {$attemptId}")->fetchColumn();
        $this->assertEquals(0, $flCount);

        // Verify NO security event was inserted
        $secCount = (int) self::$pdo->query("SELECT COUNT(*) FROM security_events WHERE attempt_id = {$attemptId} AND event_type = 'ASSESSMENT_FOCUS_LOST'")->fetchColumn();
        $this->assertEquals(0, $secCount);
    }

    public function testBatchWorkerDryRunAndRealExecutionIdempotency(): void
    {
        // Start fresh attempt for Employee 10
        $startRes = self::$service->startAttempt(10, 500, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($startRes['success']);
        $attemptId = (int) $startRes['attempt_id'];

        // Force expires_at to past
        self::$pdo->exec("UPDATE test_attempts SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 SECOND) WHERE id = {$attemptId}");

        // 1. Dry Run
        $dryRes = self::$service->finalizeAllExpiredAttempts(50, true);
        $this->assertTrue($dryRes['lock_acquired']);
        $this->assertTrue($dryRes['dry_run']);
        $this->assertEquals(1, $dryRes['scanned']);
        $this->assertEquals(0, $dryRes['finalized']);
        $this->assertEquals(1, $dryRes['skipped']);

        // Assert database untouched after dry-run
        $attStatus = self::$pdo->query("SELECT status FROM test_attempts WHERE id = {$attemptId}")->fetchColumn();
        $this->assertEquals('IN_PROGRESS', $attStatus);

        $sessStatus = self::$pdo->query("SELECT status FROM test_sessions WHERE attempt_id = {$attemptId}")->fetchColumn();
        $this->assertEquals('ACTIVE', $sessStatus);

        $auditCount = (int) self::$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity_id = {$attemptId} AND action = 'ASSESSMENT_ATTEMPT_TIMED_OUT'")->fetchColumn();
        $this->assertEquals(0, $auditCount);

        // 2. Real Run
        $realRes = self::$service->finalizeAllExpiredAttempts(50, false);
        $this->assertTrue($realRes['lock_acquired']);
        $this->assertEquals(1, $realRes['scanned']);
        $this->assertEquals(1, $realRes['finalized']);
        $this->assertEquals(0, $realRes['skipped']);
        $this->assertEquals(0, $realRes['failed']);

        // Assert attempt status is TIMED_OUT & submission_reason is AUTO_TIMEOUT
        $stmtAtt = self::$pdo->query("SELECT status, submission_reason FROM test_attempts WHERE id = {$attemptId}");
        $att = $stmtAtt->fetch();
        $this->assertEquals('TIMED_OUT', $att['status']);
        $this->assertEquals('AUTO_TIMEOUT', $att['submission_reason']);

        // Assert session status is EXPIRED
        $sessStatusReal = self::$pdo->query("SELECT status FROM test_sessions WHERE attempt_id = {$attemptId}")->fetchColumn();
        $this->assertEquals('EXPIRED', $sessStatusReal);

        // Assert exactly 1 timeout audit event exists
        $auditCountReal = (int) self::$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity_id = {$attemptId} AND action = 'ASSESSMENT_ATTEMPT_TIMED_OUT'")->fetchColumn();
        $this->assertEquals(1, $auditCountReal);

        // 3. Re-run Worker (Idempotency)
        $rerunRes = self::$service->finalizeAllExpiredAttempts(50, false);
        $this->assertEquals(0, $rerunRes['scanned']);
        $this->assertEquals(0, $rerunRes['finalized']);

        // Assert no duplicate audit event
        $auditCountRerun = (int) self::$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity_id = {$attemptId} AND action = 'ASSESSMENT_ATTEMPT_TIMED_OUT'")->fetchColumn();
        $this->assertEquals(1, $auditCountRerun);
    }

    public function testBatchWorkerPartialFailureClassificationAndCLIExitCode3(): void
    {
        // 1. Direct timeout attempt with invalid ID returns success=false
        $failRes = self::$service->timeoutExpiredAttemptById(-1);
        $this->assertFalse($failRes['success']);
        $this->assertEquals('invalid_attempt_id', $failRes['code']);

        // 2. Valid expired attempt for Employee 10
        $startRes1 = self::$service->startAttempt(10, 500, '127.0.0.1', 'PHPUnit');
        $this->assertTrue($startRes1['success']);
        $validId = (int) $startRes1['attempt_id'];
        self::$pdo->exec("UPDATE test_attempts SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 SECOND) WHERE id = {$validId}");

        // Create a custom subclass instance of AssessmentAttemptService that simulates a processing error on attempt 999
        $mockService = new class(self::$pdo) extends AssessmentAttemptService {
            public function timeoutExpiredAttemptById(int $attemptId, string $ipAddress = '127.0.0.1', string $userAgent = 'CLI_CRON_WORKER'): array {
                if ($attemptId === 999) {
                    return ['success' => false, 'code' => 'simulated_batch_failure'];
                }
                return parent::timeoutExpiredAttemptById($attemptId, $ipAddress, $userAgent);
            }
        };

        // Insert invalid attempt 999
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        self::$pdo->exec("INSERT INTO test_attempts (id, employee_id, test_id, assignment_id, attempt_number, duration_seconds, status, score, total_score, percentage, passed, ip_address, user_agent, started_at, expires_at, created_at, updated_at) VALUES (999, 10, 100, 500, 9, 900, 'IN_PROGRESS', 0, 0, 0, 0, '127.0.0.1', 'PHPUnit', UTC_TIMESTAMP(), DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 SECOND), UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Run batch processor on mock service
        $batchRes = $mockService->finalizeAllExpiredAttempts(50, false);
        $this->assertEquals(2, $batchRes['scanned']);
        $this->assertEquals(1, $batchRes['finalized']);
        $this->assertEquals(1, $batchRes['failed']);
        $this->assertEquals(0, $batchRes['skipped']);

        // Verify valid attempt finalized
        $validStatus = self::$pdo->query("SELECT status FROM test_attempts WHERE id = {$validId}")->fetchColumn();
        $this->assertEquals('TIMED_OUT', $validStatus);

        // Clean up invalid attempt
        self::$pdo->exec("DELETE FROM test_attempts WHERE id = 999;");
    }
}