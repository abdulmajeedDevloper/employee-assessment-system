-- Migration: 006_admin_account_management.down.sql
-- Rollback for Admin Account Management Schema Additions
-- CAUTION: Destructive operation for development/testing environments only.

ALTER TABLE admins DROP INDEX IF EXISTS idx_admins_username_unique;
ALTER TABLE admins DROP INDEX IF EXISTS idx_admins_email_unique;
ALTER TABLE admins DROP COLUMN IF EXISTS auth_version;
ALTER TABLE admins DROP COLUMN IF EXISTS is_primary;
ALTER TABLE admins DROP COLUMN IF EXISTS display_name;
