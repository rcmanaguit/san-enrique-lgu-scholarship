-- =========================================================
-- Flood Seed Data (Applicants + Applications + Documents)
-- =========================================================
-- Notes:
-- 1) This script is re-runnable (uses NOT EXISTS / ON DUPLICATE checks).
-- 2) It seeds metadata paths for photos/documents:
--    - uploads/photos/app_flood_XXXX.jpg
--    - uploads/documents/app_flood_XXXX_*.pdf
-- 3) For full preview testing, make sure those files exist in uploads/.

USE lgu_scholarship;

SET @seed_password_hash = '$2y$10$OJ2MKaZbtsNsLW1pWY.oO.G7BqoSPLyvnkNeQbl7xpMXlFC/cFAZm';
SET @flood_count = 10;

SET @period_id = (
    SELECT id
    FROM application_periods
    WHERE period_status = 'open'
    ORDER BY start_date DESC, id DESC
    LIMIT 1
);

SET @period_id = COALESCE(
    @period_id,
    (SELECT id FROM application_periods ORDER BY id DESC LIMIT 1)
);

SET @school_year = COALESCE(
    (SELECT academic_year FROM application_periods WHERE id = @period_id LIMIT 1),
    CONCAT(YEAR(CURDATE()), '-', YEAR(CURDATE()) + 1)
);
SET @semester = COALESCE(
    (SELECT semester FROM application_periods WHERE id = @period_id LIMIT 1),
    'First Semester'
);

DROP TEMPORARY TABLE IF EXISTS seed_flood_people;
CREATE TEMPORARY TABLE seed_flood_people (
    seed_no INT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    birth_date DATE NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    school_name VARCHAR(180) NOT NULL,
    school_type ENUM('public', 'private') NOT NULL,
    course VARCHAR(150) NOT NULL,
    applicant_type ENUM('new', 'renew') NOT NULL,
    app_status VARCHAR(40) NOT NULL
);

INSERT INTO seed_flood_people (
    seed_no, first_name, middle_name, last_name, sex, birth_date, barangay, school_name, school_type, course, applicant_type, app_status
)
SELECT
    b.n AS seed_no,
    CASE b.idx MOD 24
        WHEN 0 THEN 'John Mark'
        WHEN 1 THEN 'Maria Ana'
        WHEN 2 THEN 'Kevin'
        WHEN 3 THEN 'Angela'
        WHEN 4 THEN 'Renz'
        WHEN 5 THEN 'Patricia'
        WHEN 6 THEN 'Allen'
        WHEN 7 THEN 'Shane'
        WHEN 8 THEN 'Joshua'
        WHEN 9 THEN 'Clarisse'
        WHEN 10 THEN 'Paolo'
        WHEN 11 THEN 'Alyssa'
        WHEN 12 THEN 'Mark Joseph'
        WHEN 13 THEN 'Janelle'
        WHEN 14 THEN 'Harold'
        WHEN 15 THEN 'Nicole'
        WHEN 16 THEN 'Ethan'
        WHEN 17 THEN 'Mika'
        WHEN 18 THEN 'Jerome'
        WHEN 19 THEN 'Denise'
        WHEN 20 THEN 'Nathaniel'
        WHEN 21 THEN 'Karen Joy'
        WHEN 22 THEN 'Leo'
        ELSE 'Camille'
    END AS first_name,
    CASE b.idx MOD 18
        WHEN 0 THEN 'Reyes'
        WHEN 1 THEN 'Lopez'
        WHEN 2 THEN 'Garcia'
        WHEN 3 THEN 'Torres'
        WHEN 4 THEN 'Flores'
        WHEN 5 THEN 'Cruz'
        WHEN 6 THEN NULL
        WHEN 7 THEN 'Lim'
        WHEN 8 THEN 'Tan'
        WHEN 9 THEN 'Uy'
        WHEN 10 THEN 'Diaz'
        WHEN 11 THEN 'Quinto'
        WHEN 12 THEN 'Sia'
        WHEN 13 THEN 'Bautista'
        WHEN 14 THEN 'Montes'
        WHEN 15 THEN 'Francisco'
        WHEN 16 THEN 'Rivera'
        ELSE 'Salazar'
    END AS middle_name,
    CASE b.idx MOD 29
        WHEN 0 THEN 'Villanueva'
        WHEN 1 THEN 'Santos'
        WHEN 2 THEN 'Reyes'
        WHEN 3 THEN 'Mendoza'
        WHEN 4 THEN 'Lazaro'
        WHEN 5 THEN 'Dela Cruz'
        WHEN 6 THEN 'Martinez'
        WHEN 7 THEN 'Ramos'
        WHEN 8 THEN 'Gomez'
        WHEN 9 THEN 'Fernandez'
        WHEN 10 THEN 'Castro'
        WHEN 11 THEN 'Navarro'
        WHEN 12 THEN 'Yu'
        WHEN 13 THEN 'Ortega'
        WHEN 14 THEN 'Pineda'
        WHEN 15 THEN 'Alvarez'
        WHEN 16 THEN 'Caballero'
        WHEN 17 THEN 'Galvez'
        WHEN 18 THEN 'Lorenzo'
        WHEN 19 THEN 'Padilla'
        WHEN 20 THEN 'Domingo'
        WHEN 21 THEN 'Velasco'
        WHEN 22 THEN 'Dizon'
        WHEN 23 THEN 'Agustin'
        WHEN 24 THEN 'Mercado'
        WHEN 25 THEN 'Rosales'
        WHEN 26 THEN 'Ledesma'
        WHEN 27 THEN 'Bernal'
        ELSE 'Fajardo'
    END AS last_name,
    CASE
        WHEN b.n MOD 2 = 0 THEN 'Female'
        ELSE 'Male'
    END AS sex,
    DATE_ADD('2002-01-15', INTERVAL ((b.n * 37) MOD 1825) DAY) AS birth_date,
    CASE b.idx MOD 10
        WHEN 0 THEN 'Poblacion'
        WHEN 1 THEN 'Tibsoc'
        WHEN 2 THEN 'Bagonawa'
        WHEN 3 THEN 'Nayon'
        WHEN 4 THEN 'Guintorilan'
        WHEN 5 THEN 'Batuan'
        WHEN 6 THEN 'Sibucao'
        WHEN 7 THEN 'Tabao Baybay'
        WHEN 8 THEN 'Tabao Rizal'
        ELSE 'Baliwagan'
    END AS barangay,
    CASE b.idx MOD 18
        WHEN 0 THEN 'Central Philippines State University'
        WHEN 1 THEN 'Carlos Hilado Memorial State University'
        WHEN 2 THEN 'University of St. La Salle'
        WHEN 3 THEN 'University of Negros Occidental - Recoletos'
        WHEN 4 THEN 'STI West Negros University'
        WHEN 5 THEN 'Colegio San Agustin - Bacolod'
        WHEN 6 THEN 'La Consolacion College Bacolod'
        WHEN 7 THEN 'Riverside College, Inc.'
        WHEN 8 THEN 'Bacolod City College'
        WHEN 9 THEN 'Bago City College'
        WHEN 10 THEN 'John B. Lacson Colleges Foundation - Bacolod'
        WHEN 11 THEN 'VMA Global College and Training Centers'
        WHEN 12 THEN 'AMA Computer College - Bacolod'
        WHEN 13 THEN 'Asian College of Aeronautics - Bacolod'
        WHEN 14 THEN 'La Carlota City College'
        WHEN 15 THEN 'I-TECH College'
        WHEN 16 THEN 'Technological University of the Philippines - Visayas'
        ELSE 'Philippine Normal University - Visayas'
    END AS school_name,
    CASE b.idx MOD 18
        WHEN 0 THEN 'public'
        WHEN 1 THEN 'public'
        WHEN 2 THEN 'private'
        WHEN 3 THEN 'private'
        WHEN 4 THEN 'private'
        WHEN 5 THEN 'private'
        WHEN 6 THEN 'private'
        WHEN 7 THEN 'private'
        WHEN 8 THEN 'public'
        WHEN 9 THEN 'public'
        WHEN 10 THEN 'private'
        WHEN 11 THEN 'private'
        WHEN 12 THEN 'private'
        WHEN 13 THEN 'private'
        WHEN 14 THEN 'public'
        WHEN 15 THEN 'private'
        WHEN 16 THEN 'public'
        ELSE 'public'
    END AS school_type,
    CASE b.idx MOD 21
        WHEN 0 THEN 'BS Information Technology'
        WHEN 1 THEN 'BS Computer Science'
        WHEN 2 THEN 'BS Information Systems'
        WHEN 3 THEN 'BS Accountancy'
        WHEN 4 THEN 'BS Business Administration'
        WHEN 5 THEN 'BS Hospitality Management'
        WHEN 6 THEN 'BS Tourism Management'
        WHEN 7 THEN 'BS Secondary Education'
        WHEN 8 THEN 'BS Elementary Education'
        WHEN 9 THEN 'BS Psychology'
        WHEN 10 THEN 'BS Criminology'
        WHEN 11 THEN 'BS Nursing'
        WHEN 12 THEN 'BS Midwifery'
        WHEN 13 THEN 'BS Medical Technology'
        WHEN 14 THEN 'BS Civil Engineering'
        WHEN 15 THEN 'BS Mechanical Engineering'
        WHEN 16 THEN 'BS Electrical Engineering'
        WHEN 17 THEN 'BS Industrial Engineering'
        WHEN 18 THEN 'BS Agriculture'
        WHEN 19 THEN 'BS Agribusiness'
        ELSE 'BS Fisheries'
    END AS course,
    CASE
        WHEN b.n MOD 3 = 0 THEN 'renew'
        ELSE 'new'
    END AS applicant_type,
    CASE b.idx MOD 9
        WHEN 0 THEN 'under_review'
        WHEN 1 THEN 'for_interview'
        WHEN 2 THEN 'for_soa'
        WHEN 3 THEN 'approved_for_release'
        WHEN 4 THEN 'released'
        WHEN 5 THEN 'needs_resubmission'
        WHEN 6 THEN 'under_review'
        WHEN 7 THEN 'needs_resubmission'
        ELSE 'rejected'
    END AS app_status
FROM (
    SELECT
        seq.n,
        (seq.n - 1) AS idx
    FROM (
        SELECT (o.n + (t.n * 10) + (h.n * 100) + 1) AS n
        FROM (
            SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
        ) o
        CROSS JOIN (
            SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
        ) t
        CROSS JOIN (
            SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
        ) h
    ) seq
    WHERE seq.n <= @flood_count
) b
ORDER BY b.n;

-- =========================================================
-- Cleanup Previously Seeded Flood Rows
-- =========================================================
DELETE d
FROM disbursements d
INNER JOIN applications a ON a.id = d.application_id
WHERE a.application_no LIKE 'APP-FLOOD-%';

DELETE d
FROM application_documents d
INNER JOIN applications a ON a.id = d.application_id
WHERE a.application_no LIKE 'APP-FLOOD-%';

DELETE u
FROM users u
INNER JOIN applications a ON a.user_id = u.id
WHERE a.application_no LIKE 'APP-FLOOD-%'
  AND u.role = 'applicant';

DELETE FROM applications
WHERE application_no LIKE 'APP-FLOOD-%';

DELETE u
FROM users u
INNER JOIN seed_flood_people p
    ON u.email = CONCAT(
        LOWER(REPLACE(REPLACE(p.first_name, ' ', ''), '''', '')),
        '.',
        LOWER(REPLACE(REPLACE(p.last_name, ' ', ''), '''', '')),
        '.',
        LPAD(p.seed_no, 2, '0'),
        '@example.com'
    )
WHERE u.role = 'applicant';

INSERT INTO users (
    role,
    first_name,
    middle_name,
    last_name,
    email,
    phone,
    password_hash,
    school_name,
    school_type,
    course,
    address,
    barangay,
    town,
    province,
    status
)
SELECT
    'applicant',
    p.first_name,
    p.middle_name,
    p.last_name,
    CONCAT(
        LOWER(REPLACE(REPLACE(p.first_name, ' ', ''), '''', '')),
        '.',
        LOWER(REPLACE(REPLACE(p.last_name, ' ', ''), '''', '')),
        '.',
        LPAD(p.seed_no, 2, '0'),
        '@example.com'
    ) AS email,
    CONCAT('0917', LPAD(p.seed_no, 6, '0')) AS phone,
    @seed_password_hash,
    p.school_name,
    p.school_type,
    p.course,
    CONCAT('Purok ', p.seed_no, ', Brgy. ', p.barangay),
    p.barangay,
    'San Enrique',
    'Negros Occidental',
    'active'
FROM seed_flood_people p
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    middle_name = VALUES(middle_name),
    last_name = VALUES(last_name),
    phone = VALUES(phone),
    school_name = VALUES(school_name),
    school_type = VALUES(school_type),
    course = VALUES(course),
    address = VALUES(address),
    barangay = VALUES(barangay),
    town = VALUES(town),
    province = VALUES(province),
    status = VALUES(status);

INSERT INTO applications (
    application_no,
    user_id,
    application_period_id,
    applicant_type,
    semester,
    school_year,
    school_name,
    school_type,
    course,
    last_name,
    first_name,
    middle_name,
    age,
    civil_status,
    sex,
    birth_date,
    birth_place,
    barangay,
    town,
    province,
    address,
    contact_number,
    photo_path,
    status,
    review_notes,
    interview_date,
    interview_location,
    soa_submission_deadline,
    soa_submitted_at,
    submitted_at
)
SELECT
    CONCAT('APP-FLOOD-', LPAD(p.seed_no, 4, '0')),
    u.id,
    @period_id,
    p.applicant_type,
    @semester,
    @school_year,
    p.school_name,
    p.school_type,
    p.course,
    p.last_name,
    p.first_name,
    p.middle_name,
    TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()),
    'Single',
    p.sex,
    p.birth_date,
    'Negros Occidental',
    p.barangay,
    'San Enrique',
    'Negros Occidental',
    CONCAT('Purok ', p.seed_no, ', Brgy. ', p.barangay),
    CONCAT('0917', LPAD(p.seed_no, 6, '0')),
    CONCAT('uploads/photos/app_flood_', LPAD(p.seed_no, 4, '0'), '.jpg'),
    p.app_status,
    CASE
        WHEN p.app_status = 'needs_resubmission' THEN 'Please re-upload the flagged requirement documents.'
        WHEN p.app_status = 'rejected' THEN 'Application was not approved for this period.'
        ELSE NULL
    END,
    CASE
        WHEN p.app_status IN ('for_interview', 'for_soa', 'approved_for_release', 'released')
            THEN DATE_ADD(DATE_SUB(NOW(), INTERVAL (p.seed_no MOD 21) DAY), INTERVAL ((p.seed_no MOD 5) + 1) DAY)
        ELSE NULL
    END,
    CASE
        WHEN p.app_status IN ('for_interview', 'for_soa', 'approved_for_release', 'released')
            THEN 'Mayor''s Office, San Enrique'
        ELSE NULL
    END,
    CASE
        WHEN p.app_status = 'for_soa' THEN DATE_ADD(CURDATE(), INTERVAL ((p.seed_no MOD 7) + 3) DAY)
        WHEN p.app_status IN ('approved_for_release', 'released') THEN DATE_SUB(CURDATE(), INTERVAL ((p.seed_no MOD 7) + 1) DAY)
        ELSE NULL
    END,
    CASE
        WHEN p.app_status IN ('approved_for_release', 'released')
            THEN DATE_SUB(NOW(), INTERVAL (p.seed_no MOD 6) DAY)
        ELSE NULL
    END,
    DATE_SUB(NOW(), INTERVAL (p.seed_no MOD 21) DAY)
FROM seed_flood_people p
INNER JOIN users u ON u.email = CONCAT(
    LOWER(REPLACE(REPLACE(p.first_name, ' ', ''), '''', '')),
    '.',
    LOWER(REPLACE(REPLACE(p.last_name, ' ', ''), '''', '')),
    '.',
    LPAD(p.seed_no, 2, '0'),
    '@example.com'
)
ON DUPLICATE KEY UPDATE
    applicant_type = VALUES(applicant_type),
    semester = VALUES(semester),
    school_year = VALUES(school_year),
    school_name = VALUES(school_name),
    school_type = VALUES(school_type),
    course = VALUES(course),
    last_name = VALUES(last_name),
    first_name = VALUES(first_name),
    middle_name = VALUES(middle_name),
    age = VALUES(age),
    civil_status = VALUES(civil_status),
    sex = VALUES(sex),
    birth_date = VALUES(birth_date),
    birth_place = VALUES(birth_place),
    barangay = VALUES(barangay),
    town = VALUES(town),
    province = VALUES(province),
    address = VALUES(address),
    contact_number = VALUES(contact_number),
    photo_path = VALUES(photo_path),
    status = VALUES(status),
    review_notes = VALUES(review_notes),
    interview_date = VALUES(interview_date),
    interview_location = VALUES(interview_location),
    soa_submission_deadline = VALUES(soa_submission_deadline),
    soa_submitted_at = VALUES(soa_submitted_at),
    submitted_at = VALUES(submitted_at),
    updated_at = CURRENT_TIMESTAMP;

-- =========================================================
-- Seed Uploaded Documents (metadata paths)
-- =========================================================
-- Core required documents
INSERT INTO application_documents (
    application_id,
    requirement_template_id,
    requirement_name,
    document_type,
    file_path,
    file_ext,
    verification_status,
    remarks
)
SELECT
    a.id,
    (
        SELECT rt.id
        FROM requirement_templates rt
        WHERE rt.requirement_name = 'Report Card / Previous Semester (Photocopy)'
        ORDER BY rt.id ASC
        LIMIT 1
    ),
    'Report Card / Previous Semester (Photocopy)',
    'requirement',
    CONCAT('uploads/documents/', LOWER(REPLACE(a.application_no, '-', '_')), '_report_card.pdf'),
    'pdf',
    CASE
        WHEN a.status IN ('under_review', 'needs_resubmission') THEN 'pending'
        WHEN a.status = 'rejected' THEN 'rejected'
        ELSE 'verified'
    END,
    CASE
        WHEN a.status = 'needs_resubmission' THEN 'Please re-upload a clearer copy.'
        WHEN a.status = 'rejected' THEN 'Application closed.'
        ELSE 'Seeded document record.'
    END
FROM applications a
WHERE a.application_no LIKE 'APP-FLOOD-%'
  AND NOT EXISTS (
      SELECT 1
      FROM application_documents d
      WHERE d.application_id = a.id
        AND d.requirement_name = 'Report Card / Previous Semester (Photocopy)'
  );

INSERT INTO application_documents (
    application_id,
    requirement_template_id,
    requirement_name,
    document_type,
    file_path,
    file_ext,
    verification_status,
    remarks
)
SELECT
    a.id,
    (
        SELECT rt.id
        FROM requirement_templates rt
        WHERE rt.requirement_name = 'Certificate of Enrollment'
        ORDER BY rt.id ASC
        LIMIT 1
    ),
    'Certificate of Enrollment',
    'requirement',
    CONCAT('uploads/documents/', LOWER(REPLACE(a.application_no, '-', '_')), '_enrollment.pdf'),
    'pdf',
    CASE
        WHEN a.status IN ('under_review', 'needs_resubmission') THEN 'pending'
        WHEN a.status = 'rejected' THEN 'rejected'
        ELSE 'verified'
    END,
    'Seeded document record.'
FROM applications a
WHERE a.application_no LIKE 'APP-FLOOD-%'
  AND NOT EXISTS (
      SELECT 1
      FROM application_documents d
      WHERE d.application_id = a.id
        AND d.requirement_name = 'Certificate of Enrollment'
  );

INSERT INTO application_documents (
    application_id,
    requirement_template_id,
    requirement_name,
    document_type,
    file_path,
    file_ext,
    verification_status,
    remarks
)
SELECT
    a.id,
    (
        SELECT rt.id
        FROM requirement_templates rt
        WHERE rt.requirement_name = 'Barangay Residency'
        ORDER BY rt.id ASC
        LIMIT 1
    ),
    'Barangay Residency',
    'requirement',
    CONCAT('uploads/documents/', LOWER(REPLACE(a.application_no, '-', '_')), '_barangay_residency.pdf'),
    'pdf',
    CASE
        WHEN a.status IN ('under_review', 'needs_resubmission') THEN 'pending'
        WHEN a.status = 'rejected' THEN 'rejected'
        ELSE 'verified'
    END,
    'Seeded document record.'
FROM applications a
WHERE a.application_no LIKE 'APP-FLOOD-%'
  AND NOT EXISTS (
      SELECT 1
      FROM application_documents d
      WHERE d.application_id = a.id
        AND d.requirement_name = 'Barangay Residency'
  );

-- SOA documents for later workflow stages
INSERT INTO application_documents (
    application_id,
    requirement_template_id,
    requirement_name,
    document_type,
    file_path,
    file_ext,
    verification_status,
    remarks
)
SELECT
    a.id,
    (
        SELECT rt.id
        FROM requirement_templates rt
        WHERE rt.requirement_name = 'Original Student Copy / Statement of Account (SOA)'
        ORDER BY rt.id ASC
        LIMIT 1
    ),
    'Original Student Copy / Statement of Account (SOA)',
    'requirement',
    CONCAT('uploads/documents/', LOWER(REPLACE(a.application_no, '-', '_')), '_soa.pdf'),
    'pdf',
    CASE
        WHEN a.status IN ('for_soa') THEN 'pending'
        WHEN a.status IN ('approved_for_release', 'released') THEN 'verified'
        ELSE 'pending'
    END,
    'Seeded SOA document record.'
FROM applications a
WHERE a.application_no LIKE 'APP-FLOOD-%'
  AND a.status IN ('for_soa', 'approved_for_release', 'released')
  AND NOT EXISTS (
      SELECT 1
      FROM application_documents d
      WHERE d.application_id = a.id
        AND d.requirement_name = 'Original Student Copy / Statement of Account (SOA)'
  );

-- =========================================================
-- Optional payout records for disbursed applications
-- =========================================================
INSERT INTO disbursements (
    application_id,
    amount,
    disbursement_date,
    disbursement_time,
    reference_no,
    payout_location,
    status,
    remarks
)
SELECT
    a.id,
    5000.00,
    DATE_SUB(CURDATE(), INTERVAL 3 DAY),
    '09:00:00',
    CONCAT('FLOOD-DISB-', LPAD(a.id, 6, '0')),
    'Mayor''s Office, San Enrique',
    'released',
    'Seeded disbursement record.'
FROM applications a
WHERE a.application_no LIKE 'APP-FLOOD-%'
  AND a.status = 'released'
  AND NOT EXISTS (
      SELECT 1
      FROM disbursements d
      WHERE d.application_id = a.id
        AND d.reference_no = CONCAT('FLOOD-DISB-', LPAD(a.id, 6, '0'))
  );

DROP TEMPORARY TABLE IF EXISTS seed_flood_people;
