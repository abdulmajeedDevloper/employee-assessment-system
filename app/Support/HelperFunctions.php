<?php

use App\Security\CsrfTokenManager;

if (!function_exists('env')) {
    /**
     * Retrieve environment variable with default fallback.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string)$value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML output for XSS protection.
     *
     * @param string|null $value
     * @return string
     */
    function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get active CSRF token string.
     *
     * @return string
     */
    function csrf_token(): string
    {
        return CsrfTokenManager::getToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate HTML input element for CSRF token.
     *
     * @return string
     */
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
    }
}

if (!function_exists('trans')) {
    /**
     * Translate key using active language dictionary.
     *
     * @param string $key
     * @param array $replace
     * @return string
     */
    function trans(string $key, array $replace = []): string
    {
        $lang = $_SESSION['lang'] ?? env('APP_LANG', 'ar');
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }

        static $dictionaries = [];
        if (!isset($dictionaries[$lang])) {
            $path = __DIR__ . '/../../lang/' . $lang . '.php';
            if (file_exists($path)) {
                $dictionaries[$lang] = require $path;
            } else {
                $dictionaries[$lang] = [];
            }
        }

        $translation = $dictionaries[$lang][$key] ?? $key;

        foreach ($replace as $placeholder => $value) {
            $translation = str_replace(':' . $placeholder, $value, $translation);
        }

        return $translation;
    }
}

if (!function_exists('response_json')) {
    /**
     * Send JSON response and terminate.
     *
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    function response_json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
