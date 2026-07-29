<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI execution required.\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap/init.php';

use App\Services\AssessmentCompletionNotificationService;
use App\Support\Database;

$limit = 500;
$afterId = 0;
foreach ($argv as $argument) {
    if (preg_match('/^--limit=(\d+)$/D', $argument, $matches) === 1) {
        $limit = max(1, min(5000, (int)$matches[1]));
    }
    if (preg_match('/^--after-id=(\d+)$/D', $argument, $matches) === 1) {
        $afterId = max(0, (int)$matches[1]);
    }
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT
            ap.employee_id,
            MAX(v.attempt_id) AS triggering_attempt_id
        FROM assessment_progress ap
        JOIN vw_official_assessment_results v ON v.employee_id = ap.employee_id
        WHERE ap.overall_status = 'COMPLETED'
          AND ap.completed_at IS NOT NULL
          AND ap.employee_id > :after_id
        GROUP BY ap.employee_id
        ORDER BY ap.employee_id ASC
        LIMIT {$limit}
    ");
    $stmt->execute([':after_id' => $afterId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $service = new AssessmentCompletionNotificationService($pdo);
    $counts = [
        'QUEUED' => 0,
        'DUPLICATE' => 0,
        'REQUIRED_RESULTS_MISSING' => 0,
        'RECIPIENTS_NOT_CONFIGURED' => 0,
        'OTHER' => 0,
    ];
    $lastId = $afterId;

    foreach ($rows as $row) {
        $employeeId = (int)$row['employee_id'];
        $lastId = max($lastId, $employeeId);
        $result = $service->enqueueIfEligible(
            $employeeId,
            (int)$row['triggering_attempt_id']
        );
        $code = (string)($result['code'] ?? 'OTHER');
        if (isset($counts[$code])) {
            $counts[$code]++;
        } else {
            $counts['OTHER']++;
        }
    }

    echo "=== COMPLETION NOTIFICATION RECONCILIATION ===\n";
    echo "Processed                : " . count($rows) . "\n";
    echo "Queued                   : {$counts['QUEUED']}\n";
    echo "Already queued           : {$counts['DUPLICATE']}\n";
    echo "Missing required results : {$counts['REQUIRED_RESULTS_MISSING']}\n";
    echo "Recipients not configured: {$counts['RECIPIENTS_NOT_CONFIGURED']}\n";
    echo "Other                    : {$counts['OTHER']}\n";
    echo "Next cursor              : {$lastId}\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Reconciliation failed: " . get_class($exception) . ':' . (string)$exception->getCode() . "\n");
    exit(1);
}
