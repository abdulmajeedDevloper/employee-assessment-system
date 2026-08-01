<?php

namespace App\Controllers\Admin;

use App\Middleware\CsrfMiddleware;
use App\Repositories\AdminRepository;
use App\Services\AdminAuthService;

class AuthController
{
    private AdminAuthService $authService;
    private AdminRepository $adminRepo;

    public function __construct(?AdminAuthService $authService = null, ?AdminRepository $adminRepo = null)
    {
        $this->authService = $authService ?? new AdminAuthService();
        $this->adminRepo = $adminRepo ?? new AdminRepository();
    }

    /**
     * Render the admin login page.
     * Redirects to /admin if a valid admin session already exists.
     */
    public function showLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['admin_auth']) && is_array($_SESSION['admin_auth'])) {
            $auth = $_SESSION['admin_auth'];
            $adminId = (int)($auth['admin_id'] ?? 0);
            $now = time();

            $lastActivity = (int)($auth['last_activity_at'] ?? 0);
            $absoluteStart = (int)($auth['absolute_start_at'] ?? 0);

            $idleMinutes = (int)($_ENV['ADMIN_SESSION_IDLE_MINUTES'] ?? 30);
            $absoluteHours = (int)($_ENV['ADMIN_SESSION_ABSOLUTE_HOURS'] ?? 8);

            $isValid = false;
            if (
                $adminId > 0 &&
                $lastActivity > 0 && $lastActivity <= $now &&
                $absoluteStart > 0 && $absoluteStart <= $now &&
                ($now - $lastActivity) <= ($idleMinutes * 60) &&
                ($now - $absoluteStart) <= ($absoluteHours * 3600)
            ) {
                try {
                    $admin = $this->adminRepo->findById($adminId);
                    $allowedRoles = ['super_admin', 'admin', 'supervisor', 'report_viewer'];
                    if ($admin && (int)$admin['is_active'] === 1 && in_array($admin['role'], $allowedRoles, true)) {
                        $isValid = true;
                    }
                } catch (\Throwable $e) {
                    error_log("Database revalidation error in showLogin: " . $e->getMessage());
                }
            }

            if ($isValid) {
                header('Location: /admin', true, 303);
                exit(0);
            } else {
                unset($_SESSION['admin_auth']);
            }
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        $expiredMessage = null;
        if (isset($_GET['expired']) && $_GET['expired'] === '1') {
            $expiredMessage = trans('session_expired');
        }

        require __DIR__ . '/../../../views/admin/login.php';
    }

    /**
     * Process admin login POST request.
     */
    public function login(): void
    {
        CsrfMiddleware::handle();

        $identifier = $_POST['username'] ?? $_POST['identifier'] ?? $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $res = $this->authService->authenticate($identifier, $password, $ipAddress, $userAgent);

        if (!$res['success']) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $code = $res['code'] ?? '';
            $message = match ($code) {
                'RATE_LIMIT_EXCEEDED' => trans('too_many_login_attempts'),
                'INTERNAL_ERROR' => trans('temporary_error'),
                default => trans('invalid_credentials'),
            };

            $_SESSION['flash_error'] = $message;
            header('Location: /admin/login', true, 303);
            exit(0);
        }

        header('Location: /admin', true, 303);
        exit(0);
    }

    /**
     * Process admin logout POST request.
     */
    public function logout(): void
    {
        CsrfMiddleware::handle();

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $this->authService->logout($ipAddress, $userAgent);

        header('Location: /admin/login', true, 303);
        exit(0);
    }
}
