<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\AdminEmployeeDirectoryService;
use App\Services\AdminEmployeeManagementService;
use Throwable;

class EmployeeController
{
    private AdminEmployeeDirectoryService $directoryService;
    private AdminEmployeeManagementService $managementService;

    public function __construct(
        ?AdminEmployeeDirectoryService $directoryService = null,
        ?AdminEmployeeManagementService $managementService = null
    ) {
        $this->directoryService = $directoryService ?? new AdminEmployeeDirectoryService();
        $this->managementService = $managementService ?? new AdminEmployeeManagementService();
    }

    /**
     * Render the admin employee directory list.
     */
    public function index(): void
    {
        $admin = AdminAuthMiddleware::handle('view_employees');
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

        $results = [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 20,
            'total_pages' => 0,
        ];
        $filters = [
            'q' => '',
            'department_id' => null,
            'status' => 'all',
            'page' => 1,
        ];
        $departments = [];
        $errorMessage = null;

        try {
            $directoryData = $this->directoryService->getDirectory($_GET);
            $results = $directoryData['results'];
            $filters = $directoryData['filters'];
            $departments = $directoryData['departments'];
        } catch (Throwable $e) {
            error_log("EmployeeController index error: " . $e->getMessage());
            $errorMessage = trans('temporary_error');

            // Safe fallback filter normalization
            $q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
            if (mb_strlen($q) > 100) {
                $q = mb_substr($q, 0, 100);
            }
            $deptId = isset($_GET['department_id']) ? $this->parsePositiveInt($_GET['department_id']) : null;
            $rawStatus = $_GET['status'] ?? 'all';
            $status = in_array($rawStatus, ['all', 'active', 'inactive', 'archived'], true) ? $rawStatus : 'all';
            $parsedPage = isset($_GET['page']) ? $this->parsePositiveInt($_GET['page']) : null;
            $page = $parsedPage ?? 1;

            $filters = [
                'q' => $q,
                'department_id' => $deptId,
                'status' => $status,
                'page' => $page,
            ];
        }

        require __DIR__ . '/../../../views/admin/employees/index.php';
    }

    /**
     * Render details for a specific employee.
     */
    public function show(): void
    {
        $admin = AdminAuthMiddleware::handle('view_employees');
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
        $employee = null;
        $errorMessage = null;

        $res = $this->directoryService->getEmployeeDetails($rawId);
        if (!$res['success']) {
            if (($res['code'] ?? '') === 'INTERNAL_ERROR') {
                http_response_code(500);
                $errorMessage = trans('temporary_error');
            } else {
                http_response_code(404);
                $errorMessage = trans('employee_not_found');
            }
        } else {
            $employee = $res['employee'];
        }

        require __DIR__ . '/../../../views/admin/employees/show.php';
    }

    /**
     * Render the Add Employee form (GET).
     */
    public function create(): void
    {
        $admin = AdminAuthMiddleware::handle('edit_employee');
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

        $formData = $this->managementService->getFormData();
        $departments = $formData['departments'];
        $sections = $formData['sections'];
        $jobTitles = $formData['job_titles'];

        $errors = [];
        $oldInput = [];
        $errorMessage = null;

        require __DIR__ . '/../../../views/admin/employees/create.php';
    }

    /**
     * Handle Add Employee form submission (POST).
     */
    public function store(): void
    {
        $admin = AdminAuthMiddleware::handle('edit_employee');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        if (!CsrfMiddleware::handle()) {
            http_response_code(403);
            $this->render403();
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $res = $this->managementService->createEmployee($_POST, $admin);

        if ($res['success']) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_success'] = trans('employee_created_successfully');
            $this->redirect('/admin/employees/view?id=' . $res['employee_id']);
            return;
        }

        $formData = $this->managementService->getFormData();
        $departments = $formData['departments'];
        $sections = $formData['sections'];
        $jobTitles = $formData['job_titles'];

        $errors = $res['errors'] ?? [];
        $errorMessage = $res['message'] ?? null;

        // Wipe national_id from safe old input before rendering
        $oldInput = $_POST;
        unset($oldInput['national_id'], $oldInput['_csrf_token']);

        require __DIR__ . '/../../../views/admin/employees/create.php';
    }

    /**
     * Render the Edit Employee form (GET).
     */
    public function edit(): void
    {
        $admin = AdminAuthMiddleware::handle('edit_employee');
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

        $id = $this->parsePositiveInt($_GET['id'] ?? null);
        if ($id === null) {
            $this->render404(trans('employee_not_found'));
            return;
        }

        $formData = $this->managementService->getFormData($id);
        $employee = $formData['employee'];

        if (!$employee || !empty($employee['archived_at'])) {
            $this->render404(trans('employee_not_found'));
            return;
        }

        $departments = $formData['departments'];
        $sections = $formData['sections'];
        $jobTitles = $formData['job_titles'];

        $errors = [];
        $oldInput = [];
        $errorMessage = null;

        require __DIR__ . '/../../../views/admin/employees/edit.php';
    }

    /**
     * Handle Edit Employee form submission (POST).
     */
    public function update(): void
    {
        $admin = AdminAuthMiddleware::handle('edit_employee');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        if (!CsrfMiddleware::handle()) {
            http_response_code(403);
            $this->render403();
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $id = $this->parsePositiveInt($_GET['id'] ?? $_POST['id'] ?? null);
        if ($id === null) {
            $this->render404(trans('employee_not_found'));
            return;
        }

        $res = $this->managementService->updateEmployee($id, $_POST, $admin);

        if ($res['success']) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_success'] = trans('employee_updated_successfully');
            $this->redirect('/admin/employees/view?id=' . $id);
            return;
        }

        if (($res['code'] ?? '') === 'NOT_FOUND') {
            $this->render404(trans('employee_not_found'));
            return;
        }

        $formData = $this->managementService->getFormData($id);
        $employee = $formData['employee'];
        $departments = $formData['departments'];
        $sections = $formData['sections'];
        $jobTitles = $formData['job_titles'];

        $errors = $res['errors'] ?? [];
        $errorMessage = $res['message'] ?? null;

        // Wipe national_id from safe old input before rendering
        $oldInput = $_POST;
        unset($oldInput['national_id'], $oldInput['_csrf_token']);

        require __DIR__ . '/../../../views/admin/employees/edit.php';
    }

    /**
     * Handle Soft Archive Employee (POST).
     */
    public function archive(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            return;
        }

        $admin = AdminAuthMiddleware::handle('edit_employee');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        if (!CsrfMiddleware::handle()) {
            http_response_code(403);
            $this->render403();
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $id = $this->parsePositiveInt($_POST['id'] ?? null);
        if ($id === null) {
            $this->render404(trans('employee_not_found'));
            return;
        }

        $res = $this->managementService->archiveEmployee($id, $admin);

        if ($res['success']) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_success'] = trans('employee_archived_successfully');
            $this->redirect('/admin/employees/view?id=' . $id);
            return;
        }

        if (($res['code'] ?? '') === 'NOT_FOUND') {
            $this->render404(trans('employee_not_found'));
            return;
        }

        if (($res['code'] ?? '') === 'ALREADY_ARCHIVED') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_success'] = trans('employee_already_archived');
            $this->redirect('/admin/employees/view?id=' . $id);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_error'] = trans('archive_failed');
        $this->redirect('/admin/employees/view?id=' . $id);
    }

    /**
     * Handle Soft Restore Employee (POST).
     */
    public function restore(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            return;
        }

        $admin = AdminAuthMiddleware::handle('edit_employee');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }

        if (!CsrfMiddleware::handle()) {
            http_response_code(403);
            $this->render403();
            return;
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $id = $this->parsePositiveInt($_POST['id'] ?? null);
        if ($id === null) {
            $this->render404(trans('employee_not_found'));
            return;
        }

        $res = $this->managementService->restoreEmployee($id, $admin);

        if ($res['success']) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_success'] = trans('employee_restored_successfully');
            $this->redirect('/admin/employees/view?id=' . $id);
            return;
        }

        if (($res['code'] ?? '') === 'NOT_FOUND') {
            $this->render404(trans('employee_not_found'));
            return;
        }

        if (($res['code'] ?? '') === 'NOT_ARCHIVED') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_success'] = trans('employee_not_archived');
            $this->redirect('/admin/employees/view?id=' . $id);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_error'] = trans('restore_failed');
        $this->redirect('/admin/employees/view?id=' . $id);
    }


    /**
     * Render HTTP 404 Employee Not Found view.
     */
    private function render404(string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $employee = null;
        $errorMessage = $message;

        http_response_code(404);
        require __DIR__ . '/../../../views/admin/employees/show.php';
    }

    /**
     * Render HTTP 403 Access Denied view.
     */
    private function render403(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $admin = null;

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        http_response_code(403);
        require __DIR__ . '/../../../views/admin/403.php';
    }

    /**
     * Redirect helper.
     */
    private function redirect(string $path): void
    {
        header("Location: {$path}", true, 303);
        if (php_sapi_name() !== 'cli') {
            exit(0);
        }
    }

    /**
     * Strict positive integer parser.
     *
     * @param mixed $val
     * @return int|null
     */
    private function parsePositiveInt(mixed $val): ?int
    {
        if (is_int($val) && $val >= 1) {
            return $val;
        }
        if (is_string($val) && preg_match('/^[1-9][0-9]*$/', $val) === 1) {
            return (int)$val;
        }
        return null;
    }
}
