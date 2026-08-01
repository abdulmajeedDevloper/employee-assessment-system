<?php
$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-attempts-index';


use App\Support\TimezoneHelper;

$pageTitle = trans('attempt_monitoring');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$filters = $filters ?? [
    'assignment_id' => '',
    'q' => '',
    'employee_id' => '',
    'test_id' => '',
    'status' => 'all',
    'outcome' => 'all',
    'attention' => 'all',
    'started_from_input' => '',
    'started_until_input' => '',
];

$employees = $employees ?? [];
$tests = $tests ?? [];
$statistics = $statistics ?? [
    'total_attempts' => 0,
    'in_progress_attempts' => 0,
    'completed_attempts' => 0,
    'timed_out_attempts' => 0,
    'expired_in_progress_attempts' => 0,
    'passed_attempts' => 0,
    'failed_attempts' => 0,
    'cancelled_attempts' => 0,
];

$results = $results ?? [
    'items' => [],
    'total' => 0,
    'page' => 1,
    'total_pages' => 1,
];

$items = $results['items'] ?? [];
$totalAttempts = (int)($results['total'] ?? count($items));
$currentPage = max(1, (int)($results['page'] ?? 1));
$totalPages = max(1, (int)($results['total_pages'] ?? 1));

$activeFiltersCount = 0;
if (!empty($filters['assignment_id'])) {
    $activeFiltersCount++;
}
if (trim((string)($filters['q'] ?? '')) !== '') {
    $activeFiltersCount++;
}
if ((int)($filters['employee_id'] ?? 0) > 0) {
    $activeFiltersCount++;
}
if ((int)($filters['test_id'] ?? 0) > 0) {
    $activeFiltersCount++;
}
if (($filters['status'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}
if (($filters['outcome'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}
if (($filters['attention'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}
if (trim((string)($filters['started_from_input'] ?? '')) !== '') {
    $activeFiltersCount++;
}
if (trim((string)($filters['started_until_input'] ?? '')) !== '') {
    $activeFiltersCount++;
}

$metricCards = [
    [
        'label' => trans('attempts_total'),
        'value' => (int)$statistics['total_attempts'],
        'tone' => 'blue',
    ],
    [
        'label' => trans('attempt_status_in_progress'),
        'value' => (int)$statistics['in_progress_attempts'],
        'tone' => 'cyan',
    ],
    [
        'label' => trans('attempts_completed_and_timed_out'),
        'value' => (int)$statistics['completed_attempts'] + (int)$statistics['timed_out_attempts'],
        'tone' => 'green',
    ],
    [
        'label' => trans('expired_in_progress_attempts'),
        'value' => (int)$statistics['expired_in_progress_attempts'],
        'tone' => 'red',
    ],
    [
        'label' => trans('passed'),
        'value' => (int)$statistics['passed_attempts'],
        'tone' => 'green',
    ],
    [
        'label' => trans('failed'),
        'value' => (int)$statistics['failed_attempts'],
        'tone' => 'red',
    ],
    [
        'label' => trans('attempt_status_timed_out'),
        'value' => (int)$statistics['timed_out_attempts'],
        'tone' => 'amber',
    ],
    [
        'label' => trans('attempt_status_cancelled'),
        'value' => (int)$statistics['cancelled_attempts'],
        'tone' => 'slate',
    ],
];

ob_start();
?>

<div class="attempt-page">
    <section class="attempt-page-header">
        <div class="attempt-page-heading">
            <h1 class="attempt-page-title"><?= e(trans('attempt_monitoring')) ?></h1>
            <p class="attempt-page-subtitle">
                <?= e($isArabic
                    ? 'مراجعة محاولات الاختبارات والإجابات وجلسات الأجهزة والأحداث الأمنية وملاحظات المشرف وإجراءات المتابعة.'
                    : 'Review assessment attempts, answers, device sessions, security events, supervisor notes, and follow-up actions.') ?>
            </p>
        </div>

        <a href="/admin/assignments" class="attempt-assignments-btn">
            <?= e(trans('assignments')) ?>
        </a>
    </section>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="attempt-alert success">
            <?= e((string)$_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="attempt-alert error">
            <?= e((string)$_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="attempt-alert error">
            <?= e((string)$errorMessage) ?>
        </div>
    <?php endif; ?>

    <section class="attempt-metrics" aria-label="<?= e(trans('attempt_statistics')) ?>">
        <?php foreach ($metricCards as $metric): ?>
            <article class="attempt-metric <?= e($metric['tone']) ?>">
                <span class="attempt-metric-label"><?= e((string)$metric['label']) ?></span>
                <strong class="attempt-metric-value"><?= (int)$metric['value'] ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="attempt-filter-card">
        <div class="attempt-section-heading">
            <h2 class="attempt-section-title">
                <?= e($isArabic ? 'البحث والتصفية' : 'Search and Filters') ?>
            </h2>

            <?php if ($activeFiltersCount > 0): ?>
                <span class="attempt-filter-count">
                    <?= e($isArabic
                        ? 'عوامل التصفية النشطة: ' . $activeFiltersCount
                        : 'Active filters: ' . $activeFiltersCount) ?>
                </span>
            <?php endif; ?>
        </div>

        <form action="/admin/attempts" method="GET">
            <?php if (!empty($filters['assignment_id'])): ?>
                <input type="hidden" name="assignment_id" value="<?= (int)$filters['assignment_id'] ?>">
            <?php endif; ?>

            <?php if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)): ?>
                <input type="hidden" name="lang" value="<?= e($_GET['lang']) ?>">
            <?php endif; ?>

            <div class="attempt-filter-grid">
                <div class="attempt-field attempt-search">
                    <label class="attempt-label" for="attempt-search"><?= e(trans('search')) ?></label>
                    <input
                        id="attempt-search"
                        name="q"
                        type="search"
                        class="attempt-control"
                        maxlength="150"
                        value="<?= e((string)($filters['q'] ?? '')) ?>"
                        placeholder="<?= e(trans('search_attempts')) ?>"
                        autocomplete="off"
                    >
                </div>

                <div class="attempt-field">
                    <label class="attempt-label" for="attempt-employee"><?= e(trans('employee')) ?></label>
                    <select id="attempt-employee" name="employee_id" class="attempt-control">
                        <option value=""><?= e(trans('all_employees')) ?></option>
                        <?php foreach ($employees as $employee): ?>
                            <option
                                value="<?= (int)$employee['id'] ?>"
                                <?= (int)($filters['employee_id'] ?? 0) === (int)$employee['id'] ? 'selected' : '' ?>
                            >
                                <?= e((string)$employee['full_name']) ?>
                                <?= !empty($employee['employee_number']) ? ' — ' . e((string)$employee['employee_number']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="attempt-field">
                    <label class="attempt-label" for="attempt-test"><?= e(trans('assessment')) ?></label>
                    <select id="attempt-test" name="test_id" class="attempt-control">
                        <option value=""><?= e(trans('all_assessments')) ?></option>
                        <?php foreach ($tests as $test): ?>
                            <?php $testName = $currentLang === 'en' ? $test['name_en'] : $test['name_ar']; ?>
                            <option
                                value="<?= (int)$test['id'] ?>"
                                <?= (int)($filters['test_id'] ?? 0) === (int)$test['id'] ? 'selected' : '' ?>
                            >
                                <?= e((string)$test['code'] . ' — ' . (string)$testName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="attempt-field">
                    <label class="attempt-label" for="attempt-status"><?= e(trans('status')) ?></label>
                    <select id="attempt-status" name="status" class="attempt-control">
                        <?php foreach (['all', 'IN_PROGRESS', 'COMPLETED', 'TIMED_OUT', 'CANCELLED'] as $statusValue): ?>
                            <option
                                value="<?= e($statusValue) ?>"
                                <?= (string)($filters['status'] ?? 'all') === $statusValue ? 'selected' : '' ?>
                            >
                                <?= e(trans(
                                    $statusValue === 'all'
                                        ? 'all_statuses'
                                        : 'attempt_status_' . strtolower($statusValue)
                                )) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="attempt-field">
                    <label class="attempt-label" for="attempt-outcome"><?= e(trans('result')) ?></label>
                    <select id="attempt-outcome" name="outcome" class="attempt-control">
                        <?php foreach (['all', 'passed', 'failed', 'ungraded'] as $outcomeValue): ?>
                            <option
                                value="<?= e($outcomeValue) ?>"
                                <?= (string)($filters['outcome'] ?? 'all') === $outcomeValue ? 'selected' : '' ?>
                            >
                                <?= e(trans('attempt_outcome_' . $outcomeValue)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="attempt-field">
                    <label class="attempt-label" for="attempt-attention"><?= e(trans('attention_required')) ?></label>
                    <select id="attempt-attention" name="attention" class="attempt-control">
                        <?php foreach (['all', 'expired', 'security', 'focus'] as $attentionValue): ?>
                            <option
                                value="<?= e($attentionValue) ?>"
                                <?= (string)($filters['attention'] ?? 'all') === $attentionValue ? 'selected' : '' ?>
                            >
                                <?= e(trans('attempt_attention_' . $attentionValue)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="attempt-field">
                    <label class="attempt-label" for="attempt-started-from"><?= e(trans('started_from')) ?></label>
                    <input
                        type="date"
                        id="attempt-started-from"
                        name="started_from"
                        class="attempt-control"
                        value="<?= e((string)($filters['started_from_input'] ?? '')) ?>"
                    >
                </div>

                <div class="attempt-field">
                    <label class="attempt-label" for="attempt-started-until"><?= e(trans('started_until')) ?></label>
                    <input
                        type="date"
                        id="attempt-started-until"
                        name="started_until"
                        class="attempt-control"
                        value="<?= e((string)($filters['started_until_input'] ?? '')) ?>"
                    >
                </div>
            </div>

            <div class="attempt-filter-actions">
                <button type="submit" class="attempt-filter-btn">
                    <?= e(trans('filter')) ?>
                </button>

                <a href="/admin/attempts" class="attempt-reset-btn">
                    <?= e(trans('reset_filters')) ?>
                </a>
            </div>
        </form>
    </section>

    <section class="attempt-list-card">
        <div class="attempt-section-heading">
            <h2 class="attempt-section-title">
                <?= e($isArabic ? 'سجل المحاولات' : 'Attempt Records') ?>
            </h2>

            <span class="attempt-count">
                <?= e(trans('attempts_found', ['count' => (string)$totalAttempts])) ?>
            </span>
        </div>

        <?php if (empty($items)): ?>
            <div class="attempt-empty">
                <div class="attempt-empty-icon" aria-hidden="true">⌕</div>
                <strong><?= e(trans('no_attempts_found')) ?></strong>
            </div>
        <?php else: ?>
            <div class="attempt-table-wrap">
                <table class="attempt-table">
                    <thead>
                        <tr>
                            <th><?= e(trans('employee')) ?></th>
                            <th><?= e(trans('assessment')) ?></th>
                            <th><?= e(trans('attempt_number')) ?></th>
                            <th><?= e(trans('status')) ?></th>
                            <th><?= e(trans('result')) ?></th>
                            <th><?= e(trans('progress')) ?></th>
                            <th><?= e(trans('security')) ?></th>
                            <th><?= e(trans('started_at')) ?></th>
                            <th><?= e(trans('actions')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $status = (string)($item['status'] ?? 'IN_PROGRESS');
                            $statusClass = match ($status) {
                                'IN_PROGRESS' => 'progress',
                                'COMPLETED' => 'completed',
                                'TIMED_OUT' => 'timeout',
                                'CANCELLED' => 'cancelled',
                                default => 'muted',
                            };

                            $testName = $currentLang === 'en'
                                ? (string)($item['test_name_en'] ?? '')
                                : (string)($item['test_name_ar'] ?? '');

                            $terminal = in_array($status, ['COMPLETED', 'TIMED_OUT'], true);
                            $questionCount = max(0, (int)($item['question_count'] ?? 0));
                            $answeredCount = max(0, (int)($item['answered_count'] ?? 0));
                            $progressPercent = $questionCount > 0
                                ? min(100, max(0, ($answeredCount / $questionCount) * 100))
                                : 0;
                            ?>
                            <tr>
                                <td class="attempt-employee-cell">
                                    <div class="attempt-employee">
                                        <strong><?= e((string)$item['employee_name']) ?></strong>
                                        <span class="attempt-employee-number">
                                            <bdi dir="ltr"><?= e((string)$item['employee_number']) ?></bdi>
                                        </span>
                                    </div>
                                </td>

                                <td class="attempt-test-cell">
                                    <span class="attempt-test-code">
                                        <bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi>
                                    </span>
                                    <div><?= e($testName) ?></div>
                                </td>

                                <td>
                                    <span class="attempt-number">
                                        <bdi dir="ltr">
                                            <?= (int)$item['attempt_number'] ?> / <?= (int)$item['max_attempts'] ?>
                                        </bdi>
                                    </span>
                                </td>

                                <td>
                                    <span class="attempt-badge <?= e($statusClass) ?>">
                                        <?= e(trans('attempt_status_' . strtolower($status))) ?>
                                    </span>

                                    <?php if ((int)($item['is_expired_in_progress'] ?? 0) === 1): ?>
                                        <br>
                                        <span class="attempt-badge alert attempt-extra-badge">
                                            <?= e(trans('expired_waiting_finalization')) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="attempt-result-cell">
                                    <?php if ($terminal): ?>
                                        <span class="attempt-badge <?= (int)$item['passed'] === 1 ? 'pass' : 'fail' ?>">
                                            <?= e(trans((int)$item['passed'] === 1 ? 'passed' : 'failed')) ?>
                                        </span>
                                        <span class="attempt-percentage">
                                            <bdi dir="ltr"><?= e(number_format((float)$item['percentage'], 2)) ?>%</bdi>
                                        </span>
                                    <?php else: ?>
                                        <span class="attempt-badge muted">
                                            <?= e(trans('attempt_outcome_ungraded')) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="attempt-progress-cell">
                                    <span class="attempt-progress-count">
                                        <bdi dir="ltr"><?= $answeredCount ?> / <?= $questionCount ?></bdi>
                                    </span>
                                    <div
                                        class="attempt-progress-track"
                                        role="progressbar"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow="<?= (int)round($progressPercent) ?>"
                                    >
                                        <div
                                            class="attempt-progress-fill"
                                            style="--progress-value:<?= e(number_format($progressPercent, 2, '.', '')) ?>%;"
                                        ></div>
                                    </div>
                                </td>

                                <td class="attempt-security-cell">
                                    <span class="attempt-security-primary">
                                        <bdi dir="ltr"><?= (int)$item['security_event_count'] ?></bdi>
                                        <?= e(trans('events')) ?>
                                    </span>
                                    <span class="attempt-security-secondary">
                                        <?= e(trans('focus_losses')) ?>:
                                        <bdi dir="ltr"><?= (int)$item['focus_loss_count'] ?></bdi>
                                    </span>
                                </td>

                                <td class="attempt-started-cell">
                                    <bdi dir="ltr">
                                        <?= e(TimezoneHelper::format((string)$item['started_at'], null, 'Y-m-d H:i')) ?>
                                    </bdi>
                                </td>

                                <td>
                                    <a
                                        href="/admin/attempts/view?id=<?= (int)$item['id'] ?>"
                                        class="attempt-details-btn"
                                    >
                                        <?= e(trans('details')) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="attempt-pagination" aria-label="<?= e(trans('pagination')) ?>">
                <?php
                $baseQuery = $_GET;
                unset($baseQuery['page']);
                ?>

                <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                    <?php
                    $pageQuery = $baseQuery;
                    $pageQuery['page'] = $pageNumber;
                    ?>
                    <a
                        class="attempt-page-btn<?= $pageNumber === $currentPage ? ' active' : '' ?>"
                        href="/admin/attempts?<?= e(http_build_query($pageQuery)) ?>"
                        <?= $pageNumber === $currentPage ? 'aria-current="page"' : '' ?>
                    >
                        <?= $pageNumber ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
