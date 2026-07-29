<?php
use App\Middleware\CsrfMiddleware;

$pageTitle = trans('edit_assessment');
$isAuthenticatedLayout = true;
ob_start();

$currentLang = $_SESSION['lang'] ?? 'ar';
$errors = $errors ?? [];
$old = $old ?? [];
$test = $test ?? [];
$attemptCounts = $attemptCounts ?? ['total_attempts' => 0, 'in_progress_attempts' => 0];
$editToken = $editToken ?? '';
$conflictMessage = $conflictMessage ?? null;

$totalAttempts = (int)($attemptCounts['total_attempts'] ?? 0);
$inProgressAttempts = (int)($attemptCounts['in_progress_attempts'] ?? 0);
$isCoreLocked = ($totalAttempts > 0);
$isNavLocked = ($inProgressAttempts > 0);

$val = function (string $key, $default = '') use ($old, $test) {
    if (isset($old[$key])) {
        return e((string)$old[$key]);
    }
    return isset($test[$key]) ? e((string)$test[$key]) : e((string)$default);
};

$isChecked = function (string $key, $default = 0) use ($old, $test) {
    if (isset($old[$key])) {
        return (int)$old[$key] === 1 ? 'checked' : '';
    }
    $v = $test[$key] ?? $default;
    return (int)$v === 1 ? 'checked' : '';
};
?>

<style>
    .form-container {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .form-header-card {
        background: var(--card-bg, rgba(30, 41, 59, 0.85));
        border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
        border-radius: 12px;
        padding: 1.5rem 2rem;
        backdrop-filter: blur(12px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .form-section-card {
        background: var(--card-bg, rgba(30, 41, 59, 0.85));
        border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
        border-radius: 12px;
        padding: 1.75rem 2rem;
        backdrop-filter: blur(12px);
    }

    .form-section-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: #60a5fa;
        margin-top: 0;
        margin-bottom: 1.25rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .form-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }

    @media (max-width: 768px) {
        .form-grid-2 {
            grid-template-columns: 1fr;
        }
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        margin-bottom: 1.1rem;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #cbd5e1;
    }

    .form-label .required-star {
        color: #f87171;
        margin-inline-start: 0.2rem;
    }

    .input-ltr {
        direction: ltr !important;
        text-align: left !important;
    }

    .input-readonly {
        background: rgba(15, 23, 42, 0.6) !important;
        color: #94a3b8 !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
        cursor: not-allowed !important;
    }

    .error-text {
        color: #f87171;
        font-size: 0.8rem;
        margin-top: 0.2rem;
    }

    .lock-text {
        color: #fbbf24;
        font-size: 0.78rem;
        margin-top: 0.2rem;
    }

    .checkbox-group {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        margin-bottom: 1rem;
    }

    .checkbox-group input[type="checkbox"] {
        width: 1.1rem;
        height: 1.1rem;
        margin-top: 0.15rem;
        cursor: pointer;
    }

    .checkbox-group input[type="checkbox"]:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .checkbox-label {
        font-size: 0.9rem;
        color: #f8fafc;
        cursor: pointer;
    }

    .warning-box {
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.3);
        color: #fbbf24;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        margin-top: 0.4rem;
        line-height: 1.5;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.65rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-active {
        background: rgba(34, 197, 94, 0.15);
        color: #4ade80;
        border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .status-inactive {
        background: rgba(148, 163, 184, 0.15);
        color: #cbd5e1;
        border: 1px solid rgba(148, 163, 184, 0.3);
    }

    .technical-value {
        unicode-bidi: isolate;
        font-family: monospace, sans-serif;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-top: 1rem;
    }
</style>

<div class="form-container">
    <!-- Header Card -->
    <div class="form-header-card">
        <div>
            <h1 class="admin-welcome-title" style="margin: 0; font-size: 1.5rem;"><?= e(trans('edit_assessment')) ?></h1>
            <div style="margin-top: 0.4rem; font-size: 0.85rem; color: #94a3b8; display: flex; gap: 0.75rem; align-items: center;">
                <span>
                    <?= e(trans('status')) ?>:
                    <?php if ((int)($test['is_active'] ?? 0) === 1): ?>
                        <span class="status-badge status-active"><?= e(trans('active')) ?></span>
                    <?php else: ?>
                        <span class="status-badge status-inactive"><?= e(trans('inactive')) ?></span>
                    <?php endif; ?>
                </span>
                <span>
                    <?= e(trans('attempts_count')) ?>: <bdi dir="ltr" class="technical-value"><?= $totalAttempts ?></bdi>
                </span>
            </div>
        </div>
        <div>
            <a href="/admin/tests/view?id=<?= (int)$test['id'] ?>" class="nav-link">&larr; <?= e(trans('back_to_test_details')) ?></a>
        </div>
    </div>

    <!-- Conflict Alert -->
    <?php if (!empty($conflictMessage)): ?>
        <div class="alert-box alert-warning" style="background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.3); color: #fbbf24;">
            <?= e($conflictMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Lock Notice Bar -->
    <?php if ($isCoreLocked || $isNavLocked): ?>
        <div class="warning-box" style="margin-top: 0;">
            <strong><?= e(trans('field_locked_notice')) ?></strong>
        </div>
    <?php endif; ?>

    <form action="/admin/tests/edit" method="POST" id="edit-assessment-form" novalidate>
        <?= CsrfMiddleware::generateField() ?>
        <input type="hidden" name="id" value="<?= (int)$test['id'] ?>">
        <input type="hidden" name="edit_token" value="<?= e($editToken) ?>">

        <!-- Section 1: Basic Information -->
        <div class="form-section-card">
            <h2 class="form-section-title"><?= e(trans('basic_info')) ?></h2>
            <div class="form-grid-2">
                <!-- Assessment Code -->
                <div class="form-group full-width">
                    <label for="code" class="form-label">
                        <?= e(trans('test_code')) ?> <span class="required-star">*</span>
                    </label>
                    <input type="text" id="code" name="code" value="<?= $val('code') ?>"
                           maxlength="64" required
                           class="input-field input-ltr <?= $isCoreLocked ? 'input-readonly' : '' ?>"
                           <?= $isCoreLocked ? 'readonly' : '' ?>
                           <?= isset($errors['code']) ? 'aria-invalid="true" aria-describedby="code-error"' : '' ?>>
                    <?php if (isset($errors['code'])): ?>
                        <div id="code-error" class="error-text"><?= e($errors['code']) ?></div>
                    <?php elseif ($isCoreLocked): ?>
                        <div class="lock-text"><?= e(trans('field_locked_attempts_exist')) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Arabic Name -->
                <div class="form-group">
                    <label for="name_ar" class="form-label">
                        <?= e(trans('name_ar_label')) ?> <span class="required-star">*</span>
                    </label>
                    <input type="text" id="name_ar" name="name_ar" value="<?= $val('name_ar') ?>"
                           maxlength="255" required class="input-field"
                           <?= isset($errors['name_ar']) ? 'aria-invalid="true" aria-describedby="name_ar-error"' : '' ?>>
                    <?php if (isset($errors['name_ar'])): ?>
                        <div id="name_ar-error" class="error-text"><?= e($errors['name_ar']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- English Name -->
                <div class="form-group">
                    <label for="name_en" class="form-label">
                        <?= e(trans('name_en_label')) ?> <span class="required-star">*</span>
                    </label>
                    <input type="text" id="name_en" name="name_en" value="<?= $val('name_en') ?>"
                           maxlength="255" required class="input-field input-ltr"
                           <?= isset($errors['name_en']) ? 'aria-invalid="true" aria-describedby="name_en-error"' : '' ?>>
                    <?php if (isset($errors['name_en'])): ?>
                        <div id="name_en-error" class="error-text"><?= e($errors['name_en']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Arabic Description -->
                <div class="form-group full-width">
                    <label for="description_ar" class="form-label">
                        <?= e(trans('description_ar_label')) ?>
                    </label>
                    <textarea id="description_ar" name="description_ar" rows="3"
                              maxlength="5000" class="input-field"><?= $val('description_ar') ?></textarea>
                    <?php if (isset($errors['description_ar'])): ?>
                        <div class="error-text"><?= e($errors['description_ar']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- English Description -->
                <div class="form-group full-width">
                    <label for="description_en" class="form-label">
                        <?= e(trans('description_en_label')) ?>
                    </label>
                    <textarea id="description_en" name="description_en" rows="3"
                              maxlength="5000" class="input-field input-ltr"><?= $val('description_en') ?></textarea>
                    <?php if (isset($errors['description_en'])): ?>
                        <div class="error-text"><?= e($errors['description_en']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section 2: Employee Instructions -->
        <div class="form-section-card" style="margin-top: 1.5rem;">
            <h2 class="form-section-title"><?= e(trans('employee_instructions')) ?></h2>
            <div class="form-grid-2">
                <!-- Arabic Instructions -->
                <div class="form-group full-width">
                    <label for="instructions_ar" class="form-label">
                        <?= e(trans('instructions_ar_label')) ?>
                    </label>
                    <textarea id="instructions_ar" name="instructions_ar" rows="4"
                              maxlength="10000" class="input-field"><?= $val('instructions_ar') ?></textarea>
                    <?php if (isset($errors['instructions_ar'])): ?>
                        <div class="error-text"><?= e($errors['instructions_ar']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- English Instructions -->
                <div class="form-group full-width">
                    <label for="instructions_en" class="form-label">
                        <?= e(trans('instructions_en_label')) ?>
                    </label>
                    <textarea id="instructions_en" name="instructions_en" rows="4"
                              maxlength="10000" class="input-field input-ltr"><?= $val('instructions_en') ?></textarea>
                    <?php if (isset($errors['instructions_en'])): ?>
                        <div class="error-text"><?= e($errors['instructions_en']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section 3: Assessment Configuration -->
        <div class="form-section-card" style="margin-top: 1.5rem;">
            <h2 class="form-section-title"><?= e(trans('assessment_configuration')) ?></h2>
            <div class="form-grid-2">
                <!-- Duration Minutes -->
                <div class="form-group">
                    <label for="duration_minutes" class="form-label">
                        <?= e(trans('duration_minutes_label')) ?> <span class="required-star">*</span>
                    </label>
                    <input type="number" id="duration_minutes" name="duration_minutes"
                           value="<?= $val('duration_minutes', 60) ?>"
                           min="1" max="1440" required
                           class="input-field input-ltr <?= $isCoreLocked ? 'input-readonly' : '' ?>"
                           <?= $isCoreLocked ? 'readonly' : '' ?>
                           <?= isset($errors['duration_minutes']) ? 'aria-invalid="true" aria-describedby="duration_minutes-error"' : '' ?>>
                    <?php if (isset($errors['duration_minutes'])): ?>
                        <div id="duration_minutes-error" class="error-text"><?= e($errors['duration_minutes']) ?></div>
                    <?php elseif ($isCoreLocked): ?>
                        <div class="lock-text"><?= e(trans('field_locked_attempts_exist')) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Question Count -->
                <div class="form-group">
                    <label for="question_count" class="form-label">
                        <?= e(trans('questions_per_attempt')) ?> <span class="required-star">*</span>
                    </label>
                    <input type="number" id="question_count" name="question_count"
                           value="<?= $val('question_count', 10) ?>"
                           min="1" max="500" required
                           class="input-field input-ltr <?= $isCoreLocked ? 'input-readonly' : '' ?>"
                           <?= $isCoreLocked ? 'readonly' : '' ?>
                           <?= isset($errors['question_count']) ? 'aria-invalid="true" aria-describedby="question_count-error"' : '' ?>>
                    <?php if (isset($errors['question_count'])): ?>
                        <div id="question_count-error" class="error-text"><?= e($errors['question_count']) ?></div>
                    <?php elseif ($isCoreLocked): ?>
                        <div class="lock-text"><?= e(trans('field_locked_attempts_exist')) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Passing Percentage -->
                <div class="form-group">
                    <label for="passing_percentage" class="form-label">
                        <?= e(trans('passing_score_label')) ?> (%) <span class="required-star">*</span>
                    </label>
                    <input type="text" id="passing_percentage" name="passing_percentage"
                           value="<?= $val('passing_percentage', '60.00') ?>"
                           required
                           class="input-field input-ltr <?= $isCoreLocked ? 'input-readonly' : '' ?>" placeholder="60.00"
                           <?= $isCoreLocked ? 'readonly' : '' ?>
                           <?= isset($errors['passing_percentage']) ? 'aria-invalid="true" aria-describedby="passing_percentage-error"' : '' ?>>
                    <?php if (isset($errors['passing_percentage'])): ?>
                        <div id="passing_percentage-error" class="error-text"><?= e($errors['passing_percentage']) ?></div>
                    <?php elseif ($isCoreLocked): ?>
                        <div class="lock-text"><?= e(trans('field_locked_attempts_exist')) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Display Order -->
                <div class="form-group">
                    <label for="display_order" class="form-label">
                        <?= e(trans('display_order_label')) ?> <span class="required-star">*</span>
                    </label>
                    <input type="number" id="display_order" name="display_order"
                           value="<?= $val('display_order', 1) ?>"
                           min="1" max="9999" required class="input-field input-ltr"
                           <?= isset($errors['display_order']) ? 'aria-invalid="true" aria-describedby="display_order-error"' : '' ?>>
                    <?php if (isset($errors['display_order'])): ?>
                        <div id="display_order-error" class="error-text"><?= e($errors['display_order']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section 4: Attempt Behavior -->
        <div class="form-section-card" style="margin-top: 1.5rem;">
            <h2 class="form-section-title"><?= e(trans('attempt_behavior')) ?></h2>

            <div class="checkbox-group">
                <input type="checkbox" id="randomize_questions" name="randomize_questions" value="1" <?= $isChecked('randomize_questions', 1) ?>>
                <label for="randomize_questions" class="checkbox-label"><?= e(trans('randomize_questions')) ?></label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="randomize_options" name="randomize_options" value="1" <?= $isChecked('randomize_options', 1) ?>>
                <label for="randomize_options" class="checkbox-label"><?= e(trans('randomize_options')) ?></label>
            </div>

            <div class="checkbox-group">
                <?php if ($isNavLocked): ?>
                    <input type="checkbox" id="allow_back_navigation" value="1" <?= $isChecked('allow_back_navigation', 1) ?> disabled>
                    <input type="hidden" name="allow_back_navigation" value="<?= (int)($test['allow_back_navigation'] ?? 1) ?>">
                    <label for="allow_back_navigation" class="checkbox-label" style="opacity: 0.7; cursor: not-allowed;">
                        <?= e(trans('allow_back_nav')) ?> (<?= e(trans('field_locked_in_progress')) ?>)
                    </label>
                <?php else: ?>
                    <input type="checkbox" id="allow_back_navigation" name="allow_back_navigation" value="1" <?= $isChecked('allow_back_navigation', 1) ?>>
                    <label for="allow_back_navigation" class="checkbox-label"><?= e(trans('allow_back_nav')) ?></label>
                <?php endif; ?>
            </div>
            <?php if (isset($errors['allow_back_navigation'])): ?>
                <div class="error-text" style="margin-bottom: 0.8rem;"><?= e($errors['allow_back_navigation']) ?></div>
            <?php endif; ?>

            <div class="checkbox-group" style="margin-bottom: 0.5rem;">
                <input type="checkbox" id="show_result_to_employee" name="show_result_to_employee" value="1" <?= $isChecked('show_result_to_employee', 0) ?>>
                <label for="show_result_to_employee" class="checkbox-label"><?= e(trans('show_result_to_emp')) ?></label>
            </div>

            <div class="warning-box">
                <strong><?= e(trans('result_visibility_warning')) ?></strong>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-size: 1rem;">
                <?= e(trans('update_assessment')) ?>
            </button>
            <a href="/admin/tests/view?id=<?= (int)$test['id'] ?>" class="btn btn-secondary" style="padding: 0.75rem 1.5rem; text-decoration: none;">
                <?= e(trans('cancel')) ?>
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
?>
