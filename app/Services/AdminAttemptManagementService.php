<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminAttemptRepository;
use App\Support\Database;
use App\Validators\AdminAttemptActionValidator;
use PDO;
use PDOException;
use Throwable;

class AdminAttemptManagementService
{
    private const TOKEN_TTL_SECONDS = 7200;
    private const MAX_SESSION_TOKENS = 20;
    private const ACTIONS = ['cancel', 'retake', 'notes', 'finalize'];

    private PDO $pdo;

    public function __construct(
        private ?AdminAttemptRepository $repository = null,
        private ?AdminAttemptActionValidator $validator = null,
        ?PDO $pdo = null,
        private ?AssessmentAttemptService $attemptService = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->repository ??= new AdminAttemptRepository($this->pdo);
        $this->validator ??= new AdminAttemptActionValidator();
        $this->attemptService ??= new AssessmentAttemptService($this->pdo);
    }

    public function issueActionToken(int $adminId, int $attemptId, string $action, array $attempt): string
    {
        if ($adminId <= 0 || $attemptId <= 0 || !in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException('Invalid attempt action token request.');
        }

        $this->cleanTokens();
        $token = bin2hex(random_bytes(32));
        $_SESSION['admin_attempt_action_tokens'][$token] = [
            'admin_id' => $adminId,
            'attempt_id' => $attemptId,
            'action' => $action,
            'fingerprint' => $this->fingerprint($attempt),
            'created_at' => time(),
        ];

        if (count($_SESSION['admin_attempt_action_tokens']) > self::MAX_SESSION_TOKENS) {
            uasort(
                $_SESSION['admin_attempt_action_tokens'],
                static fn(array $a, array $b): int => (int)($a['created_at'] ?? 0) <=> (int)($b['created_at'] ?? 0)
            );
            while (count($_SESSION['admin_attempt_action_tokens']) > self::MAX_SESSION_TOKENS) {
                $oldest = array_key_first($_SESSION['admin_attempt_action_tokens']);
                if ($oldest === null) {
                    break;
                }
                unset($_SESSION['admin_attempt_action_tokens'][$oldest]);
            }
        }

        return $token;
    }

    public function cancelAttempt(int $attemptId, string $token, array $input, int $adminId): array
    {
        $tokenData = $this->validateToken($token, $adminId, $attemptId, 'cancel');
        if ($tokenData === null) {
            return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
        }

        $validated = $this->validator->validateCancellation($input);
        if (!$validated['valid']) {
            return ['code' => 'VALIDATION_ERROR', 'errors' => $validated['errors']];
        }

        try {
            $this->pdo->beginTransaction();
            $attempt = $this->repository->findForUpdate($attemptId);
            if ($attempt === null) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'NOT_FOUND'];
            }
            if (!$this->matchesFingerprint($tokenData, $attempt)) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
            }
            if ((string)$attempt['status'] !== 'IN_PROGRESS') {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'STATUS_LOCKED', 'message' => trans('attempt_cancel_status_locked')];
            }
            if (strtotime((string)$attempt['expires_at'] . ' UTC') <= time()) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'EXPIRED', 'message' => trans('attempt_cancel_expired_use_finalize')];
            }

            if (!$this->repository->cancelInProgressAttempt($attemptId, $adminId, $validated['reason'])) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
            }

            $this->repository->closeActiveSessions($attemptId, 'CLOSED');
            $this->repository->updateAssignmentStatus((int)$attempt['assignment_id'], 'COMPLETED');
            $this->repository->recomputeEmployeeProgress((int)$attempt['employee_id']);

            $audit = ['changed_field_names' => [
                'status', 'submission_reason', 'submitted_at', 'cancelled_by',
                'cancelled_at', 'cancellation_reason', 'test_session_status',
                'assignment_status', 'assessment_progress',
            ]];
            $this->repository->logAdminAudit($adminId, 'CANCEL_ATTEMPT', $attemptId, $audit, $audit);

            $this->pdo->commit();
            $this->consumeToken($token);
            return ['code' => 'SUCCESS', 'attempt_id' => $attemptId];
        } catch (Throwable $e) {
            $this->rollBack();
            $this->consumeToken($token);
            error_log('AdminAttemptManagementService cancel error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    public function authorizeRetake(int $attemptId, string $token, array $input, int $adminId): array
    {
        $tokenData = $this->validateToken($token, $adminId, $attemptId, 'retake');
        if ($tokenData === null) {
            return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
        }

        $validated = $this->validator->validateRetake($input);
        if (!$validated['valid']) {
            return ['code' => 'VALIDATION_ERROR', 'errors' => $validated['errors']];
        }

        try {
            $this->pdo->beginTransaction();
            $attempt = $this->repository->findForUpdate($attemptId);
            if ($attempt === null) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'NOT_FOUND'];
            }
            if (!$this->matchesFingerprint($tokenData, $attempt)) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
            }

            if (!in_array((string)$attempt['status'], ['COMPLETED', 'TIMED_OUT', 'CANCELLED'], true)) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'STATUS_LOCKED', 'message' => trans('attempt_retake_status_locked')];
            }
            if (!$this->repository->isLatestAttemptForAssignment($attemptId, (int)$attempt['assignment_id'])) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'NOT_LATEST', 'message' => trans('attempt_retake_latest_only')];
            }
            if ($this->repository->hasActiveAttemptForEmployee((int)$attempt['employee_id'])) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'ACTIVE_ATTEMPT', 'message' => trans('attempt_retake_active_attempt_exists')];
            }
            if ($this->repository->hasPendingRetakeAuthorization((int)$attempt['assignment_id'])) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'ALREADY_AUTHORIZED', 'message' => trans('attempt_retake_already_authorized')];
            }

            $attemptNumber = (int)$attempt['attempt_number'];
            if ($attemptNumber >= 20) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'LIMIT_REACHED', 'message' => trans('attempt_retake_limit_reached')];
            }

            $newMaxAttempts = max((int)$attempt['max_attempts'], $attemptNumber + 1);
            $newMaxAttempts = min(20, $newMaxAttempts);

            $this->repository->createRetakeAuthorization(
                (int)$attempt['assignment_id'],
                $attemptId,
                $adminId,
                $validated['reason']
            );
            $this->repository->updateAssignmentStatus(
                (int)$attempt['assignment_id'],
                'RETAKE_AUTHORIZED',
                $newMaxAttempts
            );
            $this->repository->recomputeEmployeeProgress((int)$attempt['employee_id'], (int)$attempt['test_id']);

            $created = ['created_field_names' => [
                'retake_authorization', 'assignment_status', 'max_attempts', 'assessment_progress',
            ]];
            $this->repository->logAdminAudit($adminId, 'AUTHORIZE_RETAKE', $attemptId, null, $created);

            $this->pdo->commit();
            $this->consumeToken($token);
            return ['code' => 'SUCCESS', 'attempt_id' => $attemptId];
        } catch (PDOException $e) {
            $this->rollBack();
            $this->consumeToken($token);
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ($e->getCode() === '23000' && $driverCode === 1062) {
                return ['code' => 'ALREADY_AUTHORIZED', 'message' => trans('attempt_retake_already_authorized')];
            }
            error_log('AdminAttemptManagementService retake database error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        } catch (Throwable $e) {
            $this->rollBack();
            $this->consumeToken($token);
            error_log('AdminAttemptManagementService retake error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    public function updateSupervisorNotes(int $attemptId, string $token, array $input, int $adminId): array
    {
        $tokenData = $this->validateToken($token, $adminId, $attemptId, 'notes');
        if ($tokenData === null) {
            return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
        }

        $validated = $this->validator->validateNotes($input);
        if (!$validated['valid']) {
            return ['code' => 'VALIDATION_ERROR', 'errors' => $validated['errors']];
        }

        try {
            $this->pdo->beginTransaction();
            $attempt = $this->repository->findForUpdate($attemptId);
            if ($attempt === null) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'NOT_FOUND'];
            }
            if (!$this->matchesFingerprint($tokenData, $attempt)) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
            }

            $currentNotes = $this->normalizeNullableText($attempt['supervisor_notes'] ?? null);
            if ($currentNotes === $validated['notes']) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'NO_CHANGES'];
            }

            if (!$this->repository->updateSupervisorNotes($attemptId, $validated['notes'])) {
                $this->pdo->rollBack();
                $this->consumeToken($token);
                return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
            }
            $audit = ['changed_field_names' => ['supervisor_notes']];
            $this->repository->logAdminAudit($adminId, 'UPDATE_ATTEMPT_NOTES', $attemptId, $audit, $audit);

            $this->pdo->commit();
            $this->consumeToken($token);
            return ['code' => 'SUCCESS', 'attempt_id' => $attemptId];
        } catch (Throwable $e) {
            $this->rollBack();
            $this->consumeToken($token);
            error_log('AdminAttemptManagementService notes error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    public function finalizeExpiredAttempt(int $attemptId, string $token, int $adminId): array
    {
        $tokenData = $this->validateToken($token, $adminId, $attemptId, 'finalize');
        if ($tokenData === null) {
            return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
        }

        try {
            $attempt = $this->repository->findSafeById($attemptId);
            if ($attempt === null) {
                $this->consumeToken($token);
                return ['code' => 'NOT_FOUND'];
            }
            if (!$this->matchesFingerprint($tokenData, $attempt)) {
                $this->consumeToken($token);
                return ['code' => 'CONFLICT', 'message' => trans('attempt_action_token_conflict')];
            }
            if ((string)$attempt['status'] !== 'IN_PROGRESS' || (int)$attempt['is_expired_in_progress'] !== 1) {
                $this->consumeToken($token);
                return ['code' => 'STATUS_LOCKED', 'message' => trans('attempt_finalize_status_locked')];
            }

            $result = $this->attemptService->timeoutExpiredAttemptById(
                $attemptId,
                (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                (string)(($_SERVER['HTTP_USER_AGENT'] ?? 'CLI') . ' ADMIN_FINALIZE_EXPIRED_ATTEMPT')
            );
            $this->consumeToken($token);

            if (!($result['success'] ?? false)) {
                return ['code' => 'INTERNAL_ERROR'];
            }
            if (!($result['timed_out'] ?? false)) {
                return ['code' => 'NO_CHANGES'];
            }

            try {
                $created = ['created_field_names' => ['admin_timeout_finalization_request']];
                $this->repository->logAdminAudit($adminId, 'FINALIZE_EXPIRED_ATTEMPT', $attemptId, null, $created);
            } catch (Throwable $auditError) {
                error_log('Admin attempt finalize audit error: ' . $auditError->getMessage());
            }

            return ['code' => 'SUCCESS', 'attempt_id' => $attemptId];
        } catch (Throwable $e) {
            $this->consumeToken($token);
            error_log('AdminAttemptManagementService finalize error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    private function validateToken(string $token, int $adminId, int $attemptId, string $action): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token) || !in_array($action, self::ACTIONS, true)) {
            return null;
        }
        $this->cleanTokens();
        $data = $_SESSION['admin_attempt_action_tokens'][$token] ?? null;
        if (!is_array($data)
            || (int)($data['admin_id'] ?? 0) !== $adminId
            || (int)($data['attempt_id'] ?? 0) !== $attemptId
            || (string)($data['action'] ?? '') !== $action
            || time() - (int)($data['created_at'] ?? 0) > self::TOKEN_TTL_SECONDS
        ) {
            unset($_SESSION['admin_attempt_action_tokens'][$token]);
            return null;
        }
        return $data;
    }

    private function matchesFingerprint(array $tokenData, array $attempt): bool
    {
        $expected = (string)($tokenData['fingerprint'] ?? '');
        return $expected !== '' && hash_equals($expected, $this->fingerprint($attempt));
    }

    private function fingerprint(array $attempt): string
    {
        $payload = [
            'id' => (int)($attempt['id'] ?? 0),
            'assignment_id' => (int)($attempt['assignment_id'] ?? 0),
            'employee_id' => (int)($attempt['employee_id'] ?? 0),
            'test_id' => (int)($attempt['test_id'] ?? 0),
            'attempt_number' => (int)($attempt['attempt_number'] ?? 0),
            'status' => (string)($attempt['status'] ?? ''),
            'submitted_at' => $this->normalizeNullableText($attempt['submitted_at'] ?? null),
            'cancelled_at' => $this->normalizeNullableText($attempt['cancelled_at'] ?? null),
            'supervisor_notes_hash' => hash('sha256', (string)($attempt['supervisor_notes'] ?? '')),
            'updated_at' => (string)($attempt['updated_at'] ?? ''),
            'assignment_status' => (string)($attempt['assignment_status'] ?? ''),
            'max_attempts' => (int)($attempt['max_attempts'] ?? 0),
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function cleanTokens(): void
    {
        if (!isset($_SESSION['admin_attempt_action_tokens']) || !is_array($_SESSION['admin_attempt_action_tokens'])) {
            $_SESSION['admin_attempt_action_tokens'] = [];
            return;
        }
        $now = time();
        foreach ($_SESSION['admin_attempt_action_tokens'] as $token => $data) {
            if (!is_array($data) || $now - (int)($data['created_at'] ?? 0) > self::TOKEN_TTL_SECONDS) {
                unset($_SESSION['admin_attempt_action_tokens'][$token]);
            }
        }
    }

    private function consumeToken(string $token): void
    {
        unset($_SESSION['admin_attempt_action_tokens'][$token]);
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string)$value;
    }

    private function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
