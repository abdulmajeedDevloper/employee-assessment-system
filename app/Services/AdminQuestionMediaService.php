<?php

namespace App\Services;

use App\Repositories\AdminQuestionMediaRepository;
use App\Repositories\AdminQuestionRepository;
use App\Support\Database;
use App\Validators\QuestionImageUploadValidator;
use PDO;
use Throwable;

class AdminQuestionMediaService
{
    private AdminQuestionRepository $questionRepository;
    private AdminQuestionMediaRepository $mediaRepository;
    private QuestionImageUploadValidator $validator;
    private PDO $pdo;
    private string $storageRoot;

    public function __construct(
        ?AdminQuestionRepository $questionRepository = null,
        ?AdminQuestionMediaRepository $mediaRepository = null,
        ?QuestionImageUploadValidator $validator = null,
        ?PDO $pdo = null,
        ?string $storageRoot = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->questionRepository = $questionRepository ?? new AdminQuestionRepository($this->pdo);
        $this->mediaRepository = $mediaRepository ?? new AdminQuestionMediaRepository($this->pdo);
        $this->validator = $validator ?? new QuestionImageUploadValidator();
        $this->storageRoot = $this->resolveStorageRoot($storageRoot);
    }

    public function issueActionToken(int $adminId, int $questionId, array $question): string
    {
        $this->cleanSessionTokens();
        $token = bin2hex(random_bytes(32));
        if (!isset($_SESSION['admin_question_media_tokens']) || !is_array($_SESSION['admin_question_media_tokens'])) {
            $_SESSION['admin_question_media_tokens'] = [];
        }
        $_SESSION['admin_question_media_tokens'][$token] = [
            'admin_id' => $adminId,
            'question_id' => $questionId,
            'fingerprint' => $this->calculateFingerprint($question),
            'created_at' => time(),
        ];

        if (count($_SESSION['admin_question_media_tokens']) > 20) {
            uasort(
                $_SESSION['admin_question_media_tokens'],
                static fn(array $a, array $b): int => ((int)($a['created_at'] ?? 0)) <=> ((int)($b['created_at'] ?? 0))
            );
            while (count($_SESSION['admin_question_media_tokens']) > 20) {
                array_shift($_SESSION['admin_question_media_tokens']);
            }
        }
        return $token;
    }

    /**
     * Upload or replace a question/option image.
     *
     * @return array{code:string,message?:string,replaced?:bool}
     */
    public function upload(
        int $questionId,
        ?int $optionId,
        string $actionToken,
        array $file,
        int $adminId
    ): array {
        $tokenData = $this->validateActionToken($actionToken, $adminId, $questionId);
        if ($tokenData === null) {
            return $this->conflict();
        }

        $validated = $this->validator->validate($file);
        if (!$validated['valid'] || !is_array($validated['data'])) {
            $this->consumeActionToken($actionToken);
            return ['code' => 'VALIDATION_ERROR', 'message' => $validated['error'] ?? trans('question_image_upload_failed')];
        }

        $stored = null;
        $this->pdo->beginTransaction();
        try {
            $question = $this->questionRepository->findForUpdate($questionId);
            if ($question === null) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return ['code' => 'NOT_FOUND'];
            }
            if ($question['archived_at'] !== null) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return ['code' => 'ARCHIVED'];
            }
            if (!hash_equals((string)$tokenData['fingerprint'], $this->calculateFingerprint($question))) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return $this->conflict();
            }

            $mediaType = $optionId === null ? 'QUESTION_IMAGE' : 'OPTION_IMAGE';
            if ($optionId !== null && !$this->mediaRepository->optionBelongsToQuestion($optionId, $questionId, true)) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return ['code' => 'INVALID_OWNER'];
            }

            $existing = $this->mediaRepository->findOwnerMediaForUpdate(
                $mediaType,
                $optionId === null ? $questionId : null,
                $optionId
            );

            $stored = $this->storeValidatedUpload($validated['data']);
            $record = [
                'media_type' => $mediaType,
                'question_id' => $optionId === null ? $questionId : null,
                'option_id' => $optionId,
                'public_token' => $stored['public_token'],
                'storage_path' => $stored['storage_path'],
                'original_filename' => $validated['data']['original_filename'],
                'mime_type' => $validated['data']['mime_type'],
                'file_extension' => $validated['data']['file_extension'],
                'file_size_bytes' => $validated['data']['file_size_bytes'],
                'width_pixels' => $validated['data']['width_pixels'],
                'height_pixels' => $validated['data']['height_pixels'],
                'created_by' => $adminId,
            ];

            if ($existing === null) {
                $this->mediaRepository->insertMedia($record);
            } else {
                $this->mediaRepository->replaceMedia((int)$existing['id'], $record);
                $this->mediaRepository->queueDeletion((string)$existing['storage_path']);
            }

            $fieldName = $optionId === null ? 'question_image' : 'option_image';
            $payload = ['changed_field_names' => [$fieldName]];
            $this->logAudit(
                $adminId,
                $existing === null ? 'UPLOAD_QUESTION_MEDIA' : 'REPLACE_QUESTION_MEDIA',
                $optionId === null ? 'QUESTION' : 'QUESTION_OPTION',
                $optionId ?? $questionId,
                $existing === null ? null : $payload,
                $payload
            );

            $this->consumeActionToken($actionToken);
            $this->pdo->commit();
            $this->processPendingDeletions(1);
            return ['code' => 'SUCCESS', 'replaced' => $existing !== null];
        } catch (Throwable $e) {
            $this->rollback();
            $this->consumeActionToken($actionToken);
            if (is_array($stored) && isset($stored['storage_path'])) {
                $this->deleteStoredPathImmediately((string)$stored['storage_path']);
            }
            error_log('AdminQuestionMediaService upload error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    /** @return array{code:string,message?:string} */
    public function remove(int $questionId, ?int $optionId, string $actionToken, int $adminId): array
    {
        $tokenData = $this->validateActionToken($actionToken, $adminId, $questionId);
        if ($tokenData === null) {
            return $this->conflict();
        }

        $this->pdo->beginTransaction();
        try {
            $question = $this->questionRepository->findForUpdate($questionId);
            if ($question === null) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return ['code' => 'NOT_FOUND'];
            }
            if ($question['archived_at'] !== null) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return ['code' => 'ARCHIVED'];
            }
            if (!hash_equals((string)$tokenData['fingerprint'], $this->calculateFingerprint($question))) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return $this->conflict();
            }

            $mediaType = $optionId === null ? 'QUESTION_IMAGE' : 'OPTION_IMAGE';
            if ($optionId !== null && !$this->mediaRepository->optionBelongsToQuestion($optionId, $questionId, true)) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return ['code' => 'INVALID_OWNER'];
            }

            $existing = $this->mediaRepository->findOwnerMediaForUpdate(
                $mediaType,
                $optionId === null ? $questionId : null,
                $optionId
            );
            if ($existing === null) {
                $this->rollback();
                $this->consumeActionToken($actionToken);
                return ['code' => 'NO_CHANGES'];
            }

            $this->mediaRepository->deleteMedia((int)$existing['id']);
            $this->mediaRepository->queueDeletion((string)$existing['storage_path']);
            $fieldName = $optionId === null ? 'question_image' : 'option_image';
            $payload = ['changed_field_names' => [$fieldName]];
            $this->logAudit(
                $adminId,
                'REMOVE_QUESTION_MEDIA',
                $optionId === null ? 'QUESTION' : 'QUESTION_OPTION',
                $optionId ?? $questionId,
                $payload,
                null
            );

            $this->consumeActionToken($actionToken);
            $this->pdo->commit();
            $this->processPendingDeletions(1);
            return ['code' => 'SUCCESS'];
        } catch (Throwable $e) {
            $this->rollback();
            $this->consumeActionToken($actionToken);
            error_log('AdminQuestionMediaService remove error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    /**
     * Resolve a protected media token to a safe absolute path and non-sensitive metadata.
     *
     * @return array<string,mixed>|null
     */
    public function getStreamableMedia(string $publicToken): ?array
    {
        if (strlen($publicToken) !== 64 || !ctype_xdigit($publicToken)) {
            return null;
        }
        $media = $this->mediaRepository->findByPublicToken(strtolower($publicToken));
        if ($media === null) {
            return null;
        }
        $absolutePath = $this->safeAbsolutePath((string)$media['storage_path'], true);
        if ($absolutePath === null) {
            return null;
        }
        $actualSize = filesize($absolutePath);
        if ($actualSize === false || (int)$actualSize !== (int)$media['file_size_bytes']) {
            return null;
        }
        $media['absolute_path'] = $absolutePath;
        return $media;
    }

    public function processPendingDeletions(int $limit = 25): int
    {
        $limit = max(1, min(100, $limit));
        $processed = 0;
        for ($i = 0; $i < $limit; $i++) {
            try {
                $this->pdo->beginTransaction();
                $row = $this->mediaRepository->findNextPendingDeletionForUpdate();
                if ($row === null) {
                    $this->pdo->commit();
                    break;
                }

                $path = $this->safeAbsolutePath((string)$row['storage_path'], false);
                $success = false;
                $errorCode = 'delete_failed';
                if ($path === null) {
                    $errorCode = 'invalid_storage_path';
                } elseif (!file_exists($path)) {
                    $success = true;
                } elseif (is_file($path) && !is_link($path) && @unlink($path)) {
                    $success = true;
                }

                if ($success) {
                    $this->mediaRepository->markDeletionProcessed((int)$row['id']);
                    $processed++;
                } else {
                    $this->mediaRepository->rescheduleDeletion((int)$row['id'], (int)$row['attempts'], $errorCode);
                }
                $this->pdo->commit();
            } catch (Throwable $e) {
                $this->rollback();
                error_log('AdminQuestionMediaService deletion queue error: ' . $e->getMessage());
                break;
            }
        }
        return $processed;
    }

    private function validateActionToken(string $token, int $adminId, int $questionId): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }
        $this->cleanSessionTokens();
        $data = $_SESSION['admin_question_media_tokens'][$token] ?? null;
        if (!is_array($data)) {
            return null;
        }
        if ((int)($data['admin_id'] ?? 0) !== $adminId || (int)($data['question_id'] ?? 0) !== $questionId) {
            return null;
        }
        if (time() - (int)($data['created_at'] ?? 0) > 7200) {
            unset($_SESSION['admin_question_media_tokens'][$token]);
            return null;
        }
        return $data;
    }

    private function consumeActionToken(string $token): void
    {
        unset($_SESSION['admin_question_media_tokens'][$token]);
    }

    private function cleanSessionTokens(): void
    {
        if (!isset($_SESSION['admin_question_media_tokens']) || !is_array($_SESSION['admin_question_media_tokens'])) {
            return;
        }
        $now = time();
        foreach ($_SESSION['admin_question_media_tokens'] as $token => $data) {
            if ($now - (int)($data['created_at'] ?? 0) > 7200) {
                unset($_SESSION['admin_question_media_tokens'][$token]);
            }
        }
    }

    /** @return array{storage_path:string,public_token:string} */
    private function storeValidatedUpload(array $data): array
    {
        $year = gmdate('Y');
        $month = gmdate('m');
        $relativeDirectory = 'questions/' . $year . '/' . $month;
        $absoluteDirectory = $this->storageRoot . DIRECTORY_SEPARATOR . 'questions' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
        $this->ensurePrivateDirectory($absoluteDirectory);

        $baseName = bin2hex(random_bytes(20));
        $extension = (string)$data['file_extension'];
        $relativePath = $relativeDirectory . '/' . $baseName . '.' . $extension;
        $finalPath = $absoluteDirectory . DIRECTORY_SEPARATOR . $baseName . '.' . $extension;
        $temporaryPath = $absoluteDirectory . DIRECTORY_SEPARATOR . '.uploading-' . bin2hex(random_bytes(12));

        $source = (string)$data['tmp_path'];
        $moved = is_uploaded_file($source)
            ? move_uploaded_file($source, $temporaryPath)
            : (in_array((string)($_ENV['APP_ENV'] ?? ''), ['test', 'testing'], true) || PHP_SAPI === 'cli')
                && (@rename($source, $temporaryPath) || @copy($source, $temporaryPath));
        if (!$moved || !is_file($temporaryPath) || is_link($temporaryPath)) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to stage uploaded media.');
        }
        @chmod($temporaryPath, 0640);
        if (!@rename($temporaryPath, $finalPath)) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to finalize uploaded media.');
        }
        @chmod($finalPath, 0640);

        $actualSize = filesize($finalPath);
        if ($actualSize === false || (int)$actualSize !== (int)$data['file_size_bytes']) {
            @unlink($finalPath);
            throw new \RuntimeException('Stored media verification failed.');
        }

        return ['storage_path' => $relativePath, 'public_token' => bin2hex(random_bytes(32))];
    }

    private function resolveStorageRoot(?string $configured): string
    {
        $path = $configured;
        if ($path === null || trim($path) === '') {
            $envPath = $_ENV['QUESTION_MEDIA_STORAGE_PATH'] ?? $_SERVER['QUESTION_MEDIA_STORAGE_PATH'] ?? getenv('QUESTION_MEDIA_STORAGE_PATH');
            $path = is_string($envPath) && trim($envPath) !== '' ? trim($envPath) : 'storage/uploads';
        }
        if (!$this->isAbsolutePath($path)) {
            $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }
        if (is_link($path)) {
            throw new \RuntimeException('Question media storage root is invalid.');
        }
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new \RuntimeException('Question media storage root is unavailable.');
        }
        if (is_link($path)) {
            throw new \RuntimeException('Question media storage root is invalid.');
        }
        $real = realpath($path);
        if ($real === false) {
            throw new \RuntimeException('Question media storage root is invalid.');
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create media directory.');
        }
        if ($this->containsSymlinkComponent($directory)) {
            throw new \RuntimeException('Unsafe media directory.');
        }
        $real = realpath($directory);
        if ($real === false || !str_starts_with($real . DIRECTORY_SEPARATOR, $this->storageRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Unsafe media directory.');
        }
    }

    private function safeAbsolutePath(string $relativePath, bool $mustExist): ?string
    {
        if (preg_match('#^questions/[0-9]{4}/[0-9]{2}/[a-f0-9]{40}\.(jpg|png|webp)$#D', $relativePath) !== 1) {
            return null;
        }
        $candidate = $this->storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if ($this->containsSymlinkComponent($candidate)) {
            return null;
        }
        if ($mustExist || file_exists($candidate)) {
            $real = realpath($candidate);
            if ($real === false || !str_starts_with($real, $this->storageRoot . DIRECTORY_SEPARATOR) || is_link($real)) {
                return null;
            }
            return $real;
        }
        $parent = realpath(dirname($candidate));
        if ($parent === false || !str_starts_with($parent . DIRECTORY_SEPARATOR, $this->storageRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $candidate;
    }

    private function deleteStoredPathImmediately(string $relativePath): void
    {
        $path = $this->safeAbsolutePath($relativePath, false);
        if ($path !== null && is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }


    private function containsSymlinkComponent(string $path): bool
    {
        $relative = ltrim(substr($path, strlen($this->storageRoot)), DIRECTORY_SEPARATOR);
        if ($relative === '') {
            return is_link($this->storageRoot);
        }
        $current = $this->storageRoot;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $part) {
            if ($part === '') {
                continue;
            }
            $current .= DIRECTORY_SEPARATOR . $part;
            if (is_link($current)) {
                return true;
            }
            if (!file_exists($current)) {
                break;
            }
        }
        return false;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
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

    private function conflict(): array
    {
        return ['code' => 'CONFLICT', 'message' => trans('concurrent_question_media_conflict')];
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
