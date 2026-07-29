<?php

declare(strict_types=1);

use App\Services\AuthorizationService;
use App\Support\TimezoneHelper;

$currentLang = $_SESSION['lang'] ?? 'ar';
$pageTitle = trans('assignments');
$isAuthenticatedLayout = true;
$canManageAssignments = AuthorizationService::can($admin ?? [], 'manage_assignments');
ob_start();
?>
<style>
.assignment-shell{width:100%}.assignment-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}.assignment-title{margin:0;color:#f8fafc;font-size:1.75rem}.assignment-card{background:rgba(30,41,59,.85);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1.2rem;margin-bottom:1rem}.assignment-filters{display:grid;grid-template-columns:2fr repeat(4,minmax(150px,1fr));gap:.8rem}.assignment-field label{display:block;color:#cbd5e1;font-size:.85rem;font-weight:600;margin-bottom:.35rem}.assignment-input{width:100%;box-sizing:border-box;padding:.68rem .75rem;border:1px solid rgba(148,163,184,.3);border-radius:8px;background:rgba(15,23,42,.72);color:#f8fafc}.assignment-actions{display:flex;gap:.5rem;flex-wrap:wrap}.assignment-table-wrap{overflow-x:auto}.assignment-table{width:100%;border-collapse:collapse;min-width:1050px}.assignment-table th,.assignment-table td{padding:.82rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:start;vertical-align:top}.assignment-table th{font-size:.8rem;color:#94a3b8}.assignment-table td{color:#e2e8f0}.assignment-badge{display:inline-flex;padding:.24rem .58rem;border-radius:999px;font-size:.74rem;font-weight:700;border:1px solid rgba(148,163,184,.3)}.badge-pending{color:#fde68a;background:rgba(245,158,11,.12)}.badge-progress{color:#93c5fd;background:rgba(59,130,246,.12)}.badge-completed{color:#86efac;background:rgba(34,197,94,.12)}.badge-retake{color:#d8b4fe;background:rgba(168,85,247,.12)}.badge-muted{color:#cbd5e1;background:rgba(148,163,184,.12)}.pagination{display:flex;gap:.45rem;justify-content:center;flex-wrap:wrap;margin-top:1rem}.page-link{padding:.45rem .7rem;border:1px solid rgba(148,163,184,.25);border-radius:7px;color:#cbd5e1;text-decoration:none}.page-link.active{background:rgba(59,130,246,.25);border-color:#3b82f6;color:#fff}@media(max-width:1050px){.assignment-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.assignment-search{grid-column:1/-1}}@media(max-width:620px){.assignment-filters{grid-template-columns:1fr}.assignment-search{grid-column:auto}}
</style>
<div class="assignment-shell">
    <div class="assignment-header">
        <div>
            <h1 class="assignment-title"><?= e(trans('assignments')) ?></h1>
            <p style="color:#94a3b8;margin:.35rem 0 0"><?= e(trans('assignments_description')) ?></p>
        </div>
        <?php if ($canManageAssignments): ?>
            <a href="/admin/assignments/create" class="nav-link"><?= e(trans('create_assignment')) ?></a>
        <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert-box alert-success"><?= e($_SESSION['flash_success']) ?></div><?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert-box alert-danger"><?= e($_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); endif; ?>
    <?php if (!empty($_SESSION['flash_info'])): ?><div class="alert-box" style="border:1px solid rgba(59,130,246,.35);background:rgba(59,130,246,.1);color:#bfdbfe"><?= e($_SESSION['flash_info']) ?></div><?php unset($_SESSION['flash_info']); endif; ?>
    <?php if (!empty($errorMessage)): ?><div class="alert-box alert-danger"><?= e($errorMessage) ?></div><?php endif; ?>

    <form action="/admin/assignments" method="GET" class="assignment-card">
        <div class="assignment-filters">
            <div class="assignment-field assignment-search">
                <label for="q"><?= e(trans('search')) ?></label>
                <input id="q" name="q" class="assignment-input" maxlength="150" value="<?= e((string)$filters['q']) ?>" placeholder="<?= e(trans('search_assignments')) ?>">
            </div>
            <div class="assignment-field">
                <label for="employee_id"><?= e(trans('employee')) ?></label>
                <select id="employee_id" name="employee_id" class="assignment-input">
                    <option value=""><?= e(trans('all_employees')) ?></option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= (int)$employee['id'] ?>" <?= (int)($filters['employee_id'] ?? 0)===(int)$employee['id']?'selected':'' ?>><?= e((string)$employee['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="assignment-field">
                <label for="test_id"><?= e(trans('assessment')) ?></label>
                <select id="test_id" name="test_id" class="assignment-input">
                    <option value=""><?= e(trans('all_assessments')) ?></option>
                    <?php foreach ($tests as $test): $testName=$currentLang==='en'?$test['name_en']:$test['name_ar']; ?>
                        <option value="<?= (int)$test['id'] ?>" <?= (int)($filters['test_id'] ?? 0)===(int)$test['id']?'selected':'' ?>><?= e($test['code'].' — '.$testName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="assignment-field">
                <label for="status"><?= e(trans('status')) ?></label>
                <select id="status" name="status" class="assignment-input">
                    <?php foreach (['all','PENDING','IN_PROGRESS','RETAKE_AUTHORIZED','COMPLETED'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= (string)$filters['status']===$status?'selected':'' ?>><?= e(trans($status==='all'?'all_statuses':'assignment_status_'.strtolower($status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="assignment-field">
                <label for="availability"><?= e(trans('availability')) ?></label>
                <select id="availability" name="availability" class="assignment-input">
                    <?php foreach (['all','open','upcoming','expired'] as $availability): ?>
                        <option value="<?= e($availability) ?>" <?= (string)$filters['availability']===$availability?'selected':'' ?>><?= e(trans($availability==='all'?'all_availability_states':'assignment_availability_'.$availability)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="assignment-actions" style="margin-top:1rem">
            <button type="submit" class="btn btn-primary" style="width:auto;margin:0;padding:.65rem 1.1rem"><?= e(trans('filter')) ?></button>
            <a href="/admin/assignments" class="nav-link"><?= e(trans('reset_filters')) ?></a>
        </div>
    </form>

    <div class="assignment-card">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:.8rem">
            <strong style="color:#f8fafc"><?= e(trans('assignments_found', ['count'=>(string)(int)$results['total']])) ?></strong>
        </div>
        <?php if (empty($results['items'])): ?>
            <p style="color:#94a3b8;text-align:center;padding:2rem"><?= e(trans('no_assignments_found')) ?></p>
        <?php else: ?>
            <div class="assignment-table-wrap"><table class="assignment-table">
                <thead><tr><th><?= e(trans('employee')) ?></th><th><?= e(trans('assessment')) ?></th><th><?= e(trans('status')) ?></th><th><?= e(trans('availability')) ?></th><th><?= e(trans('attempts')) ?></th><th><?= e(trans('assigned_at')) ?></th><th><?= e(trans('actions')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($results['items'] as $item):
                    $testName=$currentLang==='en'?$item['test_name_en']:$item['test_name_ar'];
                    $status=(string)$item['status'];
                    $statusClass=match($status){'PENDING'=>'badge-pending','IN_PROGRESS'=>'badge-progress','RETAKE_AUTHORIZED'=>'badge-retake','COMPLETED'=>'badge-completed',default=>'badge-muted'};
                    $availability=(string)$item['availability_state'];
                ?>
                    <tr>
                        <td><strong><?= e((string)$item['employee_name']) ?></strong><?php if (!empty($item['employee_number'])): ?><br><small><bdi dir="ltr"><?= e((string)$item['employee_number']) ?></bdi></small><?php endif; ?></td>
                        <td><bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi><br><small><?= e((string)$testName) ?></small></td>
                        <td><span class="assignment-badge <?= e($statusClass) ?>"><?= e(trans('assignment_status_'.strtolower($status))) ?></span></td>
                        <td><span class="assignment-badge <?= $availability==='OPEN'?'badge-completed':($availability==='UPCOMING'?'badge-progress':'badge-muted') ?>"><?= e(trans('assignment_availability_'.strtolower($availability))) ?></span><br><small><?= e(trans('from')) ?>: <?= e(TimezoneHelper::format($item['available_from'] ?? null, null, 'Y-m-d H:i')) ?><br><?= e(trans('until')) ?>: <?= e(TimezoneHelper::format($item['available_until'] ?? null, null, 'Y-m-d H:i')) ?></small></td>
                        <td><bdi dir="ltr"><?= (int)$item['attempt_count'] ?> / <?= (int)$item['max_attempts'] ?></bdi><?php if ($item['latest_attempt_percentage']!==null): ?><br><small><?= e(trans('latest_result')) ?>: <bdi dir="ltr"><?= e(number_format((float)$item['latest_attempt_percentage'],2)) ?>%</bdi></small><?php endif; ?></td>
                        <td><?= e(TimezoneHelper::format((string)$item['assigned_at'], null, 'Y-m-d H:i')) ?><br><small><?= e((string)$item['assigned_by_name']) ?></small></td>
                        <td><div class="assignment-actions"><a href="/admin/assignments/view?id=<?= (int)$item['id'] ?>" class="nav-link"><?= e(trans('details')) ?></a><?php if ($canManageAssignments && $status==='PENDING' && (int)$item['attempt_count']===0): ?><a href="/admin/assignments/edit?id=<?= (int)$item['id'] ?>" class="nav-link"><?= e(trans('edit')) ?></a><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>

        <?php if ((int)$results['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="<?= e(trans('pagination')) ?>">
                <?php $base=$_GET; unset($base['page']); for($page=1;$page<=(int)$results['total_pages'];$page++): $base['page']=$page; ?>
                    <a class="page-link <?= $page===(int)$results['page']?'active':'' ?>" href="/admin/assignments?<?= e(http_build_query($base)) ?>"><?= $page ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php $content=ob_get_clean(); require __DIR__.'/../../layouts/admin.php'; ?>
