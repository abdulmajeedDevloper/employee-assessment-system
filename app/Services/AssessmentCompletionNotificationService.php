<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class AssessmentCompletionNotificationService
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @return array{code:string,notification_id?:int,notification_ids?:array<int,int>,queued_count?:int,duplicate_count?:int} */
    public function enqueueIfEligible(int $employeeId, int $triggeringAttemptId): array
    {
        if ($employeeId <= 0 || $triggeringAttemptId <= 0) {
            return ['code' => 'INVALID_INPUT'];
        }

        if (!(bool)filter_var(env('MAIL_NOTIFICATIONS_ENABLED', true), FILTER_VALIDATE_BOOL)) {
            return ['code' => 'DISABLED'];
        }

        $employeeStmt = $this->pdo->prepare("
            SELECT
                e.id,
                e.full_name,
                e.national_id_last4,
                e.employee_number,
                e.preferred_language,
                d.name_ar AS department_name_ar,
                d.name_en AS department_name_en,
                s.name_ar AS section_name_ar,
                s.name_en AS section_name_en,
                ap.overall_status,
                ap.completed_at
            FROM employees e
            JOIN departments d ON d.id = e.department_id
            JOIN sections s ON s.id = e.section_id
            JOIN assessment_progress ap ON ap.employee_id = e.id
            WHERE e.id = :employee_id
              AND ap.overall_status = 'COMPLETED'
              AND ap.completed_at IS NOT NULL
            LIMIT 1
        ");
        $employeeStmt->execute([':employee_id' => $employeeId]);
        $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            return ['code' => 'NOT_COMPLETED'];
        }

        $recipients = $this->configuredRecipients();
        if ($recipients === []) {
            return ['code' => 'RECIPIENTS_NOT_CONFIGURED'];
        }

        $requiredCodes = $this->requiredTestCodes();
        $placeholders = implode(',', array_fill(0, count($requiredCodes), '?'));
        $resultStmt = $this->pdo->prepare("
            SELECT
                v.attempt_id,
                v.test_id,
                t.code,
                t.name_ar,
                t.name_en,
                v.score,
                v.total_score,
                v.percentage,
                v.passed,
                v.attempt_status,
                v.submitted_at
            FROM vw_official_assessment_results v
            JOIN tests t ON t.id = v.test_id
            WHERE v.employee_id = ?
              AND t.code IN ({$placeholders})
            ORDER BY t.display_order ASC, t.id ASC
        ");
        $resultStmt->execute(array_merge([$employeeId], $requiredCodes));
        $rows = $resultStmt->fetchAll(PDO::FETCH_ASSOC);

        $byCode = [];
        foreach ($rows as $row) {
            $byCode[(string)$row['code']] = $row;
        }
        foreach ($requiredCodes as $requiredCode) {
            if (!isset($byCode[$requiredCode])) {
                return ['code' => 'REQUIRED_RESULTS_MISSING'];
            }
        }

        $results = [];
        $attemptIds = [];
        $allPassed = true;
        foreach ($requiredCodes as $code) {
            $row = $byCode[$code];
            $attemptIds[] = (int)$row['attempt_id'];
            $passed = (int)$row['passed'] === 1;
            $allPassed = $allPassed && $passed;
            $results[] = [
                'code' => $code,
                'name_ar' => (string)$row['name_ar'],
                'name_en' => (string)$row['name_en'],
                'score' => round((float)$row['score'], 2),
                'total_score' => round((float)$row['total_score'], 2),
                'percentage' => round((float)$row['percentage'], 2),
                'passed' => $passed,
                'attempt_status' => (string)$row['attempt_status'],
                'submitted_at_utc' => (string)$row['submitted_at'],
            ];
        }

        $payload = [
            'schema_version' => 1,
            'employee_id' => $employeeId,
            'employee' => [
                'name' => (string)$employee['full_name'],
                'masked_national_id' => '********' . (string)$employee['national_id_last4'],
                'employee_number' => (string)($employee['employee_number'] ?? ''),
                'preferred_language' => in_array((string)$employee['preferred_language'], ['ar', 'en'], true)
                    ? (string)$employee['preferred_language']
                    : 'ar',
                'department' => [
                    'ar' => (string)$employee['department_name_ar'],
                    'en' => (string)$employee['department_name_en'],
                ],
                'section' => [
                    'ar' => (string)$employee['section_name_ar'],
                    'en' => (string)$employee['section_name_en'],
                ],
            ],
            'completion' => [
                'completed_at_utc' => (string)$employee['completed_at'],
                'final_status' => $allPassed ? 'PASSED' : 'FAILED',
                'results' => $results,
            ],
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $insert = $this->pdo->prepare("
            INSERT INTO email_notifications
                (deduplication_key, employee_id, attempt_id, recipient, type, payload,
                 status, attempts, locked_at, locked_by, next_attempt_at, sent_at,
                 error_message, created_at)
            VALUES
                (:deduplication_key, :employee_id, :attempt_id, :recipient,
                 'ASSESSMENT_COMPLETED', :payload, 'PENDING', 0, NULL, NULL,
                 UTC_TIMESTAMP(), NULL, NULL, UTC_TIMESTAMP())
        ");
        $audit = $this->pdo->prepare("
            INSERT INTO audit_logs
                (actor_type, employee_id, admin_id, action, entity_type, entity_id,
                 old_values, new_values, ip_address, user_agent, created_at)
            VALUES
                ('SYSTEM', NULL, NULL, 'ASSESSMENT_COMPLETION_NOTIFICATION_QUEUED',
                 'EMAIL_NOTIFICATION', :entity_id, NULL, :new_values,
                 '127.0.0.1', 'SYSTEM_NOTIFICATION_OUTBOX', UTC_TIMESTAMP())
        ");

        $notificationIds = [];
        $duplicateCount = 0;
        foreach ($recipients as $recipient) {
            $deduplicationKey = hash(
                'sha256',
                'assessment-completed|' . $employeeId . '|' . implode(',', $attemptIds) . '|' . strtolower($recipient)
            );

            try {
                $insert->execute([
                    ':deduplication_key' => $deduplicationKey,
                    ':employee_id' => $employeeId,
                    ':attempt_id' => $triggeringAttemptId,
                    ':recipient' => $recipient,
                    ':payload' => $payloadJson,
                ]);
            } catch (PDOException $exception) {
                $driverCode = (int)($exception->errorInfo[1] ?? 0);
                if ($exception->getCode() === '23000' && $driverCode === 1062) {
                    $duplicateCount++;
                    continue;
                }
                throw $exception;
            }

            $notificationId = (int)$this->pdo->lastInsertId();
            if ($notificationId <= 0) {
                throw new RuntimeException('notification_insert_id_missing');
            }
            $notificationIds[] = $notificationId;

            $audit->execute([
                ':entity_id' => $notificationId,
                ':new_values' => json_encode([
                    'queued_field_names' => ['type', 'employee_id', 'attempt_id'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        }

        if ($notificationIds === []) {
            return ['code' => 'DUPLICATE'];
        }

        return [
            'code' => 'QUEUED',
            'notification_id' => $notificationIds[0],
            'notification_ids' => $notificationIds,
            'queued_count' => count($notificationIds),
            'duplicate_count' => $duplicateCount,
        ];
    }

    /** @return array<int,string> */
    private function configuredRecipients(): array
    {
        $raw = trim((string)env('ASSESSMENT_COMPLETION_RECIPIENTS', ''));
        if ($raw === '') {
            return [];
        }

        $recipients = [];
        foreach (preg_split('/[,;\r\n]+/', $raw) ?: [] as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $recipients[$candidate] = $candidate;
        }

        return array_values($recipients);
    }

    /** @return array<int,string> */
    private function requiredTestCodes(): array
    {
        $raw = trim((string)env('NOTIFICATION_REQUIRED_TEST_CODES', 'IQ_TEST,ENG_TEST'));
        $codes = array_values(array_unique(array_filter(array_map(
            static fn(string $value): string => strtoupper(trim($value)),
            explode(',', $raw)
        ), static fn(string $value): bool => preg_match('/^[A-Z0-9_-]{1,64}$/D', $value) === 1)));

        return $codes !== [] ? $codes : ['IQ_TEST', 'ENG_TEST'];
    }
}
