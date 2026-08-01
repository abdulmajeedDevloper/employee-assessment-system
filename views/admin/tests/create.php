<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-tests-create';


$pageTitle = trans('create_assessment');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];

$value = static function (string $key, mixed $default = '') use ($old): string {
    return e((string)($old[$key] ?? $default));
};

$isChecked = static function (string $key, int $default = 0) use ($old): string {
    if (array_key_exists($key, $old)) {
        return (int)$old[$key] === 1 ? 'checked' : '';
    }

    return $default === 1 ? 'checked' : '';
};

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'test' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'basic' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="19" cy="18" r="2"/></svg>',
        'instructions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>',
        'config' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.38.3.73.6 1 .3.27.68.4 1.1.4H21v4h-.09a1.7 1.7 0 0 0-1.1.4c-.27.3-.47.64-.6 1Z"/></svg>',
        'behavior' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h10M4 12h16M4 17h7"/><circle cx="18" cy="7" r="2"/><circle cx="14" cy="17" r="2"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/></svg>',
        'cancel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'questions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.6 2.1c-.9.5-1.4 1-1.4 2M12 17h.01"/></svg>',
        'percent' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 18 12-12"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>',
        'order' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 5h.01M4 11h.01M4 17h.01"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="test-create-page">
    <section class="test-create-hero">
        <div class="test-create-heading">
            <span class="test-create-kicker">
                <?= $icon('test') ?>
                <?= e($text('إدارة الاختبارات والتقييمات', 'Assessment and Test Management')) ?>
            </span>

            <h1 class="test-create-title"><?= e(trans('create_assessment')) ?></h1>

            <p class="test-create-subtitle">
                <?= e($text(
                    'أنشئ اختبارًا جديدًا وحدد بياناته وتعليماته وإعدادات المحاولة قبل إضافة الأسئلة وتفعيله للموظفين.',
                    'Create a new assessment and define its information, instructions, and attempt settings before adding questions and activating it for employees.'
                )) ?>
            </p>
        </div>

        <a href="/admin/tests" class="test-create-btn">
            <?= $icon('back') ?>
            <?= e(trans('back_to_tests')) ?>
        </a>
    </section>

    <form action="/admin/tests/create" method="POST" id="create-assessment-form" novalidate class="test-create-form">
        <?= csrf_field() ?>

        <section class="test-section">
            <div class="test-section-head">
                <span class="section-icon"><?= $icon('basic') ?></span>
                <div>
                    <h2><?= e(trans('basic_info')) ?></h2>
                    <p><?= e($text('بيانات تعريف الاختبار باللغتين العربية والإنجليزية.', 'Assessment identity and bilingual descriptive information.')) ?></p>
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
                            placeholder="EVAL-2026-01"
                            autocomplete="off"
                            <?= isset($errors['code']) ? 'aria-invalid="true" aria-describedby="code-error"' : '' ?>
                        >
                        <span class="test-help"><?= e($text('رمز فريد وواضح للاختبار.', 'Use a unique and recognizable assessment code.')) ?></span>
                        <?php if (isset($errors['code'])): ?>
                            <span id="code-error" class="test-error"><?= e((string)$errors['code']) ?></span>
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
                    <p><?= e($text('التعليمات التي تظهر للموظف قبل بدء المحاولة.', 'Instructions shown to the employee before starting the attempt.')) ?></p>
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
                    <p><?= e($text('حدد مدة المحاولة وعدد الأسئلة ونسبة النجاح وترتيب العرض.', 'Set the attempt duration, question count, passing threshold, and display order.')) ?></p>
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
                            <?= isset($errors['duration_minutes']) ? 'aria-invalid="true" aria-describedby="duration_minutes-error"' : '' ?>
                        >
                        <?php if (isset($errors['duration_minutes'])): ?>
                            <span id="duration_minutes-error" class="test-error"><?= e((string)$errors['duration_minutes']) ?></span>
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
                            <?= isset($errors['question_count']) ? 'aria-invalid="true" aria-describedby="question_count-error"' : '' ?>
                        >
                        <?php if (isset($errors['question_count'])): ?>
                            <span id="question_count-error" class="test-error"><?= e((string)$errors['question_count']) ?></span>
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
                            <?= isset($errors['passing_percentage']) ? 'aria-invalid="true" aria-describedby="passing_percentage-error"' : '' ?>
                        >
                        <?php if (isset($errors['passing_percentage'])): ?>
                            <span id="passing_percentage-error" class="test-error"><?= e((string)$errors['passing_percentage']) ?></span>
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
                    <p><?= e($text('حدد طريقة ترتيب الأسئلة وخيارات التنقل وعرض النتيجة.', 'Define question ordering, navigation behavior, and result visibility.')) ?></p>
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

                <div class="test-warning">
                    <strong><?= e(trans('result_visibility_warning')) ?></strong>
                </div>
            </div>
        </section>

        <div class="test-security-note">
            <?= $icon('shield') ?>
            <span>
                <?= e(trans('new_assessment_inactive_notice')) ?>
            </span>
        </div>

        <div class="test-form-footer">
            <div class="test-form-footer-copy">
                <strong><?= e($text('جاهز لإنشاء الاختبار؟', 'Ready to create the assessment?')) ?></strong>
                <small><?= e($text('سيتم حفظ الاختبار بحالة غير نشطة حتى تتم مراجعته وإضافة الأسئلة.', 'The assessment will be saved as inactive until it is reviewed and questions are added.')) ?></small>
            </div>

            <div class="test-form-actions">
                <a href="/admin/tests" class="test-create-btn">
                    <?= $icon('cancel') ?>
                    <?= e(trans('cancel')) ?>
                </a>

                <button type="submit" class="test-create-btn primary">
                    <?= $icon('save') ?>
                    <?= e(trans('save_assessment')) ?>
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

    if (codeInput) {
        codeInput.addEventListener('input', function () {
            this.value = this.value
                .toUpperCase()
                .replace(/[^A-Z0-9_-]/g, '');
        });
    }

    if (passingInput) {
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
