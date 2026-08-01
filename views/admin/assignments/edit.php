<?php
$pageTitle = trans('edit_assignment');
$isAuthenticatedLayout = true;
ob_start();
?>
<div class="assignment-page-intro">
    <h1 class="admin-welcome-title"><?= e(trans('edit_assignment')) ?></h1>
    <p class="assignment-page-description"><?= e(trans('assignment_edit_description')) ?></p>
</div>
<?php require __DIR__ . '/_form.php'; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/admin.php'; ?>
