<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ReportExportSanitizer;
use PHPUnit\Framework\TestCase;

class ReportExportSanitizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../vendor/autoload.php';
    }

    /** @dataProvider formulaValues */
    public function testSpreadsheetTextPreventsFormulaInjection(string $input): void
    {
        $this->assertStringStartsWith("'", ReportExportSanitizer::spreadsheetText($input));
    }

    public static function formulaValues(): array
    {
        return [
            'equals' => ['=HYPERLINK("https://example.test")'],
            'plus' => ['+1+1'],
            'minus' => ['-2+3'],
            'at' => ['@SUM(A1:A2)'],
            'leading whitespace' => ["  =1+1"],
        ];
    }

    public function testSpreadsheetTextKeepsNormalUnicodeContent(): void
    {
        $this->assertSame('موظف الاختبار', ReportExportSanitizer::spreadsheetText(' موظف الاختبار '));
    }

    public function testTextRemovesUnsafeControlCharacters(): void
    {
        $this->assertSame('AB', ReportExportSanitizer::text("A\x00\x07B"));
    }

    public function testHtmlEscapesMarkup(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', ReportExportSanitizer::html('<script>alert(1)</script>'));
    }
}
