<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Repositories\AdminQuestionRepository;
use App\Services\AdminQuestionDirectoryService;
use App\Services\AdminQuestionManagementService;
use App\Services\AdminQuestionMediaService;
use App\Services\AuthorizationService;
use Throwable;

class QuestionController
{
    private AdminQuestionDirectoryService $directoryService;
    private AdminQuestionManagementService $managementService;
    private AdminQuestionRepository $repository;
    private AdminQuestionMediaService $mediaService;

    public function __construct(
        ?AdminQuestionDirectoryService $directoryService = null,
        ?AdminQuestionManagementService $managementService = null,
        ?AdminQuestionRepository $repository = null,
        ?AdminQuestionMediaService $mediaService = null
    ) {
        $this->repository = $repository ?? new AdminQuestionRepository();
        $this->directoryService = $directoryService ?? new AdminQuestionDirectoryService($this->repository);
        $this->managementService = $managementService ?? new AdminQuestionManagementService($this->repository);
        $this->mediaService = $mediaService ?? new AdminQuestionMediaService($this->repository);
    }

    public function index(): void
    {
        $admin = $this->authorizedAdmin('view_questions');
        if ($admin === null) {
            return;
        }
        $lang = $this->setLanguage();
        $canManageQuestions = AuthorizationService::can($admin, 'manage_questions');

        $results = ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 0];
        $filters = ['q' => '', 'test_id' => null, 'category_id' => null, 'difficulty' => 'all', 'status' => 'all', 'validity' => 'all', 'page' => 1];
        $tests = [];
        $categories = [];
        $errorMessage = null;

        try {
            $data = $this->directoryService->getDirectory($_GET);
            $results = $data['results'];
            $filters = $data['filters'];
            $tests = $data['tests'];
            $categories = $data['categories'];
        } catch (Throwable $e) {
            error_log('QuestionController index error: ' . $e->getMessage());
            $errorMessage = trans('temporary_question_error');
        }

        require __DIR__ . '/../../../views/admin/questions/index.php';
    }

    public function show(): void
    {
        $admin = $this->authorizedAdmin('view_questions');
        if ($admin === null) {
            return;
        }
        $this->setLanguage();
        $canManageQuestions = AuthorizationService::can($admin, 'manage_questions');

        try {
            $data = $this->directoryService->getQuestionDetails($_GET['id'] ?? null);
        } catch (Throwable $e) {
            error_log('QuestionController show error: ' . $e->getMessage());
            $this->render500();
            return;
        }

        if (!$data['success']) {
            if (in_array($data['code'], ['INVALID_ID', 'NOT_FOUND'], true)) {
                $this->render404();
                return;
            }
            $this->render500();
            return;
        }

        $question = $data['question'];
        $editToken = $canManageQuestions
            ? $this->managementService->issueEditToken((int)$admin['id'], (int)$question['id'], $question)
            : null;
        $mediaActionToken = $canManageQuestions && $question['archived_at'] === null
            ? $this->mediaService->issueActionToken((int)$admin['id'], (int)$question['id'], $question)
            : null;
        require __DIR__ . '/../../../views/admin/questions/show.php';
    }

    public function create(): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        $this->setLanguage();
        $tests = $this->repository->getTestsForFilter();
        $categories = $this->repository->getCategoriesForFilter();
        $errors = [];
        $old = $this->defaultQuestionOld($_GET['test_id'] ?? null);
        require __DIR__ . '/../../../views/admin/questions/create.php';
    }

    public function store(): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        if (!CsrfMiddleware::verify()) {
            $this->render403();
            return;
        }

        $result = $this->managementService->createQuestion($_POST, (int)$admin['id']);
        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans('question_created_successfully');
            header('Location: /admin/questions/view?id=' . (int)$result['question_id']);
            exit;
        }
        if ($result['code'] === 'VALIDATION_ERROR') {
            http_response_code(200);
            $this->setLanguage();
            $tests = $this->repository->getTestsForFilter();
            $categories = $this->repository->getCategoriesForFilter();
            $errors = $result['errors'] ?? [];
            $old = $result['old'] ?? $this->defaultQuestionOld(null);
            require __DIR__ . '/../../../views/admin/questions/create.php';
            return;
        }
        $this->render500();
    }

    public function edit(): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        $this->setLanguage();
        $id = $this->directoryService->parsePositiveInt($_GET['id'] ?? null);
        if ($id === null) {
            $this->render404();
            return;
        }
        $question = $this->repository->findEditableById($id);
        if ($question === null) {
            $this->render404();
            return;
        }
        if ($question['archived_at'] !== null) {
            $_SESSION['flash_error'] = trans('restore_question_before_editing');
            header('Location: /admin/questions/view?id=' . $id);
            exit;
        }

        $tests = $this->repository->getTestsForFilter();
        $categories = $this->repository->getCategoriesForFilter((int)$question['test_id']);
        $editToken = $this->managementService->issueEditToken((int)$admin['id'], $id, $question);
        $errors = [];
        $old = $this->questionToOld($question);
        require __DIR__ . '/../../../views/admin/questions/edit.php';
    }

    public function update(): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        if (!CsrfMiddleware::verify()) {
            $this->render403();
            return;
        }
        $id = $this->directoryService->parsePositiveInt($_POST['id'] ?? null);
        if ($id === null) {
            $this->render404();
            return;
        }
        $editToken = is_string($_POST['edit_token'] ?? null) ? trim($_POST['edit_token']) : '';
        $result = $this->managementService->updateQuestion($id, $editToken, $_POST, (int)$admin['id']);

        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans('question_updated_successfully');
            header('Location: /admin/questions/view?id=' . $id);
            exit;
        }
        if ($result['code'] === 'NO_CHANGES') {
            $_SESSION['flash_info'] = trans('no_changes_made');
            header('Location: /admin/questions/view?id=' . $id);
            exit;
        }
        if ($result['code'] === 'NOT_FOUND') {
            $this->render404();
            return;
        }
        if ($result['code'] === 'ARCHIVED') {
            $_SESSION['flash_error'] = trans('restore_question_before_editing');
            header('Location: /admin/questions/view?id=' . $id);
            exit;
        }

        $this->setLanguage();
        $question = $this->repository->findEditableById($id);
        if ($question === null) {
            $this->render404();
            return;
        }
        $tests = $this->repository->getTestsForFilter();
        $categories = $this->repository->getCategoriesForFilter((int)$question['test_id']);
        $freshEditToken = $this->managementService->issueEditToken((int)$admin['id'], $id, $question);

        if (in_array($result['code'], ['VALIDATION_ERROR', 'CONFLICT', 'MEDIA_LOCKED', 'INTEGRITY_LOCK'], true)) {
            http_response_code(200);
            $editToken = $freshEditToken;
            $errors = $result['errors'] ?? [];
            $old = $result['old'] ?? $this->questionToOld($question);
            $formMessage = $result['message'] ?? null;
            require __DIR__ . '/../../../views/admin/questions/edit.php';
            return;
        }
        $this->render500();
    }

    public function media(): void
    {
        $admin = $this->authorizedAdmin('view_questions');
        if ($admin === null) {
            return;
        }

        $token = is_string($_GET['token'] ?? null) ? strtolower(trim($_GET['token'])) : '';
        $media = $this->mediaService->getStreamableMedia($token);
        if ($media === null) {
            $this->renderMediaNotFound();
            return;
        }

        $path = (string)$media['absolute_path'];
        $etag = '"' . hash('sha256', $token . ':' . (string)$media['file_size_bytes']) . '"';
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            header('Cache-Control: private, max-age=300');
            header('ETag: ' . $etag);
            return;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            $this->renderMediaNotFound();
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: ' . (string)$media['mime_type']);
        header('Content-Length: ' . (string)$media['file_size_bytes']);
        header('Content-Disposition: inline; filename="question-image.' . (string)$media['file_extension'] . '"');
        header('Cache-Control: private, max-age=300');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox");
        header('Referrer-Policy: no-referrer');

        fpassthru($handle);
        fclose($handle);
    }

    public function mediaUpload(): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        if (!CsrfMiddleware::verify()) {
            $this->render403();
            return;
        }

        $questionId = $this->directoryService->parsePositiveInt($_POST['question_id'] ?? null);
        if ($questionId === null) {
            $this->render404();
            return;
        }
        $ownerType = is_string($_POST['owner_type'] ?? null) ? trim($_POST['owner_type']) : '';
        $optionId = null;
        if ($ownerType === 'option') {
            $optionId = $this->directoryService->parsePositiveInt($_POST['option_id'] ?? null);
            if ($optionId === null) {
                $_SESSION['flash_error'] = trans('invalid_media_owner');
                $this->redirectToQuestion($questionId);
            }
        } elseif ($ownerType !== 'question') {
            $_SESSION['flash_error'] = trans('invalid_media_owner');
            $this->redirectToQuestion($questionId);
        }

        $actionToken = is_string($_POST['media_action_token'] ?? null) ? trim($_POST['media_action_token']) : '';
        $file = is_array($_FILES['image'] ?? null) ? $_FILES['image'] : [];
        $result = $this->mediaService->upload($questionId, $optionId, $actionToken, $file, (int)$admin['id']);
        $this->applyMediaResultFlash($result, true);
        if ($result['code'] === 'NOT_FOUND') {
            $this->render404();
            return;
        }
        $this->redirectToQuestion($questionId);
    }

    public function mediaRemove(): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        if (!CsrfMiddleware::verify()) {
            $this->render403();
            return;
        }

        $questionId = $this->directoryService->parsePositiveInt($_POST['question_id'] ?? null);
        if ($questionId === null) {
            $this->render404();
            return;
        }
        $ownerType = is_string($_POST['owner_type'] ?? null) ? trim($_POST['owner_type']) : '';
        $optionId = null;
        if ($ownerType === 'option') {
            $optionId = $this->directoryService->parsePositiveInt($_POST['option_id'] ?? null);
            if ($optionId === null) {
                $_SESSION['flash_error'] = trans('invalid_media_owner');
                $this->redirectToQuestion($questionId);
            }
        } elseif ($ownerType !== 'question') {
            $_SESSION['flash_error'] = trans('invalid_media_owner');
            $this->redirectToQuestion($questionId);
        }

        $actionToken = is_string($_POST['media_action_token'] ?? null) ? trim($_POST['media_action_token']) : '';
        $result = $this->mediaService->remove($questionId, $optionId, $actionToken, (int)$admin['id']);
        $this->applyMediaResultFlash($result, false);
        if ($result['code'] === 'NOT_FOUND') {
            $this->render404();
            return;
        }
        $this->redirectToQuestion($questionId);
    }

    public function archive(): void
    {
        $this->handleArchiveChange(true);
    }

    public function restore(): void
    {
        $this->handleArchiveChange(false);
    }

    public function categoryCreate(): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        $this->setLanguage();
        $tests = $this->repository->getTestsForFilter();
        $errors = [];
        $old = [
            'test_id' => is_string($_GET['test_id'] ?? null) ? $_GET['test_id'] : '',
            'name_ar' => '',
            'name_en' => '',
        ];
        require __DIR__ . '/../../../views/admin/questions/category-create.php';
    }

    public function categoryStore(): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        if (!CsrfMiddleware::verify()) {
            $this->render403();
            return;
        }
        $result = $this->managementService->createCategory($_POST, (int)$admin['id']);
        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans('question_category_created');
            header('Location: /admin/questions/create?test_id=' . (int)$result['test_id']);
            exit;
        }
        if ($result['code'] === 'VALIDATION_ERROR') {
            http_response_code(200);
            $this->setLanguage();
            $tests = $this->repository->getTestsForFilter();
            $errors = $result['errors'] ?? [];
            $old = $result['old'] ?? ['test_id' => '', 'name_ar' => '', 'name_en' => ''];
            require __DIR__ . '/../../../views/admin/questions/category-create.php';
            return;
        }
        $this->render500();
    }

    private function handleArchiveChange(bool $archive): void
    {
        $admin = $this->authorizedAdmin('manage_questions');
        if ($admin === null) {
            return;
        }
        if (!CsrfMiddleware::verify()) {
            $this->render403();
            return;
        }
        $id = $this->directoryService->parsePositiveInt($_POST['id'] ?? null);
        if ($id === null) {
            $this->render404();
            return;
        }
        $token = is_string($_POST['edit_token'] ?? null) ? trim($_POST['edit_token']) : '';
        $result = $archive
            ? $this->managementService->archiveQuestion($id, $token, (int)$admin['id'])
            : $this->managementService->restoreQuestion($id, $token, (int)$admin['id']);

        if ($result['code'] === 'SUCCESS') {
            $_SESSION['flash_success'] = trans($archive ? 'question_archived_successfully' : 'question_restored_successfully');
        } elseif ($result['code'] === 'NO_CHANGES') {
            $_SESSION['flash_info'] = trans('no_changes_made');
        } elseif (in_array($result['code'], ['CONFLICT', 'INTEGRITY_LOCK'], true)) {
            $_SESSION['flash_error'] = $result['message'] ?? trans('temporary_question_error');
        } elseif ($result['code'] === 'NOT_FOUND') {
            $this->render404();
            return;
        } else {
            $_SESSION['flash_error'] = trans('temporary_question_error');
        }
        header('Location: /admin/questions/view?id=' . $id);
        exit;
    }

    private function defaultQuestionOld(mixed $rawTestId): array
    {
        $testId = $this->directoryService->parsePositiveInt($rawTestId);
        return [
            'test_id' => $testId === null ? '' : (string)$testId,
            'category_id' => '',
            'type' => 'single_choice',
            'text_ar' => '',
            'text_en' => '',
            'difficulty' => 'medium',
            'score' => '1.00',
            'is_active' => 1,
            'correct_option' => '0',
            'options' => [
                ['text_ar' => '', 'text_en' => ''],
                ['text_ar' => '', 'text_en' => ''],
            ],
        ];
    }

    private function questionToOld(array $question): array
    {
        $correctIndex = '';
        $options = [];
        foreach (array_values($question['options'] ?? []) as $index => $option) {
            $options[] = ['text_ar' => (string)$option['text_ar'], 'text_en' => (string)$option['text_en']];
            if ((int)$option['is_correct'] === 1) {
                $correctIndex = (string)$index;
            }
        }
        return [
            'test_id' => (string)$question['test_id'],
            'category_id' => (string)$question['category_id'],
            'type' => (string)$question['type'],
            'text_ar' => (string)$question['text_ar'],
            'text_en' => (string)$question['text_en'],
            'difficulty' => (string)$question['difficulty'],
            'score' => number_format((float)$question['score'], 2, '.', ''),
            'is_active' => (int)$question['is_active'],
            'correct_option' => $correctIndex,
            'options' => $options,
        ];
    }

    private function applyMediaResultFlash(array $result, bool $upload): void
    {
        $code = (string)($result['code'] ?? 'INTERNAL_ERROR');
        if ($code === 'SUCCESS') {
            if ($upload) {
                $_SESSION['flash_success'] = trans(!empty($result['replaced']) ? 'question_image_replaced' : 'question_image_uploaded');
            } else {
                $_SESSION['flash_success'] = trans('question_image_removed');
            }
            return;
        }
        if ($code === 'NO_CHANGES') {
            $_SESSION['flash_info'] = trans('no_changes_made');
            return;
        }
        if ($code === 'ARCHIVED') {
            $_SESSION['flash_error'] = trans('restore_question_before_media');
            return;
        }
        if ($code === 'VALIDATION_ERROR' || $code === 'CONFLICT' || $code === 'INVALID_OWNER') {
            $_SESSION['flash_error'] = (string)($result['message'] ?? trans($code === 'INVALID_OWNER' ? 'invalid_media_owner' : 'temporary_question_error'));
            return;
        }
        $_SESSION['flash_error'] = trans('question_image_operation_failed');
    }

    private function redirectToQuestion(int $questionId): never
    {
        header('Location: /admin/questions/view?id=' . $questionId, true, 303);
        exit;
    }

    private function renderMediaNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo 'Media not found.';
    }

    private function setLanguage(): string
    {
        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;
        return $lang;
    }

    private function authorizedAdmin(string $permission): ?array
    {
        $admin = AdminAuthMiddleware::handle($permission);
        if ($admin === null && http_response_code() === 403) {
            $this->render403();
        }
        return $admin;
    }

    private function render403(): void
    {
        http_response_code(403);
        require __DIR__ . '/../../../views/admin/403.php';
    }

    private function render404(): void
    {
        http_response_code(404);
        $pageTitle = trans('question_not_found');
        $isAuthenticatedLayout = true;
        $admin = $_SESSION['admin_auth'] ?? null;
        ob_start();
        ?>
        <div class="admin-welcome-card" style="border-color:rgba(239,68,68,.4);">
            <h1 class="admin-welcome-title" style="color:#f87171;"><?= e(trans('question_not_found')) ?></h1>
            <a href="/admin/questions" class="nav-link" style="margin-top:1rem;"><?= e(trans('back_to_question_bank')) ?></a>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../../../views/layouts/admin.php';
    }

    private function render500(): void
    {
        http_response_code(500);
        $pageTitle = trans('temporary_error');
        $isAuthenticatedLayout = true;
        $admin = $_SESSION['admin_auth'] ?? null;
        ob_start();
        ?>
        <div class="admin-welcome-card" style="border-color:rgba(239,68,68,.4);">
            <h1 class="admin-welcome-title" style="color:#f87171;"><?= e(trans('temporary_error')) ?></h1>
            <p style="color:#94a3b8;"><?= e(trans('temporary_question_error')) ?></p>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../../../views/layouts/admin.php';
    }
}
