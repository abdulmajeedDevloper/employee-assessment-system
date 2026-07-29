<?php
$currentLang = $_SESSION['lang'] ?? 'ar';
$pageTitle = trans('confirm_profile_title') . ' - ' . trans('app_name');
ob_start();
?>

<div class="card-box" style="max-width: 600px;">
    <h1 class="card-title"><?= e(trans('confirm_profile_title')) ?></h1>
    <p class="card-subtitle"><?= e(trans('profile_incomplete_notice')) ?></p>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert-box alert-danger">
            <?= e($errors['general']) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-box alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <div class="notice-box">
        <?= e(trans('org_managed_notice')) ?>
    </div>

    <form action="/employee/profile" method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="full_name" class="form-label"><?= e(trans('full_name')) ?></label>
            <input type="text" id="full_name" name="full_name" class="form-control"
                   value="<?= e($employee['full_name'] ?? '') ?>" required>
            <?php if (!empty($errors['full_name'])): ?>
                <small style="color: var(--error-text);"><?= e($errors['full_name']) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email" class="form-label"><?= e(trans('email')) ?></label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?= e($employee['email'] ?? '') ?>" required>
            <?php if (!empty($errors['email'])): ?>
                <small style="color: var(--error-text);"><?= e($errors['email']) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="phone" class="form-label"><?= e(trans('phone')) ?></label>
            <input type="tel" id="phone" name="phone" class="form-control"
                   value="<?= e($employee['phone'] ?? '') ?>" placeholder="0512345678" required>
            <?php if (!empty($errors['phone'])): ?>
                <small style="color: var(--error-text);"><?= e($errors['phone']) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="preferred_language" class="form-label"><?= e(trans('select_preferred_language')) ?></label>
            <select id="preferred_language" name="preferred_language" class="form-control">
                <option value="ar" <?= ($employee['preferred_language'] ?? 'ar') === 'ar' ? 'selected' : '' ?>>العربية (RTL)</option>
                <option value="en" <?= ($employee['preferred_language'] ?? 'ar') === 'en' ? 'selected' : '' ?>>English (LTR)</option>
            </select>
        </div>

        <hr style="border-color: var(--card-border); margin: 1.5rem 0;">

        <!-- Organization Controlled Fields (Read-Only Confirmation) -->
        <div class="form-group">
            <label for="department_display" class="form-label"><?= e(trans('department')) ?></label>
            <input type="text" id="department_display" class="form-control"
                   value="<?= e(($currentLang === 'ar' ? $employee['dept_name_ar'] : $employee['dept_name_en']) ?? '') ?>" readonly>
        </div>

        <div class="form-group">
            <label for="section_display" class="form-label"><?= e(trans('section')) ?></label>
            <input type="text" id="section_display" class="form-control"
                   value="<?= e(($currentLang === 'ar' ? $employee['sec_name_ar'] : $employee['sec_name_en']) ?? '') ?>" readonly>
        </div>

        <div class="form-group">
            <label for="job_title_display" class="form-label"><?= e(trans('job_title')) ?></label>
            <input type="text" id="job_title_display" class="form-control"
                   value="<?= e(($currentLang === 'ar' ? $employee['job_name_ar'] : $employee['job_name_en']) ?? '') ?>" readonly>
        </div>

        <button type="submit" class="btn-primary"><?= e(trans('save')) ?> & <?= e(trans('confirm')) ?></button>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
