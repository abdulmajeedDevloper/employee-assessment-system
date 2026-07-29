<?php

namespace App\Validators;

use finfo;

class QuestionImageUploadValidator
{
    private const DEFAULT_MAX_BYTES = 5242880;
    private const DEFAULT_MAX_DIMENSION = 5000;
    private const DEFAULT_MAX_PIXELS = 20000000;

    /** @var array<string,string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private int $maxBytes;
    private int $maxDimension;
    private int $maxPixels;

    public function __construct(?int $maxBytes = null, ?int $maxDimension = null, ?int $maxPixels = null)
    {
        $this->maxBytes = $maxBytes ?? $this->positiveEnvInt('QUESTION_MEDIA_MAX_BYTES', self::DEFAULT_MAX_BYTES);
        $this->maxDimension = $maxDimension ?? $this->positiveEnvInt('QUESTION_MEDIA_MAX_DIMENSION', self::DEFAULT_MAX_DIMENSION);
        $this->maxPixels = $maxPixels ?? $this->positiveEnvInt('QUESTION_MEDIA_MAX_PIXELS', self::DEFAULT_MAX_PIXELS);
    }

    /**
     * Validate an uploaded raster image using the actual file bytes, not the browser MIME value.
     *
     * @return array{valid:bool,error:?string,data:?array<string,mixed>}
     */
    public function validate(array $file): array
    {
        $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($errorCode)) {
            $errorCode = is_numeric($errorCode) ? (int)$errorCode : UPLOAD_ERR_NO_FILE;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            return $this->failure($this->uploadErrorMessage($errorCode));
        }

        $tmpPath = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        if ($tmpPath === '' || !is_file($tmpPath) || is_link($tmpPath)) {
            return $this->failure(trans('question_image_invalid_upload'));
        }

        $isTestEnvironment = in_array((string)($_ENV['APP_ENV'] ?? ''), ['test', 'testing'], true);
        if (PHP_SAPI !== 'cli' && !$isTestEnvironment && !is_uploaded_file($tmpPath)) {
            return $this->failure(trans('question_image_invalid_upload'));
        }

        $actualSize = filesize($tmpPath);
        if ($actualSize === false || $actualSize < 1 || $actualSize > $this->maxBytes) {
            return $this->failure(trans('question_image_size_error', [
                'size' => (string)max(1, (int)ceil($this->maxBytes / 1048576)),
            ]));
        }

        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($tmpPath);
        } catch (\Throwable) {
            $detectedMime = false;
        }
        if (!is_string($detectedMime) || !isset(self::MIME_EXTENSIONS[$detectedMime])) {
            return $this->failure(trans('question_image_type_error'));
        }

        $imageInfo = @getimagesize($tmpPath);
        if (!is_array($imageInfo)) {
            return $this->failure(trans('question_image_invalid_content'));
        }

        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        $imageMime = (string)($imageInfo['mime'] ?? '');
        if ($width < 1 || $height < 1 || $imageMime !== $detectedMime) {
            return $this->failure(trans('question_image_invalid_content'));
        }
        if ($width > $this->maxDimension || $height > $this->maxDimension || ($width * $height) > $this->maxPixels) {
            return $this->failure(trans('question_image_dimensions_error', [
                'dimension' => (string)$this->maxDimension,
            ]));
        }

        $originalName = is_string($file['name'] ?? null) ? basename(str_replace('\\', '/', $file['name'])) : 'image';
        $originalName = trim($originalName);
        $nameLength = function_exists('mb_strlen') ? mb_strlen($originalName) : strlen($originalName);
        if ($originalName === '' || $nameLength > 255 || $this->containsControlCharacters($originalName)) {
            $originalName = 'image.' . self::MIME_EXTENSIONS[$detectedMime];
        }

        return [
            'valid' => true,
            'error' => null,
            'data' => [
                'tmp_path' => $tmpPath,
                'original_filename' => $originalName,
                'mime_type' => $detectedMime,
                'file_extension' => self::MIME_EXTENSIONS[$detectedMime],
                'file_size_bytes' => (int)$actualSize,
                'width_pixels' => $width,
                'height_pixels' => $height,
            ],
        ];
    }

    /** @return array{valid:false,error:string,data:null} */
    private function failure(string $message): array
    {
        return ['valid' => false, 'error' => $message, 'data' => null];
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => trans('question_image_size_error', [
                'size' => (string)max(1, (int)ceil($this->maxBytes / 1048576)),
            ]),
            UPLOAD_ERR_NO_FILE => trans('question_image_required'),
            default => trans('question_image_upload_failed'),
        };
    }

    private function positiveEnvInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (!is_scalar($value) || preg_match('/^[1-9][0-9]*$/', (string)$value) !== 1) {
            return $default;
        }
        $parsed = (int)$value;
        return $parsed > 0 ? $parsed : $default;
    }

    private function containsControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1
            || preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $value) === 1;
    }
}
