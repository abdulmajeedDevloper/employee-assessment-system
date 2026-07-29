<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI execution required.\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap/init.php';

use App\Services\AssessmentAttemptService;

try {
    $dryRun = in_array('--dry-run', $argv, true);
    $batchSize = (int) ($_ENV['FINALIZE_EXPIRED_BATCH_SIZE'] ?? 50);
    $batchSize = max(1, min(500, $batchSize));

    $service = new AssessmentAttemptService();
    $result = $service->finalizeAllExpiredAttempts($batchSize, $dryRun);

    if (($result['lock_acquired'] ?? false) === false) {
        echo "WORKER SKIPPED: Worker lock 'employee_assessment_finalize_expired' could not be acquired.\n";
        exit(2);
    }

    $scanned = (int) ($result['scanned'] ?? 0);
    $finalized = (int) ($result['finalized'] ?? 0);
    $skipped = (int) ($result['skipped'] ?? 0);
    $failed = (int) ($result['failed'] ?? 0);

    $modeText = $dryRun ? ' (DRY RUN)' : '';
    echo "=== EXPIRED ASSESSMENT FINALIZATION REPORT{$modeText} ===\n";
    echo "Timestamp  : " . date('Y-m-d H:i:s') . " UTC\n";
    echo "Batch Size : {$batchSize}\n";
    echo "Scanned    : {$scanned}\n";
    echo "Finalized  : {$finalized}\n";
    echo "Skipped    : {$skipped}\n";
    echo "Failed     : {$failed}\n";
    echo "--------------------------------------------------------\n";

    if ($failed > 0) {
        echo "STATUS: PARTIAL_FAILURE - {$failed} attempt(s) encountered processing errors.\n";
        exit(3);
    }

    echo "STATUS: SUCCESS\n";
    exit(0);
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}