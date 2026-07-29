<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AdminRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Find an administrator by normalized identifier (username or email).
     * Includes password_hash for authentication purposes.
     *
     * @param string $normalizedIdentifier
     * @return array|null
     */
    public function findForAuthenticationByIdentifier(string $normalizedIdentifier): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                name,
                email,
                username,
                password_hash,
                role,
                is_active,
                last_login_at,
                created_at,
                updated_at
            FROM admins
            WHERE username = :username_identifier
               OR email = :email_identifier
            LIMIT 1
        ");
        $stmt->bindValue(':username_identifier', $normalizedIdentifier, PDO::PARAM_STR);
        $stmt->bindValue(':email_identifier', $normalizedIdentifier, PDO::PARAM_STR);
        $stmt->execute();

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ?: null;
    }

    /**
     * Find an administrator by ID.
     * Excludes password_hash.
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
                email,
                username,
                role,
                is_active,
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
     * Update the last_login_at timestamp for an administrator using UTC_TIMESTAMP().
     *
     * @param int $id
     * @return bool
     */
    public function updateLastLogin(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE admins
            SET last_login_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Update password hash for an administrator using UTC_TIMESTAMP() for updated_at.
     *
     * @param int $id
     * @param string $passwordHash
     * @return bool
     */
    public function updatePasswordHash(int $id, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE admins
            SET password_hash = :password_hash,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
