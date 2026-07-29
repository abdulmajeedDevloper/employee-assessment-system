<?php

namespace App\Services;

use App\Repositories\AdminEmployeeRepository;
use App\Security\EncryptionService;
use App\Security\HashManager;
use App\Support\Database;
use App\Validators\AdminEmployeeFormValidator;
use PDO;
use PDOException;
use Throwable;

class AdminEmployeeManagementService
{
    private AdminEmployeeRepository $repository;
    private EncryptionService $encryptionService;
    private PDO $pdo;

    public function __construct(
        ?AdminEmployeeRepository $repository = null,
        ?EncryptionService $encryptionService = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->repository = $repository ?? new AdminEmployeeRepository($this->pdo);
        $this->encryptionService = $encryptionService ?? new EncryptionService();
    }

    /**
     * Get active reference data for form select rendering.
     *
     * @param int|null $employeeId
     * @return array{departments: array, sections: array, job_titles: array, employee: array|null}
     */
    public function getFormData(?int $employeeId = null): array
    {
        $employee = null;
        if ($employeeId !== null && $employeeId > 0) {
            $employee = $this->repository->findSafeById($employeeId);
        }

        return [
            'departments' => $this->repository->getDepartments(),
            'sections' => $this->repository->getSections(),
            'job_titles' => $this->repository->getJobTitles(),
            'employee' => $employee,
        ];
    }

    /**
     * Create a new employee safely with encryption, transactions, and audit logging.
     *
     * @param array $input
     * @param array $admin
     * @return array{success: bool, code?: string, errors?: array, employee_id?: int, message?: string}
     */
    public function createEmployee(array $input, array $admin): array
    {
        $departments = $this->repository->getDepartments();
        $sections = $this->repository->getSections();
        $jobTitles = $this->repository->getJobTitles();

        $val = AdminEmployeeFormValidator::validateCreate($input, $departments, $sections, $jobTitles);
        if (!$val['valid']) {
            return [
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'errors' => $val['errors'],
            ];
        }

        $data = $val['data'];
        $rawNationalId = $data['national_id'];
        unset($data['national_id']);

        // Check National ID hash duplicate
        $hashBin = HashManager::hashNationalId($rawNationalId);
        if ($this->repository->existsNationalIdHash($hashBin)) {
            return [
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'errors' => ['national_id' => trans('val_national_id_duplicate')],
            ];
        }

        // Check Employee Number duplicate
        if ($data['employee_number'] !== null && $this->repository->existsEmployeeNumber($data['employee_number'])) {
            return [
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'errors' => ['employee_number' => trans('val_employee_number_duplicate')],
            ];
        }

        // Encrypt National ID
        $enc = $this->encryptionService->encrypt($rawNationalId);
        $last4 = substr($rawNationalId, -4);
        unset($rawNationalId); // Wipe raw string

        $dbPayload = array_merge($data, [
            'national_id_hash' => $hashBin,
            'national_id_ciphertext' => $enc['ciphertext'],
            'national_id_iv' => $enc['iv'],
            'national_id_tag' => $enc['tag'],
            'key_version' => $enc['key_version'] ?? 1,
            'national_id_last4' => $last4,
        ]);

        try {
            $this->pdo->beginTransaction();

            $empId = $this->repository->createEmployee($dbPayload);

            // Safe Audit Log Record
            $safeNewValues = [
                'full_name' => $data['full_name'],
                'employee_number' => $data['employee_number'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'department_id' => $data['department_id'],
                'section_id' => $data['section_id'],
                'job_title_id' => $data['job_title_id'],
                'preferred_language' => $data['preferred_language'],
                'is_active' => $data['is_active'],
            ];

            $adminId = (int)($admin['id'] ?? 0);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

            $auditStmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    actor_type, admin_id, employee_id, action, entity_type, entity_id,
                    old_values, new_values, ip_address, user_agent, created_at
                ) VALUES (
                    'ADMIN', :admin_id, NULL, 'CREATE_EMPLOYEE', 'EMPLOYEE', :entity_id,
                    NULL, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
                )
            ");
            $auditStmt->execute([
                ':admin_id' => $adminId,
                ':entity_id' => $empId,
                ':new_values' => json_encode($safeNewValues, JSON_UNESCAPED_UNICODE),
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'employee_id' => $empId,
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                $msg = $e->getMessage();
                if (str_contains($msg, 'national_id_hash')) {
                    return [
                        'success' => false,
                        'code' => 'VALIDATION_FAILED',
                        'errors' => ['national_id' => trans('val_national_id_duplicate')],
                    ];
                }
                if (str_contains($msg, 'employee_number')) {
                    return [
                        'success' => false,
                        'code' => 'VALIDATION_FAILED',
                        'errors' => ['employee_number' => trans('val_employee_number_duplicate')],
                    ];
                }
            }

            error_log("AdminEmployeeManagementService create error: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'message' => trans('temporary_error'),
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log("AdminEmployeeManagementService create error: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'message' => trans('temporary_error'),
            ];
        }
    }

    /**
     * Update an existing employee safely.
     *
     * @param int $id
     * @param array $input
     * @param array $admin
     * @return array{success: bool, code?: string, errors?: array, employee_id?: int, message?: string}
     */
    public function updateEmployee(int $id, array $input, array $admin): array
    {
        $existing = $this->repository->findSafeById($id);
        if (!$existing || !empty($existing['archived_at'])) {
            return [
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => trans('employee_not_found'),
            ];
        }

        $departments = $this->repository->getDepartments();
        $sections = $this->repository->getSections();
        $jobTitles = $this->repository->getJobTitles();

        $val = AdminEmployeeFormValidator::validateEdit($input, $existing, $departments, $sections, $jobTitles);
        if (!$val['valid']) {
            return [
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'errors' => $val['errors'],
            ];
        }

        $data = $val['data'];
        $replaceNationalId = !empty($data['replace_national_id']);
        unset($data['replace_national_id']);

        $hashBin = null;
        $enc = null;
        $last4 = null;

        if ($replaceNationalId && isset($data['national_id'])) {
            $rawNationalId = $data['national_id'];
            unset($data['national_id']);

            $hashBin = HashManager::hashNationalId($rawNationalId);
            if ($this->repository->existsNationalIdHash($hashBin, $id)) {
                return [
                    'success' => false,
                    'code' => 'VALIDATION_FAILED',
                    'errors' => ['national_id' => trans('val_national_id_duplicate')],
                ];
            }

            $enc = $this->encryptionService->encrypt($rawNationalId);
            $last4 = substr($rawNationalId, -4);
            unset($rawNationalId); // Wipe raw string
        } else {
            unset($data['national_id']);
        }

        // Check Employee Number duplicate excluding current employee
        if ($data['employee_number'] !== null && $this->repository->existsEmployeeNumber($data['employee_number'], $id)) {
            return [
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'errors' => ['employee_number' => trans('val_employee_number_duplicate')],
            ];
        }

        // Compute safe audit diff
        $safeFields = [
            'full_name', 'employee_number', 'email', 'phone',
            'department_id', 'section_id', 'job_title_id',
            'preferred_language', 'is_active'
        ];

        $oldDiff = [];
        $newDiff = [];

        foreach ($safeFields as $field) {
            $oldVal = $existing[$field] ?? null;
            $newVal = $data[$field] ?? null;

            // Handle type coercions for integer IDs
            if (in_array($field, ['department_id', 'section_id', 'job_title_id', 'is_active'], true)) {
                $oldVal = (int)$oldVal;
                $newVal = (int)$newVal;
            }

            if ($oldVal !== $newVal) {
                $oldDiff[$field] = $oldVal;
                $newDiff[$field] = $newVal;
            }
        }

        if ($replaceNationalId) {
            $newDiff['national_id_updated'] = true;
        }

        $dbPayload = $data;
        if ($replaceNationalId && $hashBin !== null && $enc !== null && $last4 !== null) {
            $dbPayload['national_id_hash'] = $hashBin;
            $dbPayload['national_id_ciphertext'] = $enc['ciphertext'];
            $dbPayload['national_id_iv'] = $enc['iv'];
            $dbPayload['national_id_tag'] = $enc['tag'];
            $dbPayload['key_version'] = $enc['key_version'] ?? 1;
            $dbPayload['national_id_last4'] = $last4;
        }

        try {
            $this->pdo->beginTransaction();

            $this->repository->updateEmployee($id, $dbPayload, $replaceNationalId);

            $adminId = (int)($admin['id'] ?? 0);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

            $auditStmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    actor_type, admin_id, employee_id, action, entity_type, entity_id,
                    old_values, new_values, ip_address, user_agent, created_at
                ) VALUES (
                    'ADMIN', :admin_id, NULL, 'UPDATE_EMPLOYEE', 'EMPLOYEE', :entity_id,
                    :old_values, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
                )
            ");
            $auditStmt->execute([
                ':admin_id' => $adminId,
                ':entity_id' => $id,
                ':old_values' => !empty($oldDiff) ? json_encode($oldDiff, JSON_UNESCAPED_UNICODE) : null,
                ':new_values' => !empty($newDiff) ? json_encode($newDiff, JSON_UNESCAPED_UNICODE) : null,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'employee_id' => $id,
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                $msg = $e->getMessage();
                if (str_contains($msg, 'national_id_hash')) {
                    return [
                        'success' => false,
                        'code' => 'VALIDATION_FAILED',
                        'errors' => ['national_id' => trans('val_national_id_duplicate')],
                    ];
                }
                if (str_contains($msg, 'employee_number')) {
                    return [
                        'success' => false,
                        'code' => 'VALIDATION_FAILED',
                        'errors' => ['employee_number' => trans('val_employee_number_duplicate')],
                    ];
                }
            }

            error_log("AdminEmployeeManagementService update error: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'message' => trans('temporary_error'),
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log("AdminEmployeeManagementService update error: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'message' => trans('temporary_error'),
            ];
        }
    }

    /**
     * Soft archive employee atomically with transaction and audit logging.
     *
     * @param mixed $rawId
     * @param array $admin Authenticated Admin session array
     * @return array
     */
    public function archiveEmployee(mixed $rawId, array $admin): array
    {
        $id = $this->parsePositiveInt($rawId);
        if ($id === null) {
            return ['success' => false, 'code' => 'INVALID_ID'];
        }

        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return ['success' => false, 'code' => 'UNAUTHORIZED'];
        }

        try {
            $this->pdo->beginTransaction();

            $emp = $this->repository->findEmployeeStateForUpdate($id);
            if (!$emp) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'code' => 'NOT_FOUND'];
            }

            if (!empty($emp['archived_at'])) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return [
                    'success' => false,
                    'code' => 'ALREADY_ARCHIVED',
                    'employee_id' => $id,
                ];
            }

            $updated = $this->repository->archiveEmployeeState($id);
            if (!$updated) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'code' => 'INTERNAL_ERROR'];
            }

            $oldState = [
                'is_active' => (int)$emp['is_active'],
                'archive_status' => 'not_archived',
            ];
            $newState = [
                'is_active' => 0,
                'archive_status' => 'archived',
            ];

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

            $auditStmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    actor_type, admin_id, employee_id, action, entity_type, entity_id,
                    old_values, new_values, ip_address, user_agent, created_at
                ) VALUES (
                    'ADMIN', :admin_id, NULL, 'ARCHIVE_EMPLOYEE', 'EMPLOYEE', :entity_id,
                    :old_values, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
                )
            ");
            $auditStmt->execute([
                ':admin_id' => $adminId,
                ':entity_id' => $id,
                ':old_values' => json_encode($oldState, JSON_UNESCAPED_UNICODE),
                ':new_values' => json_encode($newState, JSON_UNESCAPED_UNICODE),
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'employee_id' => $id,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log("AdminEmployeeManagementService archive error: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'message' => trans('temporary_error'),
            ];
        }
    }

    /**
     * Soft restore employee atomically with transaction and audit logging.
     * Restored employee remains inactive (is_active = 0).
     *
     * @param mixed $rawId
     * @param array $admin Authenticated Admin session array
     * @return array
     */
    public function restoreEmployee(mixed $rawId, array $admin): array
    {
        $id = $this->parsePositiveInt($rawId);
        if ($id === null) {
            return ['success' => false, 'code' => 'INVALID_ID'];
        }

        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return ['success' => false, 'code' => 'UNAUTHORIZED'];
        }

        try {
            $this->pdo->beginTransaction();

            $emp = $this->repository->findEmployeeStateForUpdate($id);
            if (!$emp) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'code' => 'NOT_FOUND'];
            }

            if (empty($emp['archived_at'])) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return [
                    'success' => false,
                    'code' => 'NOT_ARCHIVED',
                    'employee_id' => $id,
                ];
            }

            $updated = $this->repository->restoreEmployeeStateInactive($id);
            if (!$updated) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'code' => 'INTERNAL_ERROR'];
            }

            $oldState = [
                'is_active' => 0,
                'archive_status' => 'archived',
            ];
            $newState = [
                'is_active' => 0,
                'archive_status' => 'restored_inactive',
            ];

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

            $auditStmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    actor_type, admin_id, employee_id, action, entity_type, entity_id,
                    old_values, new_values, ip_address, user_agent, created_at
                ) VALUES (
                    'ADMIN', :admin_id, NULL, 'RESTORE_EMPLOYEE', 'EMPLOYEE', :entity_id,
                    :old_values, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
                )
            ");
            $auditStmt->execute([
                ':admin_id' => $adminId,
                ':entity_id' => $id,
                ':old_values' => json_encode($oldState, JSON_UNESCAPED_UNICODE),
                ':new_values' => json_encode($newState, JSON_UNESCAPED_UNICODE),
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'employee_id' => $id,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log("AdminEmployeeManagementService restore error: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'message' => trans('temporary_error'),
            ];
        }
    }

    /**
     * Strict positive integer parser (accepts int >= 1 or digit-only string >= 1).
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
