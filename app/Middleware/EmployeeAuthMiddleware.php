<?php

namespace App\Middleware;

use App\Repositories\EmployeeRepository;

class EmployeeAuthMiddleware
{
    public static function handle(bool $allowIncompleteProfile = false): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['employee_auth']) || !is_array($_SESSION['employee_auth'])) {
            self::redirect('/employee/login');
            return null;
        }

        $auth = $_SESSION['employee_auth'];
        $employeeId = (int)($auth['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            unset($_SESSION['employee_auth']);
            self::redirect('/employee/login');
            return null;
        }

        // Timeouts Check
        $now = time();
        $idleMinutes = (int)($_ENV['EMPLOYEE_SESSION_IDLE_MINUTES'] ?? 30);
        $absoluteHours = (int)($_ENV['EMPLOYEE_SESSION_ABSOLUTE_HOURS'] ?? 8);

        $lastActivity = (int)($auth['last_activity_at'] ?? $now);
        $absoluteStart = (int)($auth['absolute_start_at'] ?? $now);

        if (($now - $lastActivity) > ($idleMinutes * 60) || ($now - $absoluteStart) > ($absoluteHours * 3600)) {
            unset($_SESSION['employee_auth']);
            self::redirect('/employee/login?expired=1');
            return null;
        }

        // Verify Database Account Status
        $empRepo = new EmployeeRepository();
        $employee = $empRepo->findById($employeeId);
        if (!$employee || (int)$employee['is_active'] !== 1 || !empty($employee['archived_at'])) {
            unset($_SESSION['employee_auth']);
            self::redirect('/employee/login');
            return null;
        }

        // Update Last Activity
        $_SESSION['employee_auth']['last_activity_at'] = $now;

        // Profile Completion Check
        if (!$allowIncompleteProfile && empty($employee['profile_completed_at'])) {
            self::redirect('/employee/profile');
            return null;
        }

        return $employee;
    }

    private static function redirect(string $path): void
    {
        header("Location: {$path}", true, 303);
        exit(0);
    }
}
