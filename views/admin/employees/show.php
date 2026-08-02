<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-employees-show';


$pageTitle = trans('employee_details');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$employee = is_array($employee ?? null) ? $employee : [];
$canManageAssignments = \App\Services\AuthorizationService::can(
    $admin ?? [],
    'manage_assignments'
);

$employeeId = (int)($employee['id'] ?? 0);
$isArchived = !empty($employee['archived_at']);
$isActive = !$isArchived && (int)($employee['is_active'] ?? 0) === 1;
$isProfileComplete = !empty($employee['profile_completed_at']);

$departmentName = $currentLang === 'en'
    ? (string)($employee['department_name_en'] ?? '')
    : (string)($employee['department_name_ar'] ?? '');

$sectionName = $currentLang === 'en'
    ? (string)($employee['section_name_en'] ?? '')
    : (string)($employee['section_name_ar'] ?? '');

$jobTitleName = $currentLang === 'en'
    ? (string)($employee['job_title_name_en'] ?? '')
    : (string)($employee['job_title_name_ar'] ?? '');

$statusClass = $isArchived ? 'archived' : ($isActive ? 'active' : 'inactive');
$statusLabel = $isArchived
    ? trans('archived')
    : ($isActive ? trans('active') : trans('inactive'));

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-1.5A6.5 6.5 0 0 1 11.5 13h1A6.5 6.5 0 0 1 19 19.5V21"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>',
        'assignment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/></svg>',
        'archive' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v14H4zM3 3h18v4H3zM9 11h6"/></svg>',
        'restore' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.2-8.8L4 10"/></svg>',
        'personal' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.8-1.6 2-2.4 3-2.4s2.2.8 3 2.4M14 9h4M14 13h4"/></svg>',
        'building' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V5l8-3v19M12 7h8v14M7 8h2M7 12h2M7 16h2M15 11h2M15 15h2M15 19h2M2 21h20"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.38.3.73.6 1 .3.27.68.4 1.1.4H21v4h-.09a1.7 1.7 0 0 0-1.1.4c-.27.3-.47.64-.6 1Z"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'mail' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h4l2 5-3 2a15 15 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2C10.2 21 3 13.8 3 5a2 2 0 0 1 2-2Z"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="employee-show-page">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="employee-show-alert success">
            <?= e((string)$_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="employee-show-alert danger">
            <?= e((string)$_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($errorMessage) || $employee === []): ?>
        <section class="employee-section">
            <div class="employee-empty">
                <h2><?= e((string)($errorMessage ?? trans('employee_not_found'))) ?></h2>
                <a href="/admin/employees" class="employee-show-btn primary">
                    <?= $icon('back') ?>
                    <?= e(trans('back_to_employees')) ?>
                </a>
            </div>
        </section>
    <?php else: ?>
        <section class="employee-show-hero">
            <div class="employee-show-heading">
                <span class="employee-show-kicker">
                    <?= $icon('user') ?>
                    <?= e($text('ملف الموظف الإداري', 'Employee Administration Profile')) ?>
                </span>

                <h1 class="employee-show-title"><?= e((string)($employee['full_name'] ?? trans('employee_details'))) ?></h1>

                <p class="employee-show-subtitle">
                    <?= e($text(
                        'عرض شامل لبيانات الموظف الشخصية والتنظيمية وحالة الحساب، مع الوصول المباشر إلى إجراءات الإدارة.',
                        'A complete view of the employee’s personal, organizational, and account information with direct access to management actions.'
                    )) ?>
                </p>

                <div class="employee-show-status-row">
                    <span class="employee-status-badge <?= e($statusClass) ?>">
                        <?= e($statusLabel) ?>
                    </span>

                    <span class="profile-badge">
                        <?= e($isProfileComplete ? trans('profile_complete') : trans('profile_incomplete')) ?>
                    </span>

                    <?php if (!empty($employee['employee_number'])): ?>
                        <span class="profile-badge">
                            <bdi dir="ltr"><?= e((string)$employee['employee_number']) ?></bdi>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="employee-show-hero-actions">
                <?php if (!$isArchived): ?>
                    <?php if ($canManageAssignments && $isActive): ?>
                        <a
                            href="/admin/assignments/create?employee_id=<?= $employeeId ?>"
                            class="employee-show-btn assignment"
                        >
                            <?= $icon('assignment') ?>
                            <?= e(trans('create_assignment')) ?>
                        </a>
                    <?php endif; ?>

                    <a href="/admin/employees/edit?id=<?= $employeeId ?>" class="employee-show-btn primary">
                        <?= $icon('edit') ?>
                        <?= e(trans('edit_employee')) ?>
                    </a>

                    <form
                        action="/admin/employees/archive"
                        method="POST"
                        data-confirm="<?= e(trans('confirm_archive_employee')) ?>"
                        onsubmit="return confirm(this.dataset.confirm);"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $employeeId ?>">

                        <button type="submit" class="employee-show-btn danger">
                            <?= $icon('archive') ?>
                            <?= e(trans('archive_employee')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <form
                        action="/admin/employees/restore"
                        method="POST"
                        data-confirm="<?= e(trans('confirm_restore_employee')) ?>"
                        onsubmit="return confirm(this.dataset.confirm);"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $employeeId ?>">

                        <button type="submit" class="employee-show-btn success">
                            <?= $icon('restore') ?>
                            <?= e(trans('restore_employee')) ?>
                        </button>
                    </form>
                <?php endif; ?>

                <a href="/admin/employees" class="employee-show-btn">
                    <?= $icon('back') ?>
                    <?= e(trans('back_to_employees')) ?>
                </a>
            </div>
        </section>

        <div class="security-strip">
            <?= $icon('shield') ?>
            <span>
                <?= e($text(
                    'يعرض النظام آخر أربعة أرقام فقط من الهوية الوطنية لحماية البيانات الحساسة.',
                    'The system displays only the final four digits of the national ID to protect sensitive information.'
                )) ?>
            </span>
        </div>

        <section class="employee-section">
            <div class="employee-section-head">
                <span class="section-icon"><?= $icon('personal') ?></span>
                <div>
                    <h2><?= e($text('البيانات الشخصية والتواصل', 'Personal and Contact Information')) ?></h2>
                    <p><?= e($text('بيانات تعريف الموظف ووسائل التواصل المسجلة.', 'Employee identity and registered contact details.')) ?></p>
                </div>
            </div>

            <div class="employee-section-body">
                <div class="employee-details-grid">
                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('user') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('full_name')) ?></span>
                            <strong class="employee-detail-value"><?= e((string)($employee['full_name'] ?? '—')) ?></strong>
                        </div>
                    </article>

                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('personal') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('employee_number')) ?></span>
                            <strong class="employee-detail-value">
                                <bdi dir="ltr" class="employee-technical-value"><?= !empty($employee['employee_number']) ? e((string)$employee['employee_number']) : '—' ?></bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('shield') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('national_id_last4')) ?></span>
                            <strong class="employee-detail-value">
                                <bdi dir="ltr" class="employee-technical-value">******<?= e((string)($employee['national_id_last4'] ?? '')) ?></bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('mail') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('email')) ?></span>
                            <strong class="employee-detail-value">
                                <bdi dir="ltr" class="employee-technical-value"><?= e((string)($employee['email'] ?? '—')) ?></bdi>
                            </strong>
                        </div>
                    </article>

                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('phone') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('phone')) ?></span>
                            <strong class="employee-detail-value">
                                <bdi dir="ltr" class="employee-technical-value"><?= e((string)($employee['phone'] ?? '—')) ?></bdi>
                            </strong>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="employee-section">
            <div class="employee-section-head">
                <span class="section-icon"><?= $icon('building') ?></span>
                <div>
                    <h2><?= e($text('الارتباط التنظيمي والوظيفي', 'Organizational and Job Information')) ?></h2>
                    <p><?= e($text('الموقع التنظيمي والمسمى الوظيفي الحالي للموظف.', 'The employee’s current organizational placement and job title.')) ?></p>
                </div>
            </div>

            <div class="employee-section-body">
                <div class="employee-details-grid">
                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('building') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('department')) ?></span>
                            <strong class="employee-detail-value"><?= e($departmentName !== '' ? $departmentName : '—') ?></strong>
                        </div>
                    </article>

                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('building') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('section')) ?></span>
                            <strong class="employee-detail-value"><?= e($sectionName !== '' ? $sectionName : '—') ?></strong>
                        </div>
                    </article>

                    <article class="employee-detail-card full">
                        <span class="detail-icon"><?= $icon('assignment') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('job_title')) ?></span>
                            <strong class="employee-detail-value"><?= e($jobTitleName !== '' ? $jobTitleName : '—') ?></strong>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="employee-section">
            <div class="employee-section-head">
                <span class="section-icon"><?= $icon('settings') ?></span>
                <div>
                    <h2><?= e($text('حالة الحساب والسجل', 'Account Status and Record')) ?></h2>
                    <p><?= e($text('إعدادات واجهة الموظف وحالة الحساب والتواريخ الإدارية.', 'Employee interface settings, account status, and administrative dates.')) ?></p>
                </div>
            </div>

            <div class="employee-section-body">
                <div class="employee-details-grid">
                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('settings') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('preferred_language')) ?></span>
                            <strong class="employee-detail-value">
                                <?= e(($employee['preferred_language'] ?? 'ar') === 'en' ? 'English' : 'العربية') ?>
                            </strong>
                        </div>
                    </article>

                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('shield') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('status')) ?></span>
                            <strong class="employee-detail-value">
                                <span class="employee-status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                            </strong>
                        </div>
                    </article>

                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('user') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('profile_status')) ?></span>
                            <strong class="employee-detail-value">
                                <?= e($isProfileComplete ? trans('profile_complete') : trans('profile_incomplete')) ?>
                            </strong>
                        </div>
                    </article>

                    <article class="employee-detail-card">
                        <span class="detail-icon"><?= $icon('clock') ?></span>
                        <div class="employee-detail-content">
                            <span class="employee-detail-label"><?= e(trans('created_at')) ?></span>
                            <strong class="employee-detail-value">
                                <bdi dir="ltr" class="employee-technical-value"><?= e((string)($employee['created_at'] ?? '—')) ?></bdi>
                            </strong>
                        </div>
                    </article>

                    <?php if ($isArchived): ?>
                        <article class="employee-detail-card full">
                            <span class="detail-icon"><?= $icon('archive') ?></span>
                            <div class="employee-detail-content">
                                <span class="employee-detail-label"><?= e(trans('archived')) ?></span>
                                <strong class="employee-detail-value admin-inline-48f44ffaa6">
                                    <bdi dir="ltr" class="employee-technical-value"><?= e((string)$employee['archived_at']) ?></bdi>
                                </strong>
                            </div>
                        </article>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
