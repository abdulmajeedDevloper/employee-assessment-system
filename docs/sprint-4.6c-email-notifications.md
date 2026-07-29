# Sprint 4.6C — Secure Assessment Completion Email Notifications

## Scope

This sprint implements the database-backed notification outbox required by Phase 6. A notification is queued only after the employee's configured required assessments have official completed results. The default required assessment codes are `IQ_TEST` and `ENG_TEST` and can be changed through `NOTIFICATION_REQUIRED_TEST_CODES`. Completion messages are sent only to the protected HR/management addresses configured in `ASSESSMENT_COMPLETION_RECIPIENTS`; they are not sent to the employee address.

## Implemented workflow

1. Manual submission or automatic timeout finalizes the last required assignment inside the existing database transaction.
2. `AssessmentCompletionNotificationService` verifies `assessment_progress`, loads the latest official result for every required test, creates a masked payload, and inserts one idempotent `PENDING` outbox row.
3. `scripts/process_email_notifications.php` atomically claims a bounded batch with `FOR UPDATE SKIP LOCKED`.
4. `PHPMailer` sends a UTF-8 HTML and plain-text message in the employee's preferred language.
5. The worker marks the row `SENT`, or schedules a bounded retry using exponential backoff. The final failure state is `FAILED`.
6. Stale `PROCESSING` rows are recovered automatically after the configured lock timeout.

## Security controls

- Employee email addresses and full National ID values are never queried for delivery or inserted into the notification payload. Recipients come only from protected environment configuration. Only `********` plus the stored last four digits are included.
- The email contains a protected administrator URL. Opening it still requires a valid authorized administrator session.
- Queue insertion is idempotent through a deterministic SHA-256 deduplication key.
- SMTP credentials are environment-only and are not included in source files, database payloads, console output, or logs.
- Delivery failures store only a technical exception class/code. Raw SMTP messages, recipients, bodies, passwords, and server responses are not stored in protected logs.
- HTML values are escaped and a plain-text alternative is always generated.
- Concurrent workers use row locking and `SKIP LOCKED`; stale worker locks are recovered safely.
- Retry counts and sent timestamps are protected by Migration `005_email_notification_queue_hardening` constraints.

## Required environment values

Copy the mail section from `.env.example` into the protected production `.env` and replace every placeholder:

```env
MAIL_NOTIFICATIONS_ENABLED=true
ASSESSMENT_COMPLETION_RECIPIENTS=hr@example.com,assessment-manager@example.com
NOTIFICATION_REQUIRED_TEST_CODES=IQ_TEST,ENG_TEST
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_SMTP_AUTH=true
MAIL_USERNAME=mailer@example.com
MAIL_PASSWORD=change_me
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Employee Assessment System"
MAIL_TIMEOUT_SECONDS=20
MAIL_QUEUE_STALE_LOCK_MINUTES=15
```

The existing settings table controls `mail_queue_batch_size` and `mail_queue_max_attempts`.

## Deployment commands

```powershell
C:\php82\php.exe scripts\migrate.php up
C:\php82\php.exe scripts\process_email_notifications.php --dry-run
C:\php82\php.exe scripts\reconcile_completion_notifications.php --limit=500 --after-id=0
C:\php82\php.exe scripts\process_email_notifications.php
```

The reconciliation command is safe to repeat. Duplicate completion messages are rejected by the database deduplication key. For more than 500 completed employees, rerun it with the `Next cursor` printed by the previous execution.

## Scheduler examples

Linux cron every two minutes:

```cron
*/2 * * * * /usr/bin/php /var/www/employee-assessment-system/scripts/process_email_notifications.php >> /var/www/employee-assessment-system/storage/logs/mail-cron.log 2>&1
```

Windows Task Scheduler command:

```cmd
schtasks /create /tn "EmployeeAssessmentMailQueue" /tr "C:\php82\php.exe C:\path\employee-assessment-system\scripts\process_email_notifications.php" /sc minute /mo 2
```

## Operational checks

- Run `--dry-run` before enabling the scheduler.
- Send one test assessment to a controlled mailbox and verify Arabic/English layout, RTL/LTR, links, spam classification, and sender authentication.
- Configure SPF, DKIM, and DMARC for the production sender domain through the mail provider.
- Monitor only notification IDs, counts, statuses, and technical error codes. Do not copy queue payloads into tickets or logs.

## Rollback

1. Disable the cron or scheduled task.
2. Set `MAIL_NOTIFICATIONS_ENABLED=false`.
3. Pending rows remain in the database and can be processed after re-enabling the worker.
4. Migration `005` may be rolled back only after the worker is disabled and no row is in `PROCESSING` state.

## Added tests

- `tests/Unit/EmailRetryPolicyTest.php`
- `tests/Unit/AssessmentCompletionEmailBuilderTest.php`
- `tests/Integration/AssessmentCompletionNotificationTest.php`

The integration test verifies deduplication, masked identity data, bilingual message construction, one-time sending, retry scheduling, and value-free audit metadata.
