<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;
use PDOStatement;

class AdminAttemptRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * @return array{items:array,total:int,page:int,per_page:int,total_pages:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        [$whereSql, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare("\n            SELECT COUNT(*)\n            FROM test_attempts att\n            INNER JOIN employees e ON e.id = att.employee_id\n            INNER JOIN tests t ON t.id = att.test_id\n            INNER JOIN test_assignments ta ON ta.id = att.assignment_id\n            {$whereSql}\n        ");
        $this->bindParams($countStmt, $params);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("\n            SELECT\n                att.id,\n                att.employee_id,\n                att.test_id,\n                att.assignment_id,\n                att.attempt_number,\n                att.started_at,\n                att.submitted_at,\n                att.expires_at,\n                att.duration_seconds,\n                att.score,\n                att.total_score,\n                att.percentage,\n                att.passed,\n                att.status,\n                att.submission_reason,\n                att.focus_loss_count,\n                att.cancelled_at,\n                att.updated_at,\n                e.full_name AS employee_name,\n                e.employee_number,\n                d.name_ar AS department_name_ar,\n                d.name_en AS department_name_en,\n                t.code AS test_code,\n                t.name_ar AS test_name_ar,\n                t.name_en AS test_name_en,\n                ta.status AS assignment_status,\n                ta.max_attempts,\n                COALESCE(ans.answered_count, 0) AS answered_count,\n                COALESCE(ans.question_count, 0) AS question_count,\n                COALESCE(sec.security_event_count, 0) AS security_event_count,\n                CASE\n                    WHEN att.status = 'IN_PROGRESS' AND att.expires_at <= UTC_TIMESTAMP() THEN 1\n                    ELSE 0\n                END AS is_expired_in_progress\n            FROM test_attempts att\n            INNER JOIN employees e ON e.id = att.employee_id\n            INNER JOIN departments d ON d.id = e.department_id\n            INNER JOIN tests t ON t.id = att.test_id\n            INNER JOIN test_assignments ta ON ta.id = att.assignment_id\n            LEFT JOIN (\n                SELECT\n                    aq.attempt_id,\n                    COUNT(aq.id) AS question_count,\n                    COUNT(aa.id) AS answered_count\n                FROM attempt_questions aq\n                LEFT JOIN attempt_answers aa ON aa.attempt_question_id = aq.id\n                GROUP BY aq.attempt_id\n            ) ans ON ans.attempt_id = att.id\n            LEFT JOIN (\n                SELECT attempt_id, COUNT(*) AS security_event_count\n                FROM security_events\n                WHERE attempt_id IS NOT NULL\n                GROUP BY attempt_id\n            ) sec ON sec.attempt_id = att.id\n            {$whereSql}\n            ORDER BY att.started_at DESC, att.id DESC\n            LIMIT :limit OFFSET :offset\n        ");
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function getStatistics(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("\n            SELECT\n                COUNT(*) AS total_attempts,\n                SUM(att.status = 'IN_PROGRESS') AS in_progress_attempts,\n                SUM(att.status = 'COMPLETED') AS completed_attempts,\n                SUM(att.status = 'TIMED_OUT') AS timed_out_attempts,\n                SUM(att.status = 'CANCELLED') AS cancelled_attempts,\n                SUM(att.status IN ('COMPLETED', 'TIMED_OUT') AND att.passed = 1) AS passed_attempts,\n                SUM(att.status IN ('COMPLETED', 'TIMED_OUT') AND att.passed = 0) AS failed_attempts,\n                SUM(att.status = 'IN_PROGRESS' AND att.expires_at <= UTC_TIMESTAMP()) AS expired_in_progress_attempts\n            FROM test_attempts att\n            INNER JOIN employees e ON e.id = att.employee_id\n            INNER JOIN tests t ON t.id = att.test_id\n            INNER JOIN test_assignments ta ON ta.id = att.assignment_id\n            {$whereSql}\n        ");
        $this->bindParams($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ([
            'total_attempts', 'in_progress_attempts', 'completed_attempts',
            'timed_out_attempts', 'cancelled_attempts', 'passed_attempts',
            'failed_attempts', 'expired_in_progress_attempts',
        ] as $key) {
            $row[$key] = (int)($row[$key] ?? 0);
        }

        return $row;
    }

    public function getEmployeesForFilter(): array
    {
        $stmt = $this->pdo->query("\n            SELECT DISTINCT e.id, e.full_name, e.employee_number\n            FROM employees e\n            INNER JOIN test_attempts att ON att.employee_id = e.id\n            ORDER BY e.full_name ASC, e.id ASC\n        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTestsForFilter(): array
    {
        $stmt = $this->pdo->query("\n            SELECT DISTINCT t.id, t.code, t.name_ar, t.name_en, t.is_active, t.display_order\n            FROM tests t\n            INNER JOIN test_attempts att ON att.test_id = t.id\n            ORDER BY t.display_order ASC, t.id ASC\n        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSafeById(int $attemptId): ?array
    {
        $stmt = $this->pdo->prepare("\n            SELECT\n                att.*,\n                e.full_name AS employee_name,\n                e.employee_number,\n                e.email AS employee_email,\n                e.phone AS employee_phone,\n                d.name_ar AS department_name_ar,\n                d.name_en AS department_name_en,\n                s.name_ar AS section_name_ar,\n                s.name_en AS section_name_en,\n                jt.name_ar AS job_title_name_ar,\n                jt.name_en AS job_title_name_en,\n                t.code AS test_code,\n                t.name_ar AS test_name_ar,\n                t.name_en AS test_name_en,\n                t.passing_percentage,\n                ta.status AS assignment_status,\n                ta.max_attempts,\n                ta.available_from AS assignment_available_from,\n                ta.available_until AS assignment_available_until,\n                cancel_admin.name AS cancelled_by_name,\n                COALESCE(ans.question_count, 0) AS question_count,\n                COALESCE(ans.answered_count, 0) AS answered_count,\n                COALESCE(sec.security_event_count, 0) AS security_event_count,\n                CASE\n                    WHEN att.status = 'IN_PROGRESS' AND att.expires_at <= UTC_TIMESTAMP() THEN 1\n                    ELSE 0\n                END AS is_expired_in_progress,\n                (\n                    SELECT COUNT(*)\n                    FROM retake_authorizations ra\n                    WHERE ra.assignment_id = att.assignment_id\n                      AND ra.consumed_at IS NULL\n                ) AS pending_retake_count,\n                (\n                    SELECT MAX(other.attempt_number)\n                    FROM test_attempts other\n                    WHERE other.assignment_id = att.assignment_id\n                ) AS latest_attempt_number\n            FROM test_attempts att\n            INNER JOIN employees e ON e.id = att.employee_id\n            INNER JOIN departments d ON d.id = e.department_id\n            INNER JOIN sections s ON s.id = e.section_id\n            INNER JOIN job_titles jt ON jt.id = e.job_title_id\n            INNER JOIN tests t ON t.id = att.test_id\n            INNER JOIN test_assignments ta ON ta.id = att.assignment_id\n            LEFT JOIN admins cancel_admin ON cancel_admin.id = att.cancelled_by\n            LEFT JOIN (\n                SELECT aq.attempt_id, COUNT(aq.id) AS question_count, COUNT(aa.id) AS answered_count\n                FROM attempt_questions aq\n                LEFT JOIN attempt_answers aa ON aa.attempt_question_id = aq.id\n                GROUP BY aq.attempt_id\n            ) ans ON ans.attempt_id = att.id\n            LEFT JOIN (\n                SELECT attempt_id, COUNT(*) AS security_event_count\n                FROM security_events\n                WHERE attempt_id IS NOT NULL\n                GROUP BY attempt_id\n            ) sec ON sec.attempt_id = att.id\n            WHERE att.id = :attempt_id\n            LIMIT 1\n        ");
        $stmt->bindValue(':attempt_id', $attemptId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findForUpdate(int $attemptId): ?array
    {
        $stmt = $this->pdo->prepare("\n            SELECT\n                att.*,\n                ta.status AS assignment_status,\n                ta.max_attempts,\n                ta.employee_id AS assignment_employee_id,\n                ta.test_id AS assignment_test_id\n            FROM test_attempts att\n            INNER JOIN test_assignments ta ON ta.id = att.assignment_id\n            WHERE att.id = :attempt_id\n            LIMIT 1\n            FOR UPDATE\n        ");
        $stmt->bindValue(':attempt_id', $attemptId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function getAnswers(int $attemptId): array
    {
        $stmt = $this->pdo->prepare("\n            SELECT\n                aq.id AS attempt_question_id,\n                aq.original_question_id,\n                aq.question_snapshot,\n                aq.options_snapshot,\n                aq.correct_option_key_snapshot,\n                aq.score_snapshot,\n                aq.display_order,\n                aa.selected_option_key,\n                aa.answer_text,\n                aa.is_correct,\n                aa.awarded_score,\n                aa.answered_at\n            FROM attempt_questions aq\n            LEFT JOIN attempt_answers aa ON aa.attempt_question_id = aq.id\n            WHERE aq.attempt_id = :attempt_id\n            ORDER BY aq.display_order ASC, aq.id ASC\n        ");
        $stmt->bindValue(':attempt_id', $attemptId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSecurityEvents(int $attemptId, int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        $stmt = $this->pdo->prepare("\n            SELECT id, event_type, metadata, ip_address, user_agent, created_at\n            FROM security_events\n            WHERE attempt_id = :attempt_id\n            ORDER BY created_at DESC, id DESC\n            LIMIT :limit\n        ");
        $stmt->bindValue(':attempt_id', $attemptId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSessions(int $attemptId): array
    {
        $stmt = $this->pdo->prepare("\n            SELECT id, ip_address, started_at, last_activity_at, ended_at, status\n            FROM test_sessions\n            WHERE attempt_id = :attempt_id\n            ORDER BY started_at DESC, id DESC\n        ");
        $stmt->bindValue(':attempt_id', $attemptId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAuditHistory(int $attemptId, int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        $stmt = $this->pdo->prepare("\n            SELECT al.id, al.actor_type, al.action, al.old_values, al.new_values,\n                   al.ip_address, al.created_at, a.name AS admin_name\n            FROM audit_logs al\n            LEFT JOIN admins a ON a.id = al.admin_id\n            WHERE al.entity_type IN ('test_attempt', 'TEST_ATTEMPT')\n              AND al.entity_id = :attempt_id\n            ORDER BY al.created_at DESC, al.id DESC\n            LIMIT :limit\n        ");
        $stmt->bindValue(':attempt_id', $attemptId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cancelInProgressAttempt(int $attemptId, int $adminId, string $reason): bool
    {
        $stmt = $this->pdo->prepare("\n            UPDATE test_attempts\n            SET status = 'CANCELLED',\n                submission_reason = 'ADMIN_CANCEL',\n                submitted_at = UTC_TIMESTAMP(),\n                cancelled_by = :admin_id,\n                cancelled_at = UTC_TIMESTAMP(),\n                cancellation_reason = :reason,\n                updated_at = UTC_TIMESTAMP()\n            WHERE id = :attempt_id\n              AND status = 'IN_PROGRESS'\n              AND expires_at > UTC_TIMESTAMP()\n        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':reason' => $reason,
            ':attempt_id' => $attemptId,
        ]);
        return $stmt->rowCount() === 1;
    }

    public function closeActiveSessions(int $attemptId, string $status = 'CLOSED'): void
    {
        if (!in_array($status, ['CLOSED', 'EXPIRED'], true)) {
            $status = 'CLOSED';
        }
        $stmt = $this->pdo->prepare("\n            UPDATE test_sessions\n            SET status = :status,\n                ended_at = UTC_TIMESTAMP(),\n                last_activity_at = UTC_TIMESTAMP()\n            WHERE attempt_id = :attempt_id\n              AND status = 'ACTIVE'\n        ");
        $stmt->execute([':status' => $status, ':attempt_id' => $attemptId]);
    }

    public function updateAssignmentStatus(int $assignmentId, string $status, ?int $maxAttempts = null): void
    {
        $allowed = ['PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED', 'COMPLETED'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid assignment status.');
        }

        if ($maxAttempts === null) {
            $stmt = $this->pdo->prepare("\n                UPDATE test_assignments\n                SET status = :status\n                WHERE id = :assignment_id\n            ");
            $stmt->execute([':status' => $status, ':assignment_id' => $assignmentId]);
            return;
        }

        $stmt = $this->pdo->prepare("\n            UPDATE test_assignments\n            SET status = :status, max_attempts = :max_attempts\n            WHERE id = :assignment_id\n        ");
        $stmt->execute([
            ':status' => $status,
            ':max_attempts' => $maxAttempts,
            ':assignment_id' => $assignmentId,
        ]);
    }

    public function updateSupervisorNotes(int $attemptId, ?string $notes): bool
    {
        $stmt = $this->pdo->prepare("\n            UPDATE test_attempts\n            SET supervisor_notes = :notes, updated_at = UTC_TIMESTAMP()\n            WHERE id = :attempt_id\n        ");
        $stmt->bindValue(':notes', $notes, $notes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':attempt_id', $attemptId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    public function isLatestAttemptForAssignment(int $attemptId, int $assignmentId): bool
    {
        $stmt = $this->pdo->prepare("\n            SELECT id\n            FROM test_attempts\n            WHERE assignment_id = :assignment_id\n            ORDER BY attempt_number DESC, id DESC\n            LIMIT 1\n            FOR UPDATE\n        ");
        $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() === $attemptId;
    }

    public function hasActiveAttemptForEmployee(int $employeeId): bool
    {
        $stmt = $this->pdo->prepare("\n            SELECT id\n            FROM test_attempts\n            WHERE employee_id = :employee_id\n              AND status = 'IN_PROGRESS'\n            LIMIT 1\n            FOR UPDATE\n        ");
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function hasPendingRetakeAuthorization(int $assignmentId): bool
    {
        $stmt = $this->pdo->prepare("\n            SELECT id\n            FROM retake_authorizations\n            WHERE assignment_id = :assignment_id\n              AND consumed_at IS NULL\n            LIMIT 1\n            FOR UPDATE\n        ");
        $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function createRetakeAuthorization(
        int $assignmentId,
        int $previousAttemptId,
        int $adminId,
        string $reason
    ): int {
        $stmt = $this->pdo->prepare("\n            INSERT INTO retake_authorizations\n                (assignment_id, previous_attempt_id, authorized_by, reason, authorized_at)\n            VALUES\n                (:assignment_id, :previous_attempt_id, :admin_id, :reason, UTC_TIMESTAMP())\n        ");
        $stmt->execute([
            ':assignment_id' => $assignmentId,
            ':previous_attempt_id' => $previousAttemptId,
            ':admin_id' => $adminId,
            ':reason' => $reason,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function recomputeEmployeeProgress(int $employeeId, ?int $currentTestId = null): void
    {
        $stmt = $this->pdo->prepare("\n            SELECT COUNT(*)\n            FROM test_assignments ta\n            INNER JOIN tests t ON t.id = ta.test_id\n            WHERE ta.employee_id = :employee_id\n              AND ta.status IN ('PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED')\n              AND t.is_active = 1\n        ");
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        $remaining = (int)$stmt->fetchColumn();

        $status = $remaining === 0 ? 'COMPLETED' : 'IN_PROGRESS';
        $completedAt = $remaining === 0 ? gmdate('Y-m-d H:i:s') : null;

        $upsert = $this->pdo->prepare("\n            INSERT INTO assessment_progress\n                (employee_id, overall_status, current_test_id, completed_at, updated_at)\n            VALUES\n                (:employee_id, :status, :current_test_id, :completed_at, UTC_TIMESTAMP())\n            ON DUPLICATE KEY UPDATE\n                overall_status = VALUES(overall_status),\n                current_test_id = VALUES(current_test_id),\n                completed_at = VALUES(completed_at),\n                updated_at = UTC_TIMESTAMP()\n        ");
        $upsert->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $upsert->bindValue(':status', $status, PDO::PARAM_STR);
        $upsert->bindValue(':current_test_id', $status === 'COMPLETED' ? null : $currentTestId, $status === 'COMPLETED' || $currentTestId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $upsert->bindValue(':completed_at', $completedAt, $completedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $upsert->execute();
    }

    public function logAdminAudit(
        int $adminId,
        string $action,
        int $attemptId,
        ?array $oldValues,
        ?array $newValues
    ): void {
        $stmt = $this->pdo->prepare("\n            INSERT INTO audit_logs\n                (actor_type, admin_id, employee_id, action, entity_type, entity_id,\n                 old_values, new_values, ip_address, user_agent, created_at)\n            VALUES\n                ('ADMIN', :admin_id, NULL, :action, 'TEST_ATTEMPT', :entity_id,\n                 :old_values, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP())\n        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':entity_id' => $attemptId,
            ':old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
        ]);
    }

    private function buildWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(e.full_name LIKE :q_employee_name OR e.employee_number LIKE :q_employee_number OR t.code LIKE :q_test_code OR t.name_ar LIKE :q_test_ar OR t.name_en LIKE :q_test_en)";
            $like = '%' . $q . '%';
            foreach ([':q_employee_name', ':q_employee_number', ':q_test_code', ':q_test_ar', ':q_test_en'] as $placeholder) {
                $params[$placeholder] = ['value' => $like, 'type' => PDO::PARAM_STR];
            }
        }

        $status = strtoupper((string)($filters['status'] ?? 'ALL'));
        if (in_array($status, ['IN_PROGRESS', 'COMPLETED', 'TIMED_OUT', 'CANCELLED'], true)) {
            $where[] = 'att.status = :status';
            $params[':status'] = ['value' => $status, 'type' => PDO::PARAM_STR];
        }

        $outcome = strtolower((string)($filters['outcome'] ?? 'all'));
        if ($outcome === 'passed') {
            $where[] = "att.status IN ('COMPLETED', 'TIMED_OUT') AND att.passed = 1";
        } elseif ($outcome === 'failed') {
            $where[] = "att.status IN ('COMPLETED', 'TIMED_OUT') AND att.passed = 0";
        } elseif ($outcome === 'ungraded') {
            $where[] = "att.status IN ('IN_PROGRESS', 'CANCELLED')";
        }

        foreach (['employee_id' => 'att.employee_id', 'test_id' => 'att.test_id', 'assignment_id' => 'att.assignment_id'] as $key => $column) {
            $value = (int)($filters[$key] ?? 0);
            if ($value > 0) {
                $where[] = "{$column} = :{$key}";
                $params[":{$key}"] = ['value' => $value, 'type' => PDO::PARAM_INT];
            }
        }

        if (!empty($filters['started_from'])) {
            $where[] = 'att.started_at >= :started_from';
            $params[':started_from'] = ['value' => (string)$filters['started_from'], 'type' => PDO::PARAM_STR];
        }
        if (!empty($filters['started_until'])) {
            $where[] = 'att.started_at <= :started_until';
            $params[':started_until'] = ['value' => (string)$filters['started_until'], 'type' => PDO::PARAM_STR];
        }

        $attention = strtolower((string)($filters['attention'] ?? 'all'));
        if ($attention === 'expired') {
            $where[] = "att.status = 'IN_PROGRESS' AND att.expires_at <= UTC_TIMESTAMP()";
        } elseif ($attention === 'security') {
            $where[] = 'EXISTS (SELECT 1 FROM security_events se_filter WHERE se_filter.attempt_id = att.id)';
        } elseif ($attention === 'focus') {
            $where[] = 'att.focus_loss_count >= 3';
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $data) {
            $stmt->bindValue($name, $data['value'], $data['type']);
        }
    }
}
