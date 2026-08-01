<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-tests-edit';


$pageTitle = trans('edit_assessment');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$test = is_array($test ?? null) ? $test : [];
$attemptCounts = is_array($attemptCounts ?? null)
    ? $attemptCounts
    : ['total_attempts' => 0, 'in_progress_attempts' => 0];

$editToken = (string)($editToken ?? '');
$conflictMessage = $conflictMessage ?? null;

$testId = (int)($test['id'] ?? 0);
$totalAttempts = (int)($attemptCounts['total_attempts'] ?? 0);
$inProgressAttempts = (int)($attemptCounts['in_progress_attempts'] ?? 0);
$isCoreLocked = $totalAttempts > 0;
$isNavLocked = $inProgressAttempts > 0;
$isActive = (int)($test['is_active'] ?? 0) === 1;

$value = static function (string $key, mixed $default = '') use ($old, $test): string {
    if (array_key_exists($key, $old)) {
        return e((string)$old[$key]);
    }

    return e((string)($test[$key] ?? $default));
};

$isChecked = static function (string $key, int $default = 0) use ($old, $test): string {
    if (array_key_exists($key, $old)) {
        return (int)$old[$key] === 1 ? 'checked' : '';
    }

    return (int)($test[$key] ?? $default) === 1 ? 'checked' : '';
};

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'test' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'basic' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="19" cy="18" r="2"/></svg>',
        'instructions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>',
        'config' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.38.3.73.6 1 .3.27.68.4 1.1.4H21v4h-.09a1.7 1.7 0 0 0-1.1.4c-.27.3-.47.64-.6 1Z"/></svg>',
        'behavior' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h10M4 12h16M4 17h7"/><circle cx="18" cy="7" r="2"/><circle cx="14" cy="17" r="2"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/></svg>',
        'cancel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'questions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.6 2.1c-.9.5-1.4 1-1.4 2M12 17h.01"/></svg>',
        'percent' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 18 12-12"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>',
        'order' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 5h.01M4 11h.01M4 17h.01"/></svg>',
        'attempts' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 22h8M12 18v4M6.5 11h2l1.4-3 2.3 6 1.6-3H18"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="test-edit-page">
    <section class="test-edit-hero">
        <div class="test-edit-heading">
            <span class="test-edit-kicker">
                <?= $icon('test') ?>
                <?= e($text('تحديث إعدادات الاختبار', 'Update Assessment Settings')) ?>
            </span>

            <h1 class="test-edit-title"><?= e(trans('edit_assessment')) ?></h1>

            <p class="test-edit-subtitle">
                <?= e($text(
                    'عدّل بيانات الاختبار وتعليماته وإعداداته المتاحة، مع الالتزام بالقيود المفروضة على الحقول المرتبطة بالمحاولات.',
                    'Update the assessment information, instructions, and available settings while respecting restrictions on fields linked to attempts.'
                )) ?>
            </p>

            <div class="test-edit-meta">
                <span class="meta-badge <?= $isActive ? 'active' : 'inactive' ?>">
                    <?= e($isActive ? trans('active') : trans('inactive')) ?>
                </span>

                <span class="meta-badge">
                    <?= e(trans('attempts_count')) ?>:
                    <bdi dir="ltr"><?= $totalAttempts ?></bdi>
                </span>

                <span class="meta-badge <?= $inProgressAttempts > 0 ? 'warning' : '' ?>">
                    <?= e($text('محاولات جارية', 'In-progress attempts')) ?>:
                    <bdi dir="ltr"><?= $inProgressAttempts ?></bdi>
                </span>

                <?php if (!empty($test['code'])): ?>
                    <span class="meta-badge"><bdi dir="ltr"><?= e((string)$test['code']) ?></bdi></span>
                <?php endif; ?>
            </div>
        </div>

        <a href="/admin/tests/view?id=<?= $testId ?>" class="test-edit-btn">
            <?= $icon('back') ?>
            <?= e(trans('back_to_test_details')) ?>
        </a>
    </section>

    <?php if (!empty($conflictMessage)): ?>
        <div class="test-edit-alert"><?= e((string)$conflictMessage) ?></div>
    <?php endif; ?>

    <?php if ($isCoreLocked || $isNavLocked): ?>
        <div class="notice-strip">
            <?= $icon('lock') ?>
            <span>
                <strong><?= e(trans('field_locked_notice')) ?></strong>
                <?php if ($isCoreLocked): ?>
                    <?= e($text(
                        ' توجد محاولات مرتبطة بهذا الاختبار، لذلك تم قفل الرمز والمدة وعدد الأسئلة ونسبة الاجتياز.',
                        ' Existing attempts are linked to this assessment, so the code, duration, question count, and passing score are locked.'
                    )) ?>
                <?php endif; ?>
                <?php if ($isNavLocked): ?>
                    <?= e($text(
                        ' كما تم قفل خيار الرجوع بسبب وجود محاولة جارية.',
                        ' Back navigation is also locked because an attempt is currently in progress.'
                    )) ?>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <form action="/admin/tests/edit" method="POST" id="edit-assessment-form" novalidate class="test-edit-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $testId ?>">
        <input type="hidden" name="edit_token" value="<?= e($editToken) ?>">

        <section class="test-section">
            <div class="test-section-head">
                <span class="section-icon"><?= $icon('basic') ?></span>
                <div>
                    <h2><?= e(trans('basic_info')) ?></h2>
                    <p><?= e($text('حدّث اسم الاختبار ووصفه باللغتين العربية والإنجليزية.', 'Update the assessment name and description in Arabic and English.')) ?></p>
                </div>
            </div>

            <div class="test-section-body">
                <div class="test-form-grid">
                    <div class="test-field full">
                        <label for="code" class="test-label">
                            <?= e(trans('test_code')) ?><span class="required-star">*</span>
                        </label>
                        <input
                            type="text"
                            id="code"
                            name="code"
                            value="<?= $value('code') ?>"
                            maxlength="64"
                            required
                            class="test-control ltr"
                            <?= $isCoreLocked ? 'readonly' : '' ?>
                            autocomplete="off"
                            <?= isset($errors['code']) ? 'aria-invalid="true" aria-describedby="code-error"' : '' ?>
                        >
                        <?php if (isset($errors['code'])): ?>
                            <span id="code-error" class="test-error"><?= e((string)$errors['code']) ?></span>
                        <?php elseif ($isCoreLocked): ?>
                            <span class="lock-help">
                                <?= $icon('lock') ?>
                                <?= e(trans('field_locked_attempts_exist')) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="test-field">
                        <label for="name_ar" class="test-label">
                            <?= e(trans('name_ar_label')) ?><span class="required-star">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_ar"
                            name="name_ar"
                            value="<?= $value('name_ar') ?>"
                            maxlength="255"
                            required
                            class="test-control"
                            <?= isset($errors['name_ar']) ? 'aria-invalid="true" aria-describedby="name_ar-error"' : '' ?>
                        >
                        <?php if (isset($errors['name_ar'])): ?>
                            <span id="name_ar-error" class="test-error"><?= e((string)$errors['name_ar']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="test-field">
                        <label for="name_en" class="test-label">
                            <?= e(trans('name_en_label')) ?><span class="required-star">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_en"
                            name="name_en"
                            value="<?= $value('name_en') ?>"
                            maxlength="255"
                            required
                            class="test-control ltr"
                            <?= isset($errors['name_en']) ? 'aria-invalid="true" aria-describedby="name_en-error"' : '' ?>
                        >
                        <?php if (isset($errors['name_en'])): ?>
                            <span id="name_en-error" class="test-error"><?= e((string)$errors['name_en']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="test-field full">
                        <label for="description_ar" class="test-label"><?= e(trans('description_ar_label')) ?></label>
                        <textarea
                            id="description_ar"
                            name="description_ar"
                            rows="4"
                            maxlength="5000"
                            class="test-control"
                        ><?= $value('description_ar') ?></textarea>
                        <?php if (isset($errors['description_ar'])): ?>
                            <span class="test-error"><?= e((string)$errors['description_ar']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="test-field full">
                        <label for="description_en" class="test-label"><?= e(trans('description_en_label')) ?></label>
                        <textarea
                            id="description_en"
                            name="description_en"
                            rows="4"
                            maxlength="5000"
                            class="test-control ltr"
                        ><?= $value('description_en') ?></textarea>
                        <?php if (isset($errors['description_en'])): ?>
                            <span class="test-error"><?= e((string)$errors['description_en']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="test-section">
            <div class="test-section-head">
                <span class="section-icon"><?= $icon('instructions') ?></span>
                <div>
                    <h2><?= e(trans('employee_instructions')) ?></h2>
                    <p><?= e($text('حدّث التعليمات التي تظهر للموظف قبل بدء الاختبار.', 'Update the instructions shown to the employee before starting the assessment.')) ?></p>
                </div>
            </div>

            <div class="test-section-body">
                <div class="test-form-grid">
                    <div class="test-field full">
                        <label for="instructions_ar" class="test-label"><?= e(trans('instructions_ar_label')) ?></label>
                        <textarea
                            id="instructions_ar"
                            name="instructions_ar"
                            rows="5"
                            maxlength="10000"
                            class="test-control"
                        ><?= $value('instructions_ar') ?></textarea>
                        <?php if (isset($errors['instructions_ar'])): ?>
                            <span class="test-error"><?= e((string)$errors['instructions_ar']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="test-field full">
                        <label for="instructions_en" class="test-label"><?= e(trans('instructions_en_label')) ?></label>
                        <textarea
                            id="instructions_en"
                            name="instructions_en"
                            rows="5"
                            maxlength="10000"
                            class="test-control ltr"
                        ><?= $value('instructions_en') ?></textarea>
                        <?php if (isset($errors['instructions_en'])): ?>
                            <span class="test-error"><?= e((string)$errors['instructions_en']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="test-section">
            <div class="test-section-head">
                <span class="section-icon"><?= $icon('config') ?></span>
                <div>
                    <h2><?= e(trans('assessment_configuration')) ?></h2>
                    <p><?= e($text('راجع مدة المحاولة وعدد الأسئلة ونسبة النجاح وترتيب العرض.', 'Review the attempt duration, question count, passing threshold, and display order.')) ?></p>
                </div>
            </div>

            <div class="test-section-body">
                <div class="config-grid">
                    <div class="config-card">
                        <div class="config-card-head">
                            <span class="metric-icon"><?= $icon('clock') ?></span>
                            <label for="duration_minutes" class="test-label">
                                <?= e(trans('duration_minutes_label')) ?><span class="required-star">*</span>
                            </label>
                        </div>
                        <input
                            type="number"
                            id="duration_minutes"
                            name="duration_minutes"
                            value="<?= $value('duration_minutes', 60) ?>"
                            min="1"
                            max="1440"
                            required
                            class="test-control ltr"
                            <?= $isCoreLocked ? 'readonly' : '' ?>
                            <?= isset($errors['duration_minutes']) ? 'aria-invalid="true" aria-describedby="duration_minutes-error"' : '' ?>
                        >
                        <?php if (isset($errors['duration_minutes'])): ?>
                            <span id="duration_minutes-error" class="test-error"><?= e((string)$errors['duration_minutes']) ?></span>
                        <?php elseif ($isCoreLocked): ?>
                            <span class="lock-help"><?= $icon('lock') ?><?= e(trans('field_locked_attempts_exist')) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="config-card">
                        <div class="config-card-head">
                            <span class="metric-icon"><?= $icon('questions') ?></span>
                            <label for="question_count" class="test-label">
                                <?= e(trans('questions_per_attempt')) ?><span class="required-star">*</span>
                            </label>
                        </div>
                        <input
                            type="number"
                            id="question_count"
                            name="question_count"
                            value="<?= $value('question_count', 10) ?>"
                            min="1"
                            max="500"
                            required
                            class="test-control ltr"
                            <?= $isCoreLocked ? 'readonly' : '' ?>
                            <?= isset($errors['question_count']) ? 'aria-invalid="true" aria-describedby="question_count-error"' : '' ?>
                        >
                        <?php if (isset($errors['question_count'])): ?>
                            <span id="question_count-error" class="test-error"><?= e((string)$errors['question_count']) ?></span>
                        <?php elseif ($isCoreLocked): ?>
                            <span class="lock-help"><?= $icon('lock') ?><?= e(trans('field_locked_attempts_exist')) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="config-card">
                        <div class="config-card-head">
                            <span class="metric-icon"><?= $icon('percent') ?></span>
                            <label for="passing_percentage" class="test-label">
                                <?= e(trans('passing_score_label')) ?> (%)<span class="required-star">*</span>
                            </label>
                        </div>
                        <input
                            type="text"
                            id="passing_percentage"
                            name="passing_percentage"
                            value="<?= $value('passing_percentage', '60.00') ?>"
                            required
                            inputmode="decimal"
                            class="test-control ltr"
                            placeholder="60.00"
                            <?= $isCoreLocked ? 'readonly' : '' ?>
                            <?= isset($errors['passing_percentage']) ? 'aria-invalid="true" aria-describedby="passing_percentage-error"' : '' ?>
                        >
                        <?php if (isset($errors['passing_percentage'])): ?>
                            <span id="passing_percentage-error" class="test-error"><?= e((string)$errors['passing_percentage']) ?></span>
                        <?php elseif ($isCoreLocked): ?>
                            <span class="lock-help"><?= $icon('lock') ?><?= e(trans('field_locked_attempts_exist')) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="config-card">
                        <div class="config-card-head">
                            <span class="metric-icon"><?= $icon('order') ?></span>
                            <label for="display_order" class="test-label">
                                <?= e(trans('display_order_label')) ?><span class="required-star">*</span>
                            </label>
                        </div>
                        <input
                            type="number"
                            id="display_order"
                            name="display_order"
                            value="<?= $value('display_order', 1) ?>"
                            min="1"
                            max="9999"
                            required
                            class="test-control ltr"
                            <?= isset($errors['display_order']) ? 'aria-invalid="true" aria-describedby="display_order-error"' : '' ?>
                        >
                        <?php if (isset($errors['display_order'])): ?>
                            <span id="display_order-error" class="test-error"><?= e((string)$errors['display_order']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="test-section">
            <div class="test-section-head">
                <span class="section-icon"><?= $icon('behavior') ?></span>
                <div>
                    <h2><?= e(trans('attempt_behavior')) ?></h2>
                    <p><?= e($text('حدّث ترتيب الأسئلة والخيارات والتنقل وعرض النتيجة.', 'Update question ordering, answer options, navigation, and result visibility.')) ?></p>
                </div>
            </div>

            <div class="test-section-body">
                <div class="behavior-grid">
                    <label class="behavior-option" for="randomize_questions">
                        <input
                            type="checkbox"
                            id="randomize_questions"
                            name="randomize_questions"
                            value="1"
                            <?= $isChecked('randomize_questions', 1) ?>
                        >
                        <span>
                            <strong><?= e(trans('randomize_questions')) ?></strong>
                            <small><?= e($text('يظهر ترتيب مختلف للأسئلة في كل محاولة.', 'Uses a different question order for each attempt.')) ?></small>
                        </span>
                    </label>

                    <label class="behavior-option" for="randomize_options">
                        <input
                            type="checkbox"
                            id="randomize_options"
                            name="randomize_options"
                            value="1"
                            <?= $isChecked('randomize_options', 1) ?>
                        >
                        <span>
                            <strong><?= e(trans('randomize_options')) ?></strong>
                            <small><?= e($text('يعيد ترتيب خيارات الإجابة داخل الأسئلة.', 'Randomizes answer choices within questions.')) ?></small>
                        </span>
                    </label>

                    <?php if ($isNavLocked): ?>
                        <label class="behavior-option locked" for="allow_back_navigation">
                            <input
                                type="checkbox"
                                id="allow_back_navigation"
                                value="1"
                                <?= $isChecked('allow_back_navigation', 1) ?>
                                disabled
                            >
                            <input
                                type="hidden"
                                name="allow_back_navigation"
                                value="<?= (int)($test['allow_back_navigation'] ?? 1) ?>"
                            >
                            <span>
                                <strong>
                                    <?= e(trans('allow_back_nav')) ?>
                                    — <?= e(trans('field_locked_in_progress')) ?>
                                </strong>
                                <small><?= e($text('لا يمكن تغيير هذا الخيار أثناء وجود محاولة جارية.', 'This option cannot be changed while an attempt is in progress.')) ?></small>
                            </span>
                        </label>
                    <?php else: ?>
                        <label class="behavior-option" for="allow_back_navigation">
                            <input
                                type="checkbox"
                                id="allow_back_navigation"
                                name="allow_back_navigation"
                                value="1"
                                <?= $isChecked('allow_back_navigation', 1) ?>
                            >
                            <span>
                                <strong><?= e(trans('allow_back_nav')) ?></strong>
                                <small><?= e($text('يسمح للموظف بالعودة للأسئلة السابقة.', 'Allows the employee to return to previous questions.')) ?></small>
                            </span>
                        </label>
                    <?php endif; ?>

                    <label class="behavior-option" for="show_result_to_employee">
                        <input
                            type="checkbox"
                            id="show_result_to_employee"
                            name="show_result_to_employee"
                            value="1"
                            <?= $isChecked('show_result_to_employee', 0) ?>
                        >
                        <span>
                            <strong><?= e(trans('show_result_to_emp')) ?></strong>
                            <small><?= e($text('يعرض النتيجة للموظف بعد التسليم.', 'Displays the result to the employee after submission.')) ?></small>
                        </span>
                    </label>
                </div>

                <?php if (isset($errors['allow_back_navigation'])): ?>
                    <div class="test-error admin-inline-8c40c9a553">
                        <?= e((string)$errors['allow_back_navigation']) ?>
                    </div>
                <?php endif; ?>

                <div class="test-warning">
                    <strong><?= e(trans('result_visibility_warning')) ?></strong>
                </div>
            </div>
        </section>

        <div class="test-form-footer">
            <div class="test-form-footer-copy">
                <strong><?= e($text('جاهز لحفظ التعديلات؟', 'Ready to save changes?')) ?></strong>
                <small><?= e($text('سيتم اعتماد الحقول القابلة للتعديل فقط مع الحفاظ على البيانات المقفلة.', 'Only editable fields will be updated while locked assessment data remains unchanged.')) ?></small>
            </div>

            <div class="test-form-actions">
                <a href="/admin/tests/view?id=<?= $testId ?>" class="test-edit-btn">
                    <?= $icon('cancel') ?>
                    <?= e(trans('cancel')) ?>
                </a>

                <button type="submit" class="test-edit-btn primary">
                    <?= $icon('save') ?>
                    <?= e(trans('update_assessment')) ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const codeInput = document.getElementById('code');
    const passingInput = document.getElementById('passing_percentage');

    if (codeInput && !codeInput.readOnly) {
        codeInput.addEventListener('input', function () {
            this.value = this.value
                .toUpperCase()
                .replace(/[^A-Z0-9_-]/g, '');
        });
    }

    if (passingInput && !passingInput.readOnly) {
        passingInput.addEventListener('input', function () {
            let value = this.value.replace(/[^\d.]/g, '');
            const parts = value.split('.');

            if (parts.length > 2) {
                value = parts.shift() + '.' + parts.join('');
            }

            this.value = value;
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
