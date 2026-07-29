<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Notifications\AssessmentCompletionEmailBuilder;
use App\Notifications\EmailRetryPolicy;
use App\Notifications\MailMessage;
use App\Notifications\MailTransportInterface;
use App\Repositories\EmailNotificationRepository;
use App\Services\AssessmentCompletionNotificationService;
use App\Services\EmailNotificationWorker;
use App\Support\Database;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class AssessmentCompletionNotificationTest extends TestCase
{
    private PDO $pdo;
    private string $prefix;
    private int $adminId;
    private int $employeeId;
    private int $triggeringAttemptId;
    private string $iqCode;
    private string $englishCode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Database::getInstance();
        $this->prefix = 'MAIL' . strtoupper(bin2hex(random_bytes(4)));
        $this->iqCode = $this->prefix . '_IQ';
        $this->englishCode = $this->prefix . '_EN';
        $_ENV['MAIL_NOTIFICATIONS_ENABLED'] = 'true';
        $_ENV['ASSESSMENT_COMPLETION_RECIPIENTS'] = 'hr@example.test';
        $_ENV['NOTIFICATION_REQUIRED_TEST_CODES'] = $this->iqCode . ',' . $this->englishCode;
        $this->seedCompletedAssessment();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $this->pdo->exec("DELETE FROM audit_logs WHERE entity_type='EMAIL_NOTIFICATION' AND entity_id IN (SELECT id FROM email_notifications WHERE employee_id=" . (int)$this->employeeId . ")");
            $this->pdo->exec("DELETE FROM email_notifications WHERE employee_id=" . (int)$this->employeeId);
            $this->pdo->exec("DELETE FROM assessment_progress WHERE employee_id=" . (int)$this->employeeId);
            $this->pdo->exec("DELETE FROM test_attempts WHERE employee_id=" . (int)$this->employeeId);
            $this->pdo->exec("DELETE FROM test_assignments WHERE employee_id=" . (int)$this->employeeId);
            $this->pdo->exec("DELETE FROM employees WHERE id=" . (int)$this->employeeId);
            $this->pdo->exec("DELETE FROM tests WHERE code LIKE " . $this->pdo->quote($this->prefix . '%'));
            $this->pdo->exec("DELETE FROM sections WHERE name_en LIKE " . $this->pdo->quote($this->prefix . '%'));
            $this->pdo->exec("DELETE FROM departments WHERE name_en LIKE " . $this->pdo->quote($this->prefix . '%'));
            $this->pdo->exec("DELETE FROM job_titles WHERE name_en LIKE " . $this->pdo->quote($this->prefix . '%'));
            $this->pdo->exec("DELETE FROM admins WHERE id=" . (int)$this->adminId);
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        unset($_ENV['NOTIFICATION_REQUIRED_TEST_CODES'], $_ENV['ASSESSMENT_COMPLETION_RECIPIENTS']);
        parent::tearDown();
    }

    public function testCompletionCreatesDeduplicatedMaskedOutboxMessage(): void
    {
        $service = new AssessmentCompletionNotificationService($this->pdo);
        $first = $service->enqueueIfEligible($this->employeeId, $this->triggeringAttemptId);
        $second = $service->enqueueIfEligible($this->employeeId, $this->triggeringAttemptId);

        $this->assertSame('QUEUED', $first['code']);
        $this->assertSame('DUPLICATE', $second['code']);

        $stmt = $this->pdo->prepare('SELECT * FROM email_notifications WHERE id = :id');
        $stmt->execute([':id' => (int)$first['notification_id']]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($notification);
        $this->assertSame('PENDING', $notification['status']);
        $this->assertSame('ASSESSMENT_COMPLETED', $notification['type']);
        $this->assertSame('hr@example.test', $notification['recipient']);
        $this->assertSame(0, (int)$notification['attempts']);

        $payloadJson = (string)$notification['payload'];
        $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('********6789', $payload['employee']['masked_national_id']);
        $this->assertStringNotContainsString('1234567890', $payloadJson);
        $this->assertStringNotContainsString('-employee@test.local', $payloadJson);
        $this->assertCount(2, $payload['completion']['results']);
        $this->assertSame('FAILED', $payload['completion']['final_status']);

        $audit = $this->pdo->prepare("SELECT new_values FROM audit_logs WHERE action='ASSESSMENT_COMPLETION_NOTIFICATION_QUEUED' AND entity_id=:id LIMIT 1");
        $audit->execute([':id' => (int)$first['notification_id']]);
        $auditJson = (string)$audit->fetchColumn();
        $this->assertStringNotContainsString($this->prefix, $auditJson);
        $this->assertStringNotContainsString('employee@test.local', $auditJson);
    }

    public function testWorkerSendsOnceAndMarksNotificationSent(): void
    {
        $service = new AssessmentCompletionNotificationService($this->pdo);
        $queued = $service->enqueueIfEligible($this->employeeId, $this->triggeringAttemptId);
        $transport = new CapturingMailTransport();
        $repository = new EmailNotificationRepository($this->pdo);
        $worker = new EmailNotificationWorker(
            $repository,
            $transport,
            new AssessmentCompletionEmailBuilder(
                'الجهة الرسمية',
                'Official Organization',
                'https://assessment.example/public',
                'Asia/Riyadh'
            ),
            new EmailRetryPolicy(),
            5,
            15
        );

        $result = $worker->run(10, 'phpunit-worker-' . bin2hex(random_bytes(4)));

        $this->assertSame(1, $result['claimed']);
        $this->assertSame(1, $result['sent']);
        $this->assertCount(1, $transport->messages);
        $this->assertStringContainsString('********6789', $transport->messages[0]->htmlBody);
        $this->assertStringContainsString('/admin/employees/view?id=' . $this->employeeId, $transport->messages[0]->htmlBody);

        $stmt = $this->pdo->prepare('SELECT status, attempts, sent_at, locked_by FROM email_notifications WHERE id=:id');
        $stmt->execute([':id' => (int)$queued['notification_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('SENT', $row['status']);
        $this->assertSame(1, (int)$row['attempts']);
        $this->assertNotNull($row['sent_at']);
        $this->assertNull($row['locked_by']);

        $again = $worker->run(10, 'phpunit-worker-' . bin2hex(random_bytes(4)));
        $this->assertSame(0, $again['claimed']);
        $this->assertCount(1, $transport->messages);
    }

    public function testWorkerSchedulesRetryWithoutPersistingTransportMessage(): void
    {
        $service = new AssessmentCompletionNotificationService($this->pdo);
        $queued = $service->enqueueIfEligible($this->employeeId, $this->triggeringAttemptId);
        $repository = new EmailNotificationRepository($this->pdo);
        $worker = new EmailNotificationWorker(
            $repository,
            new FailingMailTransport(),
            new AssessmentCompletionEmailBuilder(
                'الجهة الرسمية',
                'Official Organization',
                'https://assessment.example/public',
                'UTC'
            ),
            new EmailRetryPolicy(),
            5,
            15
        );

        $result = $worker->run(10, 'phpunit-worker-' . bin2hex(random_bytes(4)));
        $this->assertSame(1, $result['retried']);

        $stmt = $this->pdo->prepare('SELECT status, attempts, error_message FROM email_notifications WHERE id=:id');
        $stmt->execute([':id' => (int)$queued['notification_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('PENDING', $row['status']);
        $this->assertSame(1, (int)$row['attempts']);
        $this->assertStringContainsString('RuntimeException', (string)$row['error_message']);
        $this->assertStringNotContainsString('employee@test.local', (string)$row['error_message']);
        $this->assertStringNotContainsString('secret', (string)$row['error_message']);
    }

    private function seedCompletedAssessment(): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO admins (name,email,username,password_hash,role,is_active,created_at,updated_at) VALUES (:name,:email,:username,:hash,'super_admin',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([
            ':name' => $this->prefix . ' Admin',
            ':email' => strtolower($this->prefix) . '-admin@test.local',
            ':username' => strtolower($this->prefix) . '-admin',
            ':hash' => password_hash('MailTest!123', PASSWORD_DEFAULT),
        ]);
        $this->adminId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("INSERT INTO departments (name_ar,name_en,is_active,created_at,updated_at) VALUES (:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':name' => $this->prefix . ' Department']);
        $departmentId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("INSERT INTO sections (department_id,name_ar,name_en,is_active,created_at,updated_at) VALUES (:department_id,:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':department_id' => $departmentId, ':name' => $this->prefix . ' Section']);
        $sectionId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("INSERT INTO job_titles (name_ar,name_en,is_active,created_at,updated_at) VALUES (:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':name' => $this->prefix . ' Job']);
        $jobTitleId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("
            INSERT INTO employees (
                full_name,national_id_hash,national_id_ciphertext,national_id_iv,national_id_tag,key_version,
                national_id_last4,employee_number,email,phone,department_id,section_id,job_title_id,
                preferred_language,is_active,profile_completed_at,created_at,updated_at,archived_at
            ) VALUES (
                :name,:hash,:cipher,:iv,:tag,1,'6789',:number,:email,'+966500000000',
                :department_id,:section_id,:job_title_id,'en',1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),NULL
            )
        ");
        $stmt->execute([
            ':name' => $this->prefix . ' Employee',
            ':hash' => random_bytes(32),
            ':cipher' => 'encrypted-1234567890',
            ':iv' => random_bytes(12),
            ':tag' => random_bytes(16),
            ':number' => $this->prefix,
            ':email' => strtolower($this->prefix) . '-employee@test.local',
            ':department_id' => $departmentId,
            ':section_id' => $sectionId,
            ':job_title_id' => $jobTitleId,
        ]);
        $this->employeeId = (int)$this->pdo->lastInsertId();

        $testIds = [];
        foreach ([[$this->iqCode, 1], [$this->englishCode, 2]] as [$code, $order]) {
            $stmt = $this->pdo->prepare("
                INSERT INTO tests (
                    code,name_ar,name_en,duration_minutes,question_count,passing_percentage,display_order,
                    randomize_questions,randomize_options,allow_back_navigation,show_result_to_employee,
                    is_active,available_from,available_until,created_at,updated_at
                ) VALUES (:code,:name,:name,30,1,60,:display_order,1,1,1,0,1,NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())
            ");
            $stmt->execute([
                ':code' => $code,
                ':name' => $code . ' Assessment',
                ':display_order' => $order,
            ]);
            $testIds[] = (int)$this->pdo->lastInsertId();
        }

        foreach ($testIds as $index => $testId) {
            $stmt = $this->pdo->prepare("
                INSERT INTO test_assignments
                    (employee_id,test_id,max_attempts,status,assigned_by,assigned_at,available_from,available_until)
                VALUES (:employee_id,:test_id,1,'COMPLETED',:admin_id,UTC_TIMESTAMP(),NULL,NULL)
            ");
            $stmt->execute([
                ':employee_id' => $this->employeeId,
                ':test_id' => $testId,
                ':admin_id' => $this->adminId,
            ]);
            $assignmentId = (int)$this->pdo->lastInsertId();

            $percentage = $index === 0 ? 85 : 55;
            $passed = $percentage >= 60 ? 1 : 0;
            $stmt = $this->pdo->prepare("
                INSERT INTO test_attempts (
                    employee_id,test_id,assignment_id,attempt_number,started_at,submitted_at,expires_at,
                    duration_seconds,score,total_score,percentage,passed,status,submission_reason,
                    ip_address,user_agent,focus_loss_count,created_at,updated_at
                ) VALUES (
                    :employee_id,:test_id,:assignment_id,1,
                    DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE),UTC_TIMESTAMP(),UTC_TIMESTAMP(),
                    1800,:score,100,:percentage,:passed,'COMPLETED','MANUAL_SUBMIT',
                    '127.0.0.1','PHPUNIT',0,UTC_TIMESTAMP(),UTC_TIMESTAMP()
                )
            ");
            $stmt->execute([
                ':employee_id' => $this->employeeId,
                ':test_id' => $testId,
                ':assignment_id' => $assignmentId,
                ':score' => $percentage,
                ':percentage' => $percentage,
                ':passed' => $passed,
            ]);
            $attemptId = (int)$this->pdo->lastInsertId();
            if ($index === 1) {
                $this->triggeringAttemptId = $attemptId;
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO assessment_progress (employee_id,overall_status,current_test_id,completed_at,updated_at)
            VALUES (:employee_id,'COMPLETED',NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())
        ");
        $stmt->execute([':employee_id' => $this->employeeId]);
    }
}

final class CapturingMailTransport implements MailTransportInterface
{
    /** @var array<int,MailMessage> */
    public array $messages = [];

    public function send(MailMessage $message): void
    {
        $this->messages[] = $message;
    }
}

final class FailingMailTransport implements MailTransportInterface
{
    public function send(MailMessage $message): void
    {
        throw new RuntimeException('smtp secret employee@test.local', 451);
    }
}
