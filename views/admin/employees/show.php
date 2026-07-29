<?php
$pageTitle = trans('employee_details');
$isAuthenticatedLayout = true;
ob_start();

$currentLang = $_SESSION['lang'] ?? 'ar';
$canManageAssignments = \App\Services\AuthorizationService::can($admin ?? [], 'manage_assignments');
?>

<style>
    .emp-details-wrapper {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .emp-details-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        box-sizing: border-box;
    }

    .emp-details-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #f8fafc;
        margin: 0;
    }

    .emp-back-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 42px;
        padding: 0 1.25rem;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 600;
        white-space: nowrap;
        transition: all 0.15s ease-in-out;
    }

    .emp-back-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.35);
    }

    .emp-details-card {
        width: 100%;
        box-sizing: border-box;
        background: var(--card-bg, rgba(30, 41, 59, 0.85));
        border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
        border-radius: 12px;
        padding: 2rem;
        backdrop-filter: blur(12px);
    }

    .emp-details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem 2rem;
        width: 100%;
        box-sizing: border-box;
    }

    .emp-detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        text-align: inherit;
    }

    .emp-detail-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-muted, #94a3b8);
        text-align: inherit;
    }

    .emp-detail-value {
        font-size: 1.1rem;
        color: #f8fafc;
        word-break: break-word;
    }

    .employee-technical-value {
        direction: ltr;
        unicode-bidi: isolate;
        display: inline-block;
        width: auto;
        max-width: 100%;
    }

    @media (max-width: 767px) {
        .emp-details-grid {
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        .emp-details-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        .emp-back-btn {
            width: 100%;
        }
    }
</style>

<div class="emp-details-wrapper">
    <div class="emp-details-header">
        <h1 class="emp-details-title">
            <?= e(trans('employee_details')) ?>
        </h1>
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <?php if (!empty($employee)): ?>
                <?php if (empty($employee['archived_at'])): ?>
                    <?php if ($canManageAssignments && (int)($employee['is_active'] ?? 0) === 1): ?>
                        <a href="/admin/assignments/create?employee_id=<?= (int)$employee['id'] ?>" class="emp-back-btn" style="background:#7c3aed;border-color:#7c3aed;">
                            <?= e(trans('create_assignment')) ?>
                        </a>
                    <?php endif; ?>
                    <a href="/admin/employees/edit?id=<?= (int)$employee['id'] ?>" class="emp-back-btn" style="background: #2563eb; border-color: #2563eb;">
                        <?= e(trans('edit_employee')) ?>
                    </a>
                    <form action="/admin/employees/archive" method="POST" style="display: inline;" data-confirm="<?= e(trans('confirm_archive_employee')) ?>" onsubmit="return confirm(this.dataset.confirm);">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$employee['id'] ?>">
                        <button type="submit" class="emp-back-btn" style="background: #dc2626; border-color: #dc2626; cursor: pointer;">
                            <?= e(trans('archive_employee')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <form action="/admin/employees/restore" method="POST" style="display: inline;" data-confirm="<?= e(trans('confirm_restore_employee')) ?>" onsubmit="return confirm(this.dataset.confirm);">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$employee['id'] ?>">
                        <button type="submit" class="emp-back-btn" style="background: #059669; border-color: #059669; cursor: pointer;">
                            <?= e(trans('restore_employee')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <a href="/admin/employees" class="emp-back-btn">
                <?= e(trans('back_to_employees')) ?>
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert-box" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 1rem 1.25rem; border-radius: 8px; font-weight: 600;">
            <?= e($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert-box" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 1rem 1.25rem; border-radius: 8px; font-weight: 600;">
            <?= e($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>



    <?php if (!empty($errorMessage) || empty($employee)): ?>
        <div class="emp-details-card" style="text-align: center;">
            <h2 style="color: #ef4444; font-size: 1.25rem; margin-bottom: 1rem;">
                <?= e($errorMessage ?? trans('employee_not_found')) ?>
            </h2>
            <a href="/admin/employees" class="emp-back-btn" style="background: #3b82f6; border-color: #3b82f6;">
                <?= e(trans('back_to_employees')) ?>
            </a>
        </div>
    <?php else: ?>
        <div class="emp-details-card">
            <div class="emp-details-grid">

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('full_name')) ?></span>
                    <strong class="emp-detail-value"><?= e($employee['full_name']) ?></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('employee_number')) ?></span>
                    <strong class="emp-detail-value"><bdi dir="ltr" class="employee-technical-value"><?= !empty($employee['employee_number']) ? e($employee['employee_number']) : '—' ?></bdi></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('national_id_last4')) ?></span>
                    <strong class="emp-detail-value"><bdi dir="ltr" class="employee-technical-value">******<?= e($employee['national_id_last4']) ?></bdi></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('email')) ?></span>
                    <strong class="emp-detail-value"><bdi dir="ltr" class="employee-technical-value"><?= e($employee['email']) ?></bdi></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('phone')) ?></span>
                    <strong class="emp-detail-value"><bdi dir="ltr" class="employee-technical-value"><?= e($employee['phone']) ?></bdi></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('department')) ?></span>
                    <strong class="emp-detail-value"><?= e($currentLang === 'en' ? ($employee['department_name_en'] ?? '') : ($employee['department_name_ar'] ?? '')) ?></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('section')) ?></span>
                    <strong class="emp-detail-value"><?= e($currentLang === 'en' ? ($employee['section_name_en'] ?? '') : ($employee['section_name_ar'] ?? '')) ?></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('job_title')) ?></span>
                    <strong class="emp-detail-value"><?= e($currentLang === 'en' ? ($employee['job_title_name_en'] ?? '') : ($employee['job_title_name_ar'] ?? '')) ?></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('preferred_language')) ?></span>
                    <strong class="emp-detail-value"><?= e(($employee['preferred_language'] ?? 'ar') === 'en' ? 'English' : 'العربية') ?></strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('status')) ?></span>
                    <div>
                        <?php if ($employee['archived_at'] !== null): ?>
                            <span style="display: inline-block; font-size: 0.875rem; color: #ef4444; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600;">
                                <?= e(trans('archived')) ?>
                            </span>
                        <?php elseif ((int)$employee['is_active'] === 1): ?>
                            <span style="display: inline-block; font-size: 0.875rem; color: #10b981; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600;">
                                <?= e(trans('active')) ?>
                            </span>
                        <?php else: ?>
                            <span style="display: inline-block; font-size: 0.875rem; color: #f59e0b; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600;">
                                <?= e(trans('inactive')) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('profile_status')) ?></span>
                    <strong class="emp-detail-value">
                        <?= e(!empty($employee['profile_completed_at']) ? trans('profile_complete') : trans('profile_incomplete')) ?>
                    </strong>
                </div>

                <div class="emp-detail-item">
                    <span class="emp-detail-label"><?= e(trans('created_at')) ?></span>
                    <strong class="emp-detail-value"><bdi dir="ltr" class="employee-technical-value"><?= e($employee['created_at']) ?></bdi></strong>
                </div>

                <?php if (!empty($employee['archived_at'])): ?>
                    <div class="emp-detail-item">
                        <span class="emp-detail-label"><?= e(trans('archived')) ?></span>
                        <strong class="emp-detail-value" style="color: #ef4444;"><bdi dir="ltr" class="employee-technical-value"><?= e($employee['archived_at']) ?></bdi></strong>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
?>
