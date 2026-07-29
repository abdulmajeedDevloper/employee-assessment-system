<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AdminReportDownloadTokenService;
use App\Services\AdminReportExportService;
use App\Services\AdminReportService;
use App\Support\Database;
use PDO;
use Tests\TestCase;

class AdminReportExportWorkflowTest extends TestCase
{
    private PDO $pdo;
    private int $adminId;
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Database::getInstance();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'report-export-workflow-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $_SESSION = [];

        $suffix = bin2hex(random_bytes(5));
        $stmt = $this->pdo->prepare("\n            INSERT INTO admins (name, email, username, password_hash, role, is_active, created_at, updated_at)\n            VALUES (:name, :email, :username, :password_hash, 'report_viewer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())\n        ");
        $stmt->execute([
            ':name' => 'Export Test Admin',
            ':email' => "export-{$suffix}@test.local",
            ':username' => "export_{$suffix}",
            ':password_hash' => password_hash('ExportTest!123', PASSWORD_DEFAULT),
        ]);
        $this->adminId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo, $this->adminId)) {
            $this->pdo->prepare("DELETE FROM audit_logs WHERE admin_id = ? AND action IN ('EXPORT_REPORT_XLSX','EXPORT_REPORT_PDF')")->execute([$this->adminId]);
            $this->pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$this->adminId]);
        }
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
        $_SESSION = [];
        parent::tearDown();
    }

    public function testExportWorkflowCreatesOneTimeDownloadAndValueFreeAuditPayload(): void
    {
        $reportService = new StubReportDataService();
        $tokenService = new AdminReportDownloadTokenService($this->directory);
        $service = new StubReportExportService($reportService, $tokenService, $this->pdo, $this->directory);

        $result = $service->createExport([
            'q' => 'Sensitive Employee Name',
            'department_id' => '12',
            'outcome' => 'passed',
        ], 'xlsx', $this->adminId, 'ar');

        $this->assertSame('SUCCESS', $result['code']);
        $this->assertSame(1, $result['row_count']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['token']);

        $download = $service->consumeDownload($result['token'], $this->adminId);
        $this->assertNotNull($download);
        $this->assertSame('xlsx', $download['format']);
        $this->assertFileExists($download['path']);
        $this->assertNull($service->consumeDownload($result['token'], $this->adminId));
        @unlink($download['path']);

        $stmt = $this->pdo->prepare("SELECT new_values FROM audit_logs WHERE admin_id=? AND action='EXPORT_REPORT_XLSX' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$this->adminId]);
        $auditJson = (string)$stmt->fetchColumn();
        $audit = json_decode($auditJson, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['exported_field_names', 'filter_field_names'], array_keys($audit));
        $this->assertContains('q', $audit['filter_field_names']);
        $this->assertContains('department_id', $audit['filter_field_names']);
        $this->assertContains('outcome', $audit['filter_field_names']);
        $this->assertStringNotContainsString('Sensitive Employee Name', $auditJson);
        $this->assertStringNotContainsString('Exported Employee', $auditJson);
        $this->assertStringNotContainsString('.xlsx', $auditJson);
        $this->assertStringNotContainsString((string)$result['token'], $auditJson);
    }

    public function testRowLimitStopsGenerationBeforeWriterRuns(): void
    {
        $reportService = new StubReportDataService(true);
        $service = new StubReportExportService(
            $reportService,
            new AdminReportDownloadTokenService($this->directory),
            $this->pdo,
            $this->directory
        );

        $result = $service->createExport([], 'pdf', $this->adminId, 'en');

        $this->assertSame('ROW_LIMIT_EXCEEDED', $result['code']);
        $this->assertFalse($service->writerCalled);
        $this->assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . 'report_*') ?: []);
    }
}

class StubReportDataService extends AdminReportService
{
    public function __construct(private bool $tooManyRows = false)
    {
    }

    public function getExportData(array $input, int $maxRows): array
    {
        return [
            'filters' => [
                'q' => $input['q'] ?? '',
                'department_id' => isset($input['department_id']) ? (int)$input['department_id'] : null,
                'section_id' => null,
                'test_id' => null,
                'outcome' => $input['outcome'] ?? 'all',
                'attempt_status' => 'all',
                'submitted_from' => null,
                'submitted_until' => null,
                'submitted_from_input' => '',
                'submitted_until_input' => '',
            ],
            'summary' => [
                'total_results' => $this->tooManyRows ? $maxRows + 1 : 1,
                'assessed_employees' => 1,
                'passed_results' => 1,
                'failed_results' => 0,
                'average_percentage' => 90.0,
                'highest_percentage' => 90.0,
                'lowest_percentage' => 90.0,
                'pass_rate' => 100.0,
            ],
            'results' => $this->tooManyRows ? [] : [[
                'employee_name' => 'Exported Employee',
                'employee_number' => '=DANGEROUS',
                'masked_national_id' => '********1234',
                'department_name_ar' => 'الإدارة',
                'department_name_en' => 'Department',
                'section_name_ar' => 'القسم',
                'section_name_en' => 'Section',
                'job_title_name_ar' => 'المسمى',
                'job_title_name_en' => 'Job Title',
                'test_code' => 'T-1',
                'test_name_ar' => 'الاختبار',
                'test_name_en' => 'Assessment',
                'attempt_number' => 1,
                'score' => 9,
                'total_score' => 10,
                'percentage' => 90,
                'passed' => 1,
                'attempt_status' => 'COMPLETED',
                'submitted_at' => '2026-07-28 00:00:00',
            ]],
            'test_breakdown' => [],
            'department_breakdown' => [],
            'departments' => [],
            'sections' => [],
            'tests' => [],
            'too_many_rows' => $this->tooManyRows,
            'max_rows' => $maxRows,
        ];
    }
}

class StubReportExportService extends AdminReportExportService
{
    public bool $writerCalled = false;

    protected function writeSpreadsheet(string $path, array $data, string $language): void
    {
        $this->writerCalled = true;
        file_put_contents($path, 'stub-xlsx');
    }

    protected function writePdf(string $path, array $data, string $language): void
    {
        $this->writerCalled = true;
        file_put_contents($path, 'stub-pdf');
    }
}
