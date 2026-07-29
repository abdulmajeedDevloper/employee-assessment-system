<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminAssignmentRepository;
use App\Support\Database;
use App\Validators\AdminAssignmentFormValidator;
use PDO;
use PDOException;
use Throwable;

class AdminAssignmentManagementService
{
    private AdminAssignmentRepository $repository;
    private AdminAssignmentFormValidator $validator;
    private PDO $pdo;

    public function __construct(
        ?AdminAssignmentRepository $repository = null,
        ?AdminAssignmentFormValidator $validator = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->repository = $repository ?? new AdminAssignmentRepository($this->pdo);
        $this->validator = $validator ?? new AdminAssignmentFormValidator();
    }

    public function getCreateFormData(): array
    {
        return [
            'employees' => $this->repository->getActiveEmployees(),
            'tests' => $this->repository->getActiveTests(),
        ];
    }

    public function issueEditToken(int $adminId, int $assignmentId, array $assignment): string
    {
        $this->cleanTokens();
        $token = bin2hex(random_bytes(32));
        $_SESSION['admin_assignment_edit_tokens'][$token] = [
            'admin_id' => $adminId,
            'assignment_id' => $assignmentId,
            'fingerprint' => $this->fingerprint($assignment),
            'created_at' => time(),
        ];

        if (count($_SESSION['admin_assignment_edit_tokens']) > 20) {
            uasort($_SESSION['admin_assignment_edit_tokens'], static fn(array $a, array $b): int => ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0));
            while (count($_SESSION['admin_assignment_edit_tokens']) > 20) {
                array_shift($_SESSION['admin_assignment_edit_tokens']);
            }
        }
        return $token;
    }

    public function createAssignment(array $input, int $adminId): array
    {
        $formData = $this->getCreateFormData();
        $validated = $this->validator->validateCreate($input, $formData['employees'], $formData['tests']);
        if (!$validated['valid']) {
            return ['code' => 'VALIDATION_ERROR', 'errors' => $validated['errors'], 'old' => $validated['old']];
        }
        $data = $validated['data'];

        if ($this->repository->assignmentExists((int)$data['employee_id'], (int)$data['test_id'])) {
            return [
                'code' => 'DUPLICATE_ASSIGNMENT',
                'errors' => ['test_id' => trans('duplicate_assignment')],
                'old' => $validated['old'],
            ];
        }

        try {
            $this->pdo->beginTransaction();
            $employeeAssignable = $this->repository->lockAssignableEmployee((int)$data['employee_id']);
            $testAssignable = $this->repository->lockAssignableTest((int)$data['test_id']);
            if (!$employeeAssignable || !$testAssignable) {
                $this->pdo->rollBack();
                $errors = [];
                if (!$employeeAssignable) {
                    $errors['employee_id'] = trans('assignment_employee_required');
                }
                if (!$testAssignable) {
                    $errors['test_id'] = trans('assignment_test_required');
                }
                return [
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $errors,
                    'old' => $validated['old'],
                ];
            }

            if ($this->repository->assignmentExists((int)$data['employee_id'], (int)$data['test_id'])) {
                $this->pdo->rollBack();
                return [
                    'code' => 'DUPLICATE_ASSIGNMENT',
                    'errors' => ['test_id' => trans('duplicate_assignment')],
                    'old' => $validated['old'],
                ];
            }

            $assignmentId = $this->repository->create($data, $adminId);
            $this->logAudit($adminId, 'CREATE_ASSIGNMENT', $assignmentId, null, [
                'created_field_names' => [
                    'employee_id', 'test_id', 'max_attempts', 'available_from', 'available_until', 'status'
                ],
            ]);
            $this->pdo->commit();
            return ['code' => 'SUCCESS', 'assignment_id' => $assignmentId];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                return [
                    'code' => 'DUPLICATE_ASSIGNMENT',
                    'errors' => ['test_id' => trans('duplicate_assignment')],
                    'old' => $validated['old'],
                ];
            }
            error_log('AdminAssignmentManagementService create SQL error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AdminAssignmentManagementService create error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    public function updateAssignment(int $assignmentId, string $editToken, array $input, int $adminId): array
    {
        $tokenData = $this->validateToken($editToken, $adminId, $assignmentId);
        if ($tokenData === null) {
            return ['code' => 'CONFLICT', 'message' => trans('concurrent_assignment_update_conflict')];
        }

        try {
            $this->pdo->beginTransaction();
            $current = $this->repository->findForUpdate($assignmentId);
            if ($current === null) {
                $this->pdo->rollBack();
                $this->consumeToken($editToken);
                return ['code' => 'NOT_FOUND'];
            }
            if (!hash_equals((string)$tokenData['fingerprint'], $this->fingerprint($current))) {
                $this->pdo->rollBack();
                $this->consumeToken($editToken);
                return ['code' => 'CONFLICT', 'message' => trans('concurrent_assignment_update_conflict')];
            }
            if ((string)$current['status'] !== 'PENDING') {
                $this->pdo->rollBack();
                $this->consumeToken($editToken);
                return ['code' => 'STATUS_LOCKED', 'message' => trans('assignment_edit_status_locked')];
            }

            $attemptCount = $this->repository->countAttempts($assignmentId);
            if ($attemptCount > 0) {
                $this->pdo->rollBack();
                $this->consumeToken($editToken);
                return ['code' => 'STATUS_LOCKED', 'message' => trans('assignment_edit_attempt_locked')];
            }

            $validated = $this->validator->validateUpdate($input, $attemptCount);
            if (!$validated['valid']) {
                $this->pdo->rollBack();
                $this->consumeToken($editToken);
                return ['code' => 'VALIDATION_ERROR', 'errors' => $validated['errors'], 'old' => $validated['old']];
            }
            $data = $validated['data'];
            $data['employee_id'] = (int)$current['employee_id'];
            $data['test_id'] = (int)$current['test_id'];

            $changedFields = [];
            if ((int)$current['max_attempts'] !== (int)$data['max_attempts']) {
                $changedFields[] = 'max_attempts';
            }
            if ($this->normalizeDate($current['available_from'] ?? null) !== $data['available_from']) {
                $changedFields[] = 'available_from';
            }
            if ($this->normalizeDate($current['available_until'] ?? null) !== $data['available_until']) {
                $changedFields[] = 'available_until';
            }

            if ($changedFields === []) {
                $this->pdo->rollBack();
                $this->consumeToken($editToken);
                return ['code' => 'NO_CHANGES', 'assignment_id' => $assignmentId];
            }

            if (!$this->repository->updatePending($assignmentId, $data)) {
                $this->pdo->rollBack();
                $this->consumeToken($editToken);
                return ['code' => 'CONFLICT', 'message' => trans('concurrent_assignment_update_conflict')];
            }

            $audit = ['changed_field_names' => $changedFields];
            $this->logAudit($adminId, 'UPDATE_ASSIGNMENT', $assignmentId, $audit, $audit);
            $this->pdo->commit();
            $this->consumeToken($editToken);
            return ['code' => 'SUCCESS', 'assignment_id' => $assignmentId];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->consumeToken($editToken);
            error_log('AdminAssignmentManagementService update error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    private function validateToken(string $token, int $adminId, int $assignmentId): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }
        $this->cleanTokens();
        $data = $_SESSION['admin_assignment_edit_tokens'][$token] ?? null;
        if (!is_array($data)
            || (int)($data['admin_id'] ?? 0) !== $adminId
            || (int)($data['assignment_id'] ?? 0) !== $assignmentId
            || time() - (int)($data['created_at'] ?? 0) > 7200
        ) {
            unset($_SESSION['admin_assignment_edit_tokens'][$token]);
            return null;
        }
        return $data;
    }

    private function consumeToken(string $token): void
    {
        unset($_SESSION['admin_assignment_edit_tokens'][$token]);
    }

    private function cleanTokens(): void
    {
        if (!isset($_SESSION['admin_assignment_edit_tokens']) || !is_array($_SESSION['admin_assignment_edit_tokens'])) {
            $_SESSION['admin_assignment_edit_tokens'] = [];
            return;
        }
        $now = time();
        foreach ($_SESSION['admin_assignment_edit_tokens'] as $token => $data) {
            if (!is_array($data) || $now - (int)($data['created_at'] ?? 0) > 7200) {
                unset($_SESSION['admin_assignment_edit_tokens'][$token]);
            }
        }
    }

    private function fingerprint(array $row): string
    {
        return hash('sha256', json_encode([
            'id' => (int)($row['id'] ?? 0),
            'employee_id' => (int)($row['employee_id'] ?? 0),
            'test_id' => (int)($row['test_id'] ?? 0),
            'max_attempts' => (int)($row['max_attempts'] ?? 0),
            'status' => (string)($row['status'] ?? ''),
            'available_from' => $this->normalizeDate($row['available_from'] ?? null),
            'available_until' => $this->normalizeDate($row['available_until'] ?? null),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string)$value;
    }

    private function logAudit(int $adminId, string $action, int $entityId, ?array $oldValues, ?array $newValues): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs (
                actor_type, admin_id, employee_id, action, entity_type, entity_id,
                old_values, new_values, ip_address, user_agent, created_at
            ) VALUES (
                'ADMIN', :admin_id, NULL, :action, 'TEST_ASSIGNMENT', :entity_id,
                :old_values, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':entity_id' => $entityId,
            ':old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
        ]);
    }
}
