<?php

namespace App\Controllers\Employee;

use App\Middleware\EmployeeAuthMiddleware;
use App\Services\AssessmentAttemptService;
use App\Services\AssessmentStatusService;

class AssessmentStatusController
{
    private AssessmentStatusService $statusService;
    private AssessmentAttemptService $attemptService;

    public function __construct(
        ?AssessmentStatusService $statusService = null,
        ?AssessmentAttemptService $attemptService = null
    ) {
        $this->statusService =
            $statusService ??
            new AssessmentStatusService();

        $this->attemptService =
            $attemptService ??
            new AssessmentAttemptService();
    }

    public function showStatus(): void
    {
        $employee = EmployeeAuthMiddleware::handle(true);

        $timeoutResult =
            $this->attemptService->timeoutExpiredAttempt(
                (int) $employee['id'],
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );

        $timedOutNow =
            ($timeoutResult['success'] ?? false) === true &&
            ($timeoutResult['timed_out'] ?? false) === true;

        $timeoutFailed =
            ($timeoutResult['success'] ?? false) !== true;

        if ($timedOutNow) {
            unset(
                $_SESSION['assessment_attempt_id'],
                $_SESSION['assessment_session_token']
            );
        }

        $statusData =
            $this->statusService->getAssessmentState(
                (int) $employee['id']
            );

        if ($statusData['state'] === 'PROFILE_INCOMPLETE') {
            $_SESSION['flash_errors'] = ['general' => 'Please confirm your personal details first before proceeding to assigned assessments.'];
            header('Location: /employee/profile', true, 303);
            exit(0);
        }

        $lang = $_GET['lang'] ?? $_SESSION['employee_auth']['preferred_language'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $translations = require __DIR__ . "/../../../lang/{$lang}.php";

        if (
            $timedOutNow &&
            !isset($_SESSION['flash_error'])
        ) {
            $_SESSION['flash_error'] =
                $translations['assessment_timed_out_message'] ??
                'The assessment time expired.';
        } elseif (
            $timeoutFailed &&
            !isset($_SESSION['flash_error'])
        ) {
            $_SESSION['flash_error'] =
                $translations['attempt_timeout_failed'] ??
                'The expired assessment could not be finalized.';
        }

        $success = $_SESSION['flash_success'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;

        unset(
            $_SESSION['flash_success'],
            $_SESSION['flash_error']
        );

        require __DIR__ . '/../../../views/employee/tests.php';
    }
}
