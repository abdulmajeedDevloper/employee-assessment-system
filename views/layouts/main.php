<?php
$currentLang = $_SESSION['lang'] ?? 'ar';
$dir = ($currentLang === 'ar') ? 'rtl' : 'ltr';
$otherLang = ($currentLang === 'ar') ? 'en' : 'ar';
$otherLangLabel = ($currentLang === 'ar') ? 'English' : 'العربية';
$isLoggedIn = isset($_SESSION['employee_auth']) && is_array($_SESSION['employee_auth']);
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? trans('app_name')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="header-bar">
        <div class="brand-title"><?= e(trans('app_name')) ?></div>
        <nav class="header-nav">
            <a href="?lang=<?= e($otherLang) ?>" class="lang-btn"><?= e($otherLangLabel) ?></a>
            <?php if ($isLoggedIn): ?>
                <form action="/employee/logout" method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="logout-btn"><?= e(trans('logout')) ?></button>
                </form>
            <?php endif; ?>
        </nav>
    </header>

    <main class="main-container">
        <?= $content ?? '' ?>
    </main>

    <footer class="footer-text">
        &copy; <?= date('Y') ?> <?= e(trans('organization_name')) ?>. <?= e(trans('all_rights_reserved')) ?>
    </footer>
</body>
</html>
