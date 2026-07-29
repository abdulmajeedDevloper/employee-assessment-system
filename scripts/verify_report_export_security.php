<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'controller' => $root . '/app/Controllers/Admin/ReportController.php',
    'export_service' => $root . '/app/Services/AdminReportExportService.php',
    'token_service' => $root . '/app/Services/AdminReportDownloadTokenService.php',
    'repository' => $root . '/app/Repositories/AdminReportRepository.php',
    'sanitizer' => $root . '/app/Support/ReportExportSanitizer.php',
    'view' => $root . '/views/admin/reports/index.php',
    'routes' => $root . '/config/routes.php',
];

$content = [];
foreach ($files as $key => $path) {
    $value = file_get_contents($path);
    if ($value === false) {
        fwrite(STDERR, "Missing verification input: {$path}\n");
        exit(1);
    }
    $content[$key] = $value;
}

$checks = [
    'export route uses POST' => str_contains($content['routes'], "'/admin/reports/export' => [ReportController::class, 'export']"),
    'download route exists' => str_contains($content['routes'], "'/admin/reports/download' => [ReportController::class, 'download']"),
    'export permission enforced' => str_contains($content['controller'], "handle('export_reports')"),
    'export CSRF verified' => str_contains($content['controller'], 'CsrfMiddleware::verify()'),
    'download disables caching' => str_contains($content['controller'], 'Cache-Control: private, no-store'),
    'download disables MIME sniffing' => str_contains($content['controller'], 'X-Content-Type-Options: nosniff'),
    'download token is random' => str_contains($content['token_service'], 'bin2hex(random_bytes(32))'),
    'stored filename allowlist exists' => str_contains($content['token_service'], "^report_[a-f0-9]{32}\\.(xlsx|pdf)$"),
    'token is admin-bound' => str_contains($content['token_service'], "(int)(\$record['admin_id'] ?? 0) !== \$adminId"),
    'file SHA-256 checked' => substr_count($content['token_service'], "hash_file('sha256'") >= 2,
    'file size checked' => str_contains($content['token_service'], '$expectedSize !== $actualSize'),
    'spreadsheet formula protection exists' => str_contains($content['sanitizer'], "[=+\\-@]"),
    'spreadsheet text is explicit string' => str_contains($content['export_service'], 'DataType::TYPE_STRING'),
    'PDF values are escaped' => str_contains($content['export_service'], 'ReportExportSanitizer::html'),
    'PDF RTL support exists' => str_contains($content['export_service'], "SetDirectionality(\$isArabic ? 'rtl' : 'ltr')"),
    'export rows use masked ID' => str_contains($content['repository'], "CONCAT('********', e.national_id_last4) AS masked_national_id"),
    'export rows omit email' => !preg_match('/getOfficialResultsForExport[\s\S]*?e\.email[\s\S]*?LIMIT :export_limit/', $content['repository']),
    'report export form uses POST' => str_contains($content['view'], 'method="POST" action="/admin/reports/export"'),
    'audit payload excludes stored filename' => !str_contains($thisAudit = substr($content['export_service'], strpos($content['export_service'], 'private function logAudit')), "'stored_filename'"),
    'audit payload excludes token' => !str_contains($thisAudit, "'token'"),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    fwrite(STDOUT, ($passed ? 'PASS' : 'FAIL') . " — {$label}\n");
    if (!$passed) {
        $failed++;
    }
}

fwrite(STDOUT, 'checks=' . count($checks) . " failed={$failed}\n");
exit($failed === 0 ? 0 : 1);
