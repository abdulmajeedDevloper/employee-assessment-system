<?php

declare(strict_types=1);

use App\Services\AdminQuestionMediaService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../bootstrap/init.php';

$rawBatch = $_ENV['MEDIA_DELETION_BATCH_SIZE'] ?? '25';
$batchSize = is_scalar($rawBatch) && preg_match('/^[1-9][0-9]*$/', (string)$rawBatch) === 1
    ? max(1, min(100, (int)$rawBatch))
    : 25;

try {
    $processed = (new AdminQuestionMediaService())->processPendingDeletions($batchSize);
    fwrite(STDOUT, "Processed {$processed} pending media deletion(s)." . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    error_log('process_pending_media_deletions fatal error: ' . $e->getMessage());
    fwrite(STDERR, "Media deletion processing failed. Review the application log." . PHP_EOL);
    exit(1);
}
