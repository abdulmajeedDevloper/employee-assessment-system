<?php

use App\Support\TimezoneHelper;

$pageTitle = trans('reports');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
$formatPercent = static fn(mixed $value): string => number_format((float)$value, 2) . '%';
$barWidth = static fn(mixed $value): string => number_format(max(0, min(100, (float)$value)), 2, '.', '');
ob_start();
?>
<style>
.report-shell{display:grid;gap:1.25rem}.report-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}.report-title{font-size:1.75rem;color:#f8fafc;margin:0 0 .35rem}.report-subtitle{color:#94a3b8;max-width:780px;line-height:1.65}.report-card{background:rgba(30,41,59,.88);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:1.2rem;box-shadow:0 12px 25px rgba(0,0,0,.22)}.report-filters{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.9rem}.report-field{display:grid;gap:.35rem}.report-field.wide{grid-column:span 2}.report-field label{color:#cbd5e1;font-size:.82rem;font-weight:700}.report-input{width:100%;padding:.7rem .8rem;border:1px solid #334155;border-radius:8px;background:#0f172a;color:#f8fafc}.report-actions{display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;margin-top:1rem}.report-button{display:inline-flex;align-items:center;justify-content:center;padding:.7rem 1rem;border-radius:8px;border:1px solid rgba(96,165,250,.45);background:rgba(37,99,235,.22);color:#e0f2fe;text-decoration:none;font-weight:800;cursor:pointer}.report-button.primary{background:#1d4ed8;border-color:#2563eb;color:#fff}.report-button.excel{background:rgba(22,163,74,.22);border-color:rgba(34,197,94,.5);color:#dcfce7}.report-button.pdf{background:rgba(220,38,38,.2);border-color:rgba(248,113,113,.5);color:#fee2e2}.report-button:disabled{opacity:.5;cursor:not-allowed;filter:none}.report-button:hover{filter:brightness(1.12)}.report-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.9rem}.report-metric{padding:1rem;border:1px solid rgba(255,255,255,.09);border-radius:11px;background:rgba(15,23,42,.48)}.report-metric span{color:#94a3b8;font-size:.8rem;font-weight:700}.report-metric strong{display:block;margin-top:.45rem;color:#f8fafc;font-size:1.65rem}.report-two{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.report-panel-head{display:flex;justify-content:space-between;align-items:center;gap:.8rem;margin-bottom:.9rem}.report-panel-head h2{font-size:1.1rem;color:#f8fafc;margin:0}.report-muted{color:#94a3b8;font-size:.82rem}.report-table-wrap{overflow:auto}.report-table{width:100%;border-collapse:collapse;min-width:790px}.report-table.compact{min-width:520px}.report-table th,.report-table td{padding:.72rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:start;vertical-align:middle}.report-table th{font-size:.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em}.report-table td{font-size:.86rem;color:#e2e8f0}.report-badge{display:inline-flex;padding:.25rem .55rem;border-radius:999px;font-size:.75rem;font-weight:800}.report-badge.pass{background:rgba(34,197,94,.16);color:#86efac}.report-badge.fail{background:rgba(239,68,68,.16);color:#fca5a5}.report-badge.timeout{background:rgba(245,158,11,.16);color:#fde68a}.report-badge.complete{background:rgba(59,130,246,.17);color:#bfdbfe}.report-progress{display:flex;align-items:center;gap:.55rem}.report-progress-track{width:95px;height:8px;background:#0f172a;border-radius:999px;overflow:hidden;border:1px solid rgba(255,255,255,.08)}.report-progress-fill{height:100%;background:linear-gradient(90deg,#2563eb,#38bdf8)}.report-empty{padding:1.5rem;text-align:center;color:#94a3b8;border:1px dashed rgba(255,255,255,.14);border-radius:10px}.pagination{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:1rem}.page-link{display:inline-flex;min-width:36px;height:36px;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.14);border-radius:7px;color:#cbd5e1;text-decoration:none}.page-link.active{background:#2563eb;color:#fff;border-color:#3b82f6}.filter-summary{display:flex;gap:.55rem;flex-wrap:wrap}.filter-chip{padding:.32rem .6rem;border-radius:999px;background:rgba(59,130,246,.14);color:#bfdbfe;border:1px solid rgba(59,130,246,.25);font-size:.75rem}.privacy-note{padding:.8rem 1rem;border-radius:9px;background:rgba(14,116,144,.15);border:1px solid rgba(34,211,238,.24);color:#cffafe;font-size:.83rem;line-height:1.55}.export-panel{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}.export-copy{display:grid;gap:.3rem}.export-copy strong{color:#f8fafc}.export-copy small{color:#94a3b8;line-height:1.5}.export-actions{display:flex;gap:.65rem;flex-wrap:wrap}@media(max-width:1050px){.report-filters{grid-template-columns:repeat(2,1fr)}.report-metrics{grid-template-columns:repeat(2,1fr)}.report-two{grid-template-columns:1fr}}@media(max-width:620px){.report-filters,.report-metrics{grid-template-columns:1fr}.report-field.wide{grid-column:auto}.report-card{padding:1rem}.report-button{flex:1}}
</style>

<div class="report-shell">
    <section class="report-card report-head">
        <div>
            <h1 class="report-title"><?= e(trans('reports_and_analytics')) ?></h1>
            <p class="report-subtitle"><?= e(trans('reports_help')) ?></p>
        </div>
        <div class="report-actions" style="margin:0">
            <a class="report-button" href="/admin/dashboard"><?= e(trans('back_to_dashboard')) ?></a>
        </div>
    </section>

    <?php if (!empty($errorMessage)): ?><div class="alert-box alert-danger"><?= e((string)$errorMessage) ?></div><?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert-box alert-success"><?= e((string)$_SESSION['flash_success']) ?></div><?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert-box alert-danger"><?= e((string)$_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); endif; ?>

    <div class="privacy-note"><?= e(trans('report_privacy_notice')) ?></div>

    <?php if (\App\Services\AuthorizationService::can($admin, 'export_reports')): ?>
        <form method="POST" action="/admin/reports/export" class="report-card export-panel">
            <?= csrf_field() ?>
            <input type="hidden" name="lang" value="<?= e($currentLang) ?>">
            <input type="hidden" name="q" value="<?= e((string)$filters['q']) ?>">
            <input type="hidden" name="department_id" value="<?= (int)($filters['department_id'] ?? 0) ?: '' ?>">
            <input type="hidden" name="section_id" value="<?= (int)($filters['section_id'] ?? 0) ?: '' ?>">
            <input type="hidden" name="test_id" value="<?= (int)($filters['test_id'] ?? 0) ?: '' ?>">
            <input type="hidden" name="outcome" value="<?= e((string)$filters['outcome']) ?>">
            <input type="hidden" name="attempt_status" value="<?= e((string)$filters['attempt_status']) ?>">
            <input type="hidden" name="submitted_from" value="<?= e((string)$filters['submitted_from_input']) ?>">
            <input type="hidden" name="submitted_until" value="<?= e((string)$filters['submitted_until_input']) ?>">
            <div class="export-copy">
                <strong><?= e(trans('export_current_report')) ?></strong>
                <small><?= e(trans('export_current_report_help')) ?></small>
            </div>
            <div class="export-actions">
                <button class="report-button excel" type="submit" name="format" value="xlsx" <?= (int)$summary['total_results'] === 0 ? 'disabled' : '' ?>><?= e(trans('export_excel')) ?></button>
                <button class="report-button pdf" type="submit" name="format" value="pdf" <?= (int)$summary['total_results'] === 0 ? 'disabled' : '' ?>><?= e(trans('export_pdf')) ?></button>
            </div>
        </form>
    <?php endif; ?>

    <form method="GET" action="/admin/reports" class="report-card">
        <div class="report-filters">
            <div class="report-field wide"><label for="q"><?= e(trans('search')) ?></label><input class="report-input" id="q" name="q" maxlength="150" value="<?= e((string)$filters['q']) ?>" placeholder="<?= e(trans('search_reports')) ?>"></div>
            <div class="report-field"><label for="department_id"><?= e(trans('department')) ?></label><select class="report-input" id="department_id" name="department_id"><option value=""><?= e(trans('all_departments')) ?></option><?php foreach ($departments as $department): $name=$currentLang==='en'?$department['name_en']:$department['name_ar']; ?><option value="<?= (int)$department['id'] ?>" <?= (int)($filters['department_id']??0)===(int)$department['id']?'selected':'' ?>><?= e((string)$name) ?></option><?php endforeach; ?></select></div>
            <div class="report-field"><label for="section_id"><?= e(trans('section')) ?></label><select class="report-input" id="section_id" name="section_id"><option value=""><?= e(trans('all_sections')) ?></option><?php foreach ($sections as $section): $name=$currentLang==='en'?$section['name_en']:$section['name_ar']; ?><option data-department-id="<?= (int)$section['department_id'] ?>" value="<?= (int)$section['id'] ?>" <?= (int)($filters['section_id']??0)===(int)$section['id']?'selected':'' ?>><?= e((string)$name) ?></option><?php endforeach; ?></select></div>
            <div class="report-field"><label for="test_id"><?= e(trans('assessment')) ?></label><select class="report-input" id="test_id" name="test_id"><option value=""><?= e(trans('all_assessments')) ?></option><?php foreach ($tests as $test): $name=$currentLang==='en'?$test['name_en']:$test['name_ar']; ?><option value="<?= (int)$test['id'] ?>" <?= (int)($filters['test_id']??0)===(int)$test['id']?'selected':'' ?>><?= e((string)$test['code']) ?> — <?= e((string)$name) ?></option><?php endforeach; ?></select></div>
            <div class="report-field"><label for="outcome"><?= e(trans('result')) ?></label><select class="report-input" id="outcome" name="outcome"><?php foreach(['all','passed','failed'] as $value): ?><option value="<?= e($value) ?>" <?= (string)$filters['outcome']===$value?'selected':'' ?>><?= e(trans($value==='all'?'all_results':$value)) ?></option><?php endforeach; ?></select></div>
            <div class="report-field"><label for="attempt_status"><?= e(trans('submission_status')) ?></label><select class="report-input" id="attempt_status" name="attempt_status"><?php foreach(['all','COMPLETED','TIMED_OUT'] as $value): ?><option value="<?= e($value) ?>" <?= (string)$filters['attempt_status']===$value?'selected':'' ?>><?= e(trans($value==='all'?'all_submission_statuses':'attempt_status_'.strtolower($value))) ?></option><?php endforeach; ?></select></div>
            <div class="report-field"><label for="submitted_from"><?= e(trans('submitted_from')) ?></label><input class="report-input" type="date" id="submitted_from" name="submitted_from" value="<?= e((string)$filters['submitted_from_input']) ?>"></div>
            <div class="report-field"><label for="submitted_until"><?= e(trans('submitted_until')) ?></label><input class="report-input" type="date" id="submitted_until" name="submitted_until" value="<?= e((string)$filters['submitted_until_input']) ?>"></div>
        </div>
        <div class="report-actions"><button class="report-button primary" type="submit"><?= e(trans('apply_filters')) ?></button><a class="report-button" href="/admin/reports"><?= e(trans('reset_filters')) ?></a></div>
    </form>

    <section class="report-card">
        <div class="report-panel-head"><h2><?= e(trans('report_summary')) ?></h2><span class="report-muted"><?= e(trans('official_latest_results')) ?></span></div>
        <div class="report-metrics">
            <div class="report-metric"><span><?= e(trans('total_results')) ?></span><strong><?= (int)$summary['total_results'] ?></strong></div>
            <div class="report-metric"><span><?= e(trans('assessed_employees')) ?></span><strong><?= (int)$summary['assessed_employees'] ?></strong></div>
            <div class="report-metric"><span><?= e(trans('overall_pass_rate')) ?></span><strong><bdi dir="ltr"><?= e($formatPercent($summary['pass_rate'])) ?></bdi></strong></div>
            <div class="report-metric"><span><?= e(trans('average_result')) ?></span><strong><bdi dir="ltr"><?= e($formatPercent($summary['average_percentage'])) ?></bdi></strong></div>
            <div class="report-metric"><span><?= e(trans('passed')) ?></span><strong><?= (int)$summary['passed_results'] ?></strong></div>
            <div class="report-metric"><span><?= e(trans('failed')) ?></span><strong><?= (int)$summary['failed_results'] ?></strong></div>
            <div class="report-metric"><span><?= e(trans('highest')) ?></span><strong><bdi dir="ltr"><?= e($formatPercent($summary['highest_percentage'])) ?></bdi></strong></div>
            <div class="report-metric"><span><?= e(trans('lowest')) ?></span><strong><bdi dir="ltr"><?= e($formatPercent($summary['lowest_percentage'])) ?></bdi></strong></div>
        </div>
    </section>

    <section class="report-two">
        <article class="report-card">
            <div class="report-panel-head"><h2><?= e(trans('performance_by_assessment')) ?></h2><span class="report-muted"><?= count($test_breakdown) ?></span></div>
            <?php if (empty($test_breakdown)): ?><div class="report-empty"><?= e(trans('no_report_data')) ?></div><?php else: ?><div class="report-table-wrap"><table class="report-table compact"><thead><tr><th><?= e(trans('assessment')) ?></th><th><?= e(trans('results')) ?></th><th><?= e(trans('average_result')) ?></th><th><?= e(trans('pass_rate')) ?></th></tr></thead><tbody><?php foreach($test_breakdown as $item): $name=$currentLang==='en'?$item['name_en']:$item['name_ar']; ?><tr><td><strong><bdi dir="ltr"><?= e((string)$item['code']) ?></bdi></strong><br><small><?= e((string)$name) ?></small></td><td><?= (int)$item['result_count'] ?></td><td><div class="report-progress"><div class="report-progress-track"><div class="report-progress-fill" style="width:<?= e($barWidth($item['average_percentage'])) ?>%"></div></div><bdi dir="ltr"><?= e($formatPercent($item['average_percentage'])) ?></bdi></div></td><td><bdi dir="ltr"><?= e($formatPercent($item['pass_rate'])) ?></bdi></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </article>
        <article class="report-card">
            <div class="report-panel-head"><h2><?= e(trans('performance_by_department')) ?></h2><span class="report-muted"><?= count($department_breakdown) ?></span></div>
            <?php if (empty($department_breakdown)): ?><div class="report-empty"><?= e(trans('no_report_data')) ?></div><?php else: ?><div class="report-table-wrap"><table class="report-table compact"><thead><tr><th><?= e(trans('department')) ?></th><th><?= e(trans('assessed_employees')) ?></th><th><?= e(trans('average_result')) ?></th><th><?= e(trans('pass_rate')) ?></th></tr></thead><tbody><?php foreach($department_breakdown as $item): $name=$currentLang==='en'?$item['name_en']:$item['name_ar']; ?><tr><td><strong><?= e((string)$name) ?></strong></td><td><?= (int)$item['assessed_employees'] ?></td><td><div class="report-progress"><div class="report-progress-track"><div class="report-progress-fill" style="width:<?= e($barWidth($item['average_percentage'])) ?>%"></div></div><bdi dir="ltr"><?= e($formatPercent($item['average_percentage'])) ?></bdi></div></td><td><bdi dir="ltr"><?= e($formatPercent($item['pass_rate'])) ?></bdi></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </article>
    </section>

    <section class="report-card">
        <div class="report-panel-head"><h2><?= e(trans('official_results_directory')) ?></h2><span class="report-muted"><?= e(trans('results_found', ['count'=>(string)(int)$results['total']])) ?></span></div>
        <?php if (empty($results['items'])): ?><div class="report-empty"><?= e(trans('no_report_data')) ?></div><?php else: ?>
            <div class="report-table-wrap"><table class="report-table"><thead><tr><th><?= e(trans('employee')) ?></th><th><?= e(trans('masked_national_id')) ?></th><th><?= e(trans('organization_unit')) ?></th><th><?= e(trans('assessment')) ?></th><th><?= e(trans('attempt_number')) ?></th><th><?= e(trans('score')) ?></th><th><?= e(trans('result')) ?></th><th><?= e(trans('submission_status')) ?></th><th><?= e(trans('submitted_at')) ?></th><th><?= e(trans('actions')) ?></th></tr></thead><tbody>
            <?php foreach($results['items'] as $item): $testName=$currentLang==='en'?$item['test_name_en']:$item['test_name_ar']; $deptName=$currentLang==='en'?$item['department_name_en']:$item['department_name_ar']; $sectionName=$currentLang==='en'?$item['section_name_en']:$item['section_name_ar']; ?>
                <tr>
                    <td><strong><?= e((string)$item['employee_name']) ?></strong><br><small><bdi dir="ltr"><?= e((string)($item['employee_number']??'')) ?></bdi></small></td>
                    <td><bdi dir="ltr"><?= e((string)$item['masked_national_id']) ?></bdi></td>
                    <td><?= e((string)$deptName) ?><br><small><?= e((string)$sectionName) ?></small></td>
                    <td><bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi><br><small><?= e((string)$testName) ?></small></td>
                    <td><bdi dir="ltr"><?= (int)$item['attempt_number'] ?></bdi></td>
                    <td><bdi dir="ltr"><?= e(number_format((float)$item['score'],2)) ?> / <?= e(number_format((float)$item['total_score'],2)) ?></bdi><br><strong><bdi dir="ltr"><?= e($formatPercent($item['percentage'])) ?></bdi></strong></td>
                    <td><span class="report-badge <?= (int)$item['passed']===1?'pass':'fail' ?>"><?= e(trans((int)$item['passed']===1?'passed':'failed')) ?></span></td>
                    <td><span class="report-badge <?= (string)$item['attempt_status']==='TIMED_OUT'?'timeout':'complete' ?>"><?= e(trans('attempt_status_'.strtolower((string)$item['attempt_status']))) ?></span></td>
                    <td><?= e(TimezoneHelper::format((string)$item['submitted_at'], null, 'Y-m-d H:i')) ?></td>
                    <td><?php if (\App\Services\AuthorizationService::can($admin, 'view_attempts')): ?><a class="report-button" href="/admin/attempts/view?id=<?= (int)$item['attempt_id'] ?>"><?= e(trans('details')) ?></a><?php else: ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>

        <?php if ((int)$results['total_pages'] > 1): ?><nav class="pagination" aria-label="<?= e(trans('pagination')) ?>"><?php $base=$_GET;unset($base['page']);for($page=1;$page<=(int)$results['total_pages'];$page++):$base['page']=$page;?><a class="page-link <?= $page===(int)$results['page']?'active':'' ?>" href="/admin/reports?<?= e(http_build_query($base)) ?>"><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
    </section>
</div>
<script>
(function(){
    const department=document.getElementById('department_id');
    const section=document.getElementById('section_id');
    if(!department||!section){return;}
    const updateSections=()=>{
        const selectedDepartment=department.value;
        Array.from(section.options).forEach((option,index)=>{
            if(index===0){option.hidden=false;return;}
            const visible=!selectedDepartment||option.dataset.departmentId===selectedDepartment;
            option.hidden=!visible;
            if(!visible&&option.selected){section.value='';}
        });
    };
    department.addEventListener('change',updateSections);
    updateSections();
})();
</script>
<?php $content=ob_get_clean();require __DIR__.'/../../layouts/admin.php'; ?>
