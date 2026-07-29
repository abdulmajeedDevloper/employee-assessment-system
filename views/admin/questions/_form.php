<?php
$isEdit = $isEdit ?? false;
$formAction = $isEdit ? '/admin/questions/edit' : '/admin/questions/create';
$optionRows = is_array($old['options'] ?? null) ? array_values($old['options']) : [];
while (count($optionRows) < 2) {
    $optionRows[] = ['text_ar' => '', 'text_en' => ''];
}
$selectedTestId = (string)($old['test_id'] ?? '');
$selectedCategoryId = (string)($old['category_id'] ?? '');
?>
<style>
.question-form-shell{width:100%;max-width:1050px;margin:0 auto;display:grid;gap:1rem}.form-card{background:var(--card-bg,rgba(30,41,59,.85));border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1.4rem}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.field{display:grid;gap:.4rem}.field-full{grid-column:1/-1}.field label{color:#cbd5e1;font-size:.88rem;font-weight:650}.input-field{width:100%;box-sizing:border-box;padding:.72rem .8rem;border:1px solid rgba(148,163,184,.3);border-radius:8px;background:rgba(15,23,42,.72);color:#f8fafc}.input-field:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.2)}textarea.input-field{min-height:120px;resize:vertical}.field-error{color:#fca5a5;font-size:.8rem}.option-editor{display:grid;gap:.85rem}.option-row{display:grid;grid-template-columns:56px 1fr 1fr auto;gap:.75rem;align-items:start;padding:1rem;border:1px solid rgba(148,163,184,.18);border-radius:10px;background:rgba(15,23,42,.45)}.option-letter{display:grid;justify-items:center;gap:.4rem;color:#93c5fd;font-weight:800}.option-letter input{width:1.1rem;height:1.1rem}.option-remove{border:1px solid rgba(239,68,68,.4);background:rgba(239,68,68,.1);color:#fca5a5;border-radius:7px;padding:.55rem .65rem;cursor:pointer}.form-actions{display:flex;gap:.7rem;flex-wrap:wrap;align-items:center}.secondary-link{display:inline-flex;padding:.65rem 1rem;border:1px solid rgba(148,163,184,.28);border-radius:8px;color:#cbd5e1;text-decoration:none}.notice{padding:.85rem 1rem;border-radius:8px;border:1px solid rgba(245,158,11,.35);background:rgba(245,158,11,.09);color:#fde68a}.checkbox-line{display:flex;gap:.55rem;align-items:center;color:#e2e8f0}.checkbox-line input{width:1.1rem;height:1.1rem}@media(max-width:760px){.form-grid{grid-template-columns:1fr}.field-full{grid-column:auto}.option-row{grid-template-columns:44px 1fr}.option-row .option-remove{grid-column:1/-1}.option-row .field{grid-column:2}}
</style>
<div class="question-form-shell">
    <div class="form-card">
        <h1 style="margin:0;color:#f8fafc;"><?= e(trans($isEdit ? 'edit_question' : 'create_question')) ?></h1>
        <p style="margin:.45rem 0 0;color:#94a3b8;"><?= e(trans('question_form_description')) ?></p>
    </div>

    <?php if (!empty($formMessage)): ?><div class="notice"><?= e($formMessage) ?></div><?php endif; ?>
    <?php if (!empty($errors['options'])): ?><div class="alert-box alert-danger"><?= e($errors['options']) ?></div><?php endif; ?>

    <form action="<?= e($formAction) ?>" method="POST" class="form-card" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$question['id'] ?>">
            <input type="hidden" name="edit_token" value="<?= e($editToken) ?>">
            <input type="hidden" name="test_id" value="<?= e($selectedTestId) ?>">
        <?php endif; ?>
        <input type="hidden" name="type" value="single_choice">

        <div class="form-grid">
            <div class="field">
                <label for="test_id_display"><?= e(trans('assessment')) ?></label>
                <?php if ($isEdit): ?>
                    <?php $testLabel=''; foreach($tests as $test){if((int)$test['id']===(int)$selectedTestId){$testLabel=$test['code'].' — '.(($_SESSION['lang']??'ar')==='en'?$test['name_en']:$test['name_ar']);break;}} ?>
                    <input id="test_id_display" class="input-field" value="<?= e($testLabel) ?>" disabled>
                <?php else: ?>
                    <select id="test_id_display" name="test_id" class="input-field" required>
                        <option value=""><?= e(trans('select_assessment')) ?></option>
                        <?php foreach ($tests as $test): $label=($_SESSION['lang']??'ar')==='en'?$test['name_en']:$test['name_ar']; ?>
                            <option value="<?= (int)$test['id'] ?>" <?= (int)$selectedTestId===(int)$test['id']?'selected':'' ?>><?= e($test['code'].' — '.$label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?php if (!empty($errors['test_id'])): ?><span class="field-error"><?= e($errors['test_id']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="category_id"><?= e(trans('category')) ?></label>
                <select id="category_id" name="category_id" class="input-field" required>
                    <option value=""><?= e(trans('select_category')) ?></option>
                    <?php foreach ($categories as $category): $label=($_SESSION['lang']??'ar')==='en'?$category['name_en']:$category['name_ar']; ?>
                        <option value="<?= (int)$category['id'] ?>" data-test-id="<?= (int)$category['test_id'] ?>" <?= (int)$selectedCategoryId===(int)$category['id']?'selected':'' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['category_id'])): ?><span class="field-error"><?= e($errors['category_id']) ?></span><?php endif; ?>
                <a href="/admin/questions/categories/create<?= $selectedTestId!==''?'?test_id='.(int)$selectedTestId:'' ?>" class="secondary-link" style="width:max-content;"><?= e(trans('create_question_category')) ?></a>
            </div>

            <div class="field field-full">
                <label for="text_ar"><?= e(trans('question_text_ar')) ?></label>
                <textarea id="text_ar" name="text_ar" class="input-field" maxlength="5000" required><?= e((string)($old['text_ar'] ?? '')) ?></textarea>
                <?php if (!empty($errors['text_ar'])): ?><span class="field-error"><?= e($errors['text_ar']) ?></span><?php endif; ?>
            </div>
            <div class="field field-full">
                <label for="text_en"><?= e(trans('question_text_en')) ?></label>
                <textarea id="text_en" name="text_en" class="input-field" maxlength="5000" dir="ltr" required><?= e((string)($old['text_en'] ?? '')) ?></textarea>
                <?php if (!empty($errors['text_en'])): ?><span class="field-error"><?= e($errors['text_en']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="difficulty"><?= e(trans('difficulty')) ?></label>
                <select id="difficulty" name="difficulty" class="input-field">
                    <?php foreach(['easy','medium','hard'] as $difficulty): ?><option value="<?= e($difficulty) ?>" <?= ($old['difficulty']??'medium')===$difficulty?'selected':'' ?>><?= e(trans($difficulty)) ?></option><?php endforeach; ?>
                </select>
                <?php if (!empty($errors['difficulty'])): ?><span class="field-error"><?= e($errors['difficulty']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="score"><?= e(trans('question_score')) ?></label>
                <input id="score" name="score" class="input-field" inputmode="decimal" value="<?= e((string)($old['score'] ?? '1.00')) ?>" required>
                <?php if (!empty($errors['score'])): ?><span class="field-error"><?= e($errors['score']) ?></span><?php endif; ?>
            </div>
            <div class="field field-full">
                <label class="checkbox-line"><input type="checkbox" name="is_active" value="1" <?= (int)($old['is_active']??0)===1?'checked':'' ?>> <?= e(trans('activate_question')) ?></label>
                <?php if (!empty($errors['is_active'])): ?><span class="field-error"><?= e($errors['is_active']) ?></span><?php endif; ?>
            </div>
        </div>

        <hr style="border:0;border-top:1px solid rgba(148,163,184,.18);margin:1.4rem 0;">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;">
            <div><h2 style="margin:0;color:#60a5fa;"><?= e(trans('answer_options')) ?></h2><small style="color:#94a3b8;"><?= e(trans('answer_options_help')) ?></small></div>
            <button type="button" id="add-option" class="secondary-link" style="background:transparent;cursor:pointer;"><?= e(trans('add_option')) ?></button>
        </div>
        <?php if (!empty($errors['correct_option'])): ?><div class="field-error" style="margin-bottom:.7rem;"><?= e($errors['correct_option']) ?></div><?php endif; ?>
        <div id="option-editor" class="option-editor">
            <?php foreach ($optionRows as $index => $option): ?>
                <div class="option-row" data-option-row>
                    <label class="option-letter"><span data-option-letter><?= e(chr(65+$index)) ?></span><input type="radio" name="correct_option" value="<?= $index ?>" <?= (string)($old['correct_option']??'')===(string)$index?'checked':'' ?> aria-label="<?= e(trans('correct_answer')) ?>"></label>
                    <div class="field"><label><?= e(trans('option_text_ar')) ?></label><textarea class="input-field" name="options[<?= $index ?>][text_ar]" maxlength="2000" required><?= e((string)($option['text_ar']??'')) ?></textarea><?php if (!empty($errors['options.'.$index.'.text_ar'])): ?><span class="field-error"><?= e($errors['options.'.$index.'.text_ar']) ?></span><?php endif; ?></div>
                    <div class="field"><label><?= e(trans('option_text_en')) ?></label><textarea class="input-field" name="options[<?= $index ?>][text_en]" maxlength="2000" dir="ltr" required><?= e((string)($option['text_en']??'')) ?></textarea><?php if (!empty($errors['options.'.$index.'.text_en'])): ?><span class="field-error"><?= e($errors['options.'.$index.'.text_en']) ?></span><?php endif; ?></div>
                    <button type="button" class="option-remove" data-remove-option><?= e(trans('remove')) ?></button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-actions" style="margin-top:1.4rem;">
            <button type="submit" class="btn btn-primary" style="width:auto;margin:0;padding:.7rem 1.35rem;"><?= e(trans($isEdit?'save_changes':'create_question')) ?></button>
            <a href="<?= $isEdit?'/admin/questions/view?id='.(int)$question['id']:'/admin/questions' ?>" class="secondary-link"><?= e(trans('cancel')) ?></a>
        </div>
    </form>
</div>
<script>
(function(){
    'use strict';
    const editor=document.getElementById('option-editor');
    const addButton=document.getElementById('add-option');
    const testSelect=document.getElementById('test_id_display');
    const categorySelect=document.getElementById('category_id');
    function renumber(){
        const rows=[...editor.querySelectorAll('[data-option-row]')];
        rows.forEach((row,index)=>{
            row.querySelector('[data-option-letter]').textContent=String.fromCharCode(65+index);
            const radio=row.querySelector('input[type="radio"]'); radio.value=String(index);
            row.querySelectorAll('textarea').forEach((field,fieldIndex)=>{field.name=`options[${index}][${fieldIndex===0?'text_ar':'text_en'}]`;});
            row.querySelector('[data-remove-option]').disabled=rows.length<=2;
        });
        addButton.disabled=rows.length>=8;
    }
    function addOption(){
        const rows=editor.querySelectorAll('[data-option-row]'); if(rows.length>=8)return;
        const template=rows[rows.length-1].cloneNode(true);
        template.querySelectorAll('textarea').forEach(field=>field.value='');
        const radio=template.querySelector('input[type="radio"]'); radio.checked=false;
        template.querySelectorAll('.field-error').forEach(node=>node.remove());
        editor.appendChild(template); renumber();
    }
    editor.addEventListener('click',event=>{const button=event.target.closest('[data-remove-option]');if(!button)return;const rows=editor.querySelectorAll('[data-option-row]');if(rows.length<=2)return;button.closest('[data-option-row]').remove();renumber();});
    addButton.addEventListener('click',addOption);
    function filterCategories(){
        if(!testSelect||testSelect.disabled)return;
        const testId=testSelect.value;
        [...categorySelect.options].forEach((option,index)=>{if(index===0)return;const visible=testId!==''&&option.dataset.testId===testId;option.hidden=!visible;option.disabled=!visible;if(!visible&&option.selected)categorySelect.value='';});
    }
    if(testSelect&&!testSelect.disabled){testSelect.addEventListener('change',filterCategories);filterCategories();}
    renumber();
})();
</script>
