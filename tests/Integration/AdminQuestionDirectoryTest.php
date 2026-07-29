<?php

namespace Tests\Integration;

use App\Repositories\AdminQuestionRepository;
use App\Services\AdminQuestionDirectoryService;
use App\Services\AuthorizationService;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminQuestionDirectoryTest extends TestCase
{
    private PDO $pdo;
    private AdminQuestionRepository $repository;
    private AdminQuestionDirectoryService $service;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Database::getInstance();
        $this->repository = new AdminQuestionRepository($this->pdo);
        $this->service = new AdminQuestionDirectoryService($this->repository);
        $this->cleanup();
        $this->adminId = $this->ensureAdmin();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testAuthorizationContract(): void
    {
        $this->assertTrue(AuthorizationService::can(['role'=>'supervisor','is_active'=>1], 'view_questions'));
        $this->assertTrue(AuthorizationService::can(['role'=>'supervisor','is_active'=>1], 'manage_questions'));
        $this->assertFalse(AuthorizationService::can(['role'=>'report_viewer','is_active'=>1], 'view_questions'));
    }

    public function testDirectoryFiltersAndValidityMetrics(): void
    {
        [$testId,$categoryId] = $this->createFixture();
        $validId = $this->createQuestion($testId,$categoryId,'Valid searchable question',1,true);
        $this->createQuestion($testId,$categoryId,'Invalid searchable question',1,false);

        $data = $this->service->getDirectory(['q'=>'searchable','test_id'=>(string)$testId,'validity'=>'valid']);
        $this->assertSame(1,$data['results']['total']);
        $this->assertSame($validId,(int)$data['results']['items'][0]['id']);
        $this->assertSame(1,(int)$data['results']['items'][0]['is_valid']);
    }

    public function testDetailsExposeOnlySafeMediaFlagsAndOrderedOptions(): void
    {
        [$testId,$categoryId] = $this->createFixture();
        $questionId = $this->createQuestion($testId,$categoryId,'Details question',1,true);
        $data = $this->service->getQuestionDetails((string)$questionId);
        $this->assertTrue($data['success']);
        $question = $data['question'];
        $this->assertCount(2,$question['options']);
        $this->assertSame('A',$question['options'][0]['option_key']);
        $this->assertArrayHasKey('has_question_image',$question);
        $this->assertArrayNotHasKey('storage_path',$question);
        $this->assertArrayNotHasKey('public_token',$question);
    }

    public function testStrictIdentifierAndFilterNormalization(): void
    {
        foreach ([null,'','0','01','+1','1.0',[],true] as $bad) {
            $this->assertNull($this->service->parsePositiveInt($bad));
        }
        $data=$this->service->getDirectory(['difficulty'=>'INVALID','status'=>'INVALID','validity'=>'INVALID','page'=>'01']);
        $this->assertSame('all',$data['filters']['difficulty']);
        $this->assertSame('all',$data['filters']['status']);
        $this->assertSame('all',$data['filters']['validity']);
        $this->assertSame(1,$data['filters']['page']);
    }

    private function createFixture(): array
    {
        $code='UNIT-QDIR-'.bin2hex(random_bytes(3));
        $stmt=$this->pdo->prepare("INSERT INTO tests (code,name_ar,name_en,duration_minutes,question_count,passing_percentage,display_order,randomize_questions,randomize_options,allow_back_navigation,show_result_to_employee,is_active,created_at,updated_at) VALUES (:code,'اختبار','Question Test',30,2,60,1,1,1,1,0,0,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':code'=>$code]); $testId=(int)$this->pdo->lastInsertId();
        $stmt=$this->pdo->prepare("INSERT INTO question_categories (test_id,name_ar,name_en,is_active,created_at) VALUES (:test_id,'تصنيف','Category',1,UTC_TIMESTAMP())");
        $stmt->execute([':test_id'=>$testId]); return [$testId,(int)$this->pdo->lastInsertId()];
    }

    private function createQuestion(int $testId,int $categoryId,string $text,int $active,bool $valid): int
    {
        $stmt=$this->pdo->prepare("INSERT INTO questions (test_id,category_id,type,text_ar,text_en,difficulty,score,is_active,archived_at,created_by,created_at,updated_at) VALUES (:test_id,:category_id,'single_choice',:text_ar,:text_en,'medium',1,:active,NULL,:admin,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':test_id'=>$testId,':category_id'=>$categoryId,':text_ar'=>'سؤال '.$text,':text_en'=>$text,':active'=>$active,':admin'=>$this->adminId]);
        $id=(int)$this->pdo->lastInsertId();
        $options=$valid?[['A',1,1],['B',0,2]]:[['A',0,1]];
        $opt=$this->pdo->prepare("INSERT INTO question_options (question_id,option_key,text_ar,text_en,is_correct,display_order) VALUES (:qid,:key,:ar,:en,:correct,:ord)");
        foreach($options as [$key,$correct,$ord]){$opt->execute([':qid'=>$id,':key'=>$key,':ar'=>'خيار '.$key,':en'=>'Option '.$key,':correct'=>$correct,':ord'=>$ord]);}
        return $id;
    }

    private function ensureAdmin(): int
    {
        $id=$this->pdo->query('SELECT id FROM admins ORDER BY id LIMIT 1')->fetchColumn(); if($id)return (int)$id;
        $stmt=$this->pdo->prepare("INSERT INTO admins (name,email,username,password_hash,role,is_active,created_at,updated_at) VALUES ('Q Admin','qadmin@test.local','q_admin',:hash,'super_admin',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([':hash'=>password_hash('TestPassword123!',PASSWORD_BCRYPT)]); return (int)$this->pdo->lastInsertId();
    }

    private function cleanup(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT q.id FROM questions q JOIN tests t ON t.id=q.test_id WHERE t.code LIKE 'UNIT-QDIR-%')");
        $this->pdo->exec("DELETE FROM questions WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-QDIR-%')");
        $this->pdo->exec("DELETE FROM question_categories WHERE test_id IN (SELECT id FROM tests WHERE code LIKE 'UNIT-QDIR-%')");
        $this->pdo->exec("DELETE FROM tests WHERE code LIKE 'UNIT-QDIR-%'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
