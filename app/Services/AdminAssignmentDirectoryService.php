<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminAssignmentRepository;

class AdminAssignmentDirectoryService
{
    public function __construct(private ?AdminAssignmentRepository $repository = null)
    {
        $this->repository ??= new AdminAssignmentRepository();
    }

    public function getDirectory(array $input): array
    {
        $q = is_string($input['q'] ?? null) ? trim((string)$input['q']) : '';
        if (mb_strlen($q) > 150) {
            $q = mb_substr($q, 0, 150);
        }

        $status = is_string($input['status'] ?? null) ? strtoupper(trim((string)$input['status'])) : 'ALL';
        $allowedStatuses = ['ALL', 'PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED', 'COMPLETED'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'ALL';
        }

        $availability = is_string($input['availability'] ?? null) ? strtolower(trim((string)$input['availability'])) : 'all';
        if (!in_array($availability, ['all', 'open', 'upcoming', 'expired'], true)) {
            $availability = 'all';
        }

        $filters = [
            'q' => $q,
            'status' => $status === 'ALL' ? 'all' : $status,
            'availability' => $availability,
            'employee_id' => $this->parsePositiveInt($input['employee_id'] ?? null),
            'test_id' => $this->parsePositiveInt($input['test_id'] ?? null),
        ];
        $page = $this->parsePositiveInt($input['page'] ?? null) ?? 1;

        return [
            'filters' => $filters + ['page' => $page],
            'results' => $this->repository->search($filters, $page, 20),
            'employees' => $this->repository->getActiveEmployees(),
            'tests' => $this->repository->getAllTestsForFilter(),
        ];
    }

    public function getDetails(mixed $rawId): array
    {
        $id = $this->parsePositiveInt($rawId);
        if ($id === null) {
            return ['success' => false, 'code' => 'NOT_FOUND'];
        }
        $assignment = $this->repository->findSafeById($id);
        return $assignment === null
            ? ['success' => false, 'code' => 'NOT_FOUND']
            : ['success' => true, 'assignment' => $assignment];
    }

    public function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', trim($value))) {
            return null;
        }
        $parsed = filter_var(trim($value), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $parsed === false ? null : (int)$parsed;
    }
}
