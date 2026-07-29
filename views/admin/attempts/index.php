<?php

use App\Support\TimezoneHelper;

$pageTitle = trans('attempt_monitoring');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
ob_start();
?>
<style>
.attempt-shell{display:grid;gap:1rem}.attempt-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}.attempt-title{margin:0;color:#f8fafc;font-size:1.75rem}.attempt-card{background:rgba(30,41,59,.82);border:1px solid rgba(148,163,184,.18);border-radius:12px;padding:1rem}.attempt-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.attempt-metric{padding:1rem;border:1px solid rgba(148,163,184,.18);border-radius:10px;background:rgba(15,23,42,.55)}.attempt-metric strong{display:block;color:#f8fafc;font-size:1.45rem;margin-top:.35rem}.attempt-metric span{color:#94a3b8;font-size:.82rem}.attempt-filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.attempt-search{grid-column:span 2}.attempt-field{display:grid;gap:.35rem}.attempt-field label{font-size:.8rem;color:#cbd5e1;font-weight:700}.attempt-input{width:100%;padding:.67rem .75rem;border-radius:8px;border:1px solid rgba(148,163,184,.28);background:#0f172a;color:#e2e8f0}.attempt-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}.attempt-table-wrap{overflow-x:auto}.attempt-table{width:100%;border-collapse:collapse;min-width:1150px}.attempt-table th,.attempt-table td{padding:.78rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:start;vertical-align:top}.attempt-table th{font-size:.78rem;color:#94a3b8}.attempt-table td{color:#e2e8f0}.attempt-badge{display:inline-flex;padding:.22rem .55rem;border-radius:999px;font-size:.72rem;font-weight:800;border:1px solid rgba(148,163,184,.25)}.badge-progress{color:#93c5fd;background:rgba(59,130,246,.12)}.badge-completed{color:#86efac;background:rgba(34,197,94,.12)}.badge-timeout{color:#fcd34d;background:rgba(245,158,11,.12)}.badge-cancelled{color:#fca5a5;background:rgba(239,68,68,.12)}.badge-alert{color:#fecaca;background:rgba(239,68,68,.18);border-color:rgba(239,68,68,.35)}.badge-pass{color:#86efac;background:rgba(34,197,94,.12)}.badge-fail{color:#fca5a5;background:rgba(239,68,68,.12)}.badge-muted{color:#cbd5e1;background:rgba(148,163,184,.12)}.alert-box{padding:.8rem 1rem;border-radius:8px}.alert-danger{color:#fecaca;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35)}.alert-success{color:#bbf7d0;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.35)}.pagination{display:flex;gap:.45rem;justify-content:center;flex-wrap:wrap;margin-top:1rem}.page-link{padding:.45rem .7rem;border:1px solid rgba(148,163,184,.25);border-radius:7px;color:#cbd5e1;text-decoration:none}.page-link.active{background:rgba(59,130,246,.25);border-color:#3b82f6;color:#fff}@media(max-width:1000px){.attempt-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.attempt-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.attempt-metrics,.attempt-filters{grid-template-columns:1fr}.attempt-search{grid-column:auto}}
</style>
<div class="attempt-shell">
    <div class="attempt-header">
        <div>
            <h1 class="attempt-title"><?= e(trans('attempt_monitoring')) ?></h1>
            <p style="color:#94a3b8;margin:.35rem 0 0"><?= e(trans('attempt_monitoring_description')) ?></p>
        </div>
        <a href="/admin/assignments" class="nav-link"><?= e(trans('assignments')) ?></a>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert-box alert-success"><?= e((string)$_SESSION['flash_success']) ?></div><?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert-box alert-danger"><?= e((string)$_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); endif; ?>
    <?php if (!empty($errorMessage)): ?><div class="alert-box alert-danger"><?= e((string)$errorMessage) ?></div><?php endif; ?>

    <div class="attempt-metrics" aria-label="<?= e(trans('attempt_statistics')) ?>">
        <div class="attempt-metric"><span><?= e(trans('attempts_total')) ?></span><strong><?= (int)$statistics['total_attempts'] ?></strong></div>
        <div class="attempt-metric"><span><?= e(trans('attempt_status_in_progress')) ?></span><strong><?= (int)$statistics['in_progress_attempts'] ?></strong></div>
        <div class="attempt-metric"><span><?= e(trans('attempts_completed_and_timed_out')) ?></span><strong><?= (int)$statistics['completed_attempts'] + (int)$statistics['timed_out_attempts'] ?></strong></div>
        <div class="attempt-metric"><span><?= e(trans('expired_in_progress_attempts')) ?></span><strong><?= (int)$statistics['expired_in_progress_attempts'] ?></strong></div>
        <div class="attempt-metric"><span><?= e(trans('passed')) ?></span><strong><?= (int)$statistics['passed_attempts'] ?></strong></div>
        <div class="attempt-metric"><span><?= e(trans('failed')) ?></span><strong><?= (int)$statistics['failed_attempts'] ?></strong></div>
        <div class="attempt-metric"><span><?= e(trans('attempt_status_timed_out')) ?></span><strong><?= (int)$statistics['timed_out_attempts'] ?></strong></div>
        <div class="attempt-metric"><span><?= e(trans('attempt_status_cancelled')) ?></span><strong><?= (int)$statistics['cancelled_attempts'] ?></strong></div>
    </div>

    <form action="/admin/attempts" method="GET" class="attempt-card">
        <?php if (!empty($filters['assignment_id'])): ?><input type="hidden" name="assignment_id" value="<?= (int)$filters['assignment_id'] ?>"><?php endif; ?>
        <div class="attempt-filters">
            <div class="attempt-field attempt-search">
                <label for="q"><?= e(trans('search')) ?></label>
                <input id="q" name="q" class="attempt-input" maxlength="150" value="<?= e((string)$filters['q']) ?>" placeholder="<?= e(trans('search_attempts')) ?>">
            </div>
            <div class="attempt-field">
                <label for="employee_id"><?= e(trans('employee')) ?></label>
                <select id="employee_id" name="employee_id" class="attempt-input">
                    <option value=""><?= e(trans('all_employees')) ?></option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= (int)$employee['id'] ?>" <?= (int)($filters['employee_id'] ?? 0)===(int)$employee['id']?'selected':'' ?>><?= e((string)$employee['full_name']) ?><?= !empty($employee['employee_number']) ? ' — ' . e((string)$employee['employee_number']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="attempt-field">
                <label for="test_id"><?= e(trans('assessment')) ?></label>
                <select id="test_id" name="test_id" class="attempt-input">
                    <option value=""><?= e(trans('all_assessments')) ?></option>
                    <?php foreach ($tests as $test): $testName=$currentLang==='en'?$test['name_en']:$test['name_ar']; ?>
                        <option value="<?= (int)$test['id'] ?>" <?= (int)($filters['test_id'] ?? 0)===(int)$test['id']?'selected':'' ?>><?= e((string)$test['code'].' — '.(string)$testName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="attempt-field">
                <label for="status"><?= e(trans('status')) ?></label>
                <select id="status" name="status" class="attempt-input">
                    <?php foreach (['all','IN_PROGRESS','COMPLETED','TIMED_OUT','CANCELLED'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= (string)$filters['status']===$status?'selected':'' ?>><?= e(trans($status==='all'?'all_statuses':'attempt_status_'.strtolower($status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="attempt-field">
                <label for="outcome"><?= e(trans('result')) ?></label>
                <select id="outcome" name="outcome" class="attempt-input">
                    <?php foreach (['all','passed','failed','ungraded'] as $outcome): ?>
                        <option value="<?= e($outcome) ?>" <?= (string)$filters['outcome']===$outcome?'selected':'' ?>><?= e(trans('attempt_outcome_'.$outcome)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="attempt-field">
                <label for="attention"><?= e(trans('attention_required')) ?></label>
                <select id="attention" name="attention" class="attempt-input">
                    <?php foreach (['all','expired','security','focus'] as $attention): ?>
                        <option value="<?= e($attention) ?>" <?= (string)$filters['attention']===$attention?'selected':'' ?>><?= e(trans('attempt_attention_'.$attention)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="attempt-field"><label for="started_from"><?= e(trans('started_from')) ?></label><input type="date" id="started_from" name="started_from" class="attempt-input" value="<?= e((string)$filters['started_from_input']) ?>"></div>
            <div class="attempt-field"><label for="started_until"><?= e(trans('started_until')) ?></label><input type="date" id="started_until" name="started_until" class="attempt-input" value="<?= e((string)$filters['started_until_input']) ?>"></div>
        </div>
        <div class="attempt-actions" style="margin-top:1rem">
            <button type="submit" class="btn btn-primary" style="width:auto;margin:0;padding:.65rem 1.1rem"><?= e(trans('filter')) ?></button>
            <a href="/admin/attempts" class="nav-link"><?= e(trans('reset_filters')) ?></a>
        </div>
    </form>

    <div class="attempt-card">
        <strong style="color:#f8fafc"><?= e(trans('attempts_found', ['count'=>(string)(int)$results['total']])) ?></strong>
        <?php if (empty($results['items'])): ?>
            <p style="color:#94a3b8;text-align:center;padding:2rem"><?= e(trans('no_attempts_found')) ?></p>
        <?php else: ?>
            <div class="attempt-table-wrap"><table class="attempt-table">
                <thead><tr><th><?= e(trans('employee')) ?></th><th><?= e(trans('assessment')) ?></th><th><?= e(trans('attempt_number')) ?></th><th><?= e(trans('status')) ?></th><th><?= e(trans('result')) ?></th><th><?= e(trans('progress')) ?></th><th><?= e(trans('security')) ?></th><th><?= e(trans('started_at')) ?></th><th><?= e(trans('actions')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($results['items'] as $item):
                    $status=(string)$item['status'];
                    $statusClass=match($status){'IN_PROGRESS'=>'badge-progress','COMPLETED'=>'badge-completed','TIMED_OUT'=>'badge-timeout','CANCELLED'=>'badge-cancelled',default=>'badge-muted'};
                    $testName=$currentLang==='en'?$item['test_name_en']:$item['test_name_ar'];
                    $terminal=in_array($status,['COMPLETED','TIMED_OUT'],true);
                ?>
                    <tr>
                        <td><strong><?= e((string)$item['employee_name']) ?></strong><br><small><bdi dir="ltr"><?= e((string)$item['employee_number']) ?></bdi></small></td>
                        <td><bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi><br><small><?= e((string)$testName) ?></small></td>
                        <td><bdi dir="ltr"><?= (int)$item['attempt_number'] ?> / <?= (int)$item['max_attempts'] ?></bdi></td>
                        <td><span class="attempt-badge <?= e($statusClass) ?>"><?= e(trans('attempt_status_'.strtolower($status))) ?></span><?php if ((int)$item['is_expired_in_progress']===1): ?><br><span class="attempt-badge badge-alert" style="margin-top:.3rem"><?= e(trans('expired_waiting_finalization')) ?></span><?php endif; ?></td>
                        <td><?php if ($terminal): ?><span class="attempt-badge <?= (int)$item['passed']===1?'badge-pass':'badge-fail' ?>"><?= e(trans((int)$item['passed']===1?'passed':'failed')) ?></span><br><small><bdi dir="ltr"><?= e(number_format((float)$item['percentage'],2)) ?>%</bdi></small><?php else: ?><span class="attempt-badge badge-muted"><?= e(trans('attempt_outcome_ungraded')) ?></span><?php endif; ?></td>
                        <td><bdi dir="ltr"><?= (int)$item['answered_count'] ?> / <?= (int)$item['question_count'] ?></bdi></td>
                        <td><bdi dir="ltr"><?= (int)$item['security_event_count'] ?></bdi> <?= e(trans('events')) ?><br><small><?= e(trans('focus_losses')) ?>: <bdi dir="ltr"><?= (int)$item['focus_loss_count'] ?></bdi></small></td>
                        <td><?= e(TimezoneHelper::format((string)$item['started_at'], null, 'Y-m-d H:i')) ?></td>
                        <td><a href="/admin/attempts/view?id=<?= (int)$item['id'] ?>" class="nav-link"><?= e(trans('details')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>

        <?php if ((int)$results['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="<?= e(trans('pagination')) ?>">
                <?php $base=$_GET; unset($base['page']); for($page=1;$page<=(int)$results['total_pages'];$page++): $base['page']=$page; ?>
                    <a class="page-link <?= $page===(int)$results['page']?'active':'' ?>" href="/admin/attempts?<?= e(http_build_query($base)) ?>"><?= $page ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php $content=ob_get_clean(); require __DIR__.'/../../layouts/admin.php'; ?>
