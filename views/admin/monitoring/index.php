<?php

declare(strict_types=1);

$adminCssScopes = is_array($adminCssScopes ?? null) ? $adminCssScopes : [];
$adminCssScopes[] = 'admin-view-monitoring-index';


$currentLang = $_SESSION['lang'] ?? 'ar';
$isArabic = $currentLang === 'ar';

$text = static function (string $ar, string $en) use ($isArabic): string {
    return $isArabic ? $ar : $en;
};

$pageTitle = $text('مراقبة الاختبارات', 'Test Monitoring');
$isAuthenticatedLayout = true;

$items = is_array($results['items'] ?? null) ? $results['items'] : [];
$total = (int)($results['total'] ?? 0);
$page = max(1, (int)($results['page'] ?? 1));
$perPage = max(5, (int)($results['per_page'] ?? 20));
$totalPages = max(0, (int)($results['total_pages'] ?? 0));

$formatNumber = static fn (mixed $value): string => number_format((int)$value);

$formatDateTime = static function (mixed $value): string {
    if (!is_string($value) || trim($value) === '') {
        return '—';
    }

    try {
        $timezoneName = $_SESSION['timezone'] ?? 'Asia/Aden';
        $timezone = new DateTimeZone(
            is_string($timezoneName) && $timezoneName !== ''
                ? $timezoneName
                : 'Asia/Aden'
        );

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone($timezone)
            ->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return $value;
    }
};

$connectionLabel = static function (string $status) use ($text): string {
    return match ($status) {
        'ONLINE' => $text('متصل', 'Online'),
        'STALE' => $text('خامل', 'Stale'),
        'DISCONNECTED' => $text('منقطع', 'Disconnected'),
        'EXPIRED' => $text('انتهى الوقت', 'Expired'),
        default => $text('غير معروف', 'Unknown'),
    };
};

$connectionClass = static function (string $status): string {
    return match ($status) {
        'ONLINE' => 'online',
        'STALE' => 'stale',
        'DISCONNECTED' => 'disconnected',
        'EXPIRED' => 'expired',
        default => 'unknown',
    };
};

$testName = static function (array $item) use ($isArabic): string {
    $preferred = $isArabic
        ? (string)($item['test_name_ar'] ?? '')
        : (string)($item['test_name_en'] ?? '');

    if ($preferred !== '') {
        return $preferred;
    }

    return (string)($item['test_code'] ?? '—');
};

$departmentName = static function (array $item) use ($isArabic): string {
    $preferred = $isArabic
        ? (string)($item['department_name_ar'] ?? '')
        : (string)($item['department_name_en'] ?? '');

    return $preferred !== '' ? $preferred : '—';
};

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $query = [
        'q' => (string)($filters['q'] ?? ''),
        'test_id' => (int)($filters['test_id'] ?? 0),
        'connection' => (string)($filters['connection'] ?? 'ALL'),
        'attention' => (string)($filters['attention'] ?? 'ALL'),
        'per_page' => (int)($filters['per_page'] ?? 20),
        'page' => $targetPage,
    ];

    return '/admin/monitoring?' . http_build_query(
        array_filter(
            $query,
            static fn (mixed $value): bool =>
                $value !== ''
                && $value !== 0
                && $value !== 'ALL'
        )
    );
};

ob_start();
?>

<div class="monitor-shell">
    <section class="monitor-head">
        <div>
            <h1 class="monitor-title"><?= e($text('مراقبة الاختبارات المباشرة', 'Live Test Monitoring')) ?></h1>
            <p class="monitor-subtitle">
                <?= e($text(
                    'متابعة الموظفين أثناء أداء الاختبارات، وحالة الاتصال والتقدم والتنبيهات الأمنية لحظة بلحظة.',
                    'Monitor employees during tests, including connectivity, progress and security alerts.'
                )) ?>
            </p>
            <span class="live-indicator">
                <span class="live-dot" aria-hidden="true"></span>
                <?= e($text('تحديث تلقائي كل 20 ثانية', 'Automatic refresh every 20 seconds')) ?>
            </span>
        </div>

        <div class="monitor-head-actions">
            <button class="monitor-action primary" type="button" id="refresh-monitor">
                <?= e($text('تحديث الآن', 'Refresh now')) ?>
            </button>
            <a class="monitor-action" href="/admin/attempts">
                <?= e($text('متابعة جميع المحاولات', 'All attempts')) ?>
            </a>
        </div>
    </section>

    <?php if (!empty($errorMessage)): ?>
        <div class="monitor-alert"><?= e((string)$errorMessage) ?></div>
    <?php endif; ?>

    <section class="monitor-stats">
        <article class="monitor-stat">
            <span><?= e($text('المحاولات الجارية', 'Active attempts')) ?></span>
            <strong><?= e($formatNumber($statistics['active_attempts'] ?? 0)) ?></strong>
        </article>
        <article class="monitor-stat online">
            <span><?= e($text('متصل الآن', 'Online now')) ?></span>
            <strong><?= e($formatNumber($statistics['online_attempts'] ?? 0)) ?></strong>
        </article>
        <article class="monitor-stat stale">
            <span><?= e($text('اتصال خامل', 'Stale connection')) ?></span>
            <strong><?= e($formatNumber($statistics['stale_attempts'] ?? 0)) ?></strong>
        </article>
        <article class="monitor-stat disconnected">
            <span><?= e($text('منقطع', 'Disconnected')) ?></span>
            <strong><?= e($formatNumber($statistics['disconnected_attempts'] ?? 0)) ?></strong>
        </article>
        <article class="monitor-stat expired">
            <span><?= e($text('انتهى الوقت', 'Expired')) ?></span>
            <strong><?= e($formatNumber($statistics['expired_attempts'] ?? 0)) ?></strong>
        </article>
        <article class="monitor-stat">
            <span><?= e($text('تنبيهات التركيز', 'Focus alerts')) ?></span>
            <strong><?= e($formatNumber($statistics['focus_alert_attempts'] ?? 0)) ?></strong>
        </article>
        <article class="monitor-stat">
            <span><?= e($text('تنبيهات أمنية', 'Security alerts')) ?></span>
            <strong><?= e($formatNumber($statistics['security_alert_attempts'] ?? 0)) ?></strong>
        </article>
    </section>

    <section class="monitor-card">
        <div class="monitor-card-head">
            <h2><?= e($text('البحث والتصفية', 'Search and filters')) ?></h2>
            <span class="monitor-muted">
                <?= e($text('إجمالي النتائج:', 'Total results:')) ?>
                <?= e($formatNumber($total)) ?>
            </span>
        </div>

        <form method="GET" action="/admin/monitoring">
            <div class="monitor-filters">
                <div class="monitor-field">
                    <label for="q"><?= e($text('بحث عام', 'Search')) ?></label>
                    <input
                        id="q"
                        name="q"
                        type="search"
                        maxlength="120"
                        value="<?= e((string)($filters['q'] ?? '')) ?>"
                        placeholder="<?= e($text('اسم الموظف، الرقم، الاختبار أو IP', 'Employee, number, test or IP')) ?>"
                    >
                </div>

                <div class="monitor-field">
                    <label for="test_id"><?= e($text('الاختبار', 'Test')) ?></label>
                    <select id="test_id" name="test_id">
                        <option value="0"><?= e($text('جميع الاختبارات الجارية', 'All active tests')) ?></option>
                        <?php foreach ($tests as $test): ?>
                            <?php
                            $name = $isArabic
                                ? (string)($test['name_ar'] ?? '')
                                : (string)($test['name_en'] ?? '');
                            ?>
                            <option
                                value="<?= e((string)(int)($test['id'] ?? 0)) ?>"
                                <?= (int)($filters['test_id'] ?? 0) === (int)($test['id'] ?? 0) ? 'selected' : '' ?>
                            >
                                <?= e((string)($test['code'] ?? '') . ' — ' . $name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="monitor-field">
                    <label for="connection"><?= e($text('حالة الاتصال', 'Connection')) ?></label>
                    <select id="connection" name="connection">
                        <?php
                        $connectionOptions = [
                            'ALL' => $text('الكل', 'All'),
                            'ONLINE' => $text('متصل', 'Online'),
                            'STALE' => $text('خامل', 'Stale'),
                            'DISCONNECTED' => $text('منقطع', 'Disconnected'),
                            'EXPIRED' => $text('انتهى الوقت', 'Expired'),
                        ];
                        ?>
                        <?php foreach ($connectionOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($filters['connection'] ?? 'ALL') === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="monitor-field">
                    <label for="attention"><?= e($text('نوع التنبيه', 'Alert type')) ?></label>
                    <select id="attention" name="attention">
                        <option value="ALL" <?= ($filters['attention'] ?? 'ALL') === 'ALL' ? 'selected' : '' ?>>
                            <?= e($text('جميع المحاولات', 'All attempts')) ?>
                        </option>
                        <option value="FOCUS" <?= ($filters['attention'] ?? '') === 'FOCUS' ? 'selected' : '' ?>>
                            <?= e($text('فقدان التركيز', 'Focus loss')) ?>
                        </option>
                        <option value="SECURITY" <?= ($filters['attention'] ?? '') === 'SECURITY' ? 'selected' : '' ?>>
                            <?= e($text('أحداث أمنية', 'Security events')) ?>
                        </option>
                    </select>
                </div>
            </div>

            <div class="monitor-filter-actions">
                <button class="monitor-btn primary" type="submit">
                    <?= e($text('تطبيق الفلاتر', 'Apply filters')) ?>
                </button>
                <a class="monitor-btn secondary" href="/admin/monitoring">
                    <?= e($text('إعادة تعيين', 'Reset')) ?>
                </a>
            </div>
        </form>
    </section>

    <section class="monitor-card">
        <div class="monitor-card-head">
            <h2><?= e($text('الاختبارات الجارية الآن', 'Tests running now')) ?></h2>
            <span class="monitor-muted">
                <?= e($text('آخر تحديث:', 'Last refresh:')) ?>
                <span id="last-refresh-time"></span>
            </span>
        </div>

        <?php if ($items === []): ?>
            <div class="monitor-empty">
                <?= e($text(
                    'لا توجد محاولات اختبار جارية مطابقة للفلاتر الحالية.',
                    'There are no active test attempts matching the current filters.'
                )) ?>
            </div>
        <?php else: ?>
            <div class="monitor-grid">
                <?php foreach ($items as $item): ?>
                    <?php
                    $connectionStatus = (string)($item['connection_status'] ?? 'UNKNOWN');
                    $connectionCss = $connectionClass($connectionStatus);
                    $answered = (int)($item['answered_count'] ?? 0);
                    $questions = (int)($item['question_count'] ?? 0);
                    $progress = $questions > 0
                        ? max(0, min(100, ($answered * 100) / $questions))
                        : 0;
                    $focusLoss = (int)($item['focus_loss_count'] ?? 0);
                    $securityCount = (int)($item['security_event_count'] ?? 0);
                    $remainingSeconds = max(0, (int)($item['remaining_seconds'] ?? 0));
                    ?>
                    <article class="attempt-card <?= e($connectionCss) ?>">
                        <div class="attempt-top">
                            <div class="attempt-person">
                                <h3><?= e((string)($item['employee_name'] ?? '—')) ?></h3>
                                <p>
                                    <?= e((string)($item['employee_number'] ?? '—')) ?>
                                    ·
                                    <?= e($departmentName($item)) ?>
                                </p>
                            </div>

                            <span class="connection-badge <?= e($connectionCss) ?>">
                                <?= e($connectionLabel($connectionStatus)) ?>
                            </span>
                        </div>

                        <div class="attempt-test">
                            <div>
                                <strong><?= e($testName($item)) ?></strong>
                                <span>
                                    <?= e((string)($item['test_code'] ?? '—')) ?>
                                    ·
                                    <?= e($text('المحاولة', 'Attempt')) ?>
                                    #<?= e((string)($item['attempt_number'] ?? 1)) ?>
                                </span>
                            </div>

                            <span
                                class="countdown <?= $connectionStatus === 'EXPIRED' ? 'expired' : '' ?>"
                                data-remaining-seconds="<?= e((string)$remainingSeconds) ?>"
                            >
                                --:--:--
                            </span>
                        </div>

                        <div class="progress-row">
                            <div class="progress-copy">
                                <span><?= e($text('تقدم الإجابة', 'Answer progress')) ?></span>
                                <strong><?= e((string)$answered) ?> / <?= e((string)$questions) ?></strong>
                            </div>
                            <div class="progress-track" aria-hidden="true">
                                <div class="progress-fill" style="--progress-value:<?= e(number_format($progress, 2, '.', '')) ?>%;"></div>
                            </div>
                        </div>

                        <div class="attempt-metrics">
                            <div class="attempt-metric">
                                <span><?= e($text('بدأ في', 'Started at')) ?></span>
                                <strong dir="ltr"><?= e($formatDateTime($item['started_at'] ?? null)) ?></strong>
                            </div>
                            <div class="attempt-metric">
                                <span><?= e($text('آخر نشاط', 'Last activity')) ?></span>
                                <strong dir="ltr"><?= e($formatDateTime($item['last_activity_at'] ?? null)) ?></strong>
                            </div>
                            <div class="attempt-metric">
                                <span><?= e($text('عنوان IP', 'IP address')) ?></span>
                                <strong dir="ltr">
                                    <?= e((string)(
                                        $item['session_ip_address']
                                        ?? $item['attempt_ip_address']
                                        ?? '—'
                                    )) ?>
                                </strong>
                            </div>
                            <div class="attempt-metric">
                                <span><?= e($text('رقم المحاولة', 'Attempt ID')) ?></span>
                                <strong>#<?= e((string)($item['attempt_id'] ?? '—')) ?></strong>
                            </div>
                        </div>

                        <div class="attempt-alerts">
                            <?php if ($focusLoss >= 3): ?>
                                <span class="alert-chip warning">
                                    <?= e($text('فقدان التركيز:', 'Focus losses:')) ?>
                                    <?= e((string)$focusLoss) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($securityCount > 0): ?>
                                <span class="alert-chip danger">
                                    <?= e($text('أحداث أمنية:', 'Security events:')) ?>
                                    <?= e((string)$securityCount) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($focusLoss < 3 && $securityCount === 0 && $connectionStatus === 'ONLINE'): ?>
                                <span class="alert-chip ok">
                                    <?= e($text('لا توجد تنبيهات حالية', 'No current alerts')) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="attempt-footer">
                            <small>
                                <?= e($text('حالة الجلسة:', 'Session status:')) ?>
                                <?= e((string)($item['session_status'] ?? '—')) ?>
                            </small>

                            <a
                                class="attempt-view"
                                href="/admin/attempts/view?id=<?= e((string)(int)($item['attempt_id'] ?? 0)) ?>"
                            >
                                <?= e($text('فتح تفاصيل المحاولة', 'Open attempt details')) ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="monitor-pagination">
                    <span class="monitor-muted">
                        <?= e($text('الصفحة', 'Page')) ?>
                        <?= e((string)$page) ?>
                        <?= e($text('من', 'of')) ?>
                        <?= e((string)$totalPages) ?>
                    </span>

                    <div class="monitor-pages">
                        <?php if ($page > 1): ?>
                            <a class="monitor-page" href="<?= e($buildPageUrl($page - 1)) ?>">
                                <?= e($text('السابق', 'Previous')) ?>
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>

                        <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                            <a
                                class="monitor-page <?= $pageNumber === $page ? 'active' : '' ?>"
                                href="<?= e($buildPageUrl($pageNumber)) ?>"
                            >
                                <?= e((string)$pageNumber) ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="monitor-page" href="<?= e($buildPageUrl($page + 1)) ?>">
                                <?= e($text('التالي', 'Next')) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<script>
(() => {
    'use strict';

    const countdownElements = document.querySelectorAll('[data-remaining-seconds]');
    const lastRefreshElement = document.getElementById('last-refresh-time');
    const refreshButton = document.getElementById('refresh-monitor');

    const pad = (value) => String(value).padStart(2, '0');

    const formatRemaining = (seconds) => {
        const safeSeconds = Math.max(0, Number(seconds) || 0);
        const hours = Math.floor(safeSeconds / 3600);
        const minutes = Math.floor((safeSeconds % 3600) / 60);
        const remainingSeconds = safeSeconds % 60;

        return `${pad(hours)}:${pad(minutes)}:${pad(remainingSeconds)}`;
    };

    const updateCountdowns = () => {
        countdownElements.forEach((element) => {
            let remaining = Number(element.dataset.remainingSeconds || 0);

            if (remaining > 0) {
                remaining -= 1;
                element.dataset.remainingSeconds = String(remaining);
            }

            element.textContent = formatRemaining(remaining);

            if (remaining <= 0) {
                element.classList.add('expired');
            }
        });
    };

    if (lastRefreshElement) {
        lastRefreshElement.textContent = new Date().toLocaleTimeString(
            document.documentElement.lang || undefined
        );
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => window.location.reload());
    }

    updateCountdowns();
    window.setInterval(updateCountdowns, 1000);
    window.setTimeout(() => window.location.reload(), 20000);
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/admin.php';
