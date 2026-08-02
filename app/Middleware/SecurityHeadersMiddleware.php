<?php

namespace App\Middleware;

class SecurityHeadersMiddleware
{
    /**
     * Get array of security headers that should be set for current request environment.
     *
     * @return array<string, string>
     */
    public static function getHeaders(): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        $appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production'));
        if ($appEnv === 'production' && SessionMiddleware::isHttps()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        return $headers;
    }

    /**
     * Set global security response headers and remove PHP disclosure headers.
     *
     * @return void
     */
    public static function handle(): void
    {
        if (headers_sent()) {
            return;
        }

        header_remove('X-Powered-By');

        foreach (self::getHeaders() as $name => $value) {
            self::setHeaderIfMissing($name, $value);
        }
    }

    /**
     * Set response header only if it has not already been set.
     */
    private static function setHeaderIfMissing(string $name, string $value): void
    {
        $prefix = strtolower($name) . ':';
        foreach (headers_list() as $header) {
            if (str_starts_with(strtolower($header), $prefix)) {
                return;
            }
        }
        header("{$name}: {$value}");
    }
}
