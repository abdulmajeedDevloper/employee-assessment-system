<?php

namespace App\Services;

use App\Repositories\AdminQuestionRepository;

class AdminQuestionDirectoryService
{
    private AdminQuestionRepository $repository;

    public function __construct(?AdminQuestionRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminQuestionRepository();
    }

    public function getDirectory(array $query): array
    {
        $q = isset($query['q']) && is_string($query['q']) ? trim($query['q']) : '';
        if (mb_strlen($q) > 150) {
            $q = mb_substr($q, 0, 150);
        }

        $testId = $this->parsePositiveInt($query['test_id'] ?? null);
        $categoryId = $this->parsePositiveInt($query['category_id'] ?? null);
        $difficulty = $this->whitelist($query['difficulty'] ?? 'all', ['all', 'easy', 'medium', 'hard'], 'all');
        $status = $this->whitelist($query['status'] ?? 'all', ['all', 'active', 'inactive', 'archived'], 'all');
        $validity = $this->whitelist($query['validity'] ?? 'all', ['all', 'valid', 'invalid'], 'all');
        $page = $this->parsePositiveInt($query['page'] ?? null) ?? 1;

        if ($testId !== null && $categoryId !== null) {
            $categories = $this->repository->getCategoriesForFilter($testId);
            $categoryIds = array_map(static fn(array $row): int => (int)$row['id'], $categories);
            if (!in_array($categoryId, $categoryIds, true)) {
                $categoryId = null;
            }
        } else {
            $categories = $this->repository->getCategoriesForFilter($testId);
        }

        $filters = [
            'q' => $q,
            'test_id' => $testId,
            'category_id' => $categoryId,
            'difficulty' => $difficulty,
            'status' => $status,
            'validity' => $validity,
        ];

        return [
            'results' => $this->repository->search($filters, $page, 20),
            'filters' => $filters + ['page' => $page],
            'tests' => $this->repository->getTestsForFilter(),
            'categories' => $categories,
        ];
    }

    public function getQuestionDetails(mixed $rawId): array
    {
        $id = $this->parsePositiveInt($rawId);
        if ($id === null) {
            return ['success' => false, 'code' => 'INVALID_ID'];
        }

        $question = $this->repository->findSafeById($id);
        if ($question === null) {
            return ['success' => false, 'code' => 'NOT_FOUND'];
        }

        return ['success' => true, 'code' => 'SUCCESS', 'question' => $question];
    }

    public function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value >= 1) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $parsed = (int)$value;
            if ((string)$parsed === $value) {
                return $parsed;
            }
        }
        return null;
    }

    private function whitelist(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }
}
