<?php

declare(strict_types=1);

use App\Services\AuthorizationService;
use App\Support\TimezoneHelper;

$currentLang = $_SESSION['lang'] ?? 'ar';
$pageTitle = trans('assignment_details');
$isAuthenticatedLayout = true;
$canManageAssignments = AuthorizationService::can($admin ?? [], 'manage_assignments');
$testName = $currentLang === 'en' ? $assignment['test_name_en'] : $assignment['test_name_ar'];
$departmentName = $currentLang === 'en' ? $assignment['department_name_en'] : $assignment['department_name_ar'];
$sectionName = $currentLang === 'en' ? $assignment['section_name_en'] : $assignment['section_name_ar'];
$jobTitleName = $currentLang === 'en' ? $assignment['job_title_name_en'] : $assignment['job_title_name_ar'];
$status = (string)$assignment['status'];
ob_start();
?>
<style>
.assignment-detail-shell{max-width:1050px;margin:0 auto}.assignment-detail-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}.assignment-detail-card{background:rgba(30,41,59,.85);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1.3rem;margin-bottom:1rem}.assignment-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.assignment-detail-item{padding:.85rem;border:1px solid rgba(148,163,184,.16);border-radius:9px;background:rgba(15,23,42,.5)}.assignment-detail-label{display:block;color:#94a3b8;font-size:.78rem;margin-bottom:.35rem}.assignment-detail-value{color:#f8fafc;font-weight:600;overflow-wrap:anywhere}.assignment-actions{display:flex;gap:.55rem;flex-wrap:wrap}.assignment-badge{display:inline-flex;padding:.28rem .65rem;border-radius:999px;font-weight:700;border:1px solid rgba(148,163,184,.3);color:#e2e8f0}@media(max-width:850px){.assignment-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.assignment-detail-grid{grid-template-columns:1fr}}
</style>
<div class="assignment-detail-shell">
    <div class="assignment-detail-header">
        <div>
            <h1 class="admin-welcome-title" style="margin:0"><?= e(trans('assignment_details')) ?> <bdi dir="ltr">#<?= (int)$assignment['id'] ?></bdi></h1>
            <p style="color:#94a3b8;margin:.35rem 0 0"><?= e((string)$assignment['employee_name']) ?> — <bdi dir="ltr"><?= e((string)$assignment['test_code']) ?></bdi></p>
        </div>
        <div class="assignment-actions">
            <?php if ($canManageAssignments && $status==='PENDING' && (int)$assignment['attempt_count']===0): ?><a href="/admin/assignments/edit?id=<?= (int)$assignment['id'] ?>" class="nav-link"><?= e(trans('edit_assignment')) ?></a><?php endif; ?>
            <?php if (AuthorizationService::can($admin ?? [], 'view_attempts') && (int)$assignment['attempt_count'] > 0): ?><a href="/admin/attempts?assignment_id=<?= (int)$assignment['id'] ?>" class="nav-link"><?= e(trans('view_assignment_attempts')) ?></a><?php endif; ?>
            <a href="/admin/assignments" class="nav-link"><?= e(trans('back_to_assignments')) ?></a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert-box alert-success"><?= e($_SESSION['flash_success']) ?></div><?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert-box alert-danger"><?= e($_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); endif; ?>
    <?php if (!empty($_SESSION['flash_info'])): ?><div class="alert-box" style="border:1px solid rgba(59,130,246,.35);background:rgba(59,130,246,.1);color:#bfdbfe"><?= e($_SESSION['flash_info']) ?></div><?php unset($_SESSION['flash_info']); endif; ?>

    <?php if ($status !== 'PENDING' || (int)$assignment['attempt_count'] > 0): ?>
        <div class="alert-box" style="border:1px solid rgba(245,158,11,.35);background:rgba(245,158,11,.1);color:#fde68a"><?= e(trans('assignment_edit_status_locked')) ?></div>
    <?php endif; ?>

    <div class="assignment-detail-card">
        <h2 style="color:#f8fafc;margin-top:0"><?= e(trans('assignment_summary')) ?></h2>
        <div class="assignment-detail-grid">
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('status')) ?></span><span class="assignment-detail-value assignment-badge"><?= e(trans('assignment_status_'.strtolower($status))) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('availability')) ?></span><span class="assignment-detail-value"><?= e(trans('assignment_availability_'.strtolower((string)$assignment['availability_state']))) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('max_attempts')) ?></span><span class="assignment-detail-value"><bdi dir="ltr"><?= (int)$assignment['max_attempts'] ?></bdi></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('attempts_used')) ?></span><span class="assignment-detail-value"><bdi dir="ltr"><?= (int)$assignment['attempt_count'] ?></bdi></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('assigned_at')) ?></span><span class="assignment-detail-value"><?= e(TimezoneHelper::format((string)$assignment['assigned_at'], null, 'Y-m-d H:i')) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('assigned_by')) ?></span><span class="assignment-detail-value"><?= e((string)$assignment['assigned_by_name']) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('available_from')) ?></span><span class="assignment-detail-value"><?= e(TimezoneHelper::format($assignment['available_from'] ?? null, null, 'Y-m-d H:i')) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('available_until')) ?></span><span class="assignment-detail-value"><?= e(TimezoneHelper::format($assignment['available_until'] ?? null, null, 'Y-m-d H:i')) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('latest_result')) ?></span><span class="assignment-detail-value"><?= $assignment['latest_attempt_percentage']===null?e(trans('not_available')):'<bdi dir="ltr">'.e(number_format((float)$assignment['latest_attempt_percentage'],2)).'%</bdi>' ?></span></div>
        </div>
    </div>

    <div class="assignment-detail-card">
        <h2 style="color:#f8fafc;margin-top:0"><?= e(trans('employee_information')) ?></h2>
        <div class="assignment-detail-grid">
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('full_name')) ?></span><span class="assignment-detail-value"><?= e((string)$assignment['employee_name']) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('employee_number')) ?></span><span class="assignment-detail-value"><bdi dir="ltr"><?= e((string)($assignment['employee_number'] ?? '-')) ?></bdi></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('email')) ?></span><span class="assignment-detail-value"><bdi dir="ltr"><?= e((string)$assignment['employee_email']) ?></bdi></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('department')) ?></span><span class="assignment-detail-value"><?= e((string)$departmentName) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('section')) ?></span><span class="assignment-detail-value"><?= e((string)$sectionName) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('job_title')) ?></span><span class="assignment-detail-value"><?= e((string)$jobTitleName) ?></span></div>
        </div>
    </div>

    <div class="assignment-detail-card">
        <h2 style="color:#f8fafc;margin-top:0"><?= e(trans('assessment_information')) ?></h2>
        <div class="assignment-detail-grid">
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('assessment_code')) ?></span><span class="assignment-detail-value"><bdi dir="ltr"><?= e((string)$assignment['test_code']) ?></bdi></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('assessment')) ?></span><span class="assignment-detail-value"><?= e((string)$testName) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('duration')) ?></span><span class="assignment-detail-value"><bdi dir="ltr"><?= (int)$assignment['duration_minutes'] ?></bdi> <?= e(trans('minutes')) ?></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('question_count')) ?></span><span class="assignment-detail-value"><bdi dir="ltr"><?= (int)$assignment['question_count'] ?></bdi></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('passing_percentage')) ?></span><span class="assignment-detail-value"><bdi dir="ltr"><?= e(number_format((float)$assignment['passing_percentage'],2)) ?>%</bdi></span></div>
            <div class="assignment-detail-item"><span class="assignment-detail-label"><?= e(trans('assessment_status')) ?></span><span class="assignment-detail-value"><?= e(trans((int)$assignment['test_is_active']===1?'active':'inactive')) ?></span></div>
        </div>
    </div>
</div>
<?php $content=ob_get_clean(); require __DIR__.'/../../layouts/admin.php'; ?>
