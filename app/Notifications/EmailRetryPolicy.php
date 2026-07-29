<?php

declare(strict_types=1);

namespace App\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class EmailRetryPolicy
{
    public function delaySeconds(int $attemptNumber): int
    {
        $attemptNumber = max(1, $attemptNumber);
        $schedule = [300, 900, 3600, 14400, 43200, 86400];
        return $schedule[min($attemptNumber - 1, count($schedule) - 1)];
    }

    public function nextAttemptAt(int $attemptNumber): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $now->modify('+' . $this->delaySeconds($attemptNumber) . ' seconds')
            ->format('Y-m-d H:i:s');
    }

    public function technicalErrorCode(Throwable $exception): string
    {
        $class = str_replace('\\', '_', get_class($exception));
        $class = preg_replace('/[^A-Za-z0-9_]/', '', $class) ?: 'Throwable';
        $code = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$exception->getCode());
        $value = 'MAIL_DELIVERY_FAILED:' . $class;
        if ($code !== '') {
            $value .= ':' . $code;
        }
        return substr($value, 0, 255);
    }
}
