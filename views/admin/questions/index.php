<?php
$pageTitle = trans('question_bank');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
ob_start();
?>
<style>
.question-shell{width:100%;max-width:1200px;margin:0 auto;display:grid;gap:1.25rem}.question-header,.question-card{background:var(--card-bg,rgba(30,41,59,.85));border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1.4rem}.question-header{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}.question-title{margin:0;color:#f8fafc;font-size:1.75rem}.filter-grid{display:grid;grid-template-columns:2fr repeat(4,minmax(140px,1fr));gap:.8rem;align-items:end}.filter-field{display:grid;gap:.35rem}.filter-field label{font-size:.82rem;color:#94a3b8}.input-field{width:100%;box-sizing:border-box;padding:.7rem .8rem;border:1px solid rgba(148,163,184,.3);border-radius:8px;background:rgba(15,23,42,.72);color:#f8fafc}.question-table-wrap{overflow-x:auto}.question-table{width:100%;border-collapse:collapse;min-width:900px}.question-table th,.question-table td{padding:.85rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:start;vertical-align:top}.question-table th{color:#94a3b8;font-size:.82rem}.question-table td{color:#e2e8f0}.question-text{max-width:360px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.badge{display:inline-flex;padding:.25rem .6rem;border-radius:999px;font-size:.76rem;font-weight:700}.badge-good{color:#86efac;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3)}.badge-warn{color:#fcd34d;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3)}.badge-muted{color:#cbd5e1;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.25)}.actions{display:flex;gap:.5rem;flex-wrap:wrap}.pagination{display:flex;gap:.5rem;justify-content:center;align-items:center;flex-wrap:wrap;margin-top:1rem}.page-link{padding:.45rem .75rem;border:1px solid rgba(148,163,184,.25);border-radius:7px;color:#cbd5e1;text-decoration:none}.page-link.active{background:rgba(59,130,246,.25);color:#fff;border-color:#3b82f6}@media(max-width:1000px){.filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.filter-search{grid-column:1/-1}}@media(max-width:620px){.filter-grid{grid-template-columns:1fr}}
</style>
<div class="question-shell">
    <div class="question-header">
        <div>
            <h1 class="question-title"><?= e(trans('question_bank')) ?></h1>
            <p style="color:#94a3b8;margin:.35rem 0 0;"><?= e(trans('question_bank_description')) ?></p>
        </div>
        <?php if ($canManageQuestions): ?>
            <div class="actions">
                <a href="/admin/questions/create" class="nav-link"><?= e(trans('create_question')) ?></a>
                <a href="/admin/questions/categories/create" class="nav-link"><?= e(trans('create_question_category')) ?></a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert-box alert-success"><?= e($_SESSION['flash_success']) ?></div><?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert-box alert-danger"><?= e($_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); endif; ?>
    <?php if (!empty($_SESSION['flash_info'])): ?><div class="alert-box" style="border:1px solid rgba(59,130,246,.35);background:rgba(59,130,246,.1);color:#bfdbfe;"><?= e($_SESSION['flash_info']) ?></div><?php unset($_SESSION['flash_info']); endif; ?>
    <?php if ($errorMessage): ?><div class="alert-box alert-danger"><?= e($errorMessage) ?></div><?php endif; ?>

    <form action="/admin/questions" method="GET" class="question-card">
        <?php if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar','en'], true)): ?><input type="hidden" name="lang" value="<?= e($_GET['lang']) ?>"><?php endif; ?>
        <div class="filter-grid">
            <div class="filter-field filter-search">
                <label for="q"><?= e(trans('search')) ?></label>
                <input id="q" name="q" class="input-field" maxlength="150" value="<?= e((string)$filters['q']) ?>" placeholder="<?= e(trans('search_questions')) ?>">
            </div>
            <div class="filter-field">
                <label for="test_id"><?= e(trans('assessment')) ?></label>
                <select id="test_id" name="test_id" class="input-field">
                    <option value=""><?= e(trans('all_assessments')) ?></option>
                    <?php foreach ($tests as $test): $label=$currentLang==='en'?$test['name_en']:$test['name_ar']; ?>
                        <option value="<?= (int)$test['id'] ?>" <?= (int)($filters['test_id'] ?? 0)===(int)$test['id']?'selected':'' ?>><?= e($test['code'].' — '.$label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="category_id"><?= e(trans('category')) ?></label>
                <select id="category_id" name="category_id" class="input-field">
                    <option value=""><?= e(trans('all_categories')) ?></option>
                    <?php foreach ($categories as $category): $label=$currentLang==='en'?$category['name_en']:$category['name_ar']; ?>
                        <option value="<?= (int)$category['id'] ?>" <?= (int)($filters['category_id'] ?? 0)===(int)$category['id']?'selected':'' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="difficulty"><?= e(trans('difficulty')) ?></label>
                <select id="difficulty" name="difficulty" class="input-field">
                    <?php foreach (['all','easy','medium','hard'] as $value): ?><option value="<?= e($value) ?>" <?= $filters['difficulty']===$value?'selected':'' ?>><?= e(trans($value==='all'?'all_difficulties':$value)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="status"><?= e(trans('status')) ?></label>
                <select id="status" name="status" class="input-field">
                    <?php foreach (['all','active','inactive','archived'] as $value): ?><option value="<?= e($value) ?>" <?= $filters['status']===$value?'selected':'' ?>><?= e(trans($value==='all'?'all_statuses':$value)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="validity"><?= e(trans('question_validity')) ?></label>
                <select id="validity" name="validity" class="input-field">
                    <?php foreach (['all','valid','invalid'] as $value): ?><option value="<?= e($value) ?>" <?= $filters['validity']===$value?'selected':'' ?>><?= e(trans($value==='all'?'all_validity_states':$value.'_question')) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="actions" style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary" style="width:auto;padding:.65rem 1.2rem;margin:0;"><?= e(trans('filter')) ?></button>
            <a href="/admin/questions" class="nav-link"><?= e(trans('reset_filters')) ?></a>
        </div>
    </form>

    <div class="question-card">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:.8rem;">
            <strong style="color:#f8fafc;"><?= e(trans('questions_found', ['count'=>(string)(int)$results['total']])) ?></strong>
        </div>
        <?php if (empty($results['items'])): ?>
            <p style="color:#94a3b8;text-align:center;padding:2rem;"><?= e(trans('no_questions_found')) ?></p>
        <?php else: ?>
            <div class="question-table-wrap"><table class="question-table">
                <thead><tr><th><?= e(trans('question')) ?></th><th><?= e(trans('assessment')) ?></th><th><?= e(trans('category')) ?></th><th><?= e(trans('difficulty')) ?></th><th><?= e(trans('options')) ?></th><th><?= e(trans('status')) ?></th><th><?= e(trans('question_validity')) ?></th><th><?= e(trans('actions')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($results['items'] as $item): $questionText=$currentLang==='en'?$item['text_en']:$item['text_ar']; $testName=$currentLang==='en'?$item['test_name_en']:$item['test_name_ar']; $categoryName=$currentLang==='en'?$item['category_name_en']:$item['category_name_ar']; ?>
                    <tr>
                        <td><div class="question-text" title="<?= e($questionText) ?>"><?= e($questionText) ?></div><small><bdi dir="ltr">#<?= (int)$item['id'] ?></bdi> · <?= e(number_format((float)$item['score'],2)) ?></small></td>
                        <td><bdi dir="ltr"><?= e($item['test_code']) ?></bdi><br><small><?= e($testName) ?></small></td>
                        <td><?= e($categoryName) ?></td>
                        <td><?= e(trans($item['difficulty'])) ?></td>
                        <td><bdi dir="ltr"><?= (int)$item['option_count'] ?></bdi></td>
                        <td><?php if ($item['archived_at']!==null): ?><span class="badge badge-muted"><?= e(trans('archived')) ?></span><?php elseif ((int)$item['is_active']===1): ?><span class="badge badge-good"><?= e(trans('active')) ?></span><?php else: ?><span class="badge badge-muted"><?= e(trans('inactive')) ?></span><?php endif; ?></td>
                        <td><span class="badge <?= (int)$item['is_valid']===1?'badge-good':'badge-warn' ?>"><?= e(trans((int)$item['is_valid']===1?'valid_question':'invalid_question')) ?></span></td>
                        <td><div class="actions"><a class="nav-link" href="/admin/questions/view?id=<?= (int)$item['id'] ?>"><?= e(trans('details')) ?></a><?php if($canManageQuestions && $item['archived_at']===null): ?><a class="nav-link" href="/admin/questions/edit?id=<?= (int)$item['id'] ?>"><?= e(trans('edit')) ?></a><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>

        <?php if ((int)$results['total_pages'] > 1): ?>
        <nav class="pagination" aria-label="<?= e(trans('pagination')) ?>">
            <?php $base=$_GET; unset($base['page']); for($page=1;$page<=(int)$results['total_pages'];$page++): $base['page']=$page; ?>
                <a class="page-link <?= $page===(int)$results['page']?'active':'' ?>" href="/admin/questions?<?= e(http_build_query($base)) ?>"><?= $page ?></a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php $content=ob_get_clean(); require __DIR__.'/../../layouts/admin.php';
