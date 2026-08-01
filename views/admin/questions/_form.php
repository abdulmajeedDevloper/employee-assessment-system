<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-questions-form';


$isEdit = $isEdit ?? false;

$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$tests = is_array($tests ?? null) ? $tests : [];
$categories = is_array($categories ?? null) ? $categories : [];
$question = is_array($question ?? null) ? $question : [];

$formAction = $isEdit ? '/admin/questions/edit' : '/admin/questions/create';
$questionId = (int)($question['id'] ?? 0);
$cancelUrl = $isEdit
    ? '/admin/questions/view?id=' . $questionId
    : '/admin/questions';

$optionRows = is_array($old['options'] ?? null)
    ? array_values($old['options'])
    : [];

while (count($optionRows) < 2) {
    $optionRows[] = ['text_ar' => '', 'text_en' => ''];
}

$selectedTestId = (string)($old['test_id'] ?? $question['test_id'] ?? '');
$selectedCategoryId = (string)($old['category_id'] ?? $question['category_id'] ?? '');
$selectedDifficulty = (string)($old['difficulty'] ?? $question['difficulty'] ?? 'medium');
$selectedScore = (string)($old['score'] ?? $question['score'] ?? '1.00');
$selectedActive = (int)($old['is_active'] ?? $question['is_active'] ?? 0) === 1;
$selectedCorrectOption = (string)($old['correct_option'] ?? '');

$categoryCreateUrl = '/admin/questions/categories/create';
if ($selectedTestId !== '') {
    $categoryCreateUrl .= '?test_id=' . (int)$selectedTestId;
}

$icon = static function (string $name): string {
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
        'question' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.6 2.1c-.9.5-1.4 1-1.4 2M12 17h.01"/></svg>',
        'basic' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="19" cy="18" r="2"/></svg>',
        'answers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.38.3.73.6 1 .3.27.68.4 1.1.4H21v4h-.09a1.7 1.7 0 0 0-1.1.4c-.27.3-.47.64-.6 1Z"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/></svg>',
        'cancel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.8 12 2.1 2.1 4.4-4.6"/></svg>',
        'category' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    ];

    return $icons[$name] ?? '';
};
?>

<div class="question-form-page">
    <section class="question-form-hero">
        <div class="question-form-heading">
            <span class="question-form-kicker">
                <?= $icon('question') ?>
                <?= e($text('إدارة بنك الأسئلة', 'Question Bank Management')) ?>
            </span>

            <h1 class="question-form-title">
                <?= e(trans($isEdit ? 'edit_question' : 'create_question')) ?>
            </h1>

            <p class="question-form-subtitle">
                <?= e($text(
                    $isEdit
                        ? 'عدّل نص السؤال وخياراته وإعداداته مع الحفاظ على الارتباط الصحيح بالاختبار والتصنيف.'
                        : 'أضف سؤال اختيار من متعدد وحدد الاختبار والتصنيف والإجابة الصحيحة والدرجة.',
                    $isEdit
                        ? 'Update the question text, options, and settings while preserving its assessment and category links.'
                        : 'Create a single-choice question and define its assessment, category, correct answer, and score.'
                )) ?>
            </p>
        </div>

        <a href="<?= e($cancelUrl) ?>" class="question-form-btn">
            <?= $icon('back') ?>
            <?= e($isEdit
                ? $text('العودة إلى تفاصيل السؤال', 'Back to Question Details')
                : $text('العودة إلى بنك الأسئلة', 'Back to Question Bank')) ?>
        </a>
    </section>

    <?php if (!empty($formMessage)): ?>
        <div class="question-message"><?= e((string)$formMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['options'])): ?>
        <div class="question-error-summary"><?= e((string)$errors['options']) ?></div>
    <?php endif; ?>

    <div class="question-note">
        <?= $icon('shield') ?>
        <span>
            <?= e($text(
                'يجب أن يحتوي السؤال على خيارين على الأقل، مع تحديد إجابة صحيحة واحدة فقط.',
                'Each question must contain at least two options with exactly one correct answer.'
            )) ?>
        </span>
    </div>

    <form action="<?= e($formAction) ?>" method="POST" class="question-form" novalidate>
        <?= csrf_field() ?>

        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $questionId ?>">
            <input type="hidden" name="edit_token" value="<?= e((string)($editToken ?? '')) ?>">
            <input type="hidden" name="test_id" value="<?= e($selectedTestId) ?>">
        <?php endif; ?>

        <input type="hidden" name="type" value="single_choice">

        <section class="question-section">
            <div class="question-section-head">
                <div class="question-section-title-group">
                    <span class="section-icon"><?= $icon('basic') ?></span>
                    <div>
                        <h2><?= e($text('بيانات السؤال', 'Question Information')) ?></h2>
                        <p><?= e($text('حدد الاختبار والتصنيف ثم اكتب السؤال باللغتين.', 'Select the assessment and category, then enter the question in both languages.')) ?></p>
                    </div>
                </div>
            </div>

            <div class="question-section-body">
                <div class="question-grid question-primary-grid">
                    <div class="question-field">
                        <label for="test_id_display" class="question-label">
                            <?= e(trans('assessment')) ?><span class="required-star">*</span>
                        </label>

                        <?php if ($isEdit): ?>
                            <?php
                            $testLabel = '';
                            foreach ($tests as $test) {
                                if ((int)($test['id'] ?? 0) === (int)$selectedTestId) {
                                    $localizedTestName = $currentLang === 'en'
                                        ? (string)($test['name_en'] ?? '')
                                        : (string)($test['name_ar'] ?? '');

                                    $testLabel = (string)($test['code'] ?? '') . ' — ' . $localizedTestName;
                                    break;
                                }
                            }
                            ?>

                            <input
                                id="test_id_display"
                                class="question-control"
                                value="<?= e($testLabel) ?>"
                                disabled
                            >

                            <span class="question-help">
                                <?= e($text(
                                    'لا يمكن تغيير الاختبار المرتبط عند تعديل السؤال.',
                                    'The linked assessment cannot be changed while editing the question.'
                                )) ?>
                            </span>
                        <?php else: ?>
                            <select
                                id="test_id_display"
                                name="test_id"
                                class="question-control question-primary-select"
                                required
                                aria-invalid="<?= !empty($errors['test_id']) ? 'true' : 'false' ?>"
                            >
                                <option value=""><?= e(trans('select_assessment')) ?></option>

                                <?php foreach ($tests as $test): ?>
                                    <?php
                                    $localizedTestName = $currentLang === 'en'
                                        ? (string)($test['name_en'] ?? '')
                                        : (string)($test['name_ar'] ?? '');
                                    ?>
                                    <option
                                        value="<?= (int)($test['id'] ?? 0) ?>"
                                        <?= (int)$selectedTestId === (int)($test['id'] ?? 0) ? 'selected' : '' ?>
                                    >
                                        <?= e((string)($test['code'] ?? '') . ' — ' . $localizedTestName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if (!empty($errors['test_id'])): ?>
                            <span class="question-error"><?= e((string)$errors['test_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="question-field">
                        <label for="category_id" class="question-label">
                            <?= e(trans('category')) ?><span class="required-star">*</span>
                        </label>

                        <select
                            id="category_id"
                            name="category_id"
                            class="question-control question-primary-select"
                            required
                            aria-invalid="<?= !empty($errors['category_id']) ? 'true' : 'false' ?>"
                        >
                            <option value=""><?= e(trans('select_category')) ?></option>

                            <?php foreach ($categories as $category): ?>
                                <?php
                                $localizedCategoryName = $currentLang === 'en'
                                    ? (string)($category['name_en'] ?? '')
                                    : (string)($category['name_ar'] ?? '');
                                ?>
                                <option
                                    value="<?= (int)($category['id'] ?? 0) ?>"
                                    data-test-id="<?= (int)($category['test_id'] ?? 0) ?>"
                                    <?= (int)$selectedCategoryId === (int)($category['id'] ?? 0) ? 'selected' : '' ?>
                                >
                                    <?= e($localizedCategoryName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if (!empty($errors['category_id'])): ?>
                            <span class="question-error"><?= e((string)$errors['category_id']) ?></span>
                        <?php endif; ?>

                        <div class="category-actions">
                            <a
                                id="create-category-link"
                                href="<?= e($categoryCreateUrl) ?>"
                                class="category-create-link"
                            >
                                <?= $icon('category') ?>
                                <?= e(trans('create_question_category')) ?>
                            </a>
                        </div>
                    </div>

                    <div class="question-field full">
                        <label for="text_ar" class="question-label">
                            <?= e(trans('question_text_ar')) ?><span class="required-star">*</span>
                        </label>

                        <textarea
                            id="text_ar"
                            name="text_ar"
                            class="question-control"
                            maxlength="5000"
                            required
                            aria-invalid="<?= !empty($errors['text_ar']) ? 'true' : 'false' ?>"
                        ><?= e((string)($old['text_ar'] ?? $question['text_ar'] ?? '')) ?></textarea>

                        <?php if (!empty($errors['text_ar'])): ?>
                            <span class="question-error"><?= e((string)$errors['text_ar']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="question-field full">
                        <label for="text_en" class="question-label">
                            <?= e(trans('question_text_en')) ?><span class="required-star">*</span>
                        </label>

                        <textarea
                            id="text_en"
                            name="text_en"
                            class="question-control"
                            maxlength="5000"
                            dir="ltr"
                            required
                            aria-invalid="<?= !empty($errors['text_en']) ? 'true' : 'false' ?>"
                        ><?= e((string)($old['text_en'] ?? $question['text_en'] ?? '')) ?></textarea>

                        <?php if (!empty($errors['text_en'])): ?>
                            <span class="question-error"><?= e((string)$errors['text_en']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="question-section">
            <div class="question-section-head">
                <div class="question-section-title-group">
                    <span class="section-icon"><?= $icon('settings') ?></span>
                    <div>
                        <h2><?= e($text('إعدادات السؤال', 'Question Settings')) ?></h2>
                        <p><?= e($text('حدد مستوى الصعوبة والدرجة وحالة تفعيل السؤال.', 'Choose the difficulty, score, and activation status.')) ?></p>
                    </div>
                </div>
            </div>

            <div class="question-section-body">
                <div class="question-grid">
                    <div class="question-field">
                        <label for="difficulty" class="question-label">
                            <?= e(trans('difficulty')) ?>
                        </label>

                        <select id="difficulty" name="difficulty" class="question-control">
                            <?php foreach (['easy', 'medium', 'hard'] as $difficulty): ?>
                                <option
                                    value="<?= e($difficulty) ?>"
                                    <?= $selectedDifficulty === $difficulty ? 'selected' : '' ?>
                                >
                                    <?= e(trans($difficulty)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if (!empty($errors['difficulty'])): ?>
                            <span class="question-error"><?= e((string)$errors['difficulty']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="question-field">
                        <label for="score" class="question-label">
                            <?= e(trans('question_score')) ?><span class="required-star">*</span>
                        </label>

                        <input
                            id="score"
                            name="score"
                            class="question-control"
                            inputmode="decimal"
                            value="<?= e($selectedScore) ?>"
                            required
                            dir="ltr"
                            aria-invalid="<?= !empty($errors['score']) ? 'true' : 'false' ?>"
                        >

                        <?php if (!empty($errors['score'])): ?>
                            <span class="question-error"><?= e((string)$errors['score']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="question-field full">
                        <label class="question-status-card">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                <?= $selectedActive ? 'checked' : '' ?>
                            >

                            <span>
                                <strong><?= e(trans('activate_question')) ?></strong>
                                <small>
                                    <?= e($text(
                                        'السؤال النشط يمكن استخدامه ضمن محاولات الاختبار.',
                                        'An active question can be used in assessment attempts.'
                                    )) ?>
                                </small>
                            </span>
                        </label>

                        <?php if (!empty($errors['is_active'])): ?>
                            <span class="question-error"><?= e((string)$errors['is_active']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="question-section">
            <div class="question-section-head">
                <div class="question-section-title-group">
                    <span class="section-icon"><?= $icon('answers') ?></span>
                    <div>
                        <h2><?= e(trans('answer_options')) ?></h2>
                        <p><?= e(trans('answer_options_help')) ?></p>
                    </div>
                </div>

                <button type="button" id="add-option" class="option-add-btn">
                    <?= $icon('plus') ?>
                    <?= e(trans('add_option')) ?>
                </button>
            </div>

            <div class="question-section-body">
                <?php if (!empty($errors['correct_option'])): ?>
                    <div class="question-error admin-inline-c5b093a5ac">
                        <?= e((string)$errors['correct_option']) ?>
                    </div>
                <?php endif; ?>

                <div id="option-editor" class="options-editor">
                    <?php foreach ($optionRows as $index => $option): ?>
                        <article class="option-row" data-option-row>
                            <label class="option-selector">
                                <span data-option-letter><?= e(chr(65 + $index)) ?></span>

                                <input
                                    type="radio"
                                    name="correct_option"
                                    value="<?= $index ?>"
                                    <?= $selectedCorrectOption === (string)$index ? 'checked' : '' ?>
                                    aria-label="<?= e(trans('correct_answer')) ?>"
                                >

                                <small><?= e(trans('correct_answer')) ?></small>
                            </label>

                            <div class="question-field">
                                <label class="question-label">
                                    <?= e(trans('option_text_ar')) ?><span class="required-star">*</span>
                                </label>

                                <textarea
                                    class="question-control"
                                    name="options[<?= $index ?>][text_ar]"
                                    maxlength="2000"
                                    required
                                    aria-invalid="<?= !empty($errors['options.' . $index . '.text_ar']) ? 'true' : 'false' ?>"
                                ><?= e((string)($option['text_ar'] ?? '')) ?></textarea>

                                <?php if (!empty($errors['options.' . $index . '.text_ar'])): ?>
                                    <span class="question-error">
                                        <?= e((string)$errors['options.' . $index . '.text_ar']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="question-field">
                                <label class="question-label">
                                    <?= e(trans('option_text_en')) ?><span class="required-star">*</span>
                                </label>

                                <textarea
                                    class="question-control"
                                    name="options[<?= $index ?>][text_en]"
                                    maxlength="2000"
                                    dir="ltr"
                                    required
                                    aria-invalid="<?= !empty($errors['options.' . $index . '.text_en']) ? 'true' : 'false' ?>"
                                ><?= e((string)($option['text_en'] ?? '')) ?></textarea>

                                <?php if (!empty($errors['options.' . $index . '.text_en'])): ?>
                                    <span class="question-error">
                                        <?= e((string)$errors['options.' . $index . '.text_en']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="option-remove" data-remove-option>
                                <?= $icon('trash') ?>
                                <?= e(trans('remove')) ?>
                            </button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <div class="question-form-footer">
            <div class="question-form-footer-copy">
                <strong>
                    <?= e($text(
                        $isEdit ? 'جاهز لحفظ التعديلات؟' : 'جاهز لإضافة السؤال؟',
                        $isEdit ? 'Ready to save changes?' : 'Ready to create the question?'
                    )) ?>
                </strong>

                <small>
                    <?= e($text(
                        'راجع نص السؤال والخيارات والإجابة الصحيحة قبل الحفظ.',
                        'Review the question text, options, and correct answer before saving.'
                    )) ?>
                </small>
            </div>

            <div class="question-form-actions">
                <a href="<?= e($cancelUrl) ?>" class="question-form-btn">
                    <?= $icon('cancel') ?>
                    <?= e(trans('cancel')) ?>
                </a>

                <button type="submit" class="question-form-btn primary">
                    <?= $icon('save') ?>
                    <?= e(trans($isEdit ? 'save_changes' : 'create_question')) ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const editor = document.getElementById('option-editor');
    const addButton = document.getElementById('add-option');
    const testSelect = document.getElementById('test_id_display');
    const categorySelect = document.getElementById('category_id');
    const createCategoryLink = document.getElementById('create-category-link');
    const scoreInput = document.getElementById('score');

    if (!editor || !addButton) {
        return;
    }

    const renumber = function () {
        const rows = Array.from(editor.querySelectorAll('[data-option-row]'));

        rows.forEach(function (row, index) {
            const letter = row.querySelector('[data-option-letter]');
            const radio = row.querySelector('input[type="radio"]');
            const textareas = row.querySelectorAll('textarea');
            const removeButton = row.querySelector('[data-remove-option]');

            if (letter) {
                letter.textContent = String.fromCharCode(65 + index);
            }

            if (radio) {
                radio.value = String(index);
            }

            textareas.forEach(function (field, fieldIndex) {
                field.name = 'options[' + index + '][' + (fieldIndex === 0 ? 'text_ar' : 'text_en') + ']';
            });

            if (removeButton) {
                removeButton.disabled = rows.length <= 2;
            }
        });

        addButton.disabled = rows.length >= 8;
    };

    const addOption = function () {
        const rows = editor.querySelectorAll('[data-option-row]');

        if (rows.length >= 8) {
            return;
        }

        const template = rows[rows.length - 1].cloneNode(true);

        template.querySelectorAll('textarea').forEach(function (field) {
            field.value = '';
            field.removeAttribute('aria-invalid');
        });

        const radio = template.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = false;
        }

        template.querySelectorAll('.question-error').forEach(function (node) {
            node.remove();
        });

        editor.appendChild(template);
        renumber();
    };

    editor.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-option]');

        if (!button) {
            return;
        }

        const rows = editor.querySelectorAll('[data-option-row]');

        if (rows.length <= 2) {
            return;
        }

        const row = button.closest('[data-option-row]');

        if (row) {
            row.remove();
            renumber();
        }
    });

    addButton.addEventListener('click', addOption);

    const updateCategoryCreateLink = function () {
        if (!createCategoryLink || !testSelect || testSelect.disabled) {
            return;
        }

        const testId = testSelect.value;
        createCategoryLink.href = '/admin/questions/categories/create'
            + (testId !== '' ? '?test_id=' + encodeURIComponent(testId) : '');
    };

    const filterCategories = function () {
        if (!testSelect || !categorySelect || testSelect.disabled) {
            return;
        }

        const testId = testSelect.value;

        Array.from(categorySelect.options).forEach(function (option, index) {
            if (index === 0) {
                return;
            }

            const visible = testId !== '' && option.dataset.testId === testId;

            option.hidden = !visible;
            option.disabled = !visible;

            if (!visible && option.selected) {
                categorySelect.value = '';
            }
        });

        updateCategoryCreateLink();
    };

    if (testSelect && !testSelect.disabled) {
        testSelect.addEventListener('change', filterCategories);
        filterCategories();
    }

    if (scoreInput) {
        scoreInput.addEventListener('input', function () {
            let value = this.value.replace(/[^\d.]/g, '');
            const parts = value.split('.');

            if (parts.length > 2) {
                value = parts.shift() + '.' + parts.join('');
            }

            this.value = value;
        });
    }

    renumber();
});
</script>