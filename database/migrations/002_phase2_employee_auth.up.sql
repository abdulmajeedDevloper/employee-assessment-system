-- Migration: 002_phase2_employee_auth.up.sql
-- Employee Assessment System Phase 2 Staged Migration: Audit Logs Enhancement & Actor Consistency

-- Step 1: Drop legacy foreign key on admin_id
ALTER TABLE audit_logs DROP FOREIGN KEY audit_logs_ibfk_1;

-- Step 2: Add actor_type (nullable) and employee_id (nullable)
ALTER TABLE audit_logs
    ADD COLUMN actor_type ENUM('ADMIN', 'EMPLOYEE', 'SYSTEM') NULL AFTER id,
    ADD COLUMN employee_id INT NULL AFTER admin_id;

-- Step 3: Safe Data Backfill for legacy audit rows
UPDATE audit_logs
SET actor_type = IF(admin_id IS NOT NULL, 'ADMIN', 'SYSTEM')
WHERE actor_type IS NULL;

-- Step 4: Enforce NOT NULL on actor_type after backfill validation
ALTER TABLE audit_logs
    MODIFY COLUMN actor_type ENUM('ADMIN', 'EMPLOYEE', 'SYSTEM') NOT NULL;

-- Step 5: Add strict foreign key constraints (ON DELETE RESTRICT ON UPDATE RESTRICT)
ALTER TABLE audit_logs
    ADD CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_audit_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Step 6: Add strict actor consistency CHECK constraint
ALTER TABLE audit_logs
    ADD CONSTRAINT chk_audit_actor CHECK (
        (actor_type = 'ADMIN' AND admin_id IS NOT NULL AND employee_id IS NULL) OR
        (actor_type = 'EMPLOYEE' AND employee_id IS NOT NULL AND admin_id IS NULL) OR
        (actor_type = 'SYSTEM' AND admin_id IS NULL AND employee_id IS NULL)
    );
