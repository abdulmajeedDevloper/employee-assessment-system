<?php
$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-tests-index';

$pageTitle = trans('test_directory');
$isAuthenticatedLayout = true;
ob_start();

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$items = $results['items'] ?? [];
$total = (int)($results['total'] ?? 0);
$page = (int)($results['page'] ?? 1);
$totalPages = (int)($results['total_pages'] ?? 0);
$canManageTests = $canManageTests ?? false;
$filters = $filters ?? ['q' => '', 'status' => 'all'];

$activeFiltersCount = 0;
if (trim((string)($filters['q'] ?? '')) !== '') {
    $activeFiltersCount++;
}
if (($filters['status'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}
?>

<div class="assessment-directory">
    <section class="assessment-page-header">
        <div class="assessment-page-heading">
            <h1 class="assessment-page-title"><?= e(trans('test_directory')) ?></h1>
            <p class="assessment-page-subtitle">
                <?= e($isArabic
                    ? 'إدارة الاختبارات وإعداداتها الأساسية ومراجعة المدة وعدد الأسئلة ونسبة النجاح والحالة.'
                    : 'Manage assessments and review duration, question count, passing score, and availability status.') ?>
            </p>
        </div>

        <div class="assessment-header-actions">
            <?php if ($canManageTests): ?>
                <a href="/admin/tests/create" class="assessment-primary-btn">
                    <span aria-hidden="true">+</span>
                    <span><?= e(trans('create_assessment')) ?></span>
                </a>
            <?php endif; ?>

            <a href="/admin/dashboard" class="assessment-secondary-btn">
                <?= e(trans('return_to_dashboard')) ?>
            </a>
        </div>
    </section>

    <?php if (!empty($errorMessage)): ?>
        <div class="assessment-alert">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <section class="assessment-filter-card">
        <div class="assessment-filter-heading">
            <h2 class="assessment-section-title">
                <?= e($isArabic ? 'البحث والتصفية' : 'Search and Filters') ?>
            </h2>

            <?php if ($activeFiltersCount > 0): ?>
                <span class="assessment-filter-count">
                    <?= e($isArabic
                        ? 'عوامل التصفية النشطة: ' . $activeFiltersCount
                        : 'Active filters: ' . $activeFiltersCount) ?>
                </span>
            <?php endif; ?>
        </div>

        <form action="/admin/tests" method="GET" class="assessment-filter-form">
            <?php if (isset($_GET['lang'])): ?>
                <input type="hidden" name="lang" value="<?= e($_GET['lang']) ?>">
            <?php endif; ?>

            <div class="assessment-field assessment-filter-search">
                <label class="assessment-label" for="assessment-search"><?= e(trans('search')) ?></label>
                <input
                    id="assessment-search"
                    type="search"
                    name="q"
                    value="<?= e($filters['q'] ?? '') ?>"
                    placeholder="<?= e(trans('search_tests')) ?>"
                    maxlength="100"
                    class="assessment-control"
                    autocomplete="off"
                >
            </div>

            <div class="assessment-field">
                <label class="assessment-label" for="assessment-status"><?= e(trans('status')) ?></label>
                <select id="assessment-status" name="status" class="assessment-control">
                    <option value="all" <?= (($filters['status'] ?? 'all') === 'all') ? 'selected' : '' ?>>
                        <?= e(trans('all_statuses')) ?>
                    </option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>
                        <?= e(trans('active')) ?>
                    </option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>
                        <?= e(trans('inactive')) ?>
                    </option>
                </select>
            </div>

            <div class="assessment-filter-actions">
                <button type="submit" class="assessment-filter-btn">
                    <?= e(trans('filter')) ?>
                </button>

                <?php
                $resetParams = [];
                if (isset($_GET['lang'])) {
                    $resetParams['lang'] = $_GET['lang'];
                }
                $resetUrl = '/admin/tests' . (!empty($resetParams) ? '?' . http_build_query($resetParams) : '');
                ?>

                <a href="<?= e($resetUrl) ?>" class="assessment-reset-btn">
                    <?= e(trans('reset_filters')) ?>
                </a>
            </div>
        </form>
    </section>

    <section class="assessment-table-card">
        <div class="assessment-table-heading">
            <h2 class="assessment-section-title">
                <?= e($isArabic ? 'سجل الاختبارات' : 'Assessment Records') ?>
            </h2>

            <span class="assessment-count">
                <?= e($isArabic ? 'الإجمالي: ' . $total : 'Total: ' . $total) ?>
            </span>
        </div>

        <?php if (empty($items)): ?>
            <div class="assessment-empty">
                <div class="assessment-empty-icon" aria-hidden="true">⌕</div>
                <strong><?= e(trans('no_tests_found')) ?></strong>
            </div>
        <?php else: ?>
            <div class="assessment-table-wrap">
                <table class="assessment-table">
                    <thead>
                        <tr>
                            <th><?= e(trans('test_name')) ?></th>
                            <th><?= e(trans('duration_minutes_label')) ?></th>
                            <th><?= e(trans('questions_per_attempt')) ?></th>
                            <th><?= e(trans('passing_score_label')) ?></th>
                            <th><?= e(trans('status')) ?></th>
                            <th><?= e(trans('actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $testName = $currentLang === 'en'
                                ? (string)($item['name_en'] ?? '')
                                : (string)($item['name_ar'] ?? '');

                            $queryParams = ['id' => (int)$item['id']];
                            if (isset($_GET['lang'])) {
                                $queryParams['lang'] = $_GET['lang'];
                            }
                            $viewUrl = '/admin/tests/view?' . http_build_query($queryParams);
                            $editUrl = '/admin/tests/edit?' . http_build_query($queryParams);
                            ?>
                            <tr>
                                <td class="assessment-name-cell">
                                    <div class="assessment-name-wrap">
                                        <strong><?= e($testName) ?></strong>
                                        <span class="assessment-code">
                                            <bdi dir="ltr"><?= e((string)$item['code']) ?></bdi>
                                        </span>
                                    </div>
                                </td>

                                <td class="assessment-metric">
                                    <strong><bdi dir="ltr"><?= (int)$item['duration_minutes'] ?></bdi></strong>
                                    <small><?= e(trans('minutes')) ?></small>
                                </td>

                                <td class="assessment-metric">
                                    <strong><bdi dir="ltr"><?= (int)$item['question_count'] ?></bdi></strong>
                                    <small><?= e($isArabic ? 'سؤالًا في المحاولة' : 'Questions per attempt') ?></small>
                                </td>

                                <td class="assessment-metric">
                                    <strong><bdi dir="ltr"><?= number_format((float)$item['passing_percentage'], 2) ?>%</bdi></strong>
                                    <small><?= e($isArabic ? 'الحد الأدنى للنجاح' : 'Minimum passing score') ?></small>
                                </td>

                                <td>
                                    <?php if ((int)$item['is_active'] === 1): ?>
                                        <span class="assessment-status active"><?= e(trans('active')) ?></span>
                                    <?php else: ?>
                                        <span class="assessment-status inactive"><?= e(trans('inactive')) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="assessment-row-actions">
                                        <a href="<?= e($viewUrl) ?>" class="assessment-details-btn">
                                            <?= e(trans('details')) ?>
                                        </a>

                                        <?php if ($canManageTests): ?>
                                            <a href="<?= e($editUrl) ?>" class="assessment-edit-btn">
                                                <?= e(trans('edit_assessment')) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <?php
                $baseQueryParams = [];

                if (!empty($filters['q'])) {
                    $baseQueryParams['q'] = $filters['q'];
                }

                if (!empty($filters['status']) && $filters['status'] !== 'all') {
                    $baseQueryParams['status'] = $filters['status'];
                }

                if (isset($_GET['lang'])) {
                    $baseQueryParams['lang'] = $_GET['lang'];
                }

                $hasPrev = $page > 1;
                $hasNext = $page < $totalPages;

                $prevPageUrl = $hasPrev
                    ? '/admin/tests?' . http_build_query(array_merge($baseQueryParams, ['page' => $page - 1]))
                    : '#';

                $nextPageUrl = $hasNext
                    ? '/admin/tests?' . http_build_query(array_merge($baseQueryParams, ['page' => $page + 1]))
                    : '#';
                ?>

                <nav class="assessment-pagination" aria-label="<?= e($isArabic ? 'التنقل بين صفحات الاختبارات' : 'Assessment pages') ?>">
                    <div>
                        <?= e(sprintf(trans('page_of'), $page, $totalPages)) ?>
                        ·
                        <?= e($isArabic ? 'إجمالي الاختبارات' : 'Total assessments') ?>:
                        <?= $total ?>
                    </div>

                    <div class="assessment-pagination-actions">
                        <a
                            href="<?= e($prevPageUrl) ?>"
                            class="assessment-page-btn<?= !$hasPrev ? ' disabled' : '' ?>"
                            <?= !$hasPrev ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                        >
                            <?= e(trans('previous')) ?>
                        </a>

                        <a
                            href="<?= e($nextPageUrl) ?>"
                            class="assessment-page-btn<?= !$hasNext ? ' disabled' : '' ?>"
                            <?= !$hasNext ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                        >
                            <?= e(trans('next')) ?>
                        </a>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
