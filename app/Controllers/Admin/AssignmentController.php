<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Repositories\AdminAssignmentRepository;
use App\Services\AdminAssignmentDirectoryService;
use App\Services\AdminAssignmentManagementService;
use App\Support\TimezoneHelper;
use Throwable;

class AssignmentController
{
    private AdminAssignmentRepository $repository;
    private AdminAssignmentDirectoryService $directoryService;
    private AdminAssignmentManagementService $managementService;

    public function __construct(
        ?AdminAssignmentRepository $repository = null,
        ?AdminAssignmentDirectoryService $directoryService = null,
        ?AdminAssignmentManagementService $managementService = null
    ) {
        $this->repository = $repository ?? new AdminAssignmentRepository();
        $this->directoryService = $directoryService ?? new AdminAssignmentDirectoryService($this->repository);
        $this->managementService = $managementService ?? new AdminAssignmentManagementService($this->repository);
    }

    public function index(): void
    {
        $admin = AdminAuthMiddleware::handle('view_assignments');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }
        $this->setLanguage();

        $results = ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 0];
        $filters = ['q' => '', 'status' => 'all', 'availability' => 'all', 'employee_id' => null, 'test_id' => null, 'page' => 1];
        $employees = [];
        $tests = [];
        $errorMessage = null;

        try {
            $data = $this->directoryService->getDirectory($_GET);
            $results = $data['results'];
            $filters = $data['filters'];
            $employees = $data['employees'];
            $tests = $data['tests'];
        } catch (Throwable $e) {
            error_log('AssignmentController index error: ' . $e->getMessage());
            $errorMessage = trans('temporary_assignment_error');
        }

        require __DIR__ . '/../../../views/admin/assignments/index.php';
    }

    public function show(): void
    {
        $admin = AdminAuthMiddleware::handle('view_assignments');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }
        $this->setLanguage();

        $result = $this->directoryService->getDetails($_GET['id'] ?? null);
        if (!$result['success']) {
            $this->render404();
            return;
        }
        $assignment = $result['assignment'];
        require __DIR__ . '/../../../views/admin/assignments/show.php';
    }

    public function create(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_assignments');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }
        $this->setLanguage();

        $formData = $this->managementService->getCreateFormData();
        $employees = $formData['employees'];
        $tests = $formData['tests'];
        $requestedEmployeeId = $this->directoryService->parsePositiveInt($_GET['employee_id'] ?? null);
        $requestedTestId = $this->directoryService->parsePositiveInt($_GET['test_id'] ?? null);
        $employeeIds = array_map(static fn(array $row): int => (int)$row['id'], $employees);
        $testIds = array_map(static fn(array $row): int => (int)$row['id'], $tests);

        $errors = [];
        $old = [
            'employee_id' => $requestedEmployeeId !== null && in_array($requestedEmployeeId, $employeeIds, true)
                ? (string)$requestedEmployeeId : '',
            'test_id' => $requestedTestId !== null && in_array($requestedTestId, $testIds, true)
                ? (string)$requestedTestId : '',
            'max_attempts' => '1',
            'available_from' => '',
            'available_until' => '',
        ];
        $formMessage = null;
        require __DIR__ . '/../../../views/admin/assignments/create.php';
    }

    public function store(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_assignments');
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
        $this->setLanguage();

        $result = $this->managementService->createAssignment($_POST, (int)$admin['id']);
        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans('assignment_created_successfully');
            header('Location: /admin/assignments/view?id=' . (int)$result['assignment_id']);
            exit;
        }

        $formData = $this->managementService->getCreateFormData();
        $employees = $formData['employees'];
        $tests = $formData['tests'];
        $errors = $result['errors'] ?? [];
        $old = $result['old'] ?? $this->sanitizeOld($_POST);
        $formMessage = $result['code'] === 'INTERNAL_ERROR' ? trans('temporary_assignment_error') : null;
        require __DIR__ . '/../../../views/admin/assignments/create.php';
    }

    public function edit(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_assignments');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }
        $this->setLanguage();

        $id = $this->directoryService->parsePositiveInt($_GET['id'] ?? null);
        if ($id === null) {
            $this->render404();
            return;
        }
        $assignment = $this->repository->findSafeById($id);
        if ($assignment === null) {
            $this->render404();
            return;
        }
        if ((string)$assignment['status'] !== 'PENDING' || (int)$assignment['attempt_count'] > 0) {
            $_SESSION['flash_error'] = trans('assignment_edit_status_locked');
            header('Location: /admin/assignments/view?id=' . $id);
            exit;
        }

        $editToken = $this->managementService->issueEditToken((int)$admin['id'], $id, $assignment);
        $errors = [];
        $old = $this->assignmentOld($assignment);
        $formMessage = null;
        require __DIR__ . '/../../../views/admin/assignments/edit.php';
    }

    public function update(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_assignments');
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
        $this->setLanguage();

        $id = $this->directoryService->parsePositiveInt($_POST['id'] ?? null);
        if ($id === null) {
            $this->render404();
            return;
        }
        $editToken = is_string($_POST['edit_token'] ?? null) ? trim((string)$_POST['edit_token']) : '';
        $result = $this->managementService->updateAssignment($id, $editToken, $_POST, (int)$admin['id']);

        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans('assignment_updated_successfully');
            header('Location: /admin/assignments/view?id=' . $id);
            exit;
        }
        if ($result['code'] === 'NO_CHANGES') {
            $_SESSION['flash_info'] = trans('no_changes_made');
            header('Location: /admin/assignments/view?id=' . $id);
            exit;
        }
        if ($result['code'] === 'NOT_FOUND') {
            $this->render404();
            return;
        }
        if ($result['code'] === 'STATUS_LOCKED') {
            $_SESSION['flash_error'] = $result['message'] ?? trans('assignment_edit_status_locked');
            header('Location: /admin/assignments/view?id=' . $id);
            exit;
        }

        $assignment = $this->repository->findSafeById($id);
        if ($assignment === null) {
            $this->render404();
            return;
        }
        $editToken = $this->managementService->issueEditToken((int)$admin['id'], $id, $assignment);
        $errors = $result['errors'] ?? [];
        $old = $result['old'] ?? $this->assignmentOld($assignment);
        $formMessage = $result['code'] === 'CONFLICT'
            ? ($result['message'] ?? trans('concurrent_assignment_update_conflict'))
            : ($result['code'] === 'INTERNAL_ERROR' ? trans('temporary_assignment_error') : null);
        require __DIR__ . '/../../../views/admin/assignments/edit.php';
    }

    private function assignmentOld(array $assignment): array
    {
        return [
            'employee_id' => (string)(int)$assignment['employee_id'],
            'test_id' => (string)(int)$assignment['test_id'],
            'max_attempts' => (string)(int)$assignment['max_attempts'],
            'available_from' => !empty($assignment['available_from'])
                ? TimezoneHelper::format((string)$assignment['available_from'], null, 'Y-m-d\\TH:i')
                : '',
            'available_until' => !empty($assignment['available_until'])
                ? TimezoneHelper::format((string)$assignment['available_until'], null, 'Y-m-d\\TH:i')
                : '',
        ];
    }

    private function sanitizeOld(array $input): array
    {
        $old = [];
        foreach (['employee_id', 'test_id', 'max_attempts', 'available_from', 'available_until'] as $field) {
            $old[$field] = is_scalar($input[$field] ?? null) ? trim((string)$input[$field]) : '';
        }
        return $old;
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

    private function render404(): void
    {
        http_response_code(404);
        $pageTitle = trans('assignment_not_found');
        $isAuthenticatedLayout = true;
        $admin = $_SESSION['admin_auth'] ?? null;
        ob_start();
        ?>
        <div class="admin-welcome-card" style="border-color:rgba(239,68,68,.4)">
            <h1 class="admin-welcome-title" style="color:#f87171"><?= e(trans('assignment_not_found')) ?></h1>
            <p style="color:#94a3b8"><?= e(trans('assignment_not_found_description')) ?></p>
            <a href="/admin/assignments" class="nav-link"><?= e(trans('back_to_assignments')) ?></a>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../../../views/layouts/admin.php';
    }
}
