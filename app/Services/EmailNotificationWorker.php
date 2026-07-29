<?php

declare(strict_types=1);

namespace App\Services;

use App\Notifications\AssessmentCompletionEmailBuilder;
use App\Notifications\EmailRetryPolicy;
use App\Notifications\MailTransportInterface;
use App\Repositories\EmailNotificationRepository;
use App\Support\Logger;
use RuntimeException;
use Throwable;

final class EmailNotificationWorker
{
    public function __construct(
        private EmailNotificationRepository $repository,
        private MailTransportInterface $transport,
        private AssessmentCompletionEmailBuilder $builder,
        private EmailRetryPolicy $retryPolicy,
        private int $maxAttempts = 5,
        private int $staleLockMinutes = 15
    ) {
        $this->maxAttempts = max(1, min(20, $this->maxAttempts));
        $this->staleLockMinutes = max(1, min(1440, $this->staleLockMinutes));
    }

    /**
     * @return array{claimed:int,sent:int,retried:int,failed:int,stale_recovered:int}
     */
    public function run(int $batchSize, string $workerId): array
    {
        $staleRecovered = $this->repository->recoverStaleProcessing(
            $this->staleLockMinutes,
            $this->maxAttempts
        );
        $notifications = $this->repository->claimBatch(
            $workerId,
            $batchSize,
            $this->maxAttempts
        );

        $sent = 0;
        $retried = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            $notificationId = (int)($notification['id'] ?? 0);
            $attempts = (int)($notification['attempts'] ?? 0);

            try {
                if ((string)($notification['type'] ?? '') !== 'ASSESSMENT_COMPLETED') {
                    throw new RuntimeException('unsupported_notification_type');
                }

                $payload = json_decode(
                    (string)($notification['payload'] ?? ''),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                if (!is_array($payload)) {
                    throw new RuntimeException('notification_payload_invalid');
                }

                $message = $this->builder->build($notification, $payload);
                $this->transport->send($message);

                if (!$this->repository->markSent($notificationId, $workerId)) {
                    throw new RuntimeException('notification_delivery_state_conflict');
                }
                $sent++;
            } catch (Throwable $exception) {
                $technicalError = $this->retryPolicy->technicalErrorCode($exception);
                $status = $this->repository->markFailure(
                    $notificationId,
                    $workerId,
                    $attempts,
                    $this->maxAttempts,
                    $technicalError,
                    $this->retryPolicy->nextAttemptAt($attempts)
                );

                if ($status === 'FAILED') {
                    $failed++;
                } else {
                    $retried++;
                }

                Logger::log('ERROR', 'Email notification delivery failed.', [
                    'notification_id' => $notificationId,
                    'attempt_number' => $attempts,
                    'queue_status' => $status,
                    'technical_error' => $technicalError,
                ]);
            }
        }

        return [
            'claimed' => count($notifications),
            'sent' => $sent,
            'retried' => $retried,
            'failed' => $failed,
            'stale_recovered' => $staleRecovered,
        ];
    }
}
