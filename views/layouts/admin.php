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
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? trans('administration')) ?> - <?= e(trans('app_name')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        /* Scoped Admin Layout Styles */
        .admin-main-wrapper {
            flex: 1;
            width: 100%;
        }

        .header-nav {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .admin-nav-group {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(59, 130, 246, 0.5);
            color: #ffffff;
        }

        .nav-link:focus-visible {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.4);
        }

        .nav-link.active {
            background: rgba(59, 130, 246, 0.25);
            border-color: rgba(59, 130, 246, 0.5);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Unauthenticated / Login Mode */

        .admin-main-wrapper.admin-login-mode {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
            min-height: calc(100vh - 140px);
        }

        /* Authenticated Dashboard Mode */
        .admin-main-wrapper.admin-dashboard-mode {
            display: block;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Welcome Section Card */
        .admin-welcome-card {
            background: var(--card-bg, rgba(30, 41, 59, 0.85));
            border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
            border-radius: 12px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            width: 100%;
        }

        .admin-welcome-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main, #f8fafc);
            margin-bottom: 0.5rem;
        }

        .admin-role-badge {
            display: inline-block;
            font-size: 0.875rem;
            color: #60a5fa;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Responsive Metrics Grid */
        .admin-metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            width: 100%;
        }

        @media (min-width: 1024px) {
            .admin-metrics-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (min-width: 640px) and (max-width: 1023px) {
            .admin-metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 639px) {
            .admin-metrics-grid {
                grid-template-columns: 1fr;
            }
        }

        .admin-metric-card {
            background: var(--card-bg, rgba(30, 41, 59, 0.85));
            border: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
            border-radius: 12px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 140px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(12px);
            width: 100%;
        }

        .admin-metric-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted, #94a3b8);
            margin-bottom: 0.75rem;
        }

        .admin-metric-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
        }
    </style>
</head>
<body>
    <header class="header-bar">
        <div class="brand-title">
            <a href="/admin" style="color: inherit; text-decoration: none;">
                <?= e(trans('app_name')) ?> - <?= e(trans('administration')) ?>
            </a>
        </div>
        <nav class="header-nav">
            <?php
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (empty($currentPath)) {
                $currentPath = '/';
            }
            $safeGet = [];
            if (!empty($_GET) && is_array($_GET)) {
                foreach ($_GET as $k => $v) {
                    if ($k !== 'lang' && is_scalar($v) && (string)$v !== '') {
                        $safeGet[$k] = (string)$v;
                    }
                }
            }
            $safeGet['lang'] = $otherLang;
            $langSwitchUrl = $currentPath . '?' . http_build_query($safeGet);

            $isDashboardActive = ($currentPath === '/admin' || $currentPath === '/admin/' || $currentPath === '/admin/dashboard');
            $isEmployeesActive = ($currentPath === '/admin/employees' || str_starts_with($currentPath, '/admin/employees/'));
            $isTestsActive = ($currentPath === '/admin/tests' || str_starts_with($currentPath, '/admin/tests/'));
            $isQuestionsActive = ($currentPath === '/admin/questions' || str_starts_with($currentPath, '/admin/questions/'));
            $isAssignmentsActive = ($currentPath === '/admin/assignments' || str_starts_with($currentPath, '/admin/assignments/'));
            $isAttemptsActive = ($currentPath === '/admin/attempts' || str_starts_with($currentPath, '/admin/attempts/'));
            $isReportsActive = ($currentPath === '/admin/reports' || str_starts_with($currentPath, '/admin/reports/'));
            ?>
            <a href="<?= e($langSwitchUrl) ?>" class="lang-btn"><?= e($otherLangLabel) ?></a>
            <?php if ($isAuthenticatedLayout && $adminUser): ?>
                <div class="admin-nav-group">
                    <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_dashboard')): ?>
                        <a href="/admin/dashboard" class="nav-link<?= $isDashboardActive ? ' active' : '' ?>"<?= $isDashboardActive ? ' aria-current="page"' : '' ?>><?= e(trans('dashboard')) ?></a>
                    <?php endif; ?>
                    <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_employees')): ?>
                        <a href="/admin/employees" class="nav-link<?= $isEmployeesActive ? ' active' : '' ?>"<?= $isEmployeesActive ? ' aria-current="page"' : '' ?>><?= e(trans('employees')) ?></a>
                    <?php endif; ?>
                    <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_tests')): ?>
                        <a href="/admin/tests" class="nav-link<?= $isTestsActive ? ' active' : '' ?>"<?= $isTestsActive ? ' aria-current="page"' : '' ?>><?= e(trans('tests')) ?></a>
                    <?php endif; ?>
                    <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_questions')): ?>
                        <a href="/admin/questions" class="nav-link<?= $isQuestionsActive ? ' active' : '' ?>"<?= $isQuestionsActive ? ' aria-current="page"' : '' ?>><?= e(trans('question_bank')) ?></a>
                    <?php endif; ?>
                    <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_assignments')): ?>
                        <a href="/admin/assignments" class="nav-link<?= $isAssignmentsActive ? ' active' : '' ?>"<?= $isAssignmentsActive ? ' aria-current="page"' : '' ?>><?= e(trans('assignments')) ?></a>
                    <?php endif; ?>
                    <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_attempts')): ?>
                        <a href="/admin/attempts" class="nav-link<?= $isAttemptsActive ? ' active' : '' ?>"<?= $isAttemptsActive ? ' aria-current="page"' : '' ?>><?= e(trans('attempt_monitoring')) ?></a>
                    <?php endif; ?>
                    <?php if (\App\Services\AuthorizationService::can($adminUser, 'view_reports')): ?>
                        <a href="/admin/reports" class="nav-link<?= $isReportsActive ? ' active' : '' ?>"<?= $isReportsActive ? ' aria-current="page"' : '' ?>><?= e(trans('reports')) ?></a>
                    <?php endif; ?>
                </div>
                <span class="user-info" style="margin: 0 10px; color: #fff;">
                    <strong><?= e($adminUser['name'] ?? '') ?></strong>
                    <small>(<?= e(trans($adminUser['role'] ?? '')) ?>)</small>
                </span>
                <form action="/admin/logout" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="logout-btn"><?= e(trans('sign_out')) ?></button>
                </form>
            <?php endif; ?>

        </nav>
    </header>

    <main class="admin-main-wrapper <?= $isAuthenticatedLayout ? 'admin-dashboard-mode' : 'admin-login-mode' ?>">
        <?= $content ?? '' ?>
    </main>

    <footer class="footer-text">
        &copy; <?= date('Y') ?> <?= e(trans('organization_name')) ?>. <?= e(trans('all_rights_reserved')) ?>
    </footer>
</body>
</html>
