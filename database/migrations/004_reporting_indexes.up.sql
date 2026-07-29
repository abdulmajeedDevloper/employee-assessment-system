-- Sprint 4.6A: dashboard and reporting query indexes

CREATE INDEX idx_attempt_official_result
    ON test_attempts (assignment_id, status, attempt_number, submitted_at);

CREATE INDEX idx_attempt_reporting_filters
    ON test_attempts (status, submitted_at, passed, test_id, employee_id);

CREATE INDEX idx_assignment_reporting_filters
    ON test_assignments (status, available_until, test_id, employee_id);

CREATE INDEX idx_employee_reporting_unit
    ON employees (archived_at, department_id, section_id, is_active);

CREATE INDEX idx_progress_dashboard_status
    ON assessment_progress (overall_status, completed_at, employee_id);

CREATE INDEX idx_security_dashboard_created
    ON security_events (created_at, attempt_id, event_type);
