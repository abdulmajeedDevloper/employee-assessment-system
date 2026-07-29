<?php

namespace App\Services;

use App\Repositories\EmployeeRepository;
use App\Support\Database;
use App\Validators\EmployeeProfileValidator;
use PDO;

class EmployeeProfileService
{
    private EmployeeRepository $empRepo;
    private EmployeeProfileValidator $validator;
    private PDO $pdo;

    public function __construct(?EmployeeRepository $empRepo = null, ?EmployeeProfileValidator $validator = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->empRepo = $empRepo ?? new EmployeeRepository($this->pdo);

        $deptRepo = new \App\Repositories\DepartmentRepository($this->pdo);
        $jobRepo = new \App\Repositories\JobTitleRepository($this->pdo);

        $this->validator = $validator ?? new EmployeeProfileValidator($deptRepo, $jobRepo);
    }

    public function updateProfile(int $employeeId, array $input, string $ipAddress, string $userAgent): array
    {
        $existing = $this->empRepo->findById($employeeId);
        if (!$existing || (int)$existing['is_active'] !== 1 || !empty($existing['archived_at'])) {
            return [
                'success' => false,
                'message' => 'Employee account inactive or invalid.'
            ];
        }

        $valResult = $this->validator->validateProfileUpdate($input, $existing);
        if (!$valResult['valid']) {
            return [
                'success' => false,
                'errors' => $valResult['errors']
            ];
        }

        $data = $valResult['data'];

        // Determine changed field names only (No values, ciphertext, HMACs, or tokens logged)
        $changedFields = [];
        if ($existing['full_name'] !== $data['full_name']) $changedFields[] = 'full_name';
        if (strtolower($existing['email']) !== $data['email']) $changedFields[] = 'email';
        if ($existing['phone'] !== $data['phone']) $changedFields[] = 'phone';
        if ($existing['preferred_language'] !== $data['preferred_language']) $changedFields[] = 'preferred_language';

        $this->pdo->beginTransaction();
        try {
            // Update profile
            $this->empRepo->updateProfile($employeeId, $data);

            // Audit log: actor_type = EMPLOYEE, store changed field names only in new_values
            $auditStmt = $this->pdo->prepare("
                INSERT INTO audit_logs (actor_type, employee_id, admin_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
                VALUES ('EMPLOYEE', :employee_id, NULL, 'PROFILE_CONFIRMATION_UPDATED', 'employee', :entity_id, NULL, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP())
            ");
            $auditStmt->execute([
                ':employee_id' => $employeeId,
                ':entity_id' => $employeeId,
                ':new_values' => json_encode(['changed_fields' => $changedFields], JSON_UNESCAPED_SLASHES),
                ':ip_address' => $ipAddress,
                ':user_agent' => substr($userAgent, 0, 500)
            ]);

            $this->pdo->commit();

            // Update session preferred_language & profile_completed flag
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['employee_auth'])) {
                $_SESSION['employee_auth']['preferred_language'] = $data['preferred_language'];
                $_SESSION['employee_auth']['profile_completed'] = true;
            }

            return [
                'success' => true,
                'message' => 'Profile details updated and confirmed successfully.'
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ];
        }
    }
}
