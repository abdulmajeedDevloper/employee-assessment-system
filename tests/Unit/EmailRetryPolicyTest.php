<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Notifications\EmailRetryPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmailRetryPolicyTest extends TestCase
{
    public function testRetryScheduleUsesBoundedBackoff(): void
    {
        $policy = new EmailRetryPolicy();

        $this->assertSame(300, $policy->delaySeconds(1));
        $this->assertSame(900, $policy->delaySeconds(2));
        $this->assertSame(3600, $policy->delaySeconds(3));
        $this->assertSame(86400, $policy->delaySeconds(99));
    }

    public function testTechnicalErrorDoesNotExposeExceptionMessage(): void
    {
        $policy = new EmailRetryPolicy();
        $exception = new RuntimeException('smtp user@example.com password=secret', 77);

        $code = $policy->technicalErrorCode($exception);

        $this->assertStringContainsString('RuntimeException', $code);
        $this->assertStringContainsString('77', $code);
        $this->assertStringNotContainsString('user@example.com', $code);
        $this->assertStringNotContainsString('secret', $code);
    }
}
