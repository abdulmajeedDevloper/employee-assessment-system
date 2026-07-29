<?php

namespace Tests\Integration;

use App\Repositories\AdminQuestionMediaRepository;
use App\Repositories\AdminQuestionRepository;
use App\Services\AdminQuestionMediaService;
use App\Support\Database;
use App\Validators\QuestionImageUploadValidator;
use PDO;
use Tests\TestCase;

class AdminQuestionMediaTest extends TestCase
{
    private PDO $pdo;
    private AdminQuestionRepository $questionRepository;
    private AdminQuestionMediaRepository $mediaRepository;
    private AdminQuestionMediaService $service;
    private int $adminId;
    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = ['lang' => 'en'];
        $this->pdo = Database::getInstance();
        $this->cleanupDatabase();
        $this->adminId = $this->ensureAdmin();
        $this->storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'employee-qmedia-' . bin2hex(random_bytes(6));
        $this->questionRepository = new AdminQuestionRepository($this->pdo);
        $this->mediaRepository = new AdminQuestionMediaRepository($this->pdo);
        $this->service = new AdminQuestionMediaService(
            $this->questionRepository,
            $this->mediaRepository,
            new QuestionImageUploadValidator(1024 * 1024, 100, 10000),
            $this->pdo,
            $this->storageRoot
        );
    }

    protected function tearDown(): void
    {
        $this->cleanupDatabase();
        $this->removeDirectory($this->storageRoot ?? '');
        $_SESSION = [];
        parent::tearDown();
    }

    public function testQuestionImageUploadAndReplacementUsePrivateRandomStorageAndFieldOnlyAudit(): void
    {
        [$questionId] = $this->createFixtureQuestion();
        $question = $this->questionRepository->findEditableById($questionId);
        $token = $this->service->issueActionToken($this->adminId, $questionId, $question);

        $first = $this->service->upload($questionId, null, $token, $this->uploadFile('original-secret-name.png'), $this->adminId);
        $this->assertSame('SUCCESS', $first['code']);
        $this->assertFalse($first['replaced']);

        $saved = $this->questionRepository->findSafeById($questionId);
        $this->assertNotEmpty($saved['question_image_token']);
        $firstToken = (string)$saved['question_image_token'];
        $streamable = $this->service->getStreamableMedia($firstToken);
        $this->assertIsArray($streamable);
        $this->assertFileExists($streamable['absolute_path']);
        $this->assertStringNotContainsString('original-secret-name', $streamable['absolute_path']);
        $this->assertMatchesRegularExpression('#/questions/[0-9]{4}/[0-9]{2}/[a-f0-9]{40}\.png$#', str_replace('\\', '/', $streamable['absolute_path']));

        $audit = $this->fetchAudit('UPLOAD_QUESTION_MEDIA', 'QUESTION', $questionId);
        $payload = json_decode((string)$audit['new_values'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['changed_field_names' => ['question_image']], $payload);
        $serializedAudit = json_encode($audit, JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('original-secret-name', (string)$serializedAudit);
        $this->assertStringNotContainsString($firstToken, (string)$serializedAudit);
        $this->assertStringNotContainsString('storage_path', (string)$serializedAudit);

        $oldPath = (string)$streamable['absolute_path'];
        $question = $this->questionRepository->findEditableById($questionId);
        $replaceToken = $this->service->issueActionToken($this->adminId, $questionId, $question);
        $replacement = $this->service->upload($questionId, null, $replaceToken, $this->uploadFile('replacement.png'), $this->adminId);
        $this->assertSame('SUCCESS', $replacement['code']);
        $this->assertTrue($replacement['replaced']);

        $reloaded = $this->questionRepository->findSafeById($questionId);
        $this->assertNotSame($firstToken, (string)$reloaded['question_image_token']);
        $this->assertFileDoesNotExist($oldPath);
        $this->assertNull($this->service->getStreamableMedia($firstToken));
    }

    public function testOptionImageOwnerIsVerifiedAndRemovalQueuesSecureDeletion(): void
    {
        [$questionId, $optionId] = $this->createFixtureQuestion();
        $question = $this->questionRepository->findEditableById($questionId);
        $token = $this->service->issueActionToken($this->adminId, $questionId, $question);
        $result = $this->service->upload($questionId, $optionId, $token, $this->uploadFile('option.png'), $this->adminId);
        $this->assertSame('SUCCESS', $result['code']);

        $saved = $this->questionRepository->findSafeById($questionId);
        $option = array_values(array_filter($saved['options'], static fn(array $row): bool => (int)$row['id'] === $optionId))[0];
        $this->assertNotEmpty($option['option_image_token']);
        $streamable = $this->service->getStreamableMedia((string)$option['option_image_token']);
        $this->assertIsArray($streamable);
        $storedPath = (string)$streamable['absolute_path'];

        $question = $this->questionRepository->findEditableById($questionId);
        $removeToken = $this->service->issueActionToken($this->adminId, $questionId, $question);
        $removed = $this->service->remove($questionId, $optionId, $removeToken, $this->adminId);
        $this->assertSame('SUCCESS', $removed['code']);
        $this->assertFileDoesNotExist($storedPath);

        [$otherQuestionId] = $this->createFixtureQuestion();
        $other = $this->questionRepository->findEditableById($otherQuestionId);
        $invalidToken = $this->service->issueActionToken($this->adminId, $otherQuestionId, $other);
        $invalid = $this->service->upload($otherQuestionId, $optionId, $invalidToken, $this->uploadFile('wrong-owner.png'), $this->adminId);
        $this->assertSame('INVALID_OWNER', $invalid['code']);
    }

    /** @return array{0:int,1:int} */
    private function createFixtureQuestion(): array
    {
        $code = 'UNIT-QMEDIA-' . bin2hex(random_bytes(5));
        $stmt = $this->pdo->prepare("
            INSERT INTO tests (
                code,name_ar,name_en,duration_minutes,question_count,passing_percentage,
                display_order,randomize_questions,randomize_options,allow_back_navigation,
                show_result_to_employee,is_active,created_at,updated_at
            ) VALUES (
                :code,'اختبار الوسائط','Media Test',30,2,60,
                1,1,1,1,0,0,UTC_TIMESTAMP(),UTC_TIMESTAMP()
            )
        ");
        $stmt->execute([':code' => $code]);
        $testId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("
            INSERT INTO question_categories (test_id,name_ar,name_en,is_active,created_at)
            VALUES (:test_id,'تصنيف الوسائط','Media Category',1,UTC_TIMESTAMP())
        ");
        $stmt->execute([':test_id' => $testId]);
        $categoryId = (int)$this->pdo->lastInsertId();

        $questionId = $this->questionRepository->createQuestion([
            'test_id' => $testId,
            'category_id' => $categoryId,
            'type' => 'single_choice',
            'text_ar' => 'سؤال وسائط صالح للاختبار',
            'text_en' => 'Valid question media fixture',
            'difficulty' => 'medium',
            'score' => '1.00',
            'is_active' => 1,
            'options' => [
                ['option_key' => 'A', 'text_ar' => 'صحيح', 'text_en' => 'Correct', 'is_correct' => 1, 'display_order' => 1],
                ['option_key' => 'B', 'text_ar' => 'خطأ', 'text_en' => 'Wrong', 'is_correct' => 0, 'display_order' => 2],
            ],
        ], $this->adminId);
        $question = $this->questionRepository->findSafeById($questionId);
        return [$questionId, (int)$question['options'][0]['id']];
    }

    private function uploadFile(string $name): array
    {
        $path = tempnam(sys_get_temp_dir(), 'qmedia-upload-');
        $this->assertNotFalse($path);
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        $this->assertIsString($bytes);
        file_put_contents($path, $bytes);
        return [
            'name' => $name,
            'type' => 'application/octet-stream',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($path),
        ];
    }

    private function fetchAudit(string $action, string $entityType, int $entityId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT old_values,new_values
            FROM audit_logs
            WHERE action = :action AND entity_type = :entity_type AND entity_id = :entity_id
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':action' => $action, ':entity_type' => $entityType, ':entity_id' => $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        return $row;
    }

    private function ensureAdmin(): int
    {
        $existing = $this->pdo->query('SELECT id FROM admins ORDER BY id LIMIT 1')->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO admins (name,email,username,password_hash,role,is_active,created_at,updated_at)
            VALUES ('Media Admin','media-admin@test.local','media_admin',:hash,'super_admin',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())
        ");
        $stmt->execute([':hash' => password_hash('MediaTest123!', PASSWORD_BCRYPT)]);
        return (int)$this->pdo->lastInsertId();
    }

    private function cleanupDatabase(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->pdo->exec("DELETE FROM pending_media_deletions WHERE storage_path LIKE 'questions/%'");
        $this->pdo->exec("DELETE FROM question_media WHERE question_id IN (SELECT q.id FROM questions q JOIN tests t ON t.id=q.test_id WHERE t.code LIKE 'UNIT-QMEDIA-%') OR option_id IN (SELECT qo.id FROM question_options qo JOIN questions q ON q.id=qo.question_id JOIN tests t ON t.id=q.test_id WHERE t.code LIKE 'UNIT-QMEDIA-%')");
        $this->pdo->exec("DELETE FROM audit_logs WHERE action IN ('UPLOAD_QUESTION_MEDIA','REPLACE_QUESTION_MEDIA','REMOVE_QUESTION_MEDIA')");
        $this->pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT q.id FROM questions q JOIN tests t ON t.id=q.test_id WHERE t.code LIKE 'UNIT-QMEDIA-%')");
        $this->pdo->exec("DELETE FROM questions WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-QMEDIA-%')");
        $this->pdo->exec("DELETE FROM question_categories WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-QMEDIA-%')");
        $this->pdo->exec("DELETE FROM tests WHERE code LIKE 'UNIT-QMEDIA-%'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function removeDirectory(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }
}
