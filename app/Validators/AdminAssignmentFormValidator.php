<?php

declare(strict_types=1);

namespace App\Validators;

use DateTimeImmutable;
use DateTimeZone;

class AdminAssignmentFormValidator
{
    /**
     * @return array{valid:bool,errors:array,old:array,data?:array}
     */
    public function validateCreate(array $input, array $employees, array $tests): array
    {
        return $this->validate($input, $employees, $tests, true, 0);
    }

    /**
     * @return array{valid:bool,errors:array,old:array,data?:array}
     */
    public function validateUpdate(array $input, int $attemptCount = 0): array
    {
        return $this->validate($input, [], [], false, $attemptCount);
    }

    private function validate(array $input, array $employees, array $tests, bool $isCreate, int $attemptCount): array
    {
        $old = [
            'employee_id' => $this->scalarString($input['employee_id'] ?? ''),
            'test_id' => $this->scalarString($input['test_id'] ?? ''),
            'max_attempts' => $this->scalarString($input['max_attempts'] ?? '1'),
            'available_from' => $this->scalarString($input['available_from'] ?? ''),
            'available_until' => $this->scalarString($input['available_until'] ?? ''),
        ];
        $errors = [];

        $employeeId = $this->positiveInt($input['employee_id'] ?? null);
        $testId = $this->positiveInt($input['test_id'] ?? null);

        if ($isCreate) {
            $employeeIds = array_map(static fn(array $row): int => (int)$row['id'], $employees);
            $testIds = array_map(static fn(array $row): int => (int)$row['id'], $tests);
            if ($employeeId === null || !in_array($employeeId, $employeeIds, true)) {
                $errors['employee_id'] = trans('assignment_employee_required');
            }
            if ($testId === null || !in_array($testId, $testIds, true)) {
                $errors['test_id'] = trans('assignment_test_required');
            }
        }

        $maxAttempts = $this->positiveInt($input['max_attempts'] ?? null);
        if ($maxAttempts === null || $maxAttempts < 1 || $maxAttempts > 20) {
            $errors['max_attempts'] = trans('invalid_assignment_max_attempts');
        } elseif ($maxAttempts < $attemptCount) {
            $errors['max_attempts'] = trans('assignment_attempts_below_used', ['count' => (string)$attemptCount]);
        }

        $availableFrom = $this->parseLocalDateTime($input['available_from'] ?? null, 'available_from', $errors);
        $availableUntil = $this->parseLocalDateTime($input['available_until'] ?? null, 'available_until', $errors);

        if ($availableFrom !== null && $availableUntil !== null && $availableFrom >= $availableUntil) {
            $errors['available_until'] = trans('availability_end_after_start');
        }
        if ($availableUntil !== null) {
            $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            if ($availableUntil <= $nowUtc) {
                $errors['available_until'] = trans('assignment_end_in_future');
            }
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors, 'old' => $old];
        }

        return [
            'valid' => true,
            'errors' => [],
            'old' => $old,
            'data' => [
                'employee_id' => $employeeId,
                'test_id' => $testId,
                'max_attempts' => $maxAttempts,
                'available_from' => $availableFrom?->format('Y-m-d H:i:s'),
                'available_until' => $availableUntil?->format('Y-m-d H:i:s'),
            ],
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', trim($value))) {
            return null;
        }
        $parsed = filter_var(trim($value), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $parsed === false ? null : (int)$parsed;
    }

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

        foreach (['Y-m-d\\TH:i', 'Y-m-d\\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
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
