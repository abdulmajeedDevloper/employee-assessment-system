<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-tests-lifecycle';


$pageTitle = trans('manage_assessment_lifecycle');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$test = is_array($test ?? null) ? $test : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$readiness = is_array($readiness ?? null) ? $readiness : [];

$testId = (int)($test['id'] ?? 0);
$testName = $currentLang === 'en'
    ? (string)($test['name_en'] ?? '')
    : (string)($test['name_ar'] ?? '');

$editToken = (string)($editToken ?? '');
$selectedStatus = (string)($old['is_active'] ?? (string)(int)($test['is_active'] ?? 0));
$isCurrentlyActive = (int)($test['is_active'] ?? 0) === 1;
$isReady = !empty($readiness['is_ready']);

$requiredQuestions = (int)($readiness['required_question_count'] ?? 0);
$activeQuestions = (int)($readiness['active_question_count'] ?? 0);
$validQuestions = (int)($readiness['valid_question_count'] ?? 0);
$invalidQuestions = (int)($readiness['invalid_question_count'] ?? 0);

$readinessPercentage = $requiredQuestions > 0
    ? min(100, (int)round(($validQuestions / $requiredQuestions) * 100))
    : 0;

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'lifecycle' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.2-8.8L4 10"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
        'questions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.6 2.1c-.9.5-1.4 1-1.4 2M12 17h.01"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg>',
        'status' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/></svg>',
        'cancel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="lifecycle-page <?= $isReady ? 'is-ready' : 'is-not-ready' ?>" style="--progress-value:<?= $readinessPercentage ?>%;">
    <section class="lifecycle-hero">
        <div class="lifecycle-heading">
            <span class="lifecycle-kicker">
                <?= $icon('lifecycle') ?>
                <?= e($text('التحكم في حالة وإتاحة الاختبار', 'Assessment Status and Availability Control')) ?>
            </span>

            <h1 class="lifecycle-title"><?= e(trans('manage_assessment_lifecycle')) ?></h1>

            <p class="lifecycle-subtitle">
                <?= e($text(
                    'راجع جاهزية بنك الأسئلة، ثم حدّد حالة الاختبار وفترة إتاحته للموظفين.',
                    'Review question-bank readiness, then control the assessment status and availability period for employees.'
                )) ?>
            </p>

            <div class="lifecycle-meta">
                <span class="meta-badge">
                    <?= e($testName) ?>
                </span>

                <span class="meta-badge">
                    <bdi dir="ltr"><?= e((string)($test['code'] ?? '')) ?></bdi>
                </span>

                <span class="meta-badge <?= $isCurrentlyActive ? 'active' : 'inactive' ?>">
                    <?= e($isCurrentlyActive ? trans('active') : trans('inactive')) ?>
                </span>
            </div>
        </div>

        <a href="/admin/tests/view?id=<?= $testId ?>" class="lifecycle-btn">
            <?= $icon('back') ?>
            <?= e(trans('back_to_test_details')) ?>
        </a>
    </section>

    <?php if (!empty($conflictMessage)): ?>
        <div class="conflict-alert"><?= e((string)$conflictMessage) ?></div>
    <?php endif; ?>

    <section class="lifecycle-section <?= $isReady ? 'ready' : 'not-ready' ?>">
        <div class="lifecycle-section-head">
            <div class="section-title-group">
                <span class="section-icon">
                    <?= $isReady ? $icon('check') : $icon('warning') ?>
                </span>
                <div>
                    <h2><?= e(trans('activation_readiness')) ?></h2>
                    <p>
                        <?= e($text(
                            'تحقق من اكتمال وصلاحية الأسئلة قبل تفعيل الاختبار.',
                            'Confirm that enough valid questions are available before activation.'
                        )) ?>
                    </p>
                </div>
            </div>

            <span class="readiness-badge <?= $isReady ? 'ready' : 'not-ready' ?>">
                <?= e(trans($isReady ? 'assessment_ready_for_activation' : 'assessment_not_ready_for_activation')) ?>
            </span>
        </div>

        <div class="lifecycle-section-body">
            <div class="readiness-grid">
                <article class="readiness-card">
                    <div class="readiness-copy">
                        <span class="readiness-label"><?= e(trans('required_questions')) ?></span>
                        <strong class="readiness-value"><bdi dir="ltr"><?= $requiredQuestions ?></bdi></strong>
                        <span class="readiness-caption">
                            <?= e($text('الحد المطلوب للتفعيل', 'Required for activation')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('questions') ?></span>
                </article>

                <article class="readiness-card">
                    <div class="readiness-copy">
                        <span class="readiness-label"><?= e(trans('active_questions')) ?></span>
                        <strong class="readiness-value"><bdi dir="ltr"><?= $activeQuestions ?></bdi></strong>
                        <span class="readiness-caption">
                            <?= e($text('الأسئلة النشطة حاليًا', 'Currently active questions')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('questions') ?></span>
                </article>

                <article class="readiness-card">
                    <div class="readiness-copy">
                        <span class="readiness-label"><?= e(trans('valid_questions')) ?></span>
                        <strong class="readiness-value is-positive">
                            <bdi dir="ltr"><?= $validQuestions ?></bdi>
                        </strong>
                        <span class="readiness-caption">
                            <?= e($text('صالحة للاستخدام', 'Valid for use')) ?>
                        </span>
                    </div>
                    <span class="metric-icon is-positive"><?= $icon('check') ?></span>
                </article>

                <article class="readiness-card">
                    <div class="readiness-copy">
                        <span class="readiness-label"><?= e(trans('invalid_questions')) ?></span>
                        <strong class="readiness-value <?= $invalidQuestions > 0 ? 'is-negative' : 'is-neutral' ?>">
                            <bdi dir="ltr"><?= $invalidQuestions ?></bdi>
                        </strong>
                        <span class="readiness-caption">
                            <?= e($text('تحتاج إلى مراجعة', 'Need review')) ?>
                        </span>
                    </div>
                    <span class="metric-icon <?= $invalidQuestions > 0 ? 'is-negative' : 'is-info' ?>">
                        <?= $icon('warning') ?>
                    </span>
                </article>
            </div>

            <div class="readiness-progress">
                <div class="readiness-progress-head">
                    <strong><?= e($text('نسبة الجاهزية الحالية', 'Current readiness progress')) ?></strong>
                    <span><bdi dir="ltr"><?= $readinessPercentage ?>%</bdi></span>
                </div>

                <div class="progress-track" aria-hidden="true">
                    <div class="progress-fill"></div>
                </div>
            </div>

            <div class="notice-strip <?= $isReady ? 'ready' : 'not-ready' ?>">
                <?= $isReady ? $icon('check') : $icon('warning') ?>
                <span>
                    <?= e(trans($isReady ? 'assessment_ready_for_activation' : 'assessment_not_ready_for_activation')) ?>
                </span>
            </div>
        </div>
    </section>

    <form method="POST" action="/admin/tests/lifecycle" class="lifecycle-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $testId ?>">
        <input type="hidden" name="edit_token" value="<?= e($editToken) ?>">

        <section class="lifecycle-section">
            <div class="lifecycle-section-head">
                <div class="section-title-group">
                    <span class="section-icon"><?= $icon('status') ?></span>
                    <div>
                        <h2><?= e(trans('activation_and_availability')) ?></h2>
                        <p>
                            <?= e($text(
                                'حدّد حالة الاختبار والنافذة الزمنية التي يمكن خلالها بدء المحاولات.',
                                'Set the assessment status and the time window during which attempts may begin.'
                            )) ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="lifecycle-section-body">
                <div class="form-grid">
                    <div class="field-group full">
                        <label for="is_active" class="field-label">
                            <?= e(trans('assessment_status')) ?>
                        </label>

                        <select
                            id="is_active"
                            name="is_active"
                            class="input-field"
                            aria-invalid="<?= isset($errors['is_active']) ? 'true' : 'false' ?>"
                            aria-describedby="is_active_help<?= isset($errors['is_active']) ? ' is_active_error' : '' ?>"
                        >
                            <option value="0" <?= $selectedStatus === '0' ? 'selected' : '' ?>>
                                <?= e(trans('inactive')) ?>
                            </option>
                            <option value="1" <?= $selectedStatus === '1' ? 'selected' : '' ?>>
                                <?= e(trans('active')) ?>
                            </option>
                        </select>

                        <div id="status_preview" class="status-preview <?= $selectedStatus === '1' ? 'active' : '' ?>">
                            <span class="status-dot"></span>
                            <div>
                                <strong id="status_preview_title">
                                    <?= e($selectedStatus === '1' ? trans('active') : trans('inactive')) ?>
                                </strong>
                                <small id="status_preview_copy">
                                    <?= e($selectedStatus === '1'
                                        ? $text('سيصبح الاختبار متاحًا وفق فترة الإتاحة المحددة.', 'The assessment will be available according to the selected schedule.')
                                        : $text('لن يتمكن الموظفون من بدء محاولات جديدة.', 'Employees will not be able to start new attempts.')) ?>
                                </small>
                            </div>
                        </div>

                        <div id="is_active_help" class="field-help">
                            <?= e(trans('activation_requires_valid_questions')) ?>
                        </div>

                        <?php if (isset($errors['is_active'])): ?>
                            <div id="is_active_error" class="field-error" role="alert">
                                <?= e((string)$errors['is_active']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <label for="available_from" class="field-label">
                            <?= e(trans('available_from')) ?>
                        </label>

                        <input
                            id="available_from"
                            name="available_from"
                            type="datetime-local"
                            class="input-field"
                            value="<?= e((string)($old['available_from'] ?? '')) ?>"
                            aria-invalid="<?= isset($errors['available_from']) ? 'true' : 'false' ?>"
                        >

                        <div class="field-help">
                            <?= e(trans(
                                'availability_timezone_notice',
                                ['timezone' => (string)env('APP_TIMEZONE', 'Asia/Riyadh')]
                            )) ?>
                        </div>

                        <?php if (isset($errors['available_from'])): ?>
                            <div class="field-error" role="alert">
                                <?= e((string)$errors['available_from']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <label for="available_until" class="field-label">
                            <?= e(trans('available_until')) ?>
                        </label>

                        <input
                            id="available_until"
                            name="available_until"
                            type="datetime-local"
                            class="input-field"
                            value="<?= e((string)($old['available_until'] ?? '')) ?>"
                            aria-invalid="<?= isset($errors['available_until']) ? 'true' : 'false' ?>"
                        >

                        <div class="field-help">
                            <?= e(trans('availability_optional_notice')) ?>
                        </div>

                        <?php if (isset($errors['available_until'])): ?>
                            <div class="field-error" role="alert">
                                <?= e((string)$errors['available_until']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="form-footer">
            <div class="form-footer-copy">
                <strong><?= e($text('جاهز لحفظ حالة الاختبار؟', 'Ready to save the assessment lifecycle?')) ?></strong>
                <small>
                    <?= e($text(
                        'راجع الجاهزية والحالة وفترة الإتاحة قبل الحفظ.',
                        'Review readiness, status, and availability before saving.'
                    )) ?>
                </small>
            </div>

            <div class="form-actions">
                <a href="/admin/tests/view?id=<?= $testId ?>" class="lifecycle-btn">
                    <?= $icon('cancel') ?>
                    <?= e(trans('cancel')) ?>
                </a>

                <button type="submit" class="lifecycle-btn primary">
                    <?= $icon('save') ?>
                    <?= e(trans('save_lifecycle')) ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const statusSelect = document.getElementById('is_active');
    const preview = document.getElementById('status_preview');
    const previewTitle = document.getElementById('status_preview_title');
    const previewCopy = document.getElementById('status_preview_copy');

    if (!statusSelect || !preview || !previewTitle || !previewCopy) {
        return;
    }

    const activeTitle = <?= json_encode(trans('active'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const inactiveTitle = <?= json_encode(trans('inactive'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const activeCopy = <?= json_encode(
        $text(
            'سيصبح الاختبار متاحًا وفق فترة الإتاحة المحددة.',
            'The assessment will be available according to the selected schedule.'
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    const inactiveCopy = <?= json_encode(
        $text(
            'لن يتمكن الموظفون من بدء محاولات جديدة.',
            'Employees will not be able to start new attempts.'
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    const updateStatusPreview = function () {
        const isActive = statusSelect.value === '1';

        preview.classList.toggle('active', isActive);
        previewTitle.textContent = isActive ? activeTitle : inactiveTitle;
        previewCopy.textContent = isActive ? activeCopy : inactiveCopy;
    };

    statusSelect.addEventListener('change', updateStatusPreview);
    updateStatusPreview();
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
