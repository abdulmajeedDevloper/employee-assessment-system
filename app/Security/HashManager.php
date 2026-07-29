<?php

namespace App\Security;

use InvalidArgumentException;

class HashManager
{
    /**
     * Compute BINARY(32) HMAC-SHA-256 hash for exact National ID index lookup.
     *
     * @param string $nationalId
     * @return string Raw 32 bytes binary
     */
    public static function hashNationalId(string $nationalId): string
    {
        $secret = $_ENV['SECURITY_HMAC_SECRET'] ?? '';
        if (empty($secret)) {
            throw new InvalidArgumentException("SECURITY_HMAC_SECRET is not configured.");
        }

        return hash_hmac('sha256', trim($nationalId), $secret, true);
    }

    /**
     * Compute CHAR(64) hex keyed HMAC-SHA-256 hash for rate limit identifier lookup.
     *
     * @param string $normalizedTarget
     * @return string Hex 64 characters
     */
    public static function hashRateLimitIdentifier(string $normalizedTarget): string
    {
        $secret = $_ENV['RATE_LIMIT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new InvalidArgumentException("RATE_LIMIT_SECRET is not configured.");
        }

        return hash_hmac('sha256', trim(strtolower($normalizedTarget)), $secret);
    }

    /**
     * Compute CHAR(64) hex SHA-256 hash for tokens (session tokens, public media tokens).
     *
     * @param string $token
     * @return string Hex 64 characters
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Generate cryptographically secure random token string.
     *
     * @param int $length
     * @return string Hex string of length $length * 2
     */
    public static function generateRandomToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
