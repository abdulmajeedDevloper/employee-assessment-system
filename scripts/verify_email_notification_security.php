<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'queue_service' => $root . '/app/Services/AssessmentCompletionNotificationService.php',
    'worker' => $root . '/app/Services/EmailNotificationWorker.php',
    'repository' => $root . '/app/Repositories/EmailNotificationRepository.php',
    'builder' => $root . '/app/Notifications/AssessmentCompletionEmailBuilder.php',
    'transport' => $root . '/app/Notifications/PhpMailerTransport.php',
    'attempt_service' => $root . '/app/Services/AssessmentAttemptService.php',
    'worker_script' => $root . '/scripts/process_email_notifications.php',
    'migration' => $root . '/database/migrations/005_email_notification_queue_hardening.up.sql',
    'env' => $root . '/.env.example',
];

$content = [];
foreach ($paths as $key => $path) {
    $value = file_get_contents($path);
    if ($value === false) {
        fwrite(STDERR, "Missing verification input: {$path}\n");
        exit(1);
    }
    $content[$key] = $value;
}

$checks = [
    'completion is integrated into manual submission' => substr_count($content['attempt_service'], 'enqueueIfEligible(') >= 2,
    'notification is deduplicated with SHA-256' => str_contains($content['queue_service'], "'sha256'") && str_contains($content['queue_service'], "'assessment-completed|'"),
    'full National ID is not selected' => !str_contains($content['queue_service'], 'national_id_ciphertext') && !str_contains($content['queue_service'], 'national_id_hash'),
    'employee email is not used as completion recipient' => !preg_match('/e\.email|employee\[[\x27\x22]email/', $content['queue_service']) && str_contains($content['queue_service'], 'ASSESSMENT_COMPLETION_RECIPIENTS'),
    'masked identity uses last four digits' => str_contains($content['queue_service'], "'********' . (string)\$employee['national_id_last4']"),
    'required results are configurable' => str_contains($content['queue_service'], 'NOTIFICATION_REQUIRED_TEST_CODES'),
    'queue claim uses row lock skipping' => str_contains($content['repository'], 'FOR UPDATE SKIP LOCKED'),
    'claim increments attempt count atomically' => str_contains($content['repository'], 'attempts = attempts + 1'),
    'stale processing rows recover' => str_contains($content['repository'], 'MAIL_WORKER_LOCK_TIMEOUT'),
    'send transition clears worker lock' => str_contains($content['repository'], "status = 'SENT'") && str_contains($content['repository'], 'locked_by = NULL'),
    'failure stores technical code only' => str_contains($content['worker'], 'technicalErrorCode($exception)') && !str_contains($content['worker'], 'getMessage()'),
    'worker logs no recipient or payload' => !preg_match('/Logger::log[\s\S]{0,500}(recipient|payload|htmlBody|textBody)/i', $content['worker']),
    'HTML values are escaped' => str_contains($content['builder'], 'htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE'),
    'plain-text alternative is generated' => str_contains($content['builder'], 'new MailMessage($recipient, $subject, $html, $text'),
    'SMTP debug is disabled' => str_contains($content['transport'], '$mail->SMTPDebug = 0'),
    'SMTP message ID is deterministic' => str_contains($content['transport'], 'assessment-\' . $message->idempotencyKey'),
    'SMTP password comes from environment' => str_contains($content['transport'], "env('MAIL_PASSWORD'") && !preg_match('/MAIL_PASSWORD\s*=\s*[^\r\n]*[A-Za-z0-9]{8,}/', $content['env']),
    'worker supports non-mutating dry run' => str_contains($content['worker_script'], "in_array('--dry-run'"),
    'database enforces nonnegative attempts' => str_contains($content['migration'], 'chk_mail_attempts_nonnegative'),
    'database enforces sent timestamp consistency' => str_contains($content['migration'], 'chk_mail_sent_timestamp'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    fwrite(STDOUT, ($passed ? 'PASS' : 'FAIL') . " — {$label}\n");
    if (!$passed) {
        $failed++;
    }
}

fwrite(STDOUT, 'checks=' . count($checks) . " failed={$failed}\n");
exit($failed === 0 ? 0 : 1);
