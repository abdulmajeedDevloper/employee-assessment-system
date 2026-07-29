<?php
$pageTitle = trans('question_details');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
$text = $currentLang === 'en' ? $question['text_en'] : $question['text_ar'];
$testName = $currentLang === 'en' ? $question['test_name_en'] : $question['test_name_ar'];
$categoryName = $currentLang === 'en' ? $question['category_name_en'] : $question['category_name_ar'];
ob_start();
?>
<style>
.q-detail-shell{width:100%;max-width:1000px;margin:0 auto;display:grid;gap:1.2rem}.q-card{background:var(--card-bg,rgba(30,41,59,.85));border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1.5rem}.q-header{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}.q-title{margin:0;color:#f8fafc;font-size:1.5rem;line-height:1.55}.q-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.q-meta-item{padding:.85rem;border:1px solid rgba(148,163,184,.16);border-radius:8px;background:rgba(15,23,42,.45)}.q-meta-item span{display:block;color:#94a3b8;font-size:.78rem}.q-meta-item strong{display:block;color:#f8fafc;margin-top:.3rem}.option-list{display:grid;gap:.9rem}.option-card{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.8rem;align-items:start;padding:1rem;border:1px solid rgba(148,163,184,.18);border-radius:9px;background:rgba(15,23,42,.45)}.option-key{font-family:monospace;color:#93c5fd;font-weight:800}.correct{border-color:rgba(34,197,94,.45);background:rgba(34,197,94,.08)}.badge{display:inline-flex;padding:.25rem .6rem;border-radius:999px;font-size:.76rem;font-weight:700}.badge-good{color:#86efac;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3)}.badge-warn{color:#fcd34d;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3)}.badge-muted{color:#cbd5e1;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.25)}.q-actions,.media-actions{display:flex;gap:.65rem;flex-wrap:wrap;align-items:center}.danger-button{border:1px solid rgba(239,68,68,.4);background:rgba(239,68,68,.1);color:#fca5a5;border-radius:7px;padding:.55rem .8rem;cursor:pointer}.restore-button{border:1px solid rgba(34,197,94,.4);background:rgba(34,197,94,.1);color:#86efac;border-radius:7px;padding:.55rem .8rem;cursor:pointer}.media-preview{display:grid;gap:.8rem}.media-preview img{display:block;max-width:100%;max-height:440px;width:auto;height:auto;object-fit:contain;border-radius:10px;border:1px solid rgba(148,163,184,.2);background:rgba(2,6,23,.65)}.option-media{grid-column:2/-1;display:grid;gap:.75rem;margin-top:.25rem}.option-media img{max-height:260px}.media-form{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap}.media-file{max-width:100%;color:#cbd5e1}.media-help{margin:.45rem 0 0;color:#94a3b8;font-size:.82rem}.media-empty{padding:1rem;border:1px dashed rgba(148,163,184,.3);border-radius:9px;color:#94a3b8}.media-submit{border:1px solid rgba(59,130,246,.45);background:rgba(59,130,246,.14);color:#bfdbfe;border-radius:7px;padding:.55rem .8rem;cursor:pointer}.section-title{color:#60a5fa;margin-top:0}.option-badges{display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end}@media(max-width:720px){.q-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.option-card{grid-template-columns:auto minmax(0,1fr)}.option-badges{grid-column:2;justify-content:flex-start}.option-media{grid-column:1/-1}}@media(max-width:480px){.q-meta{grid-template-columns:1fr}}
</style>
<div class="q-detail-shell">
    <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert-box alert-success"><?= e($_SESSION['flash_success']) ?></div><?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert-box alert-danger"><?= e($_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); endif; ?>
    <?php if (!empty($_SESSION['flash_info'])): ?><div class="alert-box" style="border:1px solid rgba(59,130,246,.35);background:rgba(59,130,246,.1);color:#bfdbfe;"><?= e($_SESSION['flash_info']) ?></div><?php unset($_SESSION['flash_info']); endif; ?>
    <div class="q-card q-header">
        <div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.7rem;">
                <span class="badge <?= (int)$question['is_valid']===1?'badge-good':'badge-warn' ?>"><?= e(trans((int)$question['is_valid']===1?'valid_question':'invalid_question')) ?></span>
                <?php if ($question['archived_at']!==null): ?><span class="badge badge-muted"><?= e(trans('archived')) ?></span><?php elseif ((int)$question['is_active']===1): ?><span class="badge badge-good"><?= e(trans('active')) ?></span><?php else: ?><span class="badge badge-muted"><?= e(trans('inactive')) ?></span><?php endif; ?>
            </div>
            <h1 class="q-title"><?= nl2br(e($text)) ?></h1>
        </div>
        <div class="q-actions">
            <?php if ($canManageQuestions && $question['archived_at']===null): ?><a href="/admin/questions/edit?id=<?= (int)$question['id'] ?>" class="nav-link"><?= e(trans('edit_question')) ?></a><?php endif; ?>
            <?php if ($canManageQuestions && $editToken !== null): ?>
                <?php if ($question['archived_at']===null): ?>
                    <form action="/admin/questions/archive" method="POST" onsubmit="return confirm('<?= e(trans('confirm_archive_question')) ?>');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$question['id'] ?>"><input type="hidden" name="edit_token" value="<?= e($editToken) ?>"><button type="submit" class="danger-button"><?= e(trans('archive_question')) ?></button></form>
                <?php else: ?>
                    <form action="/admin/questions/restore" method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$question['id'] ?>"><input type="hidden" name="edit_token" value="<?= e($editToken) ?>"><button type="submit" class="restore-button"><?= e(trans('restore_question')) ?></button></form>
                <?php endif; ?>
            <?php endif; ?>
            <a href="/admin/questions" class="nav-link"><?= e(trans('back_to_question_bank')) ?></a>
        </div>
    </div>

    <div class="q-card">
        <div class="q-meta">
            <div class="q-meta-item"><span><?= e(trans('assessment')) ?></span><strong><bdi dir="ltr"><?= e($question['test_code']) ?></bdi> — <?= e($testName) ?></strong></div>
            <div class="q-meta-item"><span><?= e(trans('category')) ?></span><strong><?= e($categoryName) ?></strong></div>
            <div class="q-meta-item"><span><?= e(trans('difficulty')) ?></span><strong><?= e(trans($question['difficulty'])) ?></strong></div>
            <div class="q-meta-item"><span><?= e(trans('question_score')) ?></span><strong><bdi dir="ltr"><?= e(number_format((float)$question['score'],2)) ?></bdi></strong></div>
            <div class="q-meta-item"><span><?= e(trans('question_type')) ?></span><strong><?= e(trans($question['type'])) ?></strong></div>
            <div class="q-meta-item"><span><?= e(trans('options_count')) ?></span><strong><bdi dir="ltr"><?= (int)$question['option_count'] ?></bdi></strong></div>
            <div class="q-meta-item"><span><?= e(trans('correct_options_count')) ?></span><strong><bdi dir="ltr"><?= (int)$question['correct_count'] ?></bdi></strong></div>
            <div class="q-meta-item"><span><?= e(trans('question_image')) ?></span><strong><?= e(trans((int)$question['has_question_image']===1?'available':'not_available')) ?></strong></div>
        </div>
    </div>

    <div class="q-card">
        <h2 class="section-title"><?= e(trans('question_image_preview')) ?></h2>
        <div class="media-preview">
            <?php if (!empty($question['question_image_token'])): ?>
                <img src="/admin/questions/media?token=<?= e((string)$question['question_image_token']) ?>" alt="<?= e(trans('question_image_alt')) ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="media-empty"><?= e(trans('question_image_not_uploaded')) ?></div>
            <?php endif; ?>

            <?php if ($canManageQuestions && $mediaActionToken !== null): ?>
                <div class="media-actions">
                    <form action="/admin/questions/media/upload" method="POST" enctype="multipart/form-data" class="media-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                        <input type="hidden" name="owner_type" value="question">
                        <input type="hidden" name="media_action_token" value="<?= e($mediaActionToken) ?>">
                        <input class="media-file" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                        <button type="submit" class="media-submit"><?= e(trans(!empty($question['question_image_token']) ? 'replace_image' : 'upload_image')) ?></button>
                    </form>
                    <?php if (!empty($question['question_image_token'])): ?>
                        <form action="/admin/questions/media/remove" method="POST" onsubmit="return confirm('<?= e(trans('confirm_remove_image')) ?>');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                            <input type="hidden" name="owner_type" value="question">
                            <input type="hidden" name="media_action_token" value="<?= e($mediaActionToken) ?>">
                            <button type="submit" class="danger-button"><?= e(trans('remove_image')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <p class="media-help"><?= e(trans('question_image_requirements')) ?></p>
            <?php elseif ($question['archived_at'] !== null): ?>
                <p class="media-help"><?= e(trans('archived_question_media_read_only')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="q-card">
        <h2 class="section-title"><?= e(trans('answer_options')) ?></h2>
        <?php if (empty($question['options'])): ?><p style="color:#fca5a5;"><?= e(trans('no_options_configured')) ?></p><?php else: ?>
            <div class="option-list">
                <?php foreach ($question['options'] as $option): $optionText=$currentLang==='en'?$option['text_en']:$option['text_ar']; ?>
                    <div class="option-card <?= (int)$option['is_correct']===1?'correct':'' ?>">
                        <span class="option-key"><bdi dir="ltr"><?= e($option['option_key']) ?></bdi></span>
                        <div style="color:#e2e8f0;line-height:1.6;"><?= nl2br(e($optionText)) ?></div>
                        <div class="option-badges"><?php if ((int)$option['is_correct']===1): ?><span class="badge badge-good"><?= e(trans('correct_answer')) ?></span><?php endif; ?><?php if ((int)$option['has_option_image']===1): ?><span class="badge badge-muted"><?= e(trans('has_image')) ?></span><?php endif; ?></div>

                        <div class="option-media media-preview">
                            <?php if (!empty($option['option_image_token'])): ?>
                                <img src="/admin/questions/media?token=<?= e((string)$option['option_image_token']) ?>" alt="<?= e(trans('option_image_alt', ['key' => (string)$option['option_key']])) ?>" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <?php if ($canManageQuestions && $mediaActionToken !== null): ?>
                                <div class="media-actions">
                                    <form action="/admin/questions/media/upload" method="POST" enctype="multipart/form-data" class="media-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                                        <input type="hidden" name="owner_type" value="option">
                                        <input type="hidden" name="option_id" value="<?= (int)$option['id'] ?>">
                                        <input type="hidden" name="media_action_token" value="<?= e($mediaActionToken) ?>">
                                        <input class="media-file" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                        <button type="submit" class="media-submit"><?= e(trans(!empty($option['option_image_token']) ? 'replace_option_image' : 'upload_option_image')) ?></button>
                                    </form>
                                    <?php if (!empty($option['option_image_token'])): ?>
                                        <form action="/admin/questions/media/remove" method="POST" onsubmit="return confirm('<?= e(trans('confirm_remove_image')) ?>');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                                            <input type="hidden" name="owner_type" value="option">
                                            <input type="hidden" name="option_id" value="<?= (int)$option['id'] ?>">
                                            <input type="hidden" name="media_action_token" value="<?= e($mediaActionToken) ?>">
                                            <button type="submit" class="danger-button"><?= e(trans('remove_image')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $content=ob_get_clean(); require __DIR__.'/../../layouts/admin.php';
