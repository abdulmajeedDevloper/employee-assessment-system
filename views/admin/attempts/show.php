<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-attempts-show';


use App\Support\TimezoneHelper;

$pageTitle = trans('attempt_details');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$attempt = is_array($attempt ?? null) ? $attempt : [];
$answers = is_array($answers ?? null) ? $answers : [];
$securityEvents = is_array($securityEvents ?? null) ? $securityEvents : [];
$sessions = is_array($sessions ?? null) ? $sessions : [];
$auditHistory = is_array($auditHistory ?? null) ? $auditHistory : [];
$actionTokens = is_array($actionTokens ?? null) ? $actionTokens : [];

$attemptId = (int)($attempt['id'] ?? 0);
$assignmentId = (int)($attempt['assignment_id'] ?? 0);

$testName = $currentLang === 'en'
    ? (string)($attempt['test_name_en'] ?? '')
    : (string)($attempt['test_name_ar'] ?? '');

$departmentName = $currentLang === 'en'
    ? (string)($attempt['department_name_en'] ?? '')
    : (string)($attempt['department_name_ar'] ?? '');

$sectionName = $currentLang === 'en'
    ? (string)($attempt['section_name_en'] ?? '')
    : (string)($attempt['section_name_ar'] ?? '');

$jobTitleName = $currentLang === 'en'
    ? (string)($attempt['job_title_name_en'] ?? '')
    : (string)($attempt['job_title_name_ar'] ?? '');

$decode = static function (mixed $value): array {
    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
};

$optionText = static function (array $options, ?string $key, string $lang): string {
    if ($key === null || $key === '') {
        return trans('not_answered');
    }

    foreach ($options as $option) {
        if (!is_array($option) || (string)($option['key'] ?? '') !== $key) {
            continue;
        }

        $field = $lang === 'en' ? 'text_en' : 'text_ar';

        return (string)(
            $option[$field]
            ?? $option['text_en']
            ?? $option['text_ar']
            ?? $key
        );
    }

    return $key;
};

$eventLabel = static function (mixed $eventType, string $lang): string {
    $type = strtoupper(trim((string)$eventType));

    $labels = [
        'ASSESSMENT_FOCUS_LOST' => [
            'ar' => 'فقدان تركيز نافذة الاختبار',
            'en' => 'Assessment Window Focus Lost',
        ],
        'TAB_SWITCHED' => [
            'ar' => 'الانتقال إلى تبويب آخر',
            'en' => 'Browser Tab Switched',
        ],
        'WINDOW_BLUR' => [
            'ar' => 'مغادرة نافذة الاختبار',
            'en' => 'Assessment Window Left',
        ],
        'FULLSCREEN_EXITED' => [
            'ar' => 'الخروج من وضع ملء الشاشة',
            'en' => 'Fullscreen Mode Exited',
        ],
        'COPY_ATTEMPT' => [
            'ar' => 'محاولة نسخ محتوى',
            'en' => 'Content Copy Attempt',
        ],
        'PASTE_ATTEMPT' => [
            'ar' => 'محاولة لصق محتوى',
            'en' => 'Content Paste Attempt',
        ],
        'CONTEXT_MENU_OPENED' => [
            'ar' => 'فتح القائمة المختصرة',
            'en' => 'Context Menu Opened',
        ],
        'DEVTOOLS_DETECTED' => [
            'ar' => 'اكتشاف أدوات المطور',
            'en' => 'Developer Tools Detected',
        ],
        'MULTIPLE_WINDOWS_DETECTED' => [
            'ar' => 'اكتشاف أكثر من نافذة',
            'en' => 'Multiple Windows Detected',
        ],
        'SESSION_STARTED' => [
            'ar' => 'بدء جلسة الاختبار',
            'en' => 'Assessment Session Started',
        ],
        'SESSION_ENDED' => [
            'ar' => 'انتهاء جلسة الاختبار',
            'en' => 'Assessment Session Ended',
        ],
    ];

    if (isset($labels[$type])) {
        return (string)$labels[$type][$lang === 'en' ? 'en' : 'ar'];
    }

    if ($type === '') {
        return trans('not_available');
    }

    return ucwords(strtolower(str_replace('_', ' ', $type)));
};

$formatDate = static function (mixed $value): string {
    if ($value === null || trim((string)$value) === '') {
        return '';
    }

    return (string)TimezoneHelper::format((string)$value, null, 'Y-m-d H:i:s');
};

$status = strtoupper((string)($attempt['status'] ?? ''));
$isExpiredInProgress = (int)($attempt['is_expired_in_progress'] ?? 0) === 1;
$isGraded = in_array($status, ['COMPLETED', 'TIMED_OUT'], true);
$passed = (int)($attempt['passed'] ?? 0) === 1;

$statusClass = match ($status) {
    'IN_PROGRESS' => 'info',
    'COMPLETED' => 'success',
    'TIMED_OUT' => 'warning',
    'CANCELLED' => 'danger',
    default => 'neutral',
};

$answeredCount = (int)($attempt['answered_count'] ?? 0);
$questionCount = max(0, (int)($attempt['question_count'] ?? 0));
$answerProgress = $questionCount > 0
    ? min(100, (int)round(($answeredCount / $questionCount) * 100))
    : 0;

$percentage = (float)($attempt['percentage'] ?? 0);
$score = (float)($attempt['score'] ?? 0);
$totalScore = (float)($attempt['total_score'] ?? 0);

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'attempt' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'assignment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/></svg>',
        'status' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'result' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16M6 16l4-5 3 3 5-7"/></svg>',
        'answers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></svg>',
        'focus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/><circle cx="12" cy="12" r="3"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>',
        'employee' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        'organization' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V7l8-4 8 4v14M4 10h16M8 10v11M16 10v11M10 14h4M10 18h4"/></svg>',
        'assessment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'security' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
        'session' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 22h8M12 18v4"/></svg>',
        'audit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14v18H5zM9 8h6M9 12h6M9 16h4"/></svg>',
        'actions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="9"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg>',
        'cancel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
        'retake' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.2-8.8L4 10"/></svg>',
        'notes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H8l-4 4z"/><path d="M8 9h8M8 13h5"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/></svg>',
        'ip' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
        'device' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 18h6"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="attempt-show-page">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="attempt-alert success"><?= e((string)$_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="attempt-alert error"><?= e((string)$_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="attempt-alert info"><?= e((string)$_SESSION['flash_info']) ?></div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>

    <section class="attempt-show-hero">
        <div class="attempt-show-heading">
            <span class="attempt-show-kicker">
                <?= $icon('attempt') ?>
                <?= e($text('ملف محاولة الاختبار', 'Assessment Attempt Profile')) ?>
            </span>

            <h1 class="attempt-show-title">
                <?= e((string)($attempt['employee_name'] ?? trans('attempt_details'))) ?>
            </h1>

            <p class="attempt-show-subtitle">
                <bdi dir="ltr"><?= e((string)($attempt['test_code'] ?? '')) ?></bdi>
                <?= $testName !== '' ? ' — ' . e($testName) : '' ?>
                <span aria-hidden="true"> · </span>
                <?= e(trans('attempt_details')) ?>
                <bdi dir="ltr">#<?= $attemptId ?></bdi>
            </p>

            <div class="attempt-show-meta">
                <span class="attempt-badge <?= e($statusClass) ?>">
                    <?= e(trans('attempt_status_' . strtolower($status))) ?>
                </span>

                <?php if ($isExpiredInProgress): ?>
                    <span class="attempt-badge danger">
                        <?= e(trans('expired_waiting_finalization')) ?>
                    </span>
                <?php endif; ?>

                <span class="attempt-badge info">
                    <?= e(trans('attempt_number')) ?>
                    <bdi dir="ltr">
                        <?= (int)($attempt['attempt_number'] ?? 0) ?>
                        /
                        <?= (int)($attempt['max_attempts'] ?? 0) ?>
                    </bdi>
                </span>

                <?php if ($isGraded): ?>
                    <span class="attempt-badge <?= $passed ? 'success' : 'danger' ?>">
                        <?= e(trans($passed ? 'passed' : 'failed')) ?>
                        <bdi dir="ltr"><?= e(number_format($percentage, 2)) ?>%</bdi>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="attempt-show-actions">
            <a href="/admin/attempts" class="attempt-show-btn">
                <?= $icon('back') ?>
                <?= e(trans('back_to_attempts')) ?>
            </a>

            <?php if ($assignmentId > 0): ?>
                <a
                    href="/admin/assignments/view?id=<?= $assignmentId ?>"
                    class="attempt-show-btn purple"
                >
                    <?= $icon('assignment') ?>
                    <?= e(trans('assignment_details')) ?>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <div class="attempt-note">
        <?= $icon('security') ?>
        <span>
            <?= e($text(
                'تعرض الصفحة نتيجة المحاولة وإجابات الموظف والجلسات والتنبيهات الأمنية وسجل الإجراءات الإدارية.',
                'This page presents the attempt result, submitted answers, sessions, security events, and administrative history.'
            )) ?>
        </span>
    </div>

    <section class="attempt-section">
        <div class="attempt-section-head">
            <div class="section-heading-group">
                <span class="section-icon"><?= $icon('status') ?></span>
                <div>
                    <h2><?= e(trans('attempt_summary')) ?></h2>
                    <p>
                        <?= e($text(
                            'ملخص حالة المحاولة والنتيجة والتقدم والتوقيت.',
                            'Summary of attempt status, outcome, progress, and timing.'
                        )) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="attempt-section-body">
            <div class="attempt-metrics">
                <article class="attempt-metric">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('status')) ?></span>
                        <strong class="metric-value admin-inline-951b72dbdd">
                            <span class="attempt-badge <?= e($statusClass) ?>">
                                <?= e(trans('attempt_status_' . strtolower($status))) ?>
                            </span>
                        </strong>
                        <span class="metric-caption">
                            <?= e($text('الحالة الحالية للمحاولة', 'Current attempt status')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('status') ?></span>
                </article>

                <article class="attempt-metric">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('result')) ?></span>

                        <?php if ($isGraded): ?>
                            <strong
                                class="metric-value <?= $passed ? 'is-positive' : 'is-negative' ?>"
                            >
                                <bdi dir="ltr"><?= e(number_format($percentage, 2)) ?>%</bdi>
                            </strong>
                        <?php else: ?>
                            <strong class="metric-value admin-inline-951b72dbdd">
                                <?= e(trans('attempt_outcome_ungraded')) ?>
                            </strong>
                        <?php endif; ?>

                        <span class="metric-caption">
                            <?= e($isGraded
                                ? trans($passed ? 'passed' : 'failed')
                                : $text('لم يتم اعتماد نتيجة نهائية', 'No final result has been recorded')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('result') ?></span>
                </article>

                <article class="attempt-metric">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('answers')) ?></span>
                        <strong class="metric-value">
                            <bdi dir="ltr"><?= $answeredCount ?> / <?= $questionCount ?></bdi>
                        </strong>
                        <span class="metric-caption">
                            <?= e($text('الأسئلة التي تم الرد عليها', 'Questions answered')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('answers') ?></span>
                </article>

                <article class="attempt-metric">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('focus_losses')) ?></span>
                        <strong class="metric-value">
                            <bdi dir="ltr"><?= (int)($attempt['focus_loss_count'] ?? 0) ?></bdi>
                        </strong>
                        <span class="metric-caption">
                            <?= e($text('مرات فقدان تركيز نافذة الاختبار', 'Times the assessment window lost focus')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('focus') ?></span>
                </article>
            </div>

            <div class="answer-progress">
                <div class="progress-head">
                    <strong><?= e($text('تقدم الإجابات', 'Answer Progress')) ?></strong>
                    <span>
                        <bdi dir="ltr"><?= $answeredCount ?> / <?= $questionCount ?></bdi>
                    </span>
                </div>

                <div class="progress-track" style="--progress-value:<?= $answerProgress ?>%;" aria-hidden="true">
                    <div class="progress-fill"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="attempt-section">
        <div class="attempt-section-head">
            <div class="section-heading-group">
                <span class="section-icon"><?= $icon('calendar') ?></span>
                <div>
                    <h2><?= e($text('تفاصيل المحاولة التقنية والزمنية', 'Attempt Timing and Technical Details')) ?></h2>
                    <p>
                        <?= e($text(
                            'الدرجات والتوقيت وسبب التسليم وبيانات الاتصال والجهاز.',
                            'Scores, timing, submission reason, connection, and device information.'
                        )) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="attempt-section-body">
            <div class="attempt-detail-grid">
                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('result') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('score')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr">
                                <?= e(number_format($score, 2)) ?>
                                /
                                <?= e(number_format($totalScore, 2)) ?>
                            </bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('calendar') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('started_at')) ?></span>
                        <strong class="detail-value date-ltr">
                            <bdi dir="ltr">
                                <?= e(($value = $formatDate($attempt['started_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                            </bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('calendar') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('expires_at')) ?></span>
                        <strong class="detail-value date-ltr">
                            <bdi dir="ltr">
                                <?= e(($value = $formatDate($attempt['expires_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                            </bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('calendar') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('submitted_at')) ?></span>
                        <strong class="detail-value date-ltr">
                            <bdi dir="ltr">
                                <?= e(($value = $formatDate($attempt['submitted_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                            </bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('status') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('submission_reason')) ?></span>
                        <strong class="detail-value">
                            <?= e(!empty($attempt['submission_reason'])
                                ? trans('submission_reason_' . strtolower((string)$attempt['submission_reason']))
                                : trans('not_available')) ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('ip') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('ip_address')) ?></span>
                        <strong class="detail-value technical-ltr">
                            <bdi dir="ltr"><?= e((string)($attempt['ip_address'] ?? '—')) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card wide">
                    <span class="detail-icon"><?= $icon('device') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('device_information')) ?></span>
                        <strong class="detail-value technical-ltr">
                            <bdi dir="ltr"><?= e((string)($attempt['user_agent'] ?? '—')) ?></bdi>
                        </strong>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="attempt-section">
        <div class="attempt-section-head">
            <div class="section-heading-group">
                <span class="section-icon"><?= $icon('employee') ?></span>
                <div>
                    <h2><?= e(trans('employee_and_assessment')) ?></h2>
                    <p>
                        <?= e($text(
                            'بيانات الموظف والاختبار والإسناد المرتبط بالمحاولة.',
                            'Employee, assessment, and assignment information linked to this attempt.'
                        )) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="attempt-section-body">
            <div class="attempt-detail-grid">
                <article class="detail-card wide">
                    <span class="detail-icon"><?= $icon('employee') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('employee')) ?></span>
                        <strong class="detail-value">
                            <?= e((string)($attempt['employee_name'] ?? '—')) ?>
                        </strong>
                        <span class="detail-label">
                            <bdi dir="ltr"><?= e((string)($attempt['employee_number'] ?? '—')) ?></bdi>
                        </span>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('organization') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('organization_unit')) ?></span>
                        <strong class="detail-value"><?= e($departmentName !== '' ? $departmentName : '—') ?></strong>
                        <span class="detail-label">
                            <?= e(trim($sectionName . ($jobTitleName !== '' ? ' — ' . $jobTitleName : ''))) ?>
                        </span>
                    </div>
                </article>

                <article class="detail-card wide">
                    <span class="detail-icon"><?= $icon('assessment') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('assessment')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= e((string)($attempt['test_code'] ?? '—')) ?></bdi>
                            <?= $testName !== '' ? ' — ' . e($testName) : '' ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('assignment') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('assignment_status')) ?></span>
                        <strong class="detail-value">
                            <?= e(trans('assignment_status_' . strtolower((string)($attempt['assignment_status'] ?? 'pending')))) ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('security') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('security_events')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= (int)($attempt['security_event_count'] ?? 0) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('result') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('passing_percentage')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr">
                                <?= e(number_format((float)($attempt['passing_percentage'] ?? 0), 2)) ?>%
                            </bdi>
                        </strong>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <?php if ($actionTokens !== []): ?>
        <section class="attempt-section">
            <div class="attempt-section-head">
                <div class="section-heading-group">
                    <span class="section-icon"><?= $icon('actions') ?></span>
                    <div>
                        <h2><?= e(trans('administrative_actions')) ?></h2>
                        <p>
                            <?= e($text(
                                'نفّذ الإجراءات المسموح بها على المحاولة مع تسجيل سبب الإجراء.',
                                'Perform permitted administrative actions with the required reason or notes.'
                            )) ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="attempt-section-body">
                <div class="action-grid">
                    <?php if (!empty($actionTokens['finalize'])): ?>
                        <article class="action-card">
                            <div class="action-card-head">
                                <span class="action-icon"><?= $icon('warning') ?></span>
                                <div>
                                    <h3><?= e(trans('finalize_expired_attempt')) ?></h3>
                                    <p><?= e(trans('finalize_expired_attempt_help')) ?></p>
                                </div>
                            </div>

                            <form
                                method="POST"
                                action="/admin/attempts/finalize-expired"
                                class="action-form"
                                data-confirm="<?= e(trans('confirm_finalize_expired_attempt')) ?>"
                                onsubmit="return confirm(this.dataset.confirm);"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
                                <input
                                    type="hidden"
                                    name="action_token"
                                    value="<?= e((string)$actionTokens['finalize']) ?>"
                                >

                                <button type="submit" class="action-button warning">
                                    <?= $icon('warning') ?>
                                    <?= e(trans('finalize_expired_attempt')) ?>
                                </button>
                            </form>
                        </article>
                    <?php endif; ?>

                    <?php if (!empty($actionTokens['cancel'])): ?>
                        <article class="action-card">
                            <div class="action-card-head">
                                <span class="action-icon"><?= $icon('cancel') ?></span>
                                <div>
                                    <h3><?= e(trans('cancel_attempt')) ?></h3>
                                    <p><?= e(trans('cancel_attempt_help')) ?></p>
                                </div>
                            </div>

                            <form
                                method="POST"
                                action="/admin/attempts/cancel"
                                class="action-form"
                                data-confirm="<?= e(trans('confirm_cancel_attempt')) ?>"
                                onsubmit="return confirm(this.dataset.confirm);"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
                                <input
                                    type="hidden"
                                    name="action_token"
                                    value="<?= e((string)$actionTokens['cancel']) ?>"
                                >

                                <label for="cancel-reason" class="action-label">
                                    <?= e(trans('cancellation_reason')) ?>
                                </label>

                                <textarea
                                    id="cancel-reason"
                                    name="reason"
                                    class="action-input"
                                    rows="4"
                                    minlength="10"
                                    maxlength="1000"
                                    required
                                ></textarea>

                                <button type="submit" class="action-button danger">
                                    <?= $icon('cancel') ?>
                                    <?= e(trans('cancel_attempt')) ?>
                                </button>
                            </form>
                        </article>
                    <?php endif; ?>

                    <?php if (!empty($actionTokens['retake'])): ?>
                        <article class="action-card">
                            <div class="action-card-head">
                                <span class="action-icon"><?= $icon('retake') ?></span>
                                <div>
                                    <h3><?= e(trans('authorize_retake')) ?></h3>
                                    <p><?= e(trans('authorize_retake_help')) ?></p>
                                </div>
                            </div>

                            <form
                                method="POST"
                                action="/admin/attempts/retake"
                                class="action-form"
                                data-confirm="<?= e(trans('confirm_authorize_retake')) ?>"
                                onsubmit="return confirm(this.dataset.confirm);"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
                                <input
                                    type="hidden"
                                    name="action_token"
                                    value="<?= e((string)$actionTokens['retake']) ?>"
                                >

                                <label for="retake-reason" class="action-label">
                                    <?= e(trans('retake_reason')) ?>
                                </label>

                                <textarea
                                    id="retake-reason"
                                    name="reason"
                                    class="action-input"
                                    rows="4"
                                    minlength="10"
                                    maxlength="1000"
                                    required
                                ></textarea>

                                <button type="submit" class="action-button success">
                                    <?= $icon('retake') ?>
                                    <?= e(trans('authorize_retake')) ?>
                                </button>
                            </form>
                        </article>
                    <?php endif; ?>

                    <?php if (!empty($actionTokens['notes'])): ?>
                        <article class="action-card">
                            <div class="action-card-head">
                                <span class="action-icon"><?= $icon('notes') ?></span>
                                <div>
                                    <h3><?= e(trans('supervisor_notes')) ?></h3>
                                    <p><?= e(trans('supervisor_notes_help')) ?></p>
                                </div>
                            </div>

                            <form method="POST" action="/admin/attempts/notes" class="action-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
                                <input
                                    type="hidden"
                                    name="action_token"
                                    value="<?= e((string)$actionTokens['notes']) ?>"
                                >

                                <textarea
                                    name="supervisor_notes"
                                    class="action-input"
                                    rows="5"
                                    maxlength="2000"
                                ><?= e((string)($attempt['supervisor_notes'] ?? '')) ?></textarea>

                                <button type="submit" class="action-button primary">
                                    <?= $icon('save') ?>
                                    <?= e(trans('save_notes')) ?>
                                </button>
                            </form>
                        </article>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($status === 'CANCELLED'): ?>
        <section class="attempt-section">
            <div class="attempt-section-head">
                <div class="section-heading-group">
                    <span class="section-icon"><?= $icon('cancel') ?></span>
                    <div>
                        <h2><?= e(trans('cancellation_details')) ?></h2>
                        <p>
                            <?= e($text(
                                'تفاصيل إلغاء المحاولة والجهة التي نفذت الإجراء.',
                                'Attempt cancellation details and the administrator who performed the action.'
                            )) ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="attempt-section-body">
                <div class="attempt-detail-grid">
                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('calendar') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('cancelled_at')) ?></span>
                            <strong class="detail-value date-ltr">
                                <bdi dir="ltr">
                                    <?= e(($value = $formatDate($attempt['cancelled_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                                </bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="detail-card">
                        <span class="detail-icon"><?= $icon('employee') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('cancelled_by')) ?></span>
                            <strong class="detail-value">
                                <?= e((string)($attempt['cancelled_by_name'] ?? trans('not_available'))) ?>
                            </strong>
                        </div>
                    </article>

                    <article class="detail-card wide">
                        <span class="detail-icon"><?= $icon('notes') ?></span>
                        <div class="detail-content">
                            <span class="detail-label"><?= e(trans('cancellation_reason')) ?></span>
                            <strong class="detail-value">
                                <?= nl2br(e((string)($attempt['cancellation_reason'] ?? ''))) ?>
                            </strong>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="attempt-section">
        <div class="attempt-section-head">
            <div class="section-heading-group">
                <span class="section-icon"><?= $icon('answers') ?></span>
                <div>
                    <h2><?= e(trans('submitted_answers')) ?></h2>
                    <p>
                        <?= e($text(
                            'مقارنة إجابة الموظف بالإجابة الصحيحة والدرجة المستحقة.',
                            'Compare the employee answer with the correct answer and awarded score.'
                        )) ?>
                    </p>
                </div>
            </div>

            <span class="section-count"><?= count($answers) ?></span>
        </div>

        <div class="attempt-section-body">
            <?php if ($answers === []): ?>
                <div class="empty-state">
                    <?= $icon('answers') ?>
                    <span><?= e(trans('no_attempt_questions')) ?></span>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= e(trans('question')) ?></th>
                                <th><?= e(trans('selected_answer')) ?></th>
                                <th><?= e(trans('correct_answer')) ?></th>
                                <th><?= e(trans('result')) ?></th>
                                <th><?= e(trans('score')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($answers as $answer): ?>
                                <?php
                                $question = $decode($answer['question_snapshot'] ?? null);
                                $options = $decode($answer['options_snapshot'] ?? null);

                                $questionText = (string)(
                                    $question[$currentLang === 'en' ? 'text_en' : 'text_ar']
                                    ?? $question['text_en']
                                    ?? $question['text_ar']
                                    ?? ''
                                );

                                $selectedKey = is_string($answer['selected_option_key'] ?? null)
                                    ? $answer['selected_option_key']
                                    : null;

                                $correctKey = (string)($answer['correct_option_key_snapshot'] ?? '');
                                $isCorrect = $answer['is_correct'] ?? null;
                                ?>
                                <tr>
                                    <td>
                                        <bdi dir="ltr"><?= (int)($answer['display_order'] ?? 0) ?></bdi>
                                    </td>

                                    <td class="answer-question">
                                        <?= nl2br(e($questionText)) ?>
                                    </td>

                                    <td>
                                        <?= e($optionText($options, $selectedKey, $currentLang)) ?>
                                    </td>

                                    <td>
                                        <?= e($optionText($options, $correctKey, $currentLang)) ?>
                                    </td>

                                    <td>
                                        <?php if ($isCorrect === null): ?>
                                            <span class="attempt-badge neutral">
                                                <?= e(trans('not_answered')) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="attempt-badge <?= (int)$isCorrect === 1 ? 'success' : 'danger' ?>">
                                                <?= e(trans((int)$isCorrect === 1 ? 'correct' : 'incorrect')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <bdi dir="ltr">
                                            <?= e(number_format((float)($answer['awarded_score'] ?? 0), 2)) ?>
                                            /
                                            <?= e(number_format((float)($answer['score_snapshot'] ?? 0), 2)) ?>
                                        </bdi>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="attempt-section">
        <div class="attempt-section-head">
            <div class="section-heading-group">
                <span class="section-icon"><?= $icon('security') ?></span>
                <div>
                    <h2><?= e(trans('security_events')) ?></h2>
                    <p>
                        <?= e($text(
                            'التنبيهات الأمنية المسجلة أثناء تنفيذ المحاولة.',
                            'Security events recorded while the attempt was in progress.'
                        )) ?>
                    </p>
                </div>
            </div>

            <span class="section-count"><?= count($securityEvents) ?></span>
        </div>

        <div class="attempt-section-body">
            <?php if ($securityEvents === []): ?>
                <div class="empty-state">
                    <?= $icon('security') ?>
                    <span><?= e(trans('no_security_events')) ?></span>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="security-event-type-col"><?= e(trans('event_type')) ?></th>
                                <th class="security-event-time-col"><?= e(trans('event_time')) ?></th>
                                <th class="security-event-ip-col"><?= e(trans('ip_address')) ?></th>
                                <th class="security-event-metadata-col"><?= e(trans('metadata')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($securityEvents as $event): ?>
                                <?php $metadata = $decode($event['metadata'] ?? null); ?>
                                <tr>
                                    <?php $eventType = (string)($event['event_type'] ?? ''); ?>
                                    <td class="security-event-type-col">
                                        <div class="event-type-cell">
                                            <span class="event-type-label">
                                                <?= e($eventLabel($eventType, $currentLang)) ?>
                                            </span>

                                            <?php if ($eventType !== ''): ?>
                                                <code class="event-type-code technical-value technical-code">
                                                    <?= e($eventType) ?>
                                                </code>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td class="security-event-time-col">
                                        <bdi class="technical-value technical-nowrap" dir="ltr">
                                            <?= e(($value = $formatDate($event['created_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                                        </bdi>
                                    </td>

                                    <td class="security-event-ip-col">
                                        <bdi class="technical-value technical-nowrap" dir="ltr"><?= e((string)($event['ip_address'] ?? '—')) ?></bdi>
                                    </td>

                                    <td class="security-event-metadata-col">
                                        <pre class="json-meta"><?= e(
                                            $metadata === []
                                                ? trans('not_available')
                                                : (string)json_encode(
                                                    $metadata,
                                                    JSON_UNESCAPED_UNICODE
                                                    | JSON_UNESCAPED_SLASHES
                                                    | JSON_PRETTY_PRINT
                                                )
                                        ) ?></pre>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="attempt-section">
        <div class="attempt-section-head">
            <div class="section-heading-group">
                <span class="section-icon"><?= $icon('session') ?></span>
                <div>
                    <h2><?= e(trans('test_sessions')) ?></h2>
                    <p>
                        <?= e($text(
                            'جلسات الاتصال المرتبطة بالمحاولة وتوقيت آخر نشاط.',
                            'Connection sessions linked to the attempt and their latest activity.'
                        )) ?>
                    </p>
                </div>
            </div>

            <span class="section-count"><?= count($sessions) ?></span>
        </div>

        <div class="attempt-section-body">
            <?php if ($sessions === []): ?>
                <div class="empty-state">
                    <?= $icon('session') ?>
                    <span><?= e(trans('no_test_sessions')) ?></span>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="session-status-col"><?= e(trans('status')) ?></th>
                                <th class="session-start-col"><?= e(trans('started_at')) ?></th>
                                <th class="session-activity-col"><?= e(trans('last_activity')) ?></th>
                                <th class="session-end-col"><?= e(trans('ended_at')) ?></th>
                                <th class="session-ip-col"><?= e(trans('ip_address')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td class="session-status-col">
                                        <span class="attempt-badge neutral">
                                            <?= e(trans('session_status_' . strtolower((string)($session['status'] ?? '')))) ?>
                                        </span>
                                    </td>

                                    <td class="session-start-col">
                                        <bdi class="technical-value technical-nowrap" dir="ltr">
                                            <?= e(($value = $formatDate($session['started_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                                        </bdi>
                                    </td>

                                    <td class="session-activity-col">
                                        <bdi class="technical-value technical-nowrap" dir="ltr">
                                            <?= e(($value = $formatDate($session['last_activity_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                                        </bdi>
                                    </td>

                                    <td class="session-end-col">
                                        <bdi class="technical-value technical-nowrap" dir="ltr">
                                            <?= e(($value = $formatDate($session['ended_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                                        </bdi>
                                    </td>

                                    <td class="session-ip-col">
                                        <bdi class="technical-value technical-nowrap" dir="ltr"><?= e((string)($session['ip_address'] ?? '—')) ?></bdi>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="attempt-section">
        <div class="attempt-section-head">
            <div class="section-heading-group">
                <span class="section-icon"><?= $icon('audit') ?></span>
                <div>
                    <h2><?= e(trans('audit_history')) ?></h2>
                    <p>
                        <?= e($text(
                            'سجل الإجراءات والتغييرات الإدارية المنفذة على المحاولة.',
                            'Administrative actions and changes recorded for this attempt.'
                        )) ?>
                    </p>
                </div>
            </div>

            <span class="section-count"><?= count($auditHistory) ?></span>
        </div>

        <div class="attempt-section-body">
            <?php if ($auditHistory === []): ?>
                <div class="empty-state">
                    <?= $icon('audit') ?>
                    <span><?= e(trans('no_audit_history')) ?></span>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="audit-action-col"><?= e(trans('action')) ?></th>
                                <th class="audit-actor-col"><?= e(trans('actor')) ?></th>
                                <th class="audit-created-col"><?= e(trans('created_at')) ?></th>
                                <th class="audit-changes-col"><?= e(trans('changed_fields')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditHistory as $audit): ?>
                                <?php
                                $payload = $decode(
                                    $audit['new_values']
                                    ?? $audit['old_values']
                                    ?? null
                                );

                                $fields = $payload['changed_field_names']
                                    ?? $payload['created_field_names']
                                    ?? [];

                                $actor = (string)(
                                    $audit['admin_name']
                                    ?? $audit['actor_type']
                                    ?? trans('not_available')
                                );
                                ?>
                                <tr>
                                    <td class="audit-action-col">
                                        <code class="technical-value technical-code"><?= e((string)($audit['action'] ?? '—')) ?></code>
                                    </td>
                                    <td class="audit-actor-col">
                                        <span class="technical-value technical-code"><?= e($actor) ?></span>
                                    </td>

                                    <td class="audit-created-col">
                                        <bdi class="technical-value technical-nowrap" dir="ltr">
                                            <?= e(($value = $formatDate($audit['created_at'] ?? null)) !== '' ? $value : trans('not_available')) ?>
                                        </bdi>
                                    </td>

                                    <td class="audit-changes-col">
                                        <?= e(
                                            is_array($fields) && $fields !== []
                                                ? implode(', ', array_map('strval', $fields))
                                                : trans('not_available')
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
