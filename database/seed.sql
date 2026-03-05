-- =========================================================
-- Applicant Seed Data (Users + Applications)
-- =========================================================
USE lgu_scholarship;
-- Demo password hash (same hash used in your existing seed admin account)
-- Plain password for this hash in your system seed is typically: admin1234
SET @seed_password_hash = '$2y$10$ANkIZQDZUi87YobLpPdhxebKPSvb29jHDWarOyYWMHjqhcCbNFOme';
-- Use currently open application period if available; fallback handled below
SET @period_id = (
        SELECT id
        FROM application_periods
        WHERE is_open = 1
        ORDER BY start_date DESC,
            id DESC
        LIMIT 1
    );
SET @school_year = COALESCE(
        (
            SELECT academic_year
            FROM application_periods
            WHERE id = @period_id
            LIMIT 1
        ), '2026-2027'
    );
SET @semester = COALESCE(
        (
            SELECT semester
            FROM application_periods
            WHERE id = @period_id
            LIMIT 1
        ), 'First Semester'
    );
-- -------------------------
-- 1) Seed applicant users
-- -------------------------
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
VALUES (
        'applicant',
        'Juan',
        'Cruz',
        'Dela Cruz',
        'juan.delacruz@example.com',
        '09170000001',
        @seed_password_hash,
        'Carlos Hilado Memorial State University',
        'public',
        'BS Information Technology',
        'Purok 1, Brgy. Poblacion',
        'Poblacion',
        'San Enrique',
        'Negros Occidental',
        'active'
    ),
    (
        'applicant',
        'Maria',
        'Lopez',
        'Santos',
        'maria.santos@example.com',
        '09170000002',
        @seed_password_hash,
        'University of St. La Salle',
        'private',
        'BS Accountancy',
        'Purok 2, Brgy. Tibsoc',
        'Tibsoc',
        'San Enrique',
        'Negros Occidental',
        'active'
    ),
    (
        'applicant',
        'Kevin',
        'Reyes',
        'Garcia',
        'kevin.garcia@example.com',
        '09170000003',
        @seed_password_hash,
        'West Visayas State University',
        'public',
        'BS Education',
        'Purok 3, Brgy. Bagonawa',
        'Bagonawa',
        'San Enrique',
        'Negros Occidental',
        'active'
    ),
    (
        'applicant',
        'Angela',
        'Torres',
        'Villanueva',
        'angela.villanueva@example.com',
        '09170000004',
        @seed_password_hash,
        'STI West Negros University',
        'private',
        'BS Hospitality Management',
        'Purok 4, Brgy. Nayon',
        'Nayon',
        'San Enrique',
        'Negros Occidental',
        'active'
    ),
    (
        'applicant',
        'Renz',
        'Flores',
        'Mendoza',
        'renz.mendoza@example.com',
        '09170000005',
        @seed_password_hash,
        'Negros Occidental State University',
        'public',
        'BS Criminology',
        'Purok 5, Brgy. Guintorilan',
        'Guintorilan',
        'San Enrique',
        'Negros Occidental',
        'active'
    ) ON DUPLICATE KEY
UPDATE first_name =
VALUES(first_name),
    middle_name =
VALUES(middle_name),
    last_name =
VALUES(last_name),
    phone =
VALUES(phone),
    school_name =
VALUES(school_name),
    school_type =
VALUES(school_type),
    course =
VALUES(course),
    address =
VALUES(address),
    barangay =
VALUES(barangay),
    town =
VALUES(town),
    province =
VALUES(province),
    status =
VALUES(status);
-- -------------------------
-- 2) Seed applications
-- -------------------------
INSERT INTO applications (
        application_no,
        user_id,
        application_period_id,
        qr_token,
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
        status,
        submitted_at
    )
SELECT 'APP-2026-0001',
    u.id,
    @period_id,
    'QRAPP20260001',
    'new',
    @semester,
    @school_year,
    u.school_name,
    u.school_type,
    u.course,
    u.last_name,
    u.first_name,
    u.middle_name,
    19,
    'Single',
    'Male',
    '2006-02-14',
    'San Enrique, Negros Occidental',
    u.barangay,
    u.town,
    u.province,
    u.address,
    u.phone,
    'submitted',
    NOW()
FROM users u
WHERE u.email = 'juan.delacruz@example.com'
    AND NOT EXISTS (
        SELECT 1
        FROM applications a
        WHERE a.application_no = 'APP-2026-0001'
    );
INSERT INTO applications (
        application_no,
        user_id,
        application_period_id,
        qr_token,
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
        status,
        submitted_at
    )
SELECT 'APP-2026-0002',
    u.id,
    @period_id,
    'QRAPP20260002',
    'renew',
    @semester,
    @school_year,
    u.school_name,
    u.school_type,
    u.course,
    u.last_name,
    u.first_name,
    u.middle_name,
    20,
    'Single',
    'Female',
    '2005-06-22',
    'Bacolod City',
    u.barangay,
    u.town,
    u.province,
    u.address,
    u.phone,
    'for_review',
    NOW()
FROM users u
WHERE u.email = 'maria.santos@example.com'
    AND NOT EXISTS (
        SELECT 1
        FROM applications a
        WHERE a.application_no = 'APP-2026-0002'
    );
INSERT INTO applications (
        application_no,
        user_id,
        application_period_id,
        qr_token,
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
        status,
        submitted_at
    )
SELECT 'APP-2026-0003',
    u.id,
    @period_id,
    'QRAPP20260003',
    'new',
    @semester,
    @school_year,
    u.school_name,
    u.school_type,
    u.course,
    u.last_name,
    u.first_name,
    u.middle_name,
    21,
    'Single',
    'Male',
    '2004-09-10',
    'San Enrique, Negros Occidental',
    u.barangay,
    u.town,
    u.province,
    u.address,
    u.phone,
    'for_interview',
    NOW()
FROM users u
WHERE u.email = 'kevin.garcia@example.com'
    AND NOT EXISTS (
        SELECT 1
        FROM applications a
        WHERE a.application_no = 'APP-2026-0003'
    );
INSERT INTO applications (
        application_no,
        user_id,
        application_period_id,
        qr_token,
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
        status,
        submitted_at
    )
SELECT 'APP-2026-0004',
    u.id,
    @period_id,
    'QRAPP20260004',
    'renew',
    @semester,
    @school_year,
    u.school_name,
    u.school_type,
    u.course,
    u.last_name,
    u.first_name,
    u.middle_name,
    22,
    'Single',
    'Female',
    '2003-12-03',
    'Bacolod City',
    u.barangay,
    u.town,
    u.province,
    u.address,
    u.phone,
    'approved',
    NOW()
FROM users u
WHERE u.email = 'angela.villanueva@example.com'
    AND NOT EXISTS (
        SELECT 1
        FROM applications a
        WHERE a.application_no = 'APP-2026-0004'
    );
INSERT INTO applications (
        application_no,
        user_id,
        application_period_id,
        qr_token,
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
        status,
        submitted_at
    )
SELECT 'APP-2026-0005',
    u.id,
    @period_id,
    'QRAPP20260005',
    'new',
    @semester,
    @school_year,
    u.school_name,
    u.school_type,
    u.course,
    u.last_name,
    u.first_name,
    u.middle_name,
    20,
    'Single',
    'Male',
    '2005-04-18',
    'San Enrique, Negros Occidental',
    u.barangay,
    u.town,
    u.province,
    u.address,
    u.phone,
    'rejected',
    NOW()
FROM users u
WHERE u.email = 'renz.mendoza@example.com'
    AND NOT EXISTS (
        SELECT 1
        FROM applications a
        WHERE a.application_no = 'APP-2026-0005'
    );