<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminDashboardRepository;
use PDO;
use Throwable;

class AdminDashboardService
{
    private AdminDashboardRepository $repository;

    public function __construct(AdminDashboardRepository|PDO|null $dependency = null)
    {
        $this->repository = $dependency instanceof AdminDashboardRepository
            ? $dependency
            : new AdminDashboardRepository($dependency instanceof PDO ? $dependency : null);
    }

    public function getDashboard(): array
    {
        try {
            return [
                'metrics' => $this->loadMetrics(),
                'test_performance' => $this->repository->getTestPerformance(),
                'department_performance' => $this->repository->getDepartmentPerformance(),
                'recent_results' => $this->repository->getRecentResults(),
                'expired_attempts' => $this->repository->getExpiredAttempts(),
                'overdue_assignments' => $this->repository->getOverdueAssignments(),
            ];
        } catch (Throwable $e) {
            error_log('AdminDashboardService error: ' . $e->getMessage());
            throw new \RuntimeException('Database error retrieving admin dashboard data.', 0, $e);
        }
    }

    /**
     * Backward-compatible summary used by older tests and callers.
     */
    public function getSummary(): array
    {
        try {
            return $this->loadMetrics();
        } catch (Throwable $e) {
            error_log('AdminDashboardService summary error: ' . $e->getMessage());
            throw new \RuntimeException('Database error retrieving admin dashboard metrics.', 0, $e);
        }
    }

    private function loadMetrics(): array
    {
        $employee = $this->repository->getEmployeeMetrics();
        $progress = $this->repository->getProgressMetrics();
        $assignments = $this->repository->getAssignmentMetrics();
        $attempts = $this->repository->getAttemptMetrics();
        $results = $this->repository->getOfficialResultMetrics();

        $trackedEmployees = (int)($progress['tracked_employees'] ?? 0);
        $completedEmployees = (int)($progress['completed_employees'] ?? 0);
        $officialResults = (int)($results['official_results'] ?? 0);
        $passedResults = (int)($results['passed_results'] ?? 0);

        return $this->normalizeMetrics(array_merge($employee, $progress, $assignments, $attempts, $results, [
            'completion_rate' => $trackedEmployees > 0
                ? round(($completedEmployees * 100) / $trackedEmployees, 2)
                : 0.0,
            'pass_rate' => $officialResults > 0
                ? round(($passedResults * 100) / $officialResults, 2)
                : 0.0,
            'security_events_last_7_days' => $this->repository->getRecentSecurityEventCount(7),
        ]));
    }

    private function normalizeMetrics(array $metrics): array
    {
        $integerKeys = [
            'total_employees', 'active_employees', 'inactive_employees', 'archived_employees',
            'tracked_employees', 'not_started_employees', 'in_progress_employees', 'completed_employees',
            'total_assignments', 'active_assignments', 'completed_assignments', 'overdue_assignments',
            'total_attempts', 'active_attempts', 'expired_in_progress_attempts', 'completed_attempts',
            'timed_out_attempts', 'cancelled_attempts', 'finalized_attempts', 'official_results',
            'passed_results', 'failed_results', 'security_events_last_7_days',
        ];
        foreach ($integerKeys as $key) {
            $metrics[$key] = (int)($metrics[$key] ?? 0);
        }

        foreach (['average_percentage', 'highest_percentage', 'lowest_percentage', 'completion_rate', 'pass_rate'] as $key) {
            $metrics[$key] = round((float)($metrics[$key] ?? 0), 2);
        }

        return $metrics;
    }
}
