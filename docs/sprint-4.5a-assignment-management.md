# Sprint 4.5A — Assessment Assignment Management

## Delivered scope

- Added an administrator assignment directory with search, employee/test filters, assignment-state filters, effective availability filters, pagination, attempt counts, latest result, and assignment details.
- Added secure assignment creation for active, non-archived employees and active assessments.
- Added configurable maximum attempts from 1 to 20 and optional assignment-specific availability dates.
- Added assignment editing while the assignment is still `PENDING` and no attempt has been recorded.
- Employee and assessment identifiers are immutable after assignment creation.
- Added direct “Create Assignment” shortcuts from active employee and active assessment detail pages.
- Added the assignments section to the administrator navigation.
- Updated the employee assessment page so unavailable assignments do not expose an active Start button. Upcoming and expired assignments display a clear availability message.

## Authorization

The new permissions are:

- `view_assignments`
- `manage_assignments`

`super_admin` has full access through the existing unrestricted rule. `supervisor` can view and manage assignments. `report_viewer` cannot access the operational assignment module.

## HTTP endpoints

### GET

- `/admin/assignments`
- `/admin/assignments/create`
- `/admin/assignments/edit?id={id}`
- `/admin/assignments/view?id={id}`

### POST

- `/admin/assignments/create`
- `/admin/assignments/edit`

All state-changing endpoints require an authenticated administrator, the `manage_assignments` permission, and a valid CSRF token.

## Integrity and concurrency controls

- Employee and assessment selections are validated against active server-side reference lists.
- Selected employee and assessment rows are locked and revalidated inside the creation transaction to prevent time-of-check/time-of-use races.
- The existing unique database constraint on `(employee_id, test_id)` remains the final duplicate-assignment guard.
- Edit operations use a 64-character cryptographic one-time token bound to the administrator, assignment, and a fingerprint of editable assignment state.
- Tokens expire after two hours and each session retains no more than 20 active assignment edit tokens.
- Assignment edits are blocked after the status leaves `PENDING` or any attempt exists.
- Availability values are interpreted in `APP_TIMEZONE` and persisted as UTC.

## Audit privacy

- `CREATE_ASSIGNMENT` stores only `created_field_names`.
- `UPDATE_ASSIGNMENT` stores only `changed_field_names` in both old and new audit payloads.
- Audit JSON does not include employee identifiers, test identifiers, attempt-limit values, or availability timestamps.

## Database behavior

This sprint uses the existing `test_assignments` table and constraints. No new migration is required.

## Verification commands for the deployment workstation

```powershell
C:\php82\php.exe vendor\bin\phpunit --filter AdminAssignment
C:\php82\php.exe vendor\bin\phpunit
```

The full suite must run against the isolated `employee_assessment_test` database before production delivery.
