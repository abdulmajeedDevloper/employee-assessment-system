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

        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }

        session_name('EAS_SESSID');
        session_start();
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
