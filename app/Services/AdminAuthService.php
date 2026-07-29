<?php

namespace App\Services;

use App\Middleware\RateLimitMiddleware;
use App\Repositories\AdminRepository;
use App\Security\HashManager;
use App\Support\Database;
use PDO;

class AdminAuthService
{
    private AdminRepository $adminRepo;
    private PDO $pdo;

    /**
     * Fixed valid BCrypt cost-12 dummy hash for non-existent accounts.
     * Prevents timing attacks without generating hash on every request.
     */
    private const DUMMY_HASH = '$2y$12$e0MYzXyjpJS7Pd0RVvHwHe1V2z6v6dC0x.J78W77u41Nq.Jp.N4rG';

    public function __construct(?AdminRepository $adminRepo = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->adminRepo = $adminRepo ?? new AdminRepository($this->pdo);
    }

    /**
     * Authenticate administrator account.
     *
     * @param string $rawIdentifier
     * @param string $rawPassword
     * @param string $ipAddress
     * @param string $userAgent
     * @return array
     */
    public function authenticate(
        string $rawIdentifier,
        string $rawPassword,
        string $ipAddress,
        string $userAgent
    ): array {
        $normalizedIdentifier = strtolower(trim($rawIdentifier));
        $rateLimitKey = 'admin_login';

        // Input Validation
        if (
            $normalizedIdentifier === '' ||
            $rawPassword === '' ||
            strlen($normalizedIdentifier) > 255 ||
            strlen($rawPassword) > 4096
        ) {
            password_verify($rawPassword, self::DUMMY_HASH);
            $this->recordFailedAttemptBestEffort($rateLimitKey, $normalizedIdentifier, $ipAddress);
            $this->recordSecurityEvent('ADMIN_LOGIN_FAILED', $normalizedIdentifier, $ipAddress, $userAgent, 'invalid_credentials');

            return [
                'success' => false,
                'message' => 'Invalid username/email or password.',
                'code' => 'INVALID_CREDENTIALS'
            ];
        }

        // Rate Limiting Check with Exception Hardening
        try {
            if (RateLimitMiddleware::isLocked($rateLimitKey, $normalizedIdentifier, $ipAddress)) {
                $this->recordSecurityEvent('ADMIN_LOGIN_RATE_LIMITED', $normalizedIdentifier, $ipAddress, $userAgent, 'rate_limited');

                return [
                    'success' => false,
                    'message' => 'Maximum login attempts exceeded. Please try again later.',
                    'code' => 'RATE_LIMIT_EXCEEDED'
                ];
            }
        } catch (\Throwable $e) {
            error_log("Rate limit check exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'code' => 'INTERNAL_ERROR'
            ];
        }

        // Lookup Admin Account with Exception Protection
        try {
            $admin = $this->adminRepo->findForAuthenticationByIdentifier($normalizedIdentifier);
        } catch (\Throwable $e) {
            error_log("Admin repository lookup error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'code' => 'INTERNAL_ERROR'
            ];
        }

        // Password Verification:
        // Use real stored password_hash whenever admin row exists, dummy hash ONLY when account is null.
        $hashToVerify = ($admin !== null) ? $admin['password_hash'] : self::DUMMY_HASH;
        $passwordValid = password_verify($rawPassword, $hashToVerify);

        // Separate Evaluation of Account Existence, Password Validity, Activity, and Allowed Role
        $allowedRoles = ['super_admin', 'supervisor', 'report_viewer'];
        $accountValid = (
            $admin !== null &&
            $passwordValid &&
            (int)$admin['is_active'] === 1 &&
            in_array($admin['role'], $allowedRoles, true)
        );

        // Reject Invalid Credentials, Inactive Accounts, or Invalid Roles Neutrally
        if (!$accountValid) {
            $this->recordFailedAttemptBestEffort($rateLimitKey, $normalizedIdentifier, $ipAddress);
            $this->recordSecurityEvent('ADMIN_LOGIN_FAILED', $normalizedIdentifier, $ipAddress, $userAgent, 'invalid_credentials');

            return [
                'success' => false,
                'message' => 'Invalid username/email or password.',
                'code' => 'INVALID_CREDENTIALS'
            ];
        }

        // Password Rehash Requirement (Only evaluated after full validation)
        $needsRehash = password_needs_rehash($admin['password_hash'], PASSWORD_BCRYPT, ['cost' => 12]);

        // Begin DB Transaction for Updates & Audit Log
        try {
            $this->pdo->beginTransaction();

            if ($needsRehash) {
                $newHash = password_hash($rawPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $this->adminRepo->updatePasswordHash((int)$admin['id'], $newHash);
            }

            $this->adminRepo->updateLastLogin((int)$admin['id']);

            $loginMethod = ($normalizedIdentifier === strtolower($admin['email'])) ? 'email' : 'username';

            $stmtAudit = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    actor_type, admin_id, employee_id, action, entity_type, entity_id,
                    old_values, new_values, ip_address, user_agent, created_at
                ) VALUES (
                    'ADMIN', :admin_id, NULL, 'ADMIN_LOGIN_SUCCESS', 'admin', :entity_id,
                    NULL, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
                )
            ");
            $stmtAudit->execute([
                ':admin_id' => (int)$admin['id'],
                ':entity_id' => (int)$admin['id'],
                ':new_values' => json_encode([
                    'role' => $admin['role'],
                    'login_method' => $loginMethod,
                ], JSON_UNESCAPED_SLASHES),
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Admin login transaction failure: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'code' => 'INTERNAL_ERROR'
            ];
        }

        // Best-effort Record Successful Rate Limit Attempt
        try {
            RateLimitMiddleware::recordAttempt($rateLimitKey, $normalizedIdentifier, $ipAddress, true);
        } catch (\Throwable $e) {
            error_log("Failed to record successful rate limit attempt: " . $e->getMessage());
        }

        // Initialize Authenticated Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $now = time();
        $_SESSION['admin_auth'] = [
            'admin_id' => (int) $admin['id'],
            'role' => $admin['role'],
            'name' => $admin['name'],
            'authenticated_at' => $now,
            'last_activity_at' => $now,
            'absolute_start_at' => $now,
        ];

        // Safe Admin Payload (never return password_hash or credentials)
        $safeAdmin = [
            'id' => (int) $admin['id'],
            'name' => $admin['name'],
            'role' => $admin['role'],
            'is_active' => (int) $admin['is_active'],
        ];

        return [
            'success' => true,
            'admin' => $safeAdmin
        ];
    }

    /**
     * Admin logout handler.
     *
     * @param string $ipAddress
     * @param string $userAgent
     * @return void
     */
    public function logout(string $ipAddress = '', string $userAgent = ''): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['admin_auth']) && is_array($_SESSION['admin_auth']) && isset($_SESSION['admin_auth']['admin_id'])) {
            $adminId = (int)$_SESSION['admin_auth']['admin_id'];
            if ($adminId > 0) {
                try {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO audit_logs (
                            actor_type, admin_id, employee_id, action, entity_type, entity_id,
                            old_values, new_values, ip_address, user_agent, created_at
                        ) VALUES (
                            'ADMIN', :admin_id, NULL, 'ADMIN_LOGOUT', 'admin', :entity_id,
                            NULL, NULL, :ip_address, :user_agent, UTC_TIMESTAMP()
                        )
                    ");
                    $stmt->execute([
                        ':admin_id' => $adminId,
                        ':entity_id' => $adminId,
                        ':ip_address' => $ipAddress,
                        ':user_agent' => $userAgent,
                    ]);
                } catch (\Throwable $e) {
                    error_log("Failed to record admin logout audit: " . $e->getMessage());
                }
            }
        }

        unset($_SESSION['admin_auth']);
        session_regenerate_id(true);
    }

    /**
     * Record failed rate-limit attempt best-effort.
     */
    private function recordFailedAttemptBestEffort(string $actionKey, string $identifier, string $ip): void
    {
        try {
            RateLimitMiddleware::recordAttempt($actionKey, $identifier, $ip, false);
        } catch (\Throwable $e) {
            error_log("Best effort rate limit recording failed: " . $e->getMessage());
        }
    }

    /**
     * Record security event (best effort).
     */
    private function recordSecurityEvent(
        string $eventType,
        string $normalizedIdentifier,
        string $ipAddress,
        string $userAgent,
        string $failureClass
    ): void {
        try {
            $identifierHash = HashManager::hashRateLimitIdentifier($normalizedIdentifier);
            $stmt = $this->pdo->prepare("
                INSERT INTO security_events (
                    employee_id, attempt_id, identifier_hash, event_type, metadata, ip_address, user_agent, created_at
                ) VALUES (
                    NULL, NULL, :identifier_hash, :event_type, :metadata, :ip_address, :user_agent, UTC_TIMESTAMP()
                )
            ");
            $stmt->execute([
                ':identifier_hash' => $identifierHash,
                ':event_type' => $eventType,
                ':metadata' => json_encode([
                    'action_key' => 'admin_login',
                    'failure_class' => $failureClass,
                ], JSON_UNESCAPED_SLASHES),
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to record security event: " . $e->getMessage());
        }
    }
}
