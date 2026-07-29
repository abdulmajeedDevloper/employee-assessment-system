<?php
$pageTitle = trans('access_denied');
$isAuthenticatedLayout = isset($admin) && is_array($admin);
http_response_code(403);
ob_start();
?>

<div class="card-box" style="text-align: center; max-width: 500px; margin: 40px auto;">
    <h1 style="color: #d9534f; font-size: 3rem; margin-bottom: 10px;">403</h1>
    <h2><?= e(trans('access_denied')) ?></h2>
    <p style="margin: 20px 0; color: #666;"><?= e(trans('access_denied_description')) ?></p>


    <?php if ($isAuthenticatedLayout): ?>
        <a href="/admin" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 10px 20px;">
            <?= e(trans('return_to_dashboard')) ?>
        </a>
    <?php else: ?>
        <a href="/admin/login" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 10px 20px;">
            <?= e(trans('sign_in')) ?>
        </a>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/admin.php';
?>
