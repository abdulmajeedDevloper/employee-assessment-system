<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Services\AdminTestMonitoringService;
use Throwable;

class TestMonitoringController
{
    private AdminTestMonitoringService $monitoringService;

    public function __construct(?AdminTestMonitoringService $monitoringService = null)
    {
        $this->monitoringService = $monitoringService ?? new AdminTestMonitoringService();
    }

    public function index(): void
    {
        $admin = AdminAuthMiddleware::handle('view_attempts');

        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }

            return;
        }

        $this->setLanguage();

        $results = [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 20,
            'total_pages' => 0,
        ];

        $statistics = [
            'active_attempts' => 0,
            'online_attempts' => 0,
            'stale_attempts' => 0,
            'disconnected_attempts' => 0,
            'expired_attempts' => 0,
            'focus_alert_attempts' => 0,
            'security_alert_attempts' => 0,
        ];

        $tests = [];

        $filters = [
            'q' => '',
            'test_id' => 0,
            'connection' => 'ALL',
            'attention' => 'ALL',
            'page' => 1,
            'per_page' => 20,
        ];

        $errorMessage = null;

        try {
            $data = $this->monitoringService->getMonitoringData($_GET);

            $results = $data['results'];
            $statistics = $data['statistics'];
            $tests = $data['tests'];
            $filters = $data['filters'];
        } catch (Throwable $e) {
            error_log('TestMonitoringController index error: ' . $e->getMessage());
            $errorMessage = trans('temporary_attempt_error');
        }

        require __DIR__ . '/../../../views/admin/monitoring/index.php';
    }

    private function setLanguage(): void
    {
        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';

        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }

        $_SESSION['lang'] = $lang;
    }

    private function render403(): void
    {
        http_response_code(403);
        require __DIR__ . '/../../../views/admin/403.php';
    }
}
