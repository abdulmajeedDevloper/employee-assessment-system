<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-tests-show';


$pageTitle = trans('test_details');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$test = is_array($test ?? null) ? $test : [];
$localized = is_array($localized ?? null) ? $localized : [];
$aggregates = is_array($aggregates ?? null) ? $aggregates : [];

$testName = (string)($localized['name'] ?? '');
$description = (string)($localized['description'] ?? '');
$instructions = (string)($localized['instructions'] ?? '');

$canManageTests = (bool)($canManageTests ?? false);
$canManageAssignments = \App\Services\AuthorizationService::can(
    $admin ?? [],
    'manage_assignments'
);

$testId = (int)($test['id'] ?? 0);
$isActive = (int)($test['is_active'] ?? 0) === 1;

$availableFrom = $test['available_from'] ?? null;
$availableUntil = $test['available_until'] ?? null;

$backUrl = '/admin/tests';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $backUrl .= '?lang=' . urlencode((string)$_GET['lang']);
}

$editUrl = '/admin/tests/edit?id=' . $testId;
$lifecycleUrl = '/admin/tests/lifecycle?id=' . $testId;

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $editUrl .= '&lang=' . urlencode((string)$_GET['lang']);
    $lifecycleUrl .= '&lang=' . urlencode((string)$_GET['lang']);
}

$assignmentsCount = (int)($aggregates['assignments_count'] ?? 0);
$attemptsCount = (int)($aggregates['attempts_count'] ?? 0);
$completedAttemptsCount = (int)($aggregates['completed_attempts_count'] ?? 0);
$activeQuestionBankCount = (int)($aggregates['active_question_bank_count'] ?? 0);

$boolBadge = static function (mixed $value): string {
    return (int)$value === 1 ? 'enabled' : 'disabled';
};

$boolText = static function (mixed $value): string {
    return trans((int)$value === 1 ? 'yes' : 'no');
};

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'test' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>',
        'assignment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/></svg>',
        'lifecycle' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.2-8.8L4 10"/></svg>',
        'details' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="19" cy="18" r="2"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.38.3.73.6 1 .3.27.68.4 1.1.4H21v4h-.09a1.7 1.7 0 0 0-1.1.4c-.27.3-.47.64-.6 1Z"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'questions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.6 2.1c-.9.5-1.4 1-1.4 2M12 17h.01"/></svg>',
        'percent' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 18 12-12"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>',
        'stats' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20V7"/><path d="M2 20h22"/></svg>',
        'description' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
        'x' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="test-show-page">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="test-show-alert success">
            <?= e((string)$_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="test-show-alert info">
            <?= e((string)$_SESSION['flash_info']) ?>
        </div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>

    <section class="test-show-hero">
        <div class="test-show-heading">
            <span class="test-show-kicker">
                <?= $icon('test') ?>
                <?= e($text('ملف الاختبار الإداري', 'Assessment Administration Profile')) ?>
            </span>

            <h1 class="test-show-title">
                <?= e($testName !== '' ? $testName : trans('test_details')) ?>
            </h1>

            <p class="test-show-subtitle">
                <?= e($text(
                    'عرض شامل لبيانات الاختبار وإعداداته وإتاحته وإحصاءاته، مع الوصول المباشر إلى إجراءات الإدارة.',
                    'A complete view of assessment information, settings, availability, and statistics with direct access to management actions.'
                )) ?>
            </p>

            <div class="test-show-meta">
                <span class="test-meta-badge <?= $isActive ? 'active' : 'inactive' ?>">
                    <?= e($isActive ? trans('active') : trans('inactive')) ?>
                </span>

                <span class="test-meta-badge">
                    <bdi dir="ltr"><?= e((string)($test['code'] ?? '')) ?></bdi>
                </span>

                <span class="test-meta-badge">
                    <?= e(trans('attempts_count')) ?>:
                    <bdi dir="ltr"><?= $attemptsCount ?></bdi>
                </span>
            </div>
        </div>

        <div class="test-show-actions">
            <?php if ($canManageAssignments && $isActive): ?>
                <a
                    href="/admin/assignments/create?test_id=<?= $testId ?>"
                    class="test-show-btn assignment"
                >
                    <?= $icon('assignment') ?>
                    <?= e(trans('create_assignment')) ?>
                </a>
            <?php endif; ?>

            <?php if ($canManageTests): ?>
                <a href="<?= e($editUrl) ?>" class="test-show-btn primary">
                    <?= $icon('edit') ?>
                    <?= e(trans('edit_assessment')) ?>
                </a>

                <a href="<?= e($lifecycleUrl) ?>" class="test-show-btn lifecycle">
                    <?= $icon('lifecycle') ?>
                    <?= e(trans('manage_assessment_lifecycle')) ?>
                </a>
            <?php endif; ?>

            <a href="<?= e($backUrl) ?>" class="test-show-btn">
                <?= $icon('back') ?>
                <?= e(trans('back_to_tests')) ?>
            </a>
        </div>
    </section>

    <div class="security-strip">
        <?= $icon('shield') ?>
        <span>
            <?= e($text(
                'تعرض هذه الصفحة إعدادات الاختبار الحالية والنتائج التجميعية دون تعديل مباشر للبيانات.',
                'This page displays current assessment settings and aggregate results without directly modifying data.'
            )) ?>
        </span>
    </div>

    <section class="overview-grid">
        <article class="test-section">
            <div class="test-section-head">
                <span class="section-icon"><?= $icon('details') ?></span>
                <div>
                    <h2><?= e(trans('test_details')) ?></h2>
                    <p><?= e($text('المعلومات الأساسية وإعدادات الإتاحة للاختبار.', 'Core assessment information and availability settings.')) ?></p>
                </div>
            </div>

            <div class="test-section-body">
                <div class="details-grid">
                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('test') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('test_code')) ?></span>
                            <strong class="detail-value">
                                <bdi dir="ltr" class="technical-value"><?= e((string)($test['code'] ?? '—')) ?></bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('clock') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('duration_minutes_label')) ?></span>
                            <strong class="detail-value">
                                <bdi dir="ltr"><?= (int)($test['duration_minutes'] ?? 0) ?></bdi>
                                <?= e(trans('minutes')) ?>
                            </strong>
                        </div>
                    </article>

                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('questions') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('questions_per_attempt')) ?></span>
                            <strong class="detail-value">
                                <bdi dir="ltr"><?= (int)($test['question_count'] ?? 0) ?></bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('questions') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('active_question_bank')) ?></span>
                            <strong class="detail-value">
                                <bdi dir="ltr"><?= $activeQuestionBankCount ?></bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('percent') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('passing_score_label')) ?></span>
                            <strong class="detail-value">
                                <bdi dir="ltr"><?= e(number_format((float)($test['passing_percentage'] ?? 0), 2)) ?>%</bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('calendar') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('availability')) ?></span>
                            <strong class="detail-value">
                                <?php if ($availableFrom === null && $availableUntil === null): ?>
                                    <?= e(trans('always_available')) ?>
                                <?php else: ?>
                                    <?= e($text('مجدولة', 'Scheduled')) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                    </article>

                    <?php if ($availableFrom !== null || $availableUntil !== null): ?>
                        <article class="detail-card">
                            <span class="detail-icon"><?= $icon('calendar') ?></span>
                            <div class="detail-content">
                                <span class="detail-label"><?= e(trans('available_from')) ?></span>
                                <strong class="detail-value">
                                    <?php if ($availableFrom !== null): ?>
                                        <bdi dir="ltr" class="technical-value">
                                            <?= e(\App\Support\TimezoneHelper::format((string)$availableFrom)) ?>
                                        </bdi>
                                    <?php else: ?>
                                        <?= e(trans('available_immediately')) ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </article>

                        <article class="detail-card">
                            <span class="detail-icon"><?= $icon('calendar') ?></span>
                            <div class="detail-content">
                                <span class="detail-label"><?= e(trans('available_until')) ?></span>
                                <strong class="detail-value">
                                    <?php if ($availableUntil !== null): ?>
                                        <bdi dir="ltr" class="technical-value">
                                            <?= e(\App\Support\TimezoneHelper::format((string)$availableUntil)) ?>
                                        </bdi>
                                    <?php else: ?>
                                        <?= e(trans('no_end_date')) ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </article>
                    <?php endif; ?>

                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('calendar') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('created_at')) ?></span>
                            <strong class="detail-value">
                                <bdi dir="ltr" class="technical-value"><?= e((string)($test['created_at'] ?? '—')) ?></bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('calendar') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('updated_at')) ?></span>
                            <strong class="detail-value">
                                <bdi dir="ltr" class="technical-value"><?= e((string)($test['updated_at'] ?? '—')) ?></bdi>
                            </strong>
                        </div>
                    </article>
                </div>
            </div>
        </article>

        <article class="test-section">
            <div class="test-section-head">
                <span class="section-icon"><?= $icon('settings') ?></span>
                <div>
                    <h2><?= e(trans('settings')) ?></h2>
                    <p><?= e($text('سلوك المحاولة وخيارات عرض الأسئلة والنتيجة.', 'Attempt behavior and question/result display options.')) ?></p>
                </div>
            </div>

            <div class="test-section-body">
                <div class="settings-grid">
                    <?php
                    $settings = [
                        [trans('randomize_questions'), $test['randomize_questions'] ?? 0],
                        [trans('randomize_options'), $test['randomize_options'] ?? 0],
                        [trans('allow_back_nav'), $test['allow_back_navigation'] ?? 0],
                        [trans('show_result_to_emp'), $test['show_result_to_employee'] ?? 0],
                    ];
                    ?>

                    <?php foreach ($settings as [$label, $settingValue]): ?>
                        <article class="setting-card">
                            <div>
                                <strong><?= e((string)$label) ?></strong>
                                <div class="detail-label admin-inline-4b4bfd433b">
                                    <?= e($boolText($settingValue)) ?>
                                </div>
                            </div>

                            <span class="setting-state <?= e($boolBadge($settingValue)) ?>">
                                <?= (int)$settingValue === 1 ? $icon('check') : $icon('x') ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>
    </section>

    <section class="test-section">
        <div class="test-section-head">
            <span class="section-icon"><?= $icon('stats') ?></span>
            <div>
                <h2><?= e(trans('assessment_statistics')) ?></h2>
                <p><?= e($text('ملخص رقمي للإسنادات والمحاولات والأسئلة المرتبطة.', 'A numeric summary of assignments, attempts, and linked questions.')) ?></p>
            </div>
        </div>

        <div class="test-section-body">
            <div class="metric-grid">
                <article class="metric-card">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('assignments_count')) ?></span>
                        <strong class="metric-value"><bdi dir="ltr"><?= $assignmentsCount ?></bdi></strong>
                        <span class="metric-caption"><?= e($text('إسناد مرتبط', 'Linked assignments')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('assignment') ?></span>
                </article>

                <article class="metric-card">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('attempts_count')) ?></span>
                        <strong class="metric-value"><bdi dir="ltr"><?= $attemptsCount ?></bdi></strong>
                        <span class="metric-caption"><?= e($text('إجمالي المحاولات', 'Total attempts')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('stats') ?></span>
                </article>

                <article class="metric-card">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('completed_attempts')) ?></span>
                        <strong class="metric-value"><bdi dir="ltr"><?= $completedAttemptsCount ?></bdi></strong>
                        <span class="metric-caption"><?= e($text('محاولة مكتملة', 'Completed attempts')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('check') ?></span>
                </article>

                <article class="metric-card">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('active_question_bank')) ?></span>
                        <strong class="metric-value"><bdi dir="ltr"><?= $activeQuestionBankCount ?></bdi></strong>
                        <span class="metric-caption"><?= e($text('سؤال نشط', 'Active questions')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('questions') ?></span>
                </article>
            </div>
        </div>
    </section>

    <section class="test-section">
        <div class="test-section-head">
            <span class="section-icon"><?= $icon('description') ?></span>
            <div>
                <h2><?= e($text('المحتوى والتعليمات', 'Content and Instructions')) ?></h2>
                <p><?= e($text('الوصف والتعليمات المعروضة للموظف باللغة الحالية.', 'Description and instructions displayed to the employee in the current language.')) ?></p>
            </div>
        </div>

        <div class="test-section-body">
            <div class="content-grid">
                <article class="content-card">
                    <h3><?= e(trans('description')) ?></h3>
                    <div>
                        <?php if ($description !== ''): ?>
                            <?= nl2br(e($description)) ?>
                        <?php else: ?>
                            <span class="empty-content">
                                <?= e($text('لا يوجد وصف مسجل.', 'No description is available.')) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="content-card">
                    <h3><?= e(trans('employee_instructions')) ?></h3>
                    <div>
                        <?php if ($instructions !== ''): ?>
                            <?= nl2br(e($instructions)) ?>
                        <?php else: ?>
                            <span class="empty-content">
                                <?= e($text('لا توجد تعليمات مسجلة.', 'No instructions are available.')) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
