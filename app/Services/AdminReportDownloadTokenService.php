<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class AdminReportDownloadTokenService
{
    private const SESSION_KEY = 'admin_report_download_tokens';
    private const MAX_SESSION_TOKENS = 20;

    public function __construct(private ?string $storageDirectory = null)
    {
        $this->storageDirectory ??= $this->resolveStorageDirectory();
    }

    /**
     * Issue a short-lived, one-time download token bound to one administrator.
     */
    public function issue(
        int $adminId,
        string $storedFilename,
        string $downloadFilename,
        string $mimeType,
        string $format
    ): string {
        if ($adminId <= 0 || !$this->isSafeStoredFilename($storedFilename)) {
            throw new RuntimeException('Invalid export token metadata.');
        }
        if (!in_array($format, ['xlsx', 'pdf'], true)) {
            throw new RuntimeException('Unsupported export format.');
        }

        $path = $this->secureFilePath($storedFilename);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Export file is unavailable.');
        }

        $this->ensureSession();
        $this->cleanupExpiredTokens(true);

        $token = bin2hex(random_bytes(32));
        $ttl = $this->tokenTtlSeconds();
        $_SESSION[self::SESSION_KEY][$token] = [
            'admin_id' => $adminId,
            'stored_filename' => $storedFilename,
            'download_filename' => $this->safeDownloadFilename($downloadFilename, $format),
            'mime_type' => $mimeType,
            'format' => $format,
            'size' => (int)filesize($path),
            'sha256' => hash_file('sha256', $path),
            'created_at' => time(),
            'expires_at' => time() + $ttl,
        ];

        $this->enforceTokenLimit();
        return $token;
    }

    /**
     * Consume and validate a one-time download token.
     *
     * @return array{path:string,download_filename:string,mime_type:string,format:string,size:int}|null
     */
    public function consume(string $token, int $adminId): ?array
    {
        if ($adminId <= 0 || strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        $this->ensureSession();
        $this->cleanupExpiredTokens(true);
        $record = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if (!is_array($record) || (int)($record['admin_id'] ?? 0) !== $adminId) {
            return null;
        }
        unset($_SESSION[self::SESSION_KEY][$token]);
        if ((int)($record['expires_at'] ?? 0) < time()) {
            $this->deleteStoredFile((string)($record['stored_filename'] ?? ''));
            return null;
        }

        $storedFilename = (string)($record['stored_filename'] ?? '');
        $path = $this->secureFilePath($storedFilename);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $expectedSize = (int)($record['size'] ?? -1);
        $expectedHash = (string)($record['sha256'] ?? '');
        $actualSize = (int)filesize($path);
        $actualHash = (string)hash_file('sha256', $path);
        if ($expectedSize < 0 || $expectedSize !== $actualSize || $expectedHash === '' || $actualHash === '' || !hash_equals($expectedHash, $actualHash)) {
            @unlink($path);
            return null;
        }

        return [
            'path' => $path,
            'download_filename' => (string)$record['download_filename'],
            'mime_type' => (string)$record['mime_type'],
            'format' => (string)$record['format'],
            'size' => $actualSize,
        ];
    }

    public function revoke(string $token, bool $deleteFile = true): void
    {
        $this->ensureSession();
        $record = $_SESSION[self::SESSION_KEY][$token] ?? null;
        unset($_SESSION[self::SESSION_KEY][$token]);
        if ($deleteFile && is_array($record)) {
            $this->deleteStoredFile((string)($record['stored_filename'] ?? ''));
        }
    }

    public function cleanupExpiredTokens(bool $deleteFiles): int
    {
        $this->ensureSession();
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($tokens)) {
            $_SESSION[self::SESSION_KEY] = [];
            return 0;
        }

        $removed = 0;
        $now = time();
        foreach ($tokens as $token => $record) {
            if (!is_array($record) || (int)($record['expires_at'] ?? 0) < $now) {
                if ($deleteFiles && is_array($record)) {
                    $this->deleteStoredFile((string)($record['stored_filename'] ?? ''));
                }
                unset($tokens[$token]);
                $removed++;
            }
        }
        $_SESSION[self::SESSION_KEY] = $tokens;
        return $removed;
    }

    private function enforceTokenLimit(): void
    {
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($tokens) || count($tokens) <= self::MAX_SESSION_TOKENS) {
            return;
        }

        uasort($tokens, static fn(array $a, array $b): int => (int)($a['created_at'] ?? 0) <=> (int)($b['created_at'] ?? 0));
        while (count($tokens) > self::MAX_SESSION_TOKENS) {
            $record = array_shift($tokens);
            if (is_array($record)) {
                $this->deleteStoredFile((string)($record['stored_filename'] ?? ''));
            }
        }
        $_SESSION[self::SESSION_KEY] = $tokens;
    }

    private function tokenTtlSeconds(): int
    {
        $ttl = (int)($_ENV['REPORT_DOWNLOAD_TOKEN_TTL_SECONDS'] ?? 600);
        return max(60, min(3600, $ttl));
    }

    private function resolveStorageDirectory(): string
    {
        $configured = trim((string)($_ENV['REPORT_EXPORT_STORAGE_PATH'] ?? 'storage/exports'));
        if ($configured === '') {
            $configured = 'storage/exports';
        }
        if ($this->isAbsolutePath($configured)) {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured);
    }

    private function secureFilePath(string $storedFilename): ?string
    {
        if (!$this->isSafeStoredFilename($storedFilename)) {
            return null;
        }

        if (is_link((string)$this->storageDirectory)) {
            return null;
        }
        $directory = realpath((string)$this->storageDirectory);
        if ($directory === false || !is_dir($directory)) {
            return null;
        }
        $unresolvedCandidate = $directory . DIRECTORY_SEPARATOR . $storedFilename;
        if (is_link($unresolvedCandidate)) {
            return null;
        }
        $candidate = realpath($unresolvedCandidate);
        if ($candidate === false || !is_file($candidate)) {
            return null;
        }
        $prefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($candidate, $prefix)) {
            return null;
        }
        return $candidate;
    }

    private function deleteStoredFile(string $storedFilename): void
    {
        $path = $this->secureFilePath($storedFilename);
        if ($path !== null) {
            @unlink($path);
        }
    }

    private function isSafeStoredFilename(string $filename): bool
    {
        return preg_match('/^report_[a-f0-9]{32}\.(xlsx|pdf)$/D', $filename) === 1;
    }

    private function safeDownloadFilename(string $filename, string $format): string
    {
        $filename = trim(str_replace(["\r", "\n", '"', "'", '/', '\\'], '', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? '';
        $filename = trim($filename, '.-_');
        if ($filename === '') {
            $filename = 'assessment-report-' . gmdate('Ymd-His');
        }
        $extension = '.' . $format;
        if (!str_ends_with(strtolower($filename), $extension)) {
            $filename .= $extension;
        }
        return substr($filename, 0, 180);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }
}
