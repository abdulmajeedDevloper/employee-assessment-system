-- Migration: 001_initial_schema.down.sql
-- Reverse Initial Schema Migration

DROP VIEW IF EXISTS vw_official_assessment_results;

DROP TABLE IF EXISTS security_events;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS email_notifications;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS assessment_progress;
DROP TABLE IF EXISTS test_sessions;
DROP TABLE IF EXISTS attempt_answers;
DROP TABLE IF EXISTS attempt_questions;
DROP TABLE IF EXISTS retake_authorizations;
DROP TABLE IF EXISTS test_attempts;
DROP TABLE IF EXISTS test_assignments;
DROP TABLE IF EXISTS pending_media_deletions;
DROP TABLE IF EXISTS question_media;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS question_categories;
DROP TABLE IF EXISTS tests;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS job_titles;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS admins;
