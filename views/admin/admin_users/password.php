<?php
$currentLang = $currentLang ?? $_SESSION['lang'] ?? 'ar';

$content = (static function() use ($targetAdmin, $errors, $flashError, $admin, $currentLang): string {
    ob_start();
    $isSelf = ((int)$targetAdmin['id'] === (int)($admin['id'] ?? 0));
    ?>
    <div class="admin-account-management">
        <header class="page-header">
            <div class="header-title-group">
                <a href="/admin/admin-users" class="btn-back" aria-label="<?= e(trans('back')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h1><?= e(trans('change_password')) ?>: <?= e($targetAdmin['display_name']) ?></h1>
                    <p><?= e(trans('change_password_subtitle')) ?></p>
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
            <form action="/admin/admin-users/password" method="POST" class="admin-form" novalidate autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$targetAdmin['id'] ?>">

                <div class="form-grid">
                    <!-- Current Password Confirmation of Acting Super Admin -->
                    <div class="form-group <?= isset($errors['current_password']) ? 'has-error' : '' ?>">
                        <label for="current_password" class="form-label required">
                            <?= $isSelf ? e(trans('current_password')) : e(trans('acting_admin_current_password_confirm')) ?>
                        </label>
                        <input type="password" id="current_password" name="current_password" class="form-input" dir="ltr" required autocomplete="current-password" autofocus>
                        <small class="field-help">
                            <?= $isSelf ? e(trans('enter_your_current_password_to_confirm')) : e(trans('enter_acting_admin_password_to_authorize')) ?>
                        </small>
                        <?php if (isset($errors['current_password'])): ?>
                            <span class="error-message"><?= e($errors['current_password']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- New Password -->
                    <div class="form-group <?= isset($errors['new_password']) ? 'has-error' : '' ?>">
                        <label for="new_password" class="form-label required"><?= e(trans('new_password')) ?></label>
                        <input type="password" id="new_password" name="new_password" class="form-input" dir="ltr" required autocomplete="new-password">
                        <small class="field-help"><?= e(trans('password_policy_help')) ?></small>
                        <?php if (isset($errors['new_password'])): ?>
                            <span class="error-message"><?= e($errors['new_password']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Confirm New Password -->
                    <div class="form-group <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
                        <label for="confirm_password" class="form-label required"><?= e(trans('confirm_password')) ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" dir="ltr" required autocomplete="new-password">
                        <?php if (isset($errors['confirm_password'])): ?>
                            <span class="error-message"><?= e($errors['confirm_password']) ?></span>
                        <?php endif; ?>
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
