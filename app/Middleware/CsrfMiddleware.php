<?php

namespace App\Middleware;

use App\Security\CsrfTokenManager;

class CsrfMiddleware
{
    /**
     * Verify CSRF token for state-changing HTTP requests.
     *
     * @return bool
     */
    /**
     * Verify a CSRF token without emitting a response. Controllers that need
     * their own error page can use this method and handle a false result.
     */
    public static function verify(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return CsrfTokenManager::validate(is_string($token) ? $token : null);
    }

    public static function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!CsrfTokenManager::validate($token)) {
            if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                response_json([
                    'success' => false,
                    'error' => 'Invalid or expired CSRF token. Please refresh the page and try again.'
                ], 419);
            } else {
                http_response_code(419);
                echo '<h1>419 Page Expired</h1><p>CSRF verification failed. Please refresh and try again.</p>';
                exit;
            }
        }

        return true;
    }
}
