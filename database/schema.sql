CREATE DATABASE IF NOT EXISTS lgu_scholarship CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lgu_scholarship;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','staff','applicant') NOT NULL DEFAULT 'applicant',
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(25) NULL,
    password_hash VARCHAR(255) NOT NULL,
    school_name VARCHAR(180) NULL,
    school_type ENUM('public','private') NULL,
    course VARCHAR(150) NULL,
    address TEXT NULL,
    barangay VARCHAR(100) NULL,
    town VARCHAR(120) NOT NULL DEFAULT 'San Enrique',
    province VARCHAR(120) NOT NULL DEFAULT 'Negros Occidental',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS barangays (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    town VARCHAR(120) NOT NULL DEFAULT 'San Enrique',
    province VARCHAR(120) NOT NULL DEFAULT 'Negros Occidental',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_barangays_name (name)
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    content TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_user
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS requirement_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requirement_name VARCHAR(180) NOT NULL,
    description VARCHAR(255) NULL,
    applicant_type ENUM('new','renew') NULL,
    school_type ENUM('public','private') NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS application_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year VARCHAR(9) NOT NULL,
    semester ENUM('First Semester','Second Semester') NOT NULL,
    period_name VARCHAR(150) NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application_periods_semester_academic_year (academic_year, semester),
    CONSTRAINT fk_application_periods_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_no VARCHAR(40) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    application_period_id INT UNSIGNED NULL,
    qr_token VARCHAR(80) NOT NULL UNIQUE,
    applicant_type ENUM('new','renew') NOT NULL DEFAULT 'new',
    semester ENUM('First Semester','Second Semester') NOT NULL,
    school_year VARCHAR(30) NOT NULL,
    school_name VARCHAR(180) NULL,
    school_type ENUM('public','private') NULL,
    course VARCHAR(150) NULL,
    last_name VARCHAR(100) NULL,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    age TINYINT UNSIGNED NULL,
    civil_status ENUM('Single','Married','Widowed','Separated') NULL,
    sex ENUM('Male','Female') NULL,
    birth_date DATE NULL,
    birth_place VARCHAR(180) NULL,
    barangay VARCHAR(100) NULL,
    town VARCHAR(120) NOT NULL DEFAULT 'San Enrique',
    province VARCHAR(120) NOT NULL DEFAULT 'Negros Occidental',
    address TEXT NULL,
    contact_number VARCHAR(25) NULL,
    mother_name VARCHAR(150) NULL,
    mother_age TINYINT UNSIGNED NULL,
    mother_occupation VARCHAR(120) NULL,
    mother_monthly_income DECIMAL(12,2) NULL,
    father_name VARCHAR(150) NULL,
    father_age TINYINT UNSIGNED NULL,
    father_occupation VARCHAR(120) NULL,
    father_monthly_income DECIMAL(12,2) NULL,
    siblings_json LONGTEXT NULL,
    educational_background_json LONGTEXT NULL,
    grants_availed_json LONGTEXT NULL,
    photo_path VARCHAR(255) NULL,
    status ENUM(
        'draft',
        'submitted',
        'for_review',
        'for_resubmission',
        'for_interview',
        'approved',
        'for_soa_submission',
        'soa_submitted',
        'disbursed',
        'rejected',
        'waitlisted'
    ) NOT NULL DEFAULT 'submitted',
    review_notes TEXT NULL,
    interview_date DATETIME NULL,
    interview_location VARCHAR(180) NULL,
    soa_submission_deadline DATE NULL,
    soa_submitted_at DATETIME NULL,
    submitted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_applications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_applications_period
        FOREIGN KEY (application_period_id) REFERENCES application_periods(id)
        ON DELETE SET NULL,
    UNIQUE KEY uq_applications_user_period (user_id, application_period_id)
);

CREATE TABLE IF NOT EXISTS application_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    requirement_template_id INT UNSIGNED NULL,
    requirement_name VARCHAR(180) NOT NULL,
    document_type VARCHAR(80) NOT NULL DEFAULT 'requirement',
    file_path VARCHAR(255) NOT NULL,
    file_ext VARCHAR(12) NULL,
    verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    remarks VARCHAR(255) NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documents_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_documents_requirement_template
        FOREIGN KEY (requirement_template_id) REFERENCES requirement_templates(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS disbursements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    disbursement_date DATE NOT NULL,
    disbursement_time TIME NULL,
    reference_no VARCHAR(80) NOT NULL,
    payout_location VARCHAR(180) NULL,
    status ENUM('scheduled','released','cancelled') NOT NULL DEFAULT 'scheduled',
    qr_token VARCHAR(100) NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_disbursements_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS qr_scan_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scanned_by_user_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NULL,
    applicant_user_id INT UNSIGNED NULL,
    purpose VARCHAR(60) NOT NULL DEFAULT 'general_verification',
    scan_status ENUM('matched','not_found','invalid') NOT NULL DEFAULT 'matched',
    scanned_qr_token VARCHAR(100) NULL,
    scanned_application_no VARCHAR(60) NULL,
    raw_content TEXT NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_qr_scan_logs_scanned_by
        FOREIGN KEY (scanned_by_user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_qr_scan_logs_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_qr_scan_logs_applicant
        FOREIGN KEY (applicant_user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sms_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    phone VARCHAR(25) NOT NULL,
    message TEXT NOT NULL,
    sms_type ENUM('single','bulk','otp','status_update') NOT NULL DEFAULT 'single',
    provider_response TEXT NULL,
    delivery_status ENUM('success','failed','queued') NOT NULL DEFAULT 'queued',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sms_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(120) NOT NULL,
    template_body VARCHAR(500) NOT NULL,
    template_category VARCHAR(60) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sms_templates_name (template_name),
    CONSTRAINT fk_sms_templates_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_sms_templates_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    user_role VARCHAR(20) NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id VARCHAR(80) NULL,
    description VARCHAR(255) NULL,
    metadata_json LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(40) NOT NULL DEFAULT 'system',
    related_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notifications_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS otp_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_otp_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS application_wizard_drafts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    wizard_json LONGTEXT NOT NULL,
    current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application_wizard_drafts_user (user_id),
    CONSTRAINT fk_wizard_drafts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_applications_school_year ON applications(school_year);
CREATE INDEX idx_applications_barangay ON applications(barangay);
CREATE INDEX idx_documents_application_id ON application_documents(application_id);
CREATE INDEX idx_application_periods_open ON application_periods(is_open, start_date, end_date);
CREATE INDEX idx_qr_scan_logs_created_at ON qr_scan_logs(created_at);
CREATE INDEX idx_qr_scan_logs_application_id ON qr_scan_logs(application_id);
CREATE INDEX idx_qr_scan_logs_purpose ON qr_scan_logs(purpose);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_notifications_user_read_created ON notifications(user_id, is_read, created_at);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);
CREATE INDEX idx_sms_templates_active ON sms_templates(is_active, template_category);

-- One-time status normalization for legacy records:
-- Submitted applications should now be treated as For Review in the workflow queue.
UPDATE applications
SET status = 'for_review'
WHERE status = 'submitted';

-- Ensure `disbursed` status exists for existing databases.
SET @has_disbursed_status := (
    SELECT CASE
        WHEN COLUMN_TYPE LIKE '%''disbursed''%' THEN 1
        ELSE 0
    END
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'applications'
      AND COLUMN_NAME = 'status'
    LIMIT 1
);
SET @alter_applications_status_sql := IF(
    COALESCE(@has_disbursed_status, 0) = 0,
    "ALTER TABLE applications MODIFY COLUMN status ENUM('draft','submitted','for_review','for_resubmission','for_interview','approved','for_soa_submission','soa_submitted','disbursed','rejected','waitlisted') NOT NULL DEFAULT 'submitted'",
    'SELECT 1'
);
PREPARE stmt_alter_applications_status FROM @alter_applications_status_sql;
EXECUTE stmt_alter_applications_status;
DEALLOCATE PREPARE stmt_alter_applications_status;

INSERT INTO users (
    role,
    first_name,
    last_name,
    email,
    phone,
    password_hash,
    status
)
VALUES (
    'admin',
    'LGU',
    'Administrator',
    'admin@sanenrique.gov.ph',
    '09123456789',
    '$2y$10$ANkIZQDZUi87YobLpPdhxebKPSvb29jHDWarOyYWMHjqhcCbNFOme',
    'active'
)
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO application_periods (
    academic_year,
    semester,
    period_name,
    start_date,
    end_date,
    is_open,
    notes,
    created_by
)
SELECT
    CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1),
    'First Semester',
    CONCAT('First Semester ', YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1),
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 6 MONTH),
    1,
    'Default open period created during setup.',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM application_periods
);

INSERT INTO requirement_templates (
    requirement_name,
    description,
    applicant_type,
    school_type,
    is_required,
    is_active,
    sort_order
) VALUES
('Report Card / Previous Semester (Photocopy)', 'Latest available academic performance record', NULL, NULL, 1, 1, 10),
('1 pc 2x2 Picture', 'Recent 2x2 ID photo', NULL, NULL, 1, 1, 20),
('Barangay Residency', 'Proof of residency in San Enrique', NULL, NULL, 1, 1, 30),
('Original Student Copy / Statement of Account (SOA)', 'School-issued statement of account', NULL, NULL, 1, 1, 40),
('Certificate of Enrollment', 'Current semester enrollment certificate', NULL, NULL, 1, 1, 50),
('Certificate of Good Moral', 'Issued by the school', 'new', NULL, 0, 1, 60);

INSERT INTO sms_templates (
    template_name,
    template_body,
    template_category,
    is_active,
    created_by,
    updated_by
) VALUES
(
    'Application Period Open',
    'San Enrique LGU Scholarship: Applications are now open for [Semester] [School Year]. Please submit your requirements on or before [Deadline].',
    'Application',
    1,
    1,
    1
),
(
    'Requirements Reminder',
    'San Enrique LGU Scholarship Reminder: Please submit your complete requirements at the Mayor''s Office on or before [Deadline].',
    'Requirements',
    1,
    1,
    1
),
(
    'Interview Notice',
    'San Enrique LGU Scholarship Notice: Your interview is scheduled on [Date] at [Time], [Location]. Please arrive early and bring your valid ID.',
    'Interview',
    1,
    1,
    1
),
(
    'SOA / Student Copy Reminder',
    'San Enrique LGU Scholarship Reminder: Please submit your SOA/Student Copy at the Mayor''s Office on or before [Deadline].',
    'SOA',
    1,
    1,
    1
),
(
    'Payout Schedule Advisory',
    'San Enrique LGU Scholarship Advisory: Your payout schedule is on [Date] at [Time], [Location]. Please bring a valid ID and keep your QR code ready.',
    'Payout',
    1,
    1,
    1
),
(
    'Office Advisory',
    'San Enrique LGU Scholarship Advisory: [Announcement]. For questions, please visit the Mayor''s Office.',
    'General',
    1,
    1,
    1
),
(
    'Application Moved to Interview Stage',
    'San Enrique LGU Scholarship: Application [Application No] passed document review and is now FOR INTERVIEW. Please wait for the official interview schedule.',
    'Interview',
    1,
    1,
    1
),
(
    'Document Resubmission Required',
    'San Enrique LGU Scholarship: Application [Application No] requires document resubmission. Please resubmit: [Missing Documents].',
    'Requirements',
    1,
    1,
    1
)
ON DUPLICATE KEY UPDATE
    template_body = VALUES(template_body),
    template_category = VALUES(template_category),
    is_active = VALUES(is_active),
    updated_by = VALUES(updated_by);

INSERT INTO barangays (name, town, province, is_active) VALUES
('Bagonawa', 'San Enrique', 'Negros Occidental', 1),
('Baliwagan', 'San Enrique', 'Negros Occidental', 1),
('Batuan', 'San Enrique', 'Negros Occidental', 1),
('Guintorilan', 'San Enrique', 'Negros Occidental', 1),
('Nayon', 'San Enrique', 'Negros Occidental', 1),
('Poblacion', 'San Enrique', 'Negros Occidental', 1),
('Sibucao', 'San Enrique', 'Negros Occidental', 1),
('Tabao Baybay', 'San Enrique', 'Negros Occidental', 1),
('Tabao Rizal', 'San Enrique', 'Negros Occidental', 1),
('Tibsoc', 'San Enrique', 'Negros Occidental', 1)
ON DUPLICATE KEY UPDATE
    town = VALUES(town),
    province = VALUES(province),
    is_active = VALUES(is_active);
