<?php

namespace App\Security;

class CsrfTokenManager
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Generate or retrieve current session CSRF token.
     *
     * @return string
     */
    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = HashManager::generateRandomToken(32);
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Validate incoming token against stored session token.
     *
     * @param string|null $token
     * @return bool
     */
    public static function validate(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $storedToken = $_SESSION[self::SESSION_KEY] ?? '';
        if (empty($storedToken)) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }
}
