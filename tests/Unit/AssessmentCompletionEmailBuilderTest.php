<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Notifications\AssessmentCompletionEmailBuilder;
use PHPUnit\Framework\TestCase;

final class AssessmentCompletionEmailBuilderTest extends TestCase
{
    public function testArabicMessageEscapesPayloadAndUsesOnlyMaskedId(): void
    {
        $builder = new AssessmentCompletionEmailBuilder(
            'الجهة <الرسمية>',
            'Official Organization',
            'https://assessment.example/public',
            'Asia/Riyadh'
        );

        $message = $builder->build(
            ['recipient' => 'hr@example.com', 'deduplication_key' => str_repeat('a', 64)],
            $this->payload('ar')
        );

        $this->assertSame('ar', $message->language);
        $this->assertSame(str_repeat('a', 64), $message->idempotencyKey);
        $this->assertStringContainsString('اكتمال تقييم الموظف', $message->subject);
        $this->assertStringContainsString('********1234', $message->htmlBody);
        $this->assertStringNotContainsString('1234567890', $message->htmlBody);
        $this->assertStringNotContainsString('<script>', $message->htmlBody);
        $this->assertStringContainsString('&lt;script&gt;', $message->htmlBody);
        $this->assertStringContainsString(
            'https://assessment.example/public/admin/employees/view?id=42',
            $message->htmlBody
        );
    }

    public function testEnglishPlainTextContainsBothRequiredResults(): void
    {
        $builder = new AssessmentCompletionEmailBuilder(
            'الجهة الرسمية',
            'Official Organization',
            'https://assessment.example/public',
            'UTC'
        );

        $message = $builder->build(
            ['recipient' => 'hr@example.com'],
            $this->payload('en')
        );

        $this->assertSame('en', $message->language);
        $this->assertStringContainsString('IQ Assessment', $message->textBody);
        $this->assertStringContainsString('English Assessment', $message->textBody);
        $this->assertStringContainsString('Passed', $message->textBody);
    }

    /** @return array<string,mixed> */
    private function payload(string $language): array
    {
        return [
            'schema_version' => 1,
            'employee_id' => 42,
            'employee' => [
                'name' => '<script>alert(1)</script>',
                'masked_national_id' => '********1234',
                'employee_number' => 'EMP-42',
                'preferred_language' => $language,
                'department' => ['ar' => 'تقنية المعلومات', 'en' => 'Information Technology'],
                'section' => ['ar' => 'التطوير', 'en' => 'Development'],
            ],
            'completion' => [
                'completed_at_utc' => '2026-07-28 12:00:00',
                'final_status' => 'PASSED',
                'results' => [
                    [
                        'code' => 'IQ_TEST',
                        'name_ar' => 'اختبار الذكاء',
                        'name_en' => 'IQ Assessment',
                        'score' => 18,
                        'total_score' => 20,
                        'percentage' => 90,
                        'passed' => true,
                    ],
                    [
                        'code' => 'ENG_TEST',
                        'name_ar' => 'اختبار الإنجليزية',
                        'name_en' => 'English Assessment',
                        'score' => 16,
                        'total_score' => 20,
                        'percentage' => 80,
                        'passed' => true,
                    ],
                ],
            ],
        ];
    }
}
