<?php
$pageTitle = trans('login') . ' - ' . trans('app_name');
ob_start();
?>

<div class="card-box">
    <h1 class="card-title"><?= e(trans('login')) ?></h1>
    <p class="card-subtitle"><?= e(trans('enter_national_id')) ?></p>

    <?php if (!empty($error)): ?>
        <div class="alert-box alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form action="/employee/login" method="POST" autocomplete="off">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="national_id" class="form-label"><?= e(trans('national_id')) ?></label>
            <input type="text" id="national_id" name="national_id" class="form-control"
                   placeholder="1000000000" maxlength="10" pattern="[0-9]{10}"
                   inputmode="numeric" required autofocus autocomplete="off">
        </div>

        <button type="submit" class="btn-primary"><?= e(trans('login')) ?></button>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
