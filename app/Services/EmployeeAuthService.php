<?php

namespace App\Services;

use App\Middleware\RateLimitMiddleware;
use App\Repositories\EmployeeRepository;
use App\Security\HashManager;
use App\Validators\EmployeeRequestValidator;

class EmployeeAuthService
{
    private EmployeeRepository $empRepo;

    public function __construct(?EmployeeRepository $empRepo = null)
    {
        $this->empRepo = $empRepo ?? new EmployeeRepository();
    }

    public function authenticate(string $rawNationalId, string $ipAddress): array
    {
        $val = EmployeeRequestValidator::validateNationalId($rawNationalId);
        if (!$val['valid']) {
            return [
                'success' => false,
                'message' => 'Invalid National ID or account inactive.',
                'code' => 'INVALID_CREDENTIALS'
            ];
        }

        $nationalId = $val['normalized'];

        // Enforce Rate Limiting (Identifier & IP)
        $rateLimitKey = 'emp_login';
        if (RateLimitMiddleware::isLocked($rateLimitKey, $nationalId, $ipAddress)) {
            return [
                'success' => false,
                'message' => 'Maximum login attempts exceeded. Please try again after 15 minutes.',
                'code' => 'RATE_LIMIT_EXCEEDED'
            ];
        }

        // Compute keyed HMAC-SHA-256 (Binary)
        $hashBin = HashManager::hashNationalId($nationalId);

        // Lookup employee strictly by national_id_hash
        $employee = $this->empRepo->findByNationalIdHash($hashBin);

        // Neutral Error handling: If non-existent, inactive, or archived
        if (!$employee || (int)$employee['is_active'] !== 1 || !empty($employee['archived_at'])) {
            RateLimitMiddleware::recordAttempt($rateLimitKey, $nationalId, $ipAddress, false);
            return [
                'success' => false,
                'message' => 'Invalid National ID or account inactive.',
                'code' => 'INVALID_CREDENTIALS'
            ];
        }

        // Authentication Successful: Record Success Attempt
        RateLimitMiddleware::recordAttempt($rateLimitKey, $nationalId, $ipAddress, true);

        // Session Hardening & Isolation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $now = time();
        $_SESSION['employee_auth'] = [
            'employee_id' => (int)$employee['id'],
            'authenticated_at' => $now,
            'preferred_language' => $employee['preferred_language'] ?? 'ar',
            'profile_completed' => !empty($employee['profile_completed_at']),
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        return [
            'success' => true,
            'employee' => $employee
        ];
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['employee_auth']);
        session_regenerate_id(true);
    }
}
