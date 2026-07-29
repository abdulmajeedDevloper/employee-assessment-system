<?php

declare(strict_types=1);

namespace App\Support;

final class ReportExportSanitizer
{
    /**
     * Convert arbitrary scalar data to safe, valid UTF-8 display text.
     */
    public static function text(mixed $value, int $maxLength = 1000): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $text = $value ? '1' : '0';
        } elseif (is_scalar($value)) {
            $text = (string)$value;
        } else {
            $text = '';
        }

        // Remove control characters except tab/newline before normalizing whitespace.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        if ($maxLength > 0 && mb_strlen($text, 'UTF-8') > $maxLength) {
            $text = mb_substr($text, 0, $maxLength, 'UTF-8');
        }

        return $text;
    }

    /**
     * Prevent spreadsheet formula injection while preserving visible text.
     */
    public static function spreadsheetText(mixed $value, int $maxLength = 1000): string
    {
        $text = self::text($value, $maxLength);
        if ($text === '') {
            return '';
        }

        // Spreadsheet applications may treat these leading characters as formulas.
        if (preg_match('/^[\s\x{0009}\x{000A}]*[=+\-@]/u', $text) === 1) {
            return "'" . $text;
        }

        return $text;
    }

    public static function html(mixed $value, int $maxLength = 5000): string
    {
        return htmlspecialchars(
            self::text($value, $maxLength),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
