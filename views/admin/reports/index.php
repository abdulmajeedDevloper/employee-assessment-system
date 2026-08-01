<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-reports-index';


use App\Support\TimezoneHelper;

$pageTitle = trans('reports');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$formatPercent = static fn (mixed $value): string =>
    number_format((float)$value, 2) . '%';

$barWidth = static fn (mixed $value): string =>
    number_format(max(0, min(100, (float)$value)), 2, '.', '');

$summary = is_array($summary ?? null) ? $summary : [];
$results = is_array($results ?? null) ? $results : [];
$filters = is_array($filters ?? null) ? $filters : [];
$departments = is_array($departments ?? null) ? $departments : [];
$sections = is_array($sections ?? null) ? $sections : [];
$tests = is_array($tests ?? null) ? $tests : [];
$test_breakdown = is_array($test_breakdown ?? null) ? $test_breakdown : [];
$department_breakdown = is_array($department_breakdown ?? null) ? $department_breakdown : [];

$totalResults = (int)($summary['total_results'] ?? 0);
$assessedEmployees = (int)($summary['assessed_employees'] ?? 0);
$passedResults = (int)($summary['passed_results'] ?? 0);
$failedResults = (int)($summary['failed_results'] ?? 0);
$passRate = (float)($summary['pass_rate'] ?? 0);
$averagePercentage = (float)($summary['average_percentage'] ?? 0);
$highestPercentage = (float)($summary['highest_percentage'] ?? 0);
$lowestPercentage = (float)($summary['lowest_percentage'] ?? 0);

$filterCount = 0;
foreach ([
    $filters['q'] ?? '',
    $filters['department_id'] ?? 0,
    $filters['section_id'] ?? 0,
    $filters['test_id'] ?? 0,
    (($filters['outcome'] ?? 'all') !== 'all' ? $filters['outcome'] : ''),
    (($filters['attempt_status'] ?? 'all') !== 'all' ? $filters['attempt_status'] : ''),
    $filters['submitted_from_input'] ?? '',
    $filters['submitted_until_input'] ?? '',
] as $filterValue) {
    if ($filterValue !== '' && $filterValue !== 0 && $filterValue !== null) {
        $filterCount++;
    }
}

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'filter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M7 12h10M10 19h4"/></svg>',
        'reset' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.2-8.8L4 10"/></svg>',
        'excel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13l4 5M12 13l-4 5"/></svg>',
        'pdf' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 17v-5h2a2 2 0 0 1 0 4H8M14 17v-5h2.5"/></svg>',
        'results' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20V7"/><path d="M2 20h22"/></svg>',
        'employees' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.2A4.8 4.8 0 0 1 8.3 13h1.4a4.8 4.8 0 0 1 4.8 4.8V19"/><circle cx="17" cy="9" r="2.3"/><path d="M15.5 14.3h1.8A3.7 3.7 0 0 1 21 18v1"/></svg>',
        'rate' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19 9 14l4 3 7-9"/><path d="M15 8h5v5"/></svg>',
        'average' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 15V9M12 15V6M16 15v-3"/></svg>',
        'details' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="reports-page">
    <section class="reports-hero">
        <div class="reports-heading">
            <span class="reports-kicker">
                <?= $icon('results') ?>
                <?= e($text('مركز التحليلات الإدارية', 'Administration Analytics Center')) ?>
            </span>

            <h1 class="reports-title"><?= e(trans('reports_and_analytics')) ?></h1>
            <p class="reports-subtitle"><?= e(trans('reports_help')) ?></p>
        </div>

        <div class="reports-hero-actions">
            <a class="reports-btn" href="/admin/dashboard">
                <?= $icon('back') ?>
                <?= e(trans('back_to_dashboard')) ?>
            </a>
        </div>
    </section>

    <?php if (!empty($errorMessage)): ?>
        <div class="reports-alert danger"><?= e((string)$errorMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="reports-alert success"><?= e((string)$_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="reports-alert danger"><?= e((string)$_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="privacy-strip">
        <?= $icon('shield') ?>
        <span><?= e(trans('report_privacy_notice')) ?></span>
    </div>

    <?php if (\App\Services\AuthorizationService::can($admin, 'export_reports')): ?>
        <form method="POST" action="/admin/reports/export" class="reports-card export-panel">
            <?= csrf_field() ?>

            <input type="hidden" name="lang" value="<?= e($currentLang) ?>">
            <input type="hidden" name="q" value="<?= e((string)($filters['q'] ?? '')) ?>">
            <input type="hidden" name="department_id" value="<?= (int)($filters['department_id'] ?? 0) ?: '' ?>">
            <input type="hidden" name="section_id" value="<?= (int)($filters['section_id'] ?? 0) ?: '' ?>">
            <input type="hidden" name="test_id" value="<?= (int)($filters['test_id'] ?? 0) ?: '' ?>">
            <input type="hidden" name="outcome" value="<?= e((string)($filters['outcome'] ?? 'all')) ?>">
            <input type="hidden" name="attempt_status" value="<?= e((string)($filters['attempt_status'] ?? 'all')) ?>">
            <input type="hidden" name="submitted_from" value="<?= e((string)($filters['submitted_from_input'] ?? '')) ?>">
            <input type="hidden" name="submitted_until" value="<?= e((string)($filters['submitted_until_input'] ?? '')) ?>">

            <div class="export-copy">
                <strong><?= e(trans('export_current_report')) ?></strong>
                <small><?= e(trans('export_current_report_help')) ?></small>
            </div>

            <div class="export-actions">
                <button
                    class="reports-btn excel"
                    type="submit"
                    name="format"
                    value="xlsx"
                    <?= $totalResults === 0 ? 'disabled' : '' ?>
                >
                    <?= $icon('excel') ?>
                    <?= e(trans('export_excel')) ?>
                </button>

                <button
                    class="reports-btn pdf"
                    type="submit"
                    name="format"
                    value="pdf"
                    <?= $totalResults === 0 ? 'disabled' : '' ?>
                >
                    <?= $icon('pdf') ?>
                    <?= e(trans('export_pdf')) ?>
                </button>
            </div>
        </form>
    <?php endif; ?>

    <form method="GET" action="/admin/reports" class="reports-card filter-card">
        <div class="reports-card-head">
            <div>
                <h2><?= e($text('البحث والتصفية المتقدمة', 'Advanced Search and Filters')) ?></h2>
                <p><?= e($text('خصص نطاق التقرير قبل مراجعة المؤشرات والنتائج.', 'Refine the report scope before reviewing metrics and results.')) ?></p>
            </div>

            <span class="reports-count"><?= e((string)$filterCount) ?></span>
        </div>

        <div class="reports-card-body">
            <div class="filter-grid">
                <div class="filter-field wide">
                    <label for="q"><?= e(trans('search')) ?></label>
                    <input
                        class="filter-control"
                        id="q"
                        name="q"
                        maxlength="150"
                        value="<?= e((string)($filters['q'] ?? '')) ?>"
                        placeholder="<?= e(trans('search_reports')) ?>"
                    >
                </div>

                <div class="filter-field">
                    <label for="department_id"><?= e(trans('department')) ?></label>
                    <select class="filter-control" id="department_id" name="department_id">
                        <option value=""><?= e(trans('all_departments')) ?></option>

                        <?php foreach ($departments as $department): ?>
                            <?php $name = $currentLang === 'en' ? $department['name_en'] : $department['name_ar']; ?>
                            <option
                                value="<?= (int)$department['id'] ?>"
                                <?= (int)($filters['department_id'] ?? 0) === (int)$department['id'] ? 'selected' : '' ?>
                            >
                                <?= e((string)$name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="section_id"><?= e(trans('section')) ?></label>
                    <select class="filter-control" id="section_id" name="section_id">
                        <option value=""><?= e(trans('all_sections')) ?></option>

                        <?php foreach ($sections as $section): ?>
                            <?php $name = $currentLang === 'en' ? $section['name_en'] : $section['name_ar']; ?>
                            <option
                                data-department-id="<?= (int)$section['department_id'] ?>"
                                value="<?= (int)$section['id'] ?>"
                                <?= (int)($filters['section_id'] ?? 0) === (int)$section['id'] ? 'selected' : '' ?>
                            >
                                <?= e((string)$name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="test_id"><?= e(trans('assessment')) ?></label>
                    <select class="filter-control" id="test_id" name="test_id">
                        <option value=""><?= e(trans('all_assessments')) ?></option>

                        <?php foreach ($tests as $test): ?>
                            <?php $name = $currentLang === 'en' ? $test['name_en'] : $test['name_ar']; ?>
                            <option
                                value="<?= (int)$test['id'] ?>"
                                <?= (int)($filters['test_id'] ?? 0) === (int)$test['id'] ? 'selected' : '' ?>
                            >
                                <?= e((string)$test['code']) ?> — <?= e((string)$name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="outcome"><?= e(trans('result')) ?></label>
                    <select class="filter-control" id="outcome" name="outcome">
                        <?php foreach (['all', 'passed', 'failed'] as $value): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= (string)($filters['outcome'] ?? 'all') === $value ? 'selected' : '' ?>
                            >
                                <?= e(trans($value === 'all' ? 'all_results' : $value)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="attempt_status"><?= e(trans('submission_status')) ?></label>
                    <select class="filter-control" id="attempt_status" name="attempt_status">
                        <?php foreach (['all', 'COMPLETED', 'TIMED_OUT'] as $value): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= (string)($filters['attempt_status'] ?? 'all') === $value ? 'selected' : '' ?>
                            >
                                <?= e(trans($value === 'all' ? 'all_submission_statuses' : 'attempt_status_' . strtolower($value))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="submitted_from"><?= e(trans('submitted_from')) ?></label>
                    <input
                        class="filter-control"
                        type="date"
                        id="submitted_from"
                        name="submitted_from"
                        value="<?= e((string)($filters['submitted_from_input'] ?? '')) ?>"
                    >
                </div>

                <div class="filter-field">
                    <label for="submitted_until"><?= e(trans('submitted_until')) ?></label>
                    <input
                        class="filter-control"
                        type="date"
                        id="submitted_until"
                        name="submitted_until"
                        value="<?= e((string)($filters['submitted_until_input'] ?? '')) ?>"
                    >
                </div>
            </div>

            <div class="filter-footer">
                <span class="active-filter-note">
                    <?= e($text('عدد الفلاتر النشطة:', 'Active filters:')) ?>
                    <?= e((string)$filterCount) ?>
                </span>

                <div class="filter-actions">
                    <button class="reports-btn primary" type="submit">
                        <?= $icon('filter') ?>
                        <?= e(trans('apply_filters')) ?>
                    </button>

                    <a class="reports-btn" href="/admin/reports">
                        <?= $icon('reset') ?>
                        <?= e(trans('reset_filters')) ?>
                    </a>
                </div>
            </div>
        </div>
    </form>

    <section class="reports-card">
        <div class="reports-card-head">
            <div>
                <h2><?= e(trans('report_summary')) ?></h2>
                <p><?= e(trans('official_latest_results')) ?></p>
            </div>
        </div>

        <div class="reports-card-body">
            <div class="metrics-grid">
                <article class="metric-card">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('total_results')) ?></span>
                        <strong class="metric-value"><?= e(number_format($totalResults)) ?></strong>
                        <span class="metric-caption"><?= e($text('نتيجة رسمية', 'Official results')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('results') ?></span>
                </article>

                <article class="metric-card">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('assessed_employees')) ?></span>
                        <strong class="metric-value"><?= e(number_format($assessedEmployees)) ?></strong>
                        <span class="metric-caption"><?= e($text('موظف تم تقييمه', 'Employees assessed')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('employees') ?></span>
                </article>

                <article class="metric-card pass">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('overall_pass_rate')) ?></span>
                        <strong class="metric-value"><bdi dir="ltr"><?= e($formatPercent($passRate)) ?></bdi></strong>
                        <span class="metric-caption">
                            <?= e(number_format($passedResults)) ?>
                            <?= e($text('نتيجة ناجحة', 'passed results')) ?>
                        </span>
                    </div>
                    <span class="metric-icon"><?= $icon('rate') ?></span>
                </article>

                <article class="metric-card">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('average_result')) ?></span>
                        <strong class="metric-value"><bdi dir="ltr"><?= e($formatPercent($averagePercentage)) ?></bdi></strong>
                        <span class="metric-caption"><?= e($text('متوسط جميع النتائج', 'Average across results')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('average') ?></span>
                </article>

                <article class="metric-card pass">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('passed')) ?></span>
                        <strong class="metric-value"><?= e(number_format($passedResults)) ?></strong>
                        <span class="metric-caption"><?= e($text('نتيجة اجتازت الحد المطلوب', 'Results above passing threshold')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('rate') ?></span>
                </article>

                <article class="metric-card fail">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('failed')) ?></span>
                        <strong class="metric-value"><?= e(number_format($failedResults)) ?></strong>
                        <span class="metric-caption"><?= e($text('نتيجة دون الحد المطلوب', 'Results below passing threshold')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('results') ?></span>
                </article>

                <article class="metric-card high">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('highest')) ?></span>
                        <strong class="metric-value"><bdi dir="ltr"><?= e($formatPercent($highestPercentage)) ?></bdi></strong>
                        <span class="metric-caption"><?= e($text('أعلى نتيجة مسجلة', 'Highest recorded result')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('rate') ?></span>
                </article>

                <article class="metric-card">
                    <div class="metric-copy">
                        <span class="metric-label"><?= e(trans('lowest')) ?></span>
                        <strong class="metric-value"><bdi dir="ltr"><?= e($formatPercent($lowestPercentage)) ?></bdi></strong>
                        <span class="metric-caption"><?= e($text('أقل نتيجة مسجلة', 'Lowest recorded result')) ?></span>
                    </div>
                    <span class="metric-icon"><?= $icon('average') ?></span>
                </article>
            </div>
        </div>
    </section>

    <section class="overview-grid">
        <article class="reports-card">
            <div class="reports-card-head">
                <div>
                    <h2><?= e(trans('overall_pass_rate')) ?></h2>
                    <p><?= e($text('ملخص النجاح والإخفاق', 'Passed and failed result summary')) ?></p>
                </div>
            </div>

            <div class="pass-ring-wrap">
                <div>
                    <div class="pass-ring" style="--ring-value:<?= e($barWidth($passRate)) ?>%;">
                        <div>
                            <strong><bdi dir="ltr"><?= e($formatPercent($passRate)) ?></bdi></strong>
                            <span><?= e(trans('pass_rate')) ?></span>
                        </div>
                    </div>

                    <div class="ring-legend">
                        <div class="ring-legend-item">
                            <span><?= e(trans('passed')) ?></span>
                            <strong><?= e(number_format($passedResults)) ?></strong>
                        </div>

                        <div class="ring-legend-item">
                            <span><?= e(trans('failed')) ?></span>
                            <strong><?= e(number_format($failedResults)) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <article class="reports-card">
            <div class="reports-card-head">
                <div>
                    <h2><?= e(trans('performance_by_assessment')) ?></h2>
                    <p><?= e($text('مقارنة متوسط الأداء ونسبة النجاح حسب الاختبار.', 'Compare average performance and pass rate by assessment.')) ?></p>
                </div>

                <span class="reports-count"><?= e((string)count($test_breakdown)) ?></span>
            </div>

            <?php if ($test_breakdown === []): ?>
                <div class="empty-state"><?= e(trans('no_report_data')) ?></div>
            <?php else: ?>
                <div class="breakdown-list">
                    <?php foreach ($test_breakdown as $item): ?>
                        <?php $name = $currentLang === 'en' ? $item['name_en'] : $item['name_ar']; ?>

                        <div class="breakdown-row">
                            <div class="breakdown-name">
                                <strong><bdi dir="ltr"><?= e((string)$item['code']) ?></bdi> — <?= e((string)$name) ?></strong>
                                <small>
                                    <?= e(number_format((int)$item['result_count'])) ?>
                                    <?= e(trans('results')) ?>
                                </small>
                            </div>

                            <div class="breakdown-progress">
                                <div class="breakdown-track">
                                    <div
                                        class="breakdown-fill"
                                        style="--progress-value:<?= e($barWidth($item['average_percentage'])) ?>%;"
                                    ></div>
                                </div>

                                <div class="breakdown-meta">
                                    <span>
                                        <?= e(trans('average_result')) ?>:
                                        <bdi dir="ltr"><?= e($formatPercent($item['average_percentage'])) ?></bdi>
                                    </span>
                                    <span>
                                        <?= e(trans('pass_rate')) ?>:
                                        <bdi dir="ltr"><?= e($formatPercent($item['pass_rate'])) ?></bdi>
                                    </span>
                                </div>
                            </div>

                            <strong class="breakdown-score">
                                <bdi dir="ltr"><?= e($formatPercent($item['average_percentage'])) ?></bdi>
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="analytics-grid">
        <article class="reports-card">
            <div class="reports-card-head">
                <div>
                    <h2><?= e(trans('performance_by_department')) ?></h2>
                    <p><?= e($text('تحليل الأداء على مستوى الإدارات.', 'Department-level performance analysis.')) ?></p>
                </div>

                <span class="reports-count"><?= e((string)count($department_breakdown)) ?></span>
            </div>

            <?php if ($department_breakdown === []): ?>
                <div class="empty-state"><?= e(trans('no_report_data')) ?></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="reports-table compact">
                        <thead>
                        <tr>
                            <th><?= e(trans('department')) ?></th>
                            <th><?= e(trans('assessed_employees')) ?></th>
                            <th><?= e(trans('average_result')) ?></th>
                            <th><?= e(trans('pass_rate')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($department_breakdown as $item): ?>
                            <?php $name = $currentLang === 'en' ? $item['name_en'] : $item['name_ar']; ?>
                            <tr>
                                <td><strong><?= e((string)$name) ?></strong></td>
                                <td><?= e(number_format((int)$item['assessed_employees'])) ?></td>
                                <td>
                                    <div class="breakdown-progress">
                                        <div class="breakdown-track">
                                            <div
                                                class="breakdown-fill"
                                                style="--progress-value:<?= e($barWidth($item['average_percentage'])) ?>%;"
                                            ></div>
                                        </div>
                                        <span><bdi dir="ltr"><?= e($formatPercent($item['average_percentage'])) ?></bdi></span>
                                    </div>
                                </td>
                                <td><bdi dir="ltr"><?= e($formatPercent($item['pass_rate'])) ?></bdi></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <article class="reports-card">
            <div class="reports-card-head">
                <div>
                    <h2><?= e($text('نطاق النتائج الحالية', 'Current Result Range')) ?></h2>
                    <p><?= e($text('قراءة سريعة لأعلى وأدنى ومتوسط نتيجة.', 'A quick view of the highest, lowest and average result.')) ?></p>
                </div>
            </div>

            <div class="breakdown-list">
                <?php
                $rangeItems = [
                    [trans('highest'), $highestPercentage, 'high'],
                    [trans('average_result'), $averagePercentage, 'average'],
                    [trans('lowest'), $lowestPercentage, 'low'],
                ];
                ?>

                <?php foreach ($rangeItems as [$label, $value, $type]): ?>
                    <div class="breakdown-row">
                        <div class="breakdown-name">
                            <strong><?= e((string)$label) ?></strong>
                            <small><?= e($text('ضمن النتائج الرسمية الحالية', 'Within current official results')) ?></small>
                        </div>

                        <div class="breakdown-progress">
                            <div class="breakdown-track">
                                <div
                                    class="breakdown-fill"
                                    style="--progress-value:<?= e($barWidth($value)) ?>%;"
                                ></div>
                            </div>
                        </div>

                        <strong class="breakdown-score">
                            <bdi dir="ltr"><?= e($formatPercent($value)) ?></bdi>
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section class="reports-card">
        <div class="reports-card-head">
            <div>
                <h2><?= e(trans('official_results_directory')) ?></h2>
                <p><?= e(trans('results_found', ['count' => (string)(int)($results['total'] ?? 0)])) ?></p>
            </div>

            <span class="reports-count"><?= e(number_format((int)($results['total'] ?? 0))) ?></span>
        </div>

        <?php if (empty($results['items'])): ?>
            <div class="empty-state"><?= e(trans('no_report_data')) ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="reports-table">
                    <thead>
                    <tr>
                        <th><?= e(trans('employee')) ?></th>
                        <th><?= e(trans('masked_national_id')) ?></th>
                        <th><?= e(trans('organization_unit')) ?></th>
                        <th><?= e(trans('assessment')) ?></th>
                        <th><?= e(trans('attempt_number')) ?></th>
                        <th><?= e(trans('score')) ?></th>
                        <th><?= e(trans('result')) ?></th>
                        <th><?= e(trans('submission_status')) ?></th>
                        <th><?= e(trans('submitted_at')) ?></th>
                        <th><?= e(trans('actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results['items'] as $item): ?>
                        <?php
                        $testName = $currentLang === 'en'
                            ? $item['test_name_en']
                            : $item['test_name_ar'];

                        $departmentName = $currentLang === 'en'
                            ? $item['department_name_en']
                            : $item['department_name_ar'];

                        $sectionName = $currentLang === 'en'
                            ? $item['section_name_en']
                            : $item['section_name_ar'];
                        ?>
                        <tr>
                            <td>
                                <strong><?= e((string)$item['employee_name']) ?></strong><br>
                                <small><bdi dir="ltr"><?= e((string)($item['employee_number'] ?? '')) ?></bdi></small>
                            </td>

                            <td><bdi dir="ltr"><?= e((string)$item['masked_national_id']) ?></bdi></td>

                            <td>
                                <?= e((string)$departmentName) ?><br>
                                <small><?= e((string)$sectionName) ?></small>
                            </td>

                            <td>
                                <strong><bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi></strong><br>
                                <small><?= e((string)$testName) ?></small>
                            </td>

                            <td><bdi dir="ltr"><?= e((string)(int)$item['attempt_number']) ?></bdi></td>

                            <td>
                                <bdi dir="ltr">
                                    <?= e(number_format((float)$item['score'], 2)) ?>
                                    /
                                    <?= e(number_format((float)$item['total_score'], 2)) ?>
                                </bdi><br>
                                <strong><bdi dir="ltr"><?= e($formatPercent($item['percentage'])) ?></bdi></strong>
                            </td>

                            <td>
                                <span class="status-badge <?= (int)$item['passed'] === 1 ? 'pass' : 'fail' ?>">
                                    <?= e(trans((int)$item['passed'] === 1 ? 'passed' : 'failed')) ?>
                                </span>
                            </td>

                            <td>
                                <span class="status-badge <?= (string)$item['attempt_status'] === 'TIMED_OUT' ? 'timeout' : 'complete' ?>">
                                    <?= e(trans('attempt_status_' . strtolower((string)$item['attempt_status']))) ?>
                                </span>
                            </td>

                            <td>
                                <bdi dir="ltr">
                                    <?= e(TimezoneHelper::format((string)$item['submitted_at'], null, 'Y-m-d H:i')) ?>
                                </bdi>
                            </td>

                            <td>
                                <?php if (\App\Services\AuthorizationService::can($admin, 'view_attempts')): ?>
                                    <a class="details-link" href="/admin/attempts/view?id=<?= (int)$item['attempt_id'] ?>">
                                        <?= $icon('details') ?>
                                        <?= e(trans('details')) ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ((int)($results['total_pages'] ?? 0) > 1): ?>
            <nav class="pagination" aria-label="<?= e(trans('pagination')) ?>">
                <?php
                $baseQuery = $_GET;
                unset($baseQuery['page']);
                ?>

                <?php for ($pageNumber = 1; $pageNumber <= (int)$results['total_pages']; $pageNumber++): ?>
                    <?php $baseQuery['page'] = $pageNumber; ?>
                    <a
                        class="page-link <?= $pageNumber === (int)$results['page'] ? 'active' : '' ?>"
                        href="/admin/reports?<?= e(http_build_query($baseQuery)) ?>"
                    >
                        <?= e((string)$pageNumber) ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<script>
(() => {
    'use strict';

    const department = document.getElementById('department_id');
    const section = document.getElementById('section_id');

    if (!department || !section) {
        return;
    }

    const updateSections = () => {
        const selectedDepartment = department.value;

        Array.from(section.options).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            const visible =
                !selectedDepartment ||
                option.dataset.departmentId === selectedDepartment;

            option.hidden = !visible;

            if (!visible && option.selected) {
                section.value = '';
            }
        });
    };

    department.addEventListener('change', updateSections);
    updateSections();
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
