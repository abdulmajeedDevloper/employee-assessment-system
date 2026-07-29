<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class EmployeeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    public function findByNationalIdHash(string $hashBin): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, d.name_ar AS dept_name_ar, d.name_en AS dept_name_en,
                   s.name_ar AS sec_name_ar, s.name_en AS sec_name_en,
                   j.name_ar AS job_name_ar, j.name_en AS job_name_en
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN sections s ON e.section_id = s.id
            LEFT JOIN job_titles j ON e.job_title_id = j.id
            WHERE e.national_id_hash = :hash
            LIMIT 1
        ");
        $stmt->bindValue(':hash', $hashBin, PDO::PARAM_STR);
        $stmt->execute();

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        return $employee ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, d.name_ar AS dept_name_ar, d.name_en AS dept_name_en,
                   s.name_ar AS sec_name_ar, s.name_en AS sec_name_en,
                   j.name_ar AS job_name_ar, j.name_en AS job_name_en
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN sections s ON e.section_id = s.id
            LEFT JOIN job_titles j ON e.job_title_id = j.id
            WHERE e.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        return $employee ?: null;
    }

    public function updateProfile(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE employees SET
                full_name = :full_name,
                email = :email,
                phone = :phone,
                preferred_language = :preferred_language,
                profile_completed_at = COALESCE(profile_completed_at, UTC_TIMESTAMP()),
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':full_name' => $data['full_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':preferred_language' => $data['preferred_language'],
            ':id' => $id
        ]);
    }
}
