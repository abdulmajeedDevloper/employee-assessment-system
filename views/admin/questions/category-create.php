<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-questions-category-create';


$pageTitle = trans('create_question_category');
$isAuthenticatedLayout = true;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$tests = is_array($tests ?? null) ? $tests : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

$selectedTestId = (int)($old['test_id'] ?? ($_GET['test_id'] ?? 0));

$value = static function (string $key, mixed $default = '') use ($old): string {
    return e((string)($old[$key] ?? $default));
};

$cancelUrl = '/admin/questions/create';
if ($selectedTestId > 0) {
    $cancelUrl .= '?test_id=' . $selectedTestId;
}

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'category' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'assessment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'language' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/></svg>',
        'cancel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
    ];

    return $icons[$name] ?? '';
};

ob_start();
?>

<div class="category-create-page">
    <section class="category-create-hero">
        <div class="category-create-heading">
            <span class="category-create-kicker">
                <?= $icon('category') ?>
                <?= e($text('تنظيم بنك الأسئلة', 'Question Bank Organization')) ?>
            </span>

            <h1 class="category-create-title">
                <?= e(trans('create_question_category')) ?>
            </h1>

            <p class="category-create-subtitle">
                <?= e(trans('question_category_description')) ?>
            </p>
        </div>

        <a href="<?= e($cancelUrl) ?>" class="category-create-btn">
            <?= $icon('back') ?>
            <?= e($text('العودة إلى إنشاء السؤال', 'Back to Question Creation')) ?>
        </a>
    </section>

    <div class="category-note">
        <?= $icon('shield') ?>
        <span>
            <?= e($text(
                'يرتبط كل تصنيف باختبار واحد، ويُستخدم لتنظيم الأسئلة وتسهيل البحث والتصفية.',
                'Each category belongs to one assessment and helps organize, search, and filter questions.'
            )) ?>
        </span>
    </div>

    <form
        action="/admin/questions/categories/create"
        method="POST"
        class="category-form"
        novalidate
    >
        <?= csrf_field() ?>

        <section class="category-section">
            <div class="category-section-head">
                <span class="section-icon"><?= $icon('category') ?></span>
                <div>
                    <h2><?= e($text('بيانات التصنيف', 'Category Information')) ?></h2>
                    <p>
                        <?= e($text(
                            'حدد الاختبار ثم أدخل اسم التصنيف بالعربية والإنجليزية.',
                            'Select the assessment, then enter the category name in Arabic and English.'
                        )) ?>
                    </p>
                </div>
            </div>

            <div class="category-section-body">
                <div class="category-grid">
                    <div class="category-field full">
                        <label for="test_id" class="category-label">
                            <span class="field-icon"><?= $icon('assessment') ?></span>
                            <?= e(trans('assessment')) ?><span class="required-star">*</span>
                        </label>

                        <select
                            id="test_id"
                            name="test_id"
                            class="category-control"
                            required
                            aria-invalid="<?= !empty($errors['test_id']) ? 'true' : 'false' ?>"
                        >
                            <option value=""><?= e(trans('select_assessment')) ?></option>

                            <?php foreach ($tests as $test): ?>
                                <?php
                                $label = $currentLang === 'en'
                                    ? (string)($test['name_en'] ?? '')
                                    : (string)($test['name_ar'] ?? '');
                                ?>
                                <option
                                    value="<?= (int)($test['id'] ?? 0) ?>"
                                    <?= $selectedTestId === (int)($test['id'] ?? 0) ? 'selected' : '' ?>
                                >
                                    <?= e((string)($test['code'] ?? '') . ' — ' . $label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <span class="category-help">
                            <?= e($text(
                                'سيظهر هذا التصنيف فقط عند إدارة أسئلة الاختبار المحدد.',
                                'This category will be available only for the selected assessment.'
                            )) ?>
                        </span>

                        <?php if (!empty($errors['test_id'])): ?>
                            <span class="category-error"><?= e((string)$errors['test_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="category-field">
                        <label for="name_ar" class="category-label">
                            <span class="field-icon"><?= $icon('language') ?></span>
                            <?= e(trans('category_name_ar')) ?><span class="required-star">*</span>
                        </label>

                        <input
                            id="name_ar"
                            name="name_ar"
                            maxlength="255"
                            class="category-control"
                            value="<?= $value('name_ar') ?>"
                            required
                            autocomplete="off"
                            aria-invalid="<?= !empty($errors['name_ar']) ? 'true' : 'false' ?>"
                        >

                        <?php if (!empty($errors['name_ar'])): ?>
                            <span class="category-error"><?= e((string)$errors['name_ar']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="category-field">
                        <label for="name_en" class="category-label">
                            <span class="field-icon"><?= $icon('language') ?></span>
                            <?= e(trans('category_name_en')) ?><span class="required-star">*</span>
                        </label>

                        <input
                            id="name_en"
                            name="name_en"
                            maxlength="255"
                            dir="ltr"
                            class="category-control ltr"
                            value="<?= $value('name_en') ?>"
                            required
                            autocomplete="off"
                            aria-invalid="<?= !empty($errors['name_en']) ? 'true' : 'false' ?>"
                        >

                        <?php if (!empty($errors['name_en'])): ?>
                            <span class="category-error"><?= e((string)$errors['name_en']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="category-form-footer">
            <div class="category-form-footer-copy">
                <strong><?= e($text('جاهز لإنشاء التصنيف؟', 'Ready to create the category?')) ?></strong>
                <small>
                    <?= e($text(
                        'راجع الاختبار والأسماء قبل الحفظ.',
                        'Review the assessment and category names before saving.'
                    )) ?>
                </small>
            </div>

            <div class="category-form-actions">
                <a href="<?= e($cancelUrl) ?>" class="category-create-btn">
                    <?= $icon('cancel') ?>
                    <?= e(trans('cancel')) ?>
                </a>

                <button type="submit" class="category-create-btn primary">
                    <?= $icon('save') ?>
                    <?= e(trans('create_category')) ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
