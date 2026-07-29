<?php

namespace App\Services;

use App\Repositories\AdminTestRepository;
use App\Validators\AdminTestFormValidator;
use App\Validators\AdminTestLifecycleValidator;
use App\Support\Database;
use PDO;
use PDOException;
use Throwable;

class AdminTestManagementService
{
    private AdminTestRepository $repository;
    private AdminTestFormValidator $validator;
    private AdminTestLifecycleValidator $lifecycleValidator;
    private PDO $pdo;

    public function __construct(
        ?AdminTestRepository $repository = null,
        ?AdminTestFormValidator $validator = null,
        ?PDO $pdo = null,
        ?AdminTestLifecycleValidator $lifecycleValidator = null
    ) {
        $this->repository = $repository ?? new AdminTestRepository();
        $this->validator = $validator ?? new AdminTestFormValidator();
        $this->pdo = $pdo ?? Database::getInstance();
        $this->lifecycleValidator = $lifecycleValidator ?? new AdminTestLifecycleValidator();
    }

    /**
     * Issue a session-bound edit token for an assessment edit session.
     *
     * @param int $adminId
     * @param int $testId
     * @param array $testRow
     * @return string
     */
    public function issueEditToken(int $adminId, int $testId, array $testRow): string
    {
        $this->cleanSessionTokens();

        $token = bin2hex(random_bytes(32));
        $fingerprint = $this->calculateFingerprint($testRow);

        if (!isset($_SESSION['admin_test_edit_tokens']) || !is_array($_SESSION['admin_test_edit_tokens'])) {
            $_SESSION['admin_test_edit_tokens'] = [];
        }

        $_SESSION['admin_test_edit_tokens'][$token] = [
            'admin_id' => $adminId,
            'test_id' => $testId,
            'fingerprint' => $fingerprint,
            'created_at' => time(),
        ];

        // Enforce max 20 active tokens per session (remove oldest)
        if (count($_SESSION['admin_test_edit_tokens']) > 20) {
            uasort($_SESSION['admin_test_edit_tokens'], function ($a, $b) {
                return ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0);
            });
            while (count($_SESSION['admin_test_edit_tokens']) > 20) {
                array_shift($_SESSION['admin_test_edit_tokens']);
            }
        }

        return $token;
    }

    /**
     * Validate session edit token.
     *
     * @param string $token
     * @param int $adminId
     * @param int $testId
     * @return array|null Returns token payload array or null if invalid/expired.
     */
    public function validateEditToken(string $token, int $adminId, int $testId): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        $this->cleanSessionTokens();

        if (!isset($_SESSION['admin_test_edit_tokens'][$token]) || !is_array($_SESSION['admin_test_edit_tokens'][$token])) {
            return null;
        }

        $tokenData = $_SESSION['admin_test_edit_tokens'][$token];
        if (($tokenData['admin_id'] ?? null) !== $adminId || ($tokenData['test_id'] ?? null) !== $testId) {
            return null;
        }

        $createdAt = (int)($tokenData['created_at'] ?? 0);
        if (time() - $createdAt > 7200) { // 2 hours
            unset($_SESSION['admin_test_edit_tokens'][$token]);
            return null;
        }

        return $tokenData;
    }

    /**
     * Explicitly consume an edit token from session.
     *
     * @param string $token
     * @return void
     */
    public function consumeEditToken(string $token): void
    {
        if (isset($_SESSION['admin_test_edit_tokens'][$token])) {
            unset($_SESSION['admin_test_edit_tokens'][$token]);
        }
    }

    /**
     * Remove expired tokens from current admin session.
     */
    private function cleanSessionTokens(): void
    {
        if (!isset($_SESSION['admin_test_edit_tokens']) || !is_array($_SESSION['admin_test_edit_tokens'])) {
            return;
        }

        $now = time();
        foreach ($_SESSION['admin_test_edit_tokens'] as $tok => $data) {
            $created = (int)($data['created_at'] ?? 0);
            if ($now - $created > 7200) {
                unset($_SESSION['admin_test_edit_tokens'][$tok]);
            }
        }
    }

    /**
     * Create a new assessment with forced inactive defaults.
     *
     * @param array $input
     * @param int $adminId
     * @return array Result descriptor
     */
    public function createTest(array $input, int $adminId): array
    {
        $validated = $this->validator->validate($input);
        if (!$validated['valid']) {
            return [
                'code' => 'VALIDATION_ERROR',
                'errors' => $validated['errors'],
                'old' => $validated['old'],
            ];
        }

        $data = $validated['data'];

        // Code uniqueness pre-check
        if ($this->repository->codeExists($data['code'])) {
            return [
                'code' => 'DUPLICATE_CODE',
                'errors' => ['code' => trans('duplicate_assessment_code')],
                'old' => $validated['old'],
            ];
        }

        $this->pdo->beginTransaction();

        try {
            $newId = $this->repository->createTest($data);

            $fixedFieldOrder = [
                'code', 'name_ar', 'name_en', 'description_ar', 'description_en',
                'instructions_ar', 'instructions_en', 'duration_minutes', 'question_count',
                'passing_percentage', 'display_order', 'randomize_questions', 'randomize_options',
                'allow_back_navigation', 'show_result_to_employee'
            ];

            // Minimal CREATE_TEST audit
            $auditPayload = [
                'created_field_names' => $fixedFieldOrder,
            ];

            $this->logAudit(
                $adminId,
                'CREATE_TEST',
                $newId,
                null,
                json_encode($auditPayload, JSON_UNESCAPED_UNICODE)
            );

            $this->pdo->commit();

            return [
                'code' => 'SUCCESS',
                'test_id' => $newId,
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                return [
                    'code' => 'DUPLICATE_CODE',
                    'errors' => ['code' => trans('duplicate_assessment_code')],
                    'old' => $validated['old'],
                ];
            }
            error_log("AdminTestManagementService createTest SQL error: " . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("AdminTestManagementService createTest error: " . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    /**
     * Update existing assessment metadata with strict lock and concurrency checks.
     *
     * @param int $testId
     * @param string $editToken
     * @param array $input
     * @param int $adminId
     * @return array Result descriptor
     */
    public function updateTest(int $testId, string $editToken, array $input, int $adminId): array
    {
        $tokenData = $this->validateEditToken($editToken, $adminId, $testId);
        if ($tokenData === null) {
            return [
                'code' => 'CONFLICT',
                'message' => trans('concurrent_update_conflict'),
            ];
        }

        $storedFingerprint = $tokenData['fingerprint'];

        $validated = $this->validator->validate($input);
        if (!$validated['valid']) {
            $this->consumeEditToken($editToken);
            return [
                'code' => 'VALIDATION_ERROR',
                'errors' => $validated['errors'],
                'old' => $validated['old'],
            ];
        }

        $data = $validated['data'];

        $this->pdo->beginTransaction();

        try {
            $current = $this->repository->findForUpdate($testId);
            if (!$current) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return ['code' => 'NOT_FOUND'];
            }

            // Fingerprint check
            $currentFingerprint = $this->calculateFingerprint($current);
            if (!hash_equals($storedFingerprint, $currentFingerprint)) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return [
                    'code' => 'CONFLICT',
                    'message' => trans('concurrent_update_conflict'),
                ];
            }

            // Attempt state counts check for field locks
            $attemptCounts = $this->repository->getAttemptStateCounts($testId);
            $totalAttempts = $attemptCounts['total_attempts'];
            $inProgressAttempts = $attemptCounts['in_progress_attempts'];

            $lockErrors = [];

            // Core immutable fields if total_attempts > 0
            if ($totalAttempts > 0) {
                if ($data['code'] !== $current['code']) {
                    $lockErrors['code'] = trans('field_locked_attempts_exist');
                }
                if ($data['duration_minutes'] !== (int)$current['duration_minutes']) {
                    $lockErrors['duration_minutes'] = trans('field_locked_attempts_exist');
                }
                if ($data['question_count'] !== (int)$current['question_count']) {
                    $lockErrors['question_count'] = trans('field_locked_attempts_exist');
                }
                if ($data['passing_percentage'] !== number_format((float)$current['passing_percentage'], 2, '.', '')) {
                    $lockErrors['passing_percentage'] = trans('field_locked_attempts_exist');
                }
            }

            // Navigation lock if in_progress_attempts > 0
            if ($inProgressAttempts > 0) {
                if ($data['allow_back_navigation'] !== (int)$current['allow_back_navigation']) {
                    $lockErrors['allow_back_navigation'] = trans('field_locked_in_progress');
                }
            }

            if (!empty($lockErrors)) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return [
                    'code' => 'FIELD_LOCKED',
                    'errors' => $lockErrors,
                    'old' => $validated['old'],
                ];
            }

            // Code uniqueness check if code changed
            if ($data['code'] !== $current['code'] && $this->repository->codeExists($data['code'], $testId)) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return [
                    'code' => 'DUPLICATE_CODE',
                    'errors' => ['code' => trans('duplicate_assessment_code')],
                    'old' => $validated['old'],
                ];
            }

            // Calculate changed fields
            $changedFieldNames = $this->calculateChangedFields($current, $data);

            if (empty($changedFieldNames)) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return [
                    'code' => 'NO_CHANGES',
                    'test_id' => $testId,
                ];
            }

            // Execute fixed explicit update
            $this->repository->updateTest($testId, $data);

            // Audit log preparation
            $oldAuditPayload = [
                'changed_field_names' => $changedFieldNames,
            ];
            $newAuditPayload = [
                'changed_field_names' => $changedFieldNames,
            ];

            $this->logAudit(
                $adminId,
                'UPDATE_TEST',
                $testId,
                json_encode($oldAuditPayload, JSON_UNESCAPED_UNICODE),
                json_encode($newAuditPayload, JSON_UNESCAPED_UNICODE)
            );

            $this->consumeEditToken($editToken);
            $this->pdo->commit();

            return [
                'code' => 'SUCCESS',
                'test_id' => $testId,
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->consumeEditToken($editToken);
            if ($e->getCode() === '23000') {
                return [
                    'code' => 'DUPLICATE_CODE',
                    'errors' => ['code' => trans('duplicate_assessment_code')],
                    'old' => $validated['old'],
                ];
            }
            error_log("AdminTestManagementService updateTest SQL error: " . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->consumeEditToken($editToken);
            error_log("AdminTestManagementService updateTest error: " . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    /**
     * Update activation and availability lifecycle with readiness and concurrency checks.
     *
     * @return array Result descriptor
     */
    public function updateLifecycle(
        int $testId,
        string $editToken,
        array $input,
        int $adminId
    ): array {
        $tokenData = $this->validateEditToken($editToken, $adminId, $testId);
        if ($tokenData === null) {
            return [
                'code' => 'CONFLICT',
                'message' => trans('concurrent_update_conflict'),
            ];
        }

        $validated = $this->lifecycleValidator->validate($input);
        if (!$validated['valid']) {
            $this->consumeEditToken($editToken);
            return [
                'code' => 'VALIDATION_ERROR',
                'errors' => $validated['errors'],
                'old' => $validated['old'],
            ];
        }

        $data = $validated['data'];
        $this->pdo->beginTransaction();

        try {
            $current = $this->repository->findForUpdate($testId);
            if (!$current) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return ['code' => 'NOT_FOUND'];
            }

            $currentFingerprint = $this->calculateFingerprint($current);
            if (!hash_equals((string)$tokenData['fingerprint'], $currentFingerprint)) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return [
                    'code' => 'CONFLICT',
                    'message' => trans('concurrent_update_conflict'),
                ];
            }

            $readiness = $this->repository->getLifecycleReadiness($testId);
            if ($data['is_active'] === 1 && !$readiness['is_ready']) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return [
                    'code' => 'NOT_READY',
                    'errors' => ['is_active' => trans('assessment_not_ready_for_activation')],
                    'old' => $validated['old'],
                    'readiness' => $readiness,
                ];
            }

            $changedFieldNames = [];
            if ((int)$current['is_active'] !== $data['is_active']) {
                $changedFieldNames[] = 'is_active';
            }
            if ($this->normalizeNullableDate($current['available_from'] ?? null) !== $data['available_from']) {
                $changedFieldNames[] = 'available_from';
            }
            if ($this->normalizeNullableDate($current['available_until'] ?? null) !== $data['available_until']) {
                $changedFieldNames[] = 'available_until';
            }

            if ($changedFieldNames === []) {
                $this->pdo->rollBack();
                $this->consumeEditToken($editToken);
                return [
                    'code' => 'NO_CHANGES',
                    'test_id' => $testId,
                ];
            }

            $this->repository->updateLifecycle(
                $testId,
                $data['is_active'],
                $data['available_from'],
                $data['available_until']
            );

            $auditPayload = ['changed_field_names' => $changedFieldNames];
            $auditJson = json_encode($auditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->logAudit(
                $adminId,
                'UPDATE_TEST_LIFECYCLE',
                $testId,
                $auditJson,
                $auditJson
            );

            $this->consumeEditToken($editToken);
            $this->pdo->commit();

            return [
                'code' => 'SUCCESS',
                'test_id' => $testId,
                'readiness' => $readiness,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->consumeEditToken($editToken);
            error_log('AdminTestManagementService updateLifecycle error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    private function normalizeNullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string)$value;
    }

    /**
     * Compute a deterministic SHA-256 fingerprint for canonical assessment fields.
     *
     * @param array $row
     * @return string
     */
    public function calculateFingerprint(array $row): string
    {
        $canonical = [
            'code' => (string)($row['code'] ?? ''),
            'name_ar' => (string)($row['name_ar'] ?? ''),
            'name_en' => (string)($row['name_en'] ?? ''),
            'description_ar' => $row['description_ar'] !== null ? (string)$row['description_ar'] : '',
            'description_en' => $row['description_en'] !== null ? (string)$row['description_en'] : '',
            'instructions_ar' => $row['instructions_ar'] !== null ? (string)$row['instructions_ar'] : '',
            'instructions_en' => $row['instructions_en'] !== null ? (string)$row['instructions_en'] : '',
            'duration_minutes' => (int)($row['duration_minutes'] ?? 0),
            'question_count' => (int)($row['question_count'] ?? 0),
            'passing_percentage' => number_format((float)($row['passing_percentage'] ?? 0), 2, '.', ''),
            'display_order' => (int)($row['display_order'] ?? 0),
            'randomize_questions' => (int)($row['randomize_questions'] ?? 0),
            'randomize_options' => (int)($row['randomize_options'] ?? 0),
            'allow_back_navigation' => (int)($row['allow_back_navigation'] ?? 0),
            'show_result_to_employee' => (int)($row['show_result_to_employee'] ?? 0),
            'is_active' => (int)($row['is_active'] ?? 0),
            'available_from' => $this->normalizeNullableDate($row['available_from'] ?? null),
            'available_until' => $this->normalizeNullableDate($row['available_until'] ?? null),
        ];

        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $json);
    }

    /**
     * Calculate list of changed field names in deterministic server-side order.
     *
     * @param array $current
     * @param array $newData
     * @return array
     */
    private function calculateChangedFields(array $current, array $newData): array
    {
        $fixedOrder = [
            'code', 'name_ar', 'name_en', 'description_ar', 'description_en',
            'instructions_ar', 'instructions_en', 'duration_minutes', 'question_count',
            'passing_percentage', 'display_order', 'randomize_questions', 'randomize_options',
            'allow_back_navigation', 'show_result_to_employee'
        ];

        $changed = [];

        foreach ($fixedOrder as $field) {
            $newVal = $newData[$field] ?? null;
            $oldVal = $current[$field] ?? null;

            if ($field === 'duration_minutes' || $field === 'question_count' || $field === 'display_order' ||
                $field === 'randomize_questions' || $field === 'randomize_options' ||
                $field === 'allow_back_navigation' || $field === 'show_result_to_employee') {
                if ((int)$newVal !== (int)$oldVal) {
                    $changed[] = $field;
                }
            } elseif ($field === 'passing_percentage') {
                if (number_format((float)$newVal, 2, '.', '') !== number_format((float)$oldVal, 2, '.', '')) {
                    $changed[] = $field;
                }
            } else {
                // String / NULL comparison
                $normOld = $oldVal !== null ? (string)$oldVal : null;
                $normNew = $newVal !== null ? (string)$newVal : null;
                if ($normOld !== $normNew) {
                    $changed[] = $field;
                }
            }
        }

        return $changed;
    }

    /**
     * Write an entry directly to the audit_logs database table inside transaction.
     *
     * @param int $adminId
     * @param string $action
     * @param int $entityId
     * @param string|null $oldValuesJson
     * @param string|null $newValuesJson
     * @return void
     */
    private function logAudit(
        int $adminId,
        string $action,
        int $entityId,
        ?string $oldValuesJson,
        ?string $newValuesJson
    ): void {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs (
                admin_id, action, entity_type, entity_id,
                old_values, new_values, ip_address, user_agent, created_at
            ) VALUES (
                :admin_id, :action, 'TEST', :entity_id,
                :old_values, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
            )
        ");

        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':entity_id' => $entityId,
            ':old_values' => $oldValuesJson,
            ':new_values' => $newValuesJson,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
        ]);
    }
}
