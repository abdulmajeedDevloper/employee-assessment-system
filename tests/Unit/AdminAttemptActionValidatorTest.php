<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\AdminAttemptActionValidator;
use PHPUnit\Framework\TestCase;

class AdminAttemptActionValidatorTest extends TestCase
{
    private AdminAttemptActionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = ['lang' => 'en'];
        $this->validator = new AdminAttemptActionValidator();
    }

    public function testCancellationRequiresMeaningfulReason(): void
    {
        $invalid = $this->validator->validateCancellation(['reason' => 'short']);
        $this->assertFalse($invalid['valid']);
        $this->assertArrayHasKey('cancellation_reason', $invalid['errors']);

        $valid = $this->validator->validateCancellation([
            'reason' => 'The employee reported a device failure during the assessment.',
        ]);
        $this->assertTrue($valid['valid']);
        $this->assertSame(
            'The employee reported a device failure during the assessment.',
            $valid['reason']
        );
    }

    public function testRetakeReasonRejectsNonScalarPayload(): void
    {
        $result = $this->validator->validateRetake(['reason' => ['unexpected']]);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('retake_reason', $result['errors']);
    }

    public function testSupervisorNotesCanBeClearedAndHaveLengthLimit(): void
    {
        $cleared = $this->validator->validateNotes(['supervisor_notes' => '   ']);
        $this->assertTrue($cleared['valid']);
        $this->assertNull($cleared['notes']);

        $tooLong = $this->validator->validateNotes([
            'supervisor_notes' => str_repeat('a', 2001),
        ]);
        $this->assertFalse($tooLong['valid']);
        $this->assertArrayHasKey('supervisor_notes', $tooLong['errors']);
    }
}
