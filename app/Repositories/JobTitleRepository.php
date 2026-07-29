<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class JobTitleRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    public function getActiveJobTitles(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, name_ar, name_en
            FROM job_titles
            WHERE is_active = 1
            ORDER BY id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getJobTitleById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name_ar, name_en, is_active
            FROM job_titles
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}
