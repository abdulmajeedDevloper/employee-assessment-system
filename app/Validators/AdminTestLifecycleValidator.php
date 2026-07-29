<?php

namespace App\Validators;

use DateTimeImmutable;
use DateTimeZone;

class AdminTestLifecycleValidator
{
    /**
     * Validate and normalize assessment lifecycle input.
     * Datetime-local values are interpreted in APP_TIMEZONE and stored as UTC.
     *
     * @param array $input
     * @return array{valid:bool,errors:array,old:array,data?:array}
     */
    public function validate(array $input): array
    {
        $old = [
            'is_active' => $this->scalarString($input['is_active'] ?? '0'),
            'available_from' => $this->scalarString($input['available_from'] ?? ''),
            'available_until' => $this->scalarString($input['available_until'] ?? ''),
        ];

        $errors = [];
        $isActive = $this->parseBoolean($input['is_active'] ?? null);
        if ($isActive === null) {
            $errors['is_active'] = trans('invalid_lifecycle_status');
        }

        $availableFrom = $this->parseLocalDateTime(
            $input['available_from'] ?? null,
            'available_from',
            $errors
        );
        $availableUntil = $this->parseLocalDateTime(
            $input['available_until'] ?? null,
            'available_until',
            $errors
        );

        if ($availableFrom !== null && $availableUntil !== null && $availableFrom >= $availableUntil) {
            $errors['available_until'] = trans('availability_end_after_start');
        }

        if ($isActive === 1 && $availableUntil !== null) {
            $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            if ($availableUntil <= $nowUtc) {
                $errors['available_until'] = trans('availability_end_in_future');
            }
        }

        if ($errors !== []) {
            return [
                'valid' => false,
                'errors' => $errors,
                'old' => $old,
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'old' => $old,
            'data' => [
                'is_active' => $isActive,
                'available_from' => $availableFrom?->format('Y-m-d H:i:s'),
                'available_until' => $availableUntil?->format('Y-m-d H:i:s'),
            ],
        ];
    }

    private function parseBoolean(mixed $value): ?int
    {
        if ($value === 0 || $value === '0') {
            return 0;
        }
        if ($value === 1 || $value === '1') {
            return 1;
        }
        return null;
    }

    /**
     * @param mixed $value
     * @param string $field
     * @param array $errors
     */
    private function parseLocalDateTime(mixed $value, string $field, array &$errors): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            $errors[$field] = trans('invalid_availability_datetime');
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezoneName = (string)env('APP_TIMEZONE', 'Asia/Riyadh');
        try {
            $localTimezone = new DateTimeZone($timezoneName);
        } catch (\Throwable) {
            $localTimezone = new DateTimeZone('Asia/Riyadh');
        }

        $formats = ['Y-m-d\\TH:i', 'Y-m-d\\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $localTimezone);
            $dateErrors = DateTimeImmutable::getLastErrors();
            $hasErrors = is_array($dateErrors)
                && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0);

            if ($parsed !== false && !$hasErrors && $parsed->format($format) === $value) {
                return $parsed->setTimezone(new DateTimeZone('UTC'));
            }
        }

        $errors[$field] = trans('invalid_availability_datetime');
        return null;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
