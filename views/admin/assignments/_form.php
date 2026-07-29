<?php
$editing = isset($assignment) && is_array($assignment);
$currentLang = $_SESSION['lang'] ?? 'ar';
?>
<style>
.assignment-form-card{background:rgba(30,41,59,.85);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1.5rem;max-width:900px;margin:0 auto}.assignment-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.assignment-field label{display:block;color:#cbd5e1;font-weight:600;margin-bottom:.4rem}.assignment-input{width:100%;box-sizing:border-box;padding:.72rem .8rem;border:1px solid rgba(148,163,184,.3);border-radius:8px;background:rgba(15,23,42,.72);color:#f8fafc}.assignment-help{color:#94a3b8;font-size:.82rem;margin-top:.35rem}.assignment-error{color:#fca5a5;font-size:.82rem;margin-top:.3rem}.assignment-readonly{padding:.8rem;border:1px solid rgba(148,163,184,.2);border-radius:8px;background:rgba(15,23,42,.5);color:#e2e8f0}.assignment-actions{display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1.3rem}@media(max-width:700px){.assignment-grid{grid-template-columns:1fr}}
</style>
<div class="assignment-form-card">
    <?php if (!empty($formMessage)): ?><div class="alert-box alert-danger"><?= e($formMessage) ?></div><?php endif; ?>
    <form method="POST" action="<?= $editing ? '/admin/assignments/edit' : '/admin/assignments/create' ?>" novalidate>
        <?= csrf_field() ?>
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int)$assignment['id'] ?>">
            <input type="hidden" name="edit_token" value="<?= e($editToken) ?>">
        <?php endif; ?>

        <div class="assignment-grid">
            <div class="assignment-field">
                <label for="employee_id"><?= e(trans('employee')) ?></label>
                <?php if ($editing): ?>
                    <div class="assignment-readonly"><?= e((string)$assignment['employee_name']) ?><?php if (!empty($assignment['employee_number'])): ?> — <bdi dir="ltr"><?= e((string)$assignment['employee_number']) ?></bdi><?php endif; ?></div>
                    <input type="hidden" name="employee_id" value="<?= (int)$assignment['employee_id'] ?>">
                <?php else: ?>
                    <select id="employee_id" name="employee_id" class="assignment-input" required>
                        <option value=""><?= e(trans('select_employee')) ?></option>
                        <?php foreach ($employees as $employee): $dept=$currentLang==='en'?$employee['department_name_en']:$employee['department_name_ar']; ?>
                            <option value="<?= (int)$employee['id'] ?>" <?= (int)($old['employee_id'] ?? 0)===(int)$employee['id']?'selected':'' ?>><?= e($employee['full_name'] . (!empty($employee['employee_number']) ? ' — '.$employee['employee_number'] : '') . ' — ' . $dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?php if (isset($errors['employee_id'])): ?><div class="assignment-error"><?= e($errors['employee_id']) ?></div><?php endif; ?>
            </div>

            <div class="assignment-field">
                <label for="test_id"><?= e(trans('assessment')) ?></label>
                <?php if ($editing): ?>
                    <?php $testName=$currentLang==='en'?$assignment['test_name_en']:$assignment['test_name_ar']; ?>
                    <div class="assignment-readonly"><bdi dir="ltr"><?= e((string)$assignment['test_code']) ?></bdi> — <?= e((string)$testName) ?></div>
                    <input type="hidden" name="test_id" value="<?= (int)$assignment['test_id'] ?>">
                <?php else: ?>
                    <select id="test_id" name="test_id" class="assignment-input" required>
                        <option value=""><?= e(trans('select_assessment')) ?></option>
                        <?php foreach ($tests as $test): $name=$currentLang==='en'?$test['name_en']:$test['name_ar']; ?>
                            <option value="<?= (int)$test['id'] ?>" <?= (int)($old['test_id'] ?? 0)===(int)$test['id']?'selected':'' ?>><?= e($test['code'] . ' — ' . $name) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?php if (isset($errors['test_id'])): ?><div class="assignment-error"><?= e($errors['test_id']) ?></div><?php endif; ?>
            </div>

            <div class="assignment-field">
                <label for="max_attempts"><?= e(trans('max_attempts')) ?></label>
                <input id="max_attempts" name="max_attempts" type="number" min="1" max="20" class="assignment-input" value="<?= e((string)($old['max_attempts'] ?? '1')) ?>" required>
                <div class="assignment-help"><?= e(trans('max_attempts_help')) ?></div>
                <?php if (isset($errors['max_attempts'])): ?><div class="assignment-error"><?= e($errors['max_attempts']) ?></div><?php endif; ?>
            </div>

            <div class="assignment-field">
                <label><?= e(trans('assignment_initial_status')) ?></label>
                <div class="assignment-readonly"><?= e(trans('assignment_status_pending')) ?></div>
                <div class="assignment-help"><?= e(trans('assignment_status_automatic_help')) ?></div>
            </div>

            <div class="assignment-field">
                <label for="available_from"><?= e(trans('available_from')) ?></label>
                <input id="available_from" name="available_from" type="datetime-local" class="assignment-input" value="<?= e((string)($old['available_from'] ?? '')) ?>">
                <div class="assignment-help"><?= e(trans('assignment_availability_optional')) ?></div>
                <?php if (isset($errors['available_from'])): ?><div class="assignment-error"><?= e($errors['available_from']) ?></div><?php endif; ?>
            </div>

            <div class="assignment-field">
                <label for="available_until"><?= e(trans('available_until')) ?></label>
                <input id="available_until" name="available_until" type="datetime-local" class="assignment-input" value="<?= e((string)($old['available_until'] ?? '')) ?>">
                <div class="assignment-help"><?= e(trans('assignment_availability_optional')) ?></div>
                <?php if (isset($errors['available_until'])): ?><div class="assignment-error"><?= e($errors['available_until']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="assignment-actions">
            <button type="submit" class="btn btn-primary" style="width:auto;margin:0;padding:.7rem 1.2rem"><?= e(trans($editing ? 'save_changes' : 'create_assignment')) ?></button>
            <a href="<?= $editing ? '/admin/assignments/view?id='.(int)$assignment['id'] : '/admin/assignments' ?>" class="nav-link"><?= e(trans('cancel')) ?></a>
        </div>
    </form>
</div>
