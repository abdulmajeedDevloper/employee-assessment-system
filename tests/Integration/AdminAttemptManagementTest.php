<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\AdminAttemptRepository;
use App\Services\AdminAttemptManagementService;
use App\Services\AssessmentAttemptService;
use App\Support\Database;
use App\Validators\AdminAttemptActionValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminAttemptManagementTest extends TestCase
{
    private PDO $pdo;
    private AdminAttemptRepository $repository;
    private AdminAttemptManagementService $service;
    private string $prefix;
    private int $adminId;
    private int $departmentId;
    private int $sectionId;
    private int $jobTitleId;
    private int $employeeId;
    private int $testId;
    private int $categoryId;
    private int $questionId;
    private int $assignmentId;
    private int $attemptId;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_ENV'] = 'test';
        $_ENV['DB_DATABASE'] = 'employee_assessment_test';
        require_once __DIR__ . '/../../bootstrap/init.php';
        $this->pdo = Database::getInstance();
        $database = (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!str_ends_with($database, '_test')) {
            $this->fail('Unsafe database for attempt tests: ' . $database);
        }

        $_SESSION = ['lang' => 'en'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $this->prefix = 'ATT-' . bin2hex(random_bytes(4));
        $this->seedReferences();
        $this->repository = new AdminAttemptRepository($this->pdo);
        $this->service = new AdminAttemptManagementService(
            $this->repository,
            new AdminAttemptActionValidator(),
            $this->pdo,
            new AssessmentAttemptService($this->pdo)
        );
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testCancelActiveAttemptClosesSessionAndUsesFieldNameOnlyAudit(): void
    {
        $attempt = $this->repository->findSafeById($this->attemptId);
        $token = $this->service->issueActionToken($this->adminId, $this->attemptId, 'cancel', $attempt);

        $result = $this->service->cancelAttempt(
            $this->attemptId,
            $token,
            ['reason' => 'The employee reported a device failure during the assessment.'],
            $this->adminId
        );
        $this->assertSame('SUCCESS', $result['code']);

        $row = $this->pdo->query("SELECT status, submission_reason, cancellation_reason FROM test_attempts WHERE id={$this->attemptId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('CANCELLED', $row['status']);
        $this->assertSame('ADMIN_CANCEL', $row['submission_reason']);
        $this->assertNotSame('', $row['cancellation_reason']);
        $this->assertSame('CLOSED', (string)$this->pdo->query("SELECT status FROM test_sessions WHERE attempt_id={$this->attemptId}")->fetchColumn());
        $this->assertSame('COMPLETED', (string)$this->pdo->query("SELECT status FROM test_assignments WHERE id={$this->assignmentId}")->fetchColumn());

        $audit = $this->fetchAudit('CANCEL_ATTEMPT');
        $old = json_decode((string)$audit['old_values'], true, 512, JSON_THROW_ON_ERROR);
        $new = json_decode((string)$audit['new_values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['changed_field_names'], array_keys($old));
        $this->assertSame($old, $new);
        $this->assertStringNotContainsString('device failure', (string)$audit['new_values']);
    }

    public function testAuthorizeRetakeExtendsLimitAndCreatesOnePendingAuthorization(): void
    {
        $this->pdo->exec("UPDATE test_attempts SET status='COMPLETED', submitted_at=UTC_TIMESTAMP(), submission_reason='MANUAL_SUBMIT' WHERE id={$this->attemptId}");
        $this->pdo->exec("UPDATE test_sessions SET status='CLOSED', ended_at=UTC_TIMESTAMP() WHERE attempt_id={$this->attemptId}");
        $this->pdo->exec("UPDATE test_assignments SET status='COMPLETED', max_attempts=1 WHERE id={$this->assignmentId}");

        $attempt = $this->repository->findSafeById($this->attemptId);
        $token = $this->service->issueActionToken($this->adminId, $this->attemptId, 'retake', $attempt);
        $reason = 'A verified network outage prevented the employee from completing the assessment.';
        $result = $this->service->authorizeRetake($this->attemptId, $token, ['reason' => $reason], $this->adminId);
        $this->assertSame('SUCCESS', $result['code']);

        $assignment = $this->pdo->query("SELECT status,max_attempts FROM test_assignments WHERE id={$this->assignmentId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('RETAKE_AUTHORIZED', $assignment['status']);
        $this->assertSame(2, (int)$assignment['max_attempts']);
        $this->assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM retake_authorizations WHERE assignment_id={$this->assignmentId} AND consumed_at IS NULL")->fetchColumn());

        $audit = $this->fetchAudit('AUTHORIZE_RETAKE');
        $payload = json_decode((string)$audit['new_values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['created_field_names'], array_keys($payload));
        $this->assertStringNotContainsString('network outage', (string)$audit['new_values']);
    }


    public function testAuthorizedRetakeIsConsumedWhenNewAttemptStarts(): void
    {
        $this->pdo->exec("UPDATE test_attempts SET status='COMPLETED', submitted_at=UTC_TIMESTAMP(), submission_reason='MANUAL_SUBMIT' WHERE id={$this->attemptId}");
        $this->pdo->exec("UPDATE test_sessions SET status='CLOSED', ended_at=UTC_TIMESTAMP() WHERE attempt_id={$this->attemptId}");
        $this->pdo->exec("UPDATE test_assignments SET status='COMPLETED', max_attempts=1 WHERE id={$this->assignmentId}");

        $attempt = $this->repository->findSafeById($this->attemptId);
        $token = $this->service->issueActionToken($this->adminId, $this->attemptId, 'retake', $attempt);
        $authorized = $this->service->authorizeRetake(
            $this->attemptId,
            $token,
            ['reason' => 'The first attempt was interrupted by a verified infrastructure outage.'],
            $this->adminId
        );
        $this->assertSame('SUCCESS', $authorized['code']);

        $started = (new AssessmentAttemptService($this->pdo))->startAttempt(
            $this->employeeId,
            $this->assignmentId,
            '127.0.0.1',
            'PHPUnit Retake'
        );
        $this->assertTrue($started['success']);
        $newAttemptId = (int)$started['attempt_id'];
        $this->assertGreaterThan($this->attemptId, $newAttemptId);

        $authorization = $this->pdo->query("SELECT consumed_at,consumed_by_attempt_id FROM retake_authorizations WHERE assignment_id={$this->assignmentId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($authorization['consumed_at']);
        $this->assertSame($newAttemptId, (int)$authorization['consumed_by_attempt_id']);
        $this->assertSame('IN_PROGRESS', (string)$this->pdo->query("SELECT status FROM test_assignments WHERE id={$this->assignmentId}")->fetchColumn());
    }

    public function testSupervisorNotesUpdateUsesOneTimeToken(): void
    {
        $attempt = $this->repository->findSafeById($this->attemptId);
        $token = $this->service->issueActionToken($this->adminId, $this->attemptId, 'notes', $attempt);
        $notes = 'Supervisor reviewed the focus-loss events and contacted the employee.';

        $result = $this->service->updateSupervisorNotes(
            $this->attemptId,
            $token,
            ['supervisor_notes' => $notes],
            $this->adminId
        );
        $this->assertSame('SUCCESS', $result['code']);
        $this->assertSame($notes, (string)$this->pdo->query("SELECT supervisor_notes FROM test_attempts WHERE id={$this->attemptId}")->fetchColumn());

        $replay = $this->service->updateSupervisorNotes(
            $this->attemptId,
            $token,
            ['supervisor_notes' => 'A second value that must not be accepted.'],
            $this->adminId
        );
        $this->assertSame('CONFLICT', $replay['code']);

        $audit = $this->fetchAudit('UPDATE_ATTEMPT_NOTES');
        $payload = json_decode((string)$audit['new_values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['changed_field_names'], array_keys($payload));
        $this->assertSame(['supervisor_notes'], $payload['changed_field_names']);
        $this->assertStringNotContainsString('focus-loss', (string)$audit['new_values']);
    }

    private function seedReferences(): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO admins (name,email,username,password_hash,role,is_active,created_at,updated_at) VALUES (:name,:email,:username,:hash,'super_admin',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([
            ':name' => $this->prefix . ' Admin',
            ':email' => strtolower($this->prefix) . '@test.local',
            ':username' => strtolower($this->prefix),
            ':hash' => password_hash('AttemptTest123!', PASSWORD_BCRYPT),
        ]);
        $this->adminId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO departments (name_ar,name_en,is_active,created_at,updated_at) VALUES (:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
            ->execute([':name' => $this->prefix]);
        $this->departmentId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO sections (department_id,name_ar,name_en,is_active,created_at,updated_at) VALUES (:department,:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
            ->execute([':department' => $this->departmentId, ':name' => $this->prefix]);
        $this->sectionId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO job_titles (name_ar,name_en,is_active,created_at,updated_at) VALUES (:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
            ->execute([':name' => $this->prefix]);
        $this->jobTitleId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("\n            INSERT INTO employees (\n                full_name,national_id_hash,national_id_ciphertext,national_id_iv,national_id_tag,key_version,\n                national_id_last4,employee_number,email,phone,department_id,section_id,job_title_id,\n                preferred_language,is_active,profile_completed_at,created_at,updated_at,archived_at\n            ) VALUES (\n                :name,:hash,:cipher,:iv,:tag,1,'0002',:number,:email,'+966500000002',\n                :department,:section,:job,'en',1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),NULL\n            )\n        ");
        $stmt->execute([
            ':name' => $this->prefix . ' Employee', ':hash' => random_bytes(32),
            ':cipher' => base64_encode(random_bytes(32)), ':iv' => random_bytes(12), ':tag' => random_bytes(16),
            ':number' => $this->prefix, ':email' => strtolower($this->prefix) . '-employee@test.local',
            ':department' => $this->departmentId, ':section' => $this->sectionId, ':job' => $this->jobTitleId,
        ]);
        $this->employeeId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("\n            INSERT INTO tests (code,name_ar,name_en,duration_minutes,question_count,passing_percentage,display_order,\n                randomize_questions,randomize_options,allow_back_navigation,show_result_to_employee,is_active,available_from,available_until,created_at,updated_at)\n            VALUES (:code,:name,:name,30,1,60,1,0,0,1,0,1,NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())\n        ")->execute([':code' => $this->prefix, ':name' => $this->prefix . ' Test']);
        $this->testId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO question_categories (test_id,name_ar,name_en,is_active,created_at) VALUES (:test,:name,:name,1,UTC_TIMESTAMP())")
            ->execute([':test' => $this->testId, ':name' => $this->prefix . ' Category']);
        $this->categoryId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO questions (test_id,category_id,type,text_ar,text_en,difficulty,score,is_active,created_by,created_at,updated_at) VALUES (:test,:category,'single_choice','سؤال تجريبي','Test question','medium',10.00,1,:admin,UTC_TIMESTAMP(),UTC_TIMESTAMP())")
            ->execute([':test' => $this->testId, ':category' => $this->categoryId, ':admin' => $this->adminId]);
        $this->questionId = (int)$this->pdo->lastInsertId();
        $optionStmt = $this->pdo->prepare("INSERT INTO question_options (question_id,option_key,text_ar,text_en,is_correct,display_order) VALUES (:question,:key,:ar,:en,:correct,:display)");
        $optionStmt->execute([':question' => $this->questionId, ':key' => 'A', ':ar' => 'صحيح', ':en' => 'Correct', ':correct' => 1, ':display' => 1]);
        $optionStmt->execute([':question' => $this->questionId, ':key' => 'B', ':ar' => 'خاطئ', ':en' => 'Incorrect', ':correct' => 0, ':display' => 2]);

        $this->pdo->prepare("INSERT INTO test_assignments (employee_id,test_id,max_attempts,status,assigned_by,assigned_at) VALUES (:employee,:test,1,'IN_PROGRESS',:admin,UTC_TIMESTAMP())")
            ->execute([':employee' => $this->employeeId, ':test' => $this->testId, ':admin' => $this->adminId]);
        $this->assignmentId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("\n            INSERT INTO test_attempts (employee_id,test_id,assignment_id,attempt_number,started_at,expires_at,duration_seconds,total_score,status,ip_address,user_agent,created_at,updated_at)\n            VALUES (:employee,:test,:assignment,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE),1800,10.00,'IN_PROGRESS','127.0.0.1','PHPUnit',UTC_TIMESTAMP(),UTC_TIMESTAMP())\n        ")->execute([':employee' => $this->employeeId, ':test' => $this->testId, ':assignment' => $this->assignmentId]);
        $this->attemptId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO test_sessions (employee_id,attempt_id,session_token_hash,ip_address,started_at,last_activity_at,status) VALUES (:employee,:attempt,:hash,'127.0.0.1',UTC_TIMESTAMP(),UTC_TIMESTAMP(),'ACTIVE')")
            ->execute([':employee' => $this->employeeId, ':attempt' => $this->attemptId, ':hash' => hash('sha256', random_bytes(32))]);
    }

    private function fetchAudit(string $action): array
    {
        $stmt = $this->pdo->prepare("SELECT old_values,new_values FROM audit_logs WHERE action=:action AND entity_type='TEST_ATTEMPT' AND entity_id=:id ORDER BY id DESC LIMIT 1");
        $stmt->execute([':action' => $action, ':id' => $this->attemptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        return $row;
    }

    private function cleanup(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->pdo->prepare("DELETE al FROM audit_logs al INNER JOIN test_attempts att ON att.id = al.entity_id WHERE att.assignment_id = :assignment_id AND al.entity_type IN ('TEST_ATTEMPT','test_attempt')")
            ->execute([':assignment_id' => $this->assignmentId]);
        $this->pdo->prepare("DELETE se FROM security_events se INNER JOIN test_attempts att ON att.id = se.attempt_id WHERE att.assignment_id = :assignment_id")
            ->execute([':assignment_id' => $this->assignmentId]);
        $this->pdo->prepare("DELETE ts FROM test_sessions ts INNER JOIN test_attempts att ON att.id = ts.attempt_id WHERE att.assignment_id = :assignment_id")
            ->execute([':assignment_id' => $this->assignmentId]);
        $this->pdo->prepare("DELETE aa FROM attempt_answers aa INNER JOIN attempt_questions aq ON aq.id = aa.attempt_question_id INNER JOIN test_attempts att ON att.id = aq.attempt_id WHERE att.assignment_id = :assignment_id")
            ->execute([':assignment_id' => $this->assignmentId]);
        $this->pdo->prepare("DELETE aq FROM attempt_questions aq INNER JOIN test_attempts att ON att.id = aq.attempt_id WHERE att.assignment_id = :assignment_id")
            ->execute([':assignment_id' => $this->assignmentId]);
        $this->pdo->prepare("DELETE FROM retake_authorizations WHERE assignment_id = :id")->execute([':id' => $this->assignmentId]);
        $this->pdo->prepare("DELETE FROM test_attempts WHERE assignment_id = :id")->execute([':id' => $this->assignmentId]);
        $this->pdo->prepare("DELETE FROM assessment_progress WHERE employee_id = :id")->execute([':id' => $this->employeeId]);
        $this->pdo->prepare("DELETE FROM test_assignments WHERE id = :id")->execute([':id' => $this->assignmentId]);
        $this->pdo->prepare("DELETE FROM employees WHERE id = :id")->execute([':id' => $this->employeeId]);
        $this->pdo->prepare("DELETE FROM question_options WHERE question_id = :id")->execute([':id' => $this->questionId]);
        $this->pdo->prepare("DELETE FROM questions WHERE id = :id")->execute([':id' => $this->questionId]);
        $this->pdo->prepare("DELETE FROM question_categories WHERE id = :id")->execute([':id' => $this->categoryId]);
        $this->pdo->prepare("DELETE FROM tests WHERE id = :id")->execute([':id' => $this->testId]);
        $this->pdo->prepare("DELETE FROM admins WHERE id = :id")->execute([':id' => $this->adminId]);
        $this->pdo->prepare("DELETE FROM sections WHERE id = :id")->execute([':id' => $this->sectionId]);
        $this->pdo->prepare("DELETE FROM departments WHERE id = :id")->execute([':id' => $this->departmentId]);
        $this->pdo->prepare("DELETE FROM job_titles WHERE id = :id")->execute([':id' => $this->jobTitleId]);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
