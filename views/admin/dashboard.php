<?php
$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-dashboard';


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
$attentionCount = (int)($metrics['overdue_assignments'] ?? 0) + (int)($metrics['expired_in_progress_attempts'] ?? 0);

$icon = static function (string $name): string {
    $icons = [
        'overview' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3"/><path d="M3 21h18"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>',
        'review' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.8 3 8.2 7 10 4-1.8 7-5.2 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg>',
        'star' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>',
        'progress' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 9 9"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
        'pulse' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l2-5 4 10 2-5h6"/></svg>',
        'alert' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 20h18z"/><path d="M12 9v4M12 17h.01"/></svg>',
        'test' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h8M9 3v3h6V3"/><rect x="5" y="5" width="14" height="16" rx="2"/><path d="m8 12 2 2 4-4M8 17h8"/></svg>',
        'building' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V7l8-4 8 4v14M2 21h20"/><path d="M8 10h2M14 10h2M8 14h2M14 14h2M10 21v-3h4v3"/></svg>',
        'results' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v4H7z"/><path d="M5 7h14v14H5z"/><path d="m8 14 2 2 5-5"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg>',
        'hourglass' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12M6 21h12M8 3c0 5 8 5 8 9s-8 4-8 9M16 3c0 5-8 5-8 9s8 4 8 9"/></svg>',
        'inbox' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l2 10v4H2v-4z"/><path d="M2 15h6l2 2h4l2-2h6"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    ];

    return $icons[$name] ?? $icons['overview'];
};

ob_start();
?>

<div class="dashboard-shell">
    <section class="dashboard-panel dashboard-hero">
        <div class="dashboard-hero-content">
            <div class="dashboard-hero-icon"><?= $icon('overview') ?></div>
            <div>
                <h1 class="dashboard-title"><?= e(trans('dashboard_overview')) ?></h1>
                <p class="dashboard-subtitle"><?= e(trans('dashboard_overview_help')) ?></p>
                <div class="dashboard-role"><?= e(trans('role')) ?>: <?= e(trans($admin['role'] ?? '')) ?></div>
            </div>
        </div>
        <div class="dashboard-actions">
            <?php if (\App\Services\AuthorizationService::can($admin, 'view_reports')): ?>
                <a class="dashboard-link primary" href="/admin/reports"><?= $icon('reports') ?><span><?= e(trans('open_reports')) ?></span></a>
            <?php endif; ?>
            <?php if (\App\Services\AuthorizationService::can($admin, 'view_attempts')): ?>
                <a class="dashboard-link" href="/admin/attempts?attention=expired"><?= $icon('review') ?><span><?= e(trans('review_follow_up_cases')) ?></span></a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert-box alert-danger"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <section class="dashboard-grid" aria-label="<?= e(trans('key_performance_indicators')) ?>">
        <article class="dashboard-card">
            <div class="dashboard-card-head"><div class="dashboard-card-label"><?= e(trans('active_employees')) ?></div><div class="dashboard-card-icon"><?= $icon('users') ?></div></div>
            <div class="dashboard-card-value"><?= (int)($metrics['active_employees'] ?? 0) ?></div>
            <div class="dashboard-card-note"><?= e(trans('out_of_total_employees', ['count' => (string)(int)($metrics['total_employees'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card success">
            <div class="dashboard-card-head"><div class="dashboard-card-label"><?= e(trans('assessment_completion_rate')) ?></div><div class="dashboard-card-icon"><?= $icon('check') ?></div></div>
            <div class="dashboard-card-value"><bdi dir="ltr"><?= e($formatPercent($metrics['completion_rate'] ?? 0)) ?></bdi></div>
            <div class="dashboard-card-note"><?= e(trans('employees_completed_count', ['count' => (string)(int)($metrics['completed_employees'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card success purple">
            <div class="dashboard-card-head"><div class="dashboard-card-label"><?= e(trans('overall_pass_rate')) ?></div><div class="dashboard-card-icon"><?= $icon('shield') ?></div></div>
            <div class="dashboard-card-value"><bdi dir="ltr"><?= e($formatPercent($metrics['pass_rate'] ?? 0)) ?></bdi></div>
            <div class="dashboard-card-note"><?= e(trans('official_results_count', ['count' => (string)(int)($metrics['official_results'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card orange">
            <div class="dashboard-card-head"><div class="dashboard-card-label"><?= e(trans('average_result')) ?></div><div class="dashboard-card-icon"><?= $icon('star') ?></div></div>
            <div class="dashboard-card-value"><bdi dir="ltr"><?= e($formatPercent($metrics['average_percentage'] ?? 0)) ?></bdi></div>
            <div class="metric-detail">
                <span class="high"><?= e(trans('highest')) ?>: <bdi dir="ltr"><?= e($formatPercent($metrics['highest_percentage'] ?? 0)) ?></bdi></span>
                <span class="low"><?= e(trans('lowest')) ?>: <bdi dir="ltr"><?= e($formatPercent($metrics['lowest_percentage'] ?? 0)) ?></bdi></span>
            </div>
        </article>
        <article class="dashboard-card">
            <div class="dashboard-card-head"><div class="dashboard-card-label"><?= e(trans('assessment_progress')) ?></div><div class="dashboard-card-icon"><?= $icon('progress') ?></div></div>
            <div class="quick-status">
                <div><strong><?= (int)($metrics['not_started_employees'] ?? 0) ?></strong><span><?= e(trans('not_started')) ?></span></div>
                <div><strong><?= (int)($metrics['in_progress_employees'] ?? 0) ?></strong><span><?= e(trans('in_progress')) ?></span></div>
                <div><strong><?= (int)($metrics['completed_employees'] ?? 0) ?></strong><span><?= e(trans('completed')) ?></span></div>
            </div>
        </article>
        <article class="dashboard-card orange">
            <div class="dashboard-card-head"><div class="dashboard-card-label"><?= e(trans('assignment_activity')) ?></div><div class="dashboard-card-icon"><?= $icon('calendar') ?></div></div>
            <div class="dashboard-card-value"><?= (int)($metrics['active_assignments'] ?? 0) ?></div>
            <div class="dashboard-card-note"><?= e(trans('completed_assignments_count', ['count' => (string)(int)($metrics['completed_assignments'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card teal<?= (int)($metrics['expired_in_progress_attempts'] ?? 0) > 0 ? ' alert' : '' ?>">
            <div class="dashboard-card-head"><div class="dashboard-card-label"><?= e(trans('active_attempts')) ?></div><div class="dashboard-card-icon"><?= $icon('pulse') ?></div></div>
            <div class="dashboard-card-value"><?= (int)($metrics['active_attempts'] ?? 0) ?></div>
            <div class="dashboard-card-note"><?= e(trans('expired_attempts_count', ['count' => (string)(int)($metrics['expired_in_progress_attempts'] ?? 0)])) ?></div>
        </article>
        <article class="dashboard-card alert">
            <div class="dashboard-card-head"><div class="dashboard-card-label"><?= e(trans('attention_required')) ?></div><div class="dashboard-card-icon"><?= $icon('alert') ?></div></div>
            <div class="dashboard-card-value"><?= $attentionCount ?></div>
            <div class="dashboard-card-note"><?= e(trans('security_events_last_7_days', ['count' => (string)(int)($metrics['security_events_last_7_days'] ?? 0)])) ?></div>
        </article>
    </section>

    <section class="dashboard-two">
        <article class="dashboard-panel">
            <div class="dashboard-panel-head">
                <div class="dashboard-heading"><span class="dashboard-heading-icon"><?= $icon('test') ?></span><h2><?= e(trans('test_performance')) ?></h2></div>
                <span class="dashboard-panel-note"><?= e(trans('official_latest_results')) ?></span>
            </div>
            <?php if (empty($testPerformance)): ?>
                <div class="empty-state"><div class="empty-state-inner"><span class="empty-state-icon"><?= $icon('inbox') ?></span><span><?= e(trans('no_report_data')) ?></span></div></div>
            <?php else: ?>
                <div class="performance-list">
                    <?php foreach ($testPerformance as $test): $name = $currentLang === 'en' ? $test['name_en'] : $test['name_ar']; ?>
                        <div class="performance-item">
                            <div class="performance-row"><strong><bdi dir="ltr"><?= e((string)$test['code']) ?></bdi> — <?= e((string)$name) ?></strong><span><bdi dir="ltr"><?= e($formatPercent($test['average_percentage'])) ?></bdi> · <?= (int)$test['result_count'] ?></span></div>
                            <div class="performance-track" role="img" aria-label="<?= e(trans('average_result')) ?> <?= e($formatPercent($test['average_percentage'])) ?>"><div class="performance-fill" style="--progress-value:<?= e($barWidth($test['average_percentage'])) ?>%;"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="dashboard-panel">
            <div class="dashboard-panel-head">
                <div class="dashboard-heading"><span class="dashboard-heading-icon"><?= $icon('building') ?></span><h2><?= e(trans('department_performance')) ?></h2></div>
                <span class="dashboard-panel-note"><?= e(trans('ranked_by_average')) ?></span>
            </div>
            <?php if (empty($departmentPerformance)): ?>
                <div class="empty-state"><div class="empty-state-inner"><span class="empty-state-icon"><?= $icon('inbox') ?></span><span><?= e(trans('no_report_data')) ?></span></div></div>
            <?php else: ?>
                <div class="performance-list">
                    <?php foreach ($departmentPerformance as $department): $name = $currentLang === 'en' ? $department['name_en'] : $department['name_ar']; ?>
                        <div class="performance-item">
                            <div class="performance-row"><strong><?= e((string)$name) ?></strong><span><bdi dir="ltr"><?= e($formatPercent($department['average_percentage'])) ?></bdi> · <?= (int)$department['assessed_employees'] ?> <?= e(trans('employees_short')) ?></span></div>
                            <div class="performance-track"><div class="performance-fill" style="--progress-value:<?= e($barWidth($department['average_percentage'])) ?>%;"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="dashboard-panel">
        <div class="dashboard-panel-head">
            <div class="dashboard-heading"><span class="dashboard-heading-icon"><?= $icon('results') ?></span><h2><?= e(trans('recent_official_results')) ?></h2></div>
            <?php if (\App\Services\AuthorizationService::can($admin, 'view_reports')): ?><a href="/admin/reports" class="dashboard-link compact"><span><?= e(trans('view_all')) ?></span><?= $icon('arrow') ?></a><?php endif; ?>
        </div>
        <?php if (empty($recentResults)): ?>
            <div class="empty-state"><div class="empty-state-inner"><span class="empty-state-icon"><?= $icon('inbox') ?></span><span><?= e(trans('no_report_data')) ?></span></div></div>
        <?php else: ?>
            <div class="dashboard-table-wrap"><table class="dashboard-table">
                <thead><tr><th><?= e(trans('employee')) ?></th><th><?= e(trans('assessment')) ?></th><th><?= e(trans('result')) ?></th><th><?= e(trans('status')) ?></th><th><?= e(trans('submitted_at')) ?></th><th><?= e(trans('actions')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($recentResults as $result): $testName = $currentLang === 'en' ? $result['test_name_en'] : $result['test_name_ar']; ?>
                    <tr>
                        <td><div class="dashboard-person"><span class="dashboard-avatar">EM</span><span class="dashboard-person-copy"><strong><?= e((string)$result['employee_name']) ?></strong><small><bdi dir="ltr"><?= e((string)($result['employee_number'] ?? '')) ?></bdi></small></span></div></td>
                        <td><strong><bdi dir="ltr"><?= e((string)$result['test_code']) ?></bdi></strong><br><small><?= e((string)$testName) ?></small></td>
                        <td><strong><bdi dir="ltr"><?= e($formatPercent($result['percentage'])) ?></bdi></strong></td>
                        <td><span class="dashboard-badge <?= (int)$result['passed'] === 1 ? 'pass' : 'fail' ?>"><?= e(trans((int)$result['passed'] === 1 ? 'passed' : 'failed')) ?></span></td>
                        <td><bdi dir="ltr"><?= e(TimezoneHelper::format((string)$result['submitted_at'], null, 'Y-m-d H:i')) ?></bdi></td>
                        <td><?php if (\App\Services\AuthorizationService::can($admin, 'view_attempts')): ?><a class="dashboard-link compact" href="/admin/attempts/view?id=<?= (int)$result['attempt_id'] ?>"><?= $icon('eye') ?><span><?= e(trans('details')) ?></span></a><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <?php if (\App\Services\AuthorizationService::can($admin, 'view_attempts')): ?>
    <section class="dashboard-two">
        <article class="dashboard-panel">
            <div class="dashboard-panel-head">
                <div class="dashboard-heading"><span class="dashboard-heading-icon"><?= $icon('hourglass') ?></span><h2><?= e(trans('expired_attempts_follow_up')) ?></h2></div>
                <a href="/admin/attempts?attention=expired" class="dashboard-link compact"><span><?= e(trans('view_all')) ?></span><?= $icon('arrow') ?></a>
            </div>
            <?php if (empty($expiredAttempts)): ?>
                <div class="empty-state"><div class="empty-state-inner"><span class="empty-state-icon"><?= $icon('inbox') ?></span><span><?= e(trans('no_expired_attempts')) ?></span></div></div>
            <?php else: ?>
                <div class="followup-list"><?php foreach ($expiredAttempts as $item): $testName = $currentLang === 'en' ? $item['test_name_en'] : $item['test_name_ar']; ?>
                    <div class="followup-item"><div class="followup-main"><strong><?= e((string)$item['employee_name']) ?></strong><small><bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi> — <?= e((string)$testName) ?> · <bdi dir="ltr"><?= e(TimezoneHelper::format((string)$item['expires_at'], null, 'Y-m-d H:i')) ?></bdi></small></div><a class="dashboard-link compact" href="/admin/attempts/view?id=<?= (int)$item['attempt_id'] ?>"><?= e(trans('review')) ?></a></div>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </article>
        <article class="dashboard-panel">
            <div class="dashboard-panel-head">
                <div class="dashboard-heading"><span class="dashboard-heading-icon"><?= $icon('calendar') ?></span><h2><?= e(trans('overdue_assignments_follow_up')) ?></h2></div>
                <a href="/admin/assignments?availability=expired" class="dashboard-link compact"><span><?= e(trans('view_all')) ?></span><?= $icon('arrow') ?></a>
            </div>
            <?php if (empty($overdueAssignments)): ?>
                <div class="empty-state"><div class="empty-state-inner"><span class="empty-state-icon"><?= $icon('inbox') ?></span><span><?= e(trans('no_overdue_assignments')) ?></span></div></div>
            <?php else: ?>
                <div class="followup-list"><?php foreach ($overdueAssignments as $item): $testName = $currentLang === 'en' ? $item['test_name_en'] : $item['test_name_ar']; ?>
                    <div class="followup-item"><div class="followup-main"><strong><?= e((string)$item['employee_name']) ?></strong><small><bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi> — <?= e((string)$testName) ?> · <bdi dir="ltr"><?= e(TimezoneHelper::format((string)$item['available_until'], null, 'Y-m-d H:i')) ?></bdi></small></div><a class="dashboard-link compact" href="/admin/assignments/view?id=<?= (int)$item['assignment_id'] ?>"><?= e(trans('review')) ?></a></div>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </article>
    </section>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/admin.php';
