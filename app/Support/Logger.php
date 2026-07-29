<?php

namespace App\Support;

class Logger
{
    /**
     * Log message to system application log file.
     *
     * @param string $level INFO | WARNING | ERROR | CRITICAL
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/app.log';
        $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
        $contextJson = !empty($context) ? ' ' . json_encode(self::sanitizeContext($context), JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextJson}" . PHP_EOL;

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log security audit event to security log file.
     *
     * @param string $eventType
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function security(string $eventType, string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/security.log';
        $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
        $contextJson = !empty($context) ? ' ' . json_encode(self::sanitizeContext($context), JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [SECURITY:{$eventType}] {$message}{$contextJson}" . PHP_EOL;

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Mask sensitive keys in context payload.
     *
     * @param array $context
     * @return array
     */
    private static function sanitizeContext(array $context): array
    {
        $sensitiveKeys = ['password', 'national_id', 'national_id_ciphertext', 'secret', 'token', '_csrf_token'];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
                $context[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $context[$key] = self::sanitizeContext($value);
            }
        }
        return $context;
    }
}
