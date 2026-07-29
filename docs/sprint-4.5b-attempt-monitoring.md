# Sprint 4.5B — Administrative Attempt Monitoring and Recovery

## Delivered scope

- Added an administrator attempt directory with search, employee/test/assignment filters, attempt-status and result filters, attention filters, date boundaries, statistics, and pagination.
- Added a detailed attempt view containing employee and assessment context, assignment state, timing, device/IP information, score, submitted answers, correct-answer snapshots, test sessions, security events, focus-loss count, supervisor notes, cancellation details, and audit history.
- Added administrative cancellation for active, non-expired attempts. Cancellation requires a meaningful reason, closes active test sessions, completes the assignment, and recomputes employee assessment progress.
- Added one-time retake authorization for the latest terminal attempt. The workflow prevents duplicate pending authorizations, blocks authorization while the employee has an active attempt, and increases the assignment attempt limit only when required, up to 20 attempts.
- Retake authorizations are now consumed atomically when the employee starts the authorized attempt. An assignment marked `RETAKE_AUTHORIZED` cannot start without an unconsumed authorization row.
- Added supervisor-note creation, update, and clearing with a 2,000-character limit.
- Added manual finalization of expired `IN_PROGRESS` attempts through the existing canonical timeout service. The same score calculation, answer preservation, session expiry, assignment completion, progress update, and system audit path are reused.
- Added direct links between assignment details and the assignment's attempt history.
- Added the attempt-monitoring section to the administrator navigation for authorized roles.

## Authorization

The attempt module uses the following permissions:

- `view_attempts`
- `cancel_attempt`
- `authorize_retake`
- `reset_attempt` for finalizing an expired stuck attempt
- `manage_attempt_notes`

`super_admin` remains unrestricted. `supervisor` receives all attempt-monitoring permissions. `report_viewer` does not receive access because attempt details include operational and security-sensitive data.

## Concurrency and request security

- Every POST action requires the corresponding permission and a valid CSRF token.
- Administrative actions use 64-character cryptographically random one-time tokens.
- Tokens are bound to the administrator, attempt, action, and a fingerprint of the attempt/assignment state.
- Tokens expire after two hours and are limited to 20 live tokens per session.
- Repository writes use prepared PDO statements and explicit transactions.
- Retake creation is protected both by row locks and the database-generated unique pending-authorization key.
- Attempt cancellation uses a conditional update so a changed, submitted, or expired attempt cannot be cancelled through a stale request.

## Audit privacy

New administrator audit payloads contain field-name arrays only. Cancellation reasons, retake reasons, supervisor-note contents, employee identifiers, assessment values, scores, and device data are not copied into the new administrator audit JSON payloads.

The operational reason and supervisor note remain stored only in their dedicated business tables/columns, where they are required by the feature.

## Database migration

Migration `003_attempt_monitoring_indexes.up.sql` adds composite indexes for:

- attempt directory/status/date access;
- security events by attempt and time;
- test sessions by attempt and status;
- audit history by entity;
- retake-authorization history by assignment.

Rollback is provided in `003_attempt_monitoring_indexes.down.sql`.

Apply pending migrations with:

```bash
php scripts/migrate.php
```

## Routes

### GET

- `/admin/attempts`
- `/admin/attempts/view?id={attempt_id}`

### POST

- `/admin/attempts/cancel`
- `/admin/attempts/retake`
- `/admin/attempts/notes`
- `/admin/attempts/finalize-expired`

## Tests added

- `tests/Unit/AdminAttemptActionValidatorTest.php`
- `tests/Integration/AdminAttemptManagementTest.php`

The integration coverage includes cancellation/session closure, one-time retake authorization, atomic authorization consumption when a new attempt starts, supervisor-note updates, one-time token replay rejection, and field-name-only audit payloads.

## Operational note

The scheduled worker in `scripts/finalize_expired_attempts.php` remains the primary automatic mechanism for expired attempts. The administrator finalization action is a controlled recovery path for an attempt that is still shown as `IN_PROGRESS` after its expiry time.
