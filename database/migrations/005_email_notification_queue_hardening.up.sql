-- Migration: 005_email_notification_queue_hardening.up.sql
-- Hardens the database-backed email outbox for concurrent cron workers.

-- Normalize any legacy rows before enforcing state constraints.
UPDATE email_notifications
SET attempts = 0
WHERE attempts < 0;

UPDATE email_notifications
SET sent_at = COALESCE(sent_at, created_at)
WHERE status = 'SENT'
  AND sent_at IS NULL;

UPDATE email_notifications
SET sent_at = NULL
WHERE status <> 'SENT'
  AND sent_at IS NOT NULL;

ALTER TABLE email_notifications
    ADD CONSTRAINT chk_mail_attempts_nonnegative CHECK (attempts >= 0),
    ADD CONSTRAINT chk_mail_sent_timestamp CHECK (
        (status = 'SENT' AND sent_at IS NOT NULL) OR
        (status <> 'SENT' AND sent_at IS NULL)
    ),
    ADD KEY idx_mail_queue_stale (status, locked_at),
    ADD KEY idx_mail_employee_type_created (employee_id, type, created_at);
