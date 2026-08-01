<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\AdminUserManagementService;

class AdminUserController
{
    private AdminUserManagementService $userService;

    public function __construct(?AdminUserManagementService $userService = null)
    {
        $this->userService = $userService ?? new AdminUserManagementService();
    }

    /**
     * Render Admin Accounts list page.
     * GET /admin/admin-users
     */
    public function index(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_admins');
        if (!$admin) {
            return;
        }

        $filters = [
            'search' => $_GET['search'] ?? '',
            'role' => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 15;

        $result = $this->userService->listAdmins($filters, $page, $perPage);

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = trans('admin_accounts');
        $currentLang = $_SESSION['lang'] ?? 'ar';
        $isAuthenticatedLayout = true;
        $adminCssScopes = ['admin-account-management'];

        require __DIR__ . '/../../../views/admin/admin_users/index.php';
    }

    /**
     * Render Create Secondary Admin form.
     * GET /admin/admin-users/create
     */
    public function create(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_admins');
        if (!$admin) {
            return;
        }

        $errors = $_SESSION['form_errors'] ?? [];
        $formData = $_SESSION['form_data'] ?? [];
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['form_errors'], $_SESSION['form_data'], $_SESSION['flash_error']);

        $pageTitle = trans('add_admin');
        $currentLang = $_SESSION['lang'] ?? 'ar';
        $isAuthenticatedLayout = true;
        $adminCssScopes = ['admin-account-management'];

        require __DIR__ . '/../../../views/admin/admin_users/create.php';
    }

    /**
     * Store secondary admin account (POST).
     * POST /admin/admin-users/create
     */
    public function store(): void
    {
        CsrfMiddleware::handle();
        $admin = AdminAuthMiddleware::handle('manage_admins');
        if (!$admin) {
            return;
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $res = $this->userService->createAdmin($_POST, $admin, $ipAddress, $userAgent);

        if (!$res['success']) {
            $_SESSION['flash_error'] = $res['message'];
            $_SESSION['form_errors'] = $res['errors'] ?? [];
            $_SESSION['form_data'] = [
                'display_name' => $_POST['display_name'] ?? '',
                'username' => $_POST['username'] ?? '',
                'email' => $_POST['email'] ?? '',
                'role' => $_POST['role'] ?? 'admin',
                'is_active' => $_POST['is_active'] ?? '1',
            ];

            header('Location: /admin/admin-users/create', true, 303);
            exit(0);
        }

        $_SESSION['flash_success'] = trans('admin_created_success');
        header('Location: /admin/admin-users', true, 303);
        exit(0);
    }

    /**
     * Render Edit Admin form.
     * GET /admin/admin-users/edit?id=X
     */
    public function edit(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_admins');
        if (!$admin) {
            return;
        }

        $targetId = (int)($_GET['id'] ?? 0);
        $targetAdmin = $this->userService->findAdminById($targetId);

        if (!$targetAdmin) {
            $_SESSION['flash_error'] = trans('account_not_found');
            header('Location: /admin/admin-users', true, 303);
            exit(0);
        }

        $errors = $_SESSION['form_errors'] ?? [];
        $formData = $_SESSION['form_data'] ?? [];
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['form_errors'], $_SESSION['form_data'], $_SESSION['flash_error']);

        $pageTitle = trans('edit_admin');
        $currentLang = $_SESSION['lang'] ?? 'ar';
        $isAuthenticatedLayout = true;
        $adminCssScopes = ['admin-account-management'];

        require __DIR__ . '/../../../views/admin/admin_users/edit.php';
    }

    /**
     * Update admin account details (POST).
     * POST /admin/admin-users/edit
     */
    public function update(): void
    {
        CsrfMiddleware::handle();
        $admin = AdminAuthMiddleware::handle('manage_admins');
        if (!$admin) {
            return;
        }

        $targetId = (int)($_POST['id'] ?? 0);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $res = $this->userService->updateAdmin($targetId, $_POST, $admin, $ipAddress, $userAgent);

        if (!$res['success']) {
            $_SESSION['flash_error'] = $res['message'];
            $_SESSION['form_errors'] = $res['errors'] ?? [];
            $_SESSION['form_data'] = $_POST;

            header("Location: /admin/admin-users/edit?id={$targetId}", true, 303);
            exit(0);
        }

        $_SESSION['flash_success'] = trans('admin_updated_success');
        header('Location: /admin/admin-users', true, 303);
        exit(0);
    }

    /**
     * Render Change Password form.
     * GET /admin/admin-users/password?id=X
     */
    public function changePassword(): void
    {
        $admin = AdminAuthMiddleware::handle('manage_admins');
        if (!$admin) {
            return;
        }

        $targetId = (int)($_GET['id'] ?? 0);
        $targetAdmin = $this->userService->findAdminById($targetId);

        if (!$targetAdmin) {
            $_SESSION['flash_error'] = trans('account_not_found');
            header('Location: /admin/admin-users', true, 303);
            exit(0);
        }

        $errors = $_SESSION['form_errors'] ?? [];
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['form_errors'], $_SESSION['flash_error']);

        $pageTitle = trans('change_password');
        $currentLang = $_SESSION['lang'] ?? 'ar';
        $isAuthenticatedLayout = true;
        $adminCssScopes = ['admin-account-management'];

        require __DIR__ . '/../../../views/admin/admin_users/password.php';
    }

    /**
     * Process Change Password (POST).
     * POST /admin/admin-users/password
     */
    public function updatePassword(): void
    {
        CsrfMiddleware::handle();
        $admin = AdminAuthMiddleware::handle('manage_admins');
        if (!$admin) {
            return;
        }

        $targetId = (int)($_POST['id'] ?? 0);
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $res = $this->userService->changePassword(
            $targetId,
            $currentPassword,
            $newPassword,
            $confirmPassword,
            $admin,
            $ipAddress,
            $userAgent
        );

        if (!$res['success']) {
            $_SESSION['flash_error'] = $res['message'];
            $_SESSION['form_errors'] = $res['errors'] ?? [];

            header("Location: /admin/admin-users/password?id={$targetId}", true, 303);
            exit(0);
        }

        $_SESSION['flash_success'] = trans('password_changed_success');
        header('Location: /admin/admin-users', true, 303);
        exit(0);
    }

    /**
     * Toggle status (activate/deactivate) (POST).
     * POST /admin/admin-users/toggle-status
     */
    public function toggleStatus(): void
    {
        CsrfMiddleware::handle();
        $admin = AdminAuthMiddleware::handle('manage_admins');
        if (!$admin) {
            return;
        }

        $targetId = (int)($_POST['id'] ?? 0);
        $newStatus = (int)($_POST['is_active'] ?? 0);

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $res = $this->userService->toggleStatus($targetId, $newStatus, $admin, $ipAddress, $userAgent);

        if (!$res['success']) {
            $_SESSION['flash_error'] = $res['message'];
        } else {
            $_SESSION['flash_success'] = $res['message'];
        }

        header('Location: /admin/admin-users', true, 303);
        exit(0);
    }
}
