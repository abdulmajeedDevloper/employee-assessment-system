<?php

namespace App\Controllers\Employee;

use App\Middleware\CsrfMiddleware;
use App\Services\EmployeeAuthService;

class AuthController
{
    private EmployeeAuthService $authService;

    public function __construct(?EmployeeAuthService $authService = null)
    {
        $this->authService = $authService ?? new EmployeeAuthService();
    }

    public function showLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['employee_auth']) && is_array($_SESSION['employee_auth'])) {
            header('Location: /employee/tests', true, 303);
            exit(0);
        }

        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $translations = require __DIR__ . "/../../../lang/{$lang}.php";
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        require __DIR__ . '/../../../views/employee/login.php';
    }

    public function login(): void
    {
        CsrfMiddleware::handle();

        $nationalId = $_POST['national_id'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $res = $this->authService->authenticate($nationalId, $ipAddress);
        if (!$res['success']) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash_error'] = $res['message'];
            header('Location: /employee/login', true, 303);
            exit(0);
        }

        // PRG Pattern: HTTP 303 Redirect to Assessment Status Router
        header('Location: /employee/tests', true, 303);
        exit(0);
    }

    public function logout(): void
    {
        CsrfMiddleware::handle();
        $this->authService->logout();

        header('Location: /employee/login', true, 303);
        exit(0);
    }
}
