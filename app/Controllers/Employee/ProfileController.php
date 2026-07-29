<?php

namespace App\Controllers\Employee;

use App\Middleware\CsrfMiddleware;
use App\Middleware\EmployeeAuthMiddleware;
use App\Repositories\DepartmentRepository;
use App\Repositories\JobTitleRepository;
use App\Services\EmployeeProfileService;

class ProfileController
{
    private EmployeeProfileService $profileService;
    private DepartmentRepository $deptRepo;
    private JobTitleRepository $jobRepo;

    public function __construct(
        ?EmployeeProfileService $profileService = null,
        ?DepartmentRepository $deptRepo = null,
        ?JobTitleRepository $jobRepo = null
    ) {
        $this->profileService = $profileService ?? new EmployeeProfileService();
        $this->deptRepo = $deptRepo ?? new DepartmentRepository();
        $this->jobRepo = $jobRepo ?? new JobTitleRepository();
    }

    public function showProfile(): void
    {
        $employee = EmployeeAuthMiddleware::handle(true); // Allow profile incomplete access

        $lang = $_GET['lang'] ?? $_SESSION['employee_auth']['preferred_language'] ?? $_SESSION['lang'] ?? 'ar';
        if (!in_array($lang, ['ar', 'en'], true)) {
            $lang = 'ar';
        }
        $_SESSION['lang'] = $lang;

        $translations = require __DIR__ . "/../../../lang/{$lang}.php";

        $departments = $this->deptRepo->getActiveDepartments();
        $sections = $this->deptRepo->getActiveSectionsByDepartment((int)$employee['department_id']);
        $jobTitles = $this->jobRepo->getActiveJobTitles();

        $errors = $_SESSION['flash_errors'] ?? [];
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_errors'], $_SESSION['flash_success']);

        require __DIR__ . '/../../../views/employee/profile.php';
    }

    public function updateProfile(): void
    {
        $employee = EmployeeAuthMiddleware::handle(true);
        CsrfMiddleware::handle();

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $res = $this->profileService->updateProfile((int)$employee['id'], $_POST, $ipAddress, $userAgent);

        if (!$res['success']) {
            $_SESSION['flash_errors'] = $res['errors'] ?? ['general' => $res['message']];
            header('Location: /employee/profile', true, 303);
            exit(0);
        }

        $_SESSION['flash_success'] = trans('profile_updated_successfully');

        // PRG Pattern: HTTP 303 Redirect to Assessment Router
        header('Location: /employee/tests', true, 303);
        exit(0);
    }
}
