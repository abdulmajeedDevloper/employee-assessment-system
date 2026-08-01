<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-assignments-index';


use App\Services\AuthorizationService;
use App\Support\TimezoneHelper;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$pageTitle = trans('assignments');
$isAuthenticatedLayout = true;
$canManageAssignments = AuthorizationService::can($admin ?? [], 'manage_assignments');

$filters = $filters ?? [
    'q' => '',
    'employee_id' => '',
    'test_id' => '',
    'status' => 'all',
    'availability' => 'all',
];

$employees = $employees ?? [];
$tests = $tests ?? [];
$results = $results ?? [
    'items' => [],
    'total' => 0,
    'page' => 1,
    'total_pages' => 1,
];

$items = $results['items'] ?? [];
$totalAssignments = (int)($results['total'] ?? count($items));
$currentPage = max(1, (int)($results['page'] ?? 1));
$totalPages = max(1, (int)($results['total_pages'] ?? 1));

$activeFiltersCount = 0;
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
if (($filters['availability'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}

$formatDateOrDash = static function (mixed $value, string $format = 'Y-m-d H:i'): string {
    if ($value === null || trim((string)$value) === '') {
        return '—';
    }

    return TimezoneHelper::format((string)$value, null, $format);
};

ob_start();
?>

<div class="assignment-page">
    <section class="assignment-page-header">
        <div class="assignment-page-heading">
            <h1 class="assignment-page-title"><?= e(trans('assignments')) ?></h1>
            <p class="assignment-page-subtitle">
                <?= e($isArabic
                    ? 'إنشاء ومتابعة إسناد الاختبارات للموظفين مع التحكم في فترات الإتاحة وحدود المحاولات والنتائج.'
                    : 'Create and monitor employee assessment assignments with availability windows, attempt limits, and results.') ?>
            </p>
        </div>

        <?php if ($canManageAssignments): ?>
            <a href="/admin/assignments/create" class="assignment-create-btn">
                <span aria-hidden="true">+</span>
                <span><?= e(trans('create_assignment')) ?></span>
            </a>
        <?php endif; ?>
    </section>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="assignment-alert success">
            <?= e($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="assignment-alert error">
            <?= e($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="assignment-alert info">
            <?= e($_SESSION['flash_info']) ?>
        </div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="assignment-alert error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <section class="assignment-filter-card">
        <div class="assignment-section-heading">
            <h2 class="assignment-section-title">
                <?= e($isArabic ? 'البحث والتصفية' : 'Search and Filters') ?>
            </h2>

            <?php if ($activeFiltersCount > 0): ?>
                <span class="assignment-filter-count">
                    <?= e($isArabic
                        ? 'عوامل التصفية النشطة: ' . $activeFiltersCount
                        : 'Active filters: ' . $activeFiltersCount) ?>
                </span>
            <?php endif; ?>
        </div>

        <form action="/admin/assignments" method="GET">
            <?php if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)): ?>
                <input type="hidden" name="lang" value="<?= e($_GET['lang']) ?>">
            <?php endif; ?>

            <div class="assignment-filter-grid">
                <div class="assignment-field assignment-search">
                    <label class="assignment-label" for="assignment-search"><?= e(trans('search')) ?></label>
                    <input
                        id="assignment-search"
                        name="q"
                        type="search"
                        class="assignment-control"
                        maxlength="150"
                        value="<?= e((string)($filters['q'] ?? '')) ?>"
                        placeholder="<?= e(trans('search_assignments')) ?>"
                        autocomplete="off"
                    >
                </div>

                <div class="assignment-field">
                    <label class="assignment-label" for="assignment-employee"><?= e(trans('employee')) ?></label>
                    <select id="assignment-employee" name="employee_id" class="assignment-control">
                        <option value=""><?= e(trans('all_employees')) ?></option>
                        <?php foreach ($employees as $employee): ?>
                            <option
                                value="<?= (int)$employee['id'] ?>"
                                <?= (int)($filters['employee_id'] ?? 0) === (int)$employee['id'] ? 'selected' : '' ?>
                            >
                                <?= e((string)$employee['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="assignment-field">
                    <label class="assignment-label" for="assignment-test"><?= e(trans('assessment')) ?></label>
                    <select id="assignment-test" name="test_id" class="assignment-control">
                        <option value=""><?= e(trans('all_assessments')) ?></option>
                        <?php foreach ($tests as $test): ?>
                            <?php $testName = $currentLang === 'en' ? $test['name_en'] : $test['name_ar']; ?>
                            <option
                                value="<?= (int)$test['id'] ?>"
                                <?= (int)($filters['test_id'] ?? 0) === (int)$test['id'] ? 'selected' : '' ?>
                            >
                                <?= e($test['code'] . ' — ' . $testName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="assignment-field">
                    <label class="assignment-label" for="assignment-status"><?= e(trans('status')) ?></label>
                    <select id="assignment-status" name="status" class="assignment-control">
                        <?php foreach (['all', 'PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED', 'COMPLETED'] as $statusValue): ?>
                            <option
                                value="<?= e($statusValue) ?>"
                                <?= (string)($filters['status'] ?? 'all') === $statusValue ? 'selected' : '' ?>
                            >
                                <?= e(trans(
                                    $statusValue === 'all'
                                        ? 'all_statuses'
                                        : 'assignment_status_' . strtolower($statusValue)
                                )) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="assignment-field">
                    <label class="assignment-label" for="assignment-availability"><?= e(trans('availability')) ?></label>
                    <select id="assignment-availability" name="availability" class="assignment-control">
                        <?php foreach (['all', 'open', 'upcoming', 'expired'] as $availabilityValue): ?>
                            <option
                                value="<?= e($availabilityValue) ?>"
                                <?= (string)($filters['availability'] ?? 'all') === $availabilityValue ? 'selected' : '' ?>
                            >
                                <?= e(trans(
                                    $availabilityValue === 'all'
                                        ? 'all_availability_states'
                                        : 'assignment_availability_' . $availabilityValue
                                )) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="assignment-filter-actions">
                <button type="submit" class="assignment-filter-btn">
                    <?= e(trans('filter')) ?>
                </button>

                <a href="/admin/assignments" class="assignment-reset-btn">
                    <?= e(trans('reset_filters')) ?>
                </a>
            </div>
        </form>
    </section>

    <section class="assignment-list-card">
        <div class="assignment-section-heading">
            <h2 class="assignment-section-title">
                <?= e($isArabic ? 'سجل الإسنادات' : 'Assignment Records') ?>
            </h2>

            <span class="assignment-count">
                <?= e(trans('assignments_found', ['count' => (string)$totalAssignments])) ?>
            </span>
        </div>

        <?php if (empty($items)): ?>
            <div class="assignment-empty">
                <div class="assignment-empty-icon" aria-hidden="true">⌕</div>
                <strong><?= e(trans('no_assignments_found')) ?></strong>
            </div>
        <?php else: ?>
            <div class="assignment-table-wrap">
                <table class="assignment-table">
                    <thead>
                        <tr>
                            <th><?= e(trans('employee')) ?></th>
                            <th><?= e(trans('assessment')) ?></th>
                            <th><?= e(trans('status')) ?></th>
                            <th><?= e(trans('availability')) ?></th>
                            <th><?= e(trans('attempts')) ?></th>
                            <th><?= e(trans('assigned_at')) ?></th>
                            <th><?= e(trans('actions')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $testName = $currentLang === 'en'
                                ? (string)($item['test_name_en'] ?? '')
                                : (string)($item['test_name_ar'] ?? '');

                            $status = (string)($item['status'] ?? 'PENDING');
                            $statusClass = match ($status) {
                                'PENDING' => 'pending',
                                'IN_PROGRESS' => 'progress',
                                'RETAKE_AUTHORIZED' => 'retake',
                                'COMPLETED' => 'completed',
                                default => 'muted',
                            };

                            $availability = strtoupper((string)($item['availability_state'] ?? 'EXPIRED'));
                            $availabilityClass = match ($availability) {
                                'OPEN' => 'open',
                                'UPCOMING' => 'upcoming',
                                'EXPIRED' => 'expired',
                                default => 'muted',
                            };
                            ?>
                            <tr>
                                <td class="assignment-employee-cell">
                                    <div class="assignment-employee">
                                        <strong><?= e((string)$item['employee_name']) ?></strong>
                                        <?php if (!empty($item['employee_number'])): ?>
                                            <span class="assignment-employee-number">
                                                <bdi dir="ltr"><?= e((string)$item['employee_number']) ?></bdi>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="assignment-test-cell">
                                    <span class="assignment-test-code">
                                        <bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi>
                                    </span>
                                    <div><?= e($testName) ?></div>
                                </td>

                                <td>
                                    <span class="assignment-badge <?= e($statusClass) ?>">
                                        <?= e(trans('assignment_status_' . strtolower($status))) ?>
                                    </span>
                                </td>

                                <td class="assignment-availability-cell">
                                    <span class="assignment-badge <?= e($availabilityClass) ?>">
                                        <?= e(trans('assignment_availability_' . strtolower($availability))) ?>
                                    </span>

                                    <div class="assignment-window">
                                        <div class="assignment-window-row">
                                            <span class="assignment-window-label"><?= e(trans('from')) ?>:</span>
                                            <span><bdi dir="ltr"><?= e($formatDateOrDash($item['available_from'] ?? null)) ?></bdi></span>
                                        </div>
                                        <div class="assignment-window-row">
                                            <span class="assignment-window-label"><?= e(trans('until')) ?>:</span>
                                            <span><bdi dir="ltr"><?= e($formatDateOrDash($item['available_until'] ?? null)) ?></bdi></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="assignment-attempt-cell">
                                    <span class="assignment-attempt-count">
                                        <bdi dir="ltr">
                                            <?= (int)$item['attempt_count'] ?> / <?= (int)$item['max_attempts'] ?>
                                        </bdi>
                                    </span>

                                    <?php if (($item['latest_attempt_percentage'] ?? null) !== null): ?>
                                        <span class="assignment-latest-result">
                                            <?= e(trans('latest_result')) ?>:
                                            <strong>
                                                <bdi dir="ltr">
                                                    <?= e(number_format((float)$item['latest_attempt_percentage'], 2)) ?>%
                                                </bdi>
                                            </strong>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="assignment-assigned-cell">
                                    <span class="assignment-assigned-date">
                                        <bdi dir="ltr"><?= e($formatDateOrDash($item['assigned_at'] ?? null)) ?></bdi>
                                    </span>
                                    <span class="assignment-assigned-by">
                                        <?= e((string)($item['assigned_by_name'] ?? '—')) ?>
                                    </span>
                                </td>

                                <td class="assignment-actions-cell">
                                    <div class="assignment-row-actions">
                                        <a
                                            href="/admin/assignments/view?id=<?= (int)$item['id'] ?>"
                                            class="assignment-action-btn details"
                                        >
                                            <?= e(trans('details')) ?>
                                        </a>

                                        <?php if (
                                            $canManageAssignments
                                            && $status === 'PENDING'
                                            && (int)$item['attempt_count'] === 0
                                        ): ?>
                                            <a
                                                href="/admin/assignments/edit?id=<?= (int)$item['id'] ?>"
                                                class="assignment-action-btn edit"
                                            >
                                                <?= e(trans('edit')) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="assignment-pagination" aria-label="<?= e(trans('pagination')) ?>">
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
                        class="assignment-page-btn<?= $pageNumber === $currentPage ? ' active' : '' ?>"
                        href="/admin/assignments?<?= e(http_build_query($pageQuery)) ?>"
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
