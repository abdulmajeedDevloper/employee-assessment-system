<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminReportRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class AdminReportService
{
    public function __construct(private ?AdminReportRepository $repository = null)
    {
        $this->repository ??= new AdminReportRepository();
    }

    public function getReport(array $input): array
    {
        $filters = $this->normalizeFilters($input);
        $page = $this->parsePositiveInt($input['page'] ?? null) ?? 1;

        $summary = $this->normalizeSummary($this->repository->getSummary($filters));

        return [
            'filters' => array_merge($filters, [
                'attempt_status' => $filters['attempt_status'] === 'ALL' ? 'all' : $filters['attempt_status'],
                'submitted_from_input' => $this->sanitizeDateInput($input['submitted_from'] ?? null),
                'submitted_until_input' => $this->sanitizeDateInput($input['submitted_until'] ?? null),
                'page' => $page,
            ]),
            'summary' => $summary,
            'results' => $this->repository->searchOfficialResults($filters, $page, 25),
            'test_breakdown' => $this->repository->getTestBreakdown($filters),
            'department_breakdown' => $this->repository->getDepartmentBreakdown($filters),
            'departments' => $this->repository->getDepartmentsForFilter(),
            'sections' => $this->repository->getSectionsForFilter(),
            'tests' => $this->repository->getTestsForFilter(),
        ];
    }


    /**
     * Build a complete, bounded report data set for Excel/PDF exports.
     */
    public function getExportData(array $input, int $maxRows): array
    {
        $maxRows = max(1, min(20000, $maxRows));
        $filters = $this->normalizeFilters($input);
        $summary = $this->normalizeSummary($this->repository->getSummary($filters));
        $tooManyRows = (int)$summary['total_results'] > $maxRows;

        return [
            'filters' => array_merge($filters, [
                'attempt_status' => $filters['attempt_status'] === 'ALL' ? 'all' : $filters['attempt_status'],
                'submitted_from_input' => $this->sanitizeDateInput($input['submitted_from'] ?? null),
                'submitted_until_input' => $this->sanitizeDateInput($input['submitted_until'] ?? null),
            ]),
            'summary' => $summary,
            'results' => $tooManyRows ? [] : $this->repository->getOfficialResultsForExport($filters, $maxRows),
            'test_breakdown' => $this->repository->getTestBreakdown($filters),
            'department_breakdown' => $this->repository->getDepartmentBreakdown($filters),
            'departments' => $this->repository->getDepartmentsForFilter(),
            'sections' => $this->repository->getSectionsForFilter(),
            'tests' => $this->repository->getTestsForFilter(),
            'too_many_rows' => $tooManyRows,
            'max_rows' => $maxRows,
        ];
    }

    public function normalizeFilters(array $input): array
    {
        $q = is_string($input['q'] ?? null) ? trim((string)$input['q']) : '';
        if (mb_strlen($q) > 150) {
            $q = mb_substr($q, 0, 150);
        }

        $outcome = strtolower(is_string($input['outcome'] ?? null) ? trim((string)$input['outcome']) : 'all');
        if (!in_array($outcome, ['all', 'passed', 'failed'], true)) {
            $outcome = 'all';
        }

        $attemptStatus = strtoupper(is_string($input['attempt_status'] ?? null) ? trim((string)$input['attempt_status']) : 'ALL');
        if (!in_array($attemptStatus, ['ALL', 'COMPLETED', 'TIMED_OUT'], true)) {
            $attemptStatus = 'ALL';
        }

        $fromInput = $this->sanitizeDateInput($input['submitted_from'] ?? null);
        $untilInput = $this->sanitizeDateInput($input['submitted_until'] ?? null);

        return [
            'q' => $q,
            'department_id' => $this->parsePositiveInt($input['department_id'] ?? null),
            'section_id' => $this->parsePositiveInt($input['section_id'] ?? null),
            'test_id' => $this->parsePositiveInt($input['test_id'] ?? null),
            'outcome' => $outcome,
            'attempt_status' => $attemptStatus,
            'submitted_from' => $this->localBoundaryToUtc($fromInput, false),
            'submitted_until' => $this->localBoundaryToUtc($untilInput, true),
        ];
    }

    public function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', trim($value))) {
            return null;
        }
        $parsed = filter_var(trim($value), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $parsed === false ? null : (int)$parsed;
    }


    private function normalizeSummary(array $summary): array
    {
        return [
            'total_results' => (int)($summary['total_results'] ?? 0),
            'assessed_employees' => (int)($summary['assessed_employees'] ?? 0),
            'passed_results' => (int)($summary['passed_results'] ?? 0),
            'failed_results' => (int)($summary['failed_results'] ?? 0),
            'average_percentage' => round((float)($summary['average_percentage'] ?? 0), 2),
            'highest_percentage' => round((float)($summary['highest_percentage'] ?? 0), 2),
            'lowest_percentage' => round((float)($summary['lowest_percentage'] ?? 0), 2),
            'pass_rate' => round((float)($summary['pass_rate'] ?? 0), 2),
        ];
    }

    private function sanitizeDateInput(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) ? $value : '';
    }

    private function localBoundaryToUtc(string $date, bool $endOfDay): ?string
    {
        if ($date === '') {
            return null;
        }

        try {
            $timezone = new DateTimeZone((string)($_ENV['APP_TIMEZONE'] ?? 'Asia/Riyadh'));
            $time = $endOfDay ? '23:59:59' : '00:00:00';
            $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $time, $timezone);
            if ($local === false || $local->format('Y-m-d') !== $date) {
                return null;
            }
            return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
