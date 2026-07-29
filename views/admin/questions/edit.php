<?php
$pageTitle = trans('edit_question');
$isAuthenticatedLayout = true;
$isEdit = true;
ob_start();
require __DIR__ . '/_form.php';
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
