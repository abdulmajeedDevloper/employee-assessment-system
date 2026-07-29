<?php

use App\Support\TimezoneHelper;

$pageTitle = trans('dashboard');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
$metrics = $dashboard['metrics'] ?? $metrics ?? [];
$testPerformance = $dashboard['test_performance'] ?? [];
$departmentPerformance = $dashboard['department_performance'] ?? [];
$recentResults = $dashboard['recent_results'] ?? [];
$expiredAttempts = $dashboard['expired_attempts'] ?? [];
$overdueAssignments = $dashboard['overdue_assignments'] ?? [];
$formatPercent = static fn(mixed $value): string => number_format((float)$value, 2) . '%';
$barWidth = static fn(mixed $value): string => number_format(max(0, min(100, (float)$value)), 2, '.', '');
ob_start();
?>
<style>
.dashboard-shell{display:grid;gap:1.4rem}.dashboard-hero{display:flex;justify-content:space-between;gap:1.25rem;align-items:flex-start;flex-wrap:wrap}.dashboard-title{font-size:1.8rem;margin:0 0 .35rem;color:#f8fafc}.dashboard-subtitle{color:#94a3b8;line-height:1.65;max-width:760px}.dashboard-actions{display:flex;gap:.65rem;flex-wrap:wrap}.dashboard-link{display:inline-flex;align-items:center;justify-content:center;padding:.7rem 1rem;border:1px solid rgba(96,165,250,.45);border-radius:9px;background:rgba(59,130,246,.13);color:#dbeafe;text-decoration:none;font-weight:700}.dashboard-link:hover{background:rgba(59,130,246,.25)}.dashboard-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.dashboard-card,.dashboard-panel{background:rgba(30,41,59,.88);border:1px solid rgba(255,255,255,.1);border-radius:14px;box-shadow:0 12px 25px rgba(0,0,0,.22)}.dashboard-card{padding:1.15rem;min-height:128px}.dashboard-card-label{color:#94a3b8;font-size:.88rem;font-weight:700}.dashboard-card-value{font-size:2rem;font-weight:800;color:#f8fafc;margin:.55rem 0}.dashboard-card-note{font-size:.8rem;color:#cbd5e1}.dashboard-card.alert{border-color:rgba(248,113,113,.42)}.dashboard-card.success{border-color:rgba(74,222,128,.38)}.dashboard-panel{padding:1.25rem}.dashboard-panel-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem}.dashboard-panel h2{font-size:1.15rem;color:#f8fafc;margin:0}.dashboard-panel-note{color:#94a3b8;font-size:.83rem}.dashboard-two{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.dashboard-table-wrap{overflow:auto}.dashboard-table{width:100%;border-collapse:collapse;min-width:650px}.dashboard-table th,.dashboard-table td{padding:.78rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:start;vertical-align:middle}.dashboard-table th{color:#94a3b8;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em}.dashboard-table td{color:#e2e8f0;font-size:.88rem}.dashboard-badge{display:inline-flex;padding:.26rem .55rem;border-radius:999px;font-size:.76rem;font-weight:800}.dashboard-badge.pass{background:rgba(34,197,94,.16);color:#86efac}.dashboard-badge.fail{background:rgba(239,68,68,.16);color:#fca5a5}.dashboard-badge.warn{background:rgba(245,158,11,.17);color:#fcd34d}.performance-list{display:grid;gap:.9rem}.performance-item{display:grid;gap:.45rem}.performance-row{display:flex;justify-content:space-between;gap:1rem;color:#e2e8f0;font-size:.87rem}.performance-track{height:9px;background:#0f172a;border-radius:999px;overflow:hidden;border:1px solid rgba(255,255,255,.08)}.performance-fill{height:100%;background:linear-gradient(90deg,#2563eb,#38bdf8);border-radius:inherit}.followup-list{display:grid;gap:.75rem}.followup-item{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.8rem;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(15,23,42,.46)}.followup-main{display:grid;gap:.2rem}.followup-main strong{color:#f8fafc}.followup-main small{color:#94a3b8}.empty-state{padding:1.3rem;text-align:center;color:#94a3b8;border:1px dashed rgba(255,255,255,.14);border-radius:10px}.metric-detail{display:flex;gap:.7rem;flex-wrap:wrap}.metric-detail span{font-size:.78rem;color:#cbd5e1}.quick-status{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-top:.9rem}.quick-status div{padding:.65rem;background:rgba(15,23,42,.52);border-radius:9px;text-align:center}.quick-status strong{display:block;color:#f8fafc;font-size:1.1rem}.quick-status span{color:#94a3b8;font-size:.72rem}@media(max-width:1050px){.dashboard-grid{grid-template-columns:repeat(2,1fr)}.dashboard-two{grid-template-columns:1fr}}@media(max-width:620px){.dashboard-grid{grid-template-columns:1fr}.dashboard-panel,.dashboard-card{padding:1rem}.dashboard-actions{width:100%}.dashboard-link{flex:1}.quick-status{grid-template-columns:1fr}}
</style>

<div class="dashboard-shell">
    <section class="dashboard-panel dashboard-hero">
        <div>
            <h1 class="dashboard-title"><?= e(trans('dashboard_overview')) ?></h1>
            <p class="dashboard-subtitle"><?= e(trans('dashboard_overview_help')) ?></p>
            <div class="admin-role-badge" style="margin-top:.8rem"><?= e(trans('role')) ?>: <?= e(trans($admin['role'] ?? '')) ?></div>
        </div>
        <div class="dashboard-actions">
            <?php if (\App\Services\AuthorizationService::can($admin, 'view_reports')): ?>
                <a class="dashboard-link" href="/admin/reports"><?= e(trans('open_reports')) ?></a>
            <?php endif; ?>
            <?php if (\App\Services\AuthorizationService::can($admin, 'view_attempts')): ?>
                <a class="dashboard-link" href="/admin/attempts?attention=expired"><?= e(trans('review_follow_up_cases')) ?></a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert-box alert-danger"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <section class="dashboard-grid" aria-label="<?= e(trans('key_performance_indicators')) ?>">
        <article class="dashboard-card">
            <div class="dashboard-card-label"><?= e(trans('active_employees')) ?></div>
            <div class="dashboard-card-value"><?= (int)($metrics['active_employees'] ?? 0) ?></div>
            <div class="dashboard-card-note"><?= e(trans('out_of_total_employees', ['count' => (string)(int)($metrics['total_employees'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card success">
            <div class="dashboard-card-label"><?= e(trans('assessment_completion_rate')) ?></div>
            <div class="dashboard-card-value"><bdi dir="ltr"><?= e($formatPercent($metrics['completion_rate'] ?? 0)) ?></bdi></div>
            <div class="dashboard-card-note"><?= e(trans('employees_completed_count', ['count' => (string)(int)($metrics['completed_employees'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card success">
            <div class="dashboard-card-label"><?= e(trans('overall_pass_rate')) ?></div>
            <div class="dashboard-card-value"><bdi dir="ltr"><?= e($formatPercent($metrics['pass_rate'] ?? 0)) ?></bdi></div>
            <div class="dashboard-card-note"><?= e(trans('official_results_count', ['count' => (string)(int)($metrics['official_results'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card">
            <div class="dashboard-card-label"><?= e(trans('average_result')) ?></div>
            <div class="dashboard-card-value"><bdi dir="ltr"><?= e($formatPercent($metrics['average_percentage'] ?? 0)) ?></bdi></div>
            <div class="metric-detail">
                <span><?= e(trans('highest')) ?>: <bdi dir="ltr"><?= e($formatPercent($metrics['highest_percentage'] ?? 0)) ?></bdi></span>
                <span><?= e(trans('lowest')) ?>: <bdi dir="ltr"><?= e($formatPercent($metrics['lowest_percentage'] ?? 0)) ?></bdi></span>
            </div>
        </article>
        <article class="dashboard-card">
            <div class="dashboard-card-label"><?= e(trans('assessment_progress')) ?></div>
            <div class="quick-status">
                <div><strong><?= (int)($metrics['not_started_employees'] ?? 0) ?></strong><span><?= e(trans('not_started')) ?></span></div>
                <div><strong><?= (int)($metrics['in_progress_employees'] ?? 0) ?></strong><span><?= e(trans('in_progress')) ?></span></div>
                <div><strong><?= (int)($metrics['completed_employees'] ?? 0) ?></strong><span><?= e(trans('completed')) ?></span></div>
            </div>
        </article>
        <article class="dashboard-card">
            <div class="dashboard-card-label"><?= e(trans('assignment_activity')) ?></div>
            <div class="dashboard-card-value"><?= (int)($metrics['active_assignments'] ?? 0) ?></div>
            <div class="dashboard-card-note"><?= e(trans('completed_assignments_count', ['count' => (string)(int)($metrics['completed_assignments'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card<?= (int)($metrics['expired_in_progress_attempts'] ?? 0) > 0 ? ' alert' : '' ?>">
            <div class="dashboard-card-label"><?= e(trans('active_attempts')) ?></div>
            <div class="dashboard-card-value"><?= (int)($metrics['active_attempts'] ?? 0) ?></div>
            <div class="dashboard-card-note"><?= e(trans('expired_attempts_count', ['count' => (string)(int)($metrics['expired_in_progress_attempts'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card<?= (int)($metrics['overdue_assignments'] ?? 0) > 0 ? ' alert' : '' ?>">
            <div class="dashboard-card-label"><?= e(trans('attention_required')) ?></div>
            <div class="dashboard-card-value"><?= (int)($metrics['overdue_assignments'] ?? 0) + (int)($metrics['expired_in_progress_attempts'] ?? 0) ?></div>
            <div class="dashboard-card-note"><?= e(trans('security_events_last_7_days', ['count' => (string)(int)($metrics['security_events_last_7_days'] ?? 0)])) ?></div>
        </article>
    </section>

    <section class="dashboard-two">
        <article class="dashboard-panel">
            <div class="dashboard-panel-head"><h2><?= e(trans('test_performance')) ?></h2><span class="dashboard-panel-note"><?= e(trans('official_latest_results')) ?></span></div>
            <?php if (empty($testPerformance)): ?>
                <div class="empty-state"><?= e(trans('no_report_data')) ?></div>
            <?php else: ?>
                <div class="performance-list">
                    <?php foreach ($testPerformance as $test): $name = $currentLang === 'en' ? $test['name_en'] : $test['name_ar']; ?>
                        <div class="performance-item">
                            <div class="performance-row"><strong><bdi dir="ltr"><?= e((string)$test['code']) ?></bdi> — <?= e((string)$name) ?></strong><span><bdi dir="ltr"><?= e($formatPercent($test['average_percentage'])) ?></bdi> · <?= (int)$test['result_count'] ?></span></div>
                            <div class="performance-track" role="img" aria-label="<?= e(trans('average_result')) ?> <?= e($formatPercent($test['average_percentage'])) ?>"><div class="performance-fill" style="width:<?= e($barWidth($test['average_percentage'])) ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="dashboard-panel">
            <div class="dashboard-panel-head"><h2><?= e(trans('department_performance')) ?></h2><span class="dashboard-panel-note"><?= e(trans('ranked_by_average')) ?></span></div>
            <?php if (empty($departmentPerformance)): ?>
                <div class="empty-state"><?= e(trans('no_report_data')) ?></div>
            <?php else: ?>
                <div class="performance-list">
                    <?php foreach ($departmentPerformance as $department): $name = $currentLang === 'en' ? $department['name_en'] : $department['name_ar']; ?>
                        <div class="performance-item">
                            <div class="performance-row"><strong><?= e((string)$name) ?></strong><span><bdi dir="ltr"><?= e($formatPercent($department['average_percentage'])) ?></bdi> · <?= (int)$department['assessed_employees'] ?> <?= e(trans('employees_short')) ?></span></div>
                            <div class="performance-track"><div class="performance-fill" style="width:<?= e($barWidth($department['average_percentage'])) ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="dashboard-panel">
        <div class="dashboard-panel-head"><h2><?= e(trans('recent_official_results')) ?></h2><?php if (\App\Services\AuthorizationService::can($admin, 'view_reports')): ?><a href="/admin/reports" class="dashboard-link"><?= e(trans('view_all')) ?></a><?php endif; ?></div>
        <?php if (empty($recentResults)): ?>
            <div class="empty-state"><?= e(trans('no_report_data')) ?></div>
        <?php else: ?>
            <div class="dashboard-table-wrap"><table class="dashboard-table">
                <thead><tr><th><?= e(trans('employee')) ?></th><th><?= e(trans('assessment')) ?></th><th><?= e(trans('result')) ?></th><th><?= e(trans('status')) ?></th><th><?= e(trans('submitted_at')) ?></th><th><?= e(trans('actions')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($recentResults as $result): $testName = $currentLang === 'en' ? $result['test_name_en'] : $result['test_name_ar']; ?>
                    <tr>
                        <td><strong><?= e((string)$result['employee_name']) ?></strong><br><small><bdi dir="ltr"><?= e((string)($result['employee_number'] ?? '')) ?></bdi></small></td>
                        <td><bdi dir="ltr"><?= e((string)$result['test_code']) ?></bdi><br><small><?= e((string)$testName) ?></small></td>
                        <td><bdi dir="ltr"><?= e($formatPercent($result['percentage'])) ?></bdi></td>
                        <td><span class="dashboard-badge <?= (int)$result['passed'] === 1 ? 'pass' : 'fail' ?>"><?= e(trans((int)$result['passed'] === 1 ? 'passed' : 'failed')) ?></span></td>
                        <td><?= e(TimezoneHelper::format((string)$result['submitted_at'], null, 'Y-m-d H:i')) ?></td>
                        <td><?php if (\App\Services\AuthorizationService::can($admin, 'view_attempts')): ?><a class="dashboard-link" href="/admin/attempts/view?id=<?= (int)$result['attempt_id'] ?>"><?= e(trans('details')) ?></a><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <?php if (\App\Services\AuthorizationService::can($admin, 'view_attempts')): ?>
    <section class="dashboard-two">
        <article class="dashboard-panel">
            <div class="dashboard-panel-head"><h2><?= e(trans('expired_attempts_follow_up')) ?></h2><a href="/admin/attempts?attention=expired" class="dashboard-link"><?= e(trans('view_all')) ?></a></div>
            <?php if (empty($expiredAttempts)): ?><div class="empty-state"><?= e(trans('no_expired_attempts')) ?></div><?php else: ?>
                <div class="followup-list"><?php foreach ($expiredAttempts as $item): $testName = $currentLang === 'en' ? $item['test_name_en'] : $item['test_name_ar']; ?>
                    <div class="followup-item"><div class="followup-main"><strong><?= e((string)$item['employee_name']) ?></strong><small><bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi> — <?= e((string)$testName) ?> · <?= e(TimezoneHelper::format((string)$item['expires_at'], null, 'Y-m-d H:i')) ?></small></div><a class="dashboard-link" href="/admin/attempts/view?id=<?= (int)$item['attempt_id'] ?>"><?= e(trans('review')) ?></a></div>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </article>
        <article class="dashboard-panel">
            <div class="dashboard-panel-head"><h2><?= e(trans('overdue_assignments_follow_up')) ?></h2><a href="/admin/assignments?availability=expired" class="dashboard-link"><?= e(trans('view_all')) ?></a></div>
            <?php if (empty($overdueAssignments)): ?><div class="empty-state"><?= e(trans('no_overdue_assignments')) ?></div><?php else: ?>
                <div class="followup-list"><?php foreach ($overdueAssignments as $item): $testName = $currentLang === 'en' ? $item['test_name_en'] : $item['test_name_ar']; ?>
                    <div class="followup-item"><div class="followup-main"><strong><?= e((string)$item['employee_name']) ?></strong><small><bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi> — <?= e((string)$testName) ?> · <?= e(TimezoneHelper::format((string)$item['available_until'], null, 'Y-m-d H:i')) ?></small></div><a class="dashboard-link" href="/admin/assignments/view?id=<?= (int)$item['assignment_id'] ?>"><?= e(trans('review')) ?></a></div>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </article>
    </section>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/admin.php';
?>
