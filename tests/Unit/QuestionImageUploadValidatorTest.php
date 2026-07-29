<?php

namespace Tests\Unit;

use App\Validators\QuestionImageUploadValidator;
use PHPUnit\Framework\TestCase;

class QuestionImageUploadValidatorTest extends TestCase
{
    /** @var array<int,string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        parent::tearDown();
    }

    public function testAcceptsARealPngUsingDetectedMimeAndDimensions(): void
    {
        $path = $this->writeTemp($this->pngBytes());
        $validator = new QuestionImageUploadValidator(1024 * 1024, 100, 10000);

        $result = $validator->validate([
            'name' => 'illustration.not-trusted-extension.txt',
            'type' => 'text/plain',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => 1,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('image/png', $result['data']['mime_type']);
        $this->assertSame('png', $result['data']['file_extension']);
        $this->assertSame(1, $result['data']['width_pixels']);
        $this->assertSame(1, $result['data']['height_pixels']);
        $this->assertSame(filesize($path), $result['data']['file_size_bytes']);
    }

    public function testRejectsFakeImageContentEvenWhenBrowserClaimsPng(): void
    {
        $path = $this->writeTemp("not an image\n");
        $validator = new QuestionImageUploadValidator();

        $result = $validator->validate([
            'name' => 'fake.png',
            'type' => 'image/png',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($path),
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testRejectsAFileThatExceedsTheActualByteLimit(): void
    {
        $path = $this->writeTemp($this->pngBytes());
        $validator = new QuestionImageUploadValidator(10, 100, 10000);

        $result = $validator->validate([
            'name' => 'large.png',
            'type' => 'image/png',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => 1,
        ]);

        $this->assertFalse($result['valid']);
    }

    private function writeTemp(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qimg-validator-');
        $this->assertNotFalse($path);
        file_put_contents($path, $bytes);
        $this->temporaryFiles[] = $path;
        return $path;
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        $this->assertIsString($bytes);
        return $bytes;
    }
}
