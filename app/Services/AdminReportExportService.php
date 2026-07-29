<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use App\Support\ReportExportSanitizer;
use App\Support\TimezoneHelper;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PDO;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class AdminReportExportService
{
    private AdminReportService $reportService;
    private AdminReportDownloadTokenService $tokenService;
    private PDO $pdo;
    private string $storageDirectory;

    public function __construct(
        ?AdminReportService $reportService = null,
        ?AdminReportDownloadTokenService $tokenService = null,
        ?PDO $pdo = null,
        ?string $storageDirectory = null
    ) {
        $this->reportService = $reportService ?? new AdminReportService();
        $this->storageDirectory = $storageDirectory ?? $this->resolveStorageDirectory();
        $this->tokenService = $tokenService ?? new AdminReportDownloadTokenService($this->storageDirectory);
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Create an export and return a temporary one-time download token.
     */
    public function createExport(array $input, string $format, int $adminId, string $language): array
    {
        $format = strtolower(trim($format));
        if (!in_array($format, ['xlsx', 'pdf'], true)) {
            return ['code' => 'UNSUPPORTED_FORMAT'];
        }
        if ($adminId <= 0) {
            return ['code' => 'UNAUTHORIZED'];
        }
        $language = in_array($language, ['ar', 'en'], true) ? $language : 'ar';

        $maxRows = $format === 'pdf'
            ? $this->boundedEnvInt('REPORT_PDF_MAX_ROWS', 1000, 1, 5000)
            : $this->boundedEnvInt('REPORT_XLSX_MAX_ROWS', 10000, 1, 20000);

        $storedFilename = '';
        $token = '';
        try {
            $data = $this->reportService->getExportData($input, $maxRows);
            if (!empty($data['too_many_rows'])) {
                return [
                    'code' => 'ROW_LIMIT_EXCEEDED',
                    'max_rows' => $maxRows,
                    'total_rows' => (int)($data['summary']['total_results'] ?? 0),
                ];
            }
            if ((int)($data['summary']['total_results'] ?? 0) === 0) {
                return ['code' => 'NO_DATA'];
            }

            $this->prepareStorageDirectory();
            $storedFilename = 'report_' . bin2hex(random_bytes(16)) . '.' . $format;
            $path = $this->storageDirectory . DIRECTORY_SEPARATOR . $storedFilename;

            if ($format === 'xlsx') {
                $this->writeSpreadsheet($path, $data, $language);
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            } else {
                $this->writePdf($path, $data, $language);
                $mimeType = 'application/pdf';
            }

            if (!is_file($path) || !is_readable($path) || (int)filesize($path) <= 0) {
                throw new RuntimeException('Generated report file is unavailable.');
            }
            @chmod($path, 0600);

            $downloadFilename = 'assessment-report-' . gmdate('Ymd-His') . '.' . $format;
            $token = $this->tokenService->issue(
                $adminId,
                $storedFilename,
                $downloadFilename,
                $mimeType,
                $format
            );

            try {
                $this->logAudit(
                    $adminId,
                    $format === 'xlsx' ? 'EXPORT_REPORT_XLSX' : 'EXPORT_REPORT_PDF',
                    $data['filters'] ?? []
                );
            } catch (Throwable $auditError) {
                $this->tokenService->revoke($token, true);
                error_log('Admin report export audit error: ' . $auditError->getMessage());
                return ['code' => 'INTERNAL_ERROR'];
            }

            return [
                'code' => 'SUCCESS',
                'token' => $token,
                'format' => $format,
                'row_count' => (int)$data['summary']['total_results'],
            ];
        } catch (Throwable $e) {
            if ($token !== '') {
                $this->tokenService->revoke($token, true);
            } elseif ($storedFilename !== '') {
                $candidate = $this->storageDirectory . DIRECTORY_SEPARATOR . $storedFilename;
                if (is_file($candidate)) {
                    @unlink($candidate);
                }
            }
            $diagnosticPath = dirname(__DIR__, 2)
                . DIRECTORY_SEPARATOR . 'storage'
                . DIRECTORY_SEPARATOR . 'report-export-error.log';
            $diagnosticMessage = '[' . date('c') . '] '
                . get_class($e) . ': ' . $e->getMessage() . PHP_EOL
                . $e->getTraceAsString() . PHP_EOL . PHP_EOL;
            @file_put_contents($diagnosticPath, $diagnosticMessage, FILE_APPEND | LOCK_EX);
            error_log('Admin report export error: ' . $e->getMessage());
            return ['code' => 'INTERNAL_ERROR'];
        }
    }

    /**
     * @return array{path:string,download_filename:string,mime_type:string,format:string,size:int}|null
     */
    public function consumeDownload(string $token, int $adminId): ?array
    {
        return $this->tokenService->consume($token, $adminId);
    }

    protected function writeSpreadsheet(string $path, array $data, string $language): void
    {
        if (!class_exists(Spreadsheet::class) || !class_exists(Xlsx::class)) {
            throw new RuntimeException('Spreadsheet export dependency is unavailable.');
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Employee Assessment System')
            ->setTitle(trans('reports_and_analytics'))
            ->setSubject(trans('official_results_directory'))
            ->setDescription(trans('report_privacy_notice'));

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $this->configureSheet($summarySheet, $language);
        $this->writeSummarySheet($summarySheet, $data, $language);

        $resultsSheet = $spreadsheet->createSheet();
        $resultsSheet->setTitle('Results');
        $this->configureSheet($resultsSheet, $language);
        $this->writeResultsSheet($resultsSheet, $data['results'] ?? [], $language);

        $testsSheet = $spreadsheet->createSheet();
        $testsSheet->setTitle('Assessments');
        $this->configureSheet($testsSheet, $language);
        $this->writeTestBreakdownSheet($testsSheet, $data['test_breakdown'] ?? [], $language);

        $departmentsSheet = $spreadsheet->createSheet();
        $departmentsSheet->setTitle('Departments');
        $this->configureSheet($departmentsSheet, $language);
        $this->writeDepartmentBreakdownSheet($departmentsSheet, $data['department_breakdown'] ?? [], $language);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function configureSheet(object $sheet, string $language): void
    {
        $sheet->setRightToLeft($language === 'ar');
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getParent()->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function writeSummarySheet(object $sheet, array $data, string $language): void
    {
        $sheet->mergeCells('A1:D1');
        $this->setTextCell($sheet, 'A1', trans('reports_and_analytics'));
        $sheet->getStyle('A1:D1')->applyFromArray($this->titleStyle());
        $sheet->getRowDimension(1)->setRowHeight(30);

        $this->setTextCell($sheet, 'A3', trans('generated_at'));
        $this->setTextCell($sheet, 'B3', TimezoneHelper::format(gmdate('Y-m-d H:i:s'), null, 'Y-m-d H:i'));
        $sheet->mergeCells('A4:D4');
        $this->setTextCell($sheet, 'A4', trans('report_privacy_notice'));
        $sheet->getStyle('A4:D4')->getAlignment()->setWrapText(true);

        $row = 6;
        $sheet->mergeCells("A{$row}:D{$row}");
        $this->setTextCell($sheet, "A{$row}", trans('applied_filters'));
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($this->sectionStyle());
        $row++;
        foreach ($this->buildFilterRows($data, $language) as [$label, $value]) {
            $this->setTextCell($sheet, "A{$row}", $label);
            $sheet->mergeCells("B{$row}:D{$row}");
            $this->setTextCell($sheet, "B{$row}", $value);
            $row++;
        }

        $row++;
        $sheet->mergeCells("A{$row}:D{$row}");
        $this->setTextCell($sheet, "A{$row}", trans('report_summary'));
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($this->sectionStyle());
        $row++;

        $summary = $data['summary'] ?? [];
        $metrics = [
            [trans('total_results'), (int)($summary['total_results'] ?? 0)],
            [trans('assessed_employees'), (int)($summary['assessed_employees'] ?? 0)],
            [trans('passed'), (int)($summary['passed_results'] ?? 0)],
            [trans('failed'), (int)($summary['failed_results'] ?? 0)],
            [trans('overall_pass_rate'), $this->percent($summary['pass_rate'] ?? 0)],
            [trans('average_result'), $this->percent($summary['average_percentage'] ?? 0)],
            [trans('highest'), $this->percent($summary['highest_percentage'] ?? 0)],
            [trans('lowest'), $this->percent($summary['lowest_percentage'] ?? 0)],
        ];
        foreach ($metrics as [$label, $value]) {
            $this->setTextCell($sheet, "A{$row}", $label);
            $this->setTextCell($sheet, "B{$row}", $value);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(38);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->freezePane('A3');
    }

    private function writeResultsSheet(object $sheet, array $rows, string $language): void
    {
        $headers = [
            trans('employee'), trans('employee_number'), trans('masked_national_id'),
            trans('department'), trans('section'), trans('job_title'), trans('assessment_code'),
            trans('assessment'), trans('attempt_number'), trans('score'), trans('total_score'),
            trans('percentage'), trans('result'), trans('submission_status'), trans('submitted_at'),
        ];
        $this->writeHeaderRow($sheet, $headers, 1);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $values = [
                $row['employee_name'] ?? '',
                $row['employee_number'] ?? '',
                $row['masked_national_id'] ?? '',
                $language === 'en' ? ($row['department_name_en'] ?? '') : ($row['department_name_ar'] ?? ''),
                $language === 'en' ? ($row['section_name_en'] ?? '') : ($row['section_name_ar'] ?? ''),
                $language === 'en' ? ($row['job_title_name_en'] ?? '') : ($row['job_title_name_ar'] ?? ''),
                $row['test_code'] ?? '',
                $language === 'en' ? ($row['test_name_en'] ?? '') : ($row['test_name_ar'] ?? ''),
                (int)($row['attempt_number'] ?? 0),
                round((float)($row['score'] ?? 0), 2),
                round((float)($row['total_score'] ?? 0), 2),
                round((float)($row['percentage'] ?? 0), 2),
                trans((int)($row['passed'] ?? 0) === 1 ? 'passed' : 'failed'),
                trans('attempt_status_' . strtolower((string)($row['attempt_status'] ?? 'COMPLETED'))),
                TimezoneHelper::format((string)($row['submitted_at'] ?? ''), null, 'Y-m-d H:i'),
            ];

            foreach ($values as $columnIndex => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowNumber;
                if (in_array($columnIndex, [8, 9, 10, 11], true)) {
                    $sheet->setCellValue($coordinate, $value);
                } else {
                    $this->setTextCell($sheet, $coordinate, $value);
                }
            }
            $rowNumber++;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = max(1, $rowNumber - 1);
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->freezePane('A2');
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true);
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(
                in_array($column, [1, 4, 5, 6, 8], true) ? 24 : 17
            );
        }
    }

    private function writeTestBreakdownSheet(object $sheet, array $rows, string $language): void
    {
        $headers = [trans('assessment_code'), trans('assessment'), trans('results'), trans('passed'), trans('failed'), trans('average_result'), trans('pass_rate')];
        $this->writeHeaderRow($sheet, $headers, 1);
        $rowNumber = 2;
        foreach ($rows as $row) {
            $values = [
                $row['code'] ?? '',
                $language === 'en' ? ($row['name_en'] ?? '') : ($row['name_ar'] ?? ''),
                (int)($row['result_count'] ?? 0),
                (int)($row['passed_count'] ?? 0),
                (int)($row['failed_count'] ?? 0),
                round((float)($row['average_percentage'] ?? 0), 2),
                round((float)($row['pass_rate'] ?? 0), 2),
            ];
            foreach ($values as $index => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($index + 1) . $rowNumber;
                if ($index >= 2) {
                    $sheet->setCellValue($coordinate, $value);
                } else {
                    $this->setTextCell($sheet, $coordinate, $value);
                }
            }
            $rowNumber++;
        }
        $sheet->freezePane('A2');
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['A', 'B'], true) ? 28 : 16);
        }
    }

    private function writeDepartmentBreakdownSheet(object $sheet, array $rows, string $language): void
    {
        $headers = [trans('department'), trans('assessed_employees'), trans('results'), trans('average_result'), trans('pass_rate')];
        $this->writeHeaderRow($sheet, $headers, 1);
        $rowNumber = 2;
        foreach ($rows as $row) {
            $values = [
                $language === 'en' ? ($row['name_en'] ?? '') : ($row['name_ar'] ?? ''),
                (int)($row['assessed_employees'] ?? 0),
                (int)($row['result_count'] ?? 0),
                round((float)($row['average_percentage'] ?? 0), 2),
                round((float)($row['pass_rate'] ?? 0), 2),
            ];
            foreach ($values as $index => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($index + 1) . $rowNumber;
                if ($index >= 1) {
                    $sheet->setCellValue($coordinate, $value);
                } else {
                    $this->setTextCell($sheet, $coordinate, $value);
                }
            }
            $rowNumber++;
        }
        $sheet->freezePane('A2');
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'A' ? 30 : 18);
        }
    }

    private function writeHeaderRow(object $sheet, array $headers, int $row): void
    {
        foreach ($headers as $index => $header) {
            $coordinate = Coordinate::stringFromColumnIndex($index + 1) . $row;
            $this->setTextCell($sheet, $coordinate, $header);
        }
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($this->headerStyle());
        $sheet->getRowDimension($row)->setRowHeight(25);
    }

    private function setTextCell(object $sheet, string $coordinate, mixed $value): void
    {
        $sheet->setCellValueExplicit(
            $coordinate,
            ReportExportSanitizer::spreadsheetText($value),
            DataType::TYPE_STRING
        );
    }

    protected function writePdf(string $path, array $data, string $language): void
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF export dependency is unavailable.');
        }

        $tempDirectory = dirname($this->storageDirectory) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'mpdf';
        if (!is_dir($tempDirectory) && !mkdir($tempDirectory, 0700, true) && !is_dir($tempDirectory)) {
            throw new RuntimeException('Unable to create PDF temporary directory.');
        }
        if (is_link($tempDirectory)) {
            throw new RuntimeException('Unsafe PDF temporary directory.');
        }
        @chmod($tempDirectory, 0700);

        $isArabic = $language === 'ar';
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'tempDir' => $tempDirectory,
            'default_font' => 'dejavusans',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 11,
            'margin_bottom' => 11,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetTitle(trans('reports_and_analytics'));
        $mpdf->SetAuthor('Employee Assessment System');
        $mpdf->SetCreator('Employee Assessment System');
        $mpdf->SetSubject(trans('official_results_directory'));
        $mpdf->SetDirectionality($isArabic ? 'rtl' : 'ltr');
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->SetHTMLFooter(
            '<div style="font-size:8px;color:#64748b;text-align:center">{PAGENO} / {nbpg}</div>'
        );
        $mpdf->WriteHTML($this->buildPdfHtml($data, $language));
        $mpdf->Output($path, Destination::FILE);
    }

    private function buildPdfHtml(array $data, string $language): string
    {
        $direction = $language === 'ar' ? 'rtl' : 'ltr';
        $align = $language === 'ar' ? 'right' : 'left';
        $summary = $data['summary'] ?? [];
        $html = '<!doctype html><html lang="' . $language . '" dir="' . $direction . '"><head><meta charset="UTF-8">';
        $html .= '<style>'
            . 'body{font-family:dejavusans;font-size:9px;color:#0f172a;direction:' . $direction . ';text-align:' . $align . ';}'
            . 'h1{font-size:20px;margin:0 0 5px;color:#0f172a}.muted{color:#64748b}.notice{border:1px solid #0891b2;background:#ecfeff;padding:8px;margin:10px 0}'
            . '.section{font-size:13px;font-weight:bold;background:#e2e8f0;padding:6px;margin-top:12px}'
            . 'table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border:1px solid #cbd5e1;padding:5px;vertical-align:top}th{background:#1e3a8a;color:#fff;font-weight:bold}'
            . '.metrics td{width:25%;font-size:10px}.metric-label{color:#64748b}.metric-value{font-size:15px;font-weight:bold}.nowrap{white-space:nowrap}'
            . '.pass{color:#166534;font-weight:bold}.fail{color:#991b1b;font-weight:bold}.page-break{page-break-before:always}'
            . '</style></head><body>';
        $html .= '<h1>' . ReportExportSanitizer::html(trans('reports_and_analytics')) . '</h1>';
        $html .= '<div class="muted">' . ReportExportSanitizer::html(trans('generated_at')) . ': ' . ReportExportSanitizer::html(TimezoneHelper::format(gmdate('Y-m-d H:i:s'), null, 'Y-m-d H:i')) . '</div>';
        $html .= '<div class="notice">' . ReportExportSanitizer::html(trans('report_privacy_notice')) . '</div>';

        $html .= '<div class="section">' . ReportExportSanitizer::html(trans('applied_filters')) . '</div><table>';
        foreach ($this->buildFilterRows($data, $language) as [$label, $value]) {
            $html .= '<tr><td style="width:25%;font-weight:bold">' . ReportExportSanitizer::html($label) . '</td><td>' . ReportExportSanitizer::html($value) . '</td></tr>';
        }
        $html .= '</table>';

        $metrics = [
            [trans('total_results'), (string)(int)($summary['total_results'] ?? 0)],
            [trans('assessed_employees'), (string)(int)($summary['assessed_employees'] ?? 0)],
            [trans('passed'), (string)(int)($summary['passed_results'] ?? 0)],
            [trans('failed'), (string)(int)($summary['failed_results'] ?? 0)],
            [trans('overall_pass_rate'), $this->percent($summary['pass_rate'] ?? 0)],
            [trans('average_result'), $this->percent($summary['average_percentage'] ?? 0)],
            [trans('highest'), $this->percent($summary['highest_percentage'] ?? 0)],
            [trans('lowest'), $this->percent($summary['lowest_percentage'] ?? 0)],
        ];
        $html .= '<div class="section">' . ReportExportSanitizer::html(trans('report_summary')) . '</div><table class="metrics">';
        for ($i = 0; $i < count($metrics); $i += 4) {
            $html .= '<tr>';
            foreach (array_slice($metrics, $i, 4) as [$label, $value]) {
                $html .= '<td><div class="metric-label">' . ReportExportSanitizer::html($label) . '</div><div class="metric-value">' . ReportExportSanitizer::html($value) . '</div></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        $html .= $this->buildPdfBreakdownTables($data, $language);
        $html .= '<div class="section page-break">' . ReportExportSanitizer::html(trans('official_results_directory')) . '</div>';
        $html .= '<table><thead><tr>';
        $resultHeaders = [trans('employee'), trans('employee_number'), trans('masked_national_id'), trans('organization_unit'), trans('assessment'), trans('attempt_number'), trans('score'), trans('result'), trans('submission_status'), trans('submitted_at')];
        foreach ($resultHeaders as $header) {
            $html .= '<th>' . ReportExportSanitizer::html($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($data['results'] ?? [] as $row) {
            $department = $language === 'en' ? ($row['department_name_en'] ?? '') : ($row['department_name_ar'] ?? '');
            $section = $language === 'en' ? ($row['section_name_en'] ?? '') : ($row['section_name_ar'] ?? '');
            $testName = $language === 'en' ? ($row['test_name_en'] ?? '') : ($row['test_name_ar'] ?? '');
            $passed = (int)($row['passed'] ?? 0) === 1;
            $cells = [
                $row['employee_name'] ?? '',
                $row['employee_number'] ?? '',
                $row['masked_national_id'] ?? '',
                $department . ' / ' . $section,
                ($row['test_code'] ?? '') . ' — ' . $testName,
                (string)(int)($row['attempt_number'] ?? 0),
                number_format((float)($row['score'] ?? 0), 2) . ' / ' . number_format((float)($row['total_score'] ?? 0), 2) . ' (' . $this->percent($row['percentage'] ?? 0) . ')',
                trans($passed ? 'passed' : 'failed'),
                trans('attempt_status_' . strtolower((string)($row['attempt_status'] ?? 'COMPLETED'))),
                TimezoneHelper::format((string)($row['submitted_at'] ?? ''), null, 'Y-m-d H:i'),
            ];
            $html .= '<tr>';
            foreach ($cells as $index => $cell) {
                $class = $index === 7 ? ($passed ? 'pass' : 'fail') : '';
                $html .= '<td class="' . $class . '">' . ReportExportSanitizer::html($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';
        return $html;
    }

    private function buildPdfBreakdownTables(array $data, string $language): string
    {
        $html = '<div class="section">' . ReportExportSanitizer::html(trans('performance_by_assessment')) . '</div>';
        $html .= '<table><thead><tr><th>' . ReportExportSanitizer::html(trans('assessment')) . '</th><th>' . ReportExportSanitizer::html(trans('results')) . '</th><th>' . ReportExportSanitizer::html(trans('average_result')) . '</th><th>' . ReportExportSanitizer::html(trans('pass_rate')) . '</th></tr></thead><tbody>';
        foreach ($data['test_breakdown'] ?? [] as $row) {
            $name = $language === 'en' ? ($row['name_en'] ?? '') : ($row['name_ar'] ?? '');
            $html .= '<tr><td>' . ReportExportSanitizer::html(($row['code'] ?? '') . ' — ' . $name) . '</td><td>' . (int)($row['result_count'] ?? 0) . '</td><td>' . ReportExportSanitizer::html($this->percent($row['average_percentage'] ?? 0)) . '</td><td>' . ReportExportSanitizer::html($this->percent($row['pass_rate'] ?? 0)) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        $html .= '<div class="section">' . ReportExportSanitizer::html(trans('performance_by_department')) . '</div>';
        $html .= '<table><thead><tr><th>' . ReportExportSanitizer::html(trans('department')) . '</th><th>' . ReportExportSanitizer::html(trans('assessed_employees')) . '</th><th>' . ReportExportSanitizer::html(trans('average_result')) . '</th><th>' . ReportExportSanitizer::html(trans('pass_rate')) . '</th></tr></thead><tbody>';
        foreach ($data['department_breakdown'] ?? [] as $row) {
            $name = $language === 'en' ? ($row['name_en'] ?? '') : ($row['name_ar'] ?? '');
            $html .= '<tr><td>' . ReportExportSanitizer::html($name) . '</td><td>' . (int)($row['assessed_employees'] ?? 0) . '</td><td>' . ReportExportSanitizer::html($this->percent($row['average_percentage'] ?? 0)) . '</td><td>' . ReportExportSanitizer::html($this->percent($row['pass_rate'] ?? 0)) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    /** @return array<int,array{0:string,1:string}> */
    private function buildFilterRows(array $data, string $language): array
    {
        $filters = $data['filters'] ?? [];
        $department = $this->lookupName($data['departments'] ?? [], (int)($filters['department_id'] ?? 0), $language);
        $section = $this->lookupName($data['sections'] ?? [], (int)($filters['section_id'] ?? 0), $language);
        $test = $this->lookupTest($data['tests'] ?? [], (int)($filters['test_id'] ?? 0), $language);
        $outcome = (string)($filters['outcome'] ?? 'all');
        $status = (string)($filters['attempt_status'] ?? 'all');

        return [
            [trans('search'), (string)($filters['q'] ?? '') !== '' ? (string)$filters['q'] : trans('not_specified')],
            [trans('department'), $department !== '' ? $department : trans('all_departments')],
            [trans('section'), $section !== '' ? $section : trans('all_sections')],
            [trans('assessment'), $test !== '' ? $test : trans('all_assessments')],
            [trans('result'), trans($outcome === 'all' ? 'all_results' : $outcome)],
            [trans('submission_status'), trans($status === 'all' ? 'all_submission_statuses' : 'attempt_status_' . strtolower($status))],
            [trans('submitted_from'), (string)($filters['submitted_from_input'] ?? '') !== '' ? (string)$filters['submitted_from_input'] : trans('not_specified')],
            [trans('submitted_until'), (string)($filters['submitted_until_input'] ?? '') !== '' ? (string)$filters['submitted_until_input'] : trans('not_specified')],
        ];
    }

    private function lookupName(array $items, int $id, string $language): string
    {
        if ($id <= 0) {
            return '';
        }
        foreach ($items as $item) {
            if ((int)($item['id'] ?? 0) === $id) {
                return (string)($language === 'en' ? ($item['name_en'] ?? '') : ($item['name_ar'] ?? ''));
            }
        }
        return '';
    }

    private function lookupTest(array $items, int $id, string $language): string
    {
        if ($id <= 0) {
            return '';
        }
        foreach ($items as $item) {
            if ((int)($item['id'] ?? 0) === $id) {
                $code = trim((string)($item['code'] ?? ''));
                $name = trim((string)($language === 'en' ? ($item['name_en'] ?? '') : ($item['name_ar'] ?? '')));
                if ($code !== '' && $name !== '') {
                    return $code . ' — ' . $name;
                }
                return $code !== '' ? $code : $name;
            }
        }
        return '';
    }

    private function titleStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
    }

    private function sectionStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '93C5FD']]],
        ];
    }

    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ];
    }

    private function prepareStorageDirectory(): void
    {
        if (!is_dir($this->storageDirectory) && !mkdir($this->storageDirectory, 0700, true) && !is_dir($this->storageDirectory)) {
            throw new RuntimeException('Unable to create report export directory.');
        }
        if (is_link($this->storageDirectory)) {
            throw new RuntimeException('Unsafe report export directory.');
        }
        @chmod($this->storageDirectory, 0700);
        if (!is_writable($this->storageDirectory)) {
            throw new RuntimeException('Report export directory is not writable.');
        }
    }

    private function resolveStorageDirectory(): string
    {
        $configured = trim((string)($_ENV['REPORT_EXPORT_STORAGE_PATH'] ?? 'storage/exports'));
        if ($configured === '') {
            $configured = 'storage/exports';
        }
        if (str_starts_with($configured, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $configured) === 1) {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured);
    }

    private function logAudit(int $adminId, string $action, array $filters): void
    {
        $filterFields = [];
        foreach (['q', 'department_id', 'section_id', 'test_id', 'outcome', 'attempt_status', 'submitted_from', 'submitted_until'] as $field) {
            $value = $filters[$field] ?? null;
            if ($value !== null && $value !== '' && $value !== 'all') {
                $filterFields[] = $field;
            }
        }

        $stmt = $this->pdo->prepare("\n            INSERT INTO audit_logs (\n                actor_type, admin_id, employee_id, action, entity_type, entity_id,\n                old_values, new_values, ip_address, user_agent, created_at\n            ) VALUES (\n                'ADMIN', :admin_id, NULL, :action, 'REPORT_EXPORT', :entity_id,\n                NULL, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP()\n            )\n        ");
        $stmt->execute([
            ':admin_id' => $adminId,
            ':action' => $action,
            ':entity_id' => $adminId,
            ':new_values' => json_encode([
                'exported_field_names' => [
                    'employee_name', 'employee_number', 'masked_national_id', 'department', 'section',
                    'job_title', 'test_code', 'test_name', 'attempt_number', 'score', 'total_score',
                    'percentage', 'result', 'attempt_status', 'submitted_at',
                ],
                'filter_field_names' => $filterFields,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
        ]);
    }

    private function boundedEnvInt(string $key, int $default, int $min, int $max): int
    {
        $value = filter_var($_ENV[$key] ?? $default, FILTER_VALIDATE_INT);
        if ($value === false) {
            $value = $default;
        }
        return max($min, min($max, (int)$value));
    }

    private function percent(mixed $value): string
    {
        return number_format((float)$value, 2, '.', '') . '%';
    }
}