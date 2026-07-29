<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Middleware\CsrfMiddleware;
use App\Middleware\EmployeeAuthMiddleware;
use App\Services\AssessmentAttemptService;

class AssessmentAttemptController
{
    private AssessmentAttemptService $attemptService;

    public function __construct(
        ?AssessmentAttemptService $attemptService = null
    ) {
        $this->attemptService =
            $attemptService ?? new AssessmentAttemptService();
    }

    public function start(): void
    {
        $employee = EmployeeAuthMiddleware::handle();

        CsrfMiddleware::handle();

        $assignmentId = filter_var(
            $_POST['assignment_id'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($assignmentId === false) {
            $_SESSION['flash_error'] =
                trans('attempt_invalid_assignment');

            $this->redirect('/employee/tests');
        }

        $ipAddress =
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $userAgent =
            $_SERVER['HTTP_USER_AGENT'] ?? '';

        $result = $this->attemptService->startAttempt(
            (int) $employee['id'],
            (int) $assignmentId,
            $ipAddress,
            $userAgent
        );

        if (!$result['success']) {
            $errorCode = (string) (
                $result['code'] ?? 'attempt_start_failed'
            );

            if (
                $errorCode === 'active_attempt_exists' &&
                !empty($_SESSION['assessment_session_token'])
            ) {
                $this->redirect('/employee/attempt');
            }

            $translationKey = match ($errorCode) {
                'invalid_assignment',
                'invalid_assignment_status',
                'invalid_retake_authorization' =>
                    'attempt_invalid_assignment',

                'test_not_available' =>
                    'attempt_not_available',

                'attempt_limit_reached' =>
                    'attempt_limit_reached',

                'insufficient_questions',
                'invalid_question_configuration',
                'invalid_test_configuration' =>
                    'attempt_configuration_error',

                default =>
                    'attempt_start_failed',
            };

            $_SESSION['flash_error'] =
                trans($translationKey);

            $this->redirect('/employee/tests');
        }

        $_SESSION['assessment_attempt_id'] =
            (int) $result['attempt_id'];

        $_SESSION['assessment_session_token'] =
            (string) $result['session_token'];

        $this->redirect('/employee/attempt');
    }

    public function saveAnswer(): void
    {
        $employee = EmployeeAuthMiddleware::handle();

        CsrfMiddleware::handle();

        $attemptQuestionId = filter_var(
            $_POST['attempt_question_id'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        $selectedOptionKey = trim(
            (string) ($_POST['selected_option_key'] ?? '')
        );

        if (
            $attemptQuestionId === false ||
            $selectedOptionKey === '' ||
            strlen($selectedOptionKey) > 64
        ) {
            response_json([
                'success' => false,
                'code' => 'invalid_answer',
                'message' => trans('invalid_answer'),
            ], 422);
        }

        $sessionToken =
            $_SESSION['assessment_session_token'] ?? '';

        if (
            !is_string($sessionToken) ||
            $sessionToken === ''
        ) {
            response_json([
                'success' => false,
                'code' => 'assessment_session_unavailable',
                'message' =>
                    trans('assessment_session_unavailable'),
            ], 409);
        }

        $result = $this->attemptService->saveAnswer(
            (int) $employee['id'],
            (int) $attemptQuestionId,
            $selectedOptionKey,
            $sessionToken
        );

        if (!$result['success']) {
            $errorCode = (string) (
                $result['code'] ??
                'attempt_answer_save_failed'
            );

            $statusCode = match ($errorCode) {
                'invalid_answer' => 422,
                'attempt_expired' => 410,

                'assessment_session_unavailable',
                'invalid_question_configuration' => 409,

                default => 500,
            };

            $translationKey = match ($errorCode) {
                'invalid_answer' =>
                    'invalid_answer',

                'attempt_expired' =>
                    'attempt_expired',

                'assessment_session_unavailable' =>
                    'assessment_session_unavailable',

                'invalid_question_configuration' =>
                    'attempt_configuration_error',

                default =>
                    'attempt_answer_save_failed',
            };

            response_json([
                'success' => false,
                'code' => $errorCode,
                'message' => trans($translationKey),
            ], $statusCode);
        }

        response_json([
            'success' => true,
            'attempt_question_id' =>
                (int) $result['attempt_question_id'],

            'selected_option_key' =>
                (string) $result['selected_option_key'],

            'remaining_seconds' =>
                (int) $result['remaining_seconds'],

            'message' =>
                trans('answer_saved_successfully'),
        ]);
    }
    public function timeout(): void
    {
        $employee = EmployeeAuthMiddleware::handle();

        CsrfMiddleware::handle();

        $ipAddress =
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $userAgent =
            $_SERVER['HTTP_USER_AGENT'] ?? '';

        $result =
            $this->attemptService->timeoutExpiredAttempt(
                (int) $employee['id'],
                $ipAddress,
                $userAgent
            );

        if (!$result['success']) {
            $errorCode = (string) (
                $result['code'] ??
                'attempt_timeout_failed'
            );

            $translationKey = match ($errorCode) {
                'attempt_configuration_error' =>
                    'attempt_configuration_error',

                default =>
                    'attempt_timeout_failed',
            };

            response_json([
                'success' => false,
                'code' => $errorCode,
                'message' => trans($translationKey),
            ], 500);
        }

        $timedOut =
            (bool) ($result['timed_out'] ?? false);

        if ($timedOut) {
            unset(
                $_SESSION['assessment_attempt_id'],
                $_SESSION['assessment_session_token']
            );

            $_SESSION['flash_error'] =
                trans('assessment_timed_out_message');
        }

        response_json([
            'success' => true,
            'timed_out' => $timedOut,
            'redirect_url' =>
                $timedOut
                    ? '/employee/tests'
                    : null,

            'message' =>
                $timedOut
                    ? trans('assessment_timed_out_message')
                    : trans('attempt_not_expired'),
        ]);
    }
    public function submit(): void
    {
        $employee = EmployeeAuthMiddleware::handle();

        CsrfMiddleware::handle();

        $sessionToken =
            $_SESSION['assessment_session_token'] ?? '';

        if (
            !is_string($sessionToken) ||
            trim($sessionToken) === ''
        ) {
            $_SESSION['flash_error'] =
                trans('assessment_session_unavailable');

            $this->redirect('/employee/tests');
        }

        $ipAddress =
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $userAgent =
            $_SERVER['HTTP_USER_AGENT'] ?? '';

        $result = $this->attemptService->submitAttempt(
            (int) $employee['id'],
            $sessionToken,
            $ipAddress,
            $userAgent
        );

        if (!$result['success']) {
            $errorCode = (string) (
                $result['code'] ??
                'attempt_submit_failed'
            );

            $translationKey = match ($errorCode) {
                'attempt_expired' =>
                    'attempt_expired',

                'attempt_already_submitted' =>
                    'attempt_already_submitted',

                'assessment_session_unavailable' =>
                    'assessment_session_unavailable',

                'attempt_configuration_error' =>
                    'attempt_configuration_error',

                default =>
                    'attempt_submit_failed',
            };

            if (
                in_array(
                    $errorCode,
                    [
                        'attempt_already_submitted',
                        'assessment_session_unavailable',
                    ],
                    true
                )
            ) {
                unset(
                    $_SESSION['assessment_attempt_id'],
                    $_SESSION['assessment_session_token']
                );
            }

            $_SESSION['flash_error'] =
                trans($translationKey);

            $this->redirect('/employee/tests');
        }

        unset(
            $_SESSION['assessment_attempt_id'],
            $_SESSION['assessment_session_token']
        );

        $_SESSION['flash_success'] =
            trans('assessment_submitted_successfully');

        $this->redirect('/employee/tests');
    }
    public function show(): void
    {
        $employee = EmployeeAuthMiddleware::handle();

        $lang =
            $_GET['lang'] ??
            $_SESSION['employee_auth']['preferred_language'] ??
            $_SESSION['lang'] ??
            'ar';

        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }

        $_SESSION['lang'] = $lang;

        $sessionToken =
            $_SESSION['assessment_session_token'] ?? null;

        $attempt = $this->attemptService->getActiveAttempt(
            (int) $employee['id'],
            is_string($sessionToken)
                ? $sessionToken
                : null
        );

        if (!$attempt) {
            unset(
                $_SESSION['assessment_attempt_id'],
                $_SESSION['assessment_session_token']
            );

            $_SESSION['flash_error'] =
                trans('assessment_session_unavailable');

            $this->redirect('/employee/tests');
        }

        $questionRows =
            $this->attemptService->getAttemptQuestions(
                (int) $attempt['id']
            );

        $questions = [];

        foreach ($questionRows as $row) {
            $questionSnapshot = json_decode(
                (string) $row['question_snapshot'],
                true
            );

            $optionsSnapshot = json_decode(
                (string) $row['options_snapshot'],
                true
            );

            if (
                !is_array($questionSnapshot) ||
                !is_array($optionsSnapshot)
            ) {
                continue;
            }

            $questions[] = [
                'attempt_question_id' =>
                    (int) $row['id'],

                'original_question_id' =>
                    (int) $row['original_question_id'],

                'display_order' =>
                    (int) $row['display_order'],

                'score' =>
                    (float) $row['score_snapshot'],

                'selected_option_key' =>
                    isset($row['selected_option_key'])
                        ? (string) $row['selected_option_key']
                        : null,

                'last_saved_at' =>
                    $row['last_saved_at'] ?? null,

                'question' =>
                    $questionSnapshot,

                'options' =>
                    $optionsSnapshot,
            ];
        }

        if (empty($questions)) {
            $_SESSION['flash_error'] =
                trans('attempt_configuration_error');

            $this->redirect('/employee/tests');
        }

        $translations = require
            __DIR__ . "/../../../lang/{$lang}.php";

        require
            __DIR__ . '/../../../views/employee/attempt.php';
    }


    public function focusLoss(): void
    {
        $employee = EmployeeAuthMiddleware::handle();
        CsrfMiddleware::handle();

        $sessionToken = $_SESSION['assessment_session_token'] ?? '';

        if (!is_string($sessionToken) || trim($sessionToken) === '') {
            response_json([
                'success' => false,
                'code' => 'assessment_session_unavailable',
                'message' => trans('assessment_session_unavailable'),
            ], 409);
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $result = $this->attemptService->recordFocusLoss(
            (int) $employee['id'],
            $sessionToken,
            $ipAddress,
            $userAgent
        );

        if (!$result['success']) {
            $errorCode = (string) ($result['code'] ?? 'focus_loss_record_failed');
            response_json([
                'success' => false,
                'code' => $errorCode,
                'message' => trans('assessment_session_unavailable'),
            ], 409);
        }

        response_json([
            'success' => true,
            'rate_limited' => (bool) ($result['rate_limited'] ?? false),
            'focus_loss_count' => (int) ($result['focus_loss_count'] ?? 0),
        ]);
    }

    private function redirect(string $path): never
    {
        header("Location: {$path}", true, 303);
        exit(0);
    }
}
