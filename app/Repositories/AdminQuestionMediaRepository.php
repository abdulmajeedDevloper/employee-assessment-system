<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AdminQuestionMediaRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    public function optionBelongsToQuestion(int $optionId, int $questionId, bool $forUpdate = false): bool
    {
        $sql = 'SELECT 1 FROM question_options WHERE id = :option_id AND question_id = :question_id LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':option_id' => $optionId, ':question_id' => $questionId]);
        return (bool)$stmt->fetchColumn();
    }

    public function findOwnerMediaForUpdate(string $mediaType, ?int $questionId, ?int $optionId): ?array
    {
        if ($mediaType === 'QUESTION_IMAGE' && $questionId !== null) {
            $sql = "SELECT * FROM question_media WHERE media_type = 'QUESTION_IMAGE' AND question_id = :owner_id LIMIT 1 FOR UPDATE";
            $ownerId = $questionId;
        } elseif ($mediaType === 'OPTION_IMAGE' && $optionId !== null) {
            $sql = "SELECT * FROM question_media WHERE media_type = 'OPTION_IMAGE' AND option_id = :owner_id LIMIT 1 FOR UPDATE";
            $ownerId = $optionId;
        } else {
            return null;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':owner_id', $ownerId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insertMedia(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO question_media (
                media_type, question_id, option_id, public_token, storage_path,
                original_filename, mime_type, file_extension, file_size_bytes,
                width_pixels, height_pixels, created_by, created_at
            ) VALUES (
                :media_type, :question_id, :option_id, :public_token, :storage_path,
                :original_filename, :mime_type, :file_extension, :file_size_bytes,
                :width_pixels, :height_pixels, :created_by, UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([
            ':media_type' => $data['media_type'],
            ':question_id' => $data['question_id'],
            ':option_id' => $data['option_id'],
            ':public_token' => $data['public_token'],
            ':storage_path' => $data['storage_path'],
            ':original_filename' => $data['original_filename'],
            ':mime_type' => $data['mime_type'],
            ':file_extension' => $data['file_extension'],
            ':file_size_bytes' => $data['file_size_bytes'],
            ':width_pixels' => $data['width_pixels'],
            ':height_pixels' => $data['height_pixels'],
            ':created_by' => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function replaceMedia(int $mediaId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE question_media
            SET public_token = :public_token,
                storage_path = :storage_path,
                original_filename = :original_filename,
                mime_type = :mime_type,
                file_extension = :file_extension,
                file_size_bytes = :file_size_bytes,
                width_pixels = :width_pixels,
                height_pixels = :height_pixels,
                created_by = :created_by,
                created_at = UTC_TIMESTAMP()
            WHERE id = :media_id
        ");
        $stmt->execute([
            ':public_token' => $data['public_token'],
            ':storage_path' => $data['storage_path'],
            ':original_filename' => $data['original_filename'],
            ':mime_type' => $data['mime_type'],
            ':file_extension' => $data['file_extension'],
            ':file_size_bytes' => $data['file_size_bytes'],
            ':width_pixels' => $data['width_pixels'],
            ':height_pixels' => $data['height_pixels'],
            ':created_by' => $data['created_by'],
            ':media_id' => $mediaId,
        ]);
    }

    public function deleteMedia(int $mediaId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM question_media WHERE id = :media_id');
        $stmt->execute([':media_id' => $mediaId]);
    }

    public function queueDeletion(string $storagePath): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pending_media_deletions (
                storage_path, queued_at, next_attempt_at, processed_at,
                attempts, last_error, status
            ) VALUES (
                :storage_path, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL,
                0, NULL, 'PENDING'
            )
        ");
        $stmt->execute([':storage_path' => $storagePath]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByPublicToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                qm.id, qm.media_type, qm.public_token, qm.storage_path,
                qm.mime_type, qm.file_extension, qm.file_size_bytes,
                qm.width_pixels, qm.height_pixels,
                COALESCE(qm.question_id, qo.question_id) AS owner_question_id
            FROM question_media qm
            LEFT JOIN question_options qo ON qo.id = qm.option_id
            WHERE qm.public_token = :public_token
            LIMIT 1
        ");
        $stmt->execute([':public_token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findNextPendingDeletionForUpdate(): ?array
    {
        $stmt = $this->pdo->query("
            SELECT id, storage_path, attempts
            FROM pending_media_deletions
            WHERE status = 'PENDING'
              AND next_attempt_at <= UTC_TIMESTAMP()
              AND attempts < 5
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function markDeletionProcessed(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE pending_media_deletions
            SET status = 'PROCESSED', processed_at = UTC_TIMESTAMP(),
                attempts = attempts + 1, last_error = NULL
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
    }

    public function rescheduleDeletion(int $id, int $attempts, string $errorCode): void
    {
        $nextAttempts = $attempts + 1;
        $status = $nextAttempts >= 5 ? 'FAILED' : 'PENDING';
        $stmt = $this->pdo->prepare("
            UPDATE pending_media_deletions
            SET status = :status,
                attempts = :attempts,
                last_error = :last_error,
                next_attempt_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':attempts' => $nextAttempts,
            ':last_error' => $errorCode,
            ':id' => $id,
        ]);
    }
}
