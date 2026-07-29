<?php
$pageTitle = trans('test_details');
$isAuthenticatedLayout = true;
ob_start();

$currentLang = $_SESSION['lang'] ?? 'ar';
$testName = $localized['name'] ?? '';
$description = $localized['description'] ?? '';
$instructions = $localized['instructions'] ?? '';
$canManageTests = $canManageTests ?? false;
$canManageAssignments = \App\Services\AuthorizationService::can($admin ?? [], 'manage_assignments');

$availableFrom = $test['available_from'] ?? null;
$availableUntil = $test['available_until'] ?? null;

$backUrl = '/admin/tests';
if (isset($_GET['lang'])) {
    $backUrl .= '?lang=' . urlencode($_GET['lang']);
}

$editUrl = '/admin/tests/edit?id=' . (int)$test['id'];
$lifecycleUrl = '/admin/tests/lifecycle?id=' . (int)$test['id'];
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $editUrl .= '&lang=' . urlencode($_GET['lang']);
    $lifecycleUrl .= '&lang=' . urlencode($_GET['lang']);
}
?>

<style>
    .details-container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .details-header-card {
        background: var(--card-bg, rgba(30, 41, 59, 0.85));
        border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
        border-radius: 12px;
        padding: 1.75rem 2rem;
        backdrop-filter: blur(12px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .details-title-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .details-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #f8fafc;
        margin: 0;
    }

    .details-badge-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .grid-two-col {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        width: 100%;
    }

    @media (max-width: 768px) {
        .grid-two-col {
            grid-template-columns: 1fr;
        }
    }

    .section-card {
        background: var(--card-bg, rgba(30, 41, 59, 0.85));
        border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
        border-radius: 12px;
        padding: 1.5rem;
        backdrop-filter: blur(12px);
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #60a5fa;
        margin-top: 0;
        margin-bottom: 1.25rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .info-list {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        font-size: 0.9rem;
    }

    .info-label {
        color: #94a3b8;
        font-weight: 500;
    }

    .info-value {
        color: #f8fafc;
        font-weight: 600;
    }

    .metrics-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
        width: 100%;
    }

    @media (max-width: 640px) {
        .metrics-row {
            grid-template-columns: 1fr;
        }
    }

    .metric-subcard {
        background: rgba(15, 23, 42, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        padding: 1.25rem;
        text-align: center;
    }

    .metric-sublabel {
        font-size: 0.85rem;
        color: #94a3b8;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .metric-subvalue {
        font-size: 1.75rem;
        font-weight: 800;
        color: #ffffff;
        line-height: 1;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.85rem;
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

    .technical-value {
        unicode-bidi: isolate;
        font-family: monospace, sans-serif;
    }
</style>

<div class="details-container">
    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert-box alert-success" style="margin-bottom: 0;">
            <?= e($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="alert-box alert-info" style="background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3); color: #60a5fa; margin-bottom: 0;">
            <?= e($_SESSION['flash_info']) ?>
        </div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>

    <!-- Header Card -->
    <div class="details-header-card">
        <div class="details-title-group">
            <h1 class="details-title"><?= e($testName) ?></h1>
            <div class="details-badge-group">
                <bdi dir="ltr" class="technical-value" style="font-size: 0.9rem; color: #94a3b8; background: rgba(255,255,255,0.05); padding: 0.2rem 0.6rem; border-radius: 4px;">
                    <?= e($test['code']) ?>
                </bdi>
                <?php if ((int)$test['is_active'] === 1): ?>
                    <span class="status-badge status-active"><?= e(trans('active')) ?></span>
                <?php else: ?>
                    <span class="status-badge status-inactive"><?= e(trans('inactive')) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <?php if ($canManageAssignments && (int)$test['is_active'] === 1): ?>
                <a href="/admin/assignments/create?test_id=<?= (int)$test['id'] ?>" class="btn btn-secondary" style="padding:.5rem 1rem;font-size:.875rem;text-decoration:none;">
                    <?= e(trans('create_assignment')) ?>
                </a>
            <?php endif; ?>
            <?php if ($canManageTests): ?>
                <a href="<?= e($editUrl) ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none;">
                    <?= e(trans('edit_assessment')) ?>
                </a>
                <a href="<?= e($lifecycleUrl) ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none;">
                    <?= e(trans('manage_assessment_lifecycle')) ?>
                </a>
            <?php endif; ?>
            <a href="<?= e($backUrl) ?>" class="nav-link">
                &larr; <?= e(trans('back_to_tests')) ?>
            </a>
        </div>
    </div>

    <!-- Metadata Grid (2 Columns) -->
    <div class="grid-two-col">
        <!-- Metadata Section -->
        <div class="section-card">
            <h2 class="section-title"><?= e(trans('test_details')) ?></h2>
            <div class="info-list">
                <div class="info-item">
                    <span class="info-label"><?= e(trans('test_code')) ?></span>
                    <span class="info-value"><bdi dir="ltr" class="technical-value"><?= e($test['code']) ?></bdi></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('duration_minutes_label')) ?></span>
                    <span class="info-value"><bdi dir="ltr" class="technical-value"><?= (int)$test['duration_minutes'] ?></bdi> <?= e(trans('minutes')) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('questions_per_attempt')) ?></span>
                    <span class="info-value"><bdi dir="ltr" class="technical-value"><?= (int)$test['question_count'] ?></bdi></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('active_question_bank')) ?></span>
                    <span class="info-value"><bdi dir="ltr" class="technical-value"><?= (int)$aggregates['active_question_bank_count'] ?></bdi></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('passing_score_label')) ?></span>
                    <span class="info-value"><bdi dir="ltr" class="technical-value"><?= number_format((float)$test['passing_percentage'], 2) ?>%</bdi></span>
                </div>
                <?php if ($availableFrom === null && $availableUntil === null): ?>
                    <div class="info-item">
                        <span class="info-label"><?= e(trans('availability')) ?></span>
                        <span class="info-value"><?= e(trans('always_available')) ?></span>
                    </div>
                <?php else: ?>
                    <div class="info-item">
                        <span class="info-label"><?= e(trans('available_from')) ?></span>
                        <span class="info-value">
                            <?php if ($availableFrom !== null): ?>
                                <bdi dir="ltr" class="technical-value"><?= e(\App\Support\TimezoneHelper::format((string)$availableFrom)) ?></bdi>
                            <?php else: ?>
                                <?= e(trans('available_immediately')) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><?= e(trans('available_until')) ?></span>
                        <span class="info-value">
                            <?php if ($availableUntil !== null): ?>
                                <bdi dir="ltr" class="technical-value"><?= e(\App\Support\TimezoneHelper::format((string)$availableUntil)) ?></bdi>
                            <?php else: ?>
                                <?= e(trans('no_end_date')) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('created_at')) ?></span>
                    <span class="info-value"><bdi dir="ltr" class="technical-value"><?= e($test['created_at']) ?></bdi></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('updated_at')) ?></span>
                    <span class="info-value"><bdi dir="ltr" class="technical-value"><?= e($test['updated_at']) ?></bdi></span>
                </div>
            </div>
        </div>

        <!-- Settings Section -->
        <div class="section-card">
            <h2 class="section-title"><?= e(trans('settings')) ?></h2>
            <div class="info-list">
                <div class="info-item">
                    <span class="info-label"><?= e(trans('randomize_questions')) ?></span>
                    <span class="info-value"><?= e(trans((int)$test['randomize_questions'] === 1 ? 'yes' : 'no')) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('randomize_options')) ?></span>
                    <span class="info-value"><?= e(trans((int)$test['randomize_options'] === 1 ? 'yes' : 'no')) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('allow_back_nav')) ?></span>
                    <span class="info-value"><?= e(trans((int)$test['allow_back_navigation'] === 1 ? 'yes' : 'no')) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?= e(trans('show_result_to_emp')) ?></span>
                    <span class="info-value"><?= e(trans((int)$test['show_result_to_employee'] === 1 ? 'yes' : 'no')) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Aggregate Metrics Row -->
    <div class="section-card" style="width: 100%; box-sizing: border-box;">
        <h2 class="section-title"><?= e(trans('assessment_statistics')) ?></h2>
        <div class="metrics-row">
            <div class="metric-subcard">
                <div class="metric-sublabel"><?= e(trans('assignments_count')) ?></div>
                <div class="metric-subvalue"><bdi dir="ltr" class="technical-value"><?= (int)$aggregates['assignments_count'] ?></bdi></div>
            </div>
            <div class="metric-subcard">
                <div class="metric-sublabel"><?= e(trans('attempts_count')) ?></div>
                <div class="metric-subvalue"><bdi dir="ltr" class="technical-value"><?= (int)$aggregates['attempts_count'] ?></bdi></div>
            </div>
            <div class="metric-subcard">
                <div class="metric-sublabel"><?= e(trans('completed_attempts')) ?></div>
                <div class="metric-subvalue"><bdi dir="ltr" class="technical-value"><?= (int)$aggregates['completed_attempts_count'] ?></bdi></div>
            </div>
        </div>
    </div>

    <!-- Content Sections (Description & Instructions) -->
    <?php if ($description !== '' || $instructions !== ''): ?>
        <div class="section-card" style="width: 100%; box-sizing: border-box;">
            <?php if ($description !== ''): ?>
                <div style="margin-bottom: 1.5rem;">
                    <h2 class="section-title"><?= e(trans('description')) ?></h2>
                    <div style="color: #cbd5e1; line-height: 1.6; font-size: 0.95rem;">
                        <?= nl2br(e($description)) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($instructions !== ''): ?>
                <div>
                    <h2 class="section-title"><?= e(trans('employee_instructions')) ?></h2>
                    <div style="color: #cbd5e1; line-height: 1.6; font-size: 0.95rem;">
                        <?= nl2br(e($instructions)) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
?>
