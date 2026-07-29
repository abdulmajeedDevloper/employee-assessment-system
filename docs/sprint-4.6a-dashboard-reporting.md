# Sprint 4.6A — Executive Dashboard and Reporting Foundation

## Delivered scope

- Replaced the four-card administration landing page with an executive dashboard backed by live database queries.
- Added employee, progress, assignment, attempt, result, follow-up, and recent-security-event indicators.
- Added completion rate, overall pass rate, average/highest/lowest official result, test performance, department performance, and recent official results.
- Added follow-up lists for expired active attempts and overdue open assignments.
- Added a protected reports module at `/admin/reports`.
- Added report filters for employee search, department, section, assessment, outcome, terminal status, and local submitted-date boundaries.
- Added report summaries, assessment breakdowns, department breakdowns, and paginated official-result rows.
- Reports use `vw_official_assessment_results`, so an assignment contributes only its latest finalized `COMPLETED` or `TIMED_OUT` attempt.
- Full National ID values are not queried or displayed. The report directory exposes only the masked form built from `national_id_last4`.
- Updated administrator navigation to respect RBAC permissions and added the Reports section for authorized administrators.
- Added report-query indexes through migration `004_reporting_indexes`.

## Authorization

The reports page requires `view_reports`.

- `super_admin`: unrestricted.
- `supervisor`: dashboard, operational modules, reports, and future exports.
- `report_viewer`: dashboard, employee directory, reports, and future exports; operational attempts, assignments, tests, and questions remain unavailable.

The layout now hides navigation entries the signed-in role cannot access instead of displaying links that will always return HTTP 403.

## Report rules

- Search is limited to employee name, employee number, email, assessment code, and translated assessment names.
- Only non-archived employees are included.
- Date inputs are interpreted in `APP_TIMEZONE` and converted to UTC before repository queries.
- Allowed report outcomes are `all`, `passed`, and `failed`.
- Allowed terminal statuses are `ALL`, `COMPLETED`, and `TIMED_OUT`.
- Query identifiers are positive integers only.
- All dynamic values use prepared PDO parameters.
- Pagination is capped by the repository.

## Routes

### GET

- `/admin/dashboard`
- `/admin/reports`

Sprint 4.6A is read-only and adds no state-changing report endpoint.

## Database migration

Apply:

```bash
php scripts/migrate.php
```

Migration files:

- `database/migrations/004_reporting_indexes.up.sql`
- `database/migrations/004_reporting_indexes.down.sql`

The indexes cover official result selection, report filters, overdue assignments, organizational units, progress status, and recent security events.

## Tests added

- `tests/Unit/AdminReportServiceTest.php`
- `tests/Integration/AdminReportRepositoryTest.php`

The unit coverage validates filter whitelisting, identifier parsing, Yemen/Saudi-compatible UTC boundary conversion through `APP_TIMEZONE`, invalid input fallback, pagination, and summary normalization.

The integration coverage validates latest-terminal-attempt selection, filtered summaries, pass/fail counts, masked identifier output, and assessment/department breakdowns.

## Next sprint

Sprint 4.6B now provides authorized Excel and PDF exports, spreadsheet-formula-injection protection, RTL Arabic PDF output, protected temporary export storage, one-time download tokens, and cleanup automation. See `docs/sprint-4.6b-secure-report-exports.md`.
