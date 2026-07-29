# Phase 3 Automated Assessment Expiry Daemon & Cron Documentation

This document describes the CLI daemon script `scripts/finalize_expired_attempts.php`, its configuration options, exit codes, lock behavior, dry-run mode, and scheduling instructions.

---

## 1. Environment & Configuration Controls

The worker reads configuration from environment variables defined in `.env`:

| Variable | Type | Default | Range | Description |
| :--- | :--- | :--- | :--- | :--- |
| `FINALIZE_EXPIRED_BATCH_SIZE` | Integer | `50` | `1` to `500` | Maximum number of candidate expired attempts processed in one execution. |

---

## 2. Named Lock & Concurrency Protections

- **MySQL Named Lock**: The worker acquires `SELECT GET_LOCK('employee_assessment_finalize_expired', 0)`.
- **Lock Failure**: If another worker instance is currently running, the worker immediately skips execution and exits with status code `2`.
- **Lock Release**: The named lock is unconditionally released in a `finally` block via `SELECT RELEASE_LOCK(...)`.
- **Transaction Row-Locking**: Individual attempt processing uses `SELECT ... FOR UPDATE` inside an isolated per-attempt transaction.

---

## 3. Worker Exit Codes

| Exit Code | Symbol | Meaning |
| :--- | :--- | :--- |
| `0` | `SUCCESS` | Worker completed successfully (including successful `--dry-run`). |
| `1` | `FATAL_ERROR` | Fatal setup, environment, or database connection failure. |
| `2` | `LOCK_NOT_ACQUIRED` | Worker skipped execution because another instance holds the named lock. |
| `3` | `PARTIAL_FAILURE` | One or more batch items encountered errors while other items succeeded. |

---

## 4. CLI Usage Instructions

### Standard Execution
```bash
/usr/bin/php scripts/finalize_expired_attempts.php
```

### Dry-Run Execution (Non-Mutating Inspection)
```bash
/usr/bin/php scripts/finalize_expired_attempts.php --dry-run
```

---

## 5. Scheduling Setup

### Linux Crontab Entry (Every Minute)
```bash
* * * * * /usr/bin/php /var/www/employee-assessment-system/scripts/finalize_expired_attempts.php >> /var/www/employee-assessment-system/storage/logs/cron.log 2>&1
```

### Windows Task Scheduler Command
```cmd
schtasks /create /tn "FinalizeExpiredAttempts" /tr "C:\php82\php.exe C:\Users\AboAbdullah\.gemini\antigravity-ide\scratch\employee-assessment-system\scripts\finalize_expired_attempts.php" /sc minute /mo 1
```

---

## 6. Rollback Instructions

To disable automated background finalization:
1. Remove the Crontab entry or disable the Windows Task Scheduler task.
2. The web application's server-side fallback (`AssessmentStatusController::showStatus()`) continues to handle expired attempt finalization upon employee dashboard visit.
---

## 7. Email Notification Queue Worker

After configuring SMTP values and applying Migration `005`, schedule:

```bash
*/2 * * * * /usr/bin/php /var/www/employee-assessment-system/scripts/process_email_notifications.php >> /var/www/employee-assessment-system/storage/logs/mail-cron.log 2>&1
```

A non-mutating queue check is available with:

```bash
/usr/bin/php scripts/process_email_notifications.php --dry-run
```

Full configuration, retry, reconciliation, privacy, and rollback instructions are in `docs/sprint-4.6c-email-notifications.md`.
