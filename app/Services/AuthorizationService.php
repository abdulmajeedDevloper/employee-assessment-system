<?php

namespace App\Services;

class AuthorizationService
{
    /**
     * Determine if an administrator has permission to perform an action.
     *
     * @param array $admin User session array
     * @param string $action
     * @param mixed|null $subject
     * @return bool
     */
    public static function can(array $admin, string $action, mixed $subject = null): bool
    {
        if (empty($admin) || empty($admin['role']) || empty($admin['is_active'])) {
            return false;
        }

        $role = $admin['role'];

        // Super Admin has unrestricted permissions
        if ($role === 'super_admin') {
            return true;
        }

        // Admin / Supervisor permissions
        if ($role === 'admin' || $role === 'supervisor') {
            return match ($action) {
                'view_dashboard',
                'view_employees',
                'edit_employee',
                'view_tests',
                'manage_tests',
                'view_questions',
                'manage_questions',
                'view_assignments',
                'manage_assignments',
                'view_attempts',
                'reset_attempt',
                'cancel_attempt',
                'authorize_retake',
                'manage_attempt_notes',
                'view_reports',
                'export_reports' => true,
                'manage_admins' => false,
                default => false,
            };
        }

        // Report Viewer permissions
        if ($role === 'report_viewer') {
            return match ($action) {
                'view_dashboard',
                'view_employees',
                'view_reports',
                'export_reports' => true,
                default => false,
            };
        }

        return false;
    }
}