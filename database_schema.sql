-- Create the database (if it doesn't exist) and use it
CREATE DATABASE IF NOT EXISTS lgu_san_enrique_scholarship;
USE lgu_san_enrique_scholarship;
-- --------------------------------------------------------
-- 1. USERS TABLE (Handles all logins, roles, and OTPs)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('Student', 'Staff', 'Admin') NOT NULL DEFAULT 'Student',
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    phone_number VARCHAR(15) NOT NULL UNIQUE,
    email VARCHAR(150) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    otp_code VARCHAR(6) NULL,
    otp_expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- --------------------------------------------------------
-- 2. STUDENT PROFILES TABLE (Permanent data)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    suffix VARCHAR(10) NULL,
    -- Added: For Jr., Sr., III, etc.
    date_of_birth DATE NOT NULL,
    place_of_birth VARCHAR(150) NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated') NOT NULL DEFAULT 'Single',
    -- Changed to ENUM
    address_line VARCHAR(150) NOT NULL,
    address_barangay ENUM(
        'Bagonawa',
        'Baliwagan',
        'Batuan',
        'Guintorilan',
        'Nayon',
        'Poblacion',
        'Sibucao',
        'Tabao Baybay',
        -- Corrected to Nayon
        'Tabao Rizal',
        'Tibsoc'
    ) NOT NULL,
    -- Family Background with Deceased Toggles
    mother_name VARCHAR(150) NULL,
    mother_is_deceased TINYINT(1) DEFAULT 0,
    mother_age TINYINT UNSIGNED NULL,
    mother_occupation VARCHAR(100) NULL,
    mother_monthly_income DECIMAL(10, 2) DEFAULT 0.00,
    father_name VARCHAR(150) NULL,
    father_is_deceased TINYINT(1) DEFAULT 0,
    father_age TINYINT UNSIGNED NULL,
    father_occupation VARCHAR(100) NULL,
    father_monthly_income DECIMAL(10, 2) DEFAULT 0.00,
    -- Education Data
    school_type ENUM('Public', 'Private') NOT NULL,
    school_name VARCHAR(150) NOT NULL,
    course VARCHAR(150) NOT NULL,
    year_level VARCHAR(50) NOT NULL,
    -- Hardware Captures
    e_signature_path VARCHAR(255) NULL,
    id_picture_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_profiles_name (last_name, first_name),
    INDEX idx_student_profiles_barangay (address_barangay),
    INDEX idx_student_profiles_school (school_name, school_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- --------------------------------------------------------
-- 3. BATCHES TABLE (For grouping Interviews and Payouts)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_type ENUM('Interview', 'Payout') NOT NULL,
    batch_name VARCHAR(100) NOT NULL,
    scheduled_date DATETIME NOT NULL,
    venue VARCHAR(150) NOT NULL DEFAULT 'Municipal Hall',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- --------------------------------------------------------
-- 3A. APPLICATION SETTINGS TABLE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS application_settings (
    id TINYINT PRIMARY KEY DEFAULT 1,
    school_year VARCHAR(20) NOT NULL,
    semester ENUM('1st Semester', '2nd Semester') NOT NULL,
    application_start_date DATE NOT NULL,
    application_end_date DATE NOT NULL,
    soa_deadline_mode VARCHAR(30) NOT NULL DEFAULT 'Manual',
    soa_deadline_days_after_passed SMALLINT NULL,
    soa_deadline DATE NULL,
    auto_archive_completed_records TINYINT(1) NOT NULL DEFAULT 1,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- --------------------------------------------------------
-- 4. APPLICATIONS TABLE (The per-semester tracking)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    semester ENUM('1st Semester', '2nd Semester') NOT NULL,
    application_type ENUM('New', 'Renewal') NOT NULL DEFAULT 'New',
    -- Removed Summer
    status ENUM(
        'Submitted',
        'Initial_Review',
        'Pending_Resubmission',
        'For_Interview',
        'Not_Eligible',
        'Eligible_Awaiting_SOA',
        'SOA_Under_Review',
        'SOA_Resubmission_Required',
        'Approved_Pending_Payroll',
        'Approved_Finished',
        'Forfeited'
    ) NOT NULL DEFAULT 'Submitted',
    siblings_json TEXT NULL,
    education_json TEXT NULL,
    grants_json TEXT NULL,
    privacy_consent_at DATETIME NULL,
    interview_result ENUM('Passed', 'Failed', 'Absent') NULL,
    interview_result_at DATETIME NULL,
    interview_batch_id INT NULL,
    payout_batch_id INT NULL,
    soa_deadline DATETIME NULL,
    final_grant_amount DECIMAL(10, 2) NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    archived_at DATETIME NULL,
    archived_by_user_id INT NULL,
    archive_notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_applications_period (school_year, semester),
    INDEX idx_applications_status_lifecycle (status, is_archived),
    INDEX idx_applications_type (application_type),
    INDEX idx_applications_student_created (student_id, created_at),
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (interview_batch_id) REFERENCES batches(id) ON DELETE
    SET NULL,
        FOREIGN KEY (payout_batch_id) REFERENCES batches(id) ON DELETE
    SET NULL,
        FOREIGN KEY (archived_by_user_id) REFERENCES users(id) ON DELETE
    SET NULL
);
-- --------------------------------------------------------
-- 5. DOCUMENTS TABLE (Granular file verification)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type ENUM('Grades', 'Residency', 'SOA') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
    rejection_remarks TEXT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_documents_application_type_status (application_id, document_type, status),
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);
-- --------------------------------------------------------
-- 5A. DOCUMENT VERSIONS TABLE (Replacement history)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS document_versions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version_number INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    document_status ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
    rejection_remarks TEXT NULL,
    source_action VARCHAR(50) NOT NULL,
    uploaded_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_versions_document_version (document_id, version_number),
    INDEX idx_document_versions_document_created (document_id, created_at),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);
-- --------------------------------------------------------
-- 6. NOTIFICATIONS TABLE (In-app notification center)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link_path VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- --------------------------------------------------------
-- 6A. ANNOUNCEMENTS TABLE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    target_audience ENUM('Public', 'Students', 'Both') NOT NULL DEFAULT 'Both',
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
-- --------------------------------------------------------
-- 6B. CASE NOTES TABLE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS case_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    user_id INT NULL,
    note_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
-- --------------------------------------------------------
-- 7. AUDIT LOGS TABLE (System activity and accountability)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    actor_role VARCHAR(20) NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT NULL,
    description TEXT NOT NULL,
    metadata_json TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_logs_created_at (created_at),
    INDEX idx_audit_logs_action (action),
    INDEX idx_audit_logs_entity (entity_type, entity_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- DEFAULT SEEDED ADMIN ACCOUNT
-- Mobile No.: 09123456789
-- Password : admin123
-- --------------------------------------------------------
INSERT INTO users (role, first_name, last_name, phone_number, password_hash, is_verified, is_active)
SELECT
    'Admin',
    'System',
    'Administrator',
    '09123456789',
    '$2y$10$U1fDFzi7O85ugx6414Kj3.RKn/yz3WkUlJqlfiaLD8RtelyMV9cL2',
    1,
    1
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE role = 'Admin'
      AND phone_number = '09123456789'
);

-- --------------------------------------------------------
-- DEFAULT SEEDED STAFF ACCOUNT
-- Mobile No.: 09987654321
-- Password : staff123
-- --------------------------------------------------------
INSERT INTO users (role, first_name, last_name, phone_number, password_hash, is_verified, is_active)
SELECT
    'Staff',
    'Default',
    'Staff',
    '09987654321',
    '$2y$10$UtGNxUckJ7G/cywjAQSw1Od75q/F3aylFJBelEoRepPyBbD.zNO82',
    1,
    1
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE role = 'Staff'
      AND phone_number = '09987654321'
);
