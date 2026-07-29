<?php

namespace App\Services;

use App\Repositories\AssessmentProgressRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\TestAssignmentRepository;

class AssessmentStatusService
{
    private EmployeeRepository $empRepo;
    private TestAssignmentRepository $assignRepo;
    private AssessmentProgressRepository $progressRepo;

    public function __construct(
        ?EmployeeRepository $empRepo = null,
        ?TestAssignmentRepository $assignRepo = null,
        ?AssessmentProgressRepository $progressRepo = null
    ) {
        $this->empRepo = $empRepo ?? new EmployeeRepository();
        $this->assignRepo = $assignRepo ?? new TestAssignmentRepository();
        $this->progressRepo = $progressRepo ?? new AssessmentProgressRepository();
    }

    public function getAssessmentState(int $employeeId): array
    {
        $employee = $this->empRepo->findById($employeeId);
        if (!$employee) {
            return ['state' => 'INVALID_EMPLOYEE'];
        }

        // 1. Profile Completion Check
        if (empty($employee['profile_completed_at'])) {
            return [
                'state' => 'PROFILE_INCOMPLETE',
                'employee' => $employee
            ];
        }

        // 2. Fetch Assignments & Progress
        $assignments = $this->assignRepo->getAssignmentsForEmployee($employeeId);
        if (empty($assignments)) {
            return [
                'state' => 'NO_ASSIGNMENTS',
                'employee' => $employee
            ];
        }

        // 3. Active Attempt Check (In Progress)
        $activeAttempt = $this->progressRepo->getActiveAttemptForEmployee($employeeId);
        if ($activeAttempt) {
            return [
                'state' => 'IN_PROGRESS',
                'employee' => $employee,
                'attempt' => $activeAttempt
            ];
        }

        // 4. Check for Pending or Completed Assignments
        $hasPending = false;
        $allCompleted = true;

        foreach ($assignments as $a) {
            if ($a['status'] === 'COMPLETED') {
                continue;
            }
            $allCompleted = false;
            if ($a['status'] === 'PENDING' || $a['status'] === 'IN_PROGRESS' || $a['status'] === 'RETAKE_AUTHORIZED') {
                $hasPending = true;
                break;
            }
        }

        if ($allCompleted) {
            return [
                'state' => 'COMPLETED_ALL',
                'employee' => $employee
            ];
        }

        if ($hasPending) {
            return [
                'state' => 'PENDING_INSTRUCTIONS',
                'employee' => $employee,
                'assignments' => $assignments
            ];
        }

        return [
            'state' => 'NO_ASSIGNMENTS',
            'employee' => $employee
        ];
    }
}
