<?php
$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-employees-index';

$pageTitle = trans('employee_directory');
$isAuthenticatedLayout = true;
ob_start();

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$filters = $filters ?? [];
$departments = $departments ?? [];
$results = $results ?? ['items' => [], 'page' => 1, 'total_pages' => 1, 'total' => 0];

$items = $results['items'] ?? [];
$totalEmployees = (int)($results['total'] ?? count($items));
$activeFiltersCount = 0;

if (trim((string)($filters['q'] ?? '')) !== '') {
    $activeFiltersCount++;
}
if ((int)($filters['department_id'] ?? 0) > 0) {
    $activeFiltersCount++;
}
if (($filters['status'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}
?>

<div class="employee-directory">
    <section class="employee-page-header">
        <div class="employee-page-heading">
            <h1 class="employee-page-title"><?= e(trans('employee_directory')) ?></h1>
            <p class="employee-page-subtitle">
                <?= e($isArabic
                    ? 'إدارة بيانات الموظفين وحالاتهم التنظيمية والوصول السريع إلى التفاصيل والتحديثات.'
                    : 'Manage employee records, organizational status, and quick access to details and updates.') ?>
            </p>
        </div>

        <a href="/admin/employees/create" class="employee-add-btn">
            <span class="employee-add-icon" aria-hidden="true">+</span>
            <span><?= e(trans('add_employee')) ?></span>
        </a>
    </section>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="employee-alert success">
            <?= e($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="employee-alert error">
            <?= e($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="employee-alert error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <section class="employee-filter-card">
        <div class="employee-filter-heading">
            <h2 class="employee-section-title">
                <?= e($isArabic ? 'البحث والتصفية' : 'Search and Filters') ?>
            </h2>

            <?php if ($activeFiltersCount > 0): ?>
                <span class="employee-filter-count">
                    <?= e($isArabic
                        ? 'عوامل التصفية النشطة: ' . $activeFiltersCount
                        : 'Active filters: ' . $activeFiltersCount) ?>
                </span>
            <?php endif; ?>
        </div>

        <form action="/admin/employees" method="GET" class="employee-filter-form">
            <div class="employee-field employee-filter-search">
                <label class="employee-label" for="employee-search"><?= e(trans('search')) ?></label>
                <input
                    id="employee-search"
                    type="search"
                    name="q"
                    value="<?= e($filters['q'] ?? '') ?>"
                    placeholder="<?= e(trans('search_employees')) ?>"
                    maxlength="100"
                    class="employee-control"
                    autocomplete="off"
                >
            </div>

            <div class="employee-field">
                <label class="employee-label" for="employee-department"><?= e(trans('department')) ?></label>
                <select id="employee-department" name="department_id" class="employee-control">
                    <option value=""><?= e(trans('all_departments')) ?></option>
                    <?php foreach ($departments as $dept): ?>
                        <option
                            value="<?= (int)$dept['id'] ?>"
                            <?= ((int)($filters['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : '' ?>
                        >
                            <?= e($currentLang === 'en' ? $dept['name_en'] : $dept['name_ar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="employee-field">
                <label class="employee-label" for="employee-status"><?= e(trans('status')) ?></label>
                <select id="employee-status" name="status" class="employee-control">
                    <option value="all" <?= (($filters['status'] ?? 'all') === 'all') ? 'selected' : '' ?>>
                        <?= e(trans('all_statuses')) ?>
                    </option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>
                        <?= e(trans('active')) ?>
                    </option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>
                        <?= e(trans('inactive')) ?>
                    </option>
                    <option value="archived" <?= (($filters['status'] ?? '') === 'archived') ? 'selected' : '' ?>>
                        <?= e(trans('archived')) ?>
                    </option>
                </select>
            </div>

            <div class="employee-filter-actions">
                <button type="submit" class="employee-filter-btn">
                    <?= e(trans('filter')) ?>
                </button>
                <a href="/admin/employees" class="employee-reset-btn">
                    <?= e(trans('reset_filters')) ?>
                </a>
            </div>
        </form>
    </section>

    <section class="employee-table-card">
        <div class="employee-table-heading">
            <h2 class="employee-section-title">
                <?= e($isArabic ? 'سجل الموظفين' : 'Employee Records') ?>
            </h2>
            <span class="employee-count">
                <?= e($isArabic ? 'الإجمالي: ' . $totalEmployees : 'Total: ' . $totalEmployees) ?>
            </span>
        </div>

        <?php if (empty($items)): ?>
            <div class="employee-empty">
                <div class="employee-empty-icon" aria-hidden="true">⌕</div>
                <strong><?= e(trans('no_employees_found')) ?></strong>
            </div>
        <?php else: ?>
            <div class="employee-table-wrap">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th><?= e(trans('employee_name')) ?></th>
                            <th><?= e(trans('department')) ?></th>
                            <th><?= e(trans('section')) ?></th>
                            <th><?= e(trans('job_title')) ?></th>
                            <th><?= e(trans('email')) ?></th>
                            <th><?= e(trans('status')) ?></th>
                            <th><?= e(trans('actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $emp): ?>
                            <?php
                            $fullName = (string)($emp['full_name'] ?? '');
                            $empNumber = !empty($emp['employee_number']) ? (string)$emp['employee_number'] : '—';
                            $deptName = $currentLang === 'en'
                                ? (string)($emp['department_name_en'] ?? '')
                                : (string)($emp['department_name_ar'] ?? '');
                            $secName = $currentLang === 'en'
                                ? (string)($emp['section_name_en'] ?? '')
                                : (string)($emp['section_name_ar'] ?? '');
                            $jobTitle = $currentLang === 'en'
                                ? (string)($emp['job_title_name_en'] ?? '')
                                : (string)($emp['job_title_name_ar'] ?? '');
                            $email = (string)($emp['email'] ?? '');
                            ?>
                            <tr>
                                <td class="employee-name-cell">
                                    <div class="employee-name">
                                        <strong><?= e($fullName) ?></strong>
                                        <span class="employee-number"><bdi dir="ltr"><?= e($empNumber) ?></bdi></span>
                                    </div>
                                </td>

                                <td class="employee-organization-cell" title="<?= e($deptName) ?>">
                                    <?= e($deptName !== '' ? $deptName : '—') ?>
                                </td>

                                <td class="employee-organization-cell" title="<?= e($secName) ?>">
                                    <?= e($secName !== '' ? $secName : '—') ?>
                                </td>

                                <td class="employee-job-cell" title="<?= e($jobTitle) ?>">
                                    <?= e($jobTitle !== '' ? $jobTitle : '—') ?>
                                </td>

                                <td class="employee-email-cell">
                                    <?php if ($email !== ''): ?>
                                        <a class="employee-email-link" href="mailto:<?= e($email) ?>">
                                            <bdi dir="ltr"><?= e($email) ?></bdi>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (($emp['archived_at'] ?? null) !== null): ?>
                                        <span class="employee-status archived"><?= e(trans('archived')) ?></span>
                                    <?php elseif ((int)($emp['is_active'] ?? 0) === 1): ?>
                                        <span class="employee-status active"><?= e(trans('active')) ?></span>
                                    <?php else: ?>
                                        <span class="employee-status inactive"><?= e(trans('inactive')) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="employee-actions-cell">
                                    <div class="employee-actions">
                                        <a
                                            href="/admin/employees/view?id=<?= (int)$emp['id'] ?>"
                                            class="employee-action-btn details"
                                        >
                                            <?= e(trans('details')) ?>
                                        </a>

                                        <?php if (($emp['archived_at'] ?? null) === null): ?>
                                            <a
                                                href="/admin/employees/edit?id=<?= (int)$emp['id'] ?>"
                                                class="employee-action-btn edit"
                                            >
                                                <?= e(trans('edit')) ?>
                                            </a>

                                            <form
                                                action="/admin/employees/archive"
                                                method="POST"
                                                data-confirm="<?= e(trans('confirm_archive_employee')) ?>"
                                                onsubmit="return confirm(this.dataset.confirm);"
                                            >
                                                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                                <button type="submit" class="employee-action-btn archive">
                                                    <?= e(trans('archive')) ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form
                                                action="/admin/employees/restore"
                                                method="POST"
                                                data-confirm="<?= e(trans('confirm_restore_employee')) ?>"
                                                onsubmit="return confirm(this.dataset.confirm);"
                                            >
                                                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                                <button type="submit" class="employee-action-btn restore">
                                                    <?= e(trans('restore')) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (($results['total_pages'] ?? 0) > 1): ?>
                <?php
                $baseParams = [];

                if (!empty($filters['q'])) {
                    $baseParams['q'] = $filters['q'];
                }

                if (!empty($filters['department_id'])) {
                    $baseParams['department_id'] = $filters['department_id'];
                }

                if (($filters['status'] ?? 'all') !== 'all') {
                    $baseParams['status'] = $filters['status'];
                }

                $currentPage = (int)($results['page'] ?? 1);
                $totalPages = (int)($results['total_pages'] ?? 1);

                $prevUrl = $currentPage > 1
                    ? '/admin/employees?' . http_build_query(array_merge($baseParams, ['page' => $currentPage - 1]))
                    : null;

                $nextUrl = $currentPage < $totalPages
                    ? '/admin/employees?' . http_build_query(array_merge($baseParams, ['page' => $currentPage + 1]))
                    : null;
                ?>

                <nav class="employee-pagination" aria-label="<?= e($isArabic ? 'التنقل بين صفحات الموظفين' : 'Employee pages') ?>">
                    <div>
                        <?= e(sprintf(trans('page_of'), $currentPage, $totalPages)) ?>
                        ·
                        <?= e(trans('total_employees')) ?>:
                        <?= $totalEmployees ?>
                    </div>

                    <div class="employee-pagination-actions">
                        <?php if ($prevUrl): ?>
                            <a href="<?= e($prevUrl) ?>" class="employee-page-btn">
                                <?= e(trans('previous')) ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($nextUrl): ?>
                            <a href="<?= e($nextUrl) ?>" class="employee-page-btn">
                                <?= e(trans('next')) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
