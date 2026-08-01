-- Migration: 006_admin_account_management.up.sql
-- Secure Admin Account Management Schema Additions & Idempotent Primary Migration

-- Step 1: Add missing columns if they do not exist
ALTER TABLE admins ADD COLUMN IF NOT EXISTS display_name VARCHAR(255) NULL AFTER name;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS auth_version INT NOT NULL DEFAULT 1 AFTER is_primary;

-- Step 2: Backfill display_name from name where display_name is NULL or empty
UPDATE admins SET display_name = name WHERE display_name IS NULL OR TRIM(display_name) = '';

-- Step 3: Modify role column ENUM to support 'admin' alongside 'super_admin', 'supervisor', 'report_viewer'
ALTER TABLE admins MODIFY COLUMN role ENUM('super_admin', 'admin', 'supervisor', 'report_viewer') NOT NULL DEFAULT 'admin';

-- Step 4: Idempotent Primary Admin Assignment
-- Only assign primary when no account has is_primary = 1
UPDATE admins
SET is_primary = 1, role = 'super_admin'
WHERE id = (
    SELECT id FROM (
        SELECT id FROM admins
        WHERE (SELECT COUNT(*) FROM admins WHERE is_primary = 1) = 0
          AND is_active = 1
        ORDER BY (CASE WHEN role = 'super_admin' THEN 0 ELSE 1 END), created_at ASC, id ASC
        LIMIT 1
    ) AS selected_primary
);

-- Step 5: Ensure Unique Indexes on username and email (utf8mb4_unicode_ci handles case-insensitivity)
-- Drops old index if present and re-creates to guarantee uniqueness
ALTER TABLE admins DROP INDEX IF EXISTS idx_admins_username_unique;
ALTER TABLE admins DROP INDEX IF EXISTS idx_admins_email_unique;
ALTER TABLE admins ADD UNIQUE INDEX idx_admins_username_unique (username);
ALTER TABLE admins ADD UNIQUE INDEX idx_admins_email_unique (email);
