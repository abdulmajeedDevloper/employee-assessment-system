<?php
$currentLang = $currentLang ?? $_SESSION['lang'] ?? 'ar';

$content = (static function() use ($errors, $formData, $flashError, $currentLang): string {
    ob_start();
    ?>
    <div class="admin-account-management">
        <header class="page-header">
            <div class="header-title-group">
                <a href="/admin/admin-users" class="btn-back" aria-label="<?= e(trans('back')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h1><?= e(trans('add_admin')) ?></h1>
                    <p><?= e(trans('add_admin_subtitle')) ?></p>
                </div>
            </div>
        </header>

        <?php if ($flashError): ?>
            <div class="alert alert-danger" role="alert">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4M12 16h.01M22 12A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z"/></svg>
                <span><?= e($flashError) ?></span>
            </div>
        <?php endif; ?>

        <section class="form-card">
            <form action="/admin/admin-users/create" method="POST" class="admin-form" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid">
                    <!-- Display Name -->
                    <div class="form-group <?= isset($errors['display_name']) ? 'has-error' : '' ?>">
                        <label for="display_name" class="form-label required"><?= e(trans('display_name')) ?></label>
                        <input type="text" id="display_name" name="display_name" value="<?= e($formData['display_name'] ?? '') ?>" class="form-input" required autofocus placeholder="e.g. Abdullah Ahmed">
                        <?php if (isset($errors['display_name'])): ?>
                            <span class="error-message"><?= e($errors['display_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Username -->
                    <div class="form-group <?= isset($errors['username']) ? 'has-error' : '' ?>">
                        <label for="username" class="form-label required"><?= e(trans('username')) ?></label>
                        <input type="text" id="username" name="username" value="<?= e($formData['username'] ?? '') ?>" class="form-input" dir="ltr" required placeholder="e.g. abdullah_admin">
                        <?php if (isset($errors['username'])): ?>
                            <span class="error-message"><?= e($errors['username']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Email -->
                    <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                        <label for="email" class="form-label required"><?= e(trans('email')) ?></label>
                        <input type="email" id="email" name="email" value="<?= e($formData['email'] ?? '') ?>" class="form-input" dir="ltr" required placeholder="name@organization.gov.sa">
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-message"><?= e($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Role -->
                    <div class="form-group <?= isset($errors['role']) ? 'has-error' : '' ?>">
                        <label for="role" class="form-label required"><?= e(trans('role')) ?></label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="admin" <?= ($formData['role'] ?? 'admin') === 'admin' ? 'selected' : '' ?>><?= e(trans('admin')) ?></option>
                            <option value="super_admin" <?= ($formData['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>><?= e(trans('super_admin')) ?></option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <span class="error-message"><?= e($errors['role']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div class="form-group <?= isset($errors['password']) ? 'has-error' : '' ?>">
                        <label for="password" class="form-label required"><?= e(trans('password')) ?></label>
                        <input type="password" id="password" name="password" class="form-input" dir="ltr" required autocomplete="new-password">
                        <small class="field-help"><?= e(trans('password_policy_help')) ?></small>
                        <?php if (isset($errors['password'])): ?>
                            <span class="error-message"><?= e($errors['password']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group <?= isset($errors['password_confirm']) ? 'has-error' : '' ?>">
                        <label for="password_confirm" class="form-label required"><?= e(trans('confirm_password')) ?></label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-input" dir="ltr" required autocomplete="new-password">
                        <?php if (isset($errors['password_confirm'])): ?>
                            <span class="error-message"><?= e($errors['password_confirm']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Active Status -->
                    <div class="form-group">
                        <label for="is_active" class="form-label required"><?= e(trans('status')) ?></label>
                        <select id="is_active" name="is_active" class="form-select">
                            <option value="1" <?= ($formData['is_active'] ?? '1') === '1' ? 'selected' : '' ?>><?= e(trans('active')) ?></option>
                            <option value="0" <?= ($formData['is_active'] ?? '') === '0' ? 'selected' : '' ?>><?= e(trans('inactive')) ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                        <span><?= e(trans('save')) ?></span>
                    </button>
                    <a href="/admin/admin-users" class="btn btn-secondary"><?= e(trans('cancel')) ?></a>
                </div>
            </form>
        </section>
    </div>
    <?php
    return ob_get_clean();
})();

require __DIR__ . '/../../layouts/admin.php';
