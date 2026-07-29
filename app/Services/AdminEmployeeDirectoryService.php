<?php

namespace App\Services;

use App\Repositories\AdminEmployeeRepository;
use Throwable;

class AdminEmployeeDirectoryService
{
    private AdminEmployeeRepository $repository;

    public function __construct(?AdminEmployeeRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminEmployeeRepository();
    }

    /**
     * Retrieve directory list with normalized filters, pagination, and departments.
     *
     * @param array $query
     * @return array
     */
    public function getDirectory(array $query): array
    {
        try {
            $departments = $this->repository->getDepartments();
            $deptWhitelist = array_map(static fn($d) => (int)$d['id'], $departments);

            // 1. Normalize q
            $q = isset($query['q']) && is_string($query['q']) ? trim($query['q']) : '';
            if (mb_strlen($q) > 100) {
                $q = mb_substr($q, 0, 100);
            }

            // 2. Normalize department_id against active departments whitelist
            $departmentId = null;
            if (isset($query['department_id'])) {
                $parsedDept = $this->parsePositiveInt($query['department_id']);
                if ($parsedDept !== null && in_array($parsedDept, $deptWhitelist, true)) {
                    $departmentId = $parsedDept;
                }
            }

            // 3. Normalize status
            $rawStatus = $query['status'] ?? 'all';
            $allowedStatuses = ['all', 'active', 'inactive', 'archived'];
            $status = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : 'all';

            // 4. Normalize page
            $page = 1;
            if (isset($query['page'])) {
                $parsedPage = $this->parsePositiveInt($query['page']);
                if ($parsedPage !== null) {
                    $page = $parsedPage;
                }
            }

            $filters = [
                'q' => $q,
                'department_id' => $departmentId,
                'status' => $status,
            ];

            $results = $this->repository->search($filters, $page, 20);

            return [
                'results' => $results,
                'filters' => [
                    'q' => $q,
                    'department_id' => $departmentId,
                    'status' => $status,
                    'page' => $results['page'] ?? $page,
                ],
                'departments' => $departments,
            ];
        } catch (Throwable $e) {
            error_log("AdminEmployeeDirectoryService getDirectory error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve single employee details safely.
     *
     * @param mixed $rawId
     * @return array
     */
    public function getEmployeeDetails(mixed $rawId): array
    {
        $id = $this->parsePositiveInt($rawId);
        if ($id === null) {
            return ['success' => false, 'code' => 'INVALID_ID'];
        }

        try {
            $employee = $this->repository->findSafeById($id);
            if (!$employee) {
                return ['success' => false, 'code' => 'NOT_FOUND'];
            }

            return [
                'success' => true,
                'employee' => $employee,
            ];
        } catch (Throwable $e) {
            error_log("AdminEmployeeDirectoryService getEmployeeDetails error: " . $e->getMessage());
            return ['success' => false, 'code' => 'INTERNAL_ERROR'];
        }
    }

    /**
     * Strict positive integer parser (accepts int >= 1 or digit-only string >= 1).
     *
     * @param mixed $val
     * @return int|null
     */
    private function parsePositiveInt(mixed $val): ?int
    {
        if (is_int($val) && $val >= 1) {
            return $val;
        }
        if (is_string($val) && preg_match('/^[1-9][0-9]*$/', $val) === 1) {
            return (int)$val;
        }
        return null;
    }
}
