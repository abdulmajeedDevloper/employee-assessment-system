<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\AdminReportExportService;
use App\Services\AdminReportService;
use Throwable;

class ReportController
{
    private AdminReportService $reportService;
    private AdminReportExportService $exportService;

    public function __construct(
        ?AdminReportService $reportService = null,
        ?AdminReportExportService $exportService = null
    ) {
        $this->reportService = $reportService ?? new AdminReportService();
        $this->exportService = $exportService ?? new AdminReportExportService($this->reportService);
    }

    public function index(): void
    {
        $admin = AdminAuthMiddleware::handle('view_reports');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        $lang = $this->resolveLanguage();
        $errorMessage = null;
        $report = $this->emptyReport();

        try {
            $report = $this->reportService->getReport($_GET);
        } catch (Throwable $e) {
            error_log('Report index data load error: ' . $e->getMessage());
            $errorMessage = trans('temporary_report_error');
        }

        extract($report, EXTR_SKIP);
        require __DIR__ . '/../../../views/admin/reports/index.php';
    }

    public function export(): void
    {
        $admin = AdminAuthMiddleware::handle('export_reports');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        if (!CsrfMiddleware::verify()) {
            $this->render403();
            return;
        }

        $lang = $this->resolveLanguage();
        $format = is_string($_POST['format'] ?? null)
            ? trim((string)$_POST['format'])
            : '';

        try {
            $result = $this->exportService->createExport(
                $_POST,
                $format,
                (int)$admin['id'],
                $lang
            );
        } catch (Throwable $e) {
            error_log('Report export creation error: ' . $e->getMessage());
            $_SESSION['flash_error'] = trans('report_export_failed');
            $this->redirectToReports($_POST);
            return;
        }

        $code = (string)($result['code'] ?? 'INTERNAL_ERROR');

        if ($code === 'SUCCESS') {
            $token = trim((string)($result['token'] ?? ''));

            if ($token === '') {
                error_log('Report export returned SUCCESS without a download token.');
                $_SESSION['flash_error'] = trans('report_export_failed');
                $this->redirectToReports($_POST);
                return;
            }

            /*
             * Stream the generated file in the same request instead of redirecting
             * to a one-time download URL. This prevents browser download managers
             * or duplicate GET requests from consuming the temporary token first
             * and causing a false 404 response.
             */
            $download = $this->exportService->consumeDownload(
                $token,
                (int)$admin['id']
            );

            if ($download === null) {
                error_log('Report export token could not be consumed immediately: ' . $token);
                $_SESSION['flash_error'] = trans('report_export_failed');
                $this->redirectToReports($_POST);
                return;
            }

            $this->streamDownload($download);
            return;
        }

        $_SESSION['flash_error'] = match ($code) {
            'NO_DATA' => trans('report_export_no_data'),
            'ROW_LIMIT_EXCEEDED' => trans('report_export_row_limit', [
                'count' => (string)(int)($result['max_rows'] ?? 0),
            ]),
            'UNSUPPORTED_FORMAT' => trans('report_export_invalid_format'),
            default => trans('report_export_failed'),
        };

        $this->redirectToReports($_POST);
    }

    public function download(): void
    {
        $admin = AdminAuthMiddleware::handle('export_reports');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        $this->resolveLanguage();

        $token = is_string($_GET['token'] ?? null)
            ? trim((string)$_GET['token'])
            : '';

        $download = $this->exportService->consumeDownload(
            $token,
            (int)$admin['id']
        );

        if ($download === null) {
            $this->renderDownloadUnavailable();
            return;
        }

        $this->streamDownload($download);
    }

    /**
     * @param array<string, mixed> $download
     */
    private function streamDownload(array $download): void
    {
        $path = (string)($download['path'] ?? '');
        $filename = (string)($download['download_filename'] ?? 'report');
        $mimeType = (string)($download['mime_type'] ?? 'application/octet-stream');
        $size = (int)($download['size'] ?? 0);

        if (
            $path === ''
            || !is_file($path)
            || !is_readable($path)
        ) {
            error_log('Report download file is missing or unreadable: ' . $path);
            $_SESSION['flash_error'] = trans('report_export_failed');
            $this->redirectToReports([]);
            return;
        }

        $actualSize = filesize($path);
        if ($actualSize !== false) {
            $size = (int)$actualSize;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $size);
        header(
            'Content-Disposition: attachment; filename="'
            . addcslashes($filename, '"\\')
            . '"; filename*=UTF-8\'\''
            . rawurlencode($filename)
        );
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Download-Options: noopen');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Content-Security-Policy: sandbox');
        header('Accept-Ranges: none');

        try {
            $streamed = readfile($path);
            if ($streamed === false) {
                error_log('Report download read failure: ' . $path);
            }
        } finally {
            @unlink($path);
        }

        if (PHP_SAPI !== 'cli') {
            exit(0);
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    private function redirectToReports(array $input): void
    {
        header('Location: ' . $this->reportRedirectUrl($input), true, 303);

        if (PHP_SAPI !== 'cli') {
            exit(0);
        }
    }

    private function emptyReport(): array
    {
        return [
            'filters' => [
                'q' => '',
                'department_id' => null,
                'section_id' => null,
                'test_id' => null,
                'outcome' => 'all',
                'attempt_status' => 'all',
                'submitted_from_input' => '',
                'submitted_until_input' => '',
                'page' => 1,
            ],
            'summary' => [
                'total_results' => 0,
                'assessed_employees' => 0,
                'passed_results' => 0,
                'failed_results' => 0,
                'average_percentage' => 0.0,
                'highest_percentage' => 0.0,
                'lowest_percentage' => 0.0,
                'pass_rate' => 0.0,
            ],
            'results' => [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 25,
                'total_pages' => 0,
            ],
            'test_breakdown' => [],
            'department_breakdown' => [],
            'departments' => [],
            'sections' => [],
            'tests' => [],
        ];
    }

    private function resolveLanguage(): string
    {
        $lang = $_GET['lang'] ?? $_POST['lang'] ?? $_SESSION['lang'] ?? 'ar';

        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }

        $_SESSION['lang'] = $lang;
        return $lang;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function reportRedirectUrl(array $input): string
    {
        $query = [];

        foreach (
            [
                'q',
                'department_id',
                'section_id',
                'test_id',
                'outcome',
                'attempt_status',
                'submitted_from',
                'submitted_until',
                'lang',
            ] as $key
        ) {
            if (
                isset($input[$key])
                && is_scalar($input[$key])
                && trim((string)$input[$key]) !== ''
            ) {
                $query[$key] = trim((string)$input[$key]);
            }
        }

        return '/admin/reports'
            . ($query === [] ? '' : '?' . http_build_query($query));
    }

    private function render403(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $admin = isset($_SESSION['admin_auth'])
            && is_array($_SESSION['admin_auth'])
            ? $_SESSION['admin_auth']
            : null;

        $this->resolveLanguage();
        http_response_code(403);
        require __DIR__ . '/../../../views/admin/403.php';
    }

    private function renderDownloadUnavailable(): void
    {
        http_response_code(404);
        $pageTitle = trans('report_download_unavailable');
        $isAuthenticatedLayout = true;
        $admin = $_SESSION['admin_auth'] ?? null;

        ob_start();
        ?>
        <div class="admin-welcome-card" style="border-color:rgba(239,68,68,.4)">
            <h1 class="admin-welcome-title" style="color:#f87171">
                <?= e(trans('report_download_unavailable')) ?>
            </h1>
            <p style="color:#94a3b8;margin:.5rem 0 1.5rem">
                <?= e(trans('report_download_unavailable_help')) ?>
            </p>
            <a href="/admin/reports" class="btn btn-secondary">
                <?= e(trans('back_to_reports')) ?>
            </a>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../../../views/layouts/admin.php';
    }
}
