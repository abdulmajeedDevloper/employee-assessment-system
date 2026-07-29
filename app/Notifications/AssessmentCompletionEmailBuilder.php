<?php

declare(strict_types=1);

namespace App\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class AssessmentCompletionEmailBuilder
{
    public function __construct(
        private string $organizationNameAr,
        private string $organizationNameEn,
        private string $appUrl,
        private string $appTimezone = 'UTC'
    ) {
    }

    /**
     * @param array<string,mixed> $notification
     * @param array<string,mixed> $payload
     */
    public function build(array $notification, array $payload): MailMessage
    {
        $recipient = trim((string)($notification['recipient'] ?? ''));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('notification_recipient_invalid');
        }

        $employee = is_array($payload['employee'] ?? null) ? $payload['employee'] : [];
        $completion = is_array($payload['completion'] ?? null) ? $payload['completion'] : [];
        $results = is_array($completion['results'] ?? null) ? $completion['results'] : [];
        $language = (string)($employee['preferred_language'] ?? 'ar');
        if (!in_array($language, ['ar', 'en'], true)) {
            $language = 'ar';
        }

        $employeeName = trim((string)($employee['name'] ?? ''));
        $maskedId = trim((string)($employee['masked_national_id'] ?? ''));
        $employeeNumber = trim((string)($employee['employee_number'] ?? ''));
        $department = is_array($employee['department'] ?? null) ? $employee['department'] : [];
        $section = is_array($employee['section'] ?? null) ? $employee['section'] : [];
        $finalStatus = strtoupper((string)($completion['final_status'] ?? 'FAILED')) === 'PASSED'
            ? 'PASSED'
            : 'FAILED';
        $completedAt = $this->formatDate((string)($completion['completed_at_utc'] ?? ''));
        $employeeId = (int)($payload['employee_id'] ?? 0);
        if ($employeeId <= 0 || $employeeName === '' || $maskedId === '' || $results === []) {
            throw new RuntimeException('notification_payload_incomplete');
        }

        $adminLink = rtrim($this->appUrl, '/') . '/admin/employees/view?id=' . $employeeId;
        $isArabic = $language === 'ar';
        $organization = $isArabic ? $this->organizationNameAr : $this->organizationNameEn;
        $subject = $isArabic
            ? 'اكتمال تقييم الموظف'
            : 'Employee assessment completed';
        $statusLabel = $isArabic
            ? ($finalStatus === 'PASSED' ? 'ناجح' : 'غير مجتاز')
            : ($finalStatus === 'PASSED' ? 'Passed' : 'Not passed');
        $direction = $isArabic ? 'rtl' : 'ltr';
        $align = $isArabic ? 'right' : 'left';

        $departmentName = trim((string)($department[$language] ?? $department['en'] ?? $department['ar'] ?? ''));
        $sectionName = trim((string)($section[$language] ?? $section['en'] ?? $section['ar'] ?? ''));

        $rowsHtml = '';
        $rowsText = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }
            $testName = trim((string)($result['name_' . $language] ?? $result['name_en'] ?? $result['name_ar'] ?? $result['code'] ?? ''));
            $score = number_format((float)($result['score'] ?? 0), 2, '.', '');
            $total = number_format((float)($result['total_score'] ?? 0), 2, '.', '');
            $percentage = number_format((float)($result['percentage'] ?? 0), 2, '.', '');
            $passed = (bool)($result['passed'] ?? false);
            $resultStatus = $isArabic
                ? ($passed ? 'ناجح' : 'غير مجتاز')
                : ($passed ? 'Passed' : 'Not passed');

            $rowsHtml .= '<tr>'
                . '<td style="padding:10px;border:1px solid #dbe3ec;">' . $this->escape($testName) . '</td>'
                . '<td style="padding:10px;border:1px solid #dbe3ec;text-align:center;">' . $this->escape($score . ' / ' . $total) . '</td>'
                . '<td style="padding:10px;border:1px solid #dbe3ec;text-align:center;">' . $this->escape($percentage . '%') . '</td>'
                . '<td style="padding:10px;border:1px solid #dbe3ec;text-align:center;">' . $this->escape($resultStatus) . '</td>'
                . '</tr>';
            $rowsText[] = $testName . ': ' . $score . '/' . $total . ' (' . $percentage . '%) - ' . $resultStatus;
        }

        if ($rowsHtml === '') {
            throw new RuntimeException('notification_results_missing');
        }

        if ($isArabic) {
            $intro = 'اكتملت جميع الاختبارات المطلوبة للموظف التالي، وأصبحت النتيجة متاحة للمراجعة الإدارية.';
            $labels = [
                'employee' => 'الموظف',
                'masked_id' => 'رقم الهوية',
                'employee_number' => 'الرقم الوظيفي',
                'department' => 'الإدارة',
                'section' => 'القسم',
                'completed_at' => 'تاريخ الاكتمال',
                'final_status' => 'الحالة النهائية',
                'test' => 'الاختبار',
                'score' => 'الدرجة',
                'percentage' => 'النسبة',
                'status' => 'الحالة',
                'review' => 'فتح سجل الموظف الآمن',
                'security' => 'يتطلب هذا الرابط تسجيل الدخول بحساب إداري مخول.',
            ];
        } else {
            $intro = 'All required assessments have been completed for the following employee. The official result is ready for administrative review.';
            $labels = [
                'employee' => 'Employee',
                'masked_id' => 'Masked ID',
                'employee_number' => 'Employee number',
                'department' => 'Department',
                'section' => 'Section',
                'completed_at' => 'Completion date',
                'final_status' => 'Final status',
                'test' => 'Assessment',
                'score' => 'Score',
                'percentage' => 'Percentage',
                'status' => 'Status',
                'review' => 'Open secure employee record',
                'security' => 'The link requires an authorized administrator account.',
            ];
        }

        $html = '<!doctype html><html lang="' . $language . '" dir="' . $direction . '"><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;background:#f4f7fb;font-family:Arial,Tahoma,sans-serif;color:#172033;direction:' . $direction . ';text-align:' . $align . ';">'
            . '<div style="max-width:760px;margin:24px auto;background:#ffffff;border:1px solid #dbe3ec;border-radius:12px;overflow:hidden;">'
            . '<div style="padding:22px 28px;background:#173a63;color:#ffffff;">'
            . '<h1 style="margin:0;font-size:22px;">' . $this->escape($organization) . '</h1>'
            . '<p style="margin:8px 0 0;opacity:.9;">' . $this->escape($subject) . '</p></div>'
            . '<div style="padding:28px;">'
            . '<p style="margin-top:0;line-height:1.8;">' . $this->escape($intro) . '</p>'
            . '<table role="presentation" style="width:100%;border-collapse:collapse;margin:18px 0;">'
            . $this->infoRow($labels['employee'], $employeeName)
            . $this->infoRow($labels['masked_id'], $maskedId)
            . $this->infoRow($labels['employee_number'], $employeeNumber !== '' ? $employeeNumber : '—')
            . $this->infoRow($labels['department'], $departmentName !== '' ? $departmentName : '—')
            . $this->infoRow($labels['section'], $sectionName !== '' ? $sectionName : '—')
            . $this->infoRow($labels['completed_at'], $completedAt)
            . $this->infoRow($labels['final_status'], $statusLabel)
            . '</table>'
            . '<table style="width:100%;border-collapse:collapse;margin:22px 0;">'
            . '<thead><tr style="background:#eef3f8;">'
            . '<th style="padding:10px;border:1px solid #dbe3ec;">' . $this->escape($labels['test']) . '</th>'
            . '<th style="padding:10px;border:1px solid #dbe3ec;">' . $this->escape($labels['score']) . '</th>'
            . '<th style="padding:10px;border:1px solid #dbe3ec;">' . $this->escape($labels['percentage']) . '</th>'
            . '<th style="padding:10px;border:1px solid #dbe3ec;">' . $this->escape($labels['status']) . '</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '<p style="margin:24px 0;"><a href="' . $this->escape($adminLink) . '" style="display:inline-block;padding:12px 20px;background:#1f6fb2;color:#fff;text-decoration:none;border-radius:7px;">' . $this->escape($labels['review']) . '</a></p>'
            . '<p style="font-size:12px;color:#5f6b7a;line-height:1.6;">' . $this->escape($labels['security']) . '</p>'
            . '</div></div></body></html>';

        $text = $organization . PHP_EOL
            . $subject . PHP_EOL . PHP_EOL
            . $intro . PHP_EOL
            . $labels['employee'] . ': ' . $employeeName . PHP_EOL
            . $labels['masked_id'] . ': ' . $maskedId . PHP_EOL
            . $labels['employee_number'] . ': ' . ($employeeNumber !== '' ? $employeeNumber : '—') . PHP_EOL
            . $labels['department'] . ': ' . ($departmentName !== '' ? $departmentName : '—') . PHP_EOL
            . $labels['section'] . ': ' . ($sectionName !== '' ? $sectionName : '—') . PHP_EOL
            . $labels['completed_at'] . ': ' . $completedAt . PHP_EOL
            . $labels['final_status'] . ': ' . $statusLabel . PHP_EOL . PHP_EOL
            . implode(PHP_EOL, $rowsText) . PHP_EOL . PHP_EOL
            . $labels['review'] . ': ' . $adminLink . PHP_EOL
            . $labels['security'];

        $idempotencyKey = (string)($notification['deduplication_key'] ?? '');
        return new MailMessage($recipient, $subject, $html, $text, $language, $idempotencyKey);
    }

    private function infoRow(string $label, string $value): string
    {
        return '<tr><th style="width:34%;padding:9px;border:1px solid #dbe3ec;background:#f8fafc;">'
            . $this->escape($label)
            . '</th><td style="padding:9px;border:1px solid #dbe3ec;">'
            . $this->escape($value)
            . '</td></tr>';
    }

    private function formatDate(string $utcValue): string
    {
        if ($utcValue === '') {
            return '—';
        }
        try {
            $date = new DateTimeImmutable($utcValue, new DateTimeZone('UTC'));
            $timezone = new DateTimeZone($this->appTimezone);
            return $date->setTimezone($timezone)->format('Y-m-d H:i') . ' ' . $timezone->getName();
        } catch (\Throwable) {
            return '—';
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
