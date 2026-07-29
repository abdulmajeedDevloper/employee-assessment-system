<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AdminEmployeeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Search and paginate employees using safe explicit column lists.
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = 20; // Fixed per page as required

        $where = [];
        $params = [];

        // 1. Search Query q (matches full_name, employee_number, email)
        if (!empty($filters['q']) && is_string($filters['q'])) {
            $q = trim($filters['q']);
            if (mb_strlen($q) > 100) {
                $q = mb_substr($q, 0, 100);
            }
            if ($q !== '') {
                $where[] = '(e.full_name LIKE :q_name OR e.employee_number LIKE :q_employee_number OR e.email LIKE :q_email)';
                $params[':q_name'] = '%' . $q . '%';
                $params[':q_employee_number'] = '%' . $q . '%';
                $params[':q_email'] = '%' . $q . '%';
            }
        }

        // 2. Department Filter
        if (isset($filters['department_id']) && is_numeric($filters['department_id']) && (int)$filters['department_id'] > 0) {
            $where[] = 'e.department_id = :dept_id';
            $params[':dept_id'] = (int)$filters['department_id'];
        }

        // 3. Status Filter (Whitelisted)
        $status = $filters['status'] ?? 'all';
        if ($status === 'active') {
            $where[] = 'e.is_active = 1 AND e.archived_at IS NULL';
        } elseif ($status === 'inactive') {
            $where[] = 'e.is_active = 0 AND e.archived_at IS NULL';
        } elseif ($status === 'archived') {
            $where[] = 'e.archived_at IS NOT NULL';
        }
        // 'all' or invalid status values do not add a status condition

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count Query
        $countSql = "
            SELECT COUNT(*)
            FROM employees e
            {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $param => $val) {
            $countStmt->bindValue($param, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        if ($total === 0) {
            $page = 1;
            $totalPages = 0;
            $offset = 0;
        } else {
            $totalPages = (int)ceil($total / $perPage);
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
        }

        // Explicit safe column list ONLY (no national_id fields in search results)
        $itemsSql = "
            SELECT
                e.id,
                e.full_name,
                e.employee_number,
                e.email,
                e.phone,
                e.department_id,
                e.section_id,
                e.job_title_id,
                e.preferred_language,
                e.is_active,
                e.profile_completed_at,
                e.created_at,
                e.updated_at,
                e.archived_at,
                d.name_ar AS department_name_ar,
                d.name_en AS department_name_en,
                s.name_ar AS section_name_ar,
                s.name_en AS section_name_en,
                j.name_ar AS job_title_name_ar,
                j.name_en AS job_title_name_en
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN sections s ON e.section_id = s.id
            LEFT JOIN job_titles j ON e.job_title_id = j.id
            {$whereSql}
            ORDER BY e.full_name ASC, e.id ASC
            LIMIT :limit OFFSET :offset
        ";

        $itemsStmt = $this->pdo->prepare($itemsSql);
        foreach ($params as $param => $val) {
            $itemsStmt->bindValue($param, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $itemsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $itemsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Find an employee by ID using a safe explicit column list.
     *
     * @param int $id
     * @return array|null
     */
    public function findSafeById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = "
            SELECT
                e.id,
                e.full_name,
                e.employee_number,
                e.email,
                e.phone,
                e.department_id,
                e.section_id,
                e.job_title_id,
                e.preferred_language,
                e.is_active,
                e.profile_completed_at,
                e.created_at,
                e.updated_at,
                e.archived_at,
                e.national_id_last4,
                d.name_ar AS department_name_ar,
                d.name_en AS department_name_en,
                s.name_ar AS section_name_ar,
                s.name_en AS section_name_en,
                j.name_ar AS job_title_name_ar,
                j.name_en AS job_title_name_en
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN sections s ON e.section_id = s.id
            LEFT JOIN job_titles j ON e.job_title_id = j.id
            WHERE e.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $record = $stmt->fetch();

        return $record ?: null;
    }

    /**
     * Fetch active departments for filter selection.
     *
     * @return array
     */
    public function getDepartments(): array
    {
        $sql = "
            SELECT id, name_ar, name_en
            FROM departments
            WHERE is_active = 1
            ORDER BY name_ar ASC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Fetch active sections for selection.
     *
     * @return array
     */
    public function getSections(): array
    {
        $sql = "
            SELECT id, department_id, name_ar, name_en
            FROM sections
            WHERE is_active = 1
            ORDER BY name_ar ASC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Fetch active job titles for selection.
     *
     * @return array
     */
    public function getJobTitles(): array
    {
        $sql = "
            SELECT id, name_ar, name_en
            FROM job_titles
            WHERE is_active = 1
            ORDER BY name_ar ASC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Check if national_id_hash exists in employees.
     *
     * @param string $hashBin
     * @param int|null $excludeId
     * @return bool
     */
    public function existsNationalIdHash(string $hashBin, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM employees WHERE national_id_hash = :hash";
        $params = [':hash' => $hashBin];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Check if employee_number exists in employees.
     *
     * @param string $empNumber
     * @param int|null $excludeId
     * @return bool
     */
    public function existsEmployeeNumber(string $empNumber, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM employees WHERE employee_number = :emp_num";
        $params = [':emp_num' => $empNumber];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Create a new employee record.
     *
     * @param array $data
     * @return int Inserted ID
     */
    public function createEmployee(array $data): int
    {
        $sql = "
            INSERT INTO employees (
                full_name,
                national_id_hash,
                national_id_ciphertext,
                national_id_iv,
                national_id_tag,
                key_version,
                national_id_last4,
                employee_number,
                email,
                phone,
                department_id,
                section_id,
                job_title_id,
                preferred_language,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :full_name,
                :national_id_hash,
                :national_id_ciphertext,
                :national_id_iv,
                :national_id_tag,
                :key_version,
                :national_id_last4,
                :employee_number,
                :email,
                :phone,
                :department_id,
                :section_id,
                :job_title_id,
                :preferred_language,
                :is_active,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':full_name', $data['full_name'], PDO::PARAM_STR);
        $stmt->bindValue(':national_id_hash', $data['national_id_hash'], PDO::PARAM_STR);
        $stmt->bindValue(':national_id_ciphertext', $data['national_id_ciphertext'], PDO::PARAM_STR);
        $stmt->bindValue(':national_id_iv', $data['national_id_iv'], PDO::PARAM_STR);
        $stmt->bindValue(':national_id_tag', $data['national_id_tag'], PDO::PARAM_STR);
        $stmt->bindValue(':key_version', $data['key_version'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':national_id_last4', $data['national_id_last4'], PDO::PARAM_STR);
        $stmt->bindValue(':employee_number', $data['employee_number'], $data['employee_number'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':department_id', $data['department_id'], PDO::PARAM_INT);
        $stmt->bindValue(':section_id', $data['section_id'], PDO::PARAM_INT);
        $stmt->bindValue(':job_title_id', $data['job_title_id'], PDO::PARAM_INT);
        $stmt->bindValue(':preferred_language', $data['preferred_language'], PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->execute();

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update an existing employee record.
     *
     * @param int $id
     * @param array $data
     * @param bool $updateNationalId
     * @return bool
     */
    public function updateEmployee(int $id, array $data, bool $updateNationalId = false): bool
    {
        if ($updateNationalId) {
            $sql = "
                UPDATE employees SET
                    full_name = :full_name,
                    employee_number = :employee_number,
                    email = :email,
                    phone = :phone,
                    department_id = :department_id,
                    section_id = :section_id,
                    job_title_id = :job_title_id,
                    preferred_language = :preferred_language,
                    is_active = :is_active,
                    national_id_hash = :national_id_hash,
                    national_id_ciphertext = :national_id_ciphertext,
                    national_id_iv = :national_id_iv,
                    national_id_tag = :national_id_tag,
                    key_version = :key_version,
                    national_id_last4 = :national_id_last4,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = :id
            ";
        } else {
            $sql = "
                UPDATE employees SET
                    full_name = :full_name,
                    employee_number = :employee_number,
                    email = :email,
                    phone = :phone,
                    department_id = :department_id,
                    section_id = :section_id,
                    job_title_id = :job_title_id,
                    preferred_language = :preferred_language,
                    is_active = :is_active,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = :id
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':full_name', $data['full_name'], PDO::PARAM_STR);
        $stmt->bindValue(':employee_number', $data['employee_number'], $data['employee_number'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':department_id', $data['department_id'], PDO::PARAM_INT);
        $stmt->bindValue(':section_id', $data['section_id'], PDO::PARAM_INT);
        $stmt->bindValue(':job_title_id', $data['job_title_id'], PDO::PARAM_INT);
        $stmt->bindValue(':preferred_language', $data['preferred_language'], PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        if ($updateNationalId) {
            $stmt->bindValue(':national_id_hash', $data['national_id_hash'], PDO::PARAM_STR);
            $stmt->bindValue(':national_id_ciphertext', $data['national_id_ciphertext'], PDO::PARAM_STR);
            $stmt->bindValue(':national_id_iv', $data['national_id_iv'], PDO::PARAM_STR);
            $stmt->bindValue(':national_id_tag', $data['national_id_tag'], PDO::PARAM_STR);
            $stmt->bindValue(':key_version', $data['key_version'] ?? 1, PDO::PARAM_INT);
            $stmt->bindValue(':national_id_last4', $data['national_id_last4'], PDO::PARAM_STR);
        }

        return $stmt->execute();
    }

    /**
     * Lock employee row for archive/restore state evaluation.
     *
     * @param int $id
     * @return array|null Returns only safe state fields: id, is_active, archived_at
     */
    public function findEmployeeStateForUpdate(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = "
            SELECT id, is_active, archived_at
            FROM employees
            WHERE id = :id
            FOR UPDATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Soft archive employee by setting archived_at = UTC_TIMESTAMP() and is_active = 0.
     *
     * @param int $id
     * @return bool True if exactly 1 row updated
     */
    public function archiveEmployeeState(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $sql = "
            UPDATE employees
            SET
                is_active = 0,
                archived_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND archived_at IS NULL
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /**
     * Restore employee from archive by clearing archived_at and keeping is_active = 0.
     *
     * @param int $id
     * @return bool True if exactly 1 row updated
     */
    public function restoreEmployeeStateInactive(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $sql = "
            UPDATE employees
            SET
                is_active = 0,
                archived_at = NULL,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND archived_at IS NOT NULL
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }
}
