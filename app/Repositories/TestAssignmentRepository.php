<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class TestAssignmentRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    public function getAssignmentsForEmployee(int $employeeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ta.*, t.code AS test_code, t.name_ar AS test_name_ar, t.name_en AS test_name_en,
                   t.description_ar, t.description_en, t.instructions_ar, t.instructions_en,
                   t.duration_minutes, t.question_count, t.passing_percentage,
                   CASE
                       WHEN (ta.available_from IS NOT NULL AND ta.available_from > UTC_TIMESTAMP())
                         OR (t.available_from IS NOT NULL AND t.available_from > UTC_TIMESTAMP())
                       THEN 'UPCOMING'
                       WHEN (ta.available_until IS NOT NULL AND ta.available_until < UTC_TIMESTAMP())
                         OR (t.available_until IS NOT NULL AND t.available_until < UTC_TIMESTAMP())
                       THEN 'EXPIRED'
                       ELSE 'OPEN'
                   END AS availability_state,
                   CASE
                       WHEN (ta.available_from IS NULL OR ta.available_from <= UTC_TIMESTAMP())
                        AND (ta.available_until IS NULL OR ta.available_until >= UTC_TIMESTAMP())
                        AND (t.available_from IS NULL OR t.available_from <= UTC_TIMESTAMP())
                        AND (t.available_until IS NULL OR t.available_until >= UTC_TIMESTAMP())
                       THEN 1 ELSE 0
                   END AS is_available
            FROM test_assignments ta
            JOIN tests t ON ta.test_id = t.id
            WHERE ta.employee_id = :employee_id AND t.is_active = 1
            ORDER BY ta.id ASC
        ");
        $stmt->execute([':employee_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
