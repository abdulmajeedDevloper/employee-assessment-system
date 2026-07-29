-- Migration rollback: 005_email_notification_queue_hardening.down.sql

ALTER TABLE email_notifications
    DROP INDEX idx_mail_employee_type_created,
    DROP INDEX idx_mail_queue_stale,
    DROP CHECK chk_mail_sent_timestamp,
    DROP CHECK chk_mail_attempts_nonnegative;
