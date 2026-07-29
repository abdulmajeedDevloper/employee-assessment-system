-- Migration: 002_phase2_employee_auth.down.sql
-- Employee Assessment System Phase 2 Migration Rollback
--
-- WARNING: This file must NOT be executed manually if employee audit records exist.
-- Internal SQL guard: aborts via SIGNAL SQLSTATE '45000' if EMPLOYEE audit records exist.

DROP PROCEDURE IF EXISTS sp_guard_phase2_rollback;

DELIMITER $$
CREATE PROCEDURE sp_guard_phase2_rollback()
BEGIN
    DECLARE emp_audit_count INT DEFAULT 0;
    SELECT COUNT(*) INTO emp_audit_count FROM audit_logs WHERE actor_type = 'EMPLOYEE';
    IF emp_audit_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ROLLBACK ABORTED: EMPLOYEE audit records exist. Rolling back Migration 002 would destroy audit history.';
    END IF;
END $$
DELIMITER ;

CALL sp_guard_phase2_rollback();

DROP PROCEDURE IF EXISTS sp_guard_phase2_rollback;

-- Step 1: Drop CHECK constraint
ALTER TABLE audit_logs DROP CHECK chk_audit_actor;

-- Step 2: Drop Phase 2 foreign keys
ALTER TABLE audit_logs DROP FOREIGN KEY fk_audit_employee;
ALTER TABLE audit_logs DROP FOREIGN KEY fk_audit_admin;

-- Step 3: Drop Phase 2 columns
ALTER TABLE audit_logs DROP COLUMN employee_id, DROP COLUMN actor_type;

-- Step 4: Restore Phase 1 legacy foreign key
ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_ibfk_1 FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL ON UPDATE CASCADE;
