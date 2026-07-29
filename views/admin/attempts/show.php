<?php

use App\Support\TimezoneHelper;

$pageTitle = trans('attempt_details');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
$testName = $currentLang === 'en' ? $attempt['test_name_en'] : $attempt['test_name_ar'];
$departmentName = $currentLang === 'en' ? $attempt['department_name_en'] : $attempt['department_name_ar'];
$sectionName = $currentLang === 'en' ? $attempt['section_name_en'] : $attempt['section_name_ar'];
$jobTitleName = $currentLang === 'en' ? $attempt['job_title_name_en'] : $attempt['job_title_name_ar'];
$decode = static function (mixed $value): array {
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
};
$optionText = static function (array $options, ?string $key, string $lang): string {
    if ($key === null || $key === '') {
        return trans('not_answered');
    }
    foreach ($options as $option) {
        if (is_array($option) && (string)($option['key'] ?? '') === $key) {
            $field = $lang === 'en' ? 'text_en' : 'text_ar';
            return (string)($option[$field] ?? $option['text_en'] ?? $option['text_ar'] ?? $key);
        }
    }
    return $key;
};
$status = (string)$attempt['status'];
$statusClass = match($status){'IN_PROGRESS'=>'badge-progress','COMPLETED'=>'badge-completed','TIMED_OUT'=>'badge-timeout','CANCELLED'=>'badge-cancelled',default=>'badge-muted'};
ob_start();
?>
<style>
.attempt-detail-shell{display:grid;gap:1rem}.attempt-detail-header{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}.attempt-detail-title{margin:0;color:#f8fafc;font-size:1.7rem}.detail-card{background:rgba(30,41,59,.82);border:1px solid rgba(148,163,184,.18);border-radius:12px;padding:1rem}.detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}.detail-item{padding:.75rem;background:rgba(15,23,42,.48);border:1px solid rgba(148,163,184,.13);border-radius:8px}.detail-item span{display:block;color:#94a3b8;font-size:.78rem;margin-bottom:.3rem}.detail-item strong,.detail-item div{color:#f8fafc;overflow-wrap:anywhere}.detail-actions-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.action-panel{padding:1rem;border:1px solid rgba(148,163,184,.18);border-radius:10px;background:rgba(15,23,42,.48)}.action-panel h3{color:#f8fafc;margin:0 0 .5rem}.action-panel p{color:#94a3b8;font-size:.88rem}.detail-input{width:100%;padding:.7rem;border-radius:8px;border:1px solid rgba(148,163,184,.28);background:#0f172a;color:#e2e8f0;resize:vertical}.danger-button{background:#991b1b;border:1px solid #ef4444;color:#fff}.warning-button{background:#92400e;border:1px solid #f59e0b;color:#fff}.success-button{background:#166534;border:1px solid #22c55e;color:#fff}.detail-table-wrap{overflow-x:auto}.detail-table{width:100%;border-collapse:collapse;min-width:900px}.detail-table th,.detail-table td{padding:.72rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:start;vertical-align:top}.detail-table th{font-size:.78rem;color:#94a3b8}.detail-table td{color:#e2e8f0}.attempt-badge{display:inline-flex;padding:.22rem .55rem;border-radius:999px;font-size:.72rem;font-weight:800;border:1px solid rgba(148,163,184,.25)}.badge-progress{color:#93c5fd;background:rgba(59,130,246,.12)}.badge-completed,.badge-pass{color:#86efac;background:rgba(34,197,94,.12)}.badge-timeout{color:#fcd34d;background:rgba(245,158,11,.12)}.badge-cancelled,.badge-fail{color:#fca5a5;background:rgba(239,68,68,.12)}.badge-muted{color:#cbd5e1;background:rgba(148,163,184,.12)}.alert-box{padding:.8rem 1rem;border-radius:8px}.alert-danger{color:#fecaca;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35)}.alert-success{color:#bbf7d0;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.35)}.json-meta{white-space:pre-wrap;max-width:520px;font-size:.78rem;color:#cbd5e1;background:#0f172a;padding:.5rem;border-radius:6px}.section-heading{color:#f8fafc;margin:0 0 .8rem}.muted{color:#94a3b8}@media(max-width:900px){.detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.detail-actions-grid{grid-template-columns:1fr}}@media(max-width:600px){.detail-grid{grid-template-columns:1fr}}
</style>
<div class="attempt-detail-shell">
    <div class="attempt-detail-header">
        <div>
            <h1 class="attempt-detail-title"><?= e(trans('attempt_details')) ?> #<?= (int)$attempt['id'] ?></h1>
            <p class="muted" style="margin:.35rem 0 0"><?= e((string)$attempt['employee_name']) ?> — <bdi dir="ltr"><?= e((string)$attempt['test_code']) ?></bdi></p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a href="/admin/attempts" class="nav-link"><?= e(trans('back_to_attempts')) ?></a>
            <a href="/admin/assignments/view?id=<?= (int)$attempt['assignment_id'] ?>" class="nav-link"><?= e(trans('assignment_details')) ?></a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?><div class="alert-box alert-success"><?= e((string)$_SESSION['flash_success']) ?></div><?php unset($_SESSION['flash_success']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert-box alert-danger"><?= e((string)$_SESSION['flash_error']) ?></div><?php unset($_SESSION['flash_error']); endif; ?>
    <?php if (!empty($_SESSION['flash_info'])): ?><div class="alert-box" style="color:#bfdbfe;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.35)"><?= e((string)$_SESSION['flash_info']) ?></div><?php unset($_SESSION['flash_info']); endif; ?>

    <section class="detail-card">
        <h2 class="section-heading"><?= e(trans('attempt_summary')) ?></h2>
        <div class="detail-grid">
            <div class="detail-item"><span><?= e(trans('status')) ?></span><strong><span class="attempt-badge <?= e($statusClass) ?>"><?= e(trans('attempt_status_'.strtolower($status))) ?></span><?php if ((int)$attempt['is_expired_in_progress']===1): ?> <span class="attempt-badge badge-fail"><?= e(trans('expired_waiting_finalization')) ?></span><?php endif; ?></strong></div>
            <div class="detail-item"><span><?= e(trans('attempt_number')) ?></span><strong><bdi dir="ltr"><?= (int)$attempt['attempt_number'] ?> / <?= (int)$attempt['max_attempts'] ?></bdi></strong></div>
            <div class="detail-item"><span><?= e(trans('result')) ?></span><strong><?php if (in_array($status,['COMPLETED','TIMED_OUT'],true)): ?><span class="attempt-badge <?= (int)$attempt['passed']===1?'badge-pass':'badge-fail' ?>"><?= e(trans((int)$attempt['passed']===1?'passed':'failed')) ?></span> — <bdi dir="ltr"><?= e(number_format((float)$attempt['percentage'],2)) ?>%</bdi><?php else: ?><?= e(trans('attempt_outcome_ungraded')) ?><?php endif; ?></strong></div>
            <div class="detail-item"><span><?= e(trans('score')) ?></span><strong><bdi dir="ltr"><?= e(number_format((float)$attempt['score'],2)) ?> / <?= e(number_format((float)$attempt['total_score'],2)) ?></bdi></strong></div>
            <div class="detail-item"><span><?= e(trans('answers')) ?></span><strong><bdi dir="ltr"><?= (int)$attempt['answered_count'] ?> / <?= (int)$attempt['question_count'] ?></bdi></strong></div>
            <div class="detail-item"><span><?= e(trans('focus_losses')) ?></span><strong><bdi dir="ltr"><?= (int)$attempt['focus_loss_count'] ?></bdi></strong></div>
            <div class="detail-item"><span><?= e(trans('started_at')) ?></span><strong><?= e(TimezoneHelper::format((string)$attempt['started_at'], null, 'Y-m-d H:i:s')) ?></strong></div>
            <div class="detail-item"><span><?= e(trans('expires_at')) ?></span><strong><?= e(TimezoneHelper::format((string)$attempt['expires_at'], null, 'Y-m-d H:i:s')) ?></strong></div>
            <div class="detail-item"><span><?= e(trans('submitted_at')) ?></span><strong><?= e(TimezoneHelper::format($attempt['submitted_at'] ?? null, null, 'Y-m-d H:i:s')) ?></strong></div>
            <div class="detail-item"><span><?= e(trans('submission_reason')) ?></span><strong><?= e(!empty($attempt['submission_reason']) ? trans('submission_reason_'.strtolower((string)$attempt['submission_reason'])) : trans('not_available')) ?></strong></div>
            <div class="detail-item"><span><?= e(trans('ip_address')) ?></span><strong><bdi dir="ltr"><?= e((string)$attempt['ip_address']) ?></bdi></strong></div>
            <div class="detail-item"><span><?= e(trans('device_information')) ?></span><div><bdi dir="ltr"><?= e((string)$attempt['user_agent']) ?></bdi></div></div>
        </div>
    </section>

    <section class="detail-card">
        <h2 class="section-heading"><?= e(trans('employee_and_assessment')) ?></h2>
        <div class="detail-grid">
            <div class="detail-item"><span><?= e(trans('employee')) ?></span><strong><?= e((string)$attempt['employee_name']) ?></strong><div><bdi dir="ltr"><?= e((string)$attempt['employee_number']) ?></bdi></div></div>
            <div class="detail-item"><span><?= e(trans('organization_unit')) ?></span><strong><?= e((string)$departmentName) ?></strong><div><?= e((string)$sectionName) ?> — <?= e((string)$jobTitleName) ?></div></div>
            <div class="detail-item"><span><?= e(trans('assessment')) ?></span><strong><bdi dir="ltr"><?= e((string)$attempt['test_code']) ?></bdi></strong><div><?= e((string)$testName) ?></div></div>
            <div class="detail-item"><span><?= e(trans('assignment_status')) ?></span><strong><?= e(trans('assignment_status_'.strtolower((string)$attempt['assignment_status']))) ?></strong></div>
            <div class="detail-item"><span><?= e(trans('security_events')) ?></span><strong><bdi dir="ltr"><?= (int)$attempt['security_event_count'] ?></bdi></strong></div>
            <div class="detail-item"><span><?= e(trans('passing_percentage')) ?></span><strong><bdi dir="ltr"><?= e(number_format((float)$attempt['passing_percentage'],2)) ?>%</bdi></strong></div>
        </div>
    </section>

    <?php if ($actionTokens !== []): ?>
    <section class="detail-card">
        <h2 class="section-heading"><?= e(trans('administrative_actions')) ?></h2>
        <div class="detail-actions-grid">
            <?php if (!empty($actionTokens['finalize'])): ?>
                <div class="action-panel">
                    <h3><?= e(trans('finalize_expired_attempt')) ?></h3>
                    <p><?= e(trans('finalize_expired_attempt_help')) ?></p>
                    <form method="POST" action="/admin/attempts/finalize-expired" onsubmit="return confirm('<?= e(trans('confirm_finalize_expired_attempt')) ?>')">
                        <?= csrf_field() ?><input type="hidden" name="attempt_id" value="<?= (int)$attempt['id'] ?>"><input type="hidden" name="action_token" value="<?= e((string)$actionTokens['finalize']) ?>">
                        <button type="submit" class="btn warning-button" style="width:auto;margin:0"><?= e(trans('finalize_expired_attempt')) ?></button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if (!empty($actionTokens['cancel'])): ?>
                <div class="action-panel">
                    <h3><?= e(trans('cancel_attempt')) ?></h3><p><?= e(trans('cancel_attempt_help')) ?></p>
                    <form method="POST" action="/admin/attempts/cancel" onsubmit="return confirm('<?= e(trans('confirm_cancel_attempt')) ?>')">
                        <?= csrf_field() ?><input type="hidden" name="attempt_id" value="<?= (int)$attempt['id'] ?>"><input type="hidden" name="action_token" value="<?= e((string)$actionTokens['cancel']) ?>">
                        <label for="cancel-reason" class="muted"><?= e(trans('cancellation_reason')) ?></label>
                        <textarea id="cancel-reason" name="reason" class="detail-input" rows="4" minlength="10" maxlength="1000" required></textarea>
                        <button type="submit" class="btn danger-button" style="width:auto;margin:.7rem 0 0"><?= e(trans('cancel_attempt')) ?></button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if (!empty($actionTokens['retake'])): ?>
                <div class="action-panel">
                    <h3><?= e(trans('authorize_retake')) ?></h3><p><?= e(trans('authorize_retake_help')) ?></p>
                    <form method="POST" action="/admin/attempts/retake" onsubmit="return confirm('<?= e(trans('confirm_authorize_retake')) ?>')">
                        <?= csrf_field() ?><input type="hidden" name="attempt_id" value="<?= (int)$attempt['id'] ?>"><input type="hidden" name="action_token" value="<?= e((string)$actionTokens['retake']) ?>">
                        <label for="retake-reason" class="muted"><?= e(trans('retake_reason')) ?></label>
                        <textarea id="retake-reason" name="reason" class="detail-input" rows="4" minlength="10" maxlength="1000" required></textarea>
                        <button type="submit" class="btn success-button" style="width:auto;margin:.7rem 0 0"><?= e(trans('authorize_retake')) ?></button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if (!empty($actionTokens['notes'])): ?>
                <div class="action-panel">
                    <h3><?= e(trans('supervisor_notes')) ?></h3><p><?= e(trans('supervisor_notes_help')) ?></p>
                    <form method="POST" action="/admin/attempts/notes">
                        <?= csrf_field() ?><input type="hidden" name="attempt_id" value="<?= (int)$attempt['id'] ?>"><input type="hidden" name="action_token" value="<?= e((string)$actionTokens['notes']) ?>">
                        <textarea name="supervisor_notes" class="detail-input" rows="5" maxlength="2000"><?= e((string)($attempt['supervisor_notes'] ?? '')) ?></textarea>
                        <button type="submit" class="btn btn-primary" style="width:auto;margin:.7rem 0 0"><?= e(trans('save_notes')) ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($status === 'CANCELLED'): ?>
    <section class="detail-card">
        <h2 class="section-heading"><?= e(trans('cancellation_details')) ?></h2>
        <div class="detail-grid">
            <div class="detail-item"><span><?= e(trans('cancelled_at')) ?></span><strong><?= e(TimezoneHelper::format($attempt['cancelled_at'] ?? null, null, 'Y-m-d H:i:s')) ?></strong></div>
            <div class="detail-item"><span><?= e(trans('cancelled_by')) ?></span><strong><?= e((string)($attempt['cancelled_by_name'] ?? trans('not_available'))) ?></strong></div>
            <div class="detail-item"><span><?= e(trans('cancellation_reason')) ?></span><div><?= nl2br(e((string)($attempt['cancellation_reason'] ?? ''))) ?></div></div>
        </div>
    </section>
    <?php endif; ?>

    <section class="detail-card">
        <h2 class="section-heading"><?= e(trans('submitted_answers')) ?></h2>
        <?php if ($answers === []): ?><p class="muted"><?= e(trans('no_attempt_questions')) ?></p><?php else: ?>
        <div class="detail-table-wrap"><table class="detail-table">
            <thead><tr><th>#</th><th><?= e(trans('question')) ?></th><th><?= e(trans('selected_answer')) ?></th><th><?= e(trans('correct_answer')) ?></th><th><?= e(trans('result')) ?></th><th><?= e(trans('score')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($answers as $answer):
                $question=$decode($answer['question_snapshot'] ?? null); $options=$decode($answer['options_snapshot'] ?? null);
                $questionText=(string)($question[$currentLang==='en'?'text_en':'text_ar'] ?? $question['text_en'] ?? $question['text_ar'] ?? '');
                $selectedKey=is_string($answer['selected_option_key'] ?? null)?$answer['selected_option_key']:null;
                $correctKey=(string)$answer['correct_option_key_snapshot'];
            ?>
                <tr>
                    <td><?= (int)$answer['display_order'] ?></td>
                    <td><?= nl2br(e($questionText)) ?></td>
                    <td><?= e($optionText($options,$selectedKey,$currentLang)) ?></td>
                    <td><?= e($optionText($options,$correctKey,$currentLang)) ?></td>
                    <td><?php if ($answer['is_correct']===null): ?><span class="attempt-badge badge-muted"><?= e(trans('not_answered')) ?></span><?php else: ?><span class="attempt-badge <?= (int)$answer['is_correct']===1?'badge-pass':'badge-fail' ?>"><?= e(trans((int)$answer['is_correct']===1?'correct':'incorrect')) ?></span><?php endif; ?></td>
                    <td><bdi dir="ltr"><?= e(number_format((float)($answer['awarded_score'] ?? 0),2)) ?> / <?= e(number_format((float)$answer['score_snapshot'],2)) ?></bdi></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>

    <section class="detail-card">
        <h2 class="section-heading"><?= e(trans('security_events')) ?></h2>
        <?php if ($securityEvents === []): ?><p class="muted"><?= e(trans('no_security_events')) ?></p><?php else: ?>
        <div class="detail-table-wrap"><table class="detail-table">
            <thead><tr><th><?= e(trans('event_type')) ?></th><th><?= e(trans('event_time')) ?></th><th><?= e(trans('ip_address')) ?></th><th><?= e(trans('metadata')) ?></th></tr></thead>
            <tbody><?php foreach ($securityEvents as $event): $metadata=$decode($event['metadata'] ?? null); ?><tr><td><?= e((string)$event['event_type']) ?></td><td><?= e(TimezoneHelper::format((string)$event['created_at'], null, 'Y-m-d H:i:s')) ?></td><td><bdi dir="ltr"><?= e((string)$event['ip_address']) ?></bdi></td><td><pre class="json-meta"><?= e($metadata === [] ? trans('not_available') : (string)json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)) ?></pre></td></tr><?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </section>

    <section class="detail-card">
        <h2 class="section-heading"><?= e(trans('test_sessions')) ?></h2>
        <?php if ($sessions === []): ?><p class="muted"><?= e(trans('no_test_sessions')) ?></p><?php else: ?>
        <div class="detail-table-wrap"><table class="detail-table"><thead><tr><th><?= e(trans('status')) ?></th><th><?= e(trans('started_at')) ?></th><th><?= e(trans('last_activity')) ?></th><th><?= e(trans('ended_at')) ?></th><th><?= e(trans('ip_address')) ?></th></tr></thead><tbody><?php foreach($sessions as $session): ?><tr><td><?= e(trans('session_status_'.strtolower((string)$session['status']))) ?></td><td><?= e(TimezoneHelper::format((string)$session['started_at'],null,'Y-m-d H:i:s')) ?></td><td><?= e(TimezoneHelper::format((string)$session['last_activity_at'],null,'Y-m-d H:i:s')) ?></td><td><?= e(TimezoneHelper::format($session['ended_at']??null,null,'Y-m-d H:i:s')) ?></td><td><bdi dir="ltr"><?= e((string)$session['ip_address']) ?></bdi></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>

    <section class="detail-card">
        <h2 class="section-heading"><?= e(trans('audit_history')) ?></h2>
        <?php if ($auditHistory === []): ?><p class="muted"><?= e(trans('no_audit_history')) ?></p><?php else: ?>
        <div class="detail-table-wrap"><table class="detail-table"><thead><tr><th><?= e(trans('action')) ?></th><th><?= e(trans('actor')) ?></th><th><?= e(trans('created_at')) ?></th><th><?= e(trans('changed_fields')) ?></th></tr></thead><tbody><?php foreach($auditHistory as $audit): $payload=$decode($audit['new_values']??$audit['old_values']??null); $fields=$payload['changed_field_names']??$payload['created_field_names']??[]; ?><tr><td><?= e((string)$audit['action']) ?></td><td><?= e((string)($audit['admin_name']??$audit['actor_type'])) ?></td><td><?= e(TimezoneHelper::format((string)$audit['created_at'],null,'Y-m-d H:i:s')) ?></td><td><?= e(is_array($fields)&&$fields!==[]?implode(', ',array_map('strval',$fields)):trans('not_available')) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>
</div>
<?php $content=ob_get_clean(); require __DIR__.'/../../layouts/admin.php'; ?>
