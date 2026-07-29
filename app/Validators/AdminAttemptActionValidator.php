<?php

declare(strict_types=1);

namespace App\Validators;

class AdminAttemptActionValidator
{
    /** @return array{valid:bool,reason:string,errors:array} */
    public function validateCancellation(array $input): array
    {
        return $this->validateReason($input['reason'] ?? null, 'cancellation_reason', 10, 1000);
    }

    /** @return array{valid:bool,reason:string,errors:array} */
    public function validateRetake(array $input): array
    {
        return $this->validateReason($input['reason'] ?? null, 'retake_reason', 10, 1000);
    }

    /** @return array{valid:bool,notes:?string,errors:array} */
    public function validateNotes(array $input): array
    {
        $raw = $input['supervisor_notes'] ?? null;
        if ($raw !== null && !is_scalar($raw)) {
            return [
                'valid' => false,
                'notes' => null,
                'errors' => ['supervisor_notes' => trans('attempt_notes_invalid')],
            ];
        }

        $notes = trim((string)($raw ?? ''));
        if (mb_strlen($notes) > 2000) {
            return [
                'valid' => false,
                'notes' => $notes,
                'errors' => ['supervisor_notes' => trans('attempt_notes_too_long')],
            ];
        }

        return ['valid' => true, 'notes' => $notes === '' ? null : $notes, 'errors' => []];
    }

    /** @return array{valid:bool,reason:string,errors:array} */
    private function validateReason(mixed $raw, string $field, int $min, int $max): array
    {
        if (!is_scalar($raw)) {
            return [
                'valid' => false,
                'reason' => '',
                'errors' => [$field => trans('attempt_reason_required')],
            ];
        }

        $reason = trim((string)$raw);
        $length = mb_strlen($reason);
        if ($length < $min || $length > $max) {
            return [
                'valid' => false,
                'reason' => $reason,
                'errors' => [$field => trans('attempt_reason_length')],
            ];
        }

        return ['valid' => true, 'reason' => $reason, 'errors' => []];
    }
}
