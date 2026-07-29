<?php
$pageTitle = trans('create_question_category');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
ob_start();
?>
<style>.category-shell{width:100%;max-width:720px;margin:0 auto}.category-card{background:var(--card-bg,rgba(30,41,59,.85));border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1.5rem}.field{display:grid;gap:.4rem;margin-bottom:1rem}.field label{color:#cbd5e1}.input-field{width:100%;box-sizing:border-box;padding:.72rem .8rem;border:1px solid rgba(148,163,184,.3);border-radius:8px;background:rgba(15,23,42,.72);color:#f8fafc}.field-error{color:#fca5a5;font-size:.8rem}.actions{display:flex;gap:.7rem;flex-wrap:wrap}.secondary-link{display:inline-flex;padding:.65rem 1rem;border:1px solid rgba(148,163,184,.28);border-radius:8px;color:#cbd5e1;text-decoration:none}</style>
<div class="category-shell"><div class="category-card">
<h1 style="margin-top:0;color:#f8fafc;"><?= e(trans('create_question_category')) ?></h1>
<p style="color:#94a3b8;"><?= e(trans('question_category_description')) ?></p>
<form action="/admin/questions/categories/create" method="POST">
<?= csrf_field() ?>
<div class="field"><label for="test_id"><?= e(trans('assessment')) ?></label><select id="test_id" name="test_id" class="input-field" required><option value=""><?= e(trans('select_assessment')) ?></option><?php foreach($tests as $test):$label=$currentLang==='en'?$test['name_en']:$test['name_ar'];?><option value="<?= (int)$test['id'] ?>" <?= (int)($old['test_id']??0)===(int)$test['id']?'selected':'' ?>><?= e($test['code'].' — '.$label) ?></option><?php endforeach;?></select><?php if(!empty($errors['test_id'])):?><span class="field-error"><?= e($errors['test_id']) ?></span><?php endif;?></div>
<div class="field"><label for="name_ar"><?= e(trans('category_name_ar')) ?></label><input id="name_ar" name="name_ar" maxlength="255" class="input-field" value="<?= e((string)($old['name_ar']??'')) ?>" required><?php if(!empty($errors['name_ar'])):?><span class="field-error"><?= e($errors['name_ar']) ?></span><?php endif;?></div>
<div class="field"><label for="name_en"><?= e(trans('category_name_en')) ?></label><input id="name_en" name="name_en" maxlength="255" dir="ltr" class="input-field" value="<?= e((string)($old['name_en']??'')) ?>" required><?php if(!empty($errors['name_en'])):?><span class="field-error"><?= e($errors['name_en']) ?></span><?php endif;?></div>
<div class="actions"><button class="btn btn-primary" style="width:auto;margin:0;padding:.7rem 1.25rem;" type="submit"><?= e(trans('create_category')) ?></button><a class="secondary-link" href="/admin/questions/create<?= !empty($old['test_id'])?'?test_id='.(int)$old['test_id']:'' ?>"><?= e(trans('cancel')) ?></a></div>
</form></div></div>
<?php $content=ob_get_clean();require __DIR__.'/../../layouts/admin.php';
