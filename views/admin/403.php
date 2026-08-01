<?php
$pageTitle = trans('access_denied');
$isAuthenticatedLayout = isset($admin) && is_array($admin);
http_response_code(403);
ob_start();
?>

<div class="card-box admin-inline-4a6f895752">
    <h1 class="error-403-title">403</h1>
    <h2><?= e(trans('access_denied')) ?></h2>
    <p class="error-403-text"><?= e(trans('access_denied_description')) ?></p>


    <?php if ($isAuthenticatedLayout): ?>
        <a href="/admin" class="btn-primary admin-inline-e08c55ed89">
            <?= e(trans('return_to_dashboard')) ?>
        </a>
    <?php else: ?>
        <a href="/admin/login" class="btn-primary admin-inline-e08c55ed89">
            <?= e(trans('sign_in')) ?>
        </a>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/admin.php';
