# Sprint 4.6B — Secure Excel and PDF Report Exports

## Scope

This sprint adds protected exports for the filtered official-results report introduced in Sprint 4.6A.

## Delivered features

- Authorized Excel (`.xlsx`) and PDF export actions on `/admin/reports`.
- The active report filters are preserved in each export.
- Exports continue to use the latest terminal result per assignment through `vw_official_assessment_results`.
- National ID values remain masked; full IDs and email addresses are not included in export rows.
- Spreadsheet cells containing user-controlled text are written explicitly as strings and protected against formula injection (`=`, `+`, `-`, and `@`).
- Arabic PDF output uses UTF-8, RTL direction, automatic script/language handling, and the bundled DejaVu Sans font.
- Generated files are stored outside `public` in `storage/exports` using cryptographically random names and restrictive file permissions.
- Downloads require a random, session-bound, administrator-bound, one-time token.
- Tokens expire after a short configurable period and validate file size plus SHA-256 before download.
- Download responses disable caching, MIME sniffing, browser opening hints, referrer disclosure, indexing, and byte ranges.
- Files are deleted after a successful download. Expired abandoned files are removed by a cleanup script.
- Export actions are audit logged without employee values, filter values, filenames, tokens, or paths. The audit payload stores field names only.

## Routes

| Method | Route | Permission | Purpose |
|---|---|---|---|
| POST | `/admin/reports/export` | `export_reports` | Generate Excel or PDF export after CSRF verification |
| GET | `/admin/reports/download?token=...` | `export_reports` | Consume a one-time token and download the protected file |

## Export contents

### Excel

- Summary and applied filters
- Official-results directory
- Assessment performance breakdown
- Department performance breakdown

### PDF

- Privacy notice and generation time
- Applied filters
- Summary metrics
- Assessment and department breakdowns
- Official-results directory in landscape format

## Configuration

```env
REPORT_EXPORT_STORAGE_PATH=storage/exports
REPORT_DOWNLOAD_TOKEN_TTL_SECONDS=600
REPORT_XLSX_MAX_ROWS=10000
REPORT_PDF_MAX_ROWS=1000
REPORT_EXPORT_CLEANUP_BATCH_SIZE=500
TEMP_EXPORT_RETENTION_HOURS=24
```

Limits are intentionally bounded in code even when environment values are larger.

## Cleanup automation

Run manually:

```powershell
C:\php82\php.exe scripts\cleanup_report_exports.php
```

Suggested Windows Task Scheduler cadence: hourly.

Suggested Linux cron cadence:

```cron
15 * * * * /usr/bin/php /path/to/employee-assessment-system/scripts/cleanup_report_exports.php >> /path/to/employee-assessment-system/storage/logs/report-export-cleanup.log 2>&1
```

The script only deletes files matching the generated export filename allowlist and older than the configured retention period. It refuses symbolic-link export directories.

## Security decisions

- Generation is a CSRF-protected POST request.
- Download is a GET request carrying an unguessable one-time token; it cannot be reused.
- Tokens are bound to the administrator ID and current authenticated session.
- Stored filenames must match `report_[32 lowercase hex characters].xlsx|pdf`.
- Canonical path checks prevent traversal outside the export directory.
- Size and SHA-256 checks detect file replacement or tampering before streaming.
- No direct public URL exists for generated files.
- PDF HTML values are escaped; spreadsheet text is normalized and formula-protected.

## Verification status

Static syntax, routes, translations, templates, token behavior, allowlists, and security searches are recorded in `evidence/sprint-4.6b-verification.txt`.

Full PHPUnit, MySQL integration, actual Excel generation, and actual PDF generation require a PHP runtime with the project-required extensions, especially `pdo_mysql`, `mbstring`, `dom`, `xmlwriter`, `gd`, and `zip`.
