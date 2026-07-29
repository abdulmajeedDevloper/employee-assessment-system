<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AdminTestRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Search and paginate tests using safe explicit column lists.
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = 20;

        $where = [];
        $params = [];

        // 1. Search Query q (matches code, name_ar, name_en)
        if (!empty($filters['q']) && is_string($filters['q'])) {
            $q = trim($filters['q']);
            if (mb_strlen($q) > 100) {
                $q = mb_substr($q, 0, 100);
            }
            if ($q !== '') {
                $escapedQ = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
                $where[] = "(t.code LIKE :q_code ESCAPE '\\\\' OR t.name_ar LIKE :q_name_ar ESCAPE '\\\\' OR t.name_en LIKE :q_name_en ESCAPE '\\\\')";
                $params[':q_code'] = '%' . $escapedQ . '%';
                $params[':q_name_ar'] = '%' . $escapedQ . '%';
                $params[':q_name_en'] = '%' . $escapedQ . '%';
            }
        }

        // 2. Status Filter (Whitelisted)
        $status = $filters['status'] ?? 'all';
        if ($status === 'active') {
            $where[] = 't.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 't.is_active = 0';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count Query
        $countSql = "
            SELECT COUNT(*)
            FROM tests t
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

        // Explicit safe column list ONLY
        $itemsSql = "
            SELECT
                t.id,
                t.code,
                t.name_ar,
                t.name_en,
                t.duration_minutes,
                t.question_count,
                t.passing_percentage,
                t.is_active,
                t.available_from,
                t.available_until,
                t.display_order,
                t.created_at,
                t.updated_at
            FROM tests t
            {$whereSql}
            ORDER BY t.display_order ASC, t.id ASC
            LIMIT :limit OFFSET :offset
        ";

        $itemsStmt = $this->pdo->prepare($itemsSql);
        foreach ($params as $param => $val) {
            $itemsStmt->bindValue($param, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $itemsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $itemsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $itemsStmt->execute();

        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Find test safe details by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function findSafeById(int $id): ?array
    {
        $sql = "
            SELECT
                t.id,
                t.code,
                t.name_ar,
                t.name_en,
                t.description_ar,
                t.description_en,
                t.instructions_ar,
                t.instructions_en,
                t.duration_minutes,
                t.question_count,
                t.passing_percentage,
                t.display_order,
                t.randomize_questions,
                t.randomize_options,
                t.allow_back_navigation,
                t.show_result_to_employee,
                t.is_active,
                t.available_from,
                t.available_until,
                t.created_at,
                t.updated_at
            FROM tests t
            WHERE t.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Find test safe details by ID (alias for findSafeById).
     *
     * @param int $id
     * @return array|null
     */
    public function findEditableById(int $id): ?array
    {
        return $this->findSafeById($id);
    }

    /**
     * Find test row for mutation locking inside a transaction.
     *
     * @param int $id
     * @return array|null
     */
    public function findForUpdate(int $id): ?array
    {
        $sql = "
            SELECT
                t.id,
                t.code,
                t.name_ar,
                t.name_en,
                t.description_ar,
                t.description_en,
                t.instructions_ar,
                t.instructions_en,
                t.duration_minutes,
                t.question_count,
                t.passing_percentage,
                t.display_order,
                t.randomize_questions,
                t.randomize_options,
                t.allow_back_navigation,
                t.show_result_to_employee,
                t.is_active,
                t.available_from,
                t.available_until,
                t.created_at,
                t.updated_at
            FROM tests t
            WHERE t.id = :id
            FOR UPDATE
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Check if assessment code already exists (case-insensitive via collation).
     *
     * @param string $code
     * @param int|null $excludeId
     * @return bool
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM tests WHERE code = :code AND id <> :exclude_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':code', $code, PDO::PARAM_STR);
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        } else {
            $sql = "SELECT COUNT(*) FROM tests WHERE code = :code";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        }

        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Insert a new assessment with forced inactive lifecycle state.
     *
     * @param array $data
     * @return int
     */
    public function createTest(array $data): int
    {
        $sql = "
            INSERT INTO tests (
                code,
                name_ar,
                name_en,
                description_ar,
                description_en,
                instructions_ar,
                instructions_en,
                duration_minutes,
                question_count,
                passing_percentage,
                display_order,
                randomize_questions,
                randomize_options,
                allow_back_navigation,
                show_result_to_employee,
                is_active,
                available_from,
                available_until,
                created_at,
                updated_at
            ) VALUES (
                :code,
                :name_ar,
                :name_en,
                :description_ar,
                :description_en,
                :instructions_ar,
                :instructions_en,
                :duration_minutes,
                :question_count,
                :passing_percentage,
                :display_order,
                :randomize_questions,
                :randomize_options,
                :allow_back_navigation,
                :show_result_to_employee,
                0,
                NULL,
                NULL,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':code', $data['code'], PDO::PARAM_STR);
        $stmt->bindValue(':name_ar', $data['name_ar'], PDO::PARAM_STR);
        $stmt->bindValue(':name_en', $data['name_en'], PDO::PARAM_STR);
        $stmt->bindValue(':description_ar', $data['description_ar'], $data['description_ar'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':description_en', $data['description_en'], $data['description_en'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':instructions_ar', $data['instructions_ar'], $data['instructions_ar'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':instructions_en', $data['instructions_en'], $data['instructions_en'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':duration_minutes', $data['duration_minutes'], PDO::PARAM_INT);
        $stmt->bindValue(':question_count', $data['question_count'], PDO::PARAM_INT);
        $stmt->bindValue(':passing_percentage', $data['passing_percentage'], PDO::PARAM_STR);
        $stmt->bindValue(':display_order', $data['display_order'], PDO::PARAM_INT);
        $stmt->bindValue(':randomize_questions', $data['randomize_questions'], PDO::PARAM_INT);
        $stmt->bindValue(':randomize_options', $data['randomize_options'], PDO::PARAM_INT);
        $stmt->bindValue(':allow_back_navigation', $data['allow_back_navigation'], PDO::PARAM_INT);
        $stmt->bindValue(':show_result_to_employee', $data['show_result_to_employee'], PDO::PARAM_INT);

        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update editable assessment metadata fields using one fixed explicit SQL query.
     *
     * @param int $id
     * @param array $data
     * @return int
     */
    public function updateTest(int $id, array $data): int
    {
        $sql = "
            UPDATE tests SET
                code = :code,
                name_ar = :name_ar,
                name_en = :name_en,
                description_ar = :description_ar,
                description_en = :description_en,
                instructions_ar = :instructions_ar,
                instructions_en = :instructions_en,
                duration_minutes = :duration_minutes,
                question_count = :question_count,
                passing_percentage = :passing_percentage,
                display_order = :display_order,
                randomize_questions = :randomize_questions,
                randomize_options = :randomize_options,
                allow_back_navigation = :allow_back_navigation,
                show_result_to_employee = :show_result_to_employee,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':code', $data['code'], PDO::PARAM_STR);
        $stmt->bindValue(':name_ar', $data['name_ar'], PDO::PARAM_STR);
        $stmt->bindValue(':name_en', $data['name_en'], PDO::PARAM_STR);
        $stmt->bindValue(':description_ar', $data['description_ar'], $data['description_ar'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':description_en', $data['description_en'], $data['description_en'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':instructions_ar', $data['instructions_ar'], $data['instructions_ar'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':instructions_en', $data['instructions_en'], $data['instructions_en'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':duration_minutes', $data['duration_minutes'], PDO::PARAM_INT);
        $stmt->bindValue(':question_count', $data['question_count'], PDO::PARAM_INT);
        $stmt->bindValue(':passing_percentage', $data['passing_percentage'], PDO::PARAM_STR);
        $stmt->bindValue(':display_order', $data['display_order'], PDO::PARAM_INT);
        $stmt->bindValue(':randomize_questions', $data['randomize_questions'], PDO::PARAM_INT);
        $stmt->bindValue(':randomize_options', $data['randomize_options'], PDO::PARAM_INT);
        $stmt->bindValue(':allow_back_navigation', $data['allow_back_navigation'], PDO::PARAM_INT);
        $stmt->bindValue(':show_result_to_employee', $data['show_result_to_employee'], PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Return question-bank readiness metrics used by lifecycle activation.
     * A valid question must be active, not archived, belong to an active category,
     * contain at least two options, and contain exactly one correct option.
     *
     * @param int $testId
     * @return array{required_question_count:int,active_question_count:int,valid_question_count:int,invalid_question_count:int,is_ready:bool}
     */
    public function getLifecycleReadiness(int $testId): array
    {
        $sql = "
            SELECT
                t.question_count AS required_question_count,
                COUNT(q.id) AS active_question_count,
                COALESCE(SUM(
                    CASE
                        WHEN qc.is_active = 1
                         AND COALESCE(os.option_count, 0) >= 2
                         AND COALESCE(os.correct_count, 0) = 1
                        THEN 1 ELSE 0
                    END
                ), 0) AS valid_question_count
            FROM tests t
            LEFT JOIN questions q
                ON q.test_id = t.id
               AND q.is_active = 1
               AND q.archived_at IS NULL
            LEFT JOIN question_categories qc
                ON qc.id = q.category_id
               AND qc.test_id = q.test_id
            LEFT JOIN (
                SELECT
                    question_id,
                    COUNT(*) AS option_count,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_count
                FROM question_options
                GROUP BY question_id
            ) os ON os.question_id = q.id
            WHERE t.id = :test_id
            GROUP BY t.id, t.question_count
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return [
                'required_question_count' => 0,
                'active_question_count' => 0,
                'valid_question_count' => 0,
                'invalid_question_count' => 0,
                'is_ready' => false,
            ];
        }

        $required = (int)$row['required_question_count'];
        $active = (int)$row['active_question_count'];
        $valid = (int)$row['valid_question_count'];

        return [
            'required_question_count' => $required,
            'active_question_count' => $active,
            'valid_question_count' => $valid,
            'invalid_question_count' => max(0, $active - $valid),
            'is_ready' => $required > 0 && $valid >= $required,
        ];
    }

    /**
     * Update only lifecycle fields using a fixed explicit statement.
     */
    public function updateLifecycle(
        int $testId,
        int $isActive,
        ?string $availableFrom,
        ?string $availableUntil
    ): int {
        $sql = "
            UPDATE tests SET
                is_active = :is_active,
                available_from = :available_from,
                available_until = :available_until,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :test_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->bindValue(
            ':available_from',
            $availableFrom,
            $availableFrom === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $stmt->bindValue(
            ':available_until',
            $availableUntil,
            $availableUntil === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Get aggregate attempt status counts for locking checks.
     *
     * @param int $testId
     * @return array
     */
    public function getAttemptStateCounts(int $testId): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_attempts,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_attempts,
                SUM(CASE WHEN status = 'TIMED_OUT' THEN 1 ELSE 0 END) as timed_out_attempts,
                SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_attempts
            FROM test_attempts
            WHERE test_id = :test_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_attempts' => (int)($row['total_attempts'] ?? 0),
            'in_progress_attempts' => (int)($row['in_progress_attempts'] ?? 0),
            'completed_attempts' => (int)($row['completed_attempts'] ?? 0),
            'timed_out_attempts' => (int)($row['timed_out_attempts'] ?? 0),
            'cancelled_attempts' => (int)($row['cancelled_attempts'] ?? 0),
        ];
    }

    /**
     * Fetch safe aggregate metrics for a test using separate queries.
     *
     * @param int $testId
     * @return array
     */
    public function getSafeAggregates(int $testId): array
    {
        // 1. Active Question Bank Count (is_active = 1 AND archived_at IS NULL)
        $qStmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM questions
            WHERE test_id = :test_id
              AND is_active = 1
              AND archived_at IS NULL
        ");
        $qStmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $qStmt->execute();
        $activeQuestionBankCount = (int)$qStmt->fetchColumn();

        // 2. Assignments Count
        $assignStmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM test_assignments
            WHERE test_id = :test_id
        ");
        $assignStmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $assignStmt->execute();
        $assignmentsCount = (int)$assignStmt->fetchColumn();

        // 3. Total Attempts Count
        $attStmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM test_attempts
            WHERE test_id = :test_id
        ");
        $attStmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $attStmt->execute();
        $attemptsCount = (int)$attStmt->fetchColumn();

        // 4. Completed Attempts Count (status = 'COMPLETED' ONLY per Section 11 spec)
        $compAttStmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM test_attempts
            WHERE test_id = :test_id
              AND status = 'COMPLETED'
        ");
        $compAttStmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
        $compAttStmt->execute();
        $completedAttemptsCount = (int)$compAttStmt->fetchColumn();

        return [
            'active_question_bank_count' => $activeQuestionBankCount,
            'assignments_count' => $assignmentsCount,
            'attempts_count' => $attemptsCount,
            'completed_attempts_count' => $completedAttemptsCount,
        ];
    }
}
