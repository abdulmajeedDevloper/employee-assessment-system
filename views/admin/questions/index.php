<?php
$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-questions-index';

$pageTitle = trans('question_bank');
$isAuthenticatedLayout = true;
$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$filters = $filters ?? [
    'q' => '',
    'test_id' => '',
    'category_id' => '',
    'difficulty' => 'all',
    'status' => 'all',
    'validity' => 'all',
];

$tests = $tests ?? [];
$categories = $categories ?? [];
$results = $results ?? [
    'items' => [],
    'total' => 0,
    'page' => 1,
    'total_pages' => 1,
];

$items = $results['items'] ?? [];
$totalQuestions = (int)($results['total'] ?? count($items));
$currentPage = max(1, (int)($results['page'] ?? 1));
$totalPages = max(1, (int)($results['total_pages'] ?? 1));
$canManageQuestions = $canManageQuestions ?? false;

$activeFiltersCount = 0;
if (trim((string)($filters['q'] ?? '')) !== '') {
    $activeFiltersCount++;
}
if ((int)($filters['test_id'] ?? 0) > 0) {
    $activeFiltersCount++;
}
if ((int)($filters['category_id'] ?? 0) > 0) {
    $activeFiltersCount++;
}
if (($filters['difficulty'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}
if (($filters['status'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}
if (($filters['validity'] ?? 'all') !== 'all') {
    $activeFiltersCount++;
}

ob_start();
?>

<div class="question-bank-page">
    <section class="question-page-header">
        <div class="question-page-heading">
            <h1 class="question-page-title"><?= e(trans('question_bank')) ?></h1>
            <p class="question-page-subtitle">
                <?= e($isArabic
                    ? 'إدارة الأسئلة والتصنيفات ومستويات الصعوبة والتحقق من صلاحية كل سؤال قبل استخدامه في الاختبارات.'
                    : 'Manage questions, categories, difficulty levels, and validity before using questions in assessments.') ?>
            </p>
        </div>

        <?php if ($canManageQuestions): ?>
            <div class="question-header-actions">
                <a href="/admin/questions/create" class="question-primary-btn">
                    <span aria-hidden="true">+</span>
                    <span><?= e(trans('create_question')) ?></span>
                </a>

                <a href="/admin/questions/categories/create" class="question-secondary-btn">
                    <?= e(trans('create_question_category')) ?>
                </a>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="question-alert success">
            <?= e($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="question-alert error">
            <?= e($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_info'])): ?>
        <div class="question-alert info">
            <?= e($_SESSION['flash_info']) ?>
        </div>
        <?php unset($_SESSION['flash_info']); ?>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="question-alert error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <section class="question-filter-card">
        <div class="question-section-heading">
            <h2 class="question-section-title">
                <?= e($isArabic ? 'البحث والتصفية' : 'Search and Filters') ?>
            </h2>

            <?php if ($activeFiltersCount > 0): ?>
                <span class="question-filter-count">
                    <?= e($isArabic
                        ? 'عوامل التصفية النشطة: ' . $activeFiltersCount
                        : 'Active filters: ' . $activeFiltersCount) ?>
                </span>
            <?php endif; ?>
        </div>

        <form action="/admin/questions" method="GET">
            <?php if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)): ?>
                <input type="hidden" name="lang" value="<?= e($_GET['lang']) ?>">
            <?php endif; ?>

            <div class="question-filter-grid">
                <div class="question-field question-filter-search">
                    <label class="question-label" for="question-search"><?= e(trans('search')) ?></label>
                    <input
                        id="question-search"
                        name="q"
                        type="search"
                        class="question-control"
                        maxlength="150"
                        value="<?= e((string)($filters['q'] ?? '')) ?>"
                        placeholder="<?= e(trans('search_questions')) ?>"
                        autocomplete="off"
                    >
                </div>

                <div class="question-field">
                    <label class="question-label" for="question-test"><?= e(trans('assessment')) ?></label>
                    <select id="question-test" name="test_id" class="question-control">
                        <option value=""><?= e(trans('all_assessments')) ?></option>
                        <?php foreach ($tests as $test): ?>
                            <?php $testLabel = $currentLang === 'en' ? $test['name_en'] : $test['name_ar']; ?>
                            <option
                                value="<?= (int)$test['id'] ?>"
                                <?= (int)($filters['test_id'] ?? 0) === (int)$test['id'] ? 'selected' : '' ?>
                            >
                                <?= e($test['code'] . ' — ' . $testLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="question-field">
                    <label class="question-label" for="question-category"><?= e(trans('category')) ?></label>
                    <select id="question-category" name="category_id" class="question-control">
                        <option value=""><?= e(trans('all_categories')) ?></option>
                        <?php foreach ($categories as $category): ?>
                            <?php $categoryLabel = $currentLang === 'en' ? $category['name_en'] : $category['name_ar']; ?>
                            <option
                                value="<?= (int)$category['id'] ?>"
                                <?= (int)($filters['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>
                            >
                                <?= e($categoryLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="question-field">
                    <label class="question-label" for="question-difficulty"><?= e(trans('difficulty')) ?></label>
                    <select id="question-difficulty" name="difficulty" class="question-control">
                        <?php foreach (['all', 'easy', 'medium', 'hard'] as $value): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= ($filters['difficulty'] ?? 'all') === $value ? 'selected' : '' ?>
                            >
                                <?= e(trans($value === 'all' ? 'all_difficulties' : $value)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="question-field">
                    <label class="question-label" for="question-status"><?= e(trans('status')) ?></label>
                    <select id="question-status" name="status" class="question-control">
                        <?php foreach (['all', 'active', 'inactive', 'archived'] as $value): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= ($filters['status'] ?? 'all') === $value ? 'selected' : '' ?>
                            >
                                <?= e(trans($value === 'all' ? 'all_statuses' : $value)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="question-field">
                    <label class="question-label" for="question-validity"><?= e(trans('question_validity')) ?></label>
                    <select id="question-validity" name="validity" class="question-control">
                        <?php foreach (['all', 'valid', 'invalid'] as $value): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= ($filters['validity'] ?? 'all') === $value ? 'selected' : '' ?>
                            >
                                <?= e(trans($value === 'all' ? 'all_validity_states' : $value . '_question')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="question-filter-actions">
                <button type="submit" class="question-filter-btn">
                    <?= e(trans('filter')) ?>
                </button>

                <a href="/admin/questions" class="question-reset-btn">
                    <?= e(trans('reset_filters')) ?>
                </a>
            </div>
        </form>
    </section>

    <section class="question-list-card">
        <div class="question-section-heading">
            <h2 class="question-section-title">
                <?= e($isArabic ? 'سجل الأسئلة' : 'Question Records') ?>
            </h2>

            <span class="question-count">
                <?= e(trans('questions_found', ['count' => (string)$totalQuestions])) ?>
            </span>
        </div>

        <?php if (empty($items)): ?>
            <div class="question-empty">
                <div class="question-empty-icon" aria-hidden="true">?</div>
                <strong><?= e(trans('no_questions_found')) ?></strong>
            </div>
        <?php else: ?>
            <div class="question-table-wrap">
                <table class="question-table">
                    <thead>
                        <tr>
                            <th><?= e(trans('question')) ?></th>
                            <th><?= e(trans('assessment')) ?></th>
                            <th><?= e(trans('category')) ?></th>
                            <th><?= e(trans('difficulty')) ?></th>
                            <th><?= e(trans('options')) ?></th>
                            <th><?= e(trans('status')) ?></th>
                            <th><?= e(trans('question_validity')) ?></th>
                            <th><?= e(trans('actions')) ?></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $questionText = $currentLang === 'en'
                                ? (string)($item['text_en'] ?? '')
                                : (string)($item['text_ar'] ?? '');

                            $testName = $currentLang === 'en'
                                ? (string)($item['test_name_en'] ?? '')
                                : (string)($item['test_name_ar'] ?? '');

                            $categoryName = $currentLang === 'en'
                                ? (string)($item['category_name_en'] ?? '')
                                : (string)($item['category_name_ar'] ?? '');

                            $difficulty = (string)($item['difficulty'] ?? 'medium');
                            ?>
                            <tr>
                                <td class="question-main-cell">
                                    <div class="question-text" title="<?= e($questionText) ?>">
                                        <?= e($questionText) ?>
                                    </div>

                                    <div class="question-meta">
                                        <span class="question-id"><bdi dir="ltr">#<?= (int)$item['id'] ?></bdi></span>
                                        <span>
                                            <?= e($isArabic ? 'الدرجة' : 'Score') ?>:
                                            <bdi dir="ltr"><?= e(number_format((float)$item['score'], 2)) ?></bdi>
                                        </span>
                                    </div>
                                </td>

                                <td class="question-test-cell">
                                    <span class="question-test-code">
                                        <bdi dir="ltr"><?= e((string)$item['test_code']) ?></bdi>
                                    </span>
                                    <div><?= e($testName) ?></div>
                                </td>

                                <td class="question-category-cell">
                                    <?= e($categoryName !== '' ? $categoryName : '—') ?>
                                </td>

                                <td>
                                    <span class="question-difficulty <?= e($difficulty) ?>">
                                        <?= e(trans($difficulty)) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="question-options-count">
                                        <bdi dir="ltr"><?= (int)$item['option_count'] ?></bdi>
                                    </span>
                                </td>

                                <td>
                                    <?php if (($item['archived_at'] ?? null) !== null): ?>
                                        <span class="question-badge archived"><?= e(trans('archived')) ?></span>
                                    <?php elseif ((int)($item['is_active'] ?? 0) === 1): ?>
                                        <span class="question-badge active"><?= e(trans('active')) ?></span>
                                    <?php else: ?>
                                        <span class="question-badge inactive"><?= e(trans('inactive')) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ((int)($item['is_valid'] ?? 0) === 1): ?>
                                        <span class="question-badge valid"><?= e(trans('valid_question')) ?></span>
                                    <?php else: ?>
                                        <span class="question-badge invalid"><?= e(trans('invalid_question')) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="question-actions-cell">
                                    <div class="question-row-actions">
                                        <a
                                            href="/admin/questions/view?id=<?= (int)$item['id'] ?>"
                                            class="question-action-btn details"
                                        >
                                            <?= e(trans('details')) ?>
                                        </a>

                                        <?php if ($canManageQuestions && ($item['archived_at'] ?? null) === null): ?>
                                            <a
                                                href="/admin/questions/edit?id=<?= (int)$item['id'] ?>"
                                                class="question-action-btn edit"
                                            >
                                                <?= e(trans('edit')) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="question-pagination" aria-label="<?= e(trans('pagination')) ?>">
                <?php
                $baseQuery = $_GET;
                unset($baseQuery['page']);
                ?>

                <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                    <?php
                    $pageQuery = $baseQuery;
                    $pageQuery['page'] = $pageNumber;
                    ?>
                    <a
                        class="question-page-btn<?= $pageNumber === $currentPage ? ' active' : '' ?>"
                        href="/admin/questions?<?= e(http_build_query($pageQuery)) ?>"
                        <?= $pageNumber === $currentPage ? 'aria-current="page"' : '' ?>
                    >
                        <?= $pageNumber ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
