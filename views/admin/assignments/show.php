<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-assignments-show';


use App\Services\AuthorizationService;
use App\Support\TimezoneHelper;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$pageTitle = trans('assignment_details');
$isAuthenticatedLayout = true;

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$assignment = is_array($assignment ?? null) ? $assignment : [];
$admin = is_array($admin ?? null) ? $admin : [];

$canManageAssignments = AuthorizationService::can($admin, 'manage_assignments');
$canViewAttempts = AuthorizationService::can($admin, 'view_attempts');

$assignmentId = (int)($assignment['id'] ?? 0);
$status = strtoupper((string)($assignment['status'] ?? 'PENDING'));
$attemptCount = (int)($assignment['attempt_count'] ?? 0);
$maxAttempts = max(1, (int)($assignment['max_attempts'] ?? 1));
$remainingAttempts = max(0, $maxAttempts - $attemptCount);
$attemptProgress = min(100, (int)round(($attemptCount / $maxAttempts) * 100));

$testName = $currentLang === 'en'
    ? (string)($assignment['test_name_en'] ?? '')
    : (string)($assignment['test_name_ar'] ?? '');

$departmentName = $currentLang === 'en'
    ? (string)($assignment['department_name_en'] ?? '')
    : (string)($assignment['department_name_ar'] ?? '');

$sectionName = $currentLang === 'en'
    ? (string)($assignment['section_name_en'] ?? '')
    : (string)($assignment['section_name_ar'] ?? '');

$jobTitleName = $currentLang === 'en'
    ? (string)($assignment['job_title_name_en'] ?? '')
    : (string)($assignment['job_title_name_ar'] ?? '');

$availabilityState = strtolower((string)($assignment['availability_state'] ?? ''));
$latestPercentage = $assignment['latest_attempt_percentage'] ?? null;
$passingPercentage = (float)($assignment['passing_percentage'] ?? 0);
$latestPassed = $latestPercentage !== null && (float)$latestPercentage >= $passingPercentage;

$canEdit = $canManageAssignments && $status === 'PENDING' && $attemptCount === 0;
$isLocked = $status !== 'PENDING' || $attemptCount > 0;

$statusClassMap = [
    'PENDING' => 'warning',
    'ACTIVE' => 'success',
    'IN_PROGRESS' => 'info',
    'COMPLETED' => 'success',
    'CANCELLED' => 'danger',
    'EXPIRED' => 'danger',
];

$statusClass = $statusClassMap[$status] ?? 'neutral';

$availabilityClass = match ($availabilityState) {
    'available', 'open', 'active', 'always' => 'success',
    'not_started', 'upcoming', 'scheduled' => 'warning',
    'expired', 'closed', 'unavailable' => 'danger',
    default => 'neutral',
};

$formatDate = static function (mixed $value): string {
    if ($value === null || trim((string)$value) === '') {
        return '';
    }

    return (string)TimezoneHelper::format((string)$value, null, 'Y-m-d H:i');
};

$assignedAt = $formatDate($assignment['assigned_at'] ?? null);
$availableFrom = $formatDate($assignment['available_from'] ?? null);
$availableUntil = $formatDate($assignment['available_until'] ?? null);

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'assignment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>',
        'attempts' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.2-8.8L4 10"/></svg>',
        'status' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'availability' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>',
        'employee' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        'assessment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'organization' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V7l8-4 8 4v14M4 10h16M8 10v11M16 10v11M10 14h4M10 18h4"/></svg>',
        'email' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
        'number' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3 7 21M17 3l-2 18M4 9h16M3 15h16"/></svg>',
        'duration' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 2h6"/></svg>',
        'questions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.6 2.1c-.9.5-1.4 1-1.4 2M12 17h.01"/></svg>',
        'percentage' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 19 14-14"/><circle cx="7" cy="7" r="2"/><circle cx="17" cy="17" r="2"/></svg>',
        'result' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16M6 16l4-5 3 3 5-7"/></svg>',
        'creator' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M17 8v6M14 11h6"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="assignment-show-page">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="assignment-alert success"><?= e((string)$_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="assignment-alert error"><?= e((string)$_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="assignment-alert info"><?= e((string)$_SESSION['flash_info']) ?></div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>

    <section class="assignment-show-hero">
        <div class="assignment-show-heading">
            <span class="assignment-show-kicker">
                <?= $icon('assignment') ?>
                <?= e($text('ملف الإسناد الإداري', 'Assignment Administration Profile')) ?>
            </span>

            <h1 class="assignment-show-title">
                <?= e((string)($assignment['employee_name'] ?? trans('assignment_details'))) ?>
            </h1>

            <p class="assignment-show-subtitle">
                <bdi dir="ltr"><?= e((string)($assignment['test_code'] ?? '')) ?></bdi>
                <?= $testName !== '' ? ' — ' . e($testName) : '' ?>
                <span aria-hidden="true"> · </span>
                <?= e(trans('assignment_details')) ?>
                <bdi dir="ltr">#<?= $assignmentId ?></bdi>
            </p>

            <div class="assignment-show-meta">
                <span class="assignment-badge <?= e($statusClass) ?>">
                    <?= e(trans('assignment_status_' . strtolower($status))) ?>
                </span>

                <span class="assignment-badge <?= e($availabilityClass) ?>">
                    <?= e(trans('assignment_availability_' . $availabilityState)) ?>
                </span>

                <span class="assignment-badge info">
                    <bdi dir="ltr"><?= $attemptCount ?> / <?= $maxAttempts ?></bdi>
                    <?= e(trans('attempts_used')) ?>
                </span>
            </div>
        </div>

        <div class="assignment-show-actions">
            <?php if ($canEdit): ?>
                <a href="/admin/assignments/edit?id=<?= $assignmentId ?>" class="assignment-show-btn primary">
                    <?= $icon('edit') ?>
                    <?= e(trans('edit_assignment')) ?>
                </a>
            <?php endif; ?>

            <?php if ($canViewAttempts && $attemptCount > 0): ?>
                <a href="/admin/attempts?assignment_id=<?= $assignmentId ?>" class="assignment-show-btn purple">
                    <?= $icon('attempts') ?>
                    <?= e(trans('view_assignment_attempts')) ?>
                </a>
            <?php endif; ?>

            <a href="/admin/assignments" class="assignment-show-btn">
                <?= $icon('back') ?>
                <?= e(trans('back_to_assignments')) ?>
            </a>
        </div>
    </section>

    <?php if ($isLocked): ?>
        <div class="assignment-alert warning">
            <?= $icon('lock') ?>
            <span><?= e(trans('assignment_edit_status_locked')) ?></span>
        </div>
    <?php endif; ?>

    <div class="assignment-note">
        <?= $icon('shield') ?>
        <span>
            <?= e($text(
                'يعرض النظام حالة الإسناد والإتاحة والمحاولات وبيانات الموظف والاختبار المرتبط في صفحة واحدة.',
                'This page combines assignment status, availability, attempts, employee information, and assessment information.'
            )) ?>
        </span>
    </div>

    <section class="assignment-section">
        <div class="assignment-section-head">
            <span class="section-icon"><?= $icon('status') ?></span>
            <div>
                <h2><?= e(trans('assignment_summary')) ?></h2>
                <p>
                    <?= e($text(
                        'ملخص تنفيذي لحالة الإسناد والاستخدام والنتيجة الأخيرة.',
                        'Executive summary of assignment state, usage, and latest result.'
                    )) ?>
                </p>
            </div>
        </div>

        <div class="assignment-section-body">
            <div class="assignment-metrics">
                <article class="assignment-metric">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('status')) ?></span>
                        <strong class="metric-value admin-inline-951b72dbdd">
                            <span class="assignment-badge <?= e($statusClass) ?>">
                                <?= e(trans('assignment_status_' . strtolower($status))) ?>
                            </span>
                        </strong>
                        <span class="metric-caption">
                            <?= e($text('الحالة الإدارية الحالية', 'Current administrative status')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('status') ?></span>
                </article>

                <article class="assignment-metric">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('attempts_used')) ?></span>
                        <strong class="metric-value">
                            <bdi dir="ltr"><?= $attemptCount ?></bdi>
                        </strong>
                        <span class="metric-caption">
                            <?= e($text('من إجمالي المحاولات المسموحة', 'Of all allowed attempts')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('attempts') ?></span>
                </article>

                <article class="assignment-metric">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e($text('المحاولات المتبقية', 'Remaining Attempts')) ?></span>
                        <strong class="metric-value <?= $remainingAttempts > 0 ? 'is-positive' : 'is-negative' ?>">
                            <bdi dir="ltr"><?= $remainingAttempts ?></bdi>
                        </strong>
                        <span class="metric-caption">
                            <?= e($text('حتى بلوغ الحد الأقصى', 'Until the maximum limit')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('attempts') ?></span>
                </article>

                <article class="assignment-metric">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('latest_result')) ?></span>

                        <?php if ($latestPercentage === null): ?>
                            <strong class="metric-value admin-inline-951b72dbdd">
                                <?= e(trans('not_available')) ?>
                            </strong>
                        <?php else: ?>
                            <strong
                                class="metric-value <?= $latestPassed ? 'is-positive' : 'is-negative' ?>"
                            >
                                <bdi dir="ltr"><?= e(number_format((float)$latestPercentage, 2)) ?>%</bdi>
                            </strong>
                        <?php endif; ?>

                        <span class="metric-caption">
                            <?= e($latestPercentage === null
                                ? $text('لم تُسجل نتيجة بعد', 'No result has been recorded yet')
                                : ($latestPassed
                                    ? $text('أحدث نتيجة ناجحة', 'Latest result passed')
                                    : $text('أحدث نتيجة دون حد الاجتياز', 'Latest result is below the passing score'))) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('result') ?></span>
                </article>
            </div>

            <div class="attempt-progress">
                <div class="attempt-progress-head">
                    <strong><?= e($text('استهلاك المحاولات', 'Attempt Usage')) ?></strong>
                    <span>
                        <bdi dir="ltr"><?= $attemptCount ?> / <?= $maxAttempts ?></bdi>
                    </span>
                </div>

                <div class="progress-track" style="--progress-value:<?= $attemptProgress ?>%;" aria-hidden="true">
                    <div class="progress-fill"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="assignment-section">
        <div class="assignment-section-head">
            <span class="section-icon"><?= $icon('availability') ?></span>
            <div>
                <h2><?= e($text('الإسناد والإتاحة', 'Assignment and Availability')) ?></h2>
                <p>
                    <?= e($text(
                        'تفاصيل الإنشاء وفترة الإتاحة والجهة التي أنشأت الإسناد.',
                        'Creation details, availability window, and assignment creator.'
                    )) ?>
                </p>
            </div>
        </div>

        <div class="assignment-section-body">
            <div class="assignment-detail-grid">
                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('availability') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('availability')) ?></span>
                        <strong class="detail-value">
                            <span class="assignment-badge <?= e($availabilityClass) ?>">
                                <?= e(trans('assignment_availability_' . $availabilityState)) ?>
                            </span>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('attempts') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('max_attempts')) ?></span>
                        <strong class="detail-value"><bdi dir="ltr"><?= $maxAttempts ?></bdi></strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('creator') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('assigned_by')) ?></span>
                        <strong class="detail-value">
                            <?= e((string)($assignment['assigned_by_name'] ?? '—')) ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('availability') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('assigned_at')) ?></span>
                        <strong class="detail-value date-value">
                            <bdi dir="ltr"><?= e($assignedAt !== '' ? $assignedAt : trans('not_available')) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('availability') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('available_from')) ?></span>
                        <strong class="detail-value date-value">
                            <bdi dir="ltr"><?= e($availableFrom !== '' ? $availableFrom : trans('not_available')) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('availability') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('available_until')) ?></span>
                        <strong class="detail-value date-value">
                            <bdi dir="ltr"><?= e($availableUntil !== '' ? $availableUntil : trans('not_available')) ?></bdi>
                        </strong>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="assignment-section">
        <div class="assignment-section-head">
            <span class="section-icon"><?= $icon('employee') ?></span>
            <div>
                <h2><?= e(trans('employee_information')) ?></h2>
                <p>
                    <?= e($text(
                        'بيانات الموظف والارتباط التنظيمي المرتبط بالإسناد.',
                        'Employee identity and organizational information linked to this assignment.'
                    )) ?>
                </p>
            </div>
        </div>

        <div class="assignment-section-body">
            <div class="assignment-detail-grid">
                <article class="detail-card wide">
                    <span class="detail-icon"><?= $icon('employee') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('full_name')) ?></span>
                        <strong class="detail-value">
                            <?= e((string)($assignment['employee_name'] ?? '—')) ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('number') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('employee_number')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= e((string)($assignment['employee_number'] ?? '—')) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card wide">
                    <span class="detail-icon"><?= $icon('email') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('email')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= e((string)($assignment['employee_email'] ?? '—')) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('organization') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('department')) ?></span>
                        <strong class="detail-value"><?= e($departmentName !== '' ? $departmentName : '—') ?></strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('organization') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('section')) ?></span>
                        <strong class="detail-value"><?= e($sectionName !== '' ? $sectionName : '—') ?></strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('organization') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('job_title')) ?></span>
                        <strong class="detail-value"><?= e($jobTitleName !== '' ? $jobTitleName : '—') ?></strong>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="assignment-section">
        <div class="assignment-section-head">
            <span class="section-icon"><?= $icon('assessment') ?></span>
            <div>
                <h2><?= e(trans('assessment_information')) ?></h2>
                <p>
                    <?= e($text(
                        'إعدادات الاختبار الأساسية المرتبطة بهذا الإسناد.',
                        'Core assessment settings linked to this assignment.'
                    )) ?>
                </p>
            </div>
        </div>

        <div class="assignment-section-body">
            <div class="assignment-detail-grid">
                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('assessment') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('assessment_code')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= e((string)($assignment['test_code'] ?? '—')) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card wide">
                    <span class="detail-icon"><?= $icon('assessment') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('assessment')) ?></span>
                        <strong class="detail-value"><?= e($testName !== '' ? $testName : '—') ?></strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('duration') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('duration')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= (int)($assignment['duration_minutes'] ?? 0) ?></bdi>
                            <?= e(trans('minutes')) ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('questions') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('question_count')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= (int)($assignment['question_count'] ?? 0) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('percentage') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('passing_percentage')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= e(number_format($passingPercentage, 2)) ?>%</bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('status') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('assessment_status')) ?></span>
                        <strong class="detail-value">
                            <span class="assignment-badge <?= (int)($assignment['test_is_active'] ?? 0) === 1 ? 'success' : 'neutral' ?>">
                                <?= e(trans((int)($assignment['test_is_active'] ?? 0) === 1 ? 'active' : 'inactive')) ?>
                            </span>
                        </strong>
                    </div>
                </article>
            </div>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
