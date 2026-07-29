<?php

namespace App\Middleware;

use App\Support\Database;
use App\Security\HashManager;
use PDO;

class RateLimitMiddleware
{
    /**
     * Check if rate limit threshold is exceeded for given action, target identifier, and IP.
     *
     * @param string $actionKey
     * @param string $normalizedTarget
     * @param string $ip
     * @return bool True if locked out, false if clear
     */
    public static function isLocked(string $actionKey, string $normalizedTarget, string $ip): bool
    {
        $pdo = Database::getInstance();
        $maxAttempts = (int)($_ENV['RATE_LIMIT_MAX_FAILED_ATTEMPTS'] ?? 5);
        $lockoutMinutes = (int)($_ENV['RATE_LIMIT_LOCKOUT_MINUTES'] ?? 15);

        $identifierHash = HashManager::hashRateLimitIdentifier($normalizedTarget);

        // Check Target Identifier failures across all IPs
        $stmtTarget = $pdo->prepare("
            SELECT COUNT(*) AS fail_count
            FROM rate_limits
            WHERE identifier_hash = :hash
              AND action_key = :action
              AND is_success = 0
              AND created_at >= UTC_TIMESTAMP() - INTERVAL :minutes MINUTE
        ");
        $stmtTarget->bindValue(':hash', $identifierHash, PDO::PARAM_STR);
        $stmtTarget->bindValue(':action', $actionKey, PDO::PARAM_STR);
        $stmtTarget->bindValue(':minutes', $lockoutMinutes, PDO::PARAM_INT);
        $stmtTarget->execute();
        $targetFails = (int)$stmtTarget->fetchColumn();

        if ($targetFails >= $maxAttempts) {
            return true;
        }

        // Check IP Address failures across all targets
        $stmtIp = $pdo->prepare("
            SELECT COUNT(*) AS fail_count
            FROM rate_limits
            WHERE ip_address = :ip
              AND action_key = :action
              AND is_success = 0
              AND created_at >= UTC_TIMESTAMP() - INTERVAL :minutes MINUTE
        ");
        $stmtIp->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmtIp->bindValue(':action', $actionKey, PDO::PARAM_STR);
        $stmtIp->bindValue(':minutes', $lockoutMinutes, PDO::PARAM_INT);
        $stmtIp->execute();
        $ipFails = (int)$stmtIp->fetchColumn();

        return ($ipFails >= $maxAttempts);
    }

    /**
     * Record an authentication attempt outcome.
     *
     * @param string $actionKey
     * @param string $normalizedTarget
     * @param string $ip
     * @param bool $isSuccess
     * @return void
     */
    public static function recordAttempt(string $actionKey, string $normalizedTarget, string $ip, bool $isSuccess): void
    {
        $pdo = Database::getInstance();
        $identifierHash = HashManager::hashRateLimitIdentifier($normalizedTarget);

        $stmt = $pdo->prepare("
            INSERT INTO rate_limits (identifier_hash, ip_address, action_key, is_success, created_at)
            VALUES (:hash, :ip, :action, :is_success, UTC_TIMESTAMP())
        ");
        $stmt->execute([
            ':hash' => $identifierHash,
            ':ip' => $ip,
            ':action' => $actionKey,
            ':is_success' => $isSuccess ? 1 : 0,
        ]);
    }
}
