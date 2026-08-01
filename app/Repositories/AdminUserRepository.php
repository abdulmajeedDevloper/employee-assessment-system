<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AdminUserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Get paginated list of admin accounts with search & filters.
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPaginatedAdmins(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $whereClause = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . strtolower(trim((string)$filters['search'])) . '%';
            $whereClause[] = "(LOWER(display_name) LIKE :s1 OR LOWER(name) LIKE :s2 OR LOWER(username) LIKE :s3 OR LOWER(email) LIKE :s4)";
            $params[':s1'] = $search;
            $params[':s2'] = $search;
            $params[':s3'] = $search;
            $params[':s4'] = $search;
        }

        if (!empty($filters['role']) && in_array($filters['role'], ['super_admin', 'admin', 'supervisor', 'report_viewer'], true)) {
            $whereClause[] = "role = :role";
            $params[':role'] = $filters['role'];
        }

        if (isset($filters['status']) && ($filters['status'] === '1' || $filters['status'] === '0' || is_int($filters['status']))) {
            $whereClause[] = "is_active = :status";
            $params[':status'] = (int)$filters['status'];
        }

        $whereSql = implode(' AND ', $whereClause);

        // Count Total Records
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins WHERE {$whereSql}");
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalItems = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($totalItems / $perPage));

        // Fetch Items
        $selectSql = "
            SELECT
                id,
                name,
                COALESCE(display_name, name) AS display_name,
                username,
                email,
                role,
                is_active,
                is_primary,
                auth_version,
                last_login_at,
                created_at,
                updated_at
            FROM admins
            WHERE {$whereSql}
            ORDER BY is_primary DESC, id ASC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($selectSql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $totalItems,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Find admin by ID. Excludes password_hash.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                name,
                COALESCE(display_name, name) AS display_name,
                username,
                email,
                role,
                is_active,
                is_primary,
                auth_version,
                last_login_at,
                created_at,
                updated_at
            FROM admins
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ?: null;
    }

    /**
     * Lock target admin row for update within an active transaction.
     *
     * @param int $id
     * @return array|null
     */
    public function findByIdForUpdate(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                name,
                COALESCE(display_name, name) AS display_name,
                username,
                email,
                role,
                is_active,
                is_primary,
                auth_version,
                last_login_at,
                created_at,
                updated_at
            FROM admins
            WHERE id = :id
            FOR UPDATE
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ?: null;
    }

    /**
     * Lock all active super_admin rows ordered by ID in an active transaction.
     * Used for concurrency-safe last active super admin protection.
     *
     * @return array Array of active super admin rows with locked state
     */
    public function lockActiveSuperAdmins(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, role, is_active, is_primary
            FROM admins
            WHERE role = 'super_admin' AND is_active = 1
            ORDER BY id ASC
            FOR UPDATE
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find admin by case-insensitive normalized username.
     *
     * @param string $username
     * @param int|null $excludeId
     * @return array|null
     */
    public function findByNormalizedUsername(string $username, ?int $excludeId = null): ?array
    {
        $sql = "SELECT id, username, email FROM admins WHERE LOWER(username) = LOWER(:username)";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':username', trim($username), PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Find admin by case-insensitive normalized email.
     *
     * @param string $email
     * @param int|null $excludeId
     * @return array|null
     */
    public function findByNormalizedEmail(string $email, ?int $excludeId = null): ?array
    {
        $sql = "SELECT id, username, email FROM admins WHERE LOWER(email) = LOWER(:email)";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':email', trim($email), PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Create a new secondary admin account.
     *
     * @param array $data
     * @return int Created admin ID
     */
    public function createAdmin(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO admins (
                name, display_name, username, email, password_hash, role, is_active, is_primary, auth_version, created_at, updated_at
            ) VALUES (
                :name, :display_name, :username, :email, :password_hash, :role, :is_active, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([
            ':name' => $data['display_name'],
            ':display_name' => $data['display_name'],
            ':username' => strtolower(trim($data['username'])),
            ':email' => strtolower(trim($data['email'])),
            ':password_hash' => $data['password_hash'],
            ':role' => $data['role'],
            ':is_active' => (int)$data['is_active'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update admin profile fields (display_name, username, email, role, is_active).
     * Automatically increments auth_version if role, username, email, or is_active changes.
     *
     * @param int $id
     * @param array $data
     * @param bool $incrementAuthVersion
     * @return bool
     */
    public function updateAdmin(int $id, array $data, bool $incrementAuthVersion = false): bool
    {
        $authVersionClause = $incrementAuthVersion ? "auth_version = auth_version + 1," : "";

        $stmt = $this->pdo->prepare("
            UPDATE admins
            SET name = :name,
                display_name = :display_name,
                username = :username,
                email = :email,
                role = :role,
                is_active = :is_active,
                {$authVersionClause}
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name' => $data['display_name'],
            ':display_name' => $data['display_name'],
            ':username' => strtolower(trim($data['username'])),
            ':email' => strtolower(trim($data['email'])),
            ':role' => $data['role'],
            ':is_active' => (int)$data['is_active'],
            ':id' => $id,
        ]);
    }

    /**
     * Update password hash and increment auth_version.
     *
     * @param int $id
     * @param string $passwordHash
     * @return bool
     */
    public function updatePasswordAndIncrementAuthVersion(int $id, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE admins
            SET password_hash = :password_hash,
                auth_version = auth_version + 1,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => $id,
        ]);
    }

    /**
     * Increment auth_version for a specified admin ID.
     *
     * @param int $id
     * @return bool
     */
    public function incrementAuthVersion(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE admins
            SET auth_version = auth_version + 1,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    /**
     * Update active status and increment auth_version.
     *
     * @param int $id
     * @param int $isActive
     * @return bool
     */
    public function updateStatus(int $id, int $isActive): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE admins
            SET is_active = :is_active,
                auth_version = auth_version + 1,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':is_active' => $isActive,
            ':id' => $id,
        ]);
    }
}
