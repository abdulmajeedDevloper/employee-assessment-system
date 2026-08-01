<?php
$pageTitle = trans('create_assignment');
$isAuthenticatedLayout = true;
$assignment = null;
ob_start();
?>
<div class="assignment-page-intro">
    <h1 class="admin-welcome-title"><?= e(trans('create_assignment')) ?></h1>
    <p class="assignment-page-description"><?= e(trans('assignment_form_description')) ?></p>
</div>
<?php require __DIR__ . '/_form.php'; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/admin.php'; ?>
