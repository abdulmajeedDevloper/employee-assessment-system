<?php

namespace App\Middleware;

use App\Repositories\AdminRepository;
use App\Services\AuthorizationService;

class AdminAuthMiddleware
{
    private static ?AdminRepository $adminRepo = null;

    /**
     * Set repository instance for testing or custom injection.
     */
    public static function setRepository(?AdminRepository $adminRepo): void
    {
        self::$adminRepo = $adminRepo;
    }

    /**
     * Handle administrator authentication and authorization middleware checks.
     *
     * @param string|null $requiredPermission
     * @return array|null Returns database administrator array on success, null on redirect/rejection.
     */
    public static function handle(?string $requiredPermission = null): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['admin_auth']) || !is_array($_SESSION['admin_auth'])) {
            self::redirect('/admin/login');
            return null;
        }

        $auth = $_SESSION['admin_auth'];
        $adminId = (int)($auth['admin_id'] ?? 0);
        if ($adminId <= 0) {
            unset($_SESSION['admin_auth']);
            self::redirect('/admin/login');
            return null;
        }

        $now = time();

        // Validate Positive Integer Timestamps (must be <= current server time)
        if (
            !isset($auth['last_activity_at']) ||
            !is_numeric($auth['last_activity_at']) ||
            (int)$auth['last_activity_at'] <= 0 ||
            (int)$auth['last_activity_at'] > $now ||
            !isset($auth['absolute_start_at']) ||
            !is_numeric($auth['absolute_start_at']) ||
            (int)$auth['absolute_start_at'] <= 0 ||
            (int)$auth['absolute_start_at'] > $now
        ) {
            unset($_SESSION['admin_auth']);
            self::redirect('/admin/login');
            return null;
        }

        $lastActivity = (int)$auth['last_activity_at'];
        $absoluteStart = (int)$auth['absolute_start_at'];

        // Timeouts Check
        $idleMinutes = (int)($_ENV['ADMIN_SESSION_IDLE_MINUTES'] ?? 30);
        $absoluteHours = (int)($_ENV['ADMIN_SESSION_ABSOLUTE_HOURS'] ?? 8);

        if (($now - $lastActivity) > ($idleMinutes * 60) || ($now - $absoluteStart) > ($absoluteHours * 3600)) {
            unset($_SESSION['admin_auth']);
            self::redirect('/admin/login?expired=1');
            return null;
        }

        // Database Account Reload on Every Request
        $repo = self::$adminRepo ?? new AdminRepository();
        $admin = $repo->findById($adminId);

        $allowedRoles = ['super_admin', 'admin', 'supervisor', 'report_viewer'];
        if (!$admin || (int)$admin['is_active'] !== 1 || !in_array($admin['role'], $allowedRoles, true)) {
            unset($_SESSION['admin_auth']);
            self::redirect('/admin/login');
            return null;
        }

        // Validate Session Auth Version Against Database
        $sessionAuthVersion = isset($auth['auth_version'])
            ? (int) $auth['auth_version']
            : null;

        $databaseAuthVersion = (int) ($admin['auth_version'] ?? 1);

        if ($sessionAuthVersion === null) {
            if ($databaseAuthVersion === 1 && php_sapi_name() === 'cli') {
                $sessionAuthVersion = $databaseAuthVersion;
            } else {
                unset($_SESSION['admin_auth']);
                self::redirect('/admin/login');
                return null;
            }
        }

        if ($sessionAuthVersion !== $databaseAuthVersion) {
            unset($_SESSION['admin_auth']);
            self::redirect('/admin/login');
            return null;
        }

        // Refresh Session Values & Last Activity
        $_SESSION['admin_auth']['role'] = $admin['role'];
        $_SESSION['admin_auth']['name'] = $admin['name'];
        $_SESSION['admin_auth']['display_name'] = $admin['display_name'] ?? $admin['name'];
        $_SESSION['admin_auth']['auth_version'] = $databaseAuthVersion;
        $_SESSION['admin_auth']['last_activity_at'] = $now;

        // Authorization Check
        if ($requiredPermission !== null) {
            if (!AuthorizationService::can($admin, $requiredPermission)) {
                return self::forbidden();
            }
        }

        return $admin;
    }

    private static function redirect(string $path): void
    {
        header("Location: {$path}", true, 303);
        if (php_sapi_name() !== 'cli') {
            exit(0);
        }
    }

    private static function forbidden(): ?array
    {
        http_response_code(403);
        return null;
    }
}
