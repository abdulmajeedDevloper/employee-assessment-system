<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-employees-edit';


$pageTitle = trans('edit_employee');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$employee = is_array($employee ?? null) ? $employee : [];
$oldInput = is_array($oldInput ?? null) ? $oldInput : [];
$errors = is_array($errors ?? null) ? $errors : [];
$departments = is_array($departments ?? null) ? $departments : [];
$sections = is_array($sections ?? null) ? $sections : [];
$jobTitles = is_array($jobTitles ?? null) ? $jobTitles : [];

$employeeId = (int)($employee['id'] ?? 0);
$selectedDept = (int)($oldInput['department_id'] ?? $employee['department_id'] ?? 0);
$selectedSec = (int)($oldInput['section_id'] ?? $employee['section_id'] ?? 0);
$selectedJob = (int)($oldInput['job_title_id'] ?? $employee['job_title_id'] ?? 0);
$selectedLang = (string)($oldInput['preferred_language'] ?? $employee['preferred_language'] ?? 'ar');
$selectedStatus = isset($oldInput['is_active'])
    ? (int)$oldInput['is_active']
    : (int)($employee['is_active'] ?? 1);

$replaceNationalChecked = !empty($oldInput['replace_national_id']);

$groupedSections = [];
foreach ($sections as $section) {
    $groupedSections[(int)$section['department_id']][] = $section;
}

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-1.5A6.5 6.5 0 0 1 11.5 13h1A6.5 6.5 0 0 1 19 19.5V21"/></svg>',
        'building' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V5l8-3v19M12 7h8v14M7 8h2M7 12h2M7 16h2M15 11h2M15 15h2M15 19h2M2 21h20"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.38.3.73.6 1 .3.27.68.4 1.1.4H21v4h-.09a1.7 1.7 0 0 0-1.1.4c-.27.3-.47.64-.6 1Z"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/></svg>',
        'cancel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
        'id' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.8-1.6 2-2.4 3-2.4s2.2.8 3 2.4M14 9h4M14 13h4"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="employee-edit-page">
    <section class="employee-edit-hero">
        <div class="employee-edit-heading">
            <span class="employee-edit-kicker">
                <?= $icon('user') ?>
                <?= e($text('تحديث بيانات الموظف', 'Update Employee Information')) ?>
            </span>

            <h1 class="employee-edit-title"><?= e(trans('edit_employee')) ?></h1>

            <p class="employee-edit-subtitle">
                <?= e($text(
                    'راجع البيانات الحالية وعدّل المعلومات المطلوبة مع الحفاظ على دقة الارتباط التنظيمي وبيانات التواصل.',
                    'Review the current information and update the required fields while keeping organizational and contact details accurate.'
                )) ?>
            </p>
        </div>

        <a href="/admin/employees/view?id=<?= $employeeId ?>" class="employee-edit-btn">
            <?= $icon('back') ?>
            <?= e(trans('back_to_employees')) ?>
        </a>
    </section>

    <?php if (!empty($errorMessage)): ?>
        <div class="employee-edit-alert"><?= e((string)$errorMessage) ?></div>
    <?php endif; ?>

    <div class="security-note">
        <?= $icon('shield') ?>
        <span>
            <?= e($text(
                'لحماية البيانات الحساسة يظهر رقم الهوية بشكل مقنّع، ولا يتم تغييره إلا عند تفعيل خيار الاستبدال وإدخال رقم جديد.',
                'To protect sensitive information, the national ID is masked and changes only when replacement is enabled and a new value is entered.'
            )) ?>
        </span>
    </div>

    <form action="/admin/employees/edit?id=<?= $employeeId ?>" method="POST" novalidate class="employee-edit-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $employeeId ?>">

        <section class="employee-section">
            <div class="employee-section-head">
                <span class="section-icon"><?= $icon('user') ?></span>
                <div>
                    <h2><?= e($text('البيانات الشخصية والتواصل', 'Personal and Contact Information')) ?></h2>
                    <p><?= e($text('حدّث معلومات تعريف الموظف ووسائل التواصل المسجلة.', 'Update the employee identity and registered contact details.')) ?></p>
                </div>
            </div>

            <div class="employee-section-body">
                <div class="employee-form-grid">
                    <div class="employee-field">
                        <label for="full_name" class="employee-label">
                            <?= e(trans('full_name')) ?><span class="required-star">*</span>
                        </label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="employee-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                            value="<?= e((string)($oldInput['full_name'] ?? $employee['full_name'] ?? '')) ?>"
                            required
                            maxlength="255"
                            autocomplete="name"
                            aria-invalid="<?= isset($errors['full_name']) ? 'true' : 'false' ?>"
                            aria-describedby="err_full_name"
                        >
                        <?php if (isset($errors['full_name'])): ?>
                            <span id="err_full_name" class="employee-field-error"><?= e((string)$errors['full_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="employee-field">
                        <label for="employee_number" class="employee-label">
                            <?= e(trans('employee_number')) ?>
                        </label>
                        <input
                            type="text"
                            id="employee_number"
                            name="employee_number"
                            class="employee-control <?= isset($errors['employee_number']) ? 'is-invalid' : '' ?>"
                            value="<?= e((string)($oldInput['employee_number'] ?? $employee['employee_number'] ?? '')) ?>"
                            maxlength="64"
                            placeholder="EMP001"
                            dir="ltr"
                            autocomplete="off"
                            aria-invalid="<?= isset($errors['employee_number']) ? 'true' : 'false' ?>"
                            aria-describedby="err_employee_number"
                        >
                        <?php if (isset($errors['employee_number'])): ?>
                            <span id="err_employee_number" class="employee-field-error"><?= e((string)$errors['employee_number']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="employee-field">
                        <label for="email" class="employee-label">
                            <?= e(trans('email')) ?><span class="required-star">*</span>
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="employee-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                            value="<?= e((string)($oldInput['email'] ?? $employee['email'] ?? '')) ?>"
                            required
                            maxlength="255"
                            placeholder="employee@example.com"
                            dir="ltr"
                            autocomplete="email"
                            aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"
                            aria-describedby="err_email"
                        >
                        <?php if (isset($errors['email'])): ?>
                            <span id="err_email" class="employee-field-error"><?= e((string)$errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="employee-field">
                        <label for="phone" class="employee-label">
                            <?= e(trans('phone')) ?><span class="required-star">*</span>
                        </label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            class="employee-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                            value="<?= e((string)($oldInput['phone'] ?? $employee['phone'] ?? '')) ?>"
                            required
                            maxlength="20"
                            inputmode="tel"
                            placeholder="05XXXXXXXX"
                            dir="ltr"
                            autocomplete="tel"
                            aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>"
                            aria-describedby="err_phone"
                        >
                        <?php if (isset($errors['phone'])): ?>
                            <span id="err_phone" class="employee-field-error"><?= e((string)$errors['phone']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="employee-field full">
                        <label class="employee-label"><?= e(trans('national_id')) ?></label>

                        <div class="national-id-card">
                            <div class="national-id-summary">
                                <div class="national-id-value">
                                    <span class="national-id-value-icon"><?= $icon('id') ?></span>
                                    <div>
                                        <span><?= e(trans('national_id_last4')) ?></span>
                                        <strong>******<?= e((string)($employee['national_id_last4'] ?? '')) ?></strong>
                                    </div>
                                </div>

                                <label class="national-id-toggle" for="replace_national_id">
                                    <input
                                        type="checkbox"
                                        id="replace_national_id"
                                        name="replace_national_id"
                                        value="1"
                                        <?= $replaceNationalChecked ? 'checked' : '' ?>
                                    >
                                    <span><?= e(trans('replace_national_id')) ?></span>
                                </label>
                            </div>

                            <div
                                id="national_id_replacement_container"
                                class="national-id-replacement <?= $replaceNationalChecked ? 'active' : '' ?>"
                            >
                                <div class="national-id-warning">
                                    <?= e(trans('replace_national_id_notice')) ?>
                                </div>

                                <label for="national_id" class="employee-label">
                                    <?= e(trans('new_national_id')) ?><span class="required-star">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="national_id"
                                    name="national_id"
                                    class="employee-control <?= isset($errors['national_id']) ? 'is-invalid' : '' ?>"
                                    value=""
                                    maxlength="10"
                                    inputmode="numeric"
                                    pattern="[0-9]{10}"
                                    placeholder="1XXXXXXXXX"
                                    dir="ltr"
                                    autocomplete="off"
                                    <?= !$replaceNationalChecked ? 'disabled' : '' ?>
                                    aria-invalid="<?= isset($errors['national_id']) ? 'true' : 'false' ?>"
                                    aria-describedby="err_national_id"
                                >

                                <?php if (isset($errors['national_id'])): ?>
                                    <span id="err_national_id" class="employee-field-error"><?= e((string)$errors['national_id']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="employee-section">
            <div class="employee-section-head">
                <span class="section-icon"><?= $icon('building') ?></span>
                <div>
                    <h2><?= e($text('الارتباط التنظيمي والوظيفي', 'Organizational and Job Information')) ?></h2>
                    <p><?= e($text('حدّث الإدارة والقسم والمسمى الوظيفي عند الحاجة.', 'Update the department, section, and job title when required.')) ?></p>
                </div>
            </div>

            <div class="employee-section-body">
                <div class="employee-form-grid">
                    <div class="employee-field">
                        <label for="department_id" class="employee-label">
                            <?= e(trans('department')) ?><span class="required-star">*</span>
                        </label>
                        <select
                            id="department_id"
                            name="department_id"
                            class="employee-control <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>"
                            required
                            aria-invalid="<?= isset($errors['department_id']) ? 'true' : 'false' ?>"
                            aria-describedby="err_department_id"
                        >
                            <option value=""><?= e(trans('select_department')) ?></option>
                            <?php foreach ($departments as $department): ?>
                                <option
                                    value="<?= (int)$department['id'] ?>"
                                    <?= $selectedDept === (int)$department['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($currentLang === 'en' ? (string)$department['name_en'] : (string)$department['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['department_id'])): ?>
                            <span id="err_department_id" class="employee-field-error"><?= e((string)$errors['department_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="employee-field">
                        <label for="section_id" class="employee-label">
                            <?= e(trans('section')) ?><span class="required-star">*</span>
                        </label>
                        <select
                            id="section_id"
                            name="section_id"
                            class="employee-control <?= isset($errors['section_id']) ? 'is-invalid' : '' ?>"
                            required
                            aria-invalid="<?= isset($errors['section_id']) ? 'true' : 'false' ?>"
                            aria-describedby="err_section_id"
                        >
                            <option value=""><?= e(trans('select_section')) ?></option>

                            <?php foreach ($departments as $department): ?>
                                <?php
                                $departmentId = (int)$department['id'];
                                if (empty($groupedSections[$departmentId])) {
                                    continue;
                                }
                                ?>
                                <optgroup
                                    label="<?= e($currentLang === 'en' ? (string)$department['name_en'] : (string)$department['name_ar']) ?>"
                                    data-dept-id="<?= $departmentId ?>"
                                >
                                    <?php foreach ($groupedSections[$departmentId] as $section): ?>
                                        <option
                                            value="<?= (int)$section['id'] ?>"
                                            data-dept-id="<?= $departmentId ?>"
                                            <?= $selectedSec === (int)$section['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e($currentLang === 'en' ? (string)$section['name_en'] : (string)$section['name_ar']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['section_id'])): ?>
                            <span id="err_section_id" class="employee-field-error"><?= e((string)$errors['section_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="employee-field full">
                        <label for="job_title_id" class="employee-label">
                            <?= e(trans('job_title')) ?><span class="required-star">*</span>
                        </label>
                        <select
                            id="job_title_id"
                            name="job_title_id"
                            class="employee-control <?= isset($errors['job_title_id']) ? 'is-invalid' : '' ?>"
                            required
                            aria-invalid="<?= isset($errors['job_title_id']) ? 'true' : 'false' ?>"
                            aria-describedby="err_job_title_id"
                        >
                            <option value=""><?= e(trans('select_job_title')) ?></option>
                            <?php foreach ($jobTitles as $jobTitle): ?>
                                <option
                                    value="<?= (int)$jobTitle['id'] ?>"
                                    <?= $selectedJob === (int)$jobTitle['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($currentLang === 'en' ? (string)$jobTitle['name_en'] : (string)$jobTitle['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['job_title_id'])): ?>
                            <span id="err_job_title_id" class="employee-field-error"><?= e((string)$errors['job_title_id']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="employee-section">
            <div class="employee-section-head">
                <span class="section-icon"><?= $icon('settings') ?></span>
                <div>
                    <h2><?= e($text('إعدادات الحساب', 'Account Settings')) ?></h2>
                    <p><?= e($text('حدّث لغة الواجهة وحالة تفعيل حساب الموظف.', 'Update the interface language and account activation status.')) ?></p>
                </div>
            </div>

            <div class="employee-section-body">
                <div class="employee-form-grid">
                    <div class="employee-field">
                        <label for="preferred_language" class="employee-label">
                            <?= e(trans('preferred_language')) ?><span class="required-star">*</span>
                        </label>
                        <select
                            id="preferred_language"
                            name="preferred_language"
                            class="employee-control <?= isset($errors['preferred_language']) ? 'is-invalid' : '' ?>"
                            required
                        >
                            <option value="ar" <?= $selectedLang === 'ar' ? 'selected' : '' ?>>العربية</option>
                            <option value="en" <?= $selectedLang === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                        <?php if (isset($errors['preferred_language'])): ?>
                            <span class="employee-field-error"><?= e((string)$errors['preferred_language']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="employee-field">
                        <label for="is_active" class="employee-label">
                            <?= e(trans('status')) ?><span class="required-star">*</span>
                        </label>
                        <select
                            id="is_active"
                            name="is_active"
                            class="employee-control <?= isset($errors['is_active']) ? 'is-invalid' : '' ?>"
                            required
                        >
                            <option value="1" <?= $selectedStatus === 1 ? 'selected' : '' ?>><?= e(trans('active')) ?></option>
                            <option value="0" <?= $selectedStatus === 0 ? 'selected' : '' ?>><?= e(trans('inactive')) ?></option>
                        </select>
                        <?php if (isset($errors['is_active'])): ?>
                            <span class="employee-field-error"><?= e((string)$errors['is_active']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="employee-form-footer">
            <div class="employee-form-footer-copy">
                <strong><?= e($text('جاهز لحفظ التعديلات؟', 'Ready to save changes?')) ?></strong>
                <small><?= e($text('راجع الحقول المعدلة قبل اعتماد بيانات الموظف.', 'Review the updated fields before saving the employee record.')) ?></small>
            </div>

            <div class="employee-form-actions">
                <a href="/admin/employees/view?id=<?= $employeeId ?>" class="employee-edit-btn">
                    <?= $icon('cancel') ?>
                    <?= e(trans('cancel')) ?>
                </a>

                <button type="submit" class="employee-edit-btn primary">
                    <?= $icon('save') ?>
                    <?= e(trans('save_changes')) ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const replaceCheckbox = document.getElementById('replace_national_id');
    const replacementContainer = document.getElementById('national_id_replacement_container');
    const nationalIdInput = document.getElementById('national_id');
    const phoneInput = document.getElementById('phone');

    const toggleNationalIdReplacement = function () {
        if (!replaceCheckbox || !replacementContainer || !nationalIdInput) {
            return;
        }

        const isEnabled = replaceCheckbox.checked;

        replacementContainer.classList.toggle('active', isEnabled);
        nationalIdInput.disabled = !isEnabled;
        nationalIdInput.required = isEnabled;

        if (!isEnabled) {
            nationalIdInput.value = '';
        }
    };

    if (replaceCheckbox) {
        replaceCheckbox.addEventListener('change', toggleNationalIdReplacement);
        toggleNationalIdReplacement();
    }

    if (nationalIdInput) {
        nationalIdInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d+]/g, '');
        });
    }

    const departmentSelect = document.getElementById('department_id');
    const sectionSelect = document.getElementById('section_id');

    if (!departmentSelect || !sectionSelect) {
        return;
    }

    const filterSections = function () {
        const selectedDepartmentId = departmentSelect.value;
        const optionGroups = sectionSelect.querySelectorAll('optgroup');

        optionGroups.forEach(function (optionGroup) {
            const departmentId = optionGroup.getAttribute('data-dept-id');
            const isVisible = !selectedDepartmentId || departmentId === selectedDepartmentId;

            optionGroup.hidden = !isVisible;
            optionGroup.style.display = isVisible ? '' : 'none';

            Array.from(optionGroup.querySelectorAll('option')).forEach(function (option) {
                option.disabled = !isVisible;
            });
        });
    };

    departmentSelect.addEventListener('change', function () {
        filterSections();

        const selectedOption = sectionSelect.options[sectionSelect.selectedIndex];

        if (selectedOption && selectedOption.disabled) {
            sectionSelect.value = '';
        }
    });

    filterSections();
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
