<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\AdminAssignmentRepository;
use App\Services\AdminAssignmentManagementService;
use App\Support\Database;
use App\Validators\AdminAssignmentFormValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminAssignmentManagementTest extends TestCase
{
    private PDO $pdo;
    private AdminAssignmentRepository $repository;
    private AdminAssignmentManagementService $service;
    private int $adminId;
    private int $departmentId;
    private int $sectionId;
    private int $jobTitleId;
    private int $employeeId;
    private int $testId;
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_ENV'] = 'test';
        $_ENV['DB_DATABASE'] = 'employee_assessment_test';
        require_once __DIR__ . '/../../bootstrap/init.php';
        $this->pdo = Database::getInstance();
        $database = (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!str_ends_with($database, '_test')) {
            $this->fail('Unsafe database for assignment tests: ' . $database);
        }

        $_SESSION = ['lang' => 'en'];
        $this->prefix = 'UNIT-ASGN-' . bin2hex(random_bytes(4));
        $this->seedReferences();
        $this->repository = new AdminAssignmentRepository($this->pdo);
        $this->service = new AdminAssignmentManagementService(
            $this->repository,
            new AdminAssignmentFormValidator(),
            $this->pdo
        );
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testCreatePersistsPendingAssignmentAndFieldNameOnlyAudit(): void
    {
        $result = $this->service->createAssignment($this->validInput(), $this->adminId);
        $this->assertSame('SUCCESS', $result['code']);

        $assignment = $this->repository->findSafeById((int)$result['assignment_id']);
        $this->assertNotNull($assignment);
        $this->assertSame('PENDING', $assignment['status']);
        $this->assertSame(2, (int)$assignment['max_attempts']);

        $audit = $this->fetchAudit('CREATE_ASSIGNMENT', (int)$result['assignment_id']);
        $payload = json_decode((string)$audit['new_values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['created_field_names'], array_keys($payload));
        $this->assertContains('employee_id', $payload['created_field_names']);
        $this->assertNull($audit['old_values']);
        $this->assertStringNotContainsString((string)$this->employeeId, (string)$audit['new_values']);
    }

    public function testDuplicateEmployeeAssessmentAssignmentIsRejected(): void
    {
        $first = $this->service->createAssignment($this->validInput(), $this->adminId);
        $this->assertSame('SUCCESS', $first['code']);
        $second = $this->service->createAssignment($this->validInput(), $this->adminId);
        $this->assertSame('DUPLICATE_ASSIGNMENT', $second['code']);
    }

    public function testPendingAssignmentUpdateUsesOneTimeConcurrencyToken(): void
    {
        $created = $this->service->createAssignment($this->validInput(), $this->adminId);
        $assignmentId = (int)$created['assignment_id'];
        $assignment = $this->repository->findSafeById($assignmentId);
        $token = $this->service->issueEditToken($this->adminId, $assignmentId, $assignment);

        $input = $this->validInput();
        $input['max_attempts'] = '4';
        $updated = $this->service->updateAssignment($assignmentId, $token, $input, $this->adminId);
        $this->assertSame('SUCCESS', $updated['code']);
        $this->assertSame(4, (int)$this->repository->findSafeById($assignmentId)['max_attempts']);

        $audit = $this->fetchAudit('UPDATE_ASSIGNMENT', $assignmentId);
        $oldPayload = json_decode((string)$audit['old_values'], true, 512, JSON_THROW_ON_ERROR);
        $newPayload = json_decode((string)$audit['new_values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['changed_field_names'], array_keys($oldPayload));
        $this->assertSame($oldPayload, $newPayload);
        $this->assertSame(['max_attempts'], $oldPayload['changed_field_names']);
        $this->assertStringNotContainsString('4', (string)$audit['new_values']);

        $replay = $this->service->updateAssignment($assignmentId, $token, $input, $this->adminId);
        $this->assertSame('CONFLICT', $replay['code']);
    }

    public function testNonPendingAssignmentCannotBeEdited(): void
    {
        $created = $this->service->createAssignment($this->validInput(), $this->adminId);
        $assignmentId = (int)$created['assignment_id'];
        $this->pdo->prepare("UPDATE test_assignments SET status='IN_PROGRESS' WHERE id=:id")
            ->execute([':id' => $assignmentId]);
        $assignment = $this->repository->findSafeById($assignmentId);
        $token = $this->service->issueEditToken($this->adminId, $assignmentId, $assignment);

        $input = $this->validInput();
        $input['max_attempts'] = '3';
        $result = $this->service->updateAssignment($assignmentId, $token, $input, $this->adminId);
        $this->assertSame('STATUS_LOCKED', $result['code']);
    }

    private function validInput(): array
    {
        $from = (new \DateTimeImmutable('+2 hours', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Asia/Riyadh'))->format('Y-m-d\\TH:i');
        $until = (new \DateTimeImmutable('+1 day', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Asia/Riyadh'))->format('Y-m-d\\TH:i');
        return [
            'employee_id' => (string)$this->employeeId,
            'test_id' => (string)$this->testId,
            'max_attempts' => '2',
            'available_from' => $from,
            'available_until' => $until,
        ];
    }

    private function seedReferences(): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO admins (name,email,username,password_hash,role,is_active,created_at,updated_at) VALUES (:name,:email,:username,:hash,'super_admin',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([
            ':name' => $this->prefix . ' Admin',
            ':email' => strtolower($this->prefix) . '@test.local',
            ':username' => strtolower($this->prefix),
            ':hash' => password_hash('AssignmentTest123!', PASSWORD_BCRYPT),
        ]);
        $this->adminId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("INSERT INTO departments (name_ar,name_en,is_active,created_at,updated_at) VALUES (:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':name' => $this->prefix]);
        $this->departmentId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("INSERT INTO sections (department_id,name_ar,name_en,is_active,created_at,updated_at) VALUES (:department_id,:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':department_id' => $this->departmentId, ':name' => $this->prefix]);
        $this->sectionId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("INSERT INTO job_titles (name_ar,name_en,is_active,created_at,updated_at) VALUES (:name,:name,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':name' => $this->prefix]);
        $this->jobTitleId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("
            INSERT INTO employees (
                full_name,national_id_hash,national_id_ciphertext,national_id_iv,national_id_tag,key_version,
                national_id_last4,employee_number,email,phone,department_id,section_id,job_title_id,
                preferred_language,is_active,profile_completed_at,created_at,updated_at,archived_at
            ) VALUES (
                :full_name,:hash,:cipher,:iv,:tag,1,'0001',:employee_number,:email,'+966500000001',
                :department_id,:section_id,:job_title_id,'en',1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),NULL
            )
        ");
        $stmt->execute([
            ':full_name' => $this->prefix . ' Employee',
            ':hash' => random_bytes(32),
            ':cipher' => base64_encode(random_bytes(32)),
            ':iv' => random_bytes(12),
            ':tag' => random_bytes(16),
            ':employee_number' => $this->prefix,
            ':email' => strtolower($this->prefix) . '-employee@test.local',
            ':department_id' => $this->departmentId,
            ':section_id' => $this->sectionId,
            ':job_title_id' => $this->jobTitleId,
        ]);
        $this->employeeId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("
            INSERT INTO tests (
                code,name_ar,name_en,duration_minutes,question_count,passing_percentage,display_order,
                randomize_questions,randomize_options,allow_back_navigation,show_result_to_employee,
                is_active,available_from,available_until,created_at,updated_at
            ) VALUES (
                :code,:name,:name,30,1,60,1,1,1,1,0,1,NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([':code' => $this->prefix, ':name' => $this->prefix . ' Test']);
        $this->testId = (int)$this->pdo->lastInsertId();
    }

    private function fetchAudit(string $action, int $entityId): array
    {
        $stmt = $this->pdo->prepare("SELECT old_values,new_values FROM audit_logs WHERE action=:action AND entity_type='TEST_ASSIGNMENT' AND entity_id=:entity_id ORDER BY id DESC LIMIT 1");
        $stmt->execute([':action' => $action, ':entity_id' => $entityId]);
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
        $prefix = $this->pdo->quote($this->prefix . '%');
        $this->pdo->exec("DELETE FROM audit_logs WHERE entity_type='TEST_ASSIGNMENT' AND entity_id IN (SELECT id FROM test_assignments WHERE employee_id IN (SELECT id FROM employees WHERE employee_number LIKE {$prefix}))");
        $this->pdo->exec("DELETE FROM test_assignments WHERE employee_id IN (SELECT id FROM employees WHERE employee_number LIKE {$prefix})");
        $this->pdo->exec("DELETE FROM employees WHERE employee_number LIKE {$prefix}");
        $this->pdo->exec("DELETE FROM tests WHERE code LIKE {$prefix}");
        $this->pdo->exec("DELETE FROM sections WHERE department_id IN (SELECT id FROM departments WHERE name_en LIKE {$prefix})");
        $this->pdo->exec("DELETE FROM departments WHERE name_en LIKE {$prefix}");
        $this->pdo->exec("DELETE FROM job_titles WHERE name_en LIKE {$prefix}");
        $this->pdo->exec("DELETE FROM admins WHERE username LIKE " . $this->pdo->quote(strtolower($this->prefix) . '%'));
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
