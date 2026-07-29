<?php

use App\Controllers\Employee\AuthController;
use App\Controllers\Employee\ProfileController;
use App\Controllers\Employee\AssessmentStatusController;
use App\Controllers\Employee\AssessmentAttemptController;
use App\Controllers\Admin\AuthController as AdminAuthController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\EmployeeController;
use App\Controllers\Admin\TestController;
use App\Controllers\Admin\QuestionController;
use App\Controllers\Admin\AssignmentController;
use App\Controllers\Admin\AttemptController;
use App\Controllers\Admin\ReportController;

return [
    'GET' => [
        '/employee/login' => [AuthController::class, 'showLogin'],
        '/employee/profile' => [ProfileController::class, 'showProfile'],
        '/employee/tests' => [AssessmentStatusController::class, 'showStatus'],
        '/employee/attempt' => [AssessmentAttemptController::class, 'show'],
        '/admin/login' => [AdminAuthController::class, 'showLogin'],
        '/admin' => [AdminDashboardController::class, 'index'],
        '/admin/dashboard' => [AdminDashboardController::class, 'index'],
        '/admin/employees' => [EmployeeController::class, 'index'],
        '/admin/employees/create' => [EmployeeController::class, 'create'],
        '/admin/employees/edit' => [EmployeeController::class, 'edit'],
        '/admin/employees/view' => [EmployeeController::class, 'show'],
        '/admin/tests' => [TestController::class, 'index'],
        '/admin/tests/create' => [TestController::class, 'create'],
        '/admin/tests/edit' => [TestController::class, 'edit'],
        '/admin/tests/lifecycle' => [TestController::class, 'lifecycle'],
        '/admin/tests/view' => [TestController::class, 'show'],
        '/admin/questions' => [QuestionController::class, 'index'],
        '/admin/questions/create' => [QuestionController::class, 'create'],
        '/admin/questions/edit' => [QuestionController::class, 'edit'],
        '/admin/questions/categories/create' => [QuestionController::class, 'categoryCreate'],
        '/admin/questions/view' => [QuestionController::class, 'show'],
        '/admin/questions/media' => [QuestionController::class, 'media'],
        '/admin/assignments' => [AssignmentController::class, 'index'],
        '/admin/assignments/create' => [AssignmentController::class, 'create'],
        '/admin/assignments/edit' => [AssignmentController::class, 'edit'],
        '/admin/assignments/view' => [AssignmentController::class, 'show'],
        '/admin/attempts' => [AttemptController::class, 'index'],
        '/admin/attempts/view' => [AttemptController::class, 'show'],
        '/admin/reports' => [ReportController::class, 'index'],
        '/admin/reports/download' => [ReportController::class, 'download'],
    ],
    'POST' => [
        '/employee/login' => [AuthController::class, 'login'],
        '/employee/logout' => [AuthController::class, 'logout'],
        '/employee/profile' => [ProfileController::class, 'updateProfile'],
        '/employee/tests/start' => [AssessmentAttemptController::class, 'start'],
        '/employee/attempt/answer' => [AssessmentAttemptController::class, 'saveAnswer'],
        '/employee/attempt/submit' => [AssessmentAttemptController::class, 'submit'],
        '/employee/attempt/timeout' => [AssessmentAttemptController::class, 'timeout'],
        '/employee/attempt/focus-loss' => [AssessmentAttemptController::class, 'focusLoss'],
        '/admin/login' => [AdminAuthController::class, 'login'],
        '/admin/logout' => [AdminAuthController::class, 'logout'],
        '/admin/employees/create' => [EmployeeController::class, 'store'],
        '/admin/employees/edit' => [EmployeeController::class, 'update'],
        '/admin/employees/archive' => [EmployeeController::class, 'archive'],
        '/admin/employees/restore' => [EmployeeController::class, 'restore'],
        '/admin/tests/create' => [TestController::class, 'store'],
        '/admin/tests/edit' => [TestController::class, 'update'],
        '/admin/tests/lifecycle' => [TestController::class, 'updateLifecycle'],
        '/admin/questions/create' => [QuestionController::class, 'store'],
        '/admin/questions/edit' => [QuestionController::class, 'update'],
        '/admin/questions/archive' => [QuestionController::class, 'archive'],
        '/admin/questions/restore' => [QuestionController::class, 'restore'],
        '/admin/questions/categories/create' => [QuestionController::class, 'categoryStore'],
        '/admin/questions/media/upload' => [QuestionController::class, 'mediaUpload'],
        '/admin/questions/media/remove' => [QuestionController::class, 'mediaRemove'],
        '/admin/assignments/create' => [AssignmentController::class, 'store'],
        '/admin/assignments/edit' => [AssignmentController::class, 'update'],
        '/admin/attempts/cancel' => [AttemptController::class, 'cancel'],
        '/admin/attempts/retake' => [AttemptController::class, 'authorizeRetake'],
        '/admin/attempts/notes' => [AttemptController::class, 'updateNotes'],
        '/admin/attempts/finalize-expired' => [AttemptController::class, 'finalizeExpired'],
        '/admin/reports/export' => [ReportController::class, 'export'],
    ]
];
