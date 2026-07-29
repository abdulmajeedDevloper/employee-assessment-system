<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AdminAssignmentRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Search assignments with safe, prepared filters and bounded pagination.
     *
     * @return array{items:array,total:int,page:int,per_page:int,total_pages:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        [$whereSql, $params] = $this->buildWhere($filters);

        $countSql = "
            SELECT COUNT(*)
            FROM test_assignments ta
            INNER JOIN employees e ON e.id = ta.employee_id
            INNER JOIN tests t ON t.id = ta.test_id
            {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $this->bindParams($countStmt, $params);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $dataSql = "
            SELECT
                ta.id,
                ta.employee_id,
                ta.test_id,
                ta.max_attempts,
                ta.status,
                ta.assigned_by,
                ta.assigned_at,
                ta.available_from,
                ta.available_until,
                e.full_name AS employee_name,
                e.employee_number,
                e.is_active AS employee_is_active,
                e.archived_at AS employee_archived_at,
                d.name_ar AS department_name_ar,
                d.name_en AS department_name_en,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en,
                t.is_active AS test_is_active,
                a.name AS assigned_by_name,
                COALESCE(ast.attempt_count, 0) AS attempt_count,
                ast.latest_attempt_status,
                ast.latest_attempt_percentage,
                CASE
                    WHEN (ta.available_from IS NOT NULL AND ta.available_from > UTC_TIMESTAMP())
                      OR (t.available_from IS NOT NULL AND t.available_from > UTC_TIMESTAMP())
                    THEN 'UPCOMING'
                    WHEN (ta.available_until IS NOT NULL AND ta.available_until < UTC_TIMESTAMP())
                      OR (t.available_until IS NOT NULL AND t.available_until < UTC_TIMESTAMP())
                    THEN 'EXPIRED'
                    ELSE 'OPEN'
                END AS availability_state
            FROM test_assignments ta
            INNER JOIN employees e ON e.id = ta.employee_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN tests t ON t.id = ta.test_id
            INNER JOIN admins a ON a.id = ta.assigned_by
            LEFT JOIN (
                SELECT
                    assignment_id,
                    COUNT(*) AS attempt_count,
                    SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY attempt_number DESC SEPARATOR ','), ',', 1) AS latest_attempt_status,
                    CAST(SUBSTRING_INDEX(GROUP_CONCAT(percentage ORDER BY attempt_number DESC SEPARATOR ','), ',', 1) AS DECIMAL(5,2)) AS latest_attempt_percentage
                FROM test_attempts
                GROUP BY assignment_id
            ) ast ON ast.assignment_id = ta.id
            {$whereSql}
            ORDER BY ta.assigned_at DESC, ta.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($dataSql);
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

    public function findSafeById(int $assignmentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ta.id,
                ta.employee_id,
                ta.test_id,
                ta.max_attempts,
                ta.status,
                ta.assigned_by,
                ta.assigned_at,
                ta.available_from,
                ta.available_until,
                e.full_name AS employee_name,
                e.employee_number,
                e.email AS employee_email,
                e.is_active AS employee_is_active,
                e.archived_at AS employee_archived_at,
                d.name_ar AS department_name_ar,
                d.name_en AS department_name_en,
                s.name_ar AS section_name_ar,
                s.name_en AS section_name_en,
                jt.name_ar AS job_title_name_ar,
                jt.name_en AS job_title_name_en,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en,
                t.duration_minutes,
                t.question_count,
                t.passing_percentage,
                t.is_active AS test_is_active,
                t.available_from AS test_available_from,
                t.available_until AS test_available_until,
                a.name AS assigned_by_name,
                COALESCE(ast.attempt_count, 0) AS attempt_count,
                COALESCE(ast.completed_attempt_count, 0) AS completed_attempt_count,
                ast.latest_attempt_id,
                ast.latest_attempt_status,
                ast.latest_attempt_percentage,
                ast.latest_attempt_submitted_at,
                CASE
                    WHEN (ta.available_from IS NOT NULL AND ta.available_from > UTC_TIMESTAMP())
                      OR (t.available_from IS NOT NULL AND t.available_from > UTC_TIMESTAMP())
                    THEN 'UPCOMING'
                    WHEN (ta.available_until IS NOT NULL AND ta.available_until < UTC_TIMESTAMP())
                      OR (t.available_until IS NOT NULL AND t.available_until < UTC_TIMESTAMP())
                    THEN 'EXPIRED'
                    ELSE 'OPEN'
                END AS availability_state
            FROM test_assignments ta
            INNER JOIN employees e ON e.id = ta.employee_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN sections s ON s.id = e.section_id
            INNER JOIN job_titles jt ON jt.id = e.job_title_id
            INNER JOIN tests t ON t.id = ta.test_id
            INNER JOIN admins a ON a.id = ta.assigned_by
            LEFT JOIN (
                SELECT
                    x.assignment_id,
                    COUNT(*) AS attempt_count,
                    SUM(CASE WHEN x.status IN ('COMPLETED', 'TIMED_OUT') THEN 1 ELSE 0 END) AS completed_attempt_count,
                    MAX(CASE WHEN x.rn = 1 THEN x.id END) AS latest_attempt_id,
                    MAX(CASE WHEN x.rn = 1 THEN x.status END) AS latest_attempt_status,
                    MAX(CASE WHEN x.rn = 1 THEN x.percentage END) AS latest_attempt_percentage,
                    MAX(CASE WHEN x.rn = 1 THEN x.submitted_at END) AS latest_attempt_submitted_at
                FROM (
                    SELECT ta2.*, ROW_NUMBER() OVER (PARTITION BY assignment_id ORDER BY attempt_number DESC, id DESC) AS rn
                    FROM test_attempts ta2
                ) x
                GROUP BY x.assignment_id
            ) ast ON ast.assignment_id = ta.id
            WHERE ta.id = :assignment_id
            LIMIT 1
        ");
        $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findForUpdate(int $assignmentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, employee_id, test_id, max_attempts, status, assigned_by,
                   assigned_at, available_from, available_until
            FROM test_assignments
            WHERE id = :assignment_id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function getActiveEmployees(): array
    {
        $stmt = $this->pdo->query("
            SELECT e.id, e.full_name, e.employee_number,
                   d.name_ar AS department_name_ar, d.name_en AS department_name_en
            FROM employees e
            INNER JOIN departments d ON d.id = e.department_id
            WHERE e.is_active = 1 AND e.archived_at IS NULL
            ORDER BY e.full_name ASC, e.id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveTests(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, code, name_ar, name_en, available_from, available_until
            FROM tests
            WHERE is_active = 1
            ORDER BY display_order ASC, id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllTestsForFilter(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, code, name_ar, name_en, is_active
            FROM tests
            ORDER BY display_order ASC, id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function lockAssignableEmployee(int $employeeId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM employees
            WHERE id = :employee_id
              AND is_active = 1
              AND archived_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    public function lockAssignableTest(int $testId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM tests
            WHERE id = :test_id
              AND is_active = 1
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    public function assignmentExists(int $employeeId, int $testId, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM test_assignments WHERE employee_id = :employee_id AND test_id = :test_id";
        if ($excludeId !== null) {
            $sql .= " AND id <> :exclude_id";
        }
        $sql .= " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    public function create(array $data, int $adminId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO test_assignments (
                employee_id, test_id, max_attempts, status, assigned_by,
                assigned_at, available_from, available_until
            ) VALUES (
                :employee_id, :test_id, :max_attempts, 'PENDING', :assigned_by,
                UTC_TIMESTAMP(), :available_from, :available_until
            )
        ");
        $stmt->bindValue(':employee_id', $data['employee_id'], PDO::PARAM_INT);
        $stmt->bindValue(':test_id', $data['test_id'], PDO::PARAM_INT);
        $stmt->bindValue(':max_attempts', $data['max_attempts'], PDO::PARAM_INT);
        $stmt->bindValue(':assigned_by', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':available_from', $data['available_from']);
        $stmt->bindValue(':available_until', $data['available_until']);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function updatePending(int $assignmentId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE test_assignments
            SET max_attempts = :max_attempts,
                available_from = :available_from,
                available_until = :available_until
            WHERE id = :assignment_id
              AND status = 'PENDING'
        ");
        $stmt->bindValue(':max_attempts', $data['max_attempts'], PDO::PARAM_INT);
        $stmt->bindValue(':available_from', $data['available_from']);
        $stmt->bindValue(':available_until', $data['available_until']);
        $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    public function countAttempts(int $assignmentId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM test_attempts WHERE assignment_id = :assignment_id");
        $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = "(
                e.full_name LIKE :q
                OR COALESCE(e.employee_number, '') LIKE :q
                OR t.code LIKE :q
                OR t.name_ar LIKE :q
                OR t.name_en LIKE :q
            )";
            $params[':q'] = ['value' => '%' . $q . '%', 'type' => PDO::PARAM_STR];
        }

        $status = (string)($filters['status'] ?? 'all');
        if (in_array($status, ['PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED', 'COMPLETED'], true)) {
            $conditions[] = 'ta.status = :status';
            $params[':status'] = ['value' => $status, 'type' => PDO::PARAM_STR];
        }

        $availability = (string)($filters['availability'] ?? 'all');
        if ($availability === 'open') {
            $conditions[] = '(ta.available_from IS NULL OR ta.available_from <= UTC_TIMESTAMP())';
            $conditions[] = '(ta.available_until IS NULL OR ta.available_until >= UTC_TIMESTAMP())';
        } elseif ($availability === 'upcoming') {
            $conditions[] = 'ta.available_from IS NOT NULL AND ta.available_from > UTC_TIMESTAMP()';
        } elseif ($availability === 'expired') {
            $conditions[] = 'ta.available_until IS NOT NULL AND ta.available_until < UTC_TIMESTAMP()';
        }

        $employeeId = (int)($filters['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $conditions[] = 'ta.employee_id = :employee_id';
            $params[':employee_id'] = ['value' => $employeeId, 'type' => PDO::PARAM_INT];
        }

        $testId = (int)($filters['test_id'] ?? 0);
        if ($testId > 0) {
            $conditions[] = 'ta.test_id = :test_id';
            $params[':test_id'] = ['value' => $testId, 'type' => PDO::PARAM_INT];
        }

        return [
            $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions),
            $params,
        ];
    }

    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $definition) {
            $stmt->bindValue($name, $definition['value'], $definition['type']);
        }
    }
}
