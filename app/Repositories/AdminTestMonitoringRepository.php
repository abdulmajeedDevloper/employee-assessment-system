<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;
use PDOStatement;

class AdminTestMonitoringRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Return currently running assessment attempts for the live monitoring page.
     *
     * Supported filters:
     * - q
     * - test_id
     * - connection: ALL, ONLINE, STALE, DISCONNECTED, EXPIRED
     * - attention: ALL, FOCUS, SECURITY
     *
     * @return array{items:array,total:int,page:int,per_page:int,total_pages:int}
     */
    public function searchActiveAttempts(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));

        [$whereSql, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM test_attempts att
            INNER JOIN employees e ON e.id = att.employee_id
            INNER JOIN tests t ON t.id = att.test_id
            LEFT JOIN (
                SELECT ts.*
                FROM test_sessions ts
                INNER JOIN (
                    SELECT attempt_id, MAX(id) AS latest_session_id
                    FROM test_sessions
                    GROUP BY attempt_id
                ) latest ON latest.latest_session_id = ts.id
            ) sess ON sess.attempt_id = att.id
            LEFT JOIN (
                SELECT
                    attempt_id,
                    COUNT(*) AS security_event_count,
                    MAX(created_at) AS last_security_event_at
                FROM security_events
                WHERE attempt_id IS NOT NULL
                GROUP BY attempt_id
            ) sec ON sec.attempt_id = att.id
            {$whereSql}
        ");

        $this->bindParams($countStmt, $params);
        $countStmt->execute();

        $total = (int)$countStmt->fetchColumn();
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("
            SELECT
                att.id AS attempt_id,
                att.employee_id,
                att.test_id,
                att.assignment_id,
                att.attempt_number,
                att.started_at,
                att.expires_at,
                att.duration_seconds,
                att.focus_loss_count,
                att.ip_address AS attempt_ip_address,
                att.user_agent AS attempt_user_agent,
                att.updated_at AS attempt_updated_at,

                e.full_name AS employee_name,
                e.employee_number,

                d.name_ar AS department_name_ar,
                d.name_en AS department_name_en,

                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en,

                COALESCE(ans.answered_count, 0) AS answered_count,
                COALESCE(ans.question_count, 0) AS question_count,

                COALESCE(sec.security_event_count, 0) AS security_event_count,
                sec.last_security_event_at,

                sess.id AS session_id,
                sess.ip_address AS session_ip_address,
                sess.started_at AS session_started_at,
                sess.last_activity_at,
                sess.ended_at AS session_ended_at,
                sess.status AS session_status,

                GREATEST(TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), att.expires_at), 0) AS remaining_seconds,

                CASE
                    WHEN att.expires_at <= UTC_TIMESTAMP() THEN 'EXPIRED'
                    WHEN sess.id IS NULL THEN 'DISCONNECTED'
                    WHEN sess.status <> 'ACTIVE' THEN 'DISCONNECTED'
                    WHEN sess.last_activity_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE) THEN 'STALE'
                    ELSE 'ONLINE'
                END AS connection_status,

                CASE
                    WHEN att.expires_at <= UTC_TIMESTAMP() THEN 1
                    ELSE 0
                END AS is_expired,

                CASE
                    WHEN att.focus_loss_count >= 3
                      OR COALESCE(sec.security_event_count, 0) > 0
                      OR sess.id IS NULL
                      OR sess.status <> 'ACTIVE'
                      OR sess.last_activity_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)
                    THEN 1
                    ELSE 0
                END AS needs_attention

            FROM test_attempts att
            INNER JOIN employees e ON e.id = att.employee_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN tests t ON t.id = att.test_id

            LEFT JOIN (
                SELECT
                    aq.attempt_id,
                    COUNT(aq.id) AS question_count,
                    COUNT(aa.id) AS answered_count
                FROM attempt_questions aq
                LEFT JOIN attempt_answers aa ON aa.attempt_question_id = aq.id
                GROUP BY aq.attempt_id
            ) ans ON ans.attempt_id = att.id

            LEFT JOIN (
                SELECT
                    attempt_id,
                    COUNT(*) AS security_event_count,
                    MAX(created_at) AS last_security_event_at
                FROM security_events
                WHERE attempt_id IS NOT NULL
                GROUP BY attempt_id
            ) sec ON sec.attempt_id = att.id

            LEFT JOIN (
                SELECT ts.*
                FROM test_sessions ts
                INNER JOIN (
                    SELECT attempt_id, MAX(id) AS latest_session_id
                    FROM test_sessions
                    GROUP BY attempt_id
                ) latest ON latest.latest_session_id = ts.id
            ) sess ON sess.attempt_id = att.id

            {$whereSql}
            ORDER BY
                needs_attention DESC,
                is_expired DESC,
                att.started_at DESC,
                att.id DESC
            LIMIT :limit OFFSET :offset
        ");

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

    public function getStatistics(array $filters = []): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS active_attempts,

                COALESCE(SUM(
                    att.expires_at > UTC_TIMESTAMP()
                    AND sess.id IS NOT NULL
                    AND sess.status = 'ACTIVE'
                    AND sess.last_activity_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)
                ), 0) AS online_attempts,

                COALESCE(SUM(
                    att.expires_at > UTC_TIMESTAMP()
                    AND sess.id IS NOT NULL
                    AND sess.status = 'ACTIVE'
                    AND sess.last_activity_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)
                ), 0) AS stale_attempts,

                COALESCE(SUM(
                    att.expires_at > UTC_TIMESTAMP()
                    AND (sess.id IS NULL OR sess.status <> 'ACTIVE')
                ), 0) AS disconnected_attempts,

                COALESCE(SUM(att.expires_at <= UTC_TIMESTAMP()), 0) AS expired_attempts,

                COALESCE(SUM(att.focus_loss_count >= 3), 0) AS focus_alert_attempts,

                COALESCE(SUM(COALESCE(sec.security_event_count, 0) > 0), 0) AS security_alert_attempts

            FROM test_attempts att
            INNER JOIN employees e ON e.id = att.employee_id
            INNER JOIN tests t ON t.id = att.test_id

            LEFT JOIN (
                SELECT ts.*
                FROM test_sessions ts
                INNER JOIN (
                    SELECT attempt_id, MAX(id) AS latest_session_id
                    FROM test_sessions
                    GROUP BY attempt_id
                ) latest ON latest.latest_session_id = ts.id
            ) sess ON sess.attempt_id = att.id

            LEFT JOIN (
                SELECT
                    attempt_id,
                    COUNT(*) AS security_event_count,
                    MAX(created_at) AS last_security_event_at
                FROM security_events
                WHERE attempt_id IS NOT NULL
                GROUP BY attempt_id
            ) sec ON sec.attempt_id = att.id

            {$whereSql}
        ");

        $this->bindParams($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ([
            'active_attempts',
            'online_attempts',
            'stale_attempts',
            'disconnected_attempts',
            'expired_attempts',
            'focus_alert_attempts',
            'security_alert_attempts',
        ] as $key) {
            $row[$key] = (int)($row[$key] ?? 0);
        }

        return $row;
    }

    public function getTestsWithActiveAttempts(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT
                t.id,
                t.code,
                t.name_ar,
                t.name_en,
                t.display_order
            FROM tests t
            INNER JOIN test_attempts att ON att.test_id = t.id
            WHERE att.status = 'IN_PROGRESS'
            ORDER BY t.display_order ASC, t.id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{0:string,1:array<string,array{value:mixed,type:int}>}
     */
    private function buildWhere(array $filters): array
    {
        $where = ["att.status = 'IN_PROGRESS'"];
        $params = [];

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = "(
                e.full_name LIKE :q_employee_name
                OR e.employee_number LIKE :q_employee_number
                OR t.code LIKE :q_test_code
                OR t.name_ar LIKE :q_test_ar
                OR t.name_en LIKE :q_test_en
                OR att.ip_address LIKE :q_ip_address
            )";

            $like = '%' . $query . '%';

            foreach ([
                ':q_employee_name',
                ':q_employee_number',
                ':q_test_code',
                ':q_test_ar',
                ':q_test_en',
                ':q_ip_address',
            ] as $placeholder) {
                $params[$placeholder] = [
                    'value' => $like,
                    'type' => PDO::PARAM_STR,
                ];
            }
        }

        $testId = (int)($filters['test_id'] ?? 0);
        if ($testId > 0) {
            $where[] = 'att.test_id = :test_id';
            $params[':test_id'] = [
                'value' => $testId,
                'type' => PDO::PARAM_INT,
            ];
        }

        $connection = strtoupper(trim((string)($filters['connection'] ?? 'ALL')));

        if ($connection === 'ONLINE') {
            $where[] = "
                att.expires_at > UTC_TIMESTAMP()
                AND sess.id IS NOT NULL
                AND sess.status = 'ACTIVE'
                AND sess.last_activity_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)
            ";
        } elseif ($connection === 'STALE') {
            $where[] = "
                att.expires_at > UTC_TIMESTAMP()
                AND sess.id IS NOT NULL
                AND sess.status = 'ACTIVE'
                AND sess.last_activity_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)
            ";
        } elseif ($connection === 'DISCONNECTED') {
            $where[] = "
                att.expires_at > UTC_TIMESTAMP()
                AND (sess.id IS NULL OR sess.status <> 'ACTIVE')
            ";
        } elseif ($connection === 'EXPIRED') {
            $where[] = 'att.expires_at <= UTC_TIMESTAMP()';
        }

        $attention = strtoupper(trim((string)($filters['attention'] ?? 'ALL')));

        if ($attention === 'FOCUS') {
            $where[] = 'att.focus_loss_count >= 3';
        } elseif ($attention === 'SECURITY') {
            $where[] = 'COALESCE(sec.security_event_count, 0) > 0';
        }

        return [
            'WHERE ' . implode(' AND ', $where),
            $params,
        ];
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $data) {
            $stmt->bindValue($name, $data['value'], $data['type']);
        }
    }
}
