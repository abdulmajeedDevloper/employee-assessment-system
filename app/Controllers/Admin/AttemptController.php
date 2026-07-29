<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Repositories\AdminAttemptRepository;
use App\Services\AdminAttemptDirectoryService;
use App\Services\AdminAttemptManagementService;
use App\Services\AuthorizationService;
use Throwable;

class AttemptController
{
    private AdminAttemptRepository $repository;
    private AdminAttemptDirectoryService $directoryService;
    private AdminAttemptManagementService $managementService;

    public function __construct(
        ?AdminAttemptRepository $repository = null,
        ?AdminAttemptDirectoryService $directoryService = null,
        ?AdminAttemptManagementService $managementService = null
    ) {
        $this->repository = $repository ?? new AdminAttemptRepository();
        $this->directoryService = $directoryService ?? new AdminAttemptDirectoryService($this->repository);
        $this->managementService = $managementService ?? new AdminAttemptManagementService($this->repository);
    }

    public function index(): void
    {
        $admin = AdminAuthMiddleware::handle('view_attempts');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }
        $this->setLanguage();

        $results = ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 0];
        $statistics = [
            'total_attempts' => 0, 'in_progress_attempts' => 0, 'completed_attempts' => 0,
            'timed_out_attempts' => 0, 'cancelled_attempts' => 0, 'passed_attempts' => 0,
            'failed_attempts' => 0, 'expired_in_progress_attempts' => 0,
        ];
        $filters = [
            'q' => '', 'status' => 'all', 'outcome' => 'all', 'attention' => 'all',
            'employee_id' => null, 'test_id' => null, 'assignment_id' => null,
            'started_from_input' => '', 'started_until_input' => '', 'page' => 1,
        ];
        $employees = [];
        $tests = [];
        $errorMessage = null;

        try {
            $data = $this->directoryService->getDirectory($_GET);
            $results = $data['results'];
            $statistics = $data['statistics'];
            $filters = $data['filters'];
            $employees = $data['employees'];
            $tests = $data['tests'];
        } catch (Throwable $e) {
            error_log('AttemptController index error: ' . $e->getMessage());
            $errorMessage = trans('temporary_attempt_error');
        }

        require __DIR__ . '/../../../views/admin/attempts/index.php';
    }

    public function show(): void
    {
        $admin = AdminAuthMiddleware::handle('view_attempts');
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return;
        }
        $this->setLanguage();

        try {
            $details = $this->directoryService->getDetails($_GET['id'] ?? null);
        } catch (Throwable $e) {
            error_log('AttemptController show error: ' . $e->getMessage());
            $this->render500();
            return;
        }
        if (!($details['success'] ?? false)) {
            $this->render404();
            return;
        }

        $attempt = $details['attempt'];
        $answers = $details['answers'];
        $securityEvents = $details['security_events'];
        $sessions = $details['sessions'];
        $auditHistory = $details['audit_history'];
        $actionTokens = [];

        $attemptId = (int)$attempt['id'];
        $isExpiredInProgress = (int)$attempt['is_expired_in_progress'] === 1;
        if (AuthorizationService::can($admin, 'cancel_attempt')
            && (string)$attempt['status'] === 'IN_PROGRESS'
            && !$isExpiredInProgress
        ) {
            $actionTokens['cancel'] = $this->managementService->issueActionToken((int)$admin['id'], $attemptId, 'cancel', $attempt);
        }
        if (AuthorizationService::can($admin, 'reset_attempt')
            && (string)$attempt['status'] === 'IN_PROGRESS'
            && $isExpiredInProgress
        ) {
            $actionTokens['finalize'] = $this->managementService->issueActionToken((int)$admin['id'], $attemptId, 'finalize', $attempt);
        }
        if (AuthorizationService::can($admin, 'authorize_retake')
            && in_array((string)$attempt['status'], ['COMPLETED', 'TIMED_OUT', 'CANCELLED'], true)
            && (int)$attempt['attempt_number'] === (int)$attempt['latest_attempt_number']
            && (int)$attempt['pending_retake_count'] === 0
        ) {
            $actionTokens['retake'] = $this->managementService->issueActionToken((int)$admin['id'], $attemptId, 'retake', $attempt);
        }
        if (AuthorizationService::can($admin, 'manage_attempt_notes')) {
            $actionTokens['notes'] = $this->managementService->issueActionToken((int)$admin['id'], $attemptId, 'notes', $attempt);
        }

        require __DIR__ . '/../../../views/admin/attempts/show.php';
    }

    public function cancel(): void
    {
        $admin = $this->authorizePost('cancel_attempt');
        if ($admin === null) {
            return;
        }
        $attemptId = $this->postAttemptId();
        if ($attemptId === null) {
            $this->render404();
            return;
        }

        $result = $this->managementService->cancelAttempt(
            $attemptId,
            $this->postToken(),
            $_POST,
            (int)$admin['id']
        );
        $this->redirectForResult($attemptId, $result, 'attempt_cancelled_successfully');
    }

    public function authorizeRetake(): void
    {
        $admin = $this->authorizePost('authorize_retake');
        if ($admin === null) {
            return;
        }
        $attemptId = $this->postAttemptId();
        if ($attemptId === null) {
            $this->render404();
            return;
        }

        $result = $this->managementService->authorizeRetake(
            $attemptId,
            $this->postToken(),
            $_POST,
            (int)$admin['id']
        );
        $this->redirectForResult($attemptId, $result, 'attempt_retake_authorized_successfully');
    }

    public function updateNotes(): void
    {
        $admin = $this->authorizePost('manage_attempt_notes');
        if ($admin === null) {
            return;
        }
        $attemptId = $this->postAttemptId();
        if ($attemptId === null) {
            $this->render404();
            return;
        }

        $result = $this->managementService->updateSupervisorNotes(
            $attemptId,
            $this->postToken(),
            $_POST,
            (int)$admin['id']
        );
        $this->redirectForResult($attemptId, $result, 'attempt_notes_updated_successfully');
    }

    public function finalizeExpired(): void
    {
        $admin = $this->authorizePost('reset_attempt');
        if ($admin === null) {
            return;
        }
        $attemptId = $this->postAttemptId();
        if ($attemptId === null) {
            $this->render404();
            return;
        }

        $result = $this->managementService->finalizeExpiredAttempt(
            $attemptId,
            $this->postToken(),
            (int)$admin['id']
        );
        $this->redirectForResult($attemptId, $result, 'attempt_finalized_successfully');
    }

    private function authorizePost(string $permission): ?array
    {
        $admin = AdminAuthMiddleware::handle($permission);
        if ($admin === null) {
            if (http_response_code() === 403) {
                $this->render403();
            }
            return null;
        }
        if (!CsrfMiddleware::verify()) {
            $this->render403();
            return null;
        }
        $this->setLanguage();
        return $admin;
    }

    private function postAttemptId(): ?int
    {
        return $this->directoryService->parsePositiveInt($_POST['attempt_id'] ?? null);
    }

    private function postToken(): string
    {
        return is_string($_POST['action_token'] ?? null) ? trim((string)$_POST['action_token']) : '';
    }

    private function redirectForResult(int $attemptId, array $result, string $successKey): void
    {
        $code = (string)($result['code'] ?? 'INTERNAL_ERROR');
        if ($code === 'SUCCESS') {
            $_SESSION['flash_success'] = trans($successKey);
        } elseif ($code === 'NO_CHANGES') {
            $_SESSION['flash_info'] = trans('no_changes_made');
        } elseif ($code === 'NOT_FOUND') {
            $this->render404();
            return;
        } elseif ($code === 'VALIDATION_ERROR') {
            $errors = $result['errors'] ?? [];
            $_SESSION['flash_error'] = is_array($errors) && $errors !== []
                ? (string)reset($errors)
                : trans('attempt_action_validation_failed');
        } elseif (isset($result['message']) && is_string($result['message'])) {
            $_SESSION['flash_error'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = trans('temporary_attempt_error');
        }

        header('Location: /admin/attempts/view?id=' . $attemptId, true, 303);
        exit;
    }

    private function setLanguage(): void
    {
        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;
    }

    private function render403(): void
    {
        http_response_code(403);
        require __DIR__ . '/../../../views/admin/403.php';
    }


    private function render500(): void
    {
        http_response_code(500);
        $pageTitle = trans('temporary_error');
        $isAuthenticatedLayout = true;
        $admin = $_SESSION['admin_auth'] ?? null;
        ob_start();
        ?>
        <div class="admin-welcome-card" style="border-color:rgba(239,68,68,.4)">
            <h1 class="admin-welcome-title" style="color:#f87171"><?= e(trans('temporary_error')) ?></h1>
            <p style="color:#94a3b8"><?= e(trans('temporary_attempt_error')) ?></p>
            <a href="/admin/attempts" class="nav-link"><?= e(trans('back_to_attempts')) ?></a>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../../../views/layouts/admin.php';
    }

    private function render404(): void
    {
        http_response_code(404);
        $pageTitle = trans('attempt_not_found_admin');
        $isAuthenticatedLayout = true;
        $admin = $_SESSION['admin_auth'] ?? null;
        ob_start();
        ?>
        <div class="admin-welcome-card" style="border-color:rgba(239,68,68,.4)">
            <h1 class="admin-welcome-title" style="color:#f87171"><?= e(trans('attempt_not_found_admin')) ?></h1>
            <p style="color:#94a3b8"><?= e(trans('attempt_not_found_admin_description')) ?></p>
            <a href="/admin/attempts" class="nav-link"><?= e(trans('back_to_attempts')) ?></a>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../../../views/layouts/admin.php';
    }
}
