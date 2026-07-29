<?php

namespace App\Validators;

use App\Validators\EmployeeProfileValidator;

class AdminEmployeeFormValidator
{
    /**
     * Parse strict positive integer (rejects arrays, floats, negatives, signs, booleans, scientific notation).
     *
     * @param mixed $val
     * @return int|null
     */
    public static function parseStrictPositiveInt(mixed $val): ?int
    {
        if (is_int($val) && $val >= 1) {
            return $val;
        }
        if (is_string($val) && preg_match('/^[1-9][0-9]*$/', $val) === 1) {
            return (int)$val;
        }
        return null;
    }

    /**
     * Validate payload for Employee Creation.
     *
     * @param array $input
     * @param array $activeDepts List of active department arrays (must have 'id')
     * @param array $activeSections List of active section arrays (must have 'id', 'department_id')
     * @param array $activeJobTitles List of active job title arrays (must have 'id')
     * @return array{valid: bool, errors: array<string, string>, data: array}
     */
    public static function validateCreate(array $input, array $activeDepts, array $activeSections, array $activeJobTitles): array
    {
        $errors = [];
        $validated = [];

        // 1. Full Name
        $fullName = isset($input['full_name']) && is_string($input['full_name']) ? trim($input['full_name']) : '';
        if ($fullName === '' || mb_strlen($fullName) < 3 || mb_strlen($fullName) > 255) {
            $errors['full_name'] = trans('val_full_name_required');
        } else {
            $validated['full_name'] = $fullName;
        }

        // 2. National ID (Required on creation, exactly 10 ASCII digits)
        $nationalId = isset($input['national_id']) && is_string($input['national_id']) ? trim($input['national_id']) : '';
        if ($nationalId === '' || preg_match('/^[0-9]{10}$/', $nationalId) !== 1) {
            $errors['national_id'] = trans('val_national_id_invalid');
        } else {
            $validated['national_id'] = $nationalId;
        }

        // 3. Employee Number (Optional)
        $empNumber = isset($input['employee_number']) && is_string($input['employee_number']) ? trim($input['employee_number']) : '';
        if ($empNumber === '') {
            $validated['employee_number'] = null;
        } else {
            if (mb_strlen($empNumber) > 64 || preg_match('/[\x00-\x1F\x7F]/', $empNumber) === 1) {
                $errors['employee_number'] = trans('val_employee_number_invalid');
            } else {
                $validated['employee_number'] = $empNumber;
            }
        }

        // 4. Email
        $email = isset($input['email']) && is_string($input['email']) ? strtolower(trim($input['email'])) : '';
        if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = trans('val_email_invalid');
        } else {
            $validated['email'] = $email;
        }

        // 5. Phone (Saudi mobile format normalized via EmployeeProfileValidator)
        $phoneInput = isset($input['phone']) && is_string($input['phone']) ? trim($input['phone']) : '';
        $normalizedPhone = EmployeeProfileValidator::normalizeSaudiMobile($phoneInput);
        if ($normalizedPhone === null) {
            $errors['phone'] = trans('val_phone_invalid');
        } else {
            $validated['phone'] = $normalizedPhone;
        }

        // 6. Department ID
        $deptId = self::parseStrictPositiveInt($input['department_id'] ?? null);
        $deptMap = [];
        foreach ($activeDepts as $d) {
            $deptMap[(int)$d['id']] = $d;
        }
        if ($deptId === null || !isset($deptMap[$deptId])) {
            $errors['department_id'] = trans('val_department_invalid');
        } else {
            $validated['department_id'] = $deptId;
        }

        // 7. Section ID (must belong to department_id)
        $sectionId = self::parseStrictPositiveInt($input['section_id'] ?? null);
        $sectionMap = [];
        foreach ($activeSections as $s) {
            $sectionMap[(int)$s['id']] = $s;
        }
        if ($sectionId === null || !isset($sectionMap[$sectionId])) {
            $errors['section_id'] = trans('val_section_invalid');
        } else {
            $sectionObj = $sectionMap[$sectionId];
            if ($deptId !== null && (int)$sectionObj['department_id'] !== $deptId) {
                $errors['section_id'] = trans('val_section_dept_mismatch');
            } else {
                $validated['section_id'] = $sectionId;
            }
        }

        // 8. Job Title ID
        $jobId = self::parseStrictPositiveInt($input['job_title_id'] ?? null);
        $jobMap = [];
        foreach ($activeJobTitles as $j) {
            $jobMap[(int)$j['id']] = $j;
        }
        if ($jobId === null || !isset($jobMap[$jobId])) {
            $errors['job_title_id'] = trans('val_job_title_invalid');
        } else {
            $validated['job_title_id'] = $jobId;
        }

        // 9. Preferred Language
        $lang = isset($input['preferred_language']) && is_string($input['preferred_language']) ? strtolower(trim($input['preferred_language'])) : 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $errors['preferred_language'] = trans('val_language_invalid');
        } else {
            $validated['preferred_language'] = $lang;
        }

        // 10. Status (is_active)
        $rawActive = $input['is_active'] ?? null;
        if ($rawActive === 1 || $rawActive === '1') {
            $validated['is_active'] = 1;
        } elseif ($rawActive === 0 || $rawActive === '0') {
            $validated['is_active'] = 0;
        } else {
            $errors['is_active'] = trans('val_status_invalid');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated,
        ];
    }

    /**
     * Validate payload for Employee Editing.
     *
     * @param array $input
     * @param array $existingEmployee
     * @param array $activeDepts
     * @param array $activeSections
     * @param array $activeJobTitles
     * @return array{valid: bool, errors: array<string, string>, data: array}
     */
    public static function validateEdit(array $input, array $existingEmployee, array $activeDepts, array $activeSections, array $activeJobTitles): array
    {
        $res = self::validateCreate($input, $activeDepts, $activeSections, $activeJobTitles);
        $errors = $res['errors'];
        $validated = $res['data'];

        // On edit, national_id is NOT required unless replace_national_id is checked
        unset($errors['national_id'], $validated['national_id']);

        $replaceNationalId = !empty($input['replace_national_id']);
        $validated['replace_national_id'] = $replaceNationalId;

        if ($replaceNationalId) {
            $nationalId = isset($input['national_id']) && is_string($input['national_id']) ? trim($input['national_id']) : '';
            if ($nationalId === '' || preg_match('/^[0-9]{10}$/', $nationalId) !== 1) {
                $errors['national_id'] = trans('val_national_id_invalid');
            } else {
                $validated['national_id'] = $nationalId;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated,
        ];
    }
}
