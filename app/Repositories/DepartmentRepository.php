<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class DepartmentRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    public function getActiveDepartments(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, name_ar, name_en
            FROM departments
            WHERE is_active = 1
            ORDER BY id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveSectionsByDepartment(int $deptId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, department_id, name_ar, name_en
            FROM sections
            WHERE department_id = :dept_id AND is_active = 1
            ORDER BY id ASC
        ");
        $stmt->execute([':dept_id' => $deptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSectionById(int $sectionId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, department_id, name_ar, name_en, is_active
            FROM sections
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $sectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        return $section ?: null;
    }
}
