<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$layoutPath = $root . '/views/layouts/admin.php';
$cssPath = $root . '/public/assets/css/admin.css';
$layout = is_file($layoutPath) ? (string)file_get_contents($layoutPath) : '';

if ($layout === '') {
    $errors[] = 'views/layouts/admin.php is missing or empty.';
}

if (substr_count($layout, "<?= \$content ?? '' ?>") !== 1) {
    $errors[] = 'The admin layout must render $content exactly once.';
}

preg_match_all('/<link\s+[^>]*href=\"\/assets\/css\/admin\.css[^\"]*\"[^>]*>/i', $layout, $adminCssLinks);
if (count($adminCssLinks[0]) !== 1) {
    $errors[] = 'The admin layout must load admin.css exactly once.';
}

if (!is_file($cssPath) || filesize($cssPath) === 0) {
    $errors[] = 'public/assets/css/admin.css is missing or empty.';
}

$viewFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/views/admin', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $viewFiles[] = $file->getPathname();
    }
}

$viewFiles[] = $layoutPath;

foreach ($viewFiles as $file) {
    $contents = (string)file_get_contents($file);
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));

    if (stripos($contents, '<style') !== false) {
        $errors[] = $relative . ' still contains an embedded <style> block.';
    }

    if (preg_match_all('/\sstyle=("[^"]*"|\'[^\']*\')/is', $contents, $matches)) {
        foreach ($matches[1] as $styleAttribute) {
            if (!str_contains($styleAttribute, '--progress-value')
                && !str_contains($styleAttribute, '--ring-value')) {
                $errors[] = $relative . ' contains a static inline style: ' . $styleAttribute;
            }
        }
    }

    if ($file !== $layoutPath) {
        preg_match_all(
            '/require\s+__DIR__\s*\.\s*[\'\"].*layouts\/admin\.php[\'\"]\s*;/',
            str_replace('\\', '/', $contents),
            $requires
        );

        if (count($requires[0]) > 1) {
            $errors[] = $relative . ' includes the admin layout more than once.';
        }
    }
}

if (is_file($cssPath)) {
    $css = (string)file_get_contents($cssPath);
    $balance = 0;
    $length = strlen($css);
    $state = 'code';
    $quote = '';

    for ($i = 0; $i < $length; $i++) {
        $char = $css[$i];
        $next = $i + 1 < $length ? $css[$i + 1] : '';

        if ($state === 'code') {
            if ($char === '/' && $next === '*') {
                $state = 'comment';
                $i++;
            } elseif ($char === '"' || $char === "'") {
                $state = 'string';
                $quote = $char;
            } elseif ($char === '{') {
                $balance++;
            } elseif ($char === '}') {
                $balance--;
            }
        } elseif ($state === 'comment') {
            if ($char === '*' && $next === '/') {
                $state = 'code';
                $i++;
            }
        } elseif ($state === 'string') {
            if ($char === '\\') {
                $i++;
            } elseif ($char === $quote) {
                $state = 'code';
            }
        }
    }

    if ($balance !== 0) {
        $errors[] = 'admin.css has unbalanced braces: ' . $balance;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Admin CSS verification failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Admin CSS verification passed.\n";
echo 'Checked ' . count($viewFiles) . " PHP presentation files.\n";
