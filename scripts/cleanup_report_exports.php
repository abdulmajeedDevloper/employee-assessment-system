<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/init.php';

$projectRoot = dirname(__DIR__);
$configuredPath = trim((string)env('REPORT_EXPORT_STORAGE_PATH', 'storage/exports'));
if ($configuredPath === '') {
    $configuredPath = 'storage/exports';
}
$isAbsolute = str_starts_with($configuredPath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
$directory = $isAbsolute
    ? rtrim($configuredPath, DIRECTORY_SEPARATOR)
    : $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath);

$retentionHours = filter_var(env('TEMP_EXPORT_RETENTION_HOURS', 24), FILTER_VALIDATE_INT);
$retentionHours = $retentionHours === false ? 24 : max(1, min(720, (int)$retentionHours));
$batchSize = filter_var(env('REPORT_EXPORT_CLEANUP_BATCH_SIZE', 500), FILTER_VALIDATE_INT);
$batchSize = $batchSize === false ? 500 : max(1, min(5000, (int)$batchSize));

if (!is_dir($directory)) {
    fwrite(STDOUT, "Report export directory does not exist; nothing to clean.\n");
    exit(0);
}
if (is_link($directory)) {
    fwrite(STDERR, "Refusing to clean a symbolic-link export directory.\n");
    exit(1);
}

$root = realpath($directory);
if ($root === false) {
    fwrite(STDERR, "Unable to resolve report export directory.\n");
    exit(1);
}

$threshold = time() - ($retentionHours * 3600);
$candidates = [];
$iterator = new DirectoryIterator($root);
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->isLink()) {
        continue;
    }
    $name = $file->getFilename();
    if (preg_match('/^report_[a-f0-9]{32}\.(xlsx|pdf)$/D', $name) !== 1) {
        continue;
    }
    if ($file->getMTime() >= $threshold) {
        continue;
    }
    $candidates[] = ['path' => $file->getPathname(), 'mtime' => $file->getMTime()];
}

usort($candidates, static fn(array $a, array $b): int => $a['mtime'] <=> $b['mtime']);
$candidates = array_slice($candidates, 0, $batchSize);
$deleted = 0;
$failed = 0;
foreach ($candidates as $candidate) {
    $path = realpath((string)$candidate['path']);
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($path === false || !str_starts_with($path, $prefix) || is_link($path)) {
        $failed++;
        continue;
    }
    if (@unlink($path)) {
        $deleted++;
    } else {
        $failed++;
    }
}

fwrite(STDOUT, "Deleted {$deleted} expired report export(s); {$failed} failed.\n");
exit($failed > 0 ? 1 : 0);
