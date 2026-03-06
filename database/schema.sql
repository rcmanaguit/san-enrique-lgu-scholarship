CREATE DATABASE IF NOT EXISTS lgu_scholarship CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lgu_scholarship;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','staff','applicant') NOT NULL DEFAULT 'applicant',
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    suffix VARCHAR(20) NULL,
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
    suffix VARCHAR(20) NULL,
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
        'under_review',
        'needs_resubmission',
        'for_interview',
        'interview_passed',
        'for_soa',
        'soa_received',
        'disbursed',
        'rejected',
        'awaiting_payout'
    ) NOT NULL DEFAULT 'under_review',
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
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_notifications_user_read_created ON notifications(user_id, is_read, created_at);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);
CREATE INDEX idx_sms_templates_active ON sms_templates(is_active, template_category);

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
    'Scholarship',
    'Administrator',
    'admin@sanenrique.gov.ph',
    '09170000000',
    '$2y$10$ANkIZQDZUi87YobLpPdhxebKPSvb29jHDWarOyYWMHjqhcCbNFOme',
    'active'
)
ON DUPLICATE KEY UPDATE email = VALUES(email);

SET @default_admin_id := (
    SELECT id
    FROM users
    WHERE email = 'admin@sanenrique.gov.ph'
    LIMIT 1
);

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
    DATE_ADD(CURDATE(), INTERVAL 14 DAY),
    1,
    'Default open period (14-day application window) created during setup. Admin may extend deadline as needed.',
    @default_admin_id
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
)
SELECT
    s.requirement_name,
    s.description,
    s.applicant_type,
    s.school_type,
    s.is_required,
    s.is_active,
    s.sort_order
FROM (
    SELECT 'Report Card / Previous Semester (Photocopy)' AS requirement_name, 'Latest available academic performance record' AS description, NULL AS applicant_type, NULL AS school_type, 1 AS is_required, 1 AS is_active, 10 AS sort_order
    UNION ALL SELECT '1 pc 2x2 Picture', 'Recent 2x2 ID photo', NULL, NULL, 0, 1, 20
    UNION ALL SELECT 'Barangay Residency', 'Proof of residency in San Enrique', NULL, NULL, 1, 1, 30
    UNION ALL SELECT 'Original Student Copy / Statement of Account (SOA)', 'School-issued statement of account', NULL, NULL, 0, 1, 40
    UNION ALL SELECT 'Certificate of Enrollment', 'Current semester enrollment certificate', NULL, NULL, 1, 1, 50
    UNION ALL SELECT 'Certificate of Good Moral', 'Issued by the school', 'new', NULL, 0, 1, 60
) AS s
WHERE NOT EXISTS (
    SELECT 1
    FROM requirement_templates r
    WHERE r.requirement_name = s.requirement_name
      AND COALESCE(r.applicant_type, '') = COALESCE(s.applicant_type, '')
      AND COALESCE(r.school_type, '') = COALESCE(s.school_type, '')
);

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
    'San Enrique LGU Scholarship: Applications are now open for [Semester] [School Year]. Kindly submit your requirements on or before [Deadline].',
    'Application',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Requirements Reminder',
    'San Enrique LGU Scholarship Reminder: Kindly submit your complete requirements at the Mayor''s Office on or before [Deadline].',
    'Requirements',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Interview Notice',
    'San Enrique LGU Scholarship Notice: Your interview is scheduled on [Date] at [Time], at [Location]. Please arrive on time and bring a valid ID.',
    'Interview',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'SOA / Student Copy Reminder',
    'San Enrique LGU Scholarship Reminder: Kindly submit your SOA/Student''s Copy at the Mayor''s Office on or before [Deadline]. If you have already submitted it, please disregard this message.',
    'SOA',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Payout Schedule Advisory',
    'San Enrique LGU Scholarship: Your application has been approved. The payout schedule is on [Date] at [Time], at [Location]. Please bring a valid ID and your QR code.',
    'Payout',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Office Advisory',
    'San Enrique LGU Scholarship Advisory: [Announcement]. For inquiries, please visit the Mayor''s Office.',
    'General',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Application Under Review',
    'San Enrique LGU Scholarship: Application [Application No] is currently under review.',
    'Application',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Documents Verified',
    'San Enrique LGU Scholarship: Application [Application No] is scheduled for interview.',
    'Interview',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Interview Passed',
    'San Enrique LGU Scholarship: Application [Application No] has passed the interview stage.',
    'Application',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'SOA Submission Required',
    'San Enrique LGU Scholarship: Please submit the SOA for application [Application No] on or before [Deadline].',
    'SOA',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'SOA Submitted Confirmation',
    'San Enrique LGU Scholarship: The SOA for application [Application No] has been received.',
    'SOA',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Awaiting Approval',
    'San Enrique LGU Scholarship: Application [Application No] is awaiting final approval.',
    'Application',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Payout Released',
    'San Enrique LGU Scholarship: Payout has been released for application [Application No].',
    'Payout',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Application Not Approved',
    'San Enrique LGU Scholarship: Application [Application No] was not approved.',
    'Application',
    1,
    @default_admin_id,
    @default_admin_id
),
(
    'Document Resubmission Required',
    'San Enrique LGU Scholarship: Application [Application No] requires resubmission of the following: [Missing Documents].',
    'Requirements',
    1,
    @default_admin_id,
    @default_admin_id
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
