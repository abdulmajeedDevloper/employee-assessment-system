<?php

namespace App\Validators;

class AdminTestFormValidator
{
    /**
     * Validate and normalize request input for creating or editing an assessment.
     *
     * @param array $input
     * @return array Contains ['valid' => bool, 'data' => array, 'errors' => array, 'old' => array]
     */
    public function validate(array $input): array
    {
        $errors = [];
        $data = [];
        $old = [];

        // 1. Code
        $rawCode = $input['code'] ?? null;
        if ($rawCode === null || $rawCode === '' || is_array($rawCode) || !is_scalar($rawCode)) {
            $errors['code'] = trans('code_required');
        } else {
            $codeStr = strtoupper(trim((string)$rawCode));
            $old['code'] = (string)$rawCode;
            if ($this->containsBidiOrControl($codeStr)) {
                $errors['code'] = trans('invalid_code_format');
            } elseif (!preg_match('/^[A-Z0-9_-]{3,64}$/', $codeStr)) {
                $errors['code'] = trans('invalid_code_format');
            } else {
                $data['code'] = $codeStr;
            }
        }

        // 2. Name AR
        $rawNameAr = $input['name_ar'] ?? null;
        if ($rawNameAr === null || $rawNameAr === '' || is_array($rawNameAr) || !is_scalar($rawNameAr)) {
            $errors['name_ar'] = trans('name_ar_required');
        } else {
            $nameArStr = trim((string)$rawNameAr);
            $old['name_ar'] = $nameArStr;
            $len = mb_strlen($nameArStr);
            if ($this->containsBidiOrC0C1Control($nameArStr, false) || $len < 2 || $len > 255) {
                $errors['name_ar'] = trans('invalid_name_ar');
            } else {
                $data['name_ar'] = $nameArStr;
            }
        }

        // 3. Name EN
        $rawNameEn = $input['name_en'] ?? null;
        if ($rawNameEn === null || $rawNameEn === '' || is_array($rawNameEn) || !is_scalar($rawNameEn)) {
            $errors['name_en'] = trans('name_en_required');
        } else {
            $nameEnStr = trim((string)$rawNameEn);
            $old['name_en'] = $nameEnStr;
            $len = mb_strlen($nameEnStr);
            if ($this->containsBidiOrC0C1Control($nameEnStr, false) || $len < 2 || $len > 255) {
                $errors['name_en'] = trans('invalid_name_en');
            } else {
                $data['name_en'] = $nameEnStr;
            }
        }

        // 4. Description AR (Optional)
        $rawDescAr = $input['description_ar'] ?? null;
        if ($rawDescAr !== null && !is_array($rawDescAr) && is_scalar($rawDescAr)) {
            $descArStr = trim((string)$rawDescAr);
            $old['description_ar'] = $descArStr;
            if ($descArStr === '') {
                $data['description_ar'] = null;
            } else {
                $len = mb_strlen($descArStr);
                if ($this->containsBidiOrC0C1Control($descArStr, true) || $len > 5000) {
                    $errors['description_ar'] = trans('invalid_description_ar');
                } else {
                    $data['description_ar'] = $descArStr;
                }
            }
        } else {
            $data['description_ar'] = null;
        }

        // 5. Description EN (Optional)
        $rawDescEn = $input['description_en'] ?? null;
        if ($rawDescEn !== null && !is_array($rawDescEn) && is_scalar($rawDescEn)) {
            $descEnStr = trim((string)$rawDescEn);
            $old['description_en'] = $descEnStr;
            if ($descEnStr === '') {
                $data['description_en'] = null;
            } else {
                $len = mb_strlen($descEnStr);
                if ($this->containsBidiOrC0C1Control($descEnStr, true) || $len > 5000) {
                    $errors['description_en'] = trans('invalid_description_en');
                } else {
                    $data['description_en'] = $descEnStr;
                }
            }
        } else {
            $data['description_en'] = null;
        }

        // 6. Instructions AR (Optional)
        $rawInstAr = $input['instructions_ar'] ?? null;
        if ($rawInstAr !== null && !is_array($rawInstAr) && is_scalar($rawInstAr)) {
            $instArStr = trim((string)$rawInstAr);
            $old['instructions_ar'] = $instArStr;
            if ($instArStr === '') {
                $data['instructions_ar'] = null;
            } else {
                $len = mb_strlen($instArStr);
                if ($this->containsBidiOrC0C1Control($instArStr, true) || $len > 10000) {
                    $errors['instructions_ar'] = trans('invalid_instructions_ar');
                } else {
                    $data['instructions_ar'] = $instArStr;
                }
            }
        } else {
            $data['instructions_ar'] = null;
        }

        // 7. Instructions EN (Optional)
        $rawInstEn = $input['instructions_en'] ?? null;
        if ($rawInstEn !== null && !is_array($rawInstEn) && is_scalar($rawInstEn)) {
            $instEnStr = trim((string)$rawInstEn);
            $old['instructions_en'] = $instEnStr;
            if ($instEnStr === '') {
                $data['instructions_en'] = null;
            } else {
                $len = mb_strlen($instEnStr);
                if ($this->containsBidiOrC0C1Control($instEnStr, true) || $len > 10000) {
                    $errors['instructions_en'] = trans('invalid_instructions_en');
                } else {
                    $data['instructions_en'] = $instEnStr;
                }
            }
        } else {
            $data['instructions_en'] = null;
        }

        // 8. Duration Minutes
        $rawDuration = $input['duration_minutes'] ?? null;
        if ($rawDuration === null || $rawDuration === '' || is_array($rawDuration) || !is_scalar($rawDuration)) {
            $errors['duration_minutes'] = trans('invalid_duration');
        } else {
            $durStr = (string)$rawDuration;
            $old['duration_minutes'] = $durStr;
            if (!preg_match('/^[1-9][0-9]*$/', $durStr)) {
                $errors['duration_minutes'] = trans('invalid_duration');
            } else {
                $val = (int)$durStr;
                if ($val < 1 || $val > 1440) {
                    $errors['duration_minutes'] = trans('invalid_duration');
                } else {
                    $data['duration_minutes'] = $val;
                }
            }
        }

        // 9. Question Count
        $rawQC = $input['question_count'] ?? null;
        if ($rawQC === null || $rawQC === '' || is_array($rawQC) || !is_scalar($rawQC)) {
            $errors['question_count'] = trans('invalid_question_count');
        } else {
            $qcStr = (string)$rawQC;
            $old['question_count'] = $qcStr;
            if (!preg_match('/^[1-9][0-9]*$/', $qcStr)) {
                $errors['question_count'] = trans('invalid_question_count');
            } else {
                $val = (int)$qcStr;
                if ($val < 1 || $val > 500) {
                    $errors['question_count'] = trans('invalid_question_count');
                } else {
                    $data['question_count'] = $val;
                }
            }
        }

        // 10. Display Order
        $rawDO = $input['display_order'] ?? null;
        if ($rawDO === null || $rawDO === '' || is_array($rawDO) || !is_scalar($rawDO)) {
            $errors['display_order'] = trans('invalid_display_order');
        } else {
            $doStr = (string)$rawDO;
            $old['display_order'] = $doStr;
            if (!preg_match('/^[1-9][0-9]*$/', $doStr)) {
                $errors['display_order'] = trans('invalid_display_order');
            } else {
                $val = (int)$doStr;
                if ($val < 1 || $val > 9999) {
                    $errors['display_order'] = trans('invalid_display_order');
                } else {
                    $data['display_order'] = $val;
                }
            }
        }

        // 11. Passing Percentage
        $rawPP = $input['passing_percentage'] ?? null;
        if ($rawPP === null || $rawPP === '' || is_array($rawPP) || !is_scalar($rawPP)) {
            $errors['passing_percentage'] = trans('invalid_passing_percentage');
        } else {
            $ppStr = (string)$rawPP;
            $old['passing_percentage'] = $ppStr;
            if (!preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/', $ppStr)) {
                $errors['passing_percentage'] = trans('invalid_passing_percentage');
            } else {
                $val = (float)$ppStr;
                if ($val < 0.0 || $val > 100.0) {
                    $errors['passing_percentage'] = trans('invalid_passing_percentage');
                } else {
                    $data['passing_percentage'] = number_format($val, 2, '.', '');
                }
            }
        }

        // 12-15. Booleans
        $boolFields = [
            'randomize_questions',
            'randomize_options',
            'allow_back_navigation',
            'show_result_to_employee',
        ];

        foreach ($boolFields as $bField) {
            $val = $input[$bField] ?? null;
            if ($val === null || $val === '' || $val === '0' || $val === 0) {
                $data[$bField] = 0;
                $old[$bField] = 0;
            } elseif ($val === '1' || $val === 1) {
                $data[$bField] = 1;
                $old[$bField] = 1;
            } else {
                $errors[$bField] = trans('invalid_boolean_value');
                $old[$bField] = is_scalar($val) ? (string)$val : '';
            }
        }

        return [
            'valid' => empty($errors),
            'data' => $data,
            'errors' => $errors,
            'old' => $old,
        ];
    }

    /**
     * Check if string contains NUL, C0/C1 control code points, or Unicode bidi controls.
     */
    private function containsBidiOrC0C1Control(string $str, bool $allowNewlines = false): bool
    {
        // Check bidi controls (U+202A-U+202E, U+2066-U+2069)
        if (preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $str)) {
            return true;
        }

        // Check NUL
        if (str_contains($str, "\0")) {
            return true;
        }

        // Check C0 controls (U+0000-U+001F) and C1 controls (U+007F-U+009F)
        if ($allowNewlines) {
            // Allow \t (U+0009), \n (U+000A), \r (U+000D)
            if (preg_match('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', $str)) {
                return true;
            }
        } else {
            if (preg_match('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', $str)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string contains bidi or general control characters for code.
     */
    private function containsBidiOrControl(string $str): bool
    {
        return $this->containsBidiOrC0C1Control($str, false);
    }
}
