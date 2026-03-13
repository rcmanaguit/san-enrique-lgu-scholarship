-- =========================================================
-- Normalize Flood Seed Data To Under Review
-- =========================================================
-- Purpose:
-- 1) Keeps the existing APP-FLOOD seeded records.
-- 2) Forces all seeded flood applications into under_review.
-- 3) Clears interview / SOA / payout progression data.
-- 4) Removes seeded SOA documents and disbursements tied to later stages.
-- 5) Safe to rerun because it performs only UPDATE/DELETE operations.
--
-- Usage:
-- - Run this after database/seed.sql if you want the same flood dataset
--   but with every application returned to the review queue.

USE lgu_scholarship;

UPDATE applications
SET
    status = 'under_review',
    review_notes = NULL,
    interview_date = NULL,
    interview_location = NULL,
    soa_submission_deadline = NULL,
    soa_submitted_at = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE application_no LIKE 'APP-FLOOD-%';

UPDATE application_documents
SET
    verification_status = 'pending',
    remarks = 'Seeded document record.',
    uploaded_at = CURRENT_TIMESTAMP
WHERE application_id IN (
    SELECT id
    FROM applications
    WHERE application_no LIKE 'APP-FLOOD-%'
)
AND requirement_name IN (
    'Report Card / Previous Semester (Photocopy)',
    'Certificate of Enrollment',
    'Barangay Residency'
);

DELETE FROM application_documents
WHERE application_id IN (
    SELECT id
    FROM applications
    WHERE application_no LIKE 'APP-FLOOD-%'
)
AND requirement_name = 'Original Student Copy / Statement of Account (SOA)';

DELETE FROM disbursements
WHERE application_id IN (
    SELECT id
    FROM applications
    WHERE application_no LIKE 'APP-FLOOD-%'
);
