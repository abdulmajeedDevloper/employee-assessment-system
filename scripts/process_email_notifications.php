<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI execution required.\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap/init.php';

use App\Notifications\AssessmentCompletionEmailBuilder;
use App\Notifications\EmailRetryPolicy;
use App\Notifications\PhpMailerTransport;
use App\Repositories\EmailNotificationRepository;
use App\Services\EmailNotificationWorker;
use App\Support\Database;
use App\Support\Logger;

try {
    $enabled = filter_var(env('MAIL_NOTIFICATIONS_ENABLED', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($enabled === false) {
        echo "Email notification worker is disabled by MAIL_NOTIFICATIONS_ENABLED.\n";
        exit(0);
    }

    $pdo = Database::getInstance();
    $readSetting = static function (string $key, string $default) use ($pdo): string {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute([':setting_key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    };

    $batchSize = max(1, min(250, (int)$readSetting(
        'mail_queue_batch_size',
        (string)env('MAIL_QUEUE_BATCH_SIZE', 25)
    )));
    $maxAttempts = max(1, min(20, (int)$readSetting(
        'mail_queue_max_attempts',
        (string)env('MAIL_QUEUE_MAX_ATTEMPTS', 5)
    )));
    $staleLockMinutes = max(1, min(1440, (int)env('MAIL_QUEUE_STALE_LOCK_MINUTES', 15)));

    $repository = new EmailNotificationRepository($pdo);

    if (in_array('--dry-run', $argv, true)) {
        $ready = $repository->countReady($maxAttempts);
        echo "=== EMAIL NOTIFICATION QUEUE DRY RUN ===\n";
        echo "Ready notifications: {$ready}\n";
        echo "Batch size         : {$batchSize}\n";
        echo "Max attempts       : {$maxAttempts}\n";
        exit(0);
    }

    $organizationNameAr = $readSetting('organization_name_ar', 'نظام تقييم الموظفين');
    $organizationNameEn = $readSetting('organization_name_en', 'Employee Assessment System');
    $appUrl = trim((string)env('APP_URL', ''));
    if ($appUrl === '') {
        throw new RuntimeException('APP_URL must be configured before sending email notifications.');
    }

    $workerId = substr(
        (gethostname() ?: 'host') . '-' . getmypid() . '-' . bin2hex(random_bytes(6)),
        0,
        64
    );

    $transport = PhpMailerTransport::fromEnvironment();
    $builder = new AssessmentCompletionEmailBuilder(
        $organizationNameAr,
        $organizationNameEn,
        $appUrl,
        (string)env('APP_TIMEZONE', 'UTC')
    );
    $worker = new EmailNotificationWorker(
        $repository,
        $transport,
        $builder,
        new EmailRetryPolicy(),
        $maxAttempts,
        $staleLockMinutes
    );

    $result = $worker->run($batchSize, $workerId);
    echo "=== EMAIL NOTIFICATION WORKER REPORT ===\n";
    echo 'Timestamp       : ' . gmdate('Y-m-d H:i:s') . " UTC\n";
    echo 'Claimed         : ' . $result['claimed'] . "\n";
    echo 'Sent            : ' . $result['sent'] . "\n";
    echo 'Scheduled retry : ' . $result['retried'] . "\n";
    echo 'Failed final    : ' . $result['failed'] . "\n";
    echo 'Stale recovered : ' . $result['stale_recovered'] . "\n";

    exit($result['failed'] > 0 ? 3 : 0);
} catch (Throwable $exception) {
    Logger::log('CRITICAL', 'Email notification worker could not start.', [
        'technical_error' => get_class($exception) . ':' . (string)$exception->getCode(),
    ]);
    fwrite(STDERR, "Email notification worker failed to start. Check protected application logs.\n");
    exit(1);
}
