<?php
$pageTitle = trans('test_directory');
$isAuthenticatedLayout = true;
ob_start();

$currentLang = $_SESSION['lang'] ?? 'ar';
$items = $results['items'] ?? [];
$total = $results['total'] ?? 0;
$page = $results['page'] ?? 1;
$totalPages = $results['total_pages'] ?? 0;
$canManageTests = $canManageTests ?? false;
?>

<style>
    .test-directory-container {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .test-full-width-card {
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .test-filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
        width: 100%;
        box-sizing: border-box;
    }

    .test-filter-group-search {
        flex: 2 1 280px;
        min-width: 0;
    }

    .test-filter-group-status {
        flex: 1 1 180px;
        min-width: 0;
    }

    .test-filter-actions {
        display: flex;
        gap: 0.5rem;
        align-items: flex-end;
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .test-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 0.5rem;
    }

    .test-table-wrapper {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .test-data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: <?= ($currentLang === 'ar') ? 'right' : 'left' ?>;
        font-size: 0.9rem;
    }

    .test-data-table th {
        background: rgba(15, 23, 42, 0.6);
        color: #94a3b8;
        padding: 0.85rem 1rem;
        font-weight: 600;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        white-space: nowrap;
    }

    .test-data-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        color: #f8fafc;
        vertical-align: middle;
    }

    .test-data-table tr:hover td {
        background: rgba(255, 255, 255, 0.03);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.65rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-active {
        background: rgba(34, 197, 94, 0.15);
        color: #4ade80;
        border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .status-inactive {
        background: rgba(148, 163, 184, 0.15);
        color: #cbd5e1;
        border: 1px solid rgba(148, 163, 184, 0.3);
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        font-size: 0.825rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
        line-height: 1.2;
    }

    .btn-view {
        background: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .btn-view:hover {
        background: rgba(59, 130, 246, 0.3);
        color: #ffffff;
    }

    .technical-value {
        unicode-bidi: isolate;
        font-family: monospace, sans-serif;
    }

    .empty-state-card {
        padding: 3rem 1.5rem;
        text-align: center;
        color: #94a3b8;
    }

    .pagination-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1rem;
    }

    .pagination-controls {
        display: flex;
        gap: 0.5rem;
    }

    .page-link {
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #cbd5e1;
        text-decoration: none;
        font-size: 0.85rem;
    }

    .page-link:hover:not(.disabled) {
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
    }

    .page-link.disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
</style>

<div class="test-directory-container">
    <!-- Header Bar -->
    <div class="test-header-bar">
        <div>
            <h1 class="admin-welcome-title" style="margin: 0; font-size: 1.5rem;"><?= e(trans('test_directory')) ?></h1>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <?php if ($canManageTests): ?>
                <a href="/admin/tests/create" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none;">
                    <?= e(trans('create_assessment')) ?>
                </a>
            <?php endif; ?>
            <a href="/admin/dashboard" class="nav-link"><?= e(trans('return_to_dashboard')) ?></a>
        </div>
    </div>

    <!-- Error Message Alert -->
    <?php if (!empty($errorMessage)): ?>
        <div class="alert-box alert-danger">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="admin-welcome-card test-full-width-card" style="padding: 1.25rem 1.5rem;">
        <form action="/admin/tests" method="GET" class="test-filter-form">
            <?php if (isset($_GET['lang'])): ?>
                <input type="hidden" name="lang" value="<?= e($_GET['lang']) ?>">
            <?php endif; ?>

            <!-- Search Filter -->
            <div class="test-filter-group-search">
                <label for="q" style="display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.35rem; font-weight: 500;">
                    <?= e(trans('search')) ?>
                </label>
                <input type="text" id="q" name="q" value="<?= e($filters['q']) ?>"
                       placeholder="<?= e(trans('search_tests')) ?>"
                       maxlength="100" class="input-field" style="width: 100%;">
            </div>

            <!-- Status Filter -->
            <div class="test-filter-group-status">
                <label for="status" style="display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.35rem; font-weight: 500;">
                    <?= e(trans('status')) ?>
                </label>
                <select id="status" name="status" class="input-field" style="width: 100%;">
                    <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>><?= e(trans('all_statuses')) ?></option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>><?= e(trans('active')) ?></option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>><?= e(trans('inactive')) ?></option>
                </select>
            </div>

            <!-- Filter Actions -->
            <div class="test-filter-actions">
                <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.25rem; font-size: 0.875rem;">
                    <?= e(trans('filter')) ?>
                </button>

                <?php
                $resetParams = [];
                if (isset($_GET['lang'])) {
                    $resetParams['lang'] = $_GET['lang'];
                }
                $resetUrl = '/admin/tests' . (!empty($resetParams) ? '?' . http_build_query($resetParams) : '');
                ?>
                <a href="<?= e($resetUrl) ?>" class="page-link" style="padding: 0.55rem 1rem;">
                    <?= e(trans('reset_filters')) ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="admin-welcome-card test-full-width-card" style="padding: 1.25rem 1.5rem;">
        <?php if (empty($items)): ?>
            <div class="empty-state-card">
                <p style="font-size: 1.1rem; margin-bottom: 0.5rem; color: #f8fafc;"><?= e(trans('no_tests_found')) ?></p>
            </div>
        <?php else: ?>
            <div class="test-table-wrapper">
                <table class="test-data-table">
                    <thead>
                        <tr>
                            <th><?= e(trans('test_name')) ?></th>
                            <th><?= e(trans('test_code')) ?></th>
                            <th><?= e(trans('duration_minutes_label')) ?></th>
                            <th><?= e(trans('questions_per_attempt')) ?></th>
                            <th><?= e(trans('passing_score_label')) ?></th>
                            <th><?= e(trans('status')) ?></th>
                            <th style="text-align: center;"><?= e(trans('actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $testName = ($currentLang === 'en') ? $item['name_en'] : $item['name_ar'];
                            $queryParams = ['id' => $item['id']];
                            if (isset($_GET['lang'])) {
                                $queryParams['lang'] = $_GET['lang'];
                            }
                            $viewUrl = '/admin/tests/view?' . http_build_query($queryParams);
                            ?>
                            <tr>
                                <td>
                                    <strong style="color: #f8fafc;"><?= e($testName) ?></strong>
                                </td>
                                <td>
                                    <bdi dir="ltr" class="technical-value"><?= e($item['code']) ?></bdi>
                                </td>
                                <td>
                                    <bdi dir="ltr" class="technical-value"><?= (int)$item['duration_minutes'] ?></bdi> <?= e(trans('minutes')) ?>
                                </td>
                                <td>
                                    <bdi dir="ltr" class="technical-value"><?= (int)$item['question_count'] ?></bdi>
                                </td>
                                <td>
                                    <bdi dir="ltr" class="technical-value"><?= number_format((float)$item['passing_percentage'], 2) ?>%</bdi>
                                </td>
                                <td>
                                    <?php if ((int)$item['is_active'] === 1): ?>
                                        <span class="status-badge status-active"><?= e(trans('active')) ?></span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive"><?= e(trans('inactive')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?= e($viewUrl) ?>" class="btn-action btn-view">
                                        <?= e(trans('details')) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
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

                $prevPageUrl = '#';
                $hasPrev = ($page > 1);
                if ($hasPrev) {
                    $prevParams = array_merge($baseQueryParams, ['page' => $page - 1]);
                    $prevPageUrl = '/admin/tests?' . http_build_query($prevParams);
                }

                $nextPageUrl = '#';
                $hasNext = ($page < $totalPages);
                if ($hasNext) {
                    $nextParams = array_merge($baseQueryParams, ['page' => $page + 1]);
                    $nextPageUrl = '/admin/tests?' . http_build_query($nextParams);
                }
                ?>
                <div class="pagination-bar">
                    <div style="font-size: 0.875rem; color: #94a3b8;">
                        <?= sprintf(e(trans('page_of')), $page, $totalPages) ?> (<?= e(trans('total_employees')) ?>: <bdi dir="ltr" class="technical-value"><?= $total ?></bdi>)
                    </div>
                    <div class="pagination-controls">
                        <a href="<?= e($prevPageUrl) ?>" class="page-link <?= !$hasPrev ? 'disabled' : '' ?>">
                            <?= e(trans('previous')) ?>
                        </a>
                        <a href="<?= e($nextPageUrl) ?>" class="page-link <?= !$hasNext ? 'disabled' : '' ?>">
                            <?= e(trans('next')) ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
?>
