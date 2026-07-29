<?php

namespace App\Services;

use App\Repositories\AdminQuestionRepository;
use App\Support\Database;
use App\Validators\AdminQuestionFormValidator;
use PDO;
use PDOException;
use Throwable;

class AdminQuestionManagementService
{
    private AdminQuestionRepository $repository;
    private AdminQuestionFormValidator $validator;
    private PDO $pdo;

    public function __construct(
        ?AdminQuestionRepository $repository = null,
        ?AdminQuestionFormValidator $validator = null,
        ?PDO $pdo = null
    ) {
        $this->repository = $repository ?? new AdminQuestionRepository();
        $this->validator = $validator ?? new AdminQuestionFormValidator();
        $this->pdo = $pdo ?? Database::getInstance();
    }

    public function issueEditToken(int $adminId, int $questionId, array $question): string
    {
        $this->cleanSessionTokens();
        $token = bin2hex(random_bytes(32));
        if (!isset($_SESSION['admin_question_edit_tokens']) || !is_array($_SESSION['admin_question_edit_tokens'])) {
            $_SESSION['admin_question_edit_tokens'] = [];
        }
        $_SESSION['admin_question_edit_tokens'][$token] = [
            'admin_id' => $adminId,
            'question_id' => $questionId,
            'fingerprint' => $this->calculateFingerprint($question),
            'created_at' => time(),
        ];

        if (count($_SESSION['admin_question_edit_tokens']) > 20) {
            uasort(
                $_SESSION['admin_question_edit_tokens'],
                static fn(array $a, array $b): int => ((int)($a['created_at'] ?? 0)) <=> ((int)($b['created_at'] ?? 0))
            );
            while (count($_SESSION['admin_question_edit_tokens']) > 20) {
                array_shift($_SESSION['admin_question_edit_tokens']);
            }
        }
        return $token;
    }

    public function validateEditToken(string $token, int $adminId, int $questionId): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }
        $this->cleanSessionTokens();
        $tokenData = $_SESSION['admin_question_edit_tokens'][$token] ?? null;
        if (!is_array($tokenData)) {
            return null;
        }
        if ((int)($tokenData['admin_id'] ?? 0) !== $adminId || (int)($tokenData['question_id'] ?? 0) !== $questionId) {
            return null;
        }
        if (time() - (int)($tokenData['created_at'] ?? 0) > 7200) {
            unset($_SESSION['admin_question_edit_tokens'][$token]);
            return null;
        }
        return $tokenData;
    }

    public function consumeEditToken(string $token): void
    {
        unset($_SESSION['admin_question_edit_tokens'][$token]);
    }

    public function createCategory(array $input, int $adminId): array
    {
        $validated = $this->validator->validateCategory($input);
        if (!$validated['valid']) {
            return ['code' => 'VALIDATION_ERROR', 'errors' => $validated['errors'], 'old' => $validated['old']];
        }
        $data = $validated['data'];
        if (!$this->repository->testExists((int)$data['test_id'])) {
            return [
                'code' => 'VALIDATION_ERROR',
                'errors' => ['test_id' => trans('assessment_not_found')],
                'old' => $validated['old'],
            ];
        }
        if ($this->repository->categoryNameExists((int)$data['test_id'], $data['name_ar'], $data['name_en'])) {
            return [
                'code' => 'VALIDATION_ERROR',
                'errors' => ['name_en' => trans('duplicate_question_category')],
                'old' => $validated['old'],
            ];
        }

        $this->pdo->beginTransaction();
        try {
            $categoryId = $this->repository->createCategory($data);
            $this->logAudit(
                $adminId,
                'CREATE_QUESTION_CATEGORY',
                'QUESTION_CATEGORY',
                $categoryId,
                null,
                ['created_field_names' => ['test_id', 'name_ar', 'name_en', 'is_active']]
            );
            $this->pdo->commit();
            return ['code' => 'SUCCESS', 'category_id' => $categoryId, 'test_id' => (int)$data['test_id']];
        } catch (Throwable $e) {
            $this->rollback();
            error_log('AdminQuestionManagementService createCategory error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    public function createQuestion(array $input, int $adminId): array
    {
        $validated = $this->validator->validate($input);
        if (!$validated['valid']) {
            return ['code' => 'VALIDATION_ERROR', 'errors' => $validated['errors'], 'old' => $validated['old']];
        }
        $data = $validated['data'];
        if (!$this->repository->testExists((int)$data['test_id'])) {
            return $this->validationFailure('test_id', trans('assessment_not_found'), $validated['old']);
        }
        if (!$this->repository->categoryBelongsToTest((int)$data['category_id'], (int)$data['test_id'], true)) {
            return $this->validationFailure('category_id', trans('category_test_mismatch'), $validated['old']);
        }

        $this->pdo->beginTransaction();
        try {
            $questionId = $this->repository->createQuestion($data, $adminId);
            $this->logAudit(
                $adminId,
                'CREATE_QUESTION',
                'QUESTION',
                $questionId,
                null,
                ['created_field_names' => [
                    'test_id', 'category_id', 'type', 'text_ar', 'text_en',
                    'difficulty', 'score', 'is_active', 'options',
                ]]
            );
            $this->pdo->commit();
            return ['code' => 'SUCCESS', 'question_id' => $questionId];
        } catch (PDOException $e) {
            $this->rollback();
            error_log('AdminQuestionManagementService createQuestion SQL error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        } catch (Throwable $e) {
            $this->rollback();
            error_log('AdminQuestionManagementService createQuestion error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    public function updateQuestion(int $questionId, string $editToken, array $input, int $adminId): array
    {
        $tokenData = $this->validateEditToken($editToken, $adminId, $questionId);
        if ($tokenData === null) {
            return $this->conflict();
        }

        $preview = $this->repository->findEditableById($questionId);
        if ($preview === null) {
            $this->consumeEditToken($editToken);
            return ['code' => 'NOT_FOUND'];
        }
        if ($preview['archived_at'] !== null) {
            $this->consumeEditToken($editToken);
            return ['code' => 'ARCHIVED'];
        }

        $validated = $this->validator->validate($input, (int)$preview['test_id']);
        if (!$validated['valid']) {
            $this->consumeEditToken($editToken);
            return ['code' => 'VALIDATION_ERROR', 'errors' => $validated['errors'], 'old' => $validated['old']];
        }
        $data = $validated['data'];
        if (!$this->repository->categoryBelongsToTest((int)$data['category_id'], (int)$preview['test_id'], true)) {
            $this->consumeEditToken($editToken);
            return $this->validationFailure('category_id', trans('category_test_mismatch'), $validated['old']);
        }

        $this->pdo->beginTransaction();
        try {
            $current = $this->repository->findForUpdate($questionId);
            if ($current === null) {
                $this->rollback();
                $this->consumeEditToken($editToken);
                return ['code' => 'NOT_FOUND'];
            }
            if ($current['archived_at'] !== null) {
                $this->rollback();
                $this->consumeEditToken($editToken);
                return ['code' => 'ARCHIVED'];
            }
            if (!hash_equals((string)$tokenData['fingerprint'], $this->calculateFingerprint($current))) {
                $this->rollback();
                $this->consumeEditToken($editToken);
                return $this->conflict();
            }
            if (!$this->repository->categoryBelongsToTest((int)$data['category_id'], (int)$current['test_id'], true)) {
                $this->rollback();
                $this->consumeEditToken($editToken);
                return $this->validationFailure('category_id', trans('category_test_mismatch'), $validated['old']);
            }

            if (count($current['options']) > count($data['options'])) {
                $removedIds = array_map(
                    static fn(array $row): int => (int)$row['id'],
                    array_slice($current['options'], count($data['options']))
                );
                if ($this->repository->optionsHaveMedia($removedIds)) {
                    $this->rollback();
                    $this->consumeEditToken($editToken);
                    return ['code' => 'MEDIA_LOCKED', 'message' => trans('option_media_delete_blocked')];
                }
            }

            $changedFields = $this->calculateChangedFields($current, $data);
            if ($changedFields === []) {
                $this->rollback();
                $this->consumeEditToken($editToken);
                return ['code' => 'NO_CHANGES'];
            }

            $currentValid = $this->isCurrentQuestionValid($current);
            $proposedValid = (int)$data['is_active'] === 1;
            if ((int)$current['test_is_active'] === 1 && $currentValid && !$proposedValid) {
                $validCount = $this->repository->countValidQuestionsForTest((int)$current['test_id']);
                if ($validCount <= (int)$current['required_question_count']) {
                    $this->rollback();
                    $this->consumeEditToken($editToken);
                    return ['code' => 'INTEGRITY_LOCK', 'message' => trans('active_test_question_integrity_lock')];
                }
            }

            $this->repository->updateQuestion($questionId, $data, $current['options']);
            $payload = ['changed_field_names' => $changedFields];
            $this->logAudit($adminId, 'UPDATE_QUESTION', 'QUESTION', $questionId, $payload, $payload);
            $this->consumeEditToken($editToken);
            $this->pdo->commit();
            return ['code' => 'SUCCESS'];
        } catch (Throwable $e) {
            $this->rollback();
            $this->consumeEditToken($editToken);
            error_log('AdminQuestionManagementService updateQuestion error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    public function archiveQuestion(int $questionId, string $editToken, int $adminId): array
    {
        return $this->changeArchiveState($questionId, $editToken, $adminId, true);
    }

    public function restoreQuestion(int $questionId, string $editToken, int $adminId): array
    {
        return $this->changeArchiveState($questionId, $editToken, $adminId, false);
    }

    private function changeArchiveState(int $questionId, string $editToken, int $adminId, bool $archive): array
    {
        $tokenData = $this->validateEditToken($editToken, $adminId, $questionId);
        if ($tokenData === null) {
            return $this->conflict();
        }

        $this->pdo->beginTransaction();
        try {
            $current = $this->repository->findForUpdate($questionId);
            if ($current === null) {
                $this->rollback();
                $this->consumeEditToken($editToken);
                return ['code' => 'NOT_FOUND'];
            }
            if (!hash_equals((string)$tokenData['fingerprint'], $this->calculateFingerprint($current))) {
                $this->rollback();
                $this->consumeEditToken($editToken);
                return $this->conflict();
            }

            $isArchived = $current['archived_at'] !== null;
            if (($archive && $isArchived) || (!$archive && !$isArchived)) {
                $this->rollback();
                $this->consumeEditToken($editToken);
                return ['code' => 'NO_CHANGES'];
            }

            if ($archive && (int)$current['test_is_active'] === 1 && $this->isCurrentQuestionValid($current)) {
                $validCount = $this->repository->countValidQuestionsForTest((int)$current['test_id']);
                if ($validCount <= (int)$current['required_question_count']) {
                    $this->rollback();
                    $this->consumeEditToken($editToken);
                    return ['code' => 'INTEGRITY_LOCK', 'message' => trans('active_test_question_integrity_lock')];
                }
            }

            if ($archive) {
                $this->repository->archiveQuestion($questionId);
                $fields = ['is_active', 'archived_at'];
                $action = 'ARCHIVE_QUESTION';
            } else {
                $this->repository->restoreQuestion($questionId);
                $fields = ['archived_at'];
                $action = 'RESTORE_QUESTION';
            }

            $payload = ['changed_field_names' => $fields];
            $this->logAudit($adminId, $action, 'QUESTION', $questionId, $payload, $payload);
            $this->consumeEditToken($editToken);
            $this->pdo->commit();
            return ['code' => 'SUCCESS'];
        } catch (Throwable $e) {
            $this->rollback();
            $this->consumeEditToken($editToken);
            error_log('AdminQuestionManagementService archive state error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    private function cleanSessionTokens(): void
    {
        if (!isset($_SESSION['admin_question_edit_tokens']) || !is_array($_SESSION['admin_question_edit_tokens'])) {
            return;
        }
        $now = time();
        foreach ($_SESSION['admin_question_edit_tokens'] as $token => $data) {
            if ($now - (int)($data['created_at'] ?? 0) > 7200) {
                unset($_SESSION['admin_question_edit_tokens'][$token]);
            }
        }
    }

    private function calculateFingerprint(array $question): string
    {
        $options = [];
        foreach (($question['options'] ?? []) as $option) {
            $options[] = [
                'id' => (int)($option['id'] ?? 0),
                'option_key' => (string)($option['option_key'] ?? ''),
                'text_ar' => (string)($option['text_ar'] ?? ''),
                'text_en' => (string)($option['text_en'] ?? ''),
                'is_correct' => (int)($option['is_correct'] ?? 0),
                'display_order' => (int)($option['display_order'] ?? 0),
            ];
        }
        $canonical = [
            'id' => (int)($question['id'] ?? 0),
            'test_id' => (int)($question['test_id'] ?? 0),
            'category_id' => (int)($question['category_id'] ?? 0),
            'type' => (string)($question['type'] ?? ''),
            'text_ar' => (string)($question['text_ar'] ?? ''),
            'text_en' => (string)($question['text_en'] ?? ''),
            'difficulty' => (string)($question['difficulty'] ?? ''),
            'score' => number_format((float)($question['score'] ?? 0), 2, '.', ''),
            'is_active' => (int)($question['is_active'] ?? 0),
            'archived_at' => $question['archived_at'] === null ? null : (string)$question['archived_at'],
            'options' => $options,
        ];
        return hash('sha256', (string)json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function calculateChangedFields(array $current, array $data): array
    {
        $changed = [];
        $fields = ['category_id', 'type', 'text_ar', 'text_en', 'difficulty', 'score', 'is_active'];
        foreach ($fields as $field) {
            if ($field === 'category_id' || $field === 'is_active') {
                if ((int)$current[$field] !== (int)$data[$field]) {
                    $changed[] = $field;
                }
            } elseif ($field === 'score') {
                if (number_format((float)$current[$field], 2, '.', '') !== number_format((float)$data[$field], 2, '.', '')) {
                    $changed[] = $field;
                }
            } elseif ((string)$current[$field] !== (string)$data[$field]) {
                $changed[] = $field;
            }
        }

        $currentOptions = array_map(static fn(array $option): array => [
            'option_key' => (string)$option['option_key'],
            'text_ar' => (string)$option['text_ar'],
            'text_en' => (string)$option['text_en'],
            'is_correct' => (int)$option['is_correct'],
            'display_order' => (int)$option['display_order'],
        ], $current['options'] ?? []);
        $newOptions = array_map(static fn(array $option): array => [
            'option_key' => (string)$option['option_key'],
            'text_ar' => (string)$option['text_ar'],
            'text_en' => (string)$option['text_en'],
            'is_correct' => (int)$option['is_correct'],
            'display_order' => (int)$option['display_order'],
        ], $data['options']);
        if ($currentOptions !== $newOptions) {
            $changed[] = 'options';
        }
        return $changed;
    }

    private function isCurrentQuestionValid(array $current): bool
    {
        if ((int)$current['is_active'] !== 1 || $current['archived_at'] !== null || (int)$current['category_is_active'] !== 1) {
            return false;
        }
        $correct = 0;
        foreach ($current['options'] ?? [] as $option) {
            $correct += (int)$option['is_correct'] === 1 ? 1 : 0;
        }
        return count($current['options'] ?? []) >= 2 && $correct === 1;
    }

    private function validationFailure(string $field, string $message, array $old): array
    {
        return ['code' => 'VALIDATION_ERROR', 'errors' => [$field => $message], 'old' => $old];
    }

    private function conflict(): array
    {
        return ['code' => 'CONFLICT', 'message' => trans('concurrent_question_update_conflict')];
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function logAudit(
        int $adminId,
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues,
        ?array $newValues
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs (
                admin_id, action, entity_type, entity_id,
                old_values, new_values, ip_address, user_agent, created_at
            ) VALUES (
                :admin_id, :action, :entity_type, :entity_id,
                :old_values, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
            ':new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
        ]);
    }
}
