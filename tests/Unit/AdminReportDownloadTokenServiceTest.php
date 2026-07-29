<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdminReportDownloadTokenService;
use PHPUnit\Framework\TestCase;

class AdminReportDownloadTokenServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../vendor/autoload.php';
        $_SESSION = [];
        $_ENV['REPORT_DOWNLOAD_TOKEN_TTL_SECONDS'] = 600;
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'report-token-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (is_dir($this->directory)) {
            foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function testTokenIsBoundToAdminAndCanBeConsumedOnlyOnce(): void
    {
        $filename = 'report_' . str_repeat('a', 32) . '.xlsx';
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . $filename, 'xlsx-content');
        $service = new AdminReportDownloadTokenService($this->directory);

        $token = $service->issue(11, $filename, 'assessment-report.xlsx', 'application/test', 'xlsx');
        $this->assertNull($service->consume($token, 12));

        $download = $service->consume($token, 11);
        $this->assertNotNull($download);
        $this->assertSame('assessment-report.xlsx', $download['download_filename']);
        $this->assertSame('xlsx', $download['format']);
        $this->assertNull($service->consume($token, 11));
    }

    public function testTamperedFileIsRejectedAndDeleted(): void
    {
        $filename = 'report_' . str_repeat('b', 32) . '.pdf';
        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, 'original-pdf');
        $service = new AdminReportDownloadTokenService($this->directory);

        $token = $service->issue(4, $filename, 'assessment-report.pdf', 'application/pdf', 'pdf');
        file_put_contents($path, 'tampered-pdf');

        $this->assertNull($service->consume($token, 4));
        $this->assertFileDoesNotExist($path);
    }

    public function testExpiredTokenRemovesItsTemporaryFile(): void
    {
        $filename = 'report_' . str_repeat('c', 32) . '.pdf';
        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, 'pdf-content');
        $service = new AdminReportDownloadTokenService($this->directory);
        $token = $service->issue(8, $filename, 'assessment-report.pdf', 'application/pdf', 'pdf');

        $_SESSION['admin_report_download_tokens'][$token]['expires_at'] = time() - 1;
        $this->assertSame(1, $service->cleanupExpiredTokens(true));
        $this->assertFileDoesNotExist($path);
    }

    public function testUnsafeStoredFilenameIsRejected(): void
    {
        $service = new AdminReportDownloadTokenService($this->directory);
        $this->expectException(\RuntimeException::class);
        $service->issue(1, '../secret.pdf', 'secret.pdf', 'application/pdf', 'pdf');
    }
}
