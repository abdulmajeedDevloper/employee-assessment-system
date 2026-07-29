<?php

namespace App\Validators;

class AdminQuestionFormValidator
{
    private const MAX_OPTIONS = 8;
    private const MIN_OPTIONS = 2;

    /**
     * Validate and normalize a single-choice question and its answer options.
     *
     * @return array{valid:bool,errors:array<string,string>,data:array<string,mixed>,old:array<string,mixed>}
     */
    public function validate(array $input, ?int $fixedTestId = null): array
    {
        $errors = [];
        $old = $this->buildOldInput($input);

        $testId = $fixedTestId ?? $this->positiveInt($input['test_id'] ?? null);
        if ($testId === null) {
            $errors['test_id'] = trans('question_test_required');
        }

        $categoryId = $this->positiveInt($input['category_id'] ?? null);
        if ($categoryId === null) {
            $errors['category_id'] = trans('question_category_required');
        }

        $type = is_string($input['type'] ?? null) ? trim($input['type']) : 'single_choice';
        if ($type !== 'single_choice') {
            $errors['type'] = trans('invalid_question_type');
        }

        $textAr = $this->normalizeText($input['text_ar'] ?? null);
        $textEn = $this->normalizeText($input['text_en'] ?? null);
        $this->validateRequiredText($textAr, 'text_ar', 5, 5000, $errors);
        $this->validateRequiredText($textEn, 'text_en', 5, 5000, $errors);

        $difficulty = is_string($input['difficulty'] ?? null) ? trim($input['difficulty']) : '';
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $errors['difficulty'] = trans('invalid_question_difficulty');
        }

        $score = $this->decimal($input['score'] ?? null, 0.01, 1000.00, 2);
        if ($score === null) {
            $errors['score'] = trans('invalid_question_score');
        }

        $isActive = $this->checkbox($input, 'is_active', $errors);

        $rawOptions = $input['options'] ?? null;
        if (!is_array($rawOptions)) {
            $rawOptions = [];
        }
        $rawOptions = array_values($rawOptions);

        $optionCount = count($rawOptions);
        if ($optionCount < self::MIN_OPTIONS || $optionCount > self::MAX_OPTIONS) {
            $errors['options'] = trans('question_options_count_error');
        }

        $correctIndex = $this->zeroBasedInt($input['correct_option'] ?? null);
        if ($correctIndex === null || $correctIndex >= $optionCount) {
            $errors['correct_option'] = trans('correct_option_required');
        }

        $options = [];
        foreach ($rawOptions as $index => $rawOption) {
            if (!is_array($rawOption)) {
                $errors['options.' . $index] = trans('invalid_question_option');
                continue;
            }

            $optionAr = $this->normalizeText($rawOption['text_ar'] ?? null);
            $optionEn = $this->normalizeText($rawOption['text_en'] ?? null);
            $optionErrors = [];
            $this->validateRequiredText($optionAr, 'text_ar', 1, 2000, $optionErrors);
            $this->validateRequiredText($optionEn, 'text_en', 1, 2000, $optionErrors);

            if (isset($optionErrors['text_ar'])) {
                $errors['options.' . $index . '.text_ar'] = trans('option_text_ar_required');
            }
            if (isset($optionErrors['text_en'])) {
                $errors['options.' . $index . '.text_en'] = trans('option_text_en_required');
            }

            $options[] = [
                'option_key' => chr(65 + $index),
                'text_ar' => $optionAr,
                'text_en' => $optionEn,
                'is_correct' => $correctIndex === $index ? 1 : 0,
                'display_order' => $index + 1,
            ];
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'data' => [
                'test_id' => $testId,
                'category_id' => $categoryId,
                'type' => 'single_choice',
                'text_ar' => $textAr,
                'text_en' => $textEn,
                'difficulty' => $difficulty,
                'score' => $score,
                'is_active' => $isActive,
                'options' => $options,
            ],
            'old' => $old,
        ];
    }

    /**
     * Validate a question category creation request.
     *
     * @return array{valid:bool,errors:array<string,string>,data:array<string,mixed>,old:array<string,mixed>}
     */
    public function validateCategory(array $input): array
    {
        $errors = [];
        $testId = $this->positiveInt($input['test_id'] ?? null);
        $nameAr = $this->normalizeText($input['name_ar'] ?? null);
        $nameEn = $this->normalizeText($input['name_en'] ?? null);

        if ($testId === null) {
            $errors['test_id'] = trans('question_test_required');
        }
        $this->validateRequiredText($nameAr, 'name_ar', 2, 255, $errors);
        $this->validateRequiredText($nameEn, 'name_en', 2, 255, $errors);

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'data' => [
                'test_id' => $testId,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
            ],
            'old' => [
                'test_id' => is_scalar($input['test_id'] ?? null) ? (string)$input['test_id'] : '',
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
            ],
        ];
    }

    private function buildOldInput(array $input): array
    {
        $options = [];
        if (is_array($input['options'] ?? null)) {
            foreach (array_values($input['options']) as $option) {
                $options[] = [
                    'text_ar' => is_array($option) ? $this->normalizeText($option['text_ar'] ?? null) : '',
                    'text_en' => is_array($option) ? $this->normalizeText($option['text_en'] ?? null) : '',
                ];
            }
        }

        return [
            'test_id' => is_scalar($input['test_id'] ?? null) ? (string)$input['test_id'] : '',
            'category_id' => is_scalar($input['category_id'] ?? null) ? (string)$input['category_id'] : '',
            'type' => 'single_choice',
            'text_ar' => $this->normalizeText($input['text_ar'] ?? null),
            'text_en' => $this->normalizeText($input['text_en'] ?? null),
            'difficulty' => is_string($input['difficulty'] ?? null) ? trim($input['difficulty']) : 'medium',
            'score' => is_scalar($input['score'] ?? null) ? (string)$input['score'] : '1.00',
            'is_active' => ($input['is_active'] ?? null) === '1' || ($input['is_active'] ?? null) === 1 ? 1 : 0,
            'correct_option' => is_scalar($input['correct_option'] ?? null) ? (string)$input['correct_option'] : '',
            'options' => $options,
        ];
    }

    private function validateRequiredText(string $value, string $field, int $min, int $max, array &$errors): void
    {
        $length = mb_strlen($value);
        if ($length < $min || $length > $max || $this->containsControlCharacters($value)) {
            $errors[$field] = trans('invalid_question_text');
        }
    }

    private function normalizeText(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        return trim($value);
    }

    private function containsControlCharacters(string $value): bool
    {
        return preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200E}\x{200F}]/u', $value) === 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value >= 1) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $parsed = (int)$value;
            return (string)$parsed === $value ? $parsed : null;
        }
        return null;
    }

    private function zeroBasedInt(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value) === 1) {
            $parsed = (int)$value;
            return (string)$parsed === $value ? $parsed : null;
        }
        return null;
    }

    private function decimal(mixed $value, float $min, float $max, int $scale): ?string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }
        $raw = trim((string)$value);
        if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', $raw) !== 1) {
            return null;
        }
        $number = (float)$raw;
        if (!is_finite($number) || $number < $min || $number > $max) {
            return null;
        }
        return number_format($number, $scale, '.', '');
    }

    private function checkbox(array $input, string $key, array &$errors): int
    {
        if (!array_key_exists($key, $input)) {
            return 0;
        }
        $value = $input[$key];
        if ($value === '1' || $value === 1) {
            return 1;
        }
        $errors[$key] = trans('invalid_boolean_value');
        return 0;
    }
}
