<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AdminDashboardRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    public function getEmployeeMetrics(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total_employees,
                COALESCE(SUM(archived_at IS NULL AND is_active = 1), 0) AS active_employees,
                COALESCE(SUM(archived_at IS NULL AND is_active = 0), 0) AS inactive_employees,
                COALESCE(SUM(archived_at IS NOT NULL), 0) AS archived_employees
            FROM employees
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProgressMetrics(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS tracked_employees,
                COALESCE(SUM(COALESCE(ap.overall_status, 'NOT_STARTED') = 'NOT_STARTED'), 0) AS not_started_employees,
                COALESCE(SUM(ap.overall_status = 'IN_PROGRESS'), 0) AS in_progress_employees,
                COALESCE(SUM(ap.overall_status = 'COMPLETED'), 0) AS completed_employees
            FROM employees e
            LEFT JOIN assessment_progress ap ON ap.employee_id = e.id
            WHERE e.archived_at IS NULL
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAssignmentMetrics(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total_assignments,
                COALESCE(SUM(status IN ('PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED')), 0) AS active_assignments,
                COALESCE(SUM(status = 'COMPLETED'), 0) AS completed_assignments,
                COALESCE(SUM(
                    status IN ('PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED')
                    AND available_until IS NOT NULL
                    AND available_until < UTC_TIMESTAMP()
                ), 0) AS overdue_assignments
            FROM test_assignments
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAttemptMetrics(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total_attempts,
                COALESCE(SUM(status = 'IN_PROGRESS' AND expires_at > UTC_TIMESTAMP()), 0) AS active_attempts,
                COALESCE(SUM(status = 'IN_PROGRESS' AND expires_at <= UTC_TIMESTAMP()), 0) AS expired_in_progress_attempts,
                COALESCE(SUM(status = 'COMPLETED'), 0) AS completed_attempts,
                COALESCE(SUM(status = 'TIMED_OUT'), 0) AS timed_out_attempts,
                COALESCE(SUM(status = 'CANCELLED'), 0) AS cancelled_attempts,
                COALESCE(SUM(status IN ('COMPLETED', 'TIMED_OUT', 'CANCELLED')), 0) AS finalized_attempts
            FROM test_attempts
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOfficialResultMetrics(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS official_results,
                COALESCE(SUM(passed = 1), 0) AS passed_results,
                COALESCE(SUM(passed = 0), 0) AS failed_results,
                COALESCE(AVG(percentage), 0) AS average_percentage,
                COALESCE(MAX(percentage), 0) AS highest_percentage,
                COALESCE(MIN(percentage), 0) AS lowest_percentage
            FROM vw_official_assessment_results
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentSecurityEventCount(int $days = 7): int
    {
        $days = max(1, min(90, $days));
        $stmt = $this->pdo->query(sprintf(
            "SELECT COUNT(*) FROM security_events WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
            $days
        ));

        return (int)$stmt->fetchColumn();
    }

    public function getTestPerformance(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                t.id,
                t.code,
                t.name_ar,
                t.name_en,
                COUNT(r.attempt_id) AS result_count,
                COALESCE(SUM(r.passed = 1), 0) AS passed_count,
                COALESCE(AVG(r.percentage), 0) AS average_percentage,
                CASE
                    WHEN COUNT(r.attempt_id) = 0 THEN 0
                    ELSE ROUND((SUM(r.passed = 1) * 100.0) / COUNT(r.attempt_id), 2)
                END AS pass_rate
            FROM tests t
            LEFT JOIN vw_official_assessment_results r ON r.test_id = t.id
            GROUP BY t.id, t.code, t.name_ar, t.name_en, t.display_order
            ORDER BY t.display_order ASC, t.id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartmentPerformance(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.name_ar,
                d.name_en,
                COUNT(DISTINCT r.employee_id) AS assessed_employees,
                COUNT(r.attempt_id) AS result_count,
                COALESCE(AVG(r.percentage), 0) AS average_percentage,
                CASE
                    WHEN COUNT(r.attempt_id) = 0 THEN 0
                    ELSE ROUND((SUM(r.passed = 1) * 100.0) / COUNT(r.attempt_id), 2)
                END AS pass_rate
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.archived_at IS NULL
            LEFT JOIN vw_official_assessment_results r ON r.employee_id = e.id
            GROUP BY d.id, d.name_ar, d.name_en
            HAVING result_count > 0
            ORDER BY average_percentage DESC, result_count DESC, d.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentResults(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->pdo->prepare("
            SELECT
                r.attempt_id,
                r.employee_id,
                r.test_id,
                r.percentage,
                r.passed,
                r.attempt_status,
                r.submitted_at,
                e.full_name AS employee_name,
                e.employee_number,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en
            FROM vw_official_assessment_results r
            INNER JOIN employees e ON e.id = r.employee_id
            INNER JOIN tests t ON t.id = r.test_id
            ORDER BY r.submitted_at DESC, r.attempt_id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExpiredAttempts(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->pdo->prepare("
            SELECT
                att.id AS attempt_id,
                att.expires_at,
                e.full_name AS employee_name,
                e.employee_number,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en
            FROM test_attempts att
            INNER JOIN employees e ON e.id = att.employee_id
            INNER JOIN tests t ON t.id = att.test_id
            WHERE att.status = 'IN_PROGRESS'
              AND att.expires_at <= UTC_TIMESTAMP()
            ORDER BY att.expires_at ASC, att.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOverdueAssignments(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->pdo->prepare("
            SELECT
                ta.id AS assignment_id,
                ta.available_until,
                ta.status,
                e.full_name AS employee_name,
                e.employee_number,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en
            FROM test_assignments ta
            INNER JOIN employees e ON e.id = ta.employee_id
            INNER JOIN tests t ON t.id = ta.test_id
            WHERE ta.status IN ('PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED')
              AND ta.available_until IS NOT NULL
              AND ta.available_until < UTC_TIMESTAMP()
            ORDER BY ta.available_until ASC, ta.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
