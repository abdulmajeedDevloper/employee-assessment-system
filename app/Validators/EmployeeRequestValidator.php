<?php

namespace App\Validators;

class EmployeeRequestValidator
{
    public static function validateNationalId(string $nationalId): array
    {
        $errors = [];
        $trimmed = trim($nationalId);

        if (empty($trimmed)) {
            $errors[] = 'National ID is required.';
        } elseif (!preg_match('/^[0-9]{10}$/', $trimmed)) {
            $errors[] = 'National ID must be a 10-digit numeric string.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'normalized' => $trimmed
        ];
    }
}
