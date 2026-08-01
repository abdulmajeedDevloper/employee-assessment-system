-- Migration: 001_initial_schema.up.sql
-- Employee Assessment and Testing System Complete Initial Schema (MySQL 8.0.16+)

CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_file VARCHAR(255) NOT NULL UNIQUE,
    checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch INT NOT NULL,
    executed_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'supervisor', 'report_viewer') NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    auth_version INT NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    national_id_hash BINARY(32) NOT NULL UNIQUE,
    national_id_ciphertext TEXT NOT NULL,
    national_id_iv VARBINARY(12) NOT NULL,
    national_id_tag VARBINARY(16) NOT NULL,
    key_version INT NOT NULL DEFAULT 1,
    national_id_last4 VARCHAR(4) NOT NULL,
    employee_number VARCHAR(64) NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(32) NOT NULL,
    department_id INT NOT NULL,
    section_id INT NOT NULL,
    job_title_id INT NOT NULL,
    preferred_language ENUM('ar', 'en') NOT NULL DEFAULT 'ar',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    profile_completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    archived_at DATETIME NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (job_title_id) REFERENCES job_titles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    KEY idx_emp_dept_sec (department_id, section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    description_ar TEXT NULL,
    description_en TEXT NULL,
    instructions_ar TEXT NULL,
    instructions_en TEXT NULL,
    duration_minutes INT NOT NULL,
    question_count INT NOT NULL,
    passing_percentage DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    display_order INT NOT NULL DEFAULT 1,
    randomize_questions TINYINT(1) NOT NULL DEFAULT 1,
    randomize_options TINYINT(1) NOT NULL DEFAULT 1,
    allow_back_navigation TINYINT(1) NOT NULL DEFAULT 1,
    show_result_to_employee TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    available_from DATETIME NULL,
    available_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT chk_test_duration CHECK (duration_minutes > 0),
    CONSTRAINT chk_test_question_count CHECK (question_count > 0),
    CONSTRAINT chk_test_passing CHECK (passing_percentage BETWEEN 0.00 AND 100.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_cat_id_test (id, test_id),
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    category_id INT NOT NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'single_choice',
    text_ar TEXT NOT NULL,
    text_en TEXT NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    score DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    archived_at DATETIME NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT chk_q_score CHECK (score >= 0.00),
    CONSTRAINT fk_q_cat_test FOREIGN KEY (category_id, test_id) REFERENCES question_categories(id, test_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    text_ar TEXT NOT NULL,
    text_en TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 1,
    UNIQUE KEY uq_option_question_key (question_id, option_key),
    UNIQUE KEY uq_option_order (question_id, display_order),
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_type ENUM('QUESTION_IMAGE', 'OPTION_IMAGE') NOT NULL,
    question_id INT NULL UNIQUE,
    option_id INT NULL UNIQUE,
    public_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    storage_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(64) NOT NULL,
    file_extension VARCHAR(10) NOT NULL,
    file_size_bytes INT NOT NULL,
    width_pixels INT NULL,
    height_pixels INT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT chk_media_owner CHECK (
        (media_type = 'QUESTION_IMAGE' AND question_id IS NOT NULL AND option_id IS NULL) OR
        (media_type = 'OPTION_IMAGE' AND option_id IS NOT NULL AND question_id IS NULL)
    ),
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT,
    FOREIGN KEY (option_id) REFERENCES question_options(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pending_media_deletions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    storage_path VARCHAR(255) NOT NULL,
    queued_at DATETIME NOT NULL,
    next_attempt_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    status ENUM('PENDING', 'PROCESSED', 'FAILED') NOT NULL DEFAULT 'PENDING',
    KEY idx_media_del_claim (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    test_id INT NOT NULL,
    max_attempts INT NOT NULL DEFAULT 1,
    status ENUM('PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED', 'COMPLETED') NOT NULL DEFAULT 'PENDING',
    assigned_by INT NOT NULL,
    assigned_at DATETIME NOT NULL,
    available_from DATETIME NULL,
    available_until DATETIME NULL,
    CONSTRAINT chk_assign_max_att CHECK (max_attempts >= 1),
    UNIQUE KEY uq_assign_id_emp_test (id, employee_id, test_id),
    UNIQUE KEY uq_assign_emp_test (employee_id, test_id),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES admins(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    test_id INT NOT NULL,
    assignment_id INT NOT NULL,
    attempt_number INT NOT NULL DEFAULT 1,
    started_at DATETIME NOT NULL,
    submitted_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    duration_seconds INT NOT NULL,
    score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total_score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('IN_PROGRESS', 'COMPLETED', 'TIMED_OUT', 'CANCELLED') NOT NULL DEFAULT 'IN_PROGRESS',
    submission_reason ENUM('MANUAL_SUBMIT', 'AUTO_TIMEOUT', 'ADMIN_CANCEL') NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    focus_loss_count INT NOT NULL DEFAULT 0,
    cancelled_by INT NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,
    supervisor_notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT chk_att_score CHECK (score >= 0.00),
    CONSTRAINT chk_att_total CHECK (total_score >= 0.00),
    CONSTRAINT chk_att_pct CHECK (percentage BETWEEN 0.00 AND 100.00),
    UNIQUE KEY uq_att_id_assign (id, assignment_id),
    UNIQUE KEY uq_att_id_emp (id, employee_id),
    UNIQUE KEY uq_att_assign_num (assignment_id, attempt_number),
    CONSTRAINT fk_att_assign_identity FOREIGN KEY (assignment_id, employee_id, test_id) REFERENCES test_assignments(id, employee_id, test_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES admins(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retake_authorizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    previous_attempt_id INT NOT NULL,
    authorized_by INT NOT NULL,
    reason TEXT NOT NULL,
    authorized_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    consumed_by_attempt_id INT NULL,
    pending_retake_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (
        IF(consumed_at IS NULL, CONCAT('ASSIGN:', assignment_id), NULL)
    ) VIRTUAL UNIQUE,
    FOREIGN KEY (assignment_id) REFERENCES test_assignments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_retake_prev_attempt FOREIGN KEY (previous_attempt_id, assignment_id) REFERENCES test_attempts(id, assignment_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_retake_consumed_attempt FOREIGN KEY (consumed_by_attempt_id, assignment_id) REFERENCES test_attempts(id, assignment_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (authorized_by) REFERENCES admins(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempt_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    original_question_id INT NOT NULL,
    question_snapshot JSON NOT NULL,
    options_snapshot JSON NOT NULL,
    correct_option_key_snapshot VARCHAR(64) NOT NULL,
    score_snapshot DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    display_order INT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT chk_att_q_score CHECK (score_snapshot >= 0.00),
    UNIQUE KEY uq_att_q_orig (attempt_id, original_question_id),
    UNIQUE KEY uq_att_q_order (attempt_id, display_order),
    FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (original_question_id) REFERENCES questions(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempt_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_question_id INT NOT NULL UNIQUE,
    selected_option_key VARCHAR(64) NULL,
    answer_text TEXT NULL,
    is_correct TINYINT(1) NULL,
    awarded_score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    answered_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT chk_ans_score CHECK (awarded_score >= 0.00),
    FOREIGN KEY (attempt_question_id) REFERENCES attempt_questions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attempt_id INT NOT NULL,
    session_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    device_fingerprint_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ip_address VARCHAR(45) NOT NULL,
    started_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    status ENUM('ACTIVE', 'CLOSED', 'EXPIRED') NOT NULL DEFAULT 'ACTIVE',
    active_employee_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (
        IF(status = 'ACTIVE', CONCAT('EMP:', employee_id), NULL)
    ) VIRTUAL UNIQUE,
    CONSTRAINT fk_session_attempt_employee FOREIGN KEY (attempt_id, employee_id) REFERENCES test_attempts(id, employee_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL UNIQUE,
    overall_status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED') NOT NULL DEFAULT 'NOT_STARTED',
    current_test_id INT NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (current_test_id) REFERENCES tests(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    action_key VARCHAR(64) NOT NULL,
    is_success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_rate_identifier (identifier_hash, action_key, created_at),
    KEY idx_rate_ip (ip_address, action_key, created_at),
    KEY idx_rate_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deduplication_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    employee_id INT NOT NULL,
    attempt_id INT NULL,
    recipient VARCHAR(255) NOT NULL,
    type VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('PENDING', 'PROCESSING', 'SENT', 'FAILED') NOT NULL DEFAULT 'PENDING',
    attempts INT NOT NULL DEFAULT 0,
    locked_at DATETIME NULL,
    locked_by VARCHAR(64) NULL,
    next_attempt_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE SET NULL ON UPDATE CASCADE,
    KEY idx_mail_queue_claim (status, next_attempt_at, id),
    KEY idx_mail_queue_worker (locked_by, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(64) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type VARCHAR(32) NOT NULL DEFAULT 'text',
    setting_group VARCHAR(32) NOT NULL DEFAULT 'general',
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id INT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL,
    attempt_id INT NULL,
    identifier_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    event_type VARCHAR(64) NOT NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View: vw_official_assessment_results
CREATE OR REPLACE VIEW vw_official_assessment_results AS
SELECT
    ta.id AS assignment_id,
    ta.employee_id,
    ta.test_id,
    att.id AS attempt_id,
    att.attempt_number,
    att.score,
    att.total_score,
    att.percentage,
    att.passed,
    att.status AS attempt_status,
    att.submitted_at
FROM test_assignments ta
INNER JOIN test_attempts att ON att.assignment_id = ta.id
WHERE att.status IN ('COMPLETED', 'TIMED_OUT')
  AND att.attempt_number = (
      SELECT MAX(sub.attempt_number)
      FROM test_attempts sub
      WHERE sub.assignment_id = ta.id
        AND sub.status IN ('COMPLETED', 'TIMED_OUT')
  );
