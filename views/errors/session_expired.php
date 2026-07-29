<?php
$pageTitle = trans('session_expired_title') . ' - ' . trans('app_name');
ob_start();
?>

<div class="card-box" style="text-align: center;">
    <h1 class="card-title"><?= e(trans('session_expired_title')) ?></h1>
    <p class="card-subtitle" style="margin-bottom: 2rem;"><?= e(trans('session_expired_message')) ?></p>
    <a href="/employee/login" class="btn-primary" style="display: inline-block; text-decoration: none; width: auto; padding: 0.75rem 2rem;"><?= e(trans('login')) ?></a>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
