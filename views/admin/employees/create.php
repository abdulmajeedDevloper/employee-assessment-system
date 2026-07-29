<?php
$pageTitle = trans('add_employee');
$isAuthenticatedLayout = true;
ob_start();

$currentLang = $_SESSION['lang'] ?? 'ar';
$selectedDept = (int)($oldInput['department_id'] ?? 0);
$selectedSec = (int)($oldInput['section_id'] ?? 0);
$selectedJob = (int)($oldInput['job_title_id'] ?? 0);
$selectedLang = $oldInput['preferred_language'] ?? 'ar';
$selectedStatus = isset($oldInput['is_active']) ? (int)$oldInput['is_active'] : 1;
?>

<style>
    .emp-form-wrapper {
        width: 100%;
        max-width: 900px;
        margin: 0 auto;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .emp-form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        box-sizing: border-box;
    }

    .emp-form-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #f8fafc;
        margin: 0;
    }

    .emp-form-card {
        width: 100%;
        box-sizing: border-box;
        background: var(--card-bg, rgba(30, 41, 59, 0.85));
        border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
        border-radius: 12px;
        padding: 2rem;
        backdrop-filter: blur(12px);
    }

    .emp-form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        width: 100%;
        box-sizing: border-box;
    }

    .emp-form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .emp-form-group.full-width {
        grid-column: 1 / -1;
    }

    .emp-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-muted, #94a3b8);
    }

    .emp-label .required-star {
        color: #ef4444;
        margin-inline-start: 0.25rem;
    }

    .emp-input {
        width: 100%;
        height: 42px;
        padding: 0 0.85rem;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(15, 23, 42, 0.6);
        color: #fff;
        box-sizing: border-box;
        font-size: 0.9rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .emp-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
    }

    .emp-input.is-invalid {
        border-color: #ef4444;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25);
    }

    .emp-field-error {
        font-size: 0.8rem;
        color: #f87171;
        margin-top: 0.2rem;
    }

    .emp-form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .emp-btn-submit {
        height: 42px;
        padding: 0 1.5rem;
        border-radius: 6px;
        background: #2563eb;
        color: #fff;
        border: none;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: background 0.15s ease-in-out;
    }

    .emp-btn-submit:hover {
        background: #1d4ed8;
    }

    .emp-btn-cancel {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 42px;
        padding: 0 1.25rem;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #cbd5e1;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.15s ease-in-out;
    }

    .emp-btn-cancel:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }

    @media (max-width: 767px) {
        .emp-form-grid {
            grid-template-columns: 1fr;
        }
        .emp-form-actions {
            flex-direction: column;
        }
        .emp-btn-submit, .emp-btn-cancel {
            width: 100%;
        }
    }
</style>

<div class="emp-form-wrapper">
    <div class="emp-form-header">
        <h1 class="emp-form-title"><?= e(trans('add_employee')) ?></h1>
        <a href="/admin/employees" class="emp-btn-cancel"><?= e(trans('back_to_employees')) ?></a>
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert-box alert-danger">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="emp-form-card">
        <form action="/admin/employees/create" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="emp-form-grid">

                <!-- Full Name -->
                <div class="emp-form-group">
                    <label for="full_name" class="emp-label">
                        <?= e(trans('full_name')) ?><span class="required-star">*</span>
                    </label>
                    <input type="text" id="full_name" name="full_name" class="emp-input <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" value="<?= e($oldInput['full_name'] ?? '') ?>" required maxlength="255" aria-invalid="<?= isset($errors['full_name']) ? 'true' : 'false' ?>" aria-describedby="err_full_name">
                    <?php if (isset($errors['full_name'])): ?>
                        <span id="err_full_name" class="emp-field-error"><?= e($errors['full_name']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- National ID (Never repopulated) -->
                <div class="emp-form-group">
                    <label for="national_id" class="emp-label">
                        <?= e(trans('national_id')) ?><span class="required-star">*</span>
                    </label>
                    <input type="text" id="national_id" name="national_id" class="emp-input <?= isset($errors['national_id']) ? 'is-invalid' : '' ?>" value="" required maxlength="10" pattern="[0-9]{10}" placeholder="1XXXXXXXXX" dir="ltr" aria-invalid="<?= isset($errors['national_id']) ? 'true' : 'false' ?>" aria-describedby="err_national_id" autocomplete="off">
                    <?php if (isset($errors['national_id'])): ?>
                        <span id="err_national_id" class="emp-field-error"><?= e($errors['national_id']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Employee Number -->
                <div class="emp-form-group">
                    <label for="employee_number" class="emp-label">
                        <?= e(trans('employee_number')) ?>
                    </label>
                    <input type="text" id="employee_number" name="employee_number" class="emp-input <?= isset($errors['employee_number']) ? 'is-invalid' : '' ?>" value="<?= e($oldInput['employee_number'] ?? '') ?>" maxlength="64" placeholder="EMP001" dir="ltr" aria-invalid="<?= isset($errors['employee_number']) ? 'true' : 'false' ?>" aria-describedby="err_employee_number">
                    <?php if (isset($errors['employee_number'])): ?>
                        <span id="err_employee_number" class="emp-field-error"><?= e($errors['employee_number']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="emp-form-group">
                    <label for="email" class="emp-label">
                        <?= e(trans('email')) ?><span class="required-star">*</span>
                    </label>
                    <input type="email" id="email" name="email" class="emp-input <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($oldInput['email'] ?? '') ?>" required maxlength="255" placeholder="employee@example.com" dir="ltr" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>" aria-describedby="err_email">
                    <?php if (isset($errors['email'])): ?>
                        <span id="err_email" class="emp-field-error"><?= e($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Phone -->
                <div class="emp-form-group">
                    <label for="phone" class="emp-label">
                        <?= e(trans('phone')) ?><span class="required-star">*</span>
                    </label>
                    <input type="text" id="phone" name="phone" class="emp-input <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" value="<?= e($oldInput['phone'] ?? '') ?>" required placeholder="05XXXXXXXX" dir="ltr" aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>" aria-describedby="err_phone">
                    <?php if (isset($errors['phone'])): ?>
                        <span id="err_phone" class="emp-field-error"><?= e($errors['phone']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Department -->
                <div class="emp-form-group">
                    <label for="department_id" class="emp-label">
                        <?= e(trans('department')) ?><span class="required-star">*</span>
                    </label>
                    <select id="department_id" name="department_id" class="emp-input <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>" required aria-invalid="<?= isset($errors['department_id']) ? 'true' : 'false' ?>" aria-describedby="err_department_id">
                        <option value=""><?= e(trans('select_department')) ?></option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int)$dept['id'] ?>" <?= ($selectedDept === (int)$dept['id']) ? 'selected' : '' ?>>
                                <?= e($currentLang === 'en' ? $dept['name_en'] : $dept['name_ar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['department_id'])): ?>
                        <span id="err_department_id" class="emp-field-error"><?= e($errors['department_id']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Section (Grouped by Department with native optgroup fallback) -->
                <div class="emp-form-group">
                    <label for="section_id" class="emp-label">
                        <?= e(trans('section')) ?><span class="required-star">*</span>
                    </label>
                    <select id="section_id" name="section_id" class="emp-input <?= isset($errors['section_id']) ? 'is-invalid' : '' ?>" required aria-invalid="<?= isset($errors['section_id']) ? 'true' : 'false' ?>" aria-describedby="err_section_id">
                        <option value=""><?= e(trans('select_section')) ?></option>
                        <?php
                        $groupedSections = [];
                        foreach ($sections as $sec) {
                            $groupedSections[(int)$sec['department_id']][] = $sec;
                        }
                        foreach ($departments as $dept):
                            $deptId = (int)$dept['id'];
                            if (empty($groupedSections[$deptId])) continue;
                        ?>
                            <optgroup label="<?= e($currentLang === 'en' ? $dept['name_en'] : $dept['name_ar']) ?>" data-dept-id="<?= $deptId ?>">
                                <?php foreach ($groupedSections[$deptId] as $sec): ?>
                                    <option value="<?= (int)$sec['id'] ?>" data-dept-id="<?= $deptId ?>" <?= ($selectedSec === (int)$sec['id']) ? 'selected' : '' ?>>
                                        <?= e($currentLang === 'en' ? $sec['name_en'] : $sec['name_ar']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['section_id'])): ?>
                        <span id="err_section_id" class="emp-field-error"><?= e($errors['section_id']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Job Title -->
                <div class="emp-form-group">
                    <label for="job_title_id" class="emp-label">
                        <?= e(trans('job_title')) ?><span class="required-star">*</span>
                    </label>
                    <select id="job_title_id" name="job_title_id" class="emp-input <?= isset($errors['job_title_id']) ? 'is-invalid' : '' ?>" required aria-invalid="<?= isset($errors['job_title_id']) ? 'true' : 'false' ?>" aria-describedby="err_job_title_id">
                        <option value=""><?= e(trans('select_job_title')) ?></option>
                        <?php foreach ($jobTitles as $job): ?>
                            <option value="<?= (int)$job['id'] ?>" <?= ($selectedJob === (int)$job['id']) ? 'selected' : '' ?>>
                                <?= e($currentLang === 'en' ? $job['name_en'] : $job['name_ar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['job_title_id'])): ?>
                        <span id="err_job_title_id" class="emp-field-error"><?= e($errors['job_title_id']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Preferred Language -->
                <div class="emp-form-group">
                    <label for="preferred_language" class="emp-label">
                        <?= e(trans('preferred_language')) ?><span class="required-star">*</span>
                    </label>
                    <select id="preferred_language" name="preferred_language" class="emp-input <?= isset($errors['preferred_language']) ? 'is-invalid' : '' ?>" required>
                        <option value="ar" <?= ($selectedLang === 'ar') ? 'selected' : '' ?>>العربية</option>
                        <option value="en" <?= ($selectedLang === 'en') ? 'selected' : '' ?>>English</option>
                    </select>
                    <?php if (isset($errors['preferred_language'])): ?>
                        <span class="emp-field-error"><?= e($errors['preferred_language']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Status (is_active) -->
                <div class="emp-form-group">
                    <label for="is_active" class="emp-label">
                        <?= e(trans('status')) ?><span class="required-star">*</span>
                    </label>
                    <select id="is_active" name="is_active" class="emp-input <?= isset($errors['is_active']) ? 'is-invalid' : '' ?>" required>
                        <option value="1" <?= ($selectedStatus === 1) ? 'selected' : '' ?>><?= e(trans('active')) ?></option>
                        <option value="0" <?= ($selectedStatus === 0) ? 'selected' : '' ?>><?= e(trans('inactive')) ?></option>
                    </select>
                    <?php if (isset($errors['is_active'])): ?>
                        <span class="emp-field-error"><?= e($errors['is_active']) ?></span>
                    <?php endif; ?>
                </div>

            </div>

            <div class="emp-form-actions">
                <a href="/admin/employees" class="emp-btn-cancel"><?= e(trans('cancel')) ?></a>
                <button type="submit" class="emp-btn-submit"><?= e(trans('add_employee')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var deptSelect = document.getElementById('department_id');
    var secSelect = document.getElementById('section_id');

    if (!deptSelect || !secSelect) return;

    function filterSections() {
        var selectedDeptId = deptSelect.value;
        var optgroups = secSelect.querySelectorAll('optgroup');

        optgroups.forEach(function(og) {
            var deptId = og.getAttribute('data-dept-id');
            if (!selectedDeptId || deptId === selectedDeptId) {
                og.style.display = '';
                Array.from(og.options).forEach(function(opt) { opt.disabled = false; });
            } else {
                og.style.display = 'none';
                Array.from(og.options).forEach(function(opt) { opt.disabled = true; });
            }
        });
    }

    deptSelect.addEventListener('change', function() {
        filterSections();
        // If current selected section does not belong to new dept, clear section selection
        var selectedOpt = secSelect.options[secSelect.selectedIndex];
        if (selectedOpt && selectedOpt.disabled) {
            secSelect.value = '';
        }
    });

    filterSections();
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
?>
