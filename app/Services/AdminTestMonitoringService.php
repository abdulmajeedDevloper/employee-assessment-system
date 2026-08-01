<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminTestMonitoringRepository;

class AdminTestMonitoringService
{
    private AdminTestMonitoringRepository $repository;

    public function __construct(?AdminTestMonitoringRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminTestMonitoringRepository();
    }

    public function getMonitoringData(array $input): array
    {
        $page = $this->positiveInt($input['page'] ?? null, 1);
        $perPage = $this->positiveInt($input['per_page'] ?? null, 20);
        $perPage = max(5, min(100, $perPage));

        $filters = [
            'q' => $this->limitedString($input['q'] ?? '', 120),
            'test_id' => $this->positiveInt($input['test_id'] ?? null, 0),
            'connection' => $this->allowedValue(
                $input['connection'] ?? 'ALL',
                ['ALL', 'ONLINE', 'STALE', 'DISCONNECTED', 'EXPIRED'],
                'ALL'
            ),
            'attention' => $this->allowedValue(
                $input['attention'] ?? 'ALL',
                ['ALL', 'FOCUS', 'SECURITY'],
                'ALL'
            ),
        ];

        $results = $this->repository->searchActiveAttempts(
            $filters,
            $page,
            $perPage
        );

        return [
            'results' => $results,
            'statistics' => $this->repository->getStatistics($filters),
            'tests' => $this->repository->getTestsWithActiveAttempts(),
            'filters' => $filters + [
                'page' => (int)($results['page'] ?? $page),
                'per_page' => $perPage,
            ],
        ];
    }

    private function positiveInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : $default;
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return $default;
        }

        $number = (int)$value;

        return $number >= 0 ? $number : $default;
    }

    private function limitedString(mixed $value, int $maxLength): string
    {
        if (!is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, $maxLength);
    }

    private function allowedValue(
        mixed $value,
        array $allowed,
        string $default
    ): string {
        if (!is_string($value)) {
            return $default;
        }

        $normalized = strtoupper(trim($value));

        return in_array($normalized, $allowed, true)
            ? $normalized
            : $default;
    }
}
