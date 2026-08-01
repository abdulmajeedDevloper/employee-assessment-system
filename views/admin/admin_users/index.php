<?php
$currentLang = $currentLang ?? $_SESSION['lang'] ?? 'ar';

$content = (static function() use ($result, $filters, $flashSuccess, $flashError, $currentLang): string {
    ob_start();
    ?>
    <div class="admin-account-management">
        <header class="page-header">
            <div class="header-title-group">
                <span class="header-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <div>
                    <h1><?= e(trans('admin_accounts')) ?></h1>
                    <p><?= e(trans('admin_accounts_subtitle')) ?></p>
                </div>
            </div>

            <div class="header-actions">
                <a href="/admin/admin-users/create" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span><?= e(trans('add_admin')) ?></span>
                </a>
            </div>
        </header>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" role="alert">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                <span><?= e($flashSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-danger" role="alert">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4M12 16h.01M22 12A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z"/></svg>
                <span><?= e($flashError) ?></span>
            </div>
        <?php endif; ?>

        <section class="filters-card">
            <form action="/admin/admin-users" method="GET" class="filters-form">
                <div class="filter-group filter-search">
                    <label for="filter-search" class="visually-hidden"><?= e(trans('search')) ?></label>
                    <input type="text" id="filter-search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="<?= e(trans('search_placeholder')) ?>" class="form-input">
                </div>

                <div class="filter-group">
                    <label for="filter-role" class="visually-hidden"><?= e(trans('role')) ?></label>
                    <select id="filter-role" name="role" class="form-select">
                        <option value=""><?= e(trans('all_roles')) ?></option>
                        <option value="super_admin" <?= ($filters['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>><?= e(trans('super_admin')) ?></option>
                        <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>><?= e(trans('admin')) ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-status" class="visually-hidden"><?= e(trans('status')) ?></label>
                    <select id="filter-status" name="status" class="form-select">
                        <option value=""><?= e(trans('all_statuses')) ?></option>
                        <option value="1" <?= ($filters['status'] ?? '') === '1' ? 'selected' : '' ?>><?= e(trans('active')) ?></option>
                        <option value="0" <?= ($filters['status'] ?? '') === '0' ? 'selected' : '' ?>><?= e(trans('inactive')) ?></option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-secondary">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <span><?= e(trans('filter')) ?></span>
                    </button>
                    <?php if (!empty($filters['search']) || !empty($filters['role']) || (isset($filters['status']) && $filters['status'] !== '')): ?>
                        <a href="/admin/admin-users" class="btn btn-text"><?= e(trans('reset')) ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="table-card">
            <?php if (empty($result['items'])): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                    <h3><?= e(trans('no_admin_accounts_found')) ?></h3>
                    <p><?= e(trans('no_admin_accounts_matched_criteria')) ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><?= e(trans('display_name')) ?></th>
                                <th><?= e(trans('username')) ?></th>
                                <th><?= e(trans('email')) ?></th>
                                <th><?= e(trans('role')) ?></th>
                                <th><?= e(trans('status')) ?></th>
                                <th><?= e(trans('last_login')) ?></th>
                                <th><?= e(trans('created_at')) ?></th>
                                <th><?= e(trans('actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['items'] as $item): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <strong><?= e($item['display_name']) ?></strong>
                                            <?php if ((int)($item['is_primary'] ?? 0) === 1): ?>
                                                <span class="badge badge-primary-account"><?= e(trans('primary_account')) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><bdi dir="ltr" class="code-text"><?= e($item['username']) ?></bdi></td>
                                    <td><bdi dir="ltr" class="code-text"><?= e($item['email']) ?></bdi></td>
                                    <td>
                                        <span class="role-badge role-<?= e($item['role']) ?>">
                                            <?= e(trans($item['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ((int)$item['is_active'] === 1): ?>
                                            <span class="status-badge status-active"><?= e(trans('active')) ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive"><?= e(trans('inactive')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <bdi dir="ltr" class="date-text">
                                            <?= $item['last_login_at'] ? e($item['last_login_at']) : e(trans('never')) ?>
                                        </bdi>
                                    </td>
                                    <td>
                                        <bdi dir="ltr" class="date-text"><?= e($item['created_at']) ?></bdi>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="/admin/admin-users/edit?id=<?= (int)$item['id'] ?>" class="btn-icon" title="<?= e(trans('edit')) ?>" aria-label="<?= e(trans('edit')) ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                            </a>

                                            <a href="/admin/admin-users/password?id=<?= (int)$item['id'] ?>" class="btn-icon" title="<?= e(trans('change_password')) ?>" aria-label="<?= e(trans('change_password')) ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                            </a>

                                            <?php if ((int)($item['is_primary'] ?? 0) !== 1 && (int)$item['id'] !== (int)($admin['id'] ?? 0)): ?>
                                                <form action="/admin/admin-users/toggle-status" method="POST" class="inline-form" onsubmit="return confirm('<?= e(trans('confirm_toggle_status_message')) ?>');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                    <input type="hidden" name="is_active" value="<?= (int)$item['is_active'] === 1 ? '0' : '1' ?>">
                                                    <button type="submit" class="btn-icon btn-toggle-status <?= (int)$item['is_active'] === 1 ? 'btn-deactivate' : 'btn-activate' ?>" title="<?= (int)$item['is_active'] === 1 ? e(trans('deactivate')) : e(trans('activate')) ?>">
                                                        <?php if ((int)$item['is_active'] === 1): ?>
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                                                        <?php else: ?>
                                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
                                                        <?php endif; ?>
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

                <?php if ($result['total_pages'] > 1): ?>
                    <nav class="pagination-wrapper" aria-label="<?= e(trans('pagination')) ?>">
                        <div class="pagination-info">
                            <?= e(trans('showing')) ?> <?= count($result['items']) ?> <?= e(trans('of')) ?> <?= $result['total'] ?>
                        </div>
                        <ul class="pagination-list">
                            <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
                                <li>
                                    <a href="/admin/admin-users?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>" class="page-link <?= $p === $result['page'] ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
    <?php
    return ob_get_clean();
})();

require __DIR__ . '/../../layouts/admin.php';
