<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class EmailNotificationRepository
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function recoverStaleProcessing(int $staleMinutes, int $maxAttempts): int
    {
        $staleMinutes = max(1, min(1440, $staleMinutes));
        $maxAttempts = max(1, min(20, $maxAttempts));

        $sql = "
            UPDATE email_notifications
            SET status = CASE WHEN attempts >= :max_attempts THEN 'FAILED' ELSE 'PENDING' END,
                locked_at = NULL,
                locked_by = NULL,
                next_attempt_at = UTC_TIMESTAMP(),
                error_message = 'MAIL_WORKER_LOCK_TIMEOUT'
            WHERE status = 'PROCESSING'
              AND locked_at IS NOT NULL
              AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$staleMinutes} MINUTE)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':max_attempts' => $maxAttempts]);
        return $stmt->rowCount();
    }

    /** @return array<int,array<string,mixed>> */
    public function claimBatch(string $workerId, int $batchSize, int $maxAttempts): array
    {
        $workerId = trim($workerId);
        if ($workerId === '' || strlen($workerId) > 64) {
            throw new RuntimeException('mail_worker_id_invalid');
        }

        $batchSize = max(1, min(250, $batchSize));
        $maxAttempts = max(1, min(20, $maxAttempts));

        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare("
                SELECT id
                FROM email_notifications
                WHERE status = 'PENDING'
                  AND next_attempt_at <= UTC_TIMESTAMP()
                  AND attempts < :max_attempts
                ORDER BY next_attempt_at ASC, id ASC
                LIMIT {$batchSize}
                FOR UPDATE
            ");
            $select->execute([':max_attempts' => $maxAttempts]);
            $ids = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));

            if ($ids === []) {
                $this->pdo->commit();
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $update = $this->pdo->prepare("
                UPDATE email_notifications
                SET status = 'PROCESSING',
                    attempts = attempts + 1,
                    locked_at = UTC_TIMESTAMP(),
                    locked_by = ?
                WHERE id IN ({$placeholders})
                  AND status = 'PENDING'
            ");
            $update->execute(array_merge([$workerId], $ids));

            $fetch = $this->pdo->prepare("
                SELECT id, deduplication_key, employee_id, attempt_id, recipient, type,
                       payload, status, attempts, locked_at, locked_by, next_attempt_at,
                       sent_at, error_message, created_at
                FROM email_notifications
                WHERE id IN ({$placeholders})
                  AND status = 'PROCESSING'
                  AND locked_by = ?
                ORDER BY id ASC
            ");
            $fetch->execute(array_merge($ids, [$workerId]));
            $rows = $fetch->fetchAll(PDO::FETCH_ASSOC);
            $this->pdo->commit();
            return $rows;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function markSent(int $notificationId, string $workerId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE email_notifications
            SET status = 'SENT',
                sent_at = UTC_TIMESTAMP(),
                locked_at = NULL,
                locked_by = NULL,
                error_message = NULL
            WHERE id = :id
              AND status = 'PROCESSING'
              AND locked_by = :worker_id
        ");
        $stmt->execute([
            ':id' => $notificationId,
            ':worker_id' => $workerId,
        ]);
        return $stmt->rowCount() === 1;
    }

    public function markFailure(
        int $notificationId,
        string $workerId,
        int $attempts,
        int $maxAttempts,
        string $technicalError,
        string $nextAttemptAt
    ): string {
        $finalStatus = $attempts >= $maxAttempts ? 'FAILED' : 'PENDING';
        $technicalError = substr(trim($technicalError), 0, 1000);
        if ($technicalError === '') {
            $technicalError = 'MAIL_DELIVERY_FAILED';
        }

        $stmt = $this->pdo->prepare("
            UPDATE email_notifications
            SET status = :status,
                next_attempt_at = :next_attempt_at,
                locked_at = NULL,
                locked_by = NULL,
                error_message = :error_message
            WHERE id = :id
              AND status = 'PROCESSING'
              AND locked_by = :worker_id
        ");
        $stmt->execute([
            ':status' => $finalStatus,
            ':next_attempt_at' => $nextAttemptAt,
            ':error_message' => $technicalError,
            ':id' => $notificationId,
            ':worker_id' => $workerId,
        ]);

        return $stmt->rowCount() === 1 ? $finalStatus : 'UNCHANGED';
    }

    public function countReady(int $maxAttempts): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM email_notifications
            WHERE status = 'PENDING'
              AND next_attempt_at <= UTC_TIMESTAMP()
              AND attempts < :max_attempts
        ");
        $stmt->execute([':max_attempts' => max(1, min(20, $maxAttempts))]);
        return (int)$stmt->fetchColumn();
    }
}
