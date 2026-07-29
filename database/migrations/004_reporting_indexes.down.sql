-- Rollback Sprint 4.6A reporting indexes

DROP INDEX idx_security_dashboard_created ON security_events;
DROP INDEX idx_progress_dashboard_status ON assessment_progress;
DROP INDEX idx_employee_reporting_unit ON employees;
DROP INDEX idx_assignment_reporting_filters ON test_assignments;
DROP INDEX idx_attempt_reporting_filters ON test_attempts;
DROP INDEX idx_attempt_official_result ON test_attempts;
