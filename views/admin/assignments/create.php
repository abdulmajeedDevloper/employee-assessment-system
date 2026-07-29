<?php
$pageTitle = trans('create_assignment');
$isAuthenticatedLayout = true;
$assignment = null;
ob_start();
?>
<div style="max-width:900px;margin:0 auto 1rem">
    <h1 class="admin-welcome-title"><?= e(trans('create_assignment')) ?></h1>
    <p style="color:#94a3b8"><?= e(trans('assignment_form_description')) ?></p>
</div>
<?php require __DIR__ . '/_form.php'; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/admin.php'; ?>
