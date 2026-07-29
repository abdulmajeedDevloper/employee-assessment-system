<?php

declare(strict_types=1);

$currentLang = $_SESSION['lang'] ?? 'ar';
$pageTitle = trans('tests') . ' - ' . trans('app_name');

ob_start();

$state = $statusData['state'] ?? 'NO_ASSIGNMENTS';
?>

<div class="card-box" style="max-width: 680px;">
    <?php if (!empty($success)): ?>
        <div class="alert-box alert-success">
            <?= e((string) $success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert-box">
            <?= e((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if ($state === 'COMPLETED_ALL'): ?>
        <h1 class="card-title">
            <?= e(trans('completed')) ?>
        </h1>

        <p class="card-subtitle">
            <?= e(trans('assessment_finished_message')) ?>
        </p>

        <div class="notice-box" style="text-align: center;">
            ✓ <?= e(trans('assessment_already_completed')) ?>
        </div>

    <?php elseif ($state === 'NO_ASSIGNMENTS'): ?>
        <h1 class="card-title">
            <?= e(trans('tests')) ?>
        </h1>

        <p class="card-subtitle">
            <?= e(trans('no_active_assignments')) ?>
        </p>

    <?php elseif ($state === 'IN_PROGRESS'): ?>
        <?php $attempt = $statusData['attempt']; ?>

        <h1 class="card-title">
            <?= e(trans('in_progress')) ?>
        </h1>

        <p class="card-subtitle">
            <?= e(
                $currentLang === 'ar'
                    ? (string) $attempt['test_name_ar']
                    : (string) $attempt['test_name_en']
            ) ?>
        </p>

        <div class="notice-box">
            <?= e(trans('unsaved_warning')) ?>
        </div>

        <p style="text-align: center; margin-top: 1rem;">
            <a
                href="/employee/attempt"
                class="btn-primary"
                style="
                    display: inline-block;
                    text-decoration: none;
                "
            >
                <?= e(trans('resume_assessment')) ?>
            </a>
        </p>

    <?php elseif ($state === 'PENDING_INSTRUCTIONS'): ?>
        <?php
        $assignments = $statusData['assignments'] ?? [];
        ?>

        <h1 class="card-title">
            <?= e(trans('instructions')) ?>
        </h1>

        <p class="card-subtitle">
            <?= e(trans('welcome')) ?>،
            <?= e((string) $statusData['employee']['full_name']) ?>
        </p>

        <?php foreach ($assignments as $assignment): ?>
            <div
                style="
                    background: rgba(15, 23, 42, 0.6);
                    padding: 1rem;
                    border-radius: 8px;
                    margin-bottom: 1rem;
                "
            >
                <h3>
                    <?= e(
                        $currentLang === 'ar'
                            ? (string) $assignment['test_name_ar']
                            : (string) $assignment['test_name_en']
                    ) ?>
                </h3>

                <p
                    style="
                        font-size: 0.875rem;
                        color: var(--text-muted);
                        margin: 0.5rem 0;
                    "
                >
                    <?= e(
                        $currentLang === 'ar'
                            ? (string) $assignment['instructions_ar']
                            : (string) $assignment['instructions_en']
                    ) ?>
                </p>

                <p
                    style="
                        font-size: 0.8rem;
                        color: #60a5fa;
                    "
                >
                    <?= e(trans('duration')) ?>:
                    <?= e((string) $assignment['duration_minutes']) ?>
                    <?= e(trans('minutes')) ?>

                    |

                    <?= e(trans('question')) ?>:
                    <?= e((string) $assignment['question_count']) ?>
                </p>

                <?php if (
                    in_array(
                        (string) $assignment['status'],
                        ['PENDING', 'RETAKE_AUTHORIZED'],
                        true
                    )
                ): ?>
                    <?php if ((int)($assignment['is_available'] ?? 0) === 1): ?>
                        <form
                            action="/employee/tests/start"
                            method="POST"
                            style="margin-top: 1rem;"
                        >
                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="assignment_id"
                                value="<?= e((string) $assignment['id']) ?>"
                            >

                            <button
                                type="submit"
                                class="btn-primary"
                            >
                                <?= e(trans('start_assessment')) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="notice-box" style="margin-top:1rem;">
                            <?= e(trans(
                                ($assignment['availability_state'] ?? 'EXPIRED') === 'UPCOMING'
                                    ? 'assignment_not_open_yet'
                                    : 'assignment_window_expired'
                            )) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php

$content = ob_get_clean();

require __DIR__ . '/../layouts/main.php';
?>