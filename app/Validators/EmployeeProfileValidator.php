<?php

namespace App\Validators;

use App\Repositories\DepartmentRepository;
use App\Repositories\JobTitleRepository;

class EmployeeProfileValidator
{
    private DepartmentRepository $deptRepo;
    private JobTitleRepository $jobRepo;

    public function __construct(?DepartmentRepository $deptRepo = null, ?JobTitleRepository $jobRepo = null)
    {
        $this->deptRepo = $deptRepo ?? new DepartmentRepository();
        $this->jobRepo = $jobRepo ?? new JobTitleRepository();
    }

    public static function normalizeSaudiMobile(string $phone): ?string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', trim($phone));

        // Format 1: 05XXXXXXXX (10 digits starting with 05)
        if (preg_match('/^05[0-9]{8}$/', $cleaned)) {
            return '+966' . substr($cleaned, 1);
        }

        // Format 2: 9665XXXXXXXX (12 digits starting with 9665)
        if (preg_match('/^9665[0-9]{8}$/', $cleaned)) {
            return '+' . $cleaned;
        }

        // Format 3: +9665XXXXXXXX (13 chars starting with +9665)
        if (preg_match('/^\+9665[0-9]{8}$/', $cleaned)) {
            return $cleaned;
        }

        // Format 4: 009665XXXXXXXX (14 digits starting with 009665)
        if (preg_match('/^009665[0-9]{8}$/', $cleaned)) {
            return '+' . substr($cleaned, 2);
        }

        return null;
    }

    public function validateProfileUpdate(array $input, array $existingEmployee): array
    {
        $errors = [];
        $validated = [];

        // Mass assignment protection: whitelist editable fields
        $fullName = trim((string)($input['full_name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $phoneInput = (string)($input['phone'] ?? '');
        $lang = strtolower(trim((string)($input['preferred_language'] ?? 'ar')));

        // 1. Validate full name
        if (empty($fullName) || mb_strlen($fullName) < 3 || mb_strlen($fullName) > 255) {
            $errors['full_name'] = 'Full name is required (between 3 and 255 characters).';
        } else {
            $validated['full_name'] = $fullName;
        }

        // 2. Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'Invalid email address format.';
        } else {
            $validated['email'] = $email;
        }

        // 3. Validate & Normalize Mobile Number to E.164 (+9665XXXXXXXX)
        $normalizedPhone = self::normalizeSaudiMobile($phoneInput);
        if ($normalizedPhone === null) {
            $errors['phone'] = 'Invalid Saudi mobile number format. Must be a valid Saudi mobile number (e.g. 0512345678).';
        } else {
            $validated['phone'] = $normalizedPhone;
        }

        // 4. Validate Preferred Language
        if (!in_array($lang, ['ar', 'en'], true)) {
            $validated['preferred_language'] = 'ar';
        } else {
            $validated['preferred_language'] = $lang;
        }

        // 5. Organizational fields validation (Read-only confirmation, department/section ownership check)
        $deptId = (int)($existingEmployee['department_id'] ?? 0);
        $sectionId = (int)($existingEmployee['section_id'] ?? 0);
        $jobId = (int)($existingEmployee['job_title_id'] ?? 0);

        $section = $this->deptRepo->getSectionById($sectionId);
        if (!$section || (int)$section['is_active'] !== 1) {
            $errors['section_id'] = 'Assigned section is inactive or invalid.';
        } elseif ((int)$section['department_id'] !== $deptId) {
            $errors['section_id'] = 'Assigned section does not belong to the selected department.';
        }

        $jobTitle = $this->jobRepo->getJobTitleById($jobId);
        if (!$jobTitle || (int)$jobTitle['is_active'] !== 1) {
            $errors['job_title_id'] = 'Assigned job title is inactive or invalid.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        ];
    }
}
