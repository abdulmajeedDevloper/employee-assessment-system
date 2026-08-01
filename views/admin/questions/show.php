<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-questions-show';


$pageTitle = trans('question_details');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$t = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$question = is_array($question ?? null) ? $question : [];
$canManageQuestions = (bool)($canManageQuestions ?? false);
$editToken = $editToken ?? null;
$mediaActionToken = $mediaActionToken ?? null;

$questionId = (int)($question['id'] ?? 0);
$questionText = $currentLang === 'en'
    ? (string)($question['text_en'] ?? '')
    : (string)($question['text_ar'] ?? '');

$testName = $currentLang === 'en'
    ? (string)($question['test_name_en'] ?? '')
    : (string)($question['test_name_ar'] ?? '');

$categoryName = $currentLang === 'en'
    ? (string)($question['category_name_en'] ?? '')
    : (string)($question['category_name_ar'] ?? '');

$isValid = (int)($question['is_valid'] ?? 0) === 1;
$isActive = (int)($question['is_active'] ?? 0) === 1;
$isArchived = ($question['archived_at'] ?? null) !== null;
$options = is_array($question['options'] ?? null) ? $question['options'] : [];

$statusClass = $isArchived ? 'archived' : ($isActive ? 'active' : 'inactive');
$statusLabel = $isArchived
    ? trans('archived')
    : ($isActive ? trans('active') : trans('inactive'));

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'question' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.6 2.1c-.9.5-1.4 1-1.4 2M12 17h.01"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>',
        'archive' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v14H4zM3 3h18v4H3zM9 11h6"/></svg>',
        'restore' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4v6h6"/><path d="M5.5 15a7 7 0 1 0 1.2-8.8L4 10"/></svg>',
        'details' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="19" cy="18" r="2"/></svg>',
        'answers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m4 17 5-5 4 4 2-2 5 5"/></svg>',
        'upload' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg>',
        'assessment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'category' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'difficulty' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h4V9H4zM10 19h4V5h-4zM16 19h4V2h-4z"/></svg>',
        'score' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 9.5 8H4l4 4-1.5 6L12 15l5.5 3-1.5-6 4-4h-5.5Z"/></svg>',
        'type' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>',
        'options' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="6" r="1.5"/><circle cx="5" cy="12" r="1.5"/><circle cx="5" cy="18" r="1.5"/><path d="M9 6h11M9 12h11M9 18h11"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="question-show-page">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="question-alert success">
            <?= e((string)$_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="question-alert error">
            <?= e((string)$_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="question-alert info">
            <?= e((string)$_SESSION['flash_info']) ?>
        </div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>

    <section class="question-show-hero">
        <div class="question-show-heading">
            <span class="question-show-kicker">
                <?= $icon('question') ?>
                <?= e($t('ملف السؤال الإداري', 'Question Administration Profile')) ?>
            </span>

            <h1 class="question-show-title">
                <?= $questionText !== '' ? nl2br(e($questionText)) : e(trans('question_details')) ?>
            </h1>

            <div class="question-show-meta">
                <span class="question-badge <?= $isValid ? 'valid' : 'invalid' ?>">
                    <?= e(trans($isValid ? 'valid_question' : 'invalid_question')) ?>
                </span>

                <span class="question-badge <?= e($statusClass) ?>">
                    <?= e($statusLabel) ?>
                </span>

                <?php if (!empty($question['test_code'])): ?>
                    <span class="question-badge inactive">
                        <bdi dir="ltr"><?= e((string)$question['test_code']) ?></bdi>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="question-show-actions">
            <?php if ($canManageQuestions && !$isArchived): ?>
                <a href="/admin/questions/edit?id=<?= $questionId ?>" class="question-show-btn primary">
                    <?= $icon('edit') ?>
                    <?= e(trans('edit_question')) ?>
                </a>
            <?php endif; ?>

            <?php if ($canManageQuestions && $editToken !== null): ?>
                <?php if (!$isArchived): ?>
                    <form
                        action="/admin/questions/archive"
                        method="POST"
                        data-confirm="<?= e(trans('confirm_archive_question')) ?>"
                        onsubmit="return confirm(this.dataset.confirm);"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $questionId ?>">
                        <input type="hidden" name="edit_token" value="<?= e((string)$editToken) ?>">

                        <button type="submit" class="question-show-btn danger">
                            <?= $icon('archive') ?>
                            <?= e(trans('archive_question')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <form action="/admin/questions/restore" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $questionId ?>">
                        <input type="hidden" name="edit_token" value="<?= e((string)$editToken) ?>">

                        <button type="submit" class="question-show-btn success">
                            <?= $icon('restore') ?>
                            <?= e(trans('restore_question')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <a href="/admin/questions" class="question-show-btn">
                <?= $icon('back') ?>
                <?= e(trans('back_to_question_bank')) ?>
            </a>
        </div>
    </section>

    <div class="question-note">
        <?= $icon('shield') ?>
        <span>
            <?= e($t(
                'يعرض النظام بيانات السؤال وخياراته ووسائطه، مع تمييز الإجابة الصحيحة وحالة صلاحية السؤال.',
                'The system displays question data, options, and media while highlighting the correct answer and validity status.'
            )) ?>
        </span>
    </div>

    <section class="question-section">
        <div class="question-section-head">
            <span class="section-icon"><?= $icon('details') ?></span>
            <div>
                <h2><?= e($t('بيانات السؤال', 'Question Information')) ?></h2>
                <p><?= e($t('معلومات الاختبار والتصنيف والصعوبة والدرجة.', 'Assessment, category, difficulty, and score details.')) ?></p>
            </div>
        </div>

        <div class="question-section-body">
            <div class="question-details-grid">
                <article class="detail-card wide">
                    <span class="detail-icon"><?= $icon('assessment') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('assessment')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= e((string)($question['test_code'] ?? '')) ?></bdi>
                            <?= $testName !== '' ? ' — ' . e($testName) : '' ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card wide">
                    <span class="detail-icon"><?= $icon('category') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('category')) ?></span>
                        <strong class="detail-value"><?= e($categoryName !== '' ? $categoryName : '—') ?></strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('difficulty') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('difficulty')) ?></span>
                        <strong class="detail-value">
                            <?= e(trans((string)($question['difficulty'] ?? 'medium'))) ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('score') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('question_score')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= e(number_format((float)($question['score'] ?? 0), 2)) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('type') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('question_type')) ?></span>
                        <strong class="detail-value">
                            <?= e(trans((string)($question['type'] ?? 'single_choice'))) ?>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('options') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('options_count')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= (int)($question['option_count'] ?? count($options)) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('check') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('correct_options_count')) ?></span>
                        <strong class="detail-value">
                            <bdi dir="ltr"><?= (int)($question['correct_count'] ?? 0) ?></bdi>
                        </strong>
                    </div>
                </article>

                <article class="detail-card">
                    <span class="detail-icon"><?= $icon('image') ?></span>
                    <div class="detail-content">
                        <span class="detail-label"><?= e(trans('question_image')) ?></span>
                        <strong class="detail-value">
                            <?= e(trans((int)($question['has_question_image'] ?? 0) === 1 ? 'available' : 'not_available')) ?>
                        </strong>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="question-section">
        <div class="question-section-head">
            <span class="section-icon"><?= $icon('image') ?></span>
            <div>
                <h2><?= e(trans('question_image_preview')) ?></h2>
                <p><?= e($t('معاينة صورة السؤال وإدارة رفعها أو استبدالها.', 'Preview and manage the question image.')) ?></p>
            </div>
        </div>

        <div class="question-section-body">
            <div class="question-media-layout">
                <div class="media-preview">
                    <?php if (!empty($question['question_image_token'])): ?>
                        <img
                            src="/admin/questions/media?token=<?= e((string)$question['question_image_token']) ?>"
                            alt="<?= e(trans('question_image_alt')) ?>"
                            loading="lazy"
                            decoding="async"
                        >
                    <?php else: ?>
                        <div class="media-empty">
                            <?= $icon('image') ?>
                            <strong><?= e(trans('question_image_not_uploaded')) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="media-panel">
                    <h3><?= e($t('إدارة صورة السؤال', 'Manage Question Image')) ?></h3>

                    <?php if ($canManageQuestions && $mediaActionToken !== null): ?>
                        <form
                            action="/admin/questions/media/upload"
                            method="POST"
                            enctype="multipart/form-data"
                            class="media-form"
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="question_id" value="<?= $questionId ?>">
                            <input type="hidden" name="owner_type" value="question">
                            <input
                                type="hidden"
                                name="media_action_token"
                                value="<?= e((string)$mediaActionToken) ?>"
                            >

                            <input
                                class="media-file"
                                type="file"
                                name="image"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                required
                            >

                            <button type="submit" class="media-btn upload">
                                <?= $icon('upload') ?>
                                <?= e(trans(!empty($question['question_image_token']) ? 'replace_image' : 'upload_image')) ?>
                            </button>
                        </form>

                        <?php if (!empty($question['question_image_token'])): ?>
                            <form
                                action="/admin/questions/media/remove"
                                method="POST"
                                data-confirm="<?= e(trans('confirm_remove_image')) ?>"
                                onsubmit="return confirm(this.dataset.confirm);"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="question_id" value="<?= $questionId ?>">
                                <input type="hidden" name="owner_type" value="question">
                                <input
                                    type="hidden"
                                    name="media_action_token"
                                    value="<?= e((string)$mediaActionToken) ?>"
                                >

                                <button type="submit" class="media-btn danger">
                                    <?= $icon('trash') ?>
                                    <?= e(trans('remove_image')) ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <p><?= e(trans('question_image_requirements')) ?></p>
                    <?php elseif ($isArchived): ?>
                        <p><?= e(trans('archived_question_media_read_only')) ?></p>
                    <?php else: ?>
                        <p><?= e($t('لا تتوفر صلاحية لإدارة وسائط هذا السؤال.', 'You do not have permission to manage this question media.')) ?></p>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </section>

    <section class="question-section">
        <div class="question-section-head">
            <span class="section-icon"><?= $icon('answers') ?></span>
            <div>
                <h2><?= e(trans('answer_options')) ?></h2>
                <p><?= e($t('عرض الخيارات مع تمييز الإجابة الصحيحة ووسائط كل خيار.', 'View options with the correct answer and option media clearly identified.')) ?></p>
            </div>
        </div>

        <div class="question-section-body">
            <?php if ($options === []): ?>
                <div class="question-empty"><?= e(trans('no_options_configured')) ?></div>
            <?php else: ?>
                <div class="option-list">
                    <?php foreach ($options as $option): ?>
                        <?php
                        $optionText = $currentLang === 'en'
                            ? (string)($option['text_en'] ?? '')
                            : (string)($option['text_ar'] ?? '');

                        $isCorrect = (int)($option['is_correct'] ?? 0) === 1;
                        $hasOptionImage = (int)($option['has_option_image'] ?? 0) === 1;
                        $optionId = (int)($option['id'] ?? 0);
                        ?>
                        <article class="option-card <?= $isCorrect ? 'correct' : '' ?>">
                            <span class="option-key">
                                <bdi dir="ltr"><?= e((string)($option['option_key'] ?? '')) ?></bdi>
                            </span>

                            <div class="option-main">
                                <div class="option-text">
                                    <?= $optionText !== '' ? nl2br(e($optionText)) : '—' ?>
                                </div>
                            </div>

                            <div class="option-badges">
                                <?php if ($isCorrect): ?>
                                    <span class="question-badge valid">
                                        <?= $icon('check') ?>
                                        <?= e(trans('correct_answer')) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($hasOptionImage): ?>
                                    <span class="question-badge inactive">
                                        <?= $icon('image') ?>
                                        <?= e(trans('has_image')) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="option-media">
                                <?php if (!empty($option['option_image_token'])): ?>
                                    <div class="option-media-preview">
                                        <img
                                            src="/admin/questions/media?token=<?= e((string)$option['option_image_token']) ?>"
                                            alt="<?= e(trans('option_image_alt', ['key' => (string)($option['option_key'] ?? '')])) ?>"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </div>
                                <?php endif; ?>

                                <?php if ($canManageQuestions && $mediaActionToken !== null): ?>
                                    <div class="option-media-controls">
                                        <form
                                            action="/admin/questions/media/upload"
                                            method="POST"
                                            enctype="multipart/form-data"
                                            class="media-form"
                                        >
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="question_id" value="<?= $questionId ?>">
                                            <input type="hidden" name="owner_type" value="option">
                                            <input type="hidden" name="option_id" value="<?= $optionId ?>">
                                            <input
                                                type="hidden"
                                                name="media_action_token"
                                                value="<?= e((string)$mediaActionToken) ?>"
                                            >

                                            <input
                                                class="media-file"
                                                type="file"
                                                name="image"
                                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                                required
                                            >

                                            <div class="media-actions">
                                                <button type="submit" class="media-btn upload">
                                                    <?= $icon('upload') ?>
                                                    <?= e(trans(!empty($option['option_image_token']) ? 'replace_option_image' : 'upload_option_image')) ?>
                                                </button>
                                            </div>
                                        </form>

                                        <?php if (!empty($option['option_image_token'])): ?>
                                            <form
                                                action="/admin/questions/media/remove"
                                                method="POST"
                                                data-confirm="<?= e(trans('confirm_remove_image')) ?>"
                                                onsubmit="return confirm(this.dataset.confirm);"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="question_id" value="<?= $questionId ?>">
                                                <input type="hidden" name="owner_type" value="option">
                                                <input type="hidden" name="option_id" value="<?= $optionId ?>">
                                                <input
                                                    type="hidden"
                                                    name="media_action_token"
                                                    value="<?= e((string)$mediaActionToken) ?>"
                                                >

                                                <button type="submit" class="media-btn danger">
                                                    <?= $icon('trash') ?>
                                                    <?= e(trans('remove_image')) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
