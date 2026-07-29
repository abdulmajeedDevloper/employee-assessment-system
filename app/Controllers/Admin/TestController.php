<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\AdminTestDirectoryService;
use App\Services\AdminTestManagementService;
use App\Repositories\AdminTestRepository;
use Throwable;

class TestController
{
    private AdminTestDirectoryService $directoryService;
    private AdminTestManagementService $managementService;
    private AdminTestRepository $repository;

    public function __construct(
        ?AdminTestDirectoryService $directoryService = null,
        ?AdminTestManagementService $managementService = null,
        ?AdminTestRepository $repository = null
    ) {
        $this->directoryService = $directoryService ?? new AdminTestDirectoryService();
        $this->managementService = $managementService ?? new AdminTestManagementService();
        $this->repository = $repository ?? new AdminTestRepository();
    }

    /**
     * Render the secure admin assessment directory list.
     */
    public function index(): void
    {
        $admin = AdminAuthMiddleware::handle('view_tests');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $canManageTests = \App\Services\AuthorizationService::can($admin, 'manage_tests');

        $results = [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 20,
            'total_pages' => 0,
        ];
        $filters = [
            'q' => '',
            'status' => 'all',
            'page' => 1,
        ];
        $errorMessage = null;

        try {
            $directoryData = $this->directoryService->getDirectory($_GET);
            $results = $directoryData['results'];
            $filters = $directoryData['filters'];
        } catch (Throwable $e) {
            error_log("TestController index error: " . $e->getMessage());
            $errorMessage = trans('temporary_test_error');

            $q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
            if (mb_strlen($q) > 100) {
                $q = mb_substr($q, 0, 100);
            }
            $rawStatus = $_GET['status'] ?? 'all';
            $status = in_array($rawStatus, ['all', 'active', 'inactive'], true) ? $rawStatus : 'all';
            $parsedPage = isset($_GET['page']) ? $this->directoryService->parsePositiveInt($_GET['page']) : null;

            $filters = [
                'q' => $q,
                'status' => $status,
                'page' => $parsedPage ?? 1,
            ];
        }

        require __DIR__ . '/../../../views/admin/tests/index.php';
    }

    /**
     * Render safe details for a specific assessment.
     */
    public function show(): void
    {
        $admin = AdminAuthMiddleware::handle('view_tests');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $canManageTests = \App\Services\AuthorizationService::can($admin, 'manage_tests');

        $rawId = $_GET['id'] ?? null;
        $detailsData = $this->directoryService->getTestDetails($rawId, $lang);

        if (!$detailsData['success']) {
            if ($detailsData['code'] === 'INVALID_ID' || $detailsData['code'] === 'NOT_FOUND') {
                $this->render404();
                return;
            }
            $this->render500();
            return;
        }

        $test = $detailsData['test'];
        $localized = $detailsData['localized'];
        $aggregates = $detailsData['aggregates'];

        require __DIR__ . '/../../../views/admin/tests/show.php';
    }

    /**
     * Render the assessment creation form.
     */
    public function create(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_tests');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $errors = [];
        $old = [
            'display_order' => '1',
            'randomize_questions' => 1,
            'randomize_options' => 1,
            'allow_back_navigation' => 1,
            'show_result_to_employee' => 0,
            'duration_minutes' => '60',
            'question_count' => '10',
            'passing_percentage' => '60.00',
        ];

        require __DIR__ . '/../../../views/admin/tests/create.php';
    }

    /**
     * Handle assessment creation POST request.
     */
    public function store(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_tests');
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

        $result = $this->managementService->createTest($_POST, (int)$admin['id']);

        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans('assessment_created_successfully');
            header('Location: /admin/tests/view?id=' . $result['test_id']);
            exit;
        }

        if ($result['code'] === 'VALIDATION_ERROR' || $result['code'] === 'DUPLICATE_CODE') {
            http_response_code(200);
            $errors = $result['errors'] ?? [];
            $old = $result['old'] ?? [];
            $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
            if (!in_array($lang, ['ar', 'en'], true)) {
                $lang = 'ar';
            }
            $_SESSION['lang'] = $lang;
            require __DIR__ . '/../../../views/admin/tests/create.php';
            return;
        }

        $this->render500();
    }

    /**
     * Render assessment edit form with safe concurrency token.
     */
    public function edit(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_tests');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $rawId = $_GET['id'] ?? null;
        $id = $this->directoryService->parsePositiveInt($rawId);
        if ($id === null) {
            $this->render404();
            return;
        }

        $test = $this->repository->findEditableById($id);
        if (!$test) {
            $this->render404();
            return;
        }

        $attemptCounts = $this->repository->getAttemptStateCounts($id);
        $editToken = $this->managementService->issueEditToken((int)$admin['id'], $id, $test);

        $errors = [];
        $old = [];

        require __DIR__ . '/../../../views/admin/tests/edit.php';
    }

    /**
     * Handle assessment edit POST request.
     */
    public function update(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_tests');
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

        $rawId = $_POST['id'] ?? null;
        $id = $this->directoryService->parsePositiveInt($rawId);
        if ($id === null) {
            $this->render404();
            return;
        }

        $editToken = is_string($_POST['edit_token'] ?? null) ? trim($_POST['edit_token']) : '';

        $result = $this->managementService->updateTest($id, $editToken, $_POST, (int)$admin['id']);

        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans('assessment_updated_successfully');
            header('Location: /admin/tests/view?id=' . $id);
            exit;
        }

        if ($result['code'] === 'NO_CHANGES') {
            $_SESSION['flash_info'] = trans('no_changes_made');
            header('Location: /admin/tests/view?id=' . $id);
            exit;
        }

        if ($result['code'] === 'NOT_FOUND') {
            $this->render404();
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        // For non-redirect form re-renders, fetch current DB row & issue a FRESH edit_token
        $test = $this->repository->findEditableById($id);
        if (!$test) {
            $this->render404();
            return;
        }
        $attemptCounts = $this->repository->getAttemptStateCounts($id);
        $freshEditToken = $this->managementService->issueEditToken((int)$admin['id'], $id, $test);

        if ($result['code'] === 'CONFLICT') {
            http_response_code(200);
            $conflictMessage = $result['message'] ?? trans('concurrent_update_conflict');
            $editToken = $freshEditToken;
            $errors = [];
            $old = [];
            require __DIR__ . '/../../../views/admin/tests/edit.php';
            return;
        }

        if ($result['code'] === 'VALIDATION_ERROR' || $result['code'] === 'DUPLICATE_CODE' || $result['code'] === 'FIELD_LOCKED') {
            http_response_code(200);
            $editToken = $freshEditToken;
            $errors = $result['errors'] ?? [];
            $old = $result['old'] ?? [];
            require __DIR__ . '/../../../views/admin/tests/edit.php';
            return;
        }

        $this->render500();
    }

    /**
     * Render activation and availability lifecycle form.
     */
    public function lifecycle(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_tests');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $id = $this->directoryService->parsePositiveInt($_GET['id'] ?? null);
        if ($id === null) {
            $this->render404();
            return;
        }

        $test = $this->repository->findEditableById($id);
        if (!$test) {
            $this->render404();
            return;
        }

        $readiness = $this->repository->getLifecycleReadiness($id);
        $editToken = $this->managementService->issueEditToken((int)$admin['id'], $id, $test);
        $errors = [];
        $old = $this->lifecycleOldValues($test);

        require __DIR__ . '/../../../views/admin/tests/lifecycle.php';
    }

    /**
     * Persist activation and availability lifecycle changes.
     */
    public function updateLifecycle(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_tests');
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

        $id = $this->directoryService->parsePositiveInt($_POST['id'] ?? null);
        if ($id === null) {
            $this->render404();
            return;
        }

        $editToken = is_string($_POST['edit_token'] ?? null) ? trim($_POST['edit_token']) : '';
        $result = $this->managementService->updateLifecycle(
            $id,
            $editToken,
            $_POST,
            (int)$admin['id']
        );

        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans('assessment_lifecycle_updated');
            header('Location: /admin/tests/view?id=' . $id);
            exit;
        }

        if ($result['code'] === 'NO_CHANGES') {
            $_SESSION['flash_info'] = trans('no_changes_made');
            header('Location: /admin/tests/view?id=' . $id);
            exit;
        }

        if ($result['code'] === 'NOT_FOUND') {
            $this->render404();
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $test = $this->repository->findEditableById($id);
        if (!$test) {
            $this->render404();
            return;
        }

        $readiness = $result['readiness'] ?? $this->repository->getLifecycleReadiness($id);
        $editToken = $this->managementService->issueEditToken((int)$admin['id'], $id, $test);
        $errors = $result['errors'] ?? [];
        $old = $result['old'] ?? $this->lifecycleOldValues($test);
        $conflictMessage = $result['code'] === 'CONFLICT'
            ? ($result['message'] ?? trans('concurrent_update_conflict'))
            : null;

        if (in_array($result['code'], ['CONFLICT', 'VALIDATION_ERROR', 'NOT_READY'], true)) {
            http_response_code(200);
            require __DIR__ . '/../../../views/admin/tests/lifecycle.php';
            return;
        }

        $this->render500();
    }

    /**
     * Convert stored UTC lifecycle timestamps to datetime-local values.
     */
    private function lifecycleOldValues(array $test): array
    {
        return [
            'is_active' => (string)(int)($test['is_active'] ?? 0),
            'available_from' => !empty($test['available_from'])
                ? \App\Support\TimezoneHelper::format((string)$test['available_from'], null, 'Y-m-d\\TH:i')
                : '',
            'available_until' => !empty($test['available_until'])
                ? \App\Support\TimezoneHelper::format((string)$test['available_until'], null, 'Y-m-d\\TH:i')
                : '',
        ];
    }

    /**
     * Render standard 403 access denied view inside admin layout.
     */
    private function render403(): void
    {
        http_response_code(403);
        require __DIR__ . '/../../../views/admin/403.php';
    }

    /**
     * Render safe 404 assessment not found view inside admin layout.
     */
    private function render404(): void
    {
        http_response_code(404);
        $pageTitle = trans('test_not_found');
        $isAuthenticatedLayout = true;
        $admin = $_SESSION['admin_auth'] ?? null;
        ob_start();
        ?>
        <div class="admin-welcome-card" style="border-color: rgba(239, 68, 68, 0.4);">
            <h1 class="admin-welcome-title" style="color: #f87171;"><?= e(trans('test_not_found')) ?></h1>
            <p style="color: #94a3b8; margin-top: 0.5rem; margin-bottom: 1.5rem;">
                <?= e(trans('test_not_found')) ?>
            </p>
            <div>
                <a href="/admin/tests" class="btn btn-secondary"><?= e(trans('back_to_tests')) ?></a>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../../../views/layouts/admin.php';
    }

    /**
     * Render generic 500 error view inside admin layout.
     */
    private function render500(): void
    {
        http_response_code(500);
        $pageTitle = trans('temporary_error');
        $isAuthenticatedLayout = true;
        $admin = $_SESSION['admin_auth'] ?? null;
        ob_start();
        ?>
        <div class="admin-welcome-card" style="border-color: rgba(239, 68, 68, 0.4);">
            <h1 class="admin-welcome-title" style="color: #f87171;"><?= e(trans('temporary_error')) ?></h1>
            <p style="color: #94a3b8; margin-top: 0.5rem; margin-bottom: 1.5rem;">
                <?= e(trans('temporary_test_error')) ?>
            </p>
            <div>
                <a href="/admin/tests" class="btn btn-secondary"><?= e(trans('back_to_tests')) ?></a>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../../../views/layouts/admin.php';
    }
}
