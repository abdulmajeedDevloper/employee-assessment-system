<?php

namespace App\Services;

use App\Repositories\AdminUserRepository;
use App\Validators\AdminUserFormValidator;
use App\Support\Database;
use PDO;

class AdminUserManagementService
{
    private AdminUserRepository $userRepo;
    private AdminUserFormValidator $validator;
    private AdminAuthService $authService;
    private PDO $pdo;

    public function __construct(
        ?AdminUserRepository $userRepo = null,
        ?AdminUserFormValidator $validator = null,
        ?AdminAuthService $authService = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->userRepo = $userRepo ?? new AdminUserRepository($this->pdo);
        $this->validator = $validator ?? new AdminUserFormValidator($this->userRepo);
        $this->authService = $authService ?? new AdminAuthService(null, $this->pdo);
    }

    /**
     * List administrators with search, filters, and pagination.
     */
    public function listAdmins(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        return $this->userRepo->getPaginatedAdmins($filters, $page, $perPage);
    }

    /**
     * Find admin by ID.
     */
    public function findAdminById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->userRepo->findById($id);
    }

    /**
     * Create a new secondary administrator account within a transaction.
     */
    public function createAdmin(array $input, array $actingAdmin, string $ipAddress = '', string $userAgent = ''): array
    {
        $validation = $this->validator->validateCreate($input);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'message' => 'Validation failed. Please correct the highlighted errors.',
            ];
        }

        $data = $validation['data'];
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $this->pdo->beginTransaction();

            $newAdminId = $this->userRepo->createAdmin([
                'display_name' => $data['display_name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $passwordHash,
                'role' => $data['role'],
                'is_active' => $data['is_active'],
            ]);

            // Record Audit Event
            $this->recordAuditEvent(
                (int)$actingAdmin['id'],
                $newAdminId,
                'ADMIN_ACCOUNT_CREATED',
                null,
                [
                    'display_name' => $data['display_name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'role' => $data['role'],
                    'is_active' => $data['is_active'],
                ],
                $ipAddress,
                $userAgent
            );

            $this->pdo->commit();

            return [
                'success' => true,
                'admin_id' => $newAdminId,
                'message' => 'Admin account created successfully.',
            ];
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                return [
                    'success' => false,
                    'errors' => ['username' => 'Username or email already exists.'],
                    'message' => 'Username or email address already exists in the system.',
                ];
            }
            error_log("Error creating admin account: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected database error occurred.',
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error creating admin account: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ];
        }
    }

    /**
     * Update administrator account details (display_name, username, email, role, is_active).
     */
    public function updateAdmin(int $targetId, array $input, array $actingAdmin, string $ipAddress = '', string $userAgent = ''): array
    {
        if ($targetId <= 0) {
            return ['success' => false, 'message' => 'Invalid target administrator ID.'];
        }

        try {
            $this->pdo->beginTransaction();

            // Lock target account row FOR UPDATE
            $targetAdmin = $this->userRepo->findByIdForUpdate($targetId);
            if (!$targetAdmin) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Target administrator account not found.'];
            }

            // Validate Input and Protections
            $validation = $this->validator->validateUpdate($input, $targetAdmin, $actingAdmin);
            if (!$validation['valid']) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'errors' => $validation['errors'],
                    'message' => 'Validation failed. Please correct the highlighted errors.',
                ];
            }

            $data = $validation['data'];
            $isSelf = ((int)$targetAdmin['id'] === (int)$actingAdmin['id']);

            // Lock Active Super Admins to protect last super admin concurrency
            $activeSuperAdmins = $this->userRepo->lockActiveSuperAdmins();
            $activeSuperAdminCount = count($activeSuperAdmins);

            $isDemoting = ($targetAdmin['role'] === 'super_admin' && $data['role'] !== 'super_admin');
            $isDeactivating = ((int)$targetAdmin['is_active'] === 1 && $data['is_active'] === 0);

            if (($isDemoting || $isDeactivating) && $activeSuperAdminCount <= 1 && $targetAdmin['role'] === 'super_admin') {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Operation aborted: The system must retain at least one active super administrator.',
                ];
            }

            // Determine if auth_version needs incrementing (role, status, username, email changes)
            $sensitiveChange = (
                $targetAdmin['role'] !== $data['role'] ||
                (int)$targetAdmin['is_active'] !== (int)$data['is_active'] ||
                strtolower($targetAdmin['username']) !== strtolower($data['username']) ||
                strtolower($targetAdmin['email']) !== strtolower($data['email'])
            );

            // Execute Database Update
            $this->userRepo->updateAdmin($targetId, $data, $sensitiveChange);

            // Audit Event Recording
            $oldValues = [
                'display_name' => $targetAdmin['display_name'],
                'username' => $targetAdmin['username'],
                'email' => $targetAdmin['email'],
                'role' => $targetAdmin['role'],
                'is_active' => (int)$targetAdmin['is_active'],
            ];
            $newValues = [
                'display_name' => $data['display_name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'role' => $data['role'],
                'is_active' => (int)$data['is_active'],
            ];

            $action = 'ADMIN_ACCOUNT_UPDATED';
            if ($targetAdmin['role'] !== $data['role']) {
                $action = 'ADMIN_ROLE_CHANGED';
            } elseif ((int)$targetAdmin['is_active'] !== (int)$data['is_active']) {
                $action = ($data['is_active'] === 1) ? 'ADMIN_ACCOUNT_ACTIVATED' : 'ADMIN_ACCOUNT_DEACTIVATED';
            }

            $this->recordAuditEvent(
                (int)$actingAdmin['id'],
                $targetId,
                $action,
                $oldValues,
                $newValues,
                $ipAddress,
                $userAgent
            );

            $this->pdo->commit();

            // Refresh Acting Session if updating self
            if ($isSelf && isset($_SESSION['admin_auth'])) {
                if ($sensitiveChange) {
                    session_regenerate_id(true);
                    $freshTarget = $this->userRepo->findById($targetId);
                    $_SESSION['admin_auth']['auth_version'] = (int)($freshTarget['auth_version'] ?? 1);
                }
                $_SESSION['admin_auth']['display_name'] = $data['display_name'];
                $_SESSION['admin_auth']['name'] = $data['display_name'];
                $_SESSION['admin_auth']['role'] = $data['role'];
            }

            return [
                'success' => true,
                'message' => 'Admin account updated successfully.',
            ];
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                return [
                    'success' => false,
                    'errors' => ['username' => 'Username or email address is already in use by another account.'],
                    'message' => 'Username or email address already exists.',
                ];
            }
            error_log("Error updating admin account: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected database error occurred.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error updating admin account: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred.'];
        }
    }

    /**
     * Change administrator password safely.
     */
    public function changePassword(
        int $targetId,
        string $currentPassword,
        string $newPassword,
        string $newPasswordConfirm,
        array $actingAdmin,
        string $ipAddress = '',
        string $userAgent = ''
    ): array {
        if ($targetId <= 0) {
            return ['success' => false, 'message' => 'Invalid target administrator ID.'];
        }

        $actingId = (int)$actingAdmin['id'];
        $isSelf = ($targetId === $actingId);

        // 1. Verify acting user's current password
        if (empty($currentPassword)) {
            return [
                'success' => false,
                'errors' => ['current_password' => 'Your current password is required for confirmation.'],
                'message' => 'Current password is required.',
            ];
        }

        if (!$this->authService->verifyCurrentPassword($actingId, $currentPassword)) {
            return [
                'success' => false,
                'errors' => ['current_password' => 'Invalid current password entered.'],
                'message' => 'Current password verification failed.',
            ];
        }

        // 2. Validate new password strength policy & confirmation match
        if (empty($newPassword)) {
            return [
                'success' => false,
                'errors' => ['new_password' => 'New password is required.'],
                'message' => 'New password is required.',
            ];
        }

        if (!AdminUserFormValidator::isPasswordPolicyCompliant($newPassword)) {
            return [
                'success' => false,
                'errors' => ['new_password' => 'New password fails security policy (minimum 12 characters, including uppercase, lowercase, digit, and symbol).'],
                'message' => 'Password policy requirement not met.',
            ];
        }

        if ($newPassword !== $newPasswordConfirm) {
            return [
                'success' => false,
                'errors' => ['confirm_password' => 'Password confirmation does not match.'],
                'message' => 'Password confirmation mismatch.',
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $targetAdmin = $this->userRepo->findByIdForUpdate($targetId);
            if (!$targetAdmin) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Target administrator account not found.'];
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->userRepo->updatePasswordAndIncrementAuthVersion($targetId, $newHash);

            // Record Audit Log (metadata contains NO passwords or hashes)
            $this->recordAuditEvent(
                $actingId,
                $targetId,
                'ADMIN_PASSWORD_CHANGED',
                null,
                ['target_id' => $targetId, 'is_self' => $isSelf],
                $ipAddress,
                $userAgent
            );

            $this->pdo->commit();

            // Refresh Acting Session if changing self password
            if ($isSelf && isset($_SESSION['admin_auth'])) {
                session_regenerate_id(true);
                $freshTarget = $this->userRepo->findById($targetId);
                $_SESSION['admin_auth']['auth_version'] = (int)($freshTarget['auth_version'] ?? 1);
            }

            return [
                'success' => true,
                'message' => 'Password changed successfully.',
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error changing admin password: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred while updating password.'];
        }
    }

    /**
     * Activate or deactivate a secondary administrator account.
     */
    public function toggleStatus(int $targetId, int $newStatus, array $actingAdmin, string $ipAddress = '', string $userAgent = ''): array
    {
        if ($targetId <= 0) {
            return ['success' => false, 'message' => 'Invalid target administrator ID.'];
        }

        $actingId = (int)$actingAdmin['id'];
        $newStatus = ($newStatus === 1) ? 1 : 0;

        // Prohibit self-deactivation
        if ($targetId === $actingId && $newStatus === 0) {
            return [
                'success' => false,
                'message' => 'You cannot deactivate your own active account session.',
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $targetAdmin = $this->userRepo->findByIdForUpdate($targetId);
            if (!$targetAdmin) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Target administrator account not found.'];
            }

            // Prohibit primary admin deactivation
            if ((int)($targetAdmin['is_primary'] ?? 0) === 1 && $newStatus === 0) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'The primary administrator account cannot be deactivated.',
                ];
            }

            // Concurrency Lock Active Super Admins
            if ($targetAdmin['role'] === 'super_admin' && $newStatus === 0) {
                $activeSuperAdmins = $this->userRepo->lockActiveSuperAdmins();
                if (count($activeSuperAdmins) <= 1) {
                    $this->pdo->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Operation aborted: The system must retain at least one active super administrator.',
                    ];
                }
            }

            $this->userRepo->updateStatus($targetId, $newStatus);

            $action = ($newStatus === 1) ? 'ADMIN_ACCOUNT_ACTIVATED' : 'ADMIN_ACCOUNT_DEACTIVATED';
            $this->recordAuditEvent(
                $actingId,
                $targetId,
                $action,
                ['is_active' => (int)$targetAdmin['is_active']],
                ['is_active' => $newStatus],
                $ipAddress,
                $userAgent
            );

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => ($newStatus === 1) ? 'Account activated successfully.' : 'Account deactivated successfully.',
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error toggling admin status: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred while toggling account status.'];
        }
    }

    /**
     * Record security-safe audit event.
     */
    private function recordAuditEvent(
        int $actorAdminId,
        int $targetAdminId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        string $ipAddress,
        string $userAgent
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs (
                actor_type, admin_id, employee_id, action, entity_type, entity_id,
                old_values, new_values, ip_address, user_agent, created_at
            ) VALUES (
                'ADMIN', :admin_id, NULL, :action, 'admin', :entity_id,
                :old_values, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([
            ':admin_id' => $actorAdminId,
            ':action' => $action,
            ':entity_id' => $targetAdminId,
            ':old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
            ':new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
        ]);
    }
}
