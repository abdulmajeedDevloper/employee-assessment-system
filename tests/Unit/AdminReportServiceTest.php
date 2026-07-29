<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AdminReportRepository;
use App\Services\AdminReportService;
use PHPUnit\Framework\TestCase;

class AdminReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../vendor/autoload.php';
        $_ENV['APP_TIMEZONE'] = 'Asia/Aden';
    }

    public function testNormalizeFiltersWhitelistsValuesAndConvertsLocalDayToUtc(): void
    {
        $service = new AdminReportService(new FakeAdminReportRepository());
        $filters = $service->normalizeFilters([
            'q' => '  Employee  ',
            'department_id' => '7',
            'section_id' => ['9'],
            'test_id' => '-2',
            'outcome' => 'PASSED',
            'attempt_status' => 'timed_out',
            'submitted_from' => '2026-07-01',
            'submitted_until' => '2026-07-01',
        ]);

        $this->assertSame('Employee', $filters['q']);
        $this->assertSame(7, $filters['department_id']);
        $this->assertNull($filters['section_id']);
        $this->assertNull($filters['test_id']);
        $this->assertSame('passed', $filters['outcome']);
        $this->assertSame('TIMED_OUT', $filters['attempt_status']);
        $this->assertSame('2026-06-30 21:00:00', $filters['submitted_from']);
        $this->assertSame('2026-07-01 20:59:59', $filters['submitted_until']);
    }

    public function testInvalidEnumsAndDatesFallBackSafely(): void
    {
        $service = new AdminReportService(new FakeAdminReportRepository());
        $filters = $service->normalizeFilters([
            'outcome' => 'anything',
            'attempt_status' => 'CANCELLED',
            'submitted_from' => '2026-02-31',
            'submitted_until' => ['2026-07-01'],
        ]);

        $this->assertSame('all', $filters['outcome']);
        $this->assertSame('ALL', $filters['attempt_status']);
        $this->assertNull($filters['submitted_from']);
        $this->assertNull($filters['submitted_until']);
    }

    public function testGetReportReturnsNormalizedSummaryAndPassesFiltersToRepository(): void
    {
        $repository = new FakeAdminReportRepository();
        $service = new AdminReportService($repository);
        $report = $service->getReport([
            'q' => 'Analyst',
            'outcome' => 'failed',
            'page' => '2',
        ]);

        $this->assertSame('failed', $repository->lastFilters['outcome']);
        $this->assertSame(2, $repository->lastPage);
        $this->assertSame(3, $report['summary']['total_results']);
        $this->assertSame(66.67, $report['summary']['pass_rate']);
        $this->assertSame(25, $report['results']['per_page']);
        $this->assertSame('all', $report['filters']['attempt_status']);
    }

    public function testGetExportDataEnforcesRowLimitBeforeLoadingRows(): void
    {
        $repository = new FakeAdminReportRepository();
        $repository->summaryTotal = 3;
        $service = new AdminReportService($repository);

        $report = $service->getExportData(['outcome' => 'all'], 2);

        $this->assertTrue($report['too_many_rows']);
        $this->assertSame(2, $report['max_rows']);
        $this->assertSame(0, $repository->exportCalls);
        $this->assertSame([], $report['results']);
    }

    public function testGetExportDataLoadsBoundedOfficialRows(): void
    {
        $repository = new FakeAdminReportRepository();
        $repository->summaryTotal = 1;
        $repository->exportRows = [['attempt_id' => 9]];
        $service = new AdminReportService($repository);

        $report = $service->getExportData(['attempt_status' => 'COMPLETED'], 10);

        $this->assertFalse($report['too_many_rows']);
        $this->assertSame([['attempt_id' => 9]], $report['results']);
        $this->assertSame(1, $repository->exportCalls);
        $this->assertSame('COMPLETED', $repository->lastFilters['attempt_status']);
    }
}

class FakeAdminReportRepository extends AdminReportRepository
{
    public array $lastFilters = [];
    public int $lastPage = 0;
    public int $summaryTotal = 3;
    public int $exportCalls = 0;
    public array $exportRows = [];

    public function __construct()
    {
    }

    public function searchOfficialResults(array $filters, int $page = 1, int $perPage = 25): array
    {
        $this->lastFilters = $filters;
        $this->lastPage = $page;
        return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
    }


    public function getOfficialResultsForExport(array $filters, int $limit): array
    {
        $this->lastFilters = $filters;
        $this->exportCalls++;
        return array_slice($this->exportRows, 0, $limit);
    }

    public function getSummary(array $filters): array
    {
        $this->lastFilters = $filters;
        return [
            'total_results' => (string)$this->summaryTotal,
            'assessed_employees' => '2',
            'passed_results' => '2',
            'failed_results' => '1',
            'average_percentage' => '75.125',
            'highest_percentage' => '95.5',
            'lowest_percentage' => '44.25',
            'pass_rate' => '66.666',
        ];
    }

    public function getTestBreakdown(array $filters): array
    {
        return [];
    }

    public function getDepartmentBreakdown(array $filters): array
    {
        return [];
    }

    public function getDepartmentsForFilter(): array
    {
        return [];
    }

    public function getSectionsForFilter(): array
    {
        return [];
    }

    public function getTestsForFilter(): array
    {
        return [];
    }
}
