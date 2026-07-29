<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Services\AdminDashboardService;
use Throwable;

class DashboardController
{
    private AdminDashboardService $dashboardService;

    public function __construct(?AdminDashboardService $dashboardService = null)
    {
        $this->dashboardService = $dashboardService ?? new AdminDashboardService();
    }

    public function index(): void
    {
        $admin = AdminAuthMiddleware::handle('view_dashboard');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        $dashboard = $this->emptyDashboard();
        $metrics = $dashboard['metrics'];
        $errorMessage = null;

        try {
            $dashboard = $this->dashboardService->getDashboard();
            $metrics = $dashboard['metrics'];
        } catch (Throwable $e) {
            error_log('Dashboard index data load error: ' . $e->getMessage());
            $errorMessage = trans('temporary_error');
        }

        $lang = $this->resolveLanguage();
        require __DIR__ . '/../../../views/admin/dashboard.php';
    }

    private function emptyDashboard(): array
    {
        return [
            'metrics' => [
                'total_employees' => 0,
                'active_employees' => 0,
                'not_started_employees' => 0,
                'in_progress_employees' => 0,
                'completed_employees' => 0,
                'completion_rate' => 0.0,
                'active_assignments' => 0,
                'completed_assignments' => 0,
                'overdue_assignments' => 0,
                'active_attempts' => 0,
                'expired_in_progress_attempts' => 0,
                'finalized_attempts' => 0,
                'timed_out_attempts' => 0,
                'official_results' => 0,
                'passed_results' => 0,
                'failed_results' => 0,
                'pass_rate' => 0.0,
                'average_percentage' => 0.0,
                'highest_percentage' => 0.0,
                'lowest_percentage' => 0.0,
                'security_events_last_7_days' => 0,
            ],
            'test_performance' => [],
            'department_performance' => [],
            'recent_results' => [],
            'expired_attempts' => [],
            'overdue_assignments' => [],
        ];
    }

    private function resolveLanguage(): string
    {
        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;
        return $lang;
    }

    private function render403(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $admin = isset($_SESSION['admin_auth']) && is_array($_SESSION['admin_auth'])
            ? $_SESSION['admin_auth']
            : null;
        $this->resolveLanguage();
        http_response_code(403);
        require __DIR__ . '/../../../views/admin/403.php';
    }
}
