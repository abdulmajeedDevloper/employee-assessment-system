<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AssessmentProgressRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    public function getProgressForEmployee(int $employeeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM assessment_progress
            WHERE employee_id = :employee_id
            LIMIT 1
        ");
        $stmt->execute([':employee_id' => $employeeId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function getActiveAttemptForEmployee(int $employeeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ta.*, t.name_ar AS test_name_ar, t.name_en AS test_name_en
            FROM test_attempts ta
            JOIN tests t ON ta.test_id = t.id
            WHERE ta.employee_id = :employee_id AND ta.status = 'IN_PROGRESS'
            ORDER BY ta.id DESC
            LIMIT 1
        ");
        $stmt->execute([':employee_id' => $employeeId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}
