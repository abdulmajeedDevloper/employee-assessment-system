<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminAttemptRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class AdminAttemptDirectoryService
{
    public function __construct(private ?AdminAttemptRepository $repository = null)
    {
        $this->repository ??= new AdminAttemptRepository();
    }

    public function getDirectory(array $input): array
    {
        $q = is_string($input['q'] ?? null) ? trim((string)$input['q']) : '';
        if (mb_strlen($q) > 150) {
            $q = mb_substr($q, 0, 150);
        }

        $status = strtoupper(is_string($input['status'] ?? null) ? trim((string)$input['status']) : 'ALL');
        if (!in_array($status, ['ALL', 'IN_PROGRESS', 'COMPLETED', 'TIMED_OUT', 'CANCELLED'], true)) {
            $status = 'ALL';
        }

        $outcome = strtolower(is_string($input['outcome'] ?? null) ? trim((string)$input['outcome']) : 'all');
        if (!in_array($outcome, ['all', 'passed', 'failed', 'ungraded'], true)) {
            $outcome = 'all';
        }

        $attention = strtolower(is_string($input['attention'] ?? null) ? trim((string)$input['attention']) : 'all');
        if (!in_array($attention, ['all', 'expired', 'security', 'focus'], true)) {
            $attention = 'all';
        }

        $startedFromInput = $this->sanitizeDateInput($input['started_from'] ?? null);
        $startedUntilInput = $this->sanitizeDateInput($input['started_until'] ?? null);
        $startedFrom = $this->localBoundaryToUtc($startedFromInput, false);
        $startedUntil = $this->localBoundaryToUtc($startedUntilInput, true);

        $filters = [
            'q' => $q,
            'status' => $status,
            'outcome' => $outcome,
            'attention' => $attention,
            'employee_id' => $this->parsePositiveInt($input['employee_id'] ?? null),
            'test_id' => $this->parsePositiveInt($input['test_id'] ?? null),
            'assignment_id' => $this->parsePositiveInt($input['assignment_id'] ?? null),
            'started_from' => $startedFrom,
            'started_until' => $startedUntil,
        ];
        $page = $this->parsePositiveInt($input['page'] ?? null) ?? 1;

        return [
            'filters' => array_merge($filters, [
                'status' => $status === 'ALL' ? 'all' : $status,
                'started_from_input' => $startedFromInput,
                'started_until_input' => $startedUntilInput,
                'page' => $page,
            ]),
            'results' => $this->repository->search($filters, $page, 20),
            'statistics' => $this->repository->getStatistics($filters),
            'employees' => $this->repository->getEmployeesForFilter(),
            'tests' => $this->repository->getTestsForFilter(),
        ];
    }

    public function getDetails(mixed $rawId): array
    {
        $id = $this->parsePositiveInt($rawId);
        if ($id === null) {
            return ['success' => false, 'code' => 'NOT_FOUND'];
        }
        $attempt = $this->repository->findSafeById($id);
        if ($attempt === null) {
            return ['success' => false, 'code' => 'NOT_FOUND'];
        }

        return [
            'success' => true,
            'attempt' => $attempt,
            'answers' => $this->repository->getAnswers($id),
            'security_events' => $this->repository->getSecurityEvents($id),
            'sessions' => $this->repository->getSessions($id),
            'audit_history' => $this->repository->getAuditHistory($id),
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
