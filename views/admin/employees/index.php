<?php
$pageTitle = trans('employee_directory');
$isAuthenticatedLayout = true;
ob_start();

$currentLang = $_SESSION['lang'] ?? 'ar';
?>

<style>
    /* Page-scoped wrapper & full-width card overrides */
    .emp-directory-container {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .emp-full-width-card {
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        grid-column: 1 / -1 !important;
    }

    /* Filter Form Layout */
    .emp-filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
        width: 100%;
        box-sizing: border-box;
    }

    .emp-filter-group-search {
        flex: 2 1 280px;
        min-width: 0;
    }

    .emp-filter-group-dept {
        flex: 1 1 200px;
        min-width: 0;
    }

    .emp-filter-group-status {
        flex: 1 1 150px;
        min-width: 0;
    }

    .emp-filter-actions {
        display: flex;
        gap: 0.5rem;
        align-items: flex-end;
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .emp-filter-btn {
        height: 42px;
        padding: 0 1.25rem;
        border-radius: 6px;
        border: none;
        background: #3b82f6;
        color: #fff;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        box-sizing: border-box;
        line-height: 1;
        transition: background 0.15s ease-in-out;
    }

    .emp-filter-btn:hover {
        background: #2563eb;
    }

    .emp-reset-btn {
        height: 42px;
        padding: 0 1rem;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.05);
        color: #cbd5e1;
        font-weight: 600;
        font-size: 0.875rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        box-sizing: border-box;
        line-height: 1;
        transition: all 0.15s ease-in-out;
    }

    .emp-reset-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.3);
    }

    /* Input Controls */
    .emp-input-control {
        width: 100%;
        height: 42px;
        padding: 0 0.8rem;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(15, 23, 42, 0.6);
        color: #fff;
        box-sizing: border-box;
        font-size: 0.875rem;
    }

    .emp-input-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
    }

    /* Table Component */
    .emp-table-wrapper {
        width: 100%;
        overflow-x: visible;
        border-radius: 8px;
    }

    .emp-table {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        border-collapse: collapse;
        text-align: inherit;
        font-size: 0.875rem;
        table-layout: fixed;
    }

    .emp-table th {
        padding: 0.75rem 0.5rem;
        text-align: inherit;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--text-muted, #94a3b8);
        font-weight: 600;
        font-size: 0.8rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .emp-table td {
        padding: 0.75rem 0.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Actions cell overrides to prevent button clipping */
    .emp-table th.emp-actions-cell,
    .emp-table td.emp-actions-cell {
        overflow: visible;
        text-overflow: clip;
        white-space: nowrap;
        box-sizing: border-box;
        vertical-align: middle;
    }

    .emp-action-group {
        display: inline-flex;
        align-items: center;
        justify-content: flex-start;
        width: max-content;
        max-width: 100%;
        gap: 5px;
        flex-wrap: nowrap;
        white-space: nowrap;
    }

    /* Responsive adjustments */
    @media (max-width: 1099px) {
        .emp-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .emp-table {
            min-width: 950px;
            table-layout: auto;
        }
    }

    @media (max-width: 1023px) and (min-width: 640px) {
        .emp-filter-group-search {
            flex: 1 1 100%;
        }
        .emp-filter-group-dept {
            flex: 1 1 45%;
        }
        .emp-filter-group-status {
            flex: 1 1 45%;
        }
        .emp-filter-actions {
            flex: 1 1 100%;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }
    }

    @media (max-width: 639px) {
        .emp-filter-group-search,
        .emp-filter-group-dept,
        .emp-filter-group-status,
        .emp-filter-actions {
            flex: 1 1 100%;
            width: 100%;
        }
        .emp-filter-actions {
            flex-direction: column;
            gap: 0.5rem;
        }
        .emp-filter-btn,
        .emp-reset-btn {
            width: 100%;
        }
    }
</style>

<div class="emp-directory-container">
    <div class="admin-welcome-card emp-full-width-card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h1 class="admin-welcome-title" style="margin: 0;"><?= e(trans('employee_directory')) ?></h1>
        <a href="/admin/employees/create" style="display: inline-flex; align-items: center; justify-content: center; height: 42px; padding: 0 1.25rem; border-radius: 6px; background: #2563eb; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 0.875rem; transition: background 0.15s ease-in-out;">
            + <?= e(trans('add_employee')) ?>
        </a>
    </div>

    <!-- Flash Notifications -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert-box alert-success" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 1rem 1.25rem; border-radius: 8px; font-weight: 600;">
            <?= e($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert-box alert-danger">
            <?= e($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert-box alert-danger emp-full-width-card">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Search & Filters Card -->
    <div class="card-box emp-full-width-card" style="background: var(--card-bg, rgba(30, 41, 59, 0.85)); border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1)); border-radius: 12px; padding: 1.5rem; backdrop-filter: blur(12px);">
        <form action="/admin/employees" method="GET" class="emp-filter-form">
            <div class="emp-filter-group-search">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-muted, #94a3b8); margin-bottom: 0.5rem; white-space: nowrap;">
                    <?= e(trans('search')) ?>
                </label>
                <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e(trans('search_employees')) ?>" maxlength="100" class="emp-input-control">
            </div>

            <div class="emp-filter-group-dept">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-muted, #94a3b8); margin-bottom: 0.5rem; white-space: nowrap;">
                    <?= e(trans('department')) ?>
                </label>
                <select name="department_id" class="emp-input-control">
                    <option value=""><?= e(trans('all_departments')) ?></option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int)$dept['id'] ?>" <?= ((int)($filters['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : '' ?>>
                            <?= e($currentLang === 'en' ? $dept['name_en'] : $dept['name_ar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="emp-filter-group-status">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-muted, #94a3b8); margin-bottom: 0.5rem; white-space: nowrap;">
                    <?= e(trans('status')) ?>
                </label>
                <select name="status" class="emp-input-control">
                    <option value="all" <?= (($filters['status'] ?? 'all') === 'all') ? 'selected' : '' ?>><?= e(trans('all_statuses')) ?></option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>><?= e(trans('active')) ?></option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>><?= e(trans('inactive')) ?></option>
                    <option value="archived" <?= (($filters['status'] ?? '') === 'archived') ? 'selected' : '' ?>><?= e(trans('archived')) ?></option>
                </select>
            </div>

            <div class="emp-filter-actions">
                <button type="submit" class="emp-filter-btn">
                    <?= e(trans('filter')) ?>
                </button>
                <a href="/admin/employees" class="emp-reset-btn">
                    <?= e(trans('reset_filters')) ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Employee Table Card -->
    <div class="card-box emp-full-width-card" style="background: var(--card-bg, rgba(30, 41, 59, 0.85)); border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1)); border-radius: 12px; padding: 1.5rem; backdrop-filter: blur(12px);">
        <?php if (empty($results['items'])): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted, #94a3b8);">
                <?= e(trans('no_employees_found')) ?>
            </div>
        <?php else: ?>
            <div class="emp-table-wrapper">
                <table class="emp-table">
                    <colgroup>
                        <col style="width: 11%;">
                        <col style="width: 9%;">
                        <col style="width: 10%;">
                        <col style="width: 13%;">
                        <col style="width: 13%;">
                        <col style="width: 16%;">
                        <col style="width: 10%;">
                        <col style="width: 18%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th><?= e(trans('employee_name')) ?></th>
                            <th><?= e(trans('employee_number')) ?></th>
                            <th><?= e(trans('department')) ?></th>
                            <th><?= e(trans('section')) ?></th>
                            <th><?= e(trans('job_title')) ?></th>
                            <th><?= e(trans('email')) ?></th>
                            <th><?= e(trans('status')) ?></th>
                            <th class="emp-actions-cell"><?= e(trans('actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['items'] as $emp): ?>
                            <?php
                            $fullName = $emp['full_name'] ?? '';
                            $empNumber = !empty($emp['employee_number']) ? $emp['employee_number'] : '—';
                            $deptName = $currentLang === 'en' ? ($emp['department_name_en'] ?? '') : ($emp['department_name_ar'] ?? '');
                            $secName = $currentLang === 'en' ? ($emp['section_name_en'] ?? '') : ($emp['section_name_ar'] ?? '');
                            $jobTitle = $currentLang === 'en' ? ($emp['job_title_name_en'] ?? '') : ($emp['job_title_name_ar'] ?? '');
                            $email = $emp['email'] ?? '';
                            ?>
                            <tr style="color: #f8fafc;">
                                <td style="font-weight: 600;" title="<?= e($fullName) ?>"><?= e($fullName) ?></td>
                                <td title="<?= e($empNumber) ?>"><?= e($empNumber) ?></td>
                                <td title="<?= e($deptName) ?>"><?= e($deptName) ?></td>
                                <td title="<?= e($secName) ?>"><?= e($secName) ?></td>
                                <td title="<?= e($jobTitle) ?>"><?= e($jobTitle) ?></td>
                                <td title="<?= e($email) ?>"><?= e($email) ?></td>
                                <td>
                                    <?php if ($emp['archived_at'] !== null): ?>
                                        <span style="display: inline-block; font-size: 0.75rem; color: #ef4444; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.2rem 0.5rem; border-radius: 12px; font-weight: 600; white-space: nowrap;">
                                            <?= e(trans('archived')) ?>
                                        </span>
                                    <?php elseif ((int)$emp['is_active'] === 1): ?>
                                        <span style="display: inline-block; font-size: 0.75rem; color: #10b981; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.2rem 0.5rem; border-radius: 12px; font-weight: 600; white-space: nowrap;">
                                            <?= e(trans('active')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; font-size: 0.75rem; color: #f59e0b; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.2rem 0.5rem; border-radius: 12px; font-weight: 600; white-space: nowrap;">
                                            <?= e(trans('inactive')) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="emp-actions-cell">
                                    <div class="emp-action-group">
                                        <a href="/admin/employees/view?id=<?= (int)$emp['id'] ?>" style="display: inline-flex; align-items: center; justify-content: center; height: 28px; padding: 0 8px; border-radius: 6px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; text-decoration: none; font-weight: 600; font-size: 0.78rem; white-space: nowrap; transition: background 0.15s ease-in-out;">
                                            <?= e(trans('details')) ?>
                                        </a>

                                        <?php if ($emp['archived_at'] === null): ?>
                                            <a href="/admin/employees/edit?id=<?= (int)$emp['id'] ?>" style="display: inline-flex; align-items: center; justify-content: center; height: 28px; padding: 0 8px; border-radius: 6px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); color: #38bdf8; text-decoration: none; font-weight: 600; font-size: 0.78rem; white-space: nowrap; transition: background 0.15s ease-in-out;">
                                                <?= e(trans('edit')) ?>
                                            </a>
                                            <form action="/admin/employees/archive" method="POST" style="display: inline-flex; margin: 0;" data-confirm="<?= e(trans('confirm_archive_employee')) ?>" onsubmit="return confirm(this.dataset.confirm);">
                                                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                                <button type="submit" style="display: inline-flex; align-items: center; justify-content: center; height: 28px; padding: 0 8px; border-radius: 6px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #f87171; font-weight: 600; font-size: 0.78rem; white-space: nowrap; cursor: pointer; line-height: 1; transition: background 0.15s ease-in-out;">
                                                    <?= e(trans('archive')) ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form action="/admin/employees/restore" method="POST" style="display: inline-flex; margin: 0;" data-confirm="<?= e(trans('confirm_restore_employee')) ?>" onsubmit="return confirm(this.dataset.confirm);">
                                                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                                <button type="submit" style="display: inline-flex; align-items: center; justify-content: center; height: 28px; padding: 0 8px; border-radius: 6px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; font-weight: 600; font-size: 0.78rem; white-space: nowrap; cursor: pointer; line-height: 1; transition: background 0.15s ease-in-out;">
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

            <!-- Pagination -->
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

                $prevUrl = $currentPage > 1 ? '/admin/employees?' . http_build_query(array_merge($baseParams, ['page' => $currentPage - 1])) : null;
                $nextUrl = $currentPage < $totalPages ? '/admin/employees?' . http_build_query(array_merge($baseParams, ['page' => $currentPage + 1])) : null;
                ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-muted, #94a3b8); font-size: 0.875rem;">
                    <div>
                        <?= e(sprintf(trans('page_of'), $currentPage, $totalPages)) ?> (<?= e(trans('total_employees')) ?>: <?= (int)($results['total'] ?? 0) ?>)
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($prevUrl): ?>
                            <a href="<?= e($prevUrl) ?>" style="padding: 0.4rem 0.8rem; border-radius: 6px; background: rgba(255, 255, 255, 0.1); color: #fff; text-decoration: none;">
                                <?= e(trans('previous')) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($nextUrl): ?>
                            <a href="<?= e($nextUrl) ?>" style="padding: 0.4rem 0.8rem; border-radius: 6px; background: rgba(255, 255, 255, 0.1); color: #fff; text-decoration: none;">
                                <?= e(trans('next')) ?>
                            </a>
                        <?php endif; ?>
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
