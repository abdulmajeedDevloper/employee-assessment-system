<?php

namespace Tests\Integration;

use App\Repositories\AdminTestRepository;
use App\Services\AdminTestManagementService;
use App\Support\Database;
use App\Validators\AdminTestFormValidator;
use App\Validators\AdminTestLifecycleValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminTestLifecycleTest extends TestCase
{
    private PDO $pdo;
    private AdminTestRepository $repository;
    private AdminTestManagementService $service;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Database::getInstance();
        $this->repository = new AdminTestRepository($this->pdo);
        $this->service = new AdminTestManagementService(
            $this->repository,
            new AdminTestFormValidator(),
            $this->pdo,
            new AdminTestLifecycleValidator()
        );
        $_SESSION = [];
        $this->cleanup();
        $this->adminId = $this->ensureAdmin();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testActivationIsBlockedUntilQuestionBankIsReady(): void
    {
        $testId = $this->createTest('UNIT-LIFE-NOTREADY', 2);
        $row = $this->repository->findSafeById($testId);
        $token = $this->service->issueEditToken($this->adminId, $testId, $row);

        $result = $this->service->updateLifecycle($testId, $token, [
            'is_active' => '1',
            'available_from' => '',
            'available_until' => '',
        ], $this->adminId);

        $this->assertSame('NOT_READY', $result['code']);
        $this->assertSame(0, $result['readiness']['valid_question_count']);
        $this->assertSame(0, (int)$this->repository->findSafeById($testId)['is_active']);
    }

    public function testValidQuestionsAllowActivationAndAuditContainsOnlyFieldNames(): void
    {
        $testId = $this->createTest('UNIT-LIFE-READY', 2);
        $categoryId = $this->createCategory($testId);
        $this->createValidQuestion($testId, $categoryId, 'Question 1');
        $this->createValidQuestion($testId, $categoryId, 'Question 2');

        $readiness = $this->repository->getLifecycleReadiness($testId);
        $this->assertTrue($readiness['is_ready']);
        $this->assertSame(2, $readiness['valid_question_count']);

        $row = $this->repository->findSafeById($testId);
        $token = $this->service->issueEditToken($this->adminId, $testId, $row);
        $result = $this->service->updateLifecycle($testId, $token, [
            'is_active' => '1',
            'available_from' => '',
            'available_until' => '',
        ], $this->adminId);

        $this->assertSame('SUCCESS', $result['code']);
        $this->assertSame(1, (int)$this->repository->findSafeById($testId)['is_active']);

        $stmt = $this->pdo->prepare("SELECT old_values, new_values FROM audit_logs WHERE action = 'UPDATE_TEST_LIFECYCLE' AND entity_type = 'TEST' AND entity_id = :id ORDER BY id DESC LIMIT 1");
        $stmt->execute([':id' => $testId]);
        $audit = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($audit);
        $old = json_decode($audit['old_values'], true);
        $new = json_decode($audit['new_values'], true);
        $this->assertSame(['changed_field_names'], array_keys($old));
        $this->assertSame(['changed_field_names'], array_keys($new));
        $this->assertSame(['is_active'], $new['changed_field_names']);
    }

    public function testAvailabilityValidationRejectsInvalidRange(): void
    {
        $validator = new AdminTestLifecycleValidator();
        $result = $validator->validate([
            'is_active' => '0',
            'available_from' => '2030-05-02T12:00',
            'available_until' => '2030-05-01T12:00',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('available_until', $result['errors']);
    }

    public function testStaleLifecycleTokenIsRejected(): void
    {
        $testId = $this->createTest('UNIT-LIFE-CONFLICT', 1);
        $row = $this->repository->findSafeById($testId);
        $token = $this->service->issueEditToken($this->adminId, $testId, $row);
        $this->pdo->prepare("UPDATE tests SET available_from = '2030-01-01 00:00:00', updated_at = UTC_TIMESTAMP() WHERE id = :id")
            ->execute([':id' => $testId]);

        $result = $this->service->updateLifecycle($testId, $token, [
            'is_active' => '0',
            'available_from' => '',
            'available_until' => '',
        ], $this->adminId);

        $this->assertSame('CONFLICT', $result['code']);
    }

    private function createTest(string $code, int $questionCount): int
    {
        $result = $this->service->createTest([
            'code' => $code,
            'name_ar' => 'اختبار دورة الحياة',
            'name_en' => 'Lifecycle Assessment',
            'duration_minutes' => '30',
            'question_count' => (string)$questionCount,
            'passing_percentage' => '60.00',
            'display_order' => '1',
        ], $this->adminId);
        $this->assertSame('SUCCESS', $result['code']);
        return (int)$result['test_id'];
    }

    private function createCategory(int $testId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO question_categories (test_id, name_ar, name_en, is_active, created_at) VALUES (:test_id, 'تصنيف', 'Category', 1, UTC_TIMESTAMP())");
        $stmt->execute([':test_id' => $testId]);
        return (int)$this->pdo->lastInsertId();
    }

    private function createValidQuestion(int $testId, int $categoryId, string $suffix): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO questions (test_id, category_id, type, text_ar, text_en, difficulty, score, is_active, archived_at, created_by, created_at, updated_at) VALUES (:test_id, :category_id, 'single_choice', :text_ar, :text_en, 'medium', 1.00, 1, NULL, :created_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmt->execute([
            ':test_id' => $testId,
            ':category_id' => $categoryId,
            ':text_ar' => 'سؤال ' . $suffix,
            ':text_en' => $suffix,
            ':created_by' => $this->adminId,
        ]);
        $questionId = (int)$this->pdo->lastInsertId();

        $option = $this->pdo->prepare("INSERT INTO question_options (question_id, option_key, text_ar, text_en, is_correct, display_order) VALUES (:question_id, :option_key, :text_ar, :text_en, :is_correct, :display_order)");
        foreach ([['A', 1, 1], ['B', 0, 2]] as [$key, $correct, $order]) {
            $option->execute([
                ':question_id' => $questionId,
                ':option_key' => $key,
                ':text_ar' => 'خيار ' . $key,
                ':text_en' => 'Option ' . $key,
                ':is_correct' => $correct,
                ':display_order' => $order,
            ]);
        }
    }

    private function ensureAdmin(): int
    {
        $id = $this->pdo->query('SELECT id FROM admins ORDER BY id LIMIT 1')->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $stmt = $this->pdo->prepare("INSERT INTO admins (name, email, username, password_hash, role, is_active, created_at, updated_at) VALUES ('Lifecycle Admin', 'lifecycle@test.local', 'lifecycle_admin', :password_hash, 'super_admin', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $stmt->execute([':password_hash' => password_hash('TestPassword123!', PASSWORD_BCRYPT)]);
        return (int)$this->pdo->lastInsertId();
    }

    private function cleanup(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec("DELETE FROM audit_logs WHERE entity_type = 'TEST' AND entity_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-LIFE-%')");
        $this->pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-LIFE-%'))");
        $this->pdo->exec("DELETE FROM questions WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-LIFE-%')");
        $this->pdo->exec("DELETE FROM question_categories WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-LIFE-%')");
        $this->pdo->exec("DELETE FROM tests WHERE code LIKE 'UNIT-LIFE-%'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
