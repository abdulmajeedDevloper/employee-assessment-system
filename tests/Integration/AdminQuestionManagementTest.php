<?php

namespace Tests\Integration;

use App\Repositories\AdminQuestionRepository;
use App\Services\AdminQuestionManagementService;
use App\Support\Database;
use App\Validators\AdminQuestionFormValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminQuestionManagementTest extends TestCase
{
    private PDO $pdo;
    private AdminQuestionRepository $repository;
    private AdminQuestionFormValidator $validator;
    private AdminQuestionManagementService $service;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Database::getInstance();
        $this->repository = new AdminQuestionRepository($this->pdo);
        $this->validator = new AdminQuestionFormValidator();
        $this->service = new AdminQuestionManagementService($this->repository, $this->validator, $this->pdo);
        $_SESSION = ['lang' => 'en'];
        $this->cleanup();
        $this->adminId = $this->ensureAdmin();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $_SESSION = [];
        parent::tearDown();
    }

    public function testValidatorRequiresTwoToEightOptionsAndOneCorrectAnswer(): void
    {
        $input = $this->validInput(1, 1);
        $result = $this->validator->validate($input);
        $this->assertTrue($result['valid']);
        $this->assertSame('A', $result['data']['options'][0]['option_key']);
        $this->assertSame(1, $result['data']['options'][0]['is_correct']);

        $input['options'] = [['text_ar' => 'واحد', 'text_en' => 'One']];
        $input['correct_option'] = '0';
        $result = $this->validator->validate($input);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('options', $result['errors']);

        $input = $this->validInput(1, 1);
        $input['correct_option'] = '2';
        $result = $this->validator->validate($input);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('correct_option', $result['errors']);
    }

    public function testCreateQuestionPersistsOptionsAndFieldNameOnlyAudit(): void
    {
        [$testId, $categoryId] = $this->createTestAndCategory(false, 2);
        $result = $this->service->createQuestion($this->validInput($testId, $categoryId), $this->adminId);
        $this->assertSame('SUCCESS', $result['code']);

        $question = $this->repository->findSafeById((int)$result['question_id']);
        $this->assertNotNull($question);
        $this->assertCount(2, $question['options']);
        $this->assertSame(1, (int)$question['correct_count']);

        $audit = $this->fetchAudit('CREATE_QUESTION', (int)$result['question_id']);
        $payload = json_decode((string)$audit['new_values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['created_field_names'], array_keys($payload));
        $this->assertContains('options', $payload['created_field_names']);
        $this->assertStringNotContainsString('Question text', (string)$audit['new_values']);
        $this->assertNull($audit['old_values']);
    }

    public function testUpdateUsesConcurrencyTokenAndAuditsChangedFieldNamesOnly(): void
    {
        [$testId, $categoryId] = $this->createTestAndCategory(false, 2);
        $created = $this->service->createQuestion($this->validInput($testId, $categoryId), $this->adminId);
        $questionId = (int)$created['question_id'];
        $question = $this->repository->findEditableById($questionId);
        $token = $this->service->issueEditToken($this->adminId, $questionId, $question);

        $input = $this->validInput($testId, $categoryId);
        $input['text_en'] = 'Updated management question';
        $input['options'][1]['text_en'] = 'Updated wrong answer';
        $input['correct_option'] = '1';
        $result = $this->service->updateQuestion($questionId, $token, $input, $this->adminId);
        $this->assertSame('SUCCESS', $result['code']);

        $updated = $this->repository->findSafeById($questionId);
        $this->assertSame('Updated management question', $updated['text_en']);
        $this->assertSame(1, (int)$updated['options'][1]['is_correct']);

        $audit = $this->fetchAudit('UPDATE_QUESTION', $questionId);
        $oldPayload = json_decode((string)$audit['old_values'], true, 512, JSON_THROW_ON_ERROR);
        $newPayload = json_decode((string)$audit['new_values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['changed_field_names'], array_keys($oldPayload));
        $this->assertSame($oldPayload, $newPayload);
        $this->assertContains('text_en', $oldPayload['changed_field_names']);
        $this->assertContains('options', $oldPayload['changed_field_names']);
        $this->assertStringNotContainsString('Updated management question', (string)$audit['new_values']);

        $replay = $this->service->updateQuestion($questionId, $token, $input, $this->adminId);
        $this->assertSame('CONFLICT', $replay['code']);
    }

    public function testActiveAssessmentIntegrityBlocksArchivingLastRequiredQuestion(): void
    {
        [$testId, $categoryId] = $this->createTestAndCategory(true, 1);
        $created = $this->service->createQuestion($this->validInput($testId, $categoryId), $this->adminId);
        $questionId = (int)$created['question_id'];
        $question = $this->repository->findEditableById($questionId);
        $token = $this->service->issueEditToken($this->adminId, $questionId, $question);

        $blocked = $this->service->archiveQuestion($questionId, $token, $this->adminId);
        $this->assertSame('INTEGRITY_LOCK', $blocked['code']);
        $this->assertNull($this->repository->findSafeById($questionId)['archived_at']);
    }

    public function testArchiveAndRestorePreserveQuestionAndRestoreAsInactive(): void
    {
        [$testId, $categoryId] = $this->createTestAndCategory(false, 2);
        $created = $this->service->createQuestion($this->validInput($testId, $categoryId), $this->adminId);
        $questionId = (int)$created['question_id'];
        $question = $this->repository->findEditableById($questionId);
        $token = $this->service->issueEditToken($this->adminId, $questionId, $question);
        $this->assertSame('SUCCESS', $this->service->archiveQuestion($questionId, $token, $this->adminId)['code']);

        $archived = $this->repository->findEditableById($questionId);
        $this->assertNotNull($archived['archived_at']);
        $restoreToken = $this->service->issueEditToken($this->adminId, $questionId, $archived);
        $this->assertSame('SUCCESS', $this->service->restoreQuestion($questionId, $restoreToken, $this->adminId)['code']);

        $restored = $this->repository->findEditableById($questionId);
        $this->assertNull($restored['archived_at']);
        $this->assertSame(0, (int)$restored['is_active']);
        $this->assertCount(2, $restored['options']);
    }

    public function testCreateCategoryRejectsDuplicateNameWithinAssessment(): void
    {
        [$testId] = $this->createTestAndCategory(false, 2);
        $input = ['test_id' => (string)$testId, 'name_ar' => 'تصنيف جديد', 'name_en' => 'New Category'];
        $first = $this->service->createCategory($input, $this->adminId);
        $this->assertSame('SUCCESS', $first['code']);
        $second = $this->service->createCategory($input, $this->adminId);
        $this->assertSame('VALIDATION_ERROR', $second['code']);
    }

    private function validInput(int $testId, int $categoryId): array
    {
        return [
            'test_id' => (string)$testId,
            'category_id' => (string)$categoryId,
            'type' => 'single_choice',
            'text_ar' => 'نص سؤال صالح للاختبار',
            'text_en' => 'Valid management question text',
            'difficulty' => 'medium',
            'score' => '1.00',
            'is_active' => '1',
            'correct_option' => '0',
            'options' => [
                ['text_ar' => 'الإجابة الصحيحة', 'text_en' => 'Correct answer'],
                ['text_ar' => 'الإجابة الخاطئة', 'text_en' => 'Wrong answer'],
            ],
        ];
    }

    private function createTestAndCategory(bool $active, int $requiredCount): array
    {
        $code = 'UNIT-QMGT-' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare("
            INSERT INTO tests (
                code,name_ar,name_en,duration_minutes,question_count,passing_percentage,
                display_order,randomize_questions,randomize_options,allow_back_navigation,
                show_result_to_employee,is_active,created_at,updated_at
            ) VALUES (
                :code,'اختبار إدارة الأسئلة','Question Management Test',30,:required,60,
                1,1,1,1,0,:active,UTC_TIMESTAMP(),UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([':code' => $code, ':required' => $requiredCount, ':active' => $active ? 1 : 0]);
        $testId = (int)$this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare("
            INSERT INTO question_categories (test_id,name_ar,name_en,is_active,created_at)
            VALUES (:test_id,'تصنيف أساسي','Primary Category',1,UTC_TIMESTAMP())
        ");
        $stmt->execute([':test_id' => $testId]);
        return [$testId, (int)$this->pdo->lastInsertId()];
    }

    private function ensureAdmin(): int
    {
        $existing = $this->pdo->query("SELECT id FROM admins ORDER BY id LIMIT 1")->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO admins (name,email,username,password_hash,role,is_active,created_at,updated_at)
            VALUES ('Question Admin','question-admin@test.local','question_admin',:hash,'super_admin',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())
        ");
        $stmt->execute([':hash' => password_hash('QuestionTest123!', PASSWORD_BCRYPT)]);
        return (int)$this->pdo->lastInsertId();
    }

    private function fetchAudit(string $action, int $entityId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT old_values,new_values
            FROM audit_logs
            WHERE action = :action AND entity_type = 'QUESTION' AND entity_id = :entity_id
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':action' => $action, ':entity_id' => $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        return $row;
    }

    private function cleanup(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->pdo->exec("DELETE FROM audit_logs WHERE entity_type IN ('QUESTION','QUESTION_CATEGORY') AND entity_id > 0");
        $this->pdo->exec("DELETE FROM question_media WHERE question_id IN (SELECT q.id FROM questions q JOIN tests t ON t.id=q.test_id WHERE t.code LIKE 'UNIT-QMGT-%') OR option_id IN (SELECT qo.id FROM question_options qo JOIN questions q ON q.id=qo.question_id JOIN tests t ON t.id=q.test_id WHERE t.code LIKE 'UNIT-QMGT-%')");
        $this->pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT q.id FROM questions q JOIN tests t ON t.id=q.test_id WHERE t.code LIKE 'UNIT-QMGT-%')");
        $this->pdo->exec("DELETE FROM questions WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-QMGT-%')");
        $this->pdo->exec("DELETE FROM question_categories WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-QMGT-%')");
        $this->pdo->exec("DELETE FROM tests WHERE code LIKE 'UNIT-QMGT-%'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
