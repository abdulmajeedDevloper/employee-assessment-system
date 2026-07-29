CREATE INDEX idx_attempt_admin_directory
    ON test_attempts (status, started_at, employee_id, test_id);

CREATE INDEX idx_security_attempt_created
    ON security_events (attempt_id, created_at, id);

CREATE INDEX idx_session_attempt_status
    ON test_sessions (attempt_id, status, last_activity_at);

CREATE INDEX idx_audit_entity_history
    ON audit_logs (entity_type, entity_id, created_at, id);

CREATE INDEX idx_retake_assignment_history
    ON retake_authorizations (assignment_id, consumed_at, authorized_at, id);
