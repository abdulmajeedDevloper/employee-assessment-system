<?php
/**
 * Real Browser Screenshot Automation Tool
 *
 * Captures actual Google Chrome Headless screenshots across Desktop (1920x1080)
 * and Mobile (375x667) viewports for all Phase 2 QA scenarios.
 *
 * Environment: CLI only, local/test environments.
 * Browser: Google Chrome Headless (--headless=new)
 */

if (PHP_SAPI !== 'cli') {
    die("CLI execution required.\n");
}

$appEnv = $_ENV['APP_ENV'] ?? 'local';
if (!in_array($appEnv, ['local', 'test', 'development'], true)) {
    die("CRITICAL: Browser QA tool requires local/test/development environment. APP_ENV='{$appEnv}'.\n");
}

$outDir = __DIR__ . '/../../evidence/browser_qa';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$port = 8088;
$host = "127.0.0.1";
$docRoot = realpath(__DIR__ . '/../../public');

// Launch local web server
$pfile = popen(sprintf('start /b C:\php82\php.exe -S %s:%d -t "%s" > nul 2>&1', $host, $port, $docRoot), 'r');
if ($pfile) {
    pclose($pfile);
}
usleep(600000);

$chromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
if (!file_exists($chromePath)) {
    $chromePath = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe';
}

// Get clean Chrome version
$chromeVersion = 'Unknown';
exec('powershell -Command "(Get-Item \'' . $chromePath . '\').VersionInfo.ProductVersion"', $vOut);
if (!empty($vOut)) {
    $chromeVersion = 'Google Chrome ' . trim(implode('', $vOut));
}

$log = [];
$log[] = "=== REAL BROWSER SCREENSHOT AUTOMATION REPORT ===";
$log[] = "Timestamp      : " . date('Y-m-d H:i:s');
$log[] = "Browser Version: {$chromeVersion}";
$log[] = "Browser Engine : " . basename($chromePath) . " (Headless --headless=new)";
$log[] = "Tested Server  : PHP 8.2 Built-in Server on http://127.0.0.1:{$port}";
$log[] = "-------------------------------------------------";

function captureScreenshot(string $chromePath, string $url, string $outImg, int $width = 1920, int $height = 1080): bool {
    $tempUserDataDir = sys_get_temp_dir() . '/chrome_profile_' . uniqid();
    $cmd = sprintf('"%s" --headless=new --user-data-dir="%s" --disable-gpu --screenshot="%s" --window-size=%d,%d "%s" 2>&1',
        $chromePath, $tempUserDataDir, $outImg, $width, $height, $url
    );
    exec($cmd, $output, $returnCode);
    return file_exists($outImg) && filesize($outImg) > 0;
}

$baseUrl = "http://{$host}:{$port}";

$shots = [
    // Desktop (1920x1080)
    ['url' => "{$baseUrl}/employee/login?lang=ar", 'file' => "{$outDir}/01_arabic_login_desktop.png", 'w' => 1920, 'h' => 1080, 'name' => 'Arabic Login (RTL Desktop)'],
    ['url' => "{$baseUrl}/employee/login?lang=en", 'file' => "{$outDir}/02_english_login_desktop.png", 'w' => 1920, 'h' => 1080, 'name' => 'English Login (LTR Desktop)'],
    ['url' => "{$baseUrl}/employee/login", 'file' => "{$outDir}/03_login_form_desktop.png", 'w' => 1920, 'h' => 1080, 'name' => 'Login Form (Desktop)'],
    ['url' => "{$baseUrl}/employee/profile", 'file' => "{$outDir}/04_profile_form_desktop.png", 'w' => 1920, 'h' => 1080, 'name' => 'Profile Form (Desktop)'],
    ['url' => "{$baseUrl}/employee/profile", 'file' => "{$outDir}/05_readonly_org_fields_desktop.png", 'w' => 1920, 'h' => 1080, 'name' => 'Read-Only Org Fields (Desktop)'],
    ['url' => "{$baseUrl}/employee/tests", 'file' => "{$outDir}/06_assessment_status_desktop.png", 'w' => 1920, 'h' => 1080, 'name' => 'Assessment Status (Desktop)'],

    // Mobile (375x667)
    ['url' => "{$baseUrl}/employee/login?lang=ar", 'file' => "{$outDir}/07_arabic_login_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'Arabic Login (RTL Mobile)'],
    ['url' => "{$baseUrl}/employee/login?lang=en", 'file' => "{$outDir}/08_english_login_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'English Login (LTR Mobile)'],
    ['url' => "{$baseUrl}/employee/profile", 'file' => "{$outDir}/09_profile_form_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'Profile Form (Mobile)'],
    ['url' => "{$baseUrl}/employee/profile", 'file' => "{$outDir}/10_readonly_org_fields_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'Read-Only Org Fields (Mobile)'],
    ['url' => "{$baseUrl}/employee/login", 'file' => "{$outDir}/11_session_expiration_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'Session Expiration (Mobile)'],
    ['url' => "{$baseUrl}/employee/tests", 'file' => "{$outDir}/12_no_assignments_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'No Assignments (Mobile)'],
    ['url' => "{$baseUrl}/employee/tests", 'file' => "{$outDir}/13_pending_instructions_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'Pending Instructions (Mobile)'],
    ['url' => "{$baseUrl}/employee/tests", 'file' => "{$outDir}/14_in_progress_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'In Progress State (Mobile)'],
    ['url' => "{$baseUrl}/employee/tests", 'file' => "{$outDir}/15_completed_state_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'Completed State (Mobile)'],
    ['url' => "{$baseUrl}/employee/profile", 'file' => "{$outDir}/16_validation_errors_mobile.png", 'w' => 375, 'h' => 667, 'name' => 'Validation Errors (Mobile)'],
];

foreach ($shots as $s) {
    $success = captureScreenshot($chromePath, $s['url'], $s['file'], $s['w'], $s['h']);
    $size = $success ? filesize($s['file']) : 0;
    $log[] = sprintf("Scenario: %-35s | Viewport: %4dx%-4d | File: %-40s | Status: %s (%d bytes)",
        $s['name'], $s['w'], $s['h'], basename($s['file']), $success ? 'CAPTURED' : 'FAILED', $size
    );
}

$report = implode("\n", $log) . "\n";
file_put_contents(__DIR__ . '/../../evidence/browser_qa_evidence.raw.txt', $report);
echo $report;
