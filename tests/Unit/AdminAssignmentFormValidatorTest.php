<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\AdminAssignmentFormValidator;
use PHPUnit\Framework\TestCase;

class AdminAssignmentFormValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../vendor/autoload.php';
        $_SESSION = ['lang' => 'en'];
        $_ENV['APP_TIMEZONE'] = 'Asia/Riyadh';
    }

    public function testValidCreateNormalizesIdentifiersAndDates(): void
    {
        $validator = new AdminAssignmentFormValidator();
        $tomorrow = (new \DateTimeImmutable('tomorrow 09:00', new \DateTimeZone('Asia/Riyadh')))->format('Y-m-d\\TH:i');
        $after = (new \DateTimeImmutable('tomorrow 10:00', new \DateTimeZone('Asia/Riyadh')))->format('Y-m-d\\TH:i');

        $result = $validator->validateCreate([
            'employee_id' => '7',
            'test_id' => '9',
            'max_attempts' => '3',
            'available_from' => $tomorrow,
            'available_until' => $after,
        ], [['id' => 7]], [['id' => 9]]);

        $this->assertTrue($result['valid']);
        $this->assertSame(7, $result['data']['employee_id']);
        $this->assertSame(9, $result['data']['test_id']);
        $this->assertSame(3, $result['data']['max_attempts']);
        $this->assertNotNull($result['data']['available_from']);
        $this->assertNotNull($result['data']['available_until']);
    }

    public function testRejectsUnlistedEntitiesAndArrayInjection(): void
    {
        $validator = new AdminAssignmentFormValidator();
        $result = $validator->validateCreate([
            'employee_id' => ['7'],
            'test_id' => '99',
            'max_attempts' => '1',
        ], [['id' => 7]], [['id' => 9]]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('employee_id', $result['errors']);
        $this->assertArrayHasKey('test_id', $result['errors']);
    }

    public function testRejectsInvalidAttemptLimitAndDateOrder(): void
    {
        $validator = new AdminAssignmentFormValidator();
        $tomorrow = (new \DateTimeImmutable('tomorrow 10:00', new \DateTimeZone('Asia/Riyadh')))->format('Y-m-d\\TH:i');
        $before = (new \DateTimeImmutable('tomorrow 09:00', new \DateTimeZone('Asia/Riyadh')))->format('Y-m-d\\TH:i');

        $result = $validator->validateUpdate([
            'max_attempts' => '21',
            'available_from' => $tomorrow,
            'available_until' => $before,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('max_attempts', $result['errors']);
        $this->assertArrayHasKey('available_until', $result['errors']);
    }
}
