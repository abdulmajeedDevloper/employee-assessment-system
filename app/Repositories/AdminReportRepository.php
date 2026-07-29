<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;
use PDOStatement;

class AdminReportRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * @return array{items:array,total:int,page:int,per_page:int,total_pages:int}
     */
    public function searchOfficialResults(array $filters, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        [$whereSql, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM vw_official_assessment_results r
            INNER JOIN employees e ON e.id = r.employee_id
            INNER JOIN tests t ON t.id = r.test_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN sections s ON s.id = e.section_id
            INNER JOIN job_titles jt ON jt.id = e.job_title_id
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
                r.assignment_id,
                r.attempt_id,
                r.employee_id,
                r.test_id,
                r.attempt_number,
                r.score,
                r.total_score,
                r.percentage,
                r.passed,
                r.attempt_status,
                r.submitted_at,
                e.full_name AS employee_name,
                e.employee_number,
                e.email,
                CONCAT('********', e.national_id_last4) AS masked_national_id,
                d.name_ar AS department_name_ar,
                d.name_en AS department_name_en,
                s.name_ar AS section_name_ar,
                s.name_en AS section_name_en,
                jt.name_ar AS job_title_name_ar,
                jt.name_en AS job_title_name_en,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en,
                t.passing_percentage
            FROM vw_official_assessment_results r
            INNER JOIN employees e ON e.id = r.employee_id
            INNER JOIN tests t ON t.id = r.test_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN sections s ON s.id = e.section_id
            INNER JOIN job_titles jt ON jt.id = e.job_title_id
            {$whereSql}
            ORDER BY r.submitted_at DESC, r.attempt_id DESC
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


    /**
     * Return all official results for a protected export. The caller must enforce
     * a reasonable maximum row count before invoking this method.
     */
    public function getOfficialResultsForExport(array $filters, int $limit): array
    {
        $limit = max(1, min(20000, $limit));
        [$whereSql, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare("
            SELECT
                r.assignment_id,
                r.attempt_id,
                r.employee_id,
                r.test_id,
                r.attempt_number,
                r.score,
                r.total_score,
                r.percentage,
                r.passed,
                r.attempt_status,
                r.submitted_at,
                e.full_name AS employee_name,
                e.employee_number,
                CONCAT('********', e.national_id_last4) AS masked_national_id,
                d.name_ar AS department_name_ar,
                d.name_en AS department_name_en,
                s.name_ar AS section_name_ar,
                s.name_en AS section_name_en,
                jt.name_ar AS job_title_name_ar,
                jt.name_en AS job_title_name_en,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en,
                t.passing_percentage
            FROM vw_official_assessment_results r
            INNER JOIN employees e ON e.id = r.employee_id
            INNER JOIN tests t ON t.id = r.test_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN sections s ON s.id = e.section_id
            INNER JOIN job_titles jt ON jt.id = e.job_title_id
            {$whereSql}
            ORDER BY r.submitted_at DESC, r.attempt_id DESC
            LIMIT :export_limit
        ");
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':export_limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total_results,
                COUNT(DISTINCT r.employee_id) AS assessed_employees,
                COALESCE(SUM(r.passed = 1), 0) AS passed_results,
                COALESCE(SUM(r.passed = 0), 0) AS failed_results,
                COALESCE(AVG(r.percentage), 0) AS average_percentage,
                COALESCE(MAX(r.percentage), 0) AS highest_percentage,
                COALESCE(MIN(r.percentage), 0) AS lowest_percentage
            FROM vw_official_assessment_results r
            INNER JOIN employees e ON e.id = r.employee_id
            INNER JOIN tests t ON t.id = r.test_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN sections s ON s.id = e.section_id
            INNER JOIN job_titles jt ON jt.id = e.job_title_id
            {$whereSql}
        ");
        $this->bindParams($stmt, $params);
        $stmt->execute();
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int)($summary['total_results'] ?? 0);
        $passed = (int)($summary['passed_results'] ?? 0);
        $summary['pass_rate'] = $total > 0 ? round(($passed * 100) / $total, 2) : 0.0;

        return $summary;
    }

    public function getTestBreakdown(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("
            SELECT
                t.id,
                t.code,
                t.name_ar,
                t.name_en,
                COUNT(*) AS result_count,
                COALESCE(SUM(r.passed = 1), 0) AS passed_count,
                COALESCE(SUM(r.passed = 0), 0) AS failed_count,
                COALESCE(AVG(r.percentage), 0) AS average_percentage,
                ROUND((SUM(r.passed = 1) * 100.0) / COUNT(*), 2) AS pass_rate
            FROM vw_official_assessment_results r
            INNER JOIN employees e ON e.id = r.employee_id
            INNER JOIN tests t ON t.id = r.test_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN sections s ON s.id = e.section_id
            INNER JOIN job_titles jt ON jt.id = e.job_title_id
            {$whereSql}
            GROUP BY t.id, t.code, t.name_ar, t.name_en, t.display_order
            ORDER BY t.display_order ASC, t.id ASC
        ");
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartmentBreakdown(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.name_ar,
                d.name_en,
                COUNT(DISTINCT r.employee_id) AS assessed_employees,
                COUNT(*) AS result_count,
                COALESCE(AVG(r.percentage), 0) AS average_percentage,
                ROUND((SUM(r.passed = 1) * 100.0) / COUNT(*), 2) AS pass_rate
            FROM vw_official_assessment_results r
            INNER JOIN employees e ON e.id = r.employee_id
            INNER JOIN tests t ON t.id = r.test_id
            INNER JOIN departments d ON d.id = e.department_id
            INNER JOIN sections s ON s.id = e.section_id
            INNER JOIN job_titles jt ON jt.id = e.job_title_id
            {$whereSql}
            GROUP BY d.id, d.name_ar, d.name_en
            ORDER BY average_percentage DESC, result_count DESC, d.id ASC
        ");
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartmentsForFilter(): array
    {
        return $this->pdo->query("
            SELECT id, name_ar, name_en
            FROM departments
            WHERE is_active = 1
            ORDER BY name_ar ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSectionsForFilter(): array
    {
        return $this->pdo->query("
            SELECT id, department_id, name_ar, name_en
            FROM sections
            WHERE is_active = 1
            ORDER BY department_id ASC, name_ar ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTestsForFilter(): array
    {
        return $this->pdo->query("
            SELECT id, code, name_ar, name_en
            FROM tests
            ORDER BY display_order ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{0:string,1:array<string,array{value:mixed,type:int}>}
     */
    private function buildWhere(array $filters): array
    {
        $clauses = ['e.archived_at IS NULL'];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $clauses[] = "(
                e.full_name LIKE :q
                OR e.employee_number LIKE :q
                OR e.email LIKE :q
                OR t.code LIKE :q
                OR t.name_ar LIKE :q
                OR t.name_en LIKE :q
            )";
            $params[':q'] = ['value' => '%' . $filters['q'] . '%', 'type' => PDO::PARAM_STR];
        }

        foreach (['department_id' => 'e.department_id', 'section_id' => 'e.section_id', 'test_id' => 'r.test_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $parameter = ':' . $key;
                $clauses[] = "{$column} = {$parameter}";
                $params[$parameter] = ['value' => (int)$filters[$key], 'type' => PDO::PARAM_INT];
            }
        }

        if (($filters['outcome'] ?? 'all') === 'passed') {
            $clauses[] = 'r.passed = 1';
        } elseif (($filters['outcome'] ?? 'all') === 'failed') {
            $clauses[] = 'r.passed = 0';
        }

        $attemptStatus = $filters['attempt_status'] ?? 'ALL';
        if (in_array($attemptStatus, ['COMPLETED', 'TIMED_OUT'], true)) {
            $clauses[] = 'r.attempt_status = :attempt_status';
            $params[':attempt_status'] = ['value' => $attemptStatus, 'type' => PDO::PARAM_STR];
        }

        if (!empty($filters['submitted_from'])) {
            $clauses[] = 'r.submitted_at >= :submitted_from';
            $params[':submitted_from'] = ['value' => $filters['submitted_from'], 'type' => PDO::PARAM_STR];
        }
        if (!empty($filters['submitted_until'])) {
            $clauses[] = 'r.submitted_at <= :submitted_until';
            $params[':submitted_until'] = ['value' => $filters['submitted_until'], 'type' => PDO::PARAM_STR];
        }

        return ['WHERE ' . implode("\n AND ", $clauses), $params];
    }

    /** @param array<string,array{value:mixed,type:int}> $params */
    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $definition) {
            $stmt->bindValue($name, $definition['value'], $definition['type']);
        }
    }
}
