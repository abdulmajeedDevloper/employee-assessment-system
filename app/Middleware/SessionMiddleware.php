<?php

namespace App\Middleware;

class SessionMiddleware
{
    /**
     * Start and configure secure server-side PHP session.
     *
     * @return void
     */
    public static function handle(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_path', '/');

        $isHttps = self::isHttps();
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');

        session_name('EAS_SESSID');
        session_start();
    }

    /**
     * Determine whether the request is HTTPS using trusted server-controlled information.
     */
    public static function isHttps(): bool
    {
        if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        $appUrl = $_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? '';
        if (!empty($appUrl) && parse_url((string) $appUrl, PHP_URL_SCHEME) === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Regenerate session ID to prevent session fixation.
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
