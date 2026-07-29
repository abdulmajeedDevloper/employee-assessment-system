<?php

if (PHP_SAPI !== 'cli') {
    die("CLI execution required.\n");
}

$out = [];
$out[] = "=== SANITIZED PROJECT-WIDE SECURITY & INVENTORY SEARCH REPORT ===";
$out[] = "Timestamp: " . date('Y-m-d H:i:s');
$out[] = "-----------------------------------------------------------------";

$baseDir = realpath(__DIR__ . '/..');

function searchFiles(string $pattern, array $includeExts = ['php', 'sql', 'md', 'json', 'htaccess'], bool $isRegex = false): array {
    global $baseDir;
    $results = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));

    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $path = $file->getPathname();
        $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $path);
        $relPath = str_replace('\\', '/', $relPath);

        // Scan deployable application sources only. Exclude dependencies,
        // runtime artefacts, tests, backups, evidence, and the scanner itself
        // so rule definitions cannot match their own search strings.
        $productionRoots = ['app/', 'bootstrap/', 'config/', 'database/migrations/', 'public/', 'views/', 'lang/'];
        $isProductionSource = in_array($relPath, ['composer.json', '.env.example'], true);
        foreach ($productionRoots as $root) {
            if (str_starts_with($relPath, $root)) {
                $isProductionSource = true;
                break;
            }
        }
        if (!$isProductionSource || str_ends_with($relPath, '.lock')) {
            continue;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (!in_array($ext, $includeExts, true) && !in_array(basename($path), ['.htaccess', '.env.example'], true)) {
            continue;
        }

        // Do not search .env file values for secret output
        if (basename($path) === '.env') continue;

        $content = file_get_contents($path);
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $matched = $isRegex ? preg_match($pattern, $line) : (stripos($line, $pattern) !== false);
            if ($matched) {
                $results[] = [
                    'file' => $relPath,
                    'line' => $lineNum + 1,
                    'content' => trim($line)
                ];
            }
        }
    }

    return $results;
}

$searches = [
    [
        'label' => '1. Raw 10-digit synthetic National IDs in evidence/reports',
        'pattern' => '/\b(1000000001|1000000002|1000000003|9999999999|9990001112)\b/',
        'exts' => ['raw.txt', 'md'],
        'regex' => true
    ],
    [
        'label' => '2. DB_NAME (Deprecated environment variable key)',
        'pattern' => 'DB_NAME',
        'exts' => ['php', 'sql', 'md', 'json', 'example'],
        'regex' => false
    ],
    [
        'label' => '3. DB_DATABASE (Canonical database environment key)',
        'pattern' => 'DB_DATABASE',
        'exts' => ['php', 'sql', 'example'],
        'regex' => false
    ],
    [
        'label' => '4. Raw national_id logging (Logger / error logging)',
        'pattern' => '/Logger::.*national_id\b/i',
        'exts' => ['php'],
        'regex' => true
    ],
    [
        'label' => '5. Unhashed passwords (excluding password_hash)',
        'pattern' => '/\$[a-zA-Z_][a-zA-Z0-9_]*password[a-zA-Z0-9_]*\s*=\s*["\'][^"\']+["\']/i',
        'exts' => ['php'],
        'regex' => true
    ],
    [
        'label' => '6. Hardcoded Secrets (SECURITY_ / secret)',
        'pattern' => '/(SECURITY_HMAC_SECRET|SECURITY_AES_KEY|RATE_LIMIT_SECRET)\s*=\s*[\'"][^\'"]+[\'"]/',
        'exts' => ['php'],
        'regex' => true
    ],
    [
        'label' => '7. Private Keys / RSA Secrets',
        'pattern' => 'BEGIN PRIVATE KEY',
        'exts' => ['php', 'sql', 'json'],
        'regex' => false
    ],
    [
        'label' => '8. Unsafe SQL Concatenation ($sql = "SELECT ... " . $var)',
        'pattern' => '/\$pdo->(query|exec)\s*\(\s*["\'].*\$[a-zA-Z0-9_]+/i',
        'exts' => ['php'],
        'regex' => true
    ],
    [
        'label' => '9. Leftover Debug Code (var_dump, print_r, dd)',
        'pattern' => '/\b(var_dump|print_r|dd)\s*\(/i',
        'exts' => ['php'],
        'regex' => true
    ],
    [
        'label' => '10. Removed Endpoint References (/employee/departments/{id}/sections)',
        'pattern' => '#departments/.+sections#i',
        'exts' => ['php', 'js', 'html'],
        'regex' => true
    ],
    [
        'label' => '11. Removed JavaScript Reference (profile.js)',
        'pattern' => 'profile.js',
        'exts' => ['php', 'html'],
        'regex' => false
    ],
    [
        'label' => '12. Obsolete QA Script References (generate_qa_snapshots, run_live_browser_qa, run_browser_qa_screenshots)',
        'pattern' => '/(generate_qa_snapshots|run_live_browser_qa|run_browser_qa_screenshots)\.php/i',
        'exts' => ['php', 'ps1', 'sh'],
        'regex' => true
    ],
    [
        'label' => '13. Platform Bypass Flags (--ignore-platform-reqs)',
        'pattern' => 'ignore-platform',
        'exts' => ['php', 'ps1', 'sh', 'json', 'md'],
        'regex' => false
    ]
];

foreach ($searches as $s) {
    $out[] = $s['label'] . ":";
    $matches = searchFiles($s['pattern'], $s['exts'], $s['regex']);

    if (empty($matches)) {
        $out[] = "  [PASS] 0 Matches Found.";
    } else {
        $out[] = sprintf("  FOUND %d MATCH(ES):", count($matches));
        foreach ($matches as $m) {
            $out[] = sprintf("    - %s:%d | %s", $m['file'], $m['line'], substr($m['content'], 0, 100));
        }
    }
    $out[] = "-----------------------------------------------------------------";
}

$report = implode("\n", $out) . "\n";
file_put_contents(__DIR__ . '/../evidence/project_searches.raw.txt', $report);
echo $report;
