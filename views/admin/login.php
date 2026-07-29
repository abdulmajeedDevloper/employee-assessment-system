<?php
$pageTitle = trans('admin_login');
$isAuthenticatedLayout = false;
ob_start();
?>

<div class="card-box">
    <h1 class="card-title"><?= e(trans('admin_login')) ?></h1>
    <p class="card-subtitle"><?= e(trans('administration')) ?></p>

    <?php if (!empty($expiredMessage)): ?>
        <div class="alert-box alert-warning">
            <?= e($expiredMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert-box alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form action="/admin/login" method="POST" autocomplete="off">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="username" class="form-label"><?= e(trans('username_or_email')) ?></label>
            <input type="text" id="username" name="username" class="form-control"
                   required autofocus autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password" class="form-label"><?= e(trans('password')) ?></label>
            <input type="password" id="password" name="password" class="form-control"
                   required autocomplete="current-password">
        </div>

        <button type="submit" class="btn-primary"><?= e(trans('sign_in')) ?></button>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/admin.php';
?>
