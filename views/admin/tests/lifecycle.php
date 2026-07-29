<?php
$pageTitle = trans('manage_assessment_lifecycle');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
$testName = $currentLang === 'en' ? $test['name_en'] : $test['name_ar'];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$selectedStatus = (string)($old['is_active'] ?? (string)(int)$test['is_active']);
ob_start();
?>
<style>
    .lifecycle-shell { display: grid; gap: 1.25rem; width: 100%; max-width: 1200px; margin: 0 auto; }
    .lifecycle-header { background: var(--card-bg, rgba(30,41,59,.85)); border: 1px solid rgba(255,255,255,.1); border-radius: 12px; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .lifecycle-title { margin: 0; color: #f8fafc; font-size: 1.7rem; }
    .section-card { background: var(--card-bg, rgba(30,41,59,.85)); border: 1px solid rgba(255,255,255,.1); border-radius: 12px; padding: 1.5rem; }
    .section-title { margin: 0 0 1.1rem; padding-bottom: .65rem; color: #60a5fa; border-bottom: 1px solid rgba(255,255,255,.1); font-size: 1.1rem; }
    .input-field { width: 100%; box-sizing: border-box; padding: .8rem .9rem; border-radius: 8px; border: 1px solid rgba(148,163,184,.3); background: rgba(15,23,42,.72); color: #f8fafc; }
    .input-field:focus-visible { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.28); }
    .btn-secondary { display: inline-flex; align-items: center; justify-content: center; padding: .75rem 1.1rem; border-radius: 8px; border: 1px solid rgba(148,163,184,.35); background: rgba(51,65,85,.6); color: #e2e8f0; }
    .alert-info { background: rgba(59,130,246,.14); border: 1px solid rgba(59,130,246,.35); color: #93c5fd; }
    .lifecycle-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
    .lifecycle-metric { background: rgba(15,23,42,.55); border: 1px solid rgba(148,163,184,.18); border-radius: 10px; padding: 1rem; }
    .lifecycle-metric strong { display: block; font-size: 1.65rem; color: #fff; margin-top: .35rem; }
    .lifecycle-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 1rem; }
    .field-group { display: grid; gap: .45rem; }
    .field-label { color: #e2e8f0; font-weight: 600; }
    .field-help { color: #94a3b8; font-size: .82rem; line-height: 1.5; }
    .field-error { color: #fca5a5; font-size: .85rem; }
    .readiness-good { border-color: rgba(34,197,94,.4); background: rgba(34,197,94,.08); }
    .readiness-bad { border-color: rgba(245,158,11,.45); background: rgba(245,158,11,.08); }
    @media (max-width: 850px) { .lifecycle-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
    @media (max-width: 620px) { .lifecycle-grid, .lifecycle-form-grid { grid-template-columns: 1fr; } }
</style>

<div class="lifecycle-shell">
    <div class="lifecycle-header">
        <div>
            <h1 class="lifecycle-title"><?= e(trans('manage_assessment_lifecycle')) ?></h1>
            <p style="color:#94a3b8;margin-top:.45rem;">
                <?= e($testName) ?> — <bdi dir="ltr"><?= e($test['code']) ?></bdi>
            </p>
        </div>
        <a href="/admin/tests/view?id=<?= (int)$test['id'] ?>" class="btn btn-secondary" style="text-decoration:none;">
            <?= e(trans('back_to_test_details')) ?>
        </a>
    </div>

    <?php if (!empty($conflictMessage)): ?>
        <div class="alert-box alert-info"><?= e($conflictMessage) ?></div>
    <?php endif; ?>

    <section class="section-card <?= !empty($readiness['is_ready']) ? 'readiness-good' : 'readiness-bad' ?>">
        <h2 class="section-title"><?= e(trans('activation_readiness')) ?></h2>
        <div class="lifecycle-grid">
            <div class="lifecycle-metric">
                <span><?= e(trans('required_questions')) ?></span>
                <strong><bdi dir="ltr"><?= (int)$readiness['required_question_count'] ?></bdi></strong>
            </div>
            <div class="lifecycle-metric">
                <span><?= e(trans('active_questions')) ?></span>
                <strong><bdi dir="ltr"><?= (int)$readiness['active_question_count'] ?></bdi></strong>
            </div>
            <div class="lifecycle-metric">
                <span><?= e(trans('valid_questions')) ?></span>
                <strong><bdi dir="ltr"><?= (int)$readiness['valid_question_count'] ?></bdi></strong>
            </div>
            <div class="lifecycle-metric">
                <span><?= e(trans('invalid_questions')) ?></span>
                <strong><bdi dir="ltr"><?= (int)$readiness['invalid_question_count'] ?></bdi></strong>
            </div>
        </div>
        <p style="margin-top:1rem;color:<?= !empty($readiness['is_ready']) ? '#86efac' : '#fcd34d' ?>;">
            <?= e(trans(!empty($readiness['is_ready']) ? 'assessment_ready_for_activation' : 'assessment_not_ready_for_activation')) ?>
        </p>
    </section>

    <form method="POST" action="/admin/tests/lifecycle" class="section-card" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$test['id'] ?>">
        <input type="hidden" name="edit_token" value="<?= e($editToken) ?>">

        <h2 class="section-title"><?= e(trans('activation_and_availability')) ?></h2>
        <div class="lifecycle-form-grid">
            <div class="field-group" style="grid-column:1/-1;">
                <label for="is_active" class="field-label"><?= e(trans('assessment_status')) ?></label>
                <select id="is_active" name="is_active" class="input-field" aria-describedby="is_active_help<?= isset($errors['is_active']) ? ' is_active_error' : '' ?>">
                    <option value="0" <?= $selectedStatus === '0' ? 'selected' : '' ?>><?= e(trans('inactive')) ?></option>
                    <option value="1" <?= $selectedStatus === '1' ? 'selected' : '' ?>><?= e(trans('active')) ?></option>
                </select>
                <div id="is_active_help" class="field-help"><?= e(trans('activation_requires_valid_questions')) ?></div>
                <?php if (isset($errors['is_active'])): ?>
                    <div id="is_active_error" class="field-error" role="alert"><?= e($errors['is_active']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field-group">
                <label for="available_from" class="field-label"><?= e(trans('available_from')) ?></label>
                <input id="available_from" name="available_from" type="datetime-local" class="input-field"
                       value="<?= e((string)($old['available_from'] ?? '')) ?>">
                <div class="field-help"><?= e(trans('availability_timezone_notice', ['timezone' => (string)env('APP_TIMEZONE', 'Asia/Riyadh')])) ?></div>
                <?php if (isset($errors['available_from'])): ?>
                    <div class="field-error" role="alert"><?= e($errors['available_from']) ?></div>
                <?php endif; ?>
            </div>

            <div class="field-group">
                <label for="available_until" class="field-label"><?= e(trans('available_until')) ?></label>
                <input id="available_until" name="available_until" type="datetime-local" class="input-field"
                       value="<?= e((string)($old['available_until'] ?? '')) ?>">
                <div class="field-help"><?= e(trans('availability_optional_notice')) ?></div>
                <?php if (isset($errors['available_until'])): ?>
                    <div class="field-error" role="alert"><?= e($errors['available_until']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem;">
            <button type="submit" class="btn btn-primary"><?= e(trans('save_lifecycle')) ?></button>
            <a href="/admin/tests/view?id=<?= (int)$test['id'] ?>" class="btn btn-secondary" style="text-decoration:none;"><?= e(trans('cancel')) ?></a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
