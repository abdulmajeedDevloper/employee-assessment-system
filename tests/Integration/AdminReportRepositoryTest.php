<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\AdminReportRepository;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminReportRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AdminReportRepository $repository;
    private string $prefix;
    private int $adminId;
    private int $departmentId;
    private int $sectionId;
    private int $jobTitleId;
    /** @var int[] */
    private array $employeeIds = [];
    /** @var int[] */
    private array $testIds = [];
    /** @var int[] */
    private array $assignmentIds = [];
    /** @var int[] */
    private array $attemptIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_ENV'] = 'test';
        $_ENV['DB_DATABASE'] = 'employee_assessment_test';
        require_once __DIR__ . '/../../bootstrap/init.php';
        $this->pdo = Database::getInstance();
        $database = (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!str_ends_with($database, '_test')) {
            $this->fail('Unsafe database for report tests: ' . $database);
        }

        $this->prefix = 'RPT-' . bin2hex(random_bytes(4));
        $this->seedReportData();
        $this->repository = new AdminReportRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testOfficialResultDirectoryUsesLatestTerminalAttemptPerAssignment(): void
    {
        $result = $this->repository->searchOfficialResults($this->baseFilters(), 1, 25);

        $this->assertSame(3, $result['total']);
        $this->assertCount(3, $result['items']);
        $assignmentOneRows = array_values(array_filter(
            $result['items'],
            fn(array $row): bool => (int)$row['assignment_id'] === $this->assignmentIds[0]
        ));
        $this->assertCount(1, $assignmentOneRows);
        $this->assertSame(2, (int)$assignmentOneRows[0]['attempt_number']);
        $this->assertSame(1, (int)$assignmentOneRows[0]['passed']);
    }

    public function testSummaryAndOutcomeFiltersUseOfficialResultsOnly(): void
    {
        $summary = $this->repository->getSummary($this->baseFilters());
        $this->assertSame(3, (int)$summary['total_results']);
        $this->assertSame(2, (int)$summary['assessed_employees']);
        $this->assertSame(2, (int)$summary['passed_results']);
        $this->assertSame(1, (int)$summary['failed_results']);
        $this->assertEqualsWithDelta(66.67, (float)$summary['pass_rate'], 0.01);

        $filters = $this->baseFilters();
        $filters['outcome'] = 'passed';
        $passed = $this->repository->searchOfficialResults($filters, 1, 25);
        $this->assertSame(2, $passed['total']);
        foreach ($passed['items'] as $item) {
            $this->assertSame(1, (int)$item['passed']);
        }
    }

    public function testReportRowsExposeMaskedIdentifierOnly(): void
    {
        $result = $this->repository->searchOfficialResults($this->baseFilters(), 1, 25);
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('********1111', $encoded);
        $this->assertStringContainsString('********2222', $encoded);
        $this->assertStringNotContainsString('1234567891', $encoded);
        $this->assertStringNotContainsString('1234567892', $encoded);
    }


    public function testExportRowsAreBoundedAndKeepNationalIdMasked(): void
    {
        $rows = $this->repository->getOfficialResultsForExport($this->baseFilters(), 2);
        $this->assertCount(2, $rows);
        $encoded = json_encode($rows, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('********', $encoded);
        $this->assertStringNotContainsString('1234567891', $encoded);
        $this->assertArrayNotHasKey('email', $rows[0]);
    }

    public function testTestAndDepartmentBreakdownsRespectFilters(): void
    {
        $filters = $this->baseFilters();
        $filters['test_id'] = $this->testIds[0];
        $tests = $this->repository->getTestBreakdown($filters);
        $this->assertCount(1, $tests);
        $this->assertSame(2, (int)$tests[0]['result_count']);

        $departments = $this->repository->getDepartmentBreakdown($filters);
        $this->assertCount(1, $departments);
        $this->assertSame($this->departmentId, (int)$departments[0]['id']);
    }

    private function baseFilters(): array
    {
        return [
            'q' => '',
            'department_id' => null,
            'section_id' => null,
            'test_id' => null,
            'outcome' => 'all',
            'attempt_status' => 'ALL',
            'submitted_from' => null,
            'submitted_until' => null,
        ];
    }

    private function seedReportData(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $hash = password_hash('ReportTest!12345', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO admins (name,email,username,password_hash,role,is_active,created_at,updated_at) VALUES (?,?,?,?,\'super_admin\',1,?,?)');
        $stmt->execute([$this->prefix . ' Admin', strtolower($this->prefix) . '@test.local', strtolower($this->prefix), $hash, $now, $now]);
        $this->adminId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare('INSERT INTO departments (name_ar,name_en,is_active,created_at,updated_at) VALUES (?,?,1,?,?)');
        $stmt->execute([$this->prefix . ' إدارة', $this->prefix . ' Department', $now, $now]);
        $this->departmentId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare('INSERT INTO sections (department_id,name_ar,name_en,is_active,created_at,updated_at) VALUES (?,?,?,1,?,?)');
        $stmt->execute([$this->departmentId, $this->prefix . ' قسم', $this->prefix . ' Section', $now, $now]);
        $this->sectionId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare('INSERT INTO job_titles (name_ar,name_en,is_active,created_at,updated_at) VALUES (?,?,1,?,?)');
        $stmt->execute([$this->prefix . ' مسمى', $this->prefix . ' Job', $now, $now]);
        $this->jobTitleId = (int)$this->pdo->lastInsertId();

        foreach ([1 => '1111', 2 => '2222'] as $index => $last4) {
            $stmt = $this->pdo->prepare('INSERT INTO employees (full_name,national_id_hash,national_id_ciphertext,national_id_iv,national_id_tag,key_version,national_id_last4,employee_number,email,phone,department_id,section_id,job_title_id,preferred_language,is_active,created_at,updated_at) VALUES (?,?,?,?,?,1,?,?,?,?,?,?,?,\'en\',1,?,?)');
            $stmt->execute([
                $this->prefix . ' Employee ' . $index,
                random_bytes(32),
                base64_encode(random_bytes(24)),
                random_bytes(12),
                random_bytes(16),
                $last4,
                $this->prefix . '-EMP-' . $index,
                strtolower($this->prefix) . '.employee' . $index . '@test.local',
                '+96770000000' . $index,
                $this->departmentId,
                $this->sectionId,
                $this->jobTitleId,
                $now,
                $now,
            ]);
            $this->employeeIds[] = (int)$this->pdo->lastInsertId();
        }

        foreach ([1 => 'IQ', 2 => 'EN'] as $order => $suffix) {
            $stmt = $this->pdo->prepare('INSERT INTO tests (code,name_ar,name_en,duration_minutes,question_count,passing_percentage,display_order,randomize_questions,randomize_options,allow_back_navigation,show_result_to_employee,is_active,created_at,updated_at) VALUES (?,?,?,?,?,60,?,1,1,1,0,1,?,?)');
            $stmt->execute([$this->prefix . '-' . $suffix, $this->prefix . ' اختبار ' . $suffix, $this->prefix . ' Test ' . $suffix, 30, 10, $order, $now, $now]);
            $this->testIds[] = (int)$this->pdo->lastInsertId();
        }

        $this->assignmentIds[] = $this->insertAssignment($this->employeeIds[0], $this->testIds[0], 2, $now);
        $this->assignmentIds[] = $this->insertAssignment($this->employeeIds[1], $this->testIds[0], 1, $now);
        $this->assignmentIds[] = $this->insertAssignment($this->employeeIds[0], $this->testIds[1], 1, $now);

        $this->attemptIds[] = $this->insertAttempt($this->assignmentIds[0], $this->employeeIds[0], $this->testIds[0], 1, 45.00, 0, 'COMPLETED', '-2 DAY');
        $this->attemptIds[] = $this->insertAttempt($this->assignmentIds[0], $this->employeeIds[0], $this->testIds[0], 2, 85.00, 1, 'COMPLETED', '-1 DAY');
        $this->attemptIds[] = $this->insertAttempt($this->assignmentIds[1], $this->employeeIds[1], $this->testIds[0], 1, 50.00, 0, 'TIMED_OUT', '-3 HOUR');
        $this->attemptIds[] = $this->insertAttempt($this->assignmentIds[2], $this->employeeIds[0], $this->testIds[1], 1, 90.00, 1, 'COMPLETED', '-1 HOUR');
    }

    private function insertAssignment(int $employeeId, int $testId, int $maxAttempts, string $now): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO test_assignments (employee_id,test_id,max_attempts,status,assigned_by,assigned_at) VALUES (?,?,?,\'COMPLETED\',?,?)');
        $stmt->execute([$employeeId, $testId, $maxAttempts, $this->adminId, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertAttempt(int $assignmentId, int $employeeId, int $testId, int $attemptNumber, float $percentage, int $passed, string $status, string $submittedOffset): int
    {
        $submittedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify($submittedOffset)->format('Y-m-d H:i:s');
        $startedAt = (new \DateTimeImmutable($submittedAt, new \DateTimeZone('UTC')))->modify('-30 minutes')->format('Y-m-d H:i:s');
        $expiresAt = (new \DateTimeImmutable($startedAt, new \DateTimeZone('UTC')))->modify('+30 minutes')->format('Y-m-d H:i:s');
        $reason = $status === 'TIMED_OUT' ? 'AUTO_TIMEOUT' : 'MANUAL_SUBMIT';
        $stmt = $this->pdo->prepare('INSERT INTO test_attempts (employee_id,test_id,assignment_id,attempt_number,started_at,submitted_at,expires_at,duration_seconds,score,total_score,percentage,passed,status,submission_reason,ip_address,user_agent,focus_loss_count,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,10,10,?,?,?,?,\'127.0.0.1\',\'PHPUnit Report\',0,?,?)');
        $stmt->execute([
            $employeeId,
            $testId,
            $assignmentId,
            $attemptNumber,
            $startedAt,
            $submittedAt,
            $expiresAt,
            1800,
            $percentage,
            $passed,
            $status,
            $reason,
            $startedAt,
            $submittedAt,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function cleanup(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        foreach (array_reverse($this->attemptIds) as $id) {
            $this->pdo->prepare('DELETE FROM test_attempts WHERE id=?')->execute([$id]);
        }
        foreach (array_reverse($this->assignmentIds) as $id) {
            $this->pdo->prepare('DELETE FROM test_assignments WHERE id=?')->execute([$id]);
        }
        foreach (array_reverse($this->employeeIds) as $id) {
            $this->pdo->prepare('DELETE FROM employees WHERE id=?')->execute([$id]);
        }
        foreach (array_reverse($this->testIds) as $id) {
            $this->pdo->prepare('DELETE FROM tests WHERE id=?')->execute([$id]);
        }
        foreach ([['sections', $this->sectionId ?? 0], ['departments', $this->departmentId ?? 0], ['job_titles', $this->jobTitleId ?? 0], ['admins', $this->adminId ?? 0]] as [$table, $id]) {
            if ($id > 0) {
                $this->pdo->prepare("DELETE FROM {$table} WHERE id=?")->execute([$id]);
            }
        }
    }
}
