<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AdminQuestionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Search the question bank with prepared filters and bounded pagination.
     *
     * @return array{items:array,total:int,page:int,per_page:int,total_pages:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        [$whereSql, $params] = $this->buildWhere($filters);
        $optionStats = $this->optionStatsSql();

        $countSql = "
            SELECT COUNT(*)
            FROM questions q
            INNER JOIN tests t ON t.id = q.test_id
            INNER JOIN question_categories qc
                ON qc.id = q.category_id AND qc.test_id = q.test_id
            LEFT JOIN ({$optionStats}) os ON os.question_id = q.id
            {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $this->bindParams($countStmt, $params);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $dataSql = "
            SELECT
                q.id,
                q.test_id,
                q.category_id,
                q.type,
                q.text_ar,
                q.text_en,
                q.difficulty,
                q.score,
                q.is_active,
                q.archived_at,
                q.created_at,
                q.updated_at,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en,
                qc.name_ar AS category_name_ar,
                qc.name_en AS category_name_en,
                qc.is_active AS category_is_active,
                COALESCE(os.option_count, 0) AS option_count,
                COALESCE(os.correct_count, 0) AS correct_count,
                CASE
                    WHEN q.is_active = 1
                     AND q.archived_at IS NULL
                     AND qc.is_active = 1
                     AND COALESCE(os.option_count, 0) >= 2
                     AND COALESCE(os.correct_count, 0) = 1
                    THEN 1 ELSE 0
                END AS is_valid
            FROM questions q
            INNER JOIN tests t ON t.id = q.test_id
            INNER JOIN question_categories qc
                ON qc.id = q.category_id AND qc.test_id = q.test_id
            LEFT JOIN ({$optionStats}) os ON os.question_id = q.id
            {$whereSql}
            ORDER BY t.display_order ASC, q.test_id ASC, q.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($dataSql);
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
     * Fetch safe question details and answer options for authorized administrators.
     */
    public function findSafeById(int $questionId): ?array
    {
        $optionStats = $this->optionStatsSql();
        $sql = "
            SELECT
                q.id,
                q.test_id,
                q.category_id,
                q.type,
                q.text_ar,
                q.text_en,
                q.difficulty,
                q.score,
                q.is_active,
                q.archived_at,
                q.created_at,
                q.updated_at,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en,
                qc.name_ar AS category_name_ar,
                qc.name_en AS category_name_en,
                qc.is_active AS category_is_active,
                COALESCE(os.option_count, 0) AS option_count,
                COALESCE(os.correct_count, 0) AS correct_count,
                CASE
                    WHEN q.is_active = 1
                     AND q.archived_at IS NULL
                     AND qc.is_active = 1
                     AND COALESCE(os.option_count, 0) >= 2
                     AND COALESCE(os.correct_count, 0) = 1
                    THEN 1 ELSE 0
                END AS is_valid,
                CASE WHEN qmi.id IS NULL THEN 0 ELSE 1 END AS has_question_image,
                qmi.public_token AS question_image_token,
                qmi.width_pixels AS question_image_width,
                qmi.height_pixels AS question_image_height
            FROM questions q
            INNER JOIN tests t ON t.id = q.test_id
            INNER JOIN question_categories qc
                ON qc.id = q.category_id AND qc.test_id = q.test_id
            LEFT JOIN ({$optionStats}) os ON os.question_id = q.id
            LEFT JOIN question_media qmi
                ON qmi.question_id = q.id AND qmi.media_type = 'QUESTION_IMAGE'
            WHERE q.id = :question_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
        $stmt->execute();
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($question === false) {
            return null;
        }

        $optionsStmt = $this->pdo->prepare("
            SELECT
                qo.id,
                qo.option_key,
                qo.text_ar,
                qo.text_en,
                qo.is_correct,
                qo.display_order,
                CASE WHEN omi.id IS NULL THEN 0 ELSE 1 END AS has_option_image,
                omi.public_token AS option_image_token,
                omi.width_pixels AS option_image_width,
                omi.height_pixels AS option_image_height
            FROM question_options qo
            LEFT JOIN question_media omi
                ON omi.option_id = qo.id AND omi.media_type = 'OPTION_IMAGE'
            WHERE qo.question_id = :question_id
            ORDER BY qo.display_order ASC, qo.id ASC
        ");
        $optionsStmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
        $optionsStmt->execute();

        $question['options'] = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);
        return $question;
    }

    /**
     * Fetch a question and options for a management form.
     */
    public function findEditableById(int $questionId): ?array
    {
        return $this->findSafeById($questionId);
    }

    public function testExists(int $testId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM tests WHERE id = :test_id LIMIT 1");
        $stmt->execute([':test_id' => $testId]);
        return (bool)$stmt->fetchColumn();
    }

    public function categoryBelongsToTest(int $categoryId, int $testId, bool $activeOnly = true): bool
    {
        $sql = "SELECT 1 FROM question_categories WHERE id = :category_id AND test_id = :test_id";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':category_id' => $categoryId, ':test_id' => $testId]);
        return (bool)$stmt->fetchColumn();
    }

    public function categoryNameExists(int $testId, string $nameAr, string $nameEn): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM question_categories
            WHERE test_id = :test_id
              AND (LOWER(name_ar) = LOWER(:name_ar) OR LOWER(name_en) = LOWER(:name_en))
            LIMIT 1
        ");
        $stmt->execute([
            ':test_id' => $testId,
            ':name_ar' => $nameAr,
            ':name_en' => $nameEn,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function createCategory(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO question_categories (
                test_id, name_ar, name_en, is_active, created_at
            ) VALUES (
                :test_id, :name_ar, :name_en, 1, UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([
            ':test_id' => $data['test_id'],
            ':name_ar' => $data['name_ar'],
            ':name_en' => $data['name_en'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function createQuestion(array $data, int $adminId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO questions (
                test_id, category_id, type, text_ar, text_en, difficulty,
                score, is_active, archived_at, created_by, created_at, updated_at
            ) VALUES (
                :test_id, :category_id, :type, :text_ar, :text_en, :difficulty,
                :score, :is_active, NULL, :created_by, UTC_TIMESTAMP(), UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([
            ':test_id' => $data['test_id'],
            ':category_id' => $data['category_id'],
            ':type' => $data['type'],
            ':text_ar' => $data['text_ar'],
            ':text_en' => $data['text_en'],
            ':difficulty' => $data['difficulty'],
            ':score' => $data['score'],
            ':is_active' => $data['is_active'],
            ':created_by' => $adminId,
        ]);
        $questionId = (int)$this->pdo->lastInsertId();
        $this->insertOptions($questionId, $data['options']);
        return $questionId;
    }

    /**
     * Lock the question and its parent test for a management transaction.
     */
    public function findForUpdate(int $questionId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                q.id, q.test_id, q.category_id, q.type, q.text_ar, q.text_en,
                q.difficulty, q.score, q.is_active, q.archived_at, q.updated_at,
                t.is_active AS test_is_active, t.question_count AS required_question_count,
                qc.is_active AS category_is_active
            FROM questions q
            INNER JOIN tests t ON t.id = q.test_id
            INNER JOIN question_categories qc
                ON qc.id = q.category_id AND qc.test_id = q.test_id
            WHERE q.id = :question_id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':question_id' => $questionId]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($question === false) {
            return null;
        }

        $optionsStmt = $this->pdo->prepare("
            SELECT id, option_key, text_ar, text_en, is_correct, display_order
            FROM question_options
            WHERE question_id = :question_id
            ORDER BY display_order ASC, id ASC
            FOR UPDATE
        ");
        $optionsStmt->execute([':question_id' => $questionId]);
        $question['options'] = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);
        return $question;
    }

    public function countValidQuestionsForTest(int $testId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM questions q
            INNER JOIN question_categories qc
                ON qc.id = q.category_id AND qc.test_id = q.test_id
            INNER JOIN (
                SELECT question_id,
                       COUNT(*) AS option_count,
                       SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_count
                FROM question_options
                GROUP BY question_id
            ) os ON os.question_id = q.id
            WHERE q.test_id = :test_id
              AND q.is_active = 1
              AND q.archived_at IS NULL
              AND qc.is_active = 1
              AND os.option_count >= 2
              AND os.correct_count = 1
        ");
        $stmt->execute([':test_id' => $testId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Persist question fields and synchronize options while preserving existing option IDs.
     */
    public function updateQuestion(int $questionId, array $data, array $currentOptions): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE questions
            SET category_id = :category_id,
                type = :type,
                text_ar = :text_ar,
                text_en = :text_en,
                difficulty = :difficulty,
                score = :score,
                is_active = :is_active,
                updated_at = UTC_TIMESTAMP()
            WHERE id = :question_id
        ");
        $stmt->execute([
            ':category_id' => $data['category_id'],
            ':type' => $data['type'],
            ':text_ar' => $data['text_ar'],
            ':text_en' => $data['text_en'],
            ':difficulty' => $data['difficulty'],
            ':score' => $data['score'],
            ':is_active' => $data['is_active'],
            ':question_id' => $questionId,
        ]);

        $updateOption = $this->pdo->prepare("
            UPDATE question_options
            SET option_key = :option_key,
                text_ar = :text_ar,
                text_en = :text_en,
                is_correct = :is_correct,
                display_order = :display_order
            WHERE id = :option_id AND question_id = :question_id
        ");
        $insertOption = $this->pdo->prepare("
            INSERT INTO question_options (
                question_id, option_key, text_ar, text_en, is_correct, display_order
            ) VALUES (
                :question_id, :option_key, :text_ar, :text_en, :is_correct, :display_order
            )
        ");

        foreach ($data['options'] as $index => $option) {
            if (isset($currentOptions[$index])) {
                $updateOption->execute([
                    ':option_key' => $option['option_key'],
                    ':text_ar' => $option['text_ar'],
                    ':text_en' => $option['text_en'],
                    ':is_correct' => $option['is_correct'],
                    ':display_order' => $option['display_order'],
                    ':option_id' => (int)$currentOptions[$index]['id'],
                    ':question_id' => $questionId,
                ]);
            } else {
                $insertOption->execute([
                    ':question_id' => $questionId,
                    ':option_key' => $option['option_key'],
                    ':text_ar' => $option['text_ar'],
                    ':text_en' => $option['text_en'],
                    ':is_correct' => $option['is_correct'],
                    ':display_order' => $option['display_order'],
                ]);
            }
        }

        if (count($currentOptions) > count($data['options'])) {
            $idsToDelete = array_map(
                static fn(array $row): int => (int)$row['id'],
                array_slice($currentOptions, count($data['options']))
            );
            $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
            $delete = $this->pdo->prepare("
                DELETE FROM question_options
                WHERE question_id = ? AND id IN ({$placeholders})
            ");
            $delete->execute(array_merge([$questionId], $idsToDelete));
        }
    }

    public function optionsHaveMedia(array $optionIds): bool
    {
        if ($optionIds === []) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
        $stmt = $this->pdo->prepare("SELECT 1 FROM question_media WHERE option_id IN ({$placeholders}) LIMIT 1");
        $stmt->execute(array_values($optionIds));
        return (bool)$stmt->fetchColumn();
    }

    public function archiveQuestion(int $questionId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE questions
            SET is_active = 0, archived_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
            WHERE id = :question_id
        ");
        $stmt->execute([':question_id' => $questionId]);
    }

    public function restoreQuestion(int $questionId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE questions
            SET is_active = 0, archived_at = NULL, updated_at = UTC_TIMESTAMP()
            WHERE id = :question_id
        ");
        $stmt->execute([':question_id' => $questionId]);
    }

    private function insertOptions(int $questionId, array $options): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO question_options (
                question_id, option_key, text_ar, text_en, is_correct, display_order
            ) VALUES (
                :question_id, :option_key, :text_ar, :text_en, :is_correct, :display_order
            )
        ");
        foreach ($options as $option) {
            $stmt->execute([
                ':question_id' => $questionId,
                ':option_key' => $option['option_key'],
                ':text_ar' => $option['text_ar'],
                ':text_en' => $option['text_en'],
                ':is_correct' => $option['is_correct'],
                ':display_order' => $option['display_order'],
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getTestsForFilter(): array
    {
        return $this->pdo->query("
            SELECT id, code, name_ar, name_en, is_active
            FROM tests
            ORDER BY display_order ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function getCategoriesForFilter(?int $testId = null): array
    {
        if ($testId !== null) {
            $stmt = $this->pdo->prepare("
                SELECT id, test_id, name_ar, name_en, is_active
                FROM question_categories
                WHERE test_id = :test_id
                ORDER BY name_en ASC, id ASC
            ");
            $stmt->bindValue(':test_id', $testId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->pdo->query("
            SELECT id, test_id, name_ar, name_en, is_active
            FROM question_categories
            ORDER BY test_id ASC, name_en ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{0:string,1:array<string,array{0:mixed,1:int}>} */
    private function buildWhere(array $filters): array
    {
        $clauses = ['1 = 1'];
        $params = [];

        $q = (string)($filters['q'] ?? '');
        if ($q !== '') {
            $clauses[] = "(
                q.text_ar LIKE :search_text_ar ESCAPE '\\\\'
                OR q.text_en LIKE :search_text_en ESCAPE '\\\\'
                OR t.code LIKE :search_code ESCAPE '\\\\'
                OR t.name_ar LIKE :search_test_ar ESCAPE '\\\\'
                OR t.name_en LIKE :search_test_en ESCAPE '\\\\'
            )";
            $searchValue = '%' . $this->escapeLike($q) . '%';
            $params[':search_text_ar'] = [$searchValue, PDO::PARAM_STR];
            $params[':search_text_en'] = [$searchValue, PDO::PARAM_STR];
            $params[':search_code'] = [$searchValue, PDO::PARAM_STR];
            $params[':search_test_ar'] = [$searchValue, PDO::PARAM_STR];
            $params[':search_test_en'] = [$searchValue, PDO::PARAM_STR];
        }

        if (!empty($filters['test_id'])) {
            $clauses[] = 'q.test_id = :test_id';
            $params[':test_id'] = [(int)$filters['test_id'], PDO::PARAM_INT];
        }
        if (!empty($filters['category_id'])) {
            $clauses[] = 'q.category_id = :category_id';
            $params[':category_id'] = [(int)$filters['category_id'], PDO::PARAM_INT];
        }
        $difficulty = (string)($filters['difficulty'] ?? 'all');
        if (in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $clauses[] = 'q.difficulty = :difficulty';
            $params[':difficulty'] = [$difficulty, PDO::PARAM_STR];
        }

        $status = (string)($filters['status'] ?? 'all');
        if ($status === 'active') {
            $clauses[] = 'q.is_active = 1 AND q.archived_at IS NULL';
        } elseif ($status === 'inactive') {
            $clauses[] = 'q.is_active = 0 AND q.archived_at IS NULL';
        } elseif ($status === 'archived') {
            $clauses[] = 'q.archived_at IS NOT NULL';
        }

        $validity = (string)($filters['validity'] ?? 'all');
        $validExpression = "(
            q.is_active = 1
            AND q.archived_at IS NULL
            AND qc.is_active = 1
            AND COALESCE(os.option_count, 0) >= 2
            AND COALESCE(os.correct_count, 0) = 1
        )";
        if ($validity === 'valid') {
            $clauses[] = $validExpression;
        } elseif ($validity === 'invalid') {
            $clauses[] = "NOT {$validExpression}";
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function optionStatsSql(): string
    {
        return "
            SELECT
                question_id,
                COUNT(*) AS option_count,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_count
            FROM question_options
            GROUP BY question_id
        ";
    }

    /** @param array<string,array{0:mixed,1:int}> $params */
    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => [$value, $type]) {
            $stmt->bindValue($name, $value, $type);
        }
    }

    private function escapeLike(string $value): string
    {
        return strtr($value, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
    }
}
