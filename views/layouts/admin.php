<?php
$currentLang = $_SESSION['lang'] ?? 'ar';
if (!in_array($currentLang, ['ar', 'en'], true)) {
    $currentLang = 'ar';
}

$dir = ($currentLang === 'ar') ? 'rtl' : 'ltr';
$otherLang = ($currentLang === 'ar') ? 'en' : 'ar';
$otherLangLabel = ($currentLang === 'ar') ? 'English' : 'العربية';

$isAuthenticatedLayout = $isAuthenticatedLayout ?? false;
$adminUser = (isset($admin) && is_array($admin)) ? $admin : null;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (empty($currentPath)) {
    $currentPath = '/';
}

$safeGet = [];
if (!empty($_GET) && is_array($_GET)) {
    foreach ($_GET as $key => $value) {
        if ($key !== 'lang' && is_scalar($value) && (string)$value !== '') {
            $safeGet[$key] = (string)$value;
        }
    }
}
$safeGet['lang'] = $otherLang;
$langSwitchUrl = $currentPath . '?' . http_build_query($safeGet);

$isDashboardActive = ($currentPath === '/admin' || $currentPath === '/admin/' || $currentPath === '/admin/dashboard');
$isAdminUsersActive = ($currentPath === '/admin/admin-users' || str_starts_with($currentPath, '/admin/admin-users/'));
$isEmployeesActive = ($currentPath === '/admin/employees' || str_starts_with($currentPath, '/admin/employees/'));
$isTestsActive = ($currentPath === '/admin/tests' || str_starts_with($currentPath, '/admin/tests/'));
$isQuestionsActive = ($currentPath === '/admin/questions' || str_starts_with($currentPath, '/admin/questions/'));
$isAssignmentsActive = ($currentPath === '/admin/assignments' || str_starts_with($currentPath, '/admin/assignments/'));
$isAttemptsActive = ($currentPath === '/admin/attempts' || str_starts_with($currentPath, '/admin/attempts/'));
$isMonitoringActive = ($currentPath === '/admin/monitoring' || str_starts_with($currentPath, '/admin/monitoring/'));
$isReportsActive = ($currentPath === '/admin/reports' || str_starts_with($currentPath, '/admin/reports/'));

$adminName = trim((string)($adminUser['display_name'] ?? $adminUser['name'] ?? ''));
$adminInitials = 'AD';
if ($adminName !== '') {
    $nameParts = preg_split('/\s+/u', $adminName) ?: [];
    $initials = '';
    foreach (array_slice($nameParts, 0, 2) as $part) {
        $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
    }
    if ($initials !== '') {
        $adminInitials = function_exists('mb_strtoupper')
            ? mb_strtoupper($initials, 'UTF-8')
            : strtoupper($initials);
    }
}

$adminCssFile = dirname(__DIR__, 2) . '/public/assets/css/admin.css';
$adminCssVersion = is_file($adminCssFile) ? (string) filemtime($adminCssFile) : '1';
$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes = array_values(array_unique(array_filter($adminCssScopes, static fn ($scope): bool => is_string($scope) && preg_match('/^[a-z0-9-]+$/', $scope) === 1)));
$adminCssScopeClasses = implode(' ', $adminCssScopes);

$icon = static function (string $name): string {
    $icons = [
        'brand' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4.5 5.3v5.9c0 4.8 3.1 9.1 7.5 10.8 4.4-1.7 7.5-6 7.5-10.8V5.3L12 2Z"/><path d="m8.7 12 2.1 2.1 4.5-4.7"/></svg>',
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'admin_users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'employees' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.2A4.8 4.8 0 0 1 8.3 13h1.4a4.8 4.8 0 0 1 4.8 4.8V19"/><circle cx="17" cy="9" r="2.3"/><path d="M15.5 14.3h1.8a3.7 3.7 0 0 1 3.7 3.7V19"/></svg>',
        'tests' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h3"/><path d="m14.5 16.5 1.5 1.5 3-3"/></svg>',
        'questions' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4.5h11a3 3 0 0 1 3 3v10a2.5 2.5 0 0 1-2.5 2.5H8l-3 2v-4.5A3 3 0 0 1 2 14.5v-7a3 3 0 0 1 3-3Z"/><path d="M9 9a2.2 2.2 0 1 1 3.8 1.5c-.8.7-1.3 1.1-1.3 2M11.5 16h.01"/></svg>',
        'assignments' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8M9 2h6v4H9z"/><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 10h6M9 14h3M15 14v4M13 16h4"/></svg>',
        'attempts' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 22h8M12 18v4M6.5 11h2l1.4-3 2.3 6 1.6-3H18"/></svg>',
        'monitoring' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M7 12h2l1.3-3 2.4 6 1.5-3H18"/><path d="M8 22h8M12 18v4"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20V7"/><path d="M2 20h22"/></svg>',
        'globe' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-3"/><path d="M10 12h11M18 9l3 3-3 3"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 8 3 3 3-3"/></svg>',
    ];

    return $icons[$name] ?? '';
};
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#071426">
    <title><?= e($pageTitle ?? trans('administration')) ?> - <?= e(trans('app_name')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= e($adminCssVersion) ?>">
</head>
<body class="admin-shell<?= $adminCssScopeClasses !== '' ? ' ' . e($adminCssScopeClasses) : '' ?>">
    <a href="#admin-main-content" class="skip-link"><?= e($currentLang === 'ar' ? 'الانتقال إلى المحتوى' : 'Skip to content') ?></a>

    <header class="header-bar">
        <div class="admin-header-inner">
            <div class="brand-title">
                <a href="/admin" class="brand-link">
                    <span class="brand-mark"><?= $icon('brand') ?></span>
                    <span class="brand-copy">
                        <span class="brand-name"><?= e(trans('app_name')) ?></span>
                        <span class="brand-section"><?= e(trans('administration')) ?></span>
                    </span>
                </a>
            </div>

            <?php if ($isAuthenticatedLayout && $adminUser): ?>
                <nav class="header-nav" aria-label="<?= e($currentLang === 'ar' ? 'التنقل الرئيسي للإدارة' : 'Administration primary navigation') ?>">
                    <div class="admin-nav-group">
                        <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_dashboard')): ?>
                            <a href="/admin/dashboard" class="nav-link<?= $isDashboardActive ? ' active' : '' ?>"<?= $isDashboardActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('dashboard') ?>
                                <span><?= e(trans('dashboard')) ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (\App\Services\AuthorizationService::can($adminUser, 'manage_admins')): ?>
                            <a href="/admin/admin-users" class="nav-link<?= $isAdminUsersActive ? ' active' : '' ?>"<?= $isAdminUsersActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('admin_users') ?>
                                <span><?= e(trans('admin_accounts')) ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_employees')): ?>
                            <a href="/admin/employees" class="nav-link<?= $isEmployeesActive ? ' active' : '' ?>"<?= $isEmployeesActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('employees') ?>
                                <span><?= e(trans('employees')) ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_tests')): ?>
                            <a href="/admin/tests" class="nav-link<?= $isTestsActive ? ' active' : '' ?>"<?= $isTestsActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('tests') ?>
                                <span><?= e(trans('tests')) ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_questions')): ?>
                            <a href="/admin/questions" class="nav-link<?= $isQuestionsActive ? ' active' : '' ?>"<?= $isQuestionsActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('questions') ?>
                                <span><?= e(trans('question_bank')) ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_assignments')): ?>
                            <a href="/admin/assignments" class="nav-link<?= $isAssignmentsActive ? ' active' : '' ?>"<?= $isAssignmentsActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('assignments') ?>
                                <span><?= e(trans('assignments')) ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_attempts')): ?>
                            <a href="/admin/attempts" class="nav-link<?= $isAttemptsActive ? ' active' : '' ?>"<?= $isAttemptsActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('attempts') ?>
                                <span><?= e(trans('attempt_monitoring')) ?></span>
                            </a>

                            <a href="/admin/monitoring" class="nav-link<?= $isMonitoringActive ? ' active' : '' ?>"<?= $isMonitoringActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('monitoring') ?>
                                <span><?= e($currentLang === 'ar' ? 'مراقبة الاختبارات' : 'Test Monitoring') ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_reports')): ?>
                            <a href="/admin/reports" class="nav-link<?= $isReportsActive ? ' active' : '' ?>"<?= $isReportsActive ? ' aria-current="page"' : '' ?>>
                                <?= $icon('reports') ?>
                                <span><?= e(trans('reports')) ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>

                <div class="admin-account-actions">
                    <a href="<?= e($langSwitchUrl) ?>" class="lang-btn" aria-label="<?= e($currentLang === 'ar' ? 'Switch to English' : 'التبديل إلى العربية') ?>">
                        <?= $icon('globe') ?>
                        <span class="action-icon-label"><?= e($otherLangLabel) ?></span>
                    </a>

                    <span class="user-info">
                        <span class="admin-avatar" aria-hidden="true"><?= e($adminInitials) ?></span>
                        <span class="user-copy">
                            <strong><?= e($adminName) ?></strong>
                            <small><?= e(trans($adminUser['role'] ?? '')) ?></small>
                        </span>
                        <span class="user-chevron" aria-hidden="true"><?= $icon('chevron') ?></span>
                    </span>

                    <form action="/admin/logout" method="POST" class="admin-inline-cccfa4560d">
                        <?= csrf_field() ?>
                        <button type="submit" class="logout-btn">
                            <?= $icon('logout') ?>
                            <span class="action-label"><?= e(trans('sign_out')) ?></span>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-account-actions admin-inline-8c5865f3a9">
                    <a href="<?= e($langSwitchUrl) ?>" class="lang-btn">
                        <?= $icon('globe') ?>
                        <span><?= e($otherLangLabel) ?></span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main id="admin-main-content" class="admin-main-wrapper <?= $isAuthenticatedLayout ? 'admin-dashboard-mode' : 'admin-login-mode' ?>">
        <?= $content ?? '' ?>
    </main>

    <footer class="footer-text">
        &copy; <?= date('Y') ?> <?= e(trans('organization_name')) ?>. <?= e(trans('all_rights_reserved')) ?>
    </footer>
</body>
</html>
