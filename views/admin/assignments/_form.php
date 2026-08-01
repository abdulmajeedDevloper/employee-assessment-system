<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-assignments-form';


$editing = isset($assignment) && is_array($assignment);
$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$assignment = is_array($assignment ?? null) ? $assignment : [];
$employees = is_array($employees ?? null) ? $employees : [];
$tests = is_array($tests ?? null) ? $tests : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

$assignmentId = (int)($assignment['id'] ?? 0);
$formAction = $editing ? '/admin/assignments/edit' : '/admin/assignments/create';
$cancelUrl = $editing
    ? '/admin/assignments/view?id=' . $assignmentId
    : '/admin/assignments';

$selectedEmployeeId = (int)($old['employee_id'] ?? $assignment['employee_id'] ?? 0);
$selectedTestId = (int)($old['test_id'] ?? $assignment['test_id'] ?? ($_GET['test_id'] ?? 0));
$selectedMaxAttempts = (string)($old['max_attempts'] ?? $assignment['max_attempts'] ?? '1');

$formatDateTimeLocal = static function (mixed $value): string {
    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('Y-m-d\TH:i', $timestamp);
};

$availableFrom = array_key_exists('available_from', $old)
    ? (string)$old['available_from']
    : $formatDateTimeLocal($assignment['available_from'] ?? '');

$availableUntil = array_key_exists('available_until', $old)
    ? (string)$old['available_until']
    : $formatDateTimeLocal($assignment['available_until'] ?? '');

$employeeDisplay = '';
if ($editing) {
    $employeeDisplay = (string)($assignment['employee_name'] ?? '');

    if (!empty($assignment['employee_number'])) {
        $employeeDisplay .= ' — ' . (string)$assignment['employee_number'];
    }
}

$testDisplay = '';
if ($editing) {
    $testName = $currentLang === 'en'
        ? (string)($assignment['test_name_en'] ?? '')
        : (string)($assignment['test_name_ar'] ?? '');

    $testDisplay = (string)($assignment['test_code'] ?? '');

    if ($testName !== '') {
        $testDisplay .= ' — ' . $testName;
    }
}

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'assignment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/></svg>',
        'employee' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        'assessment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'attempts' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.2-8.8L4 10"/></svg>',
        'status' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/></svg>',
        'cancel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
    ];

    return $icons[$name] ?? '';
};
?>

<div class="assignment-form-page">
    <section class="assignment-hero">
        <div class="assignment-hero-heading">
            <span class="assignment-kicker">
                <?= $icon('assignment') ?>
                <?= e($text('إدارة إسنادات الاختبارات', 'Assessment Assignment Management')) ?>
            </span>

            <h1 class="assignment-title">
                <?= e(trans($editing ? 'edit_assignment' : 'create_assignment')) ?>
            </h1>

            <p class="assignment-subtitle">
                <?= e($text(
                    $editing
                        ? 'عدّل عدد المحاولات وفترة إتاحة الإسناد مع الحفاظ على الموظف والاختبار المرتبطين.'
                        : 'اربط موظفًا باختبار وحدد عدد المحاولات وفترة الإتاحة لبدء التقييم.',
                    $editing
                        ? 'Update the attempt limit and availability period while preserving the linked employee and assessment.'
                        : 'Assign an assessment to an employee and define the attempt limit and availability period.'
                )) ?>
            </p>
        </div>

        <a href="<?= e($cancelUrl) ?>" class="assignment-btn">
            <?= $icon('back') ?>
            <?= e($editing
                ? $text('العودة إلى تفاصيل الإسناد', 'Back to Assignment Details')
                : $text('العودة إلى سجل الإسنادات', 'Back to Assignments')) ?>
        </a>
    </section>

    <?php if (!empty($formMessage)): ?>
        <div class="assignment-message"><?= e((string)$formMessage) ?></div>
    <?php endif; ?>

    <div class="assignment-note">
        <?= $icon('shield') ?>
        <span>
            <?= e($text(
                'يُنشأ الإسناد بحالة انتظار، ويحدد النظام حالته الفعلية تلقائيًا وفق الإتاحة والمحاولات.',
                'The assignment is created in a pending state, and its effective status is managed automatically according to availability and attempts.'
            )) ?>
        </span>
    </div>

    <form
        method="POST"
        action="<?= e($formAction) ?>"
        class="assignment-form"
        novalidate
    >
        <?= csrf_field() ?>

        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= $assignmentId ?>">
            <input type="hidden" name="edit_token" value="<?= e((string)($editToken ?? '')) ?>">
        <?php endif; ?>

        <section class="assignment-section">
            <div class="assignment-section-head">
                <span class="section-icon"><?= $icon('assignment') ?></span>
                <div>
                    <h2><?= e($text('بيانات الإسناد', 'Assignment Information')) ?></h2>
                    <p>
                        <?= e($text(
                            'حدد الموظف والاختبار وعدد المحاولات المسموح بها.',
                            'Select the employee, assessment, and allowed number of attempts.'
                        )) ?>
                    </p>
                </div>
            </div>

            <div class="assignment-section-body">
                <div class="assignment-grid">
                    <div class="assignment-field">
                        <label for="employee_id" class="assignment-label">
                            <span class="field-icon"><?= $icon('employee') ?></span>
                            <?= e(trans('employee')) ?><span class="required-star">*</span>
                        </label>

                        <?php if ($editing): ?>
                            <div class="assignment-readonly">
                                <span class="readonly-icon"><?= $icon('lock') ?></span>
                                <span><?= e($employeeDisplay) ?></span>
                            </div>
                            <input type="hidden" name="employee_id" value="<?= $selectedEmployeeId ?>">
                            <span class="assignment-help">
                                <?= e($text(
                                    'لا يمكن تغيير الموظف بعد إنشاء الإسناد.',
                                    'The employee cannot be changed after the assignment is created.'
                                )) ?>
                            </span>
                        <?php else: ?>
                            <select
                                id="employee_id"
                                name="employee_id"
                                class="assignment-input"
                                required
                                aria-invalid="<?= isset($errors['employee_id']) ? 'true' : 'false' ?>"
                            >
                                <option value=""><?= e(trans('select_employee')) ?></option>

                                <?php foreach ($employees as $employee): ?>
                                    <?php
                                    $department = $currentLang === 'en'
                                        ? (string)($employee['department_name_en'] ?? '')
                                        : (string)($employee['department_name_ar'] ?? '');

                                    $employeeLabel = (string)($employee['full_name'] ?? '');

                                    if (!empty($employee['employee_number'])) {
                                        $employeeLabel .= ' — ' . (string)$employee['employee_number'];
                                    }

                                    if ($department !== '') {
                                        $employeeLabel .= ' — ' . $department;
                                    }
                                    ?>
                                    <option
                                        value="<?= (int)($employee['id'] ?? 0) ?>"
                                        <?= $selectedEmployeeId === (int)($employee['id'] ?? 0) ? 'selected' : '' ?>
                                    >
                                        <?= e($employeeLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if (isset($errors['employee_id'])): ?>
                            <span class="assignment-error"><?= e((string)$errors['employee_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="assignment-field">
                        <label for="test_id" class="assignment-label">
                            <span class="field-icon"><?= $icon('assessment') ?></span>
                            <?= e(trans('assessment')) ?><span class="required-star">*</span>
                        </label>

                        <?php if ($editing): ?>
                            <div class="assignment-readonly">
                                <span class="readonly-icon"><?= $icon('lock') ?></span>
                                <span><?= e($testDisplay) ?></span>
                            </div>
                            <input type="hidden" name="test_id" value="<?= $selectedTestId ?>">
                            <span class="assignment-help">
                                <?= e($text(
                                    'لا يمكن تغيير الاختبار بعد إنشاء الإسناد.',
                                    'The assessment cannot be changed after the assignment is created.'
                                )) ?>
                            </span>
                        <?php else: ?>
                            <select
                                id="test_id"
                                name="test_id"
                                class="assignment-input"
                                required
                                aria-invalid="<?= isset($errors['test_id']) ? 'true' : 'false' ?>"
                            >
                                <option value=""><?= e(trans('select_assessment')) ?></option>

                                <?php foreach ($tests as $test): ?>
                                    <?php
                                    $name = $currentLang === 'en'
                                        ? (string)($test['name_en'] ?? '')
                                        : (string)($test['name_ar'] ?? '');

                                    $testLabel = (string)($test['code'] ?? '');

                                    if ($name !== '') {
                                        $testLabel .= ' — ' . $name;
                                    }
                                    ?>
                                    <option
                                        value="<?= (int)($test['id'] ?? 0) ?>"
                                        <?= $selectedTestId === (int)($test['id'] ?? 0) ? 'selected' : '' ?>
                                    >
                                        <?= e($testLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if (isset($errors['test_id'])): ?>
                            <span class="assignment-error"><?= e((string)$errors['test_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="assignment-field">
                        <label for="max_attempts" class="assignment-label">
                            <span class="field-icon"><?= $icon('attempts') ?></span>
                            <?= e(trans('max_attempts')) ?><span class="required-star">*</span>
                        </label>

                        <input
                            id="max_attempts"
                            name="max_attempts"
                            type="number"
                            min="1"
                            max="20"
                            class="assignment-input"
                            value="<?= e($selectedMaxAttempts) ?>"
                            required
                            inputmode="numeric"
                            aria-invalid="<?= isset($errors['max_attempts']) ? 'true' : 'false' ?>"
                        >

                        <span class="assignment-help"><?= e(trans('max_attempts_help')) ?></span>

                        <?php if (isset($errors['max_attempts'])): ?>
                            <span class="assignment-error"><?= e((string)$errors['max_attempts']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="assignment-field">
                        <span class="assignment-label">
                            <span class="field-icon"><?= $icon('status') ?></span>
                            <?= e(trans('assignment_initial_status')) ?>
                        </span>

                        <div class="assignment-status-card">
                            <span class="status-dot"></span>
                            <strong><?= e(trans('assignment_status_pending')) ?></strong>
                        </div>

                        <span class="assignment-help">
                            <?= e(trans('assignment_status_automatic_help')) ?>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="assignment-section">
            <div class="assignment-section-head">
                <span class="section-icon"><?= $icon('calendar') ?></span>
                <div>
                    <h2><?= e($text('فترة الإتاحة', 'Availability Period')) ?></h2>
                    <p>
                        <?= e($text(
                            'حدد بداية ونهاية الإتاحة، أو اترك الحقول فارغة لاستخدام الإتاحة الافتراضية.',
                            'Set the availability start and end, or leave the fields empty to use the default availability.'
                        )) ?>
                    </p>
                </div>
            </div>

            <div class="assignment-section-body">
                <div class="assignment-grid">
                    <div class="assignment-field">
                        <label for="available_from" class="assignment-label">
                            <span class="field-icon"><?= $icon('calendar') ?></span>
                            <?= e(trans('available_from')) ?>
                        </label>

                        <?php if ($currentLang === 'en'): ?>
                            <input
                                id="available_from_display"
                                type="text"
                                class="assignment-input assignment-datetime-text"
                                value="<?= e(str_replace('T', ' ', $availableFrom)) ?>"
                                placeholder="YYYY-MM-DD HH:MM"
                                autocomplete="off"
                                inputmode="numeric"
                                aria-invalid="<?= isset($errors['available_from']) ? 'true' : 'false' ?>"
                                aria-describedby="available_from_format"
                            >
                            <input
                                id="available_from_hidden"
                                name="available_from"
                                type="hidden"
                                value="<?= e($availableFrom) ?>"
                            >
                            <span id="available_from_format" class="assignment-help">
                                Use the format: YYYY-MM-DD HH:MM
                            </span>
                        <?php else: ?>
                            <input
                                id="available_from"
                                name="available_from"
                                type="datetime-local"
                                lang="ar-SA"
                                dir="rtl"
                                class="assignment-input"
                                value="<?= e($availableFrom) ?>"
                                aria-invalid="<?= isset($errors['available_from']) ? 'true' : 'false' ?>"
                            >
                        <?php endif; ?>

                        <span class="assignment-help">
                            <?= e(trans('assignment_availability_optional')) ?>
                        </span>

                        <?php if (isset($errors['available_from'])): ?>
                            <span class="assignment-error"><?= e((string)$errors['available_from']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="assignment-field">
                        <label for="available_until" class="assignment-label">
                            <span class="field-icon"><?= $icon('calendar') ?></span>
                            <?= e(trans('available_until')) ?>
                        </label>

                        <?php if ($currentLang === 'en'): ?>
                            <input
                                id="available_until_display"
                                type="text"
                                class="assignment-input assignment-datetime-text"
                                value="<?= e(str_replace('T', ' ', $availableUntil)) ?>"
                                placeholder="YYYY-MM-DD HH:MM"
                                autocomplete="off"
                                inputmode="numeric"
                                aria-invalid="<?= isset($errors['available_until']) ? 'true' : 'false' ?>"
                                aria-describedby="available_until_format"
                            >
                            <input
                                id="available_until_hidden"
                                name="available_until"
                                type="hidden"
                                value="<?= e($availableUntil) ?>"
                            >
                            <span id="available_until_format" class="assignment-help">
                                Use the format: YYYY-MM-DD HH:MM
                            </span>
                        <?php else: ?>
                            <input
                                id="available_until"
                                name="available_until"
                                type="datetime-local"
                                lang="ar-SA"
                                dir="rtl"
                                class="assignment-input"
                                value="<?= e($availableUntil) ?>"
                                aria-invalid="<?= isset($errors['available_until']) ? 'true' : 'false' ?>"
                            >
                        <?php endif; ?>

                        <span class="assignment-help">
                            <?= e(trans('assignment_availability_optional')) ?>
                        </span>

                        <?php if (isset($errors['available_until'])): ?>
                            <span class="assignment-error"><?= e((string)$errors['available_until']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="availability-preview">
                    <article class="availability-card">
                        <span><?= e($text('بداية الإتاحة المختارة', 'Selected start')) ?></span>
                        <strong id="availability_start_preview">
                            <?= e($availableFrom !== ''
                                ? $availableFrom
                                : $text('غير محددة', 'Not specified')) ?>
                        </strong>
                    </article>

                    <article class="availability-card">
                        <span><?= e($text('نهاية الإتاحة المختارة', 'Selected end')) ?></span>
                        <strong id="availability_end_preview">
                            <?= e($availableUntil !== ''
                                ? $availableUntil
                                : $text('غير محددة', 'Not specified')) ?>
                        </strong>
                    </article>
                </div>
            </div>
        </section>

        <div class="assignment-footer">
            <div class="assignment-footer-copy">
                <strong>
                    <?= e($text(
                        $editing ? 'جاهز لحفظ التعديلات؟' : 'جاهز لإنشاء الإسناد؟',
                        $editing ? 'Ready to save changes?' : 'Ready to create the assignment?'
                    )) ?>
                </strong>

                <small>
                    <?= e($text(
                        'راجع الموظف والاختبار وعدد المحاولات وفترة الإتاحة قبل الحفظ.',
                        'Review the employee, assessment, attempt limit, and availability before saving.'
                    )) ?>
                </small>
            </div>

            <div class="assignment-actions">
                <a href="<?= e($cancelUrl) ?>" class="assignment-btn">
                    <?= $icon('cancel') ?>
                    <?= e(trans('cancel')) ?>
                </a>

                <button type="submit" class="assignment-btn primary">
                    <?= $icon('save') ?>
                    <?= e(trans($editing ? 'save_changes' : 'create_assignment')) ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const form = document.querySelector('.assignment-form');
    const startInput = document.getElementById('available_from_display')
        || document.getElementById('available_from');
    const endInput = document.getElementById('available_until_display')
        || document.getElementById('available_until');
    const startHidden = document.getElementById('available_from_hidden');
    const endHidden = document.getElementById('available_until_hidden');
    const startPreview = document.getElementById('availability_start_preview');
    const endPreview = document.getElementById('availability_end_preview');
    const englishDateTimePattern = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/;

    const emptyLabel = <?= json_encode(
        $text('غير محددة', 'Not specified'),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    const normalizeEnglishDateTime = function (value) {
        return value.trim().replace(/\s+/, 'T');
    };

    const syncHiddenDateTime = function (input, hidden) {
        if (!input || !hidden) {
            return true;
        }

        const rawValue = input.value.trim();

        if (rawValue === '') {
            hidden.value = '';
            input.setCustomValidity('');
            return true;
        }

        if (!englishDateTimePattern.test(rawValue)) {
            input.setCustomValidity('Use the format YYYY-MM-DD HH:MM.');
            return false;
        }

        hidden.value = normalizeEnglishDateTime(rawValue);
        input.setCustomValidity('');
        return true;
    };

    const updatePreview = function (input, preview) {
        if (!input || !preview) {
            return;
        }

        preview.textContent = input.value !== '' ? input.value.replace('T', ' ') : emptyLabel;
    };

    if (startInput && startPreview) {
        const handleStartChange = function () {
            syncHiddenDateTime(startInput, startHidden);
            updatePreview(startInput, startPreview);
        };

        startInput.addEventListener('input', handleStartChange);
        startInput.addEventListener('change', handleStartChange);
        handleStartChange();
    }

    if (endInput && endPreview) {
        const handleEndChange = function () {
            syncHiddenDateTime(endInput, endHidden);
            updatePreview(endInput, endPreview);
        };

        endInput.addEventListener('input', handleEndChange);
        endInput.addEventListener('change', handleEndChange);
        handleEndChange();
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const startValid = syncHiddenDateTime(startInput, startHidden);
            const endValid = syncHiddenDateTime(endInput, endHidden);

            if (!startValid || !endValid) {
                event.preventDefault();

                const invalidInput = !startValid ? startInput : endInput;

                if (invalidInput) {
                    invalidInput.reportValidity();
                    invalidInput.focus();
                }
            }
        });
    }
});
</script>