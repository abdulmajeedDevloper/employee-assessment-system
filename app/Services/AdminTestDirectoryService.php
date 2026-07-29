<?php

namespace App\Services;

use App\Repositories\AdminTestRepository;
use Throwable;

class AdminTestDirectoryService
{
    private AdminTestRepository $repository;

    public function __construct(?AdminTestRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminTestRepository();
    }

    /**
     * Retrieve assessment directory list with normalized filters and pagination.
     *
     * @param array $query
     * @return array
     */
    public function getDirectory(array $query): array
    {
        try {
            // 1. Normalize q (scalar string, max 100 Unicode chars)
            $q = isset($query['q']) && is_string($query['q']) ? trim($query['q']) : '';
            if (mb_strlen($q) > 100) {
                $q = mb_substr($q, 0, 100);
            }

            // 2. Normalize status against whitelist
            $rawStatus = $query['status'] ?? 'all';
            $allowedStatuses = ['all', 'active', 'inactive'];
            $status = (is_string($rawStatus) && in_array($rawStatus, $allowedStatuses, true)) ? $rawStatus : 'all';

            // 3. Normalize page using strict positive int parser
            $page = 1;
            if (isset($query['page'])) {
                $parsedPage = $this->parsePositiveInt($query['page']);
                if ($parsedPage !== null) {
                    $page = $parsedPage;
                }
            }

            $filters = [
                'q' => $q,
                'status' => $status,
            ];

            $results = $this->repository->search($filters, $page, 20);

            return [
                'results' => $results,
                'filters' => [
                    'q' => $q,
                    'status' => $status,
                    'page' => $results['page'] ?? $page,
                ],
            ];
        } catch (Throwable $e) {
            error_log("AdminTestDirectoryService getDirectory error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve single assessment safe details.
     *
     * @param mixed $rawId
     * @param string $lang
     * @return array
     */
    public function getTestDetails(mixed $rawId, string $lang = 'ar'): array
    {
        $id = $this->parsePositiveInt($rawId);
        if ($id === null) {
            return ['success' => false, 'code' => 'INVALID_ID'];
        }

        try {
            $test = $this->repository->findSafeById($id);
            if (!$test) {
                return ['success' => false, 'code' => 'NOT_FOUND'];
            }

            $aggregates = $this->repository->getSafeAggregates($id);

            $name = ($lang === 'en') ? $test['name_en'] : $test['name_ar'];
            $description = ($lang === 'en') ? ($test['description_en'] ?? '') : ($test['description_ar'] ?? '');
            $instructions = ($lang === 'en') ? ($test['instructions_en'] ?? '') : ($test['instructions_ar'] ?? '');

            return [
                'success' => true,
                'code' => 'SUCCESS',
                'test' => $test,
                'localized' => [
                    'name' => $name,
                    'description' => $description,
                    'instructions' => $instructions,
                ],
                'aggregates' => $aggregates,
            ];
        } catch (Throwable $e) {
            error_log("AdminTestDirectoryService getTestDetails error: " . $e->getMessage());
            return ['success' => false, 'code' => 'INTERNAL_ERROR'];
        }
    }

    /**
     * Strict positive integer parser (accepts int >= 1 or digit-only string matching /^[1-9][0-9]*$/).
     * Rejects: missing/null/array/bool/empty, 0, negative, +1, 01, 1.0, 1e2, whitespace, overflow.
     *
     * @param mixed $val
     * @return int|null
     */
    public function parsePositiveInt(mixed $val): ?int
    {
        if (is_int($val) && $val >= 1) {
            return $val;
        }
        if (is_string($val) && preg_match('/^[1-9][0-9]*$/', $val) === 1) {
            $intVal = (int)$val;
            if ((string)$intVal === $val) {
                return $intVal;
            }
        }
        return null;
    }
}
