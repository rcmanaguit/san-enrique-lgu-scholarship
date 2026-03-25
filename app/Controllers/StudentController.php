<?php

namespace App\Controllers;

use App\Config\Database;
use App\Models\Application;
use App\Models\ApplicationPeriod;
use App\Models\AuditLog;
use App\Models\DocumentVersion;
use App\Models\Notification;
use App\Models\User;
use App\Support\Validation;
use App\Support\ValidationException;
use Exception;

class StudentController
{
    private const SCHOOL_NAME_OPTIONS = [
        'Central Philippines State University',
        'Carlos Hilado Memorial State University',
        'University of St. La Salle',
        'University of Negros Occidental - Recoletos',
        'STI West Negros University',
        'Colegio San Agustin - Bacolod',
        'La Consolacion College Bacolod',
        'Riverside College, Inc.',
        'Bacolod City College',
        'Bago City College',
        'John B. Lacson Colleges Foundation - Bacolod',
        'VMA Global College and Training Centers',
        'AMA Computer College - Bacolod',
        'Asian College of Aeronautics - Bacolod',
        'La Carlota City College',
        'I-TECH College',
        'Technological University of the Philippines - Visayas',
        'Philippine Normal University - Visayas',
    ];

    private const COURSE_OPTIONS = [
        'BS Information Technology',
        'BS Computer Science',
        'BS Information Systems',
        'BS Accountancy',
        'BS Business Administration',
        'BS Hospitality Management',
        'BS Tourism Management',
        'BS Secondary Education',
        'BS Elementary Education',
        'BS Psychology',
        'BS Criminology',
        'BS Nursing',
        'BS Midwifery',
        'BS Medical Technology',
        'BS Civil Engineering',
        'BS Mechanical Engineering',
        'BS Electrical Engineering',
        'BS Industrial Engineering',
        'BS Agriculture',
        'BS Agribusiness',
        'BS Fisheries',
    ];

    // Force user to be logged in as a Student
    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
            header('Location: ' . \base_url('login'));
            exit;
        }
    }

    public function dashboard()
    {
        ApplicationPeriod::syncOverdueSoaStatuses();
        require __DIR__ . '/../../views/student/dashboard.php';
    }

    public function history()
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $historyData = Application::getStudentHistory($userId);

        $totalApproved = 0;
        $totalAmount = 0.0;

        foreach ($historyData as $row) {
            if (in_array((string) ($row['status'] ?? ''), ['Approved_Pending_Payroll', 'Approved_Finished'], true)) {
                $totalApproved++;
                $totalAmount += (float) ($row['final_grant_amount'] ?? 0);
            }
        }

        require __DIR__ . '/../../views/student/history.php';
    }

    public function viewApplicationRecord($applicationId)
    {
        $applicationId = (int) $applicationId;
        if ($applicationId <= 0) {
            redirect_with_flash('student/history', 'error', 'Application record not found.');
        }

        $record = $this->findOwnedApplicationRecord((int) $_SESSION['user_id'], $applicationId);
        if ($record === null) {
            redirect_with_flash('student/history', 'error', 'You are not allowed to view that application record.');
        }

        $application = $record['application'];
        $documents = $record['documents'];
        foreach ($documents as &$document) {
            $status = (string) ($document['status'] ?? 'Pending');
            $document['status_badge'] = match ($status) {
                'Verified' => ['class' => 'app-status-badge app-status-complete', 'label' => 'Verified'],
                'Rejected' => ['class' => 'app-status-badge app-status-correction', 'label' => 'Rejected'],
                default => ['class' => 'app-status-badge app-status-review', 'label' => 'Pending'],
            };

            $path = trim((string) ($document['file_path'] ?? ''));
            $fileSizeBytes = ($path !== '' && is_file($path)) ? (int) filesize($path) : 0;
            $document['file_size_bytes'] = $fileSizeBytes;
            $document['file_size_label'] = $fileSizeBytes > 0 ? $this->formatFileSize($fileSizeBytes) : 'N/A';
        }
        unset($document);

        $siblings = $record['siblings'];
        $education = $record['education'];
        $grants = $record['grants'];
        $applicationTimeline = $this->buildStudentApplicationTimeline($application, $documents);
        $photoSrc = $this->toDataUri((string) ($application['id_picture_path'] ?? ''));
        $signatureSrc = $this->toDataUri((string) ($application['e_signature_path'] ?? ''));

        require __DIR__ . '/../../views/student/application_record.php';
    }

    public function viewApplicationDocument($applicationId, $documentId)
    {
        $applicationId = (int) $applicationId;
        $documentId = (int) $documentId;

        if ($applicationId <= 0 || $documentId <= 0) {
            http_response_code(404);
            exit('Document not found.');
        }

        $record = $this->findOwnedApplicationRecord((int) $_SESSION['user_id'], $applicationId);
        if ($record === null) {
            http_response_code(403);
            exit('You are not allowed to view this document.');
        }

        $selectedDocument = null;
        foreach ($record['documents'] as $document) {
            if ((int) ($document['id'] ?? 0) === $documentId) {
                $selectedDocument = $document;
                break;
            }
        }

        if ($selectedDocument === null) {
            http_response_code(404);
            exit('Document not found.');
        }

        $path = trim((string) ($selectedDocument['file_path'] ?? ''));
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            exit('Stored file not found.');
        }

        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }

    public function showForm()
    {
        $currentUser = User::findById((int) $_SESSION['user_id']);
        $db = Database::connect();
        $applicationSettings = ApplicationPeriod::getCurrent();
        ApplicationPeriod::syncOverdueSoaStatuses($db, $applicationSettings);
        $applicationWindowOpen = ApplicationPeriod::isAcceptingApplications($applicationSettings);
        $currentSchoolYear = (string) ($applicationSettings['school_year'] ?? '');
        $currentSemester = (string) ($applicationSettings['semester'] ?? '');
        $soaDeadlinePolicyLabel = ApplicationPeriod::soaDeadlinePolicyLabel($applicationSettings);

        if ($currentSchoolYear === '' || $currentSemester === '') {
            redirect_with_flash('student/dashboard', 'error', 'The application period has not been configured yet.');
        }

        $currentApplicationStmt = $db->prepare("
            SELECT a.*
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE p.user_id = :uid AND a.school_year = :sy AND a.semester = :sem
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT 1
        ");
        $currentApplicationStmt->execute([
            'uid' => (int) $_SESSION['user_id'],
            'sy' => $currentSchoolYear,
            'sem' => $currentSemester,
        ]);
        $currentApplication = $currentApplicationStmt->fetch();

        $isSoaUploadMode = false;
        $isDocumentResubmissionMode = false;
        $resubmissionDocuments = [];
        if ($currentApplication) {
            $currentStatus = (string) ($currentApplication['status'] ?? '');
            if (in_array($currentStatus, ['Eligible_Awaiting_SOA', 'SOA_Resubmission_Required', 'SOA_Overdue'], true)) {
                $isSoaUploadMode = true;
                $soaDeadline = ApplicationPeriod::resolveApplicationSoaDeadline($applicationSettings, (array) $currentApplication);
                $soaDeadlineOpen = ApplicationPeriod::isApplicationSoaDeadlineOpen($applicationSettings, (array) $currentApplication);

                if ($soaDeadline !== '' && !$soaDeadlineOpen) {
                    redirect_with_flash('student/dashboard', 'error', 'The SOA submission deadline has already passed. Please contact the LGU office.');
                }
            } elseif ($currentStatus === 'Pending_Resubmission') {
                $isDocumentResubmissionMode = true;
                $resubmissionDocuments = $this->getResubmissionDocuments((int) ($currentApplication['id'] ?? 0));
            } else {
                redirect_with_flash('student/dashboard', 'error', 'Your current application cannot be edited right now.');
            }
        } elseif (!$applicationWindowOpen) {
            redirect_with_flash('student/dashboard', 'error', 'Applications are currently closed.');
        }

        $historyCheck = $db->prepare("
            SELECT COUNT(*)
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE p.user_id = :uid
              AND a.status IN ('Approved_Pending_Payroll', 'Approved_Finished')
              AND NOT (a.school_year = :sy AND a.semester = :sem)
        ");
        $historyCheck->execute([
            'uid' => (int) $_SESSION['user_id'],
            'sy' => $currentSchoolYear,
            'sem' => $currentSemester,
        ]);
        $detectedApplicationType = (int) $historyCheck->fetchColumn() > 0 ? 'Renewal' : 'New';
        $prefillData = [
            'last_name' => (string) ($currentUser['last_name'] ?? ''),
            'first_name' => (string) ($currentUser['first_name'] ?? ''),
            'middle_name' => '',
            'suffix' => '',
            'email' => (string) ($currentUser['email'] ?? ''),
            'date_of_birth' => '',
            'place_of_birth' => '',
            'sex' => '',
            'civil_status' => '',
            'address_line' => '',
            'address_barangay' => '',
            'mother_name' => '',
            'mother_is_deceased' => 0,
            'mother_not_applicable' => 0,
            'mother_age' => '',
            'mother_occupation' => '',
            'mother_monthly_income' => '',
            'father_name' => '',
            'father_is_deceased' => 0,
            'father_not_applicable' => 0,
            'father_age' => '',
            'father_occupation' => '',
            'father_monthly_income' => '',
            'school_type' => '',
            'school_name' => '',
            'course' => '',
            'year_level' => '',
            'siblings' => [['name' => '', 'age' => '', 'education' => '', 'occupation' => '', 'income' => '']],
            'siblings_na' => 0,
            'education' => [
                ['level' => 'Elementary', 'school' => '', 'year' => '', 'honors' => ''],
                ['level' => 'High School', 'school' => '', 'year' => '', 'honors' => ''],
                ['level' => 'College', 'school' => '', 'course' => '', 'year' => '', 'honors' => ''],
            ],
            'grants' => [['program' => '', 'period' => '']],
            'grants_na' => 0,
        ];

        if ($detectedApplicationType === 'Renewal') {
            $previousApproved = $db->prepare("
                SELECT a.*, p.*, u.email
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE p.user_id = :uid
                  AND a.status IN ('Approved_Pending_Payroll', 'Approved_Finished')
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT 1
            ");
            $previousApproved->execute(['uid' => (int) $_SESSION['user_id']]);
            $lastApprovedApplication = $previousApproved->fetch();

            if ($lastApprovedApplication) {
                $prefillData = [
                    'last_name' => (string) ($lastApprovedApplication['last_name'] ?? ''),
                    'first_name' => (string) ($lastApprovedApplication['first_name'] ?? ''),
                    'middle_name' => (string) ($lastApprovedApplication['middle_name'] ?? ''),
                    'suffix' => (string) ($lastApprovedApplication['suffix'] ?? ''),
                    'email' => (string) ($lastApprovedApplication['email'] ?? $prefillData['email']),
                    'date_of_birth' => (string) ($lastApprovedApplication['date_of_birth'] ?? ''),
                    'place_of_birth' => (string) ($lastApprovedApplication['place_of_birth'] ?? ''),
                    'sex' => (string) ($lastApprovedApplication['sex'] ?? ''),
                    'civil_status' => (string) ($lastApprovedApplication['civil_status'] ?? ''),
                    'address_line' => (string) ($lastApprovedApplication['address_line'] ?? ''),
                    'address_barangay' => (string) ($lastApprovedApplication['address_barangay'] ?? ''),
                    'mother_name' => (string) ($lastApprovedApplication['mother_name'] ?? ''),
                    'mother_is_deceased' => (int) ($lastApprovedApplication['mother_is_deceased'] ?? 0),
                    'mother_not_applicable' => ((int) ($lastApprovedApplication['mother_is_deceased'] ?? 0) !== 1
                        && trim((string) ($lastApprovedApplication['mother_name'] ?? '')) === 'N/A') ? 1 : 0,
                    'mother_age' => (string) ($lastApprovedApplication['mother_age'] ?? ''),
                    'mother_occupation' => (string) ($lastApprovedApplication['mother_occupation'] ?? ''),
                    'mother_monthly_income' => (string) ($lastApprovedApplication['mother_monthly_income'] ?? ''),
                    'father_name' => (string) ($lastApprovedApplication['father_name'] ?? ''),
                    'father_is_deceased' => (int) ($lastApprovedApplication['father_is_deceased'] ?? 0),
                    'father_not_applicable' => ((int) ($lastApprovedApplication['father_is_deceased'] ?? 0) !== 1
                        && trim((string) ($lastApprovedApplication['father_name'] ?? '')) === 'N/A') ? 1 : 0,
                    'father_age' => (string) ($lastApprovedApplication['father_age'] ?? ''),
                    'father_occupation' => (string) ($lastApprovedApplication['father_occupation'] ?? ''),
                    'father_monthly_income' => (string) ($lastApprovedApplication['father_monthly_income'] ?? ''),
                    'school_type' => (string) ($lastApprovedApplication['school_type'] ?? ''),
                    'school_name' => (string) ($lastApprovedApplication['school_name'] ?? ''),
                    'course' => (string) ($lastApprovedApplication['course'] ?? ''),
                    'year_level' => (string) ($lastApprovedApplication['year_level'] ?? ''),
                    'siblings' => $this->decodeJsonRows((string) ($lastApprovedApplication['siblings_json'] ?? ''), $prefillData['siblings']),
                    'siblings_na' => trim((string) ($lastApprovedApplication['siblings_json'] ?? '')) === '' ? 1 : 0,
                    'education' => $this->decodeJsonRows((string) ($lastApprovedApplication['education_json'] ?? ''), $prefillData['education']),
                    'grants' => $this->decodeJsonRows((string) ($lastApprovedApplication['grants_json'] ?? ''), $prefillData['grants']),
                    'grants_na' => trim((string) ($lastApprovedApplication['grants_json'] ?? '')) === '' ? 1 : 0,
                ];
            }
        }

        $selectedSchoolName = '';
        if ($prefillData['school_name'] !== '') {
            $selectedSchoolName = in_array($prefillData['school_name'], self::SCHOOL_NAME_OPTIONS, true)
                ? $prefillData['school_name']
                : '__other__';
        }
        $otherSchoolName = $selectedSchoolName === '__other__' ? $prefillData['school_name'] : '';

        $selectedCourse = '';
        if ($prefillData['course'] !== '') {
            $selectedCourse = in_array($prefillData['course'], self::COURSE_OPTIONS, true)
                ? $prefillData['course']
                : '__other__';
        }
        $otherCourse = $selectedCourse === '__other__' ? $prefillData['course'] : '';

        if ($isSoaUploadMode) {
            require __DIR__ . '/../../views/student/soa_upload.php';
            return;
        }

        if ($isDocumentResubmissionMode) {
            require __DIR__ . '/../../views/student/document_resubmission.php';
            return;
        }

        $schoolNameOptions = self::SCHOOL_NAME_OPTIONS;
        $courseOptions = self::COURSE_OPTIONS;
        require __DIR__ . '/../../views/student/form.php';
    }

    // ---------------------------------------------------------
    // MASTER APPLICATION SUBMISSION (Transactions & File Uploads)
    // ---------------------------------------------------------
    public function submitApplication()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $db = Database::connect();
        $userId = $_SESSION['user_id'];
        $storedFiles = [];

        try {
            $applicationSettings = ApplicationPeriod::getCurrent();
            ApplicationPeriod::syncOverdueSoaStatuses($db, $applicationSettings);
            $currentSchoolYear = (string) ($applicationSettings['school_year'] ?? '');
            $currentSemester = (string) ($applicationSettings['semester'] ?? '');

            if ($currentSchoolYear === '' || $currentSemester === '') {
                throw new ValidationException('The application period has not been configured yet.');
            }

            $existingApplication = $db->prepare("
                SELECT a.*
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                WHERE p.user_id = :uid AND a.school_year = :sy AND a.semester = :sem
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT 1
            ");
            $existingApplication->execute([
                'uid' => $userId,
                'sy' => $currentSchoolYear,
                'sem' => $currentSemester,
            ]);
            $existingApplicationRow = $existingApplication->fetch();

            if ($existingApplicationRow) {
                $existingStatus = (string) ($existingApplicationRow['status'] ?? '');
                if ($existingStatus === 'Pending_Resubmission') {
                    $this->handleInitialDocumentResubmission(
                        $db,
                        (int) $userId,
                        $existingApplicationRow,
                        $currentSchoolYear,
                        $currentSemester,
                        $storedFiles
                    );
                    return;
                }

                if (!in_array($existingStatus, ['Eligible_Awaiting_SOA', 'SOA_Resubmission_Required', 'SOA_Overdue'], true)) {
                    throw new ValidationException('You already have an application for the current semester.');
                }

                $soaDeadline = ApplicationPeriod::resolveApplicationSoaDeadline($applicationSettings, (array) $existingApplicationRow);
                $soaDeadlineOpen = ApplicationPeriod::isApplicationSoaDeadlineOpen($applicationSettings, (array) $existingApplicationRow);
                if ($soaDeadline === '' || !$soaDeadlineOpen) {
                    throw new ValidationException('SOA submission is no longer open. Please contact the LGU office.');
                }

                $soaExtension = Validation::fileUpload($_FILES['soa_file'] ?? [], 'Statement of Account', $allowedUploads = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf',
                ], 2 * 1024 * 1024);

                $db->beginTransaction();

                $soaPath = $this->persistUploadedFile($_FILES['soa_file'], 'documents', 'soa_', $soaExtension, $storedFiles);
                $existingSoaStmt = $db->prepare("
                    SELECT id, file_path
                    FROM documents
                    WHERE application_id = :application_id AND document_type = 'SOA'
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $existingSoaStmt->execute(['application_id' => (int) $existingApplicationRow['id']]);
                $existingSoa = $existingSoaStmt->fetch();

                if ($existingSoa) {
                    $updateSoaStmt = $db->prepare("
                        UPDATE documents
                        SET file_path = :file_path,
                            status = 'Pending',
                            rejection_remarks = NULL,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id
                    ");
                    $updateSoaStmt->execute([
                        'file_path' => $soaPath,
                        'id' => (int) $existingSoa['id'],
                    ]);
                    DocumentVersion::createSnapshot(
                        (int) $existingSoa['id'],
                        $soaPath,
                        'Pending',
                        null,
                        (int) $userId,
                        'SOA Resubmission'
                    );

                    $oldSoaPath = (string) ($existingSoa['file_path'] ?? '');
                    if ($oldSoaPath !== '' && is_file($oldSoaPath) && $oldSoaPath !== $soaPath) {
                        @unlink($oldSoaPath);
                    }
                } else {
                    $insertSoaStmt = $db->prepare("
                        INSERT INTO documents (application_id, document_type, file_path, status)
                        VALUES (:application_id, 'SOA', :file_path, 'Pending')
                    ");
                    $insertSoaStmt->execute([
                        'application_id' => (int) $existingApplicationRow['id'],
                        'file_path' => $soaPath,
                    ]);
                    $soaDocumentId = (int) $db->lastInsertId();
                    DocumentVersion::createSnapshot(
                        $soaDocumentId,
                        $soaPath,
                        'Pending',
                        null,
                        (int) $userId,
                        'SOA Upload'
                    );
                }

                $updateApplicationStmt = $db->prepare("
                    UPDATE applications
                    SET status = 'SOA_Under_Review'
                    WHERE id = :application_id
                ");
                $updateApplicationStmt->execute(['application_id' => (int) $existingApplicationRow['id']]);

                $db->commit();

                Notification::create(
                    (int) $userId,
                    'SOA Submitted',
                    'Your Statement of Account was submitted successfully and is now waiting for review.',
                    'student/dashboard'
                );
                Notification::createForRoles(
                    ['Staff', 'Admin'],
                    'SOA Submitted',
                    'A student submitted a Statement of Account for review.',
                    'staff/dashboard'
                );

                AuditLog::recordCurrentUser(
                    'soa.submitted',
                    'application',
                    (int) $existingApplicationRow['id'],
                    'Student submitted a Statement of Account.',
                    [
                        'school_year' => $currentSchoolYear,
                        'semester' => $currentSemester,
                    ]
                );

                redirect_with_flash('student/dashboard', 'success', 'Statement of Account submitted successfully.');
            }

            if (!ApplicationPeriod::isAcceptingApplications($applicationSettings)) {
                throw new ValidationException('Applications are currently closed.');
            }

            if (!isset($_POST['privacy_consent']) || (string) $_POST['privacy_consent'] !== '1') {
                throw new ValidationException('You must agree to the Data Privacy Notice before submitting your application.');
            }

            $historyCheck = $db->prepare("
                SELECT COUNT(*)
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                WHERE p.user_id = :uid
                  AND a.status IN ('Approved_Pending_Payroll', 'Approved_Finished')
                  AND NOT (a.school_year = :sy AND a.semester = :sem)
            ");
            $historyCheck->execute([
                'uid' => $userId,
                'sy' => $currentSchoolYear,
                'sem' => $currentSemester,
            ]);
            $applicationType = (int) $historyCheck->fetchColumn() > 0 ? 'Renewal' : 'New';

            $siblingsNa = isset($_POST['siblings_na']) ? 1 : 0;
            $grantsNa = isset($_POST['grants_na']) ? 1 : 0;
            $siblings = [];
            if ($siblingsNa === 0) {
                foreach ((array) ($_POST['siblings'] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $name = Validation::optionalString($row['name'] ?? '', 150);
                    $ageRaw = trim((string) ($row['age'] ?? ''));
                    $education = Validation::optionalString($row['education'] ?? '', 100);
                    $occupation = Validation::optionalString($row['occupation'] ?? '', 100);
                    $income = Validation::nonNegativeDecimal($row['income'] ?? '', 'Sibling monthly income');

                    $hasAnyValue = $name !== null
                        || $ageRaw !== ''
                        || $education !== null
                        || $occupation !== null
                        || $income !== null;

                    if (!$hasAnyValue) {
                        continue;
                    }

                    if ($name === null || $ageRaw === '' || $education === null || $occupation === null) {
                        throw new ValidationException('Please complete all sibling details or mark the siblings section as not applicable.');
                    }

                    if (!ctype_digit($ageRaw)) {
                        throw new ValidationException('Sibling age must be a valid whole number.');
                    }

                    $age = (int) $ageRaw;
                    if ($age < 0 || $age > 120) {
                        throw new ValidationException('Sibling age must be between 0 and 120.');
                    }

                    $siblings[] = [
                        'name' => $name,
                        'age' => (string) $age,
                        'education' => $education,
                        'occupation' => $occupation,
                        'income' => $income ?? '0.00',
                    ];
                }

                if ($siblings === []) {
                    throw new ValidationException('Please add at least one sibling entry or mark the siblings section as not applicable.');
                }
            }

            $education = [];
            foreach ((array) ($_POST['education'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $level = Validation::optionalString($row['level'] ?? '', 50);
                $school = Validation::optionalString($row['school'] ?? '', 150);
                $course = Validation::optionalString($row['course'] ?? '', 150);
                $yearRaw = trim((string) ($row['year'] ?? ''));
                $honors = Validation::optionalString($row['honors'] ?? '', 150);

                if ($yearRaw !== '' && !preg_match('/^\d{1,4}$/', $yearRaw)) {
                    throw new ValidationException('Educational background year must contain digits only.');
                }

                $hasAnyValue = $level !== null
                    || $school !== null
                    || $course !== null
                    || $yearRaw !== ''
                    || $honors !== null;

                if (!$hasAnyValue) {
                    continue;
                }

                $education[] = [
                    'level' => $level ?? '',
                    'school' => $school ?? '',
                    'course' => $course ?? '',
                    'year' => $yearRaw,
                    'honors' => $honors ?? '',
                ];
            }

            $requiredEducationRows = [
                0 => 'Elementary educational background',
                1 => 'High school educational background',
                2 => 'College educational background',
            ];

            foreach ($requiredEducationRows as $index => $label) {
                $row = $education[$index] ?? null;
                if (!$row || trim((string) ($row['school'] ?? '')) === '' || trim((string) ($row['year'] ?? '')) === '') {
                    throw new ValidationException('Please complete the required ' . strtolower($label) . ' fields.');
                }
            }

            if (trim((string) ($education[2]['course'] ?? '')) === '') {
                throw new ValidationException('Please complete the course field in the educational background section.');
            }

            $grants = [];
            if ($grantsNa === 0) {
                foreach ((array) ($_POST['grants'] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $program = Validation::optionalString($row['program'] ?? '', 150);
                    $period = Validation::optionalString($row['period'] ?? '', 50);
                    $hasAnyValue = $program !== null || $period !== null;

                    if (!$hasAnyValue) {
                        continue;
                    }

                    if ($program === null || $period === null) {
                        throw new ValidationException('Please complete all grant details or mark the grants section as not applicable.');
                    }

                    $grants[] = [
                        'program' => $program,
                        'period' => $period,
                    ];
                }

                if ($grants === []) {
                    throw new ValidationException('Please add at least one scholarship grant entry or mark the grants section as not applicable.');
                }
            }

            $selectedSchoolName = trim((string) ($_POST['school_name'] ?? ''));
            $selectedCourse = trim((string) ($_POST['course'] ?? ''));

            if ($selectedSchoolName === '__other__') {
                $resolvedSchoolName = Validation::requiredString($_POST['school_name_other'] ?? '', 'Other school name', 150);
            } else {
                $resolvedSchoolName = Validation::enum($selectedSchoolName, self::SCHOOL_NAME_OPTIONS, 'School name');
            }

            if ($selectedCourse === '__other__') {
                $resolvedCourse = Validation::requiredString($_POST['course_other'] ?? '', 'Other course', 150);
            } else {
                $resolvedCourse = Validation::enum($selectedCourse, self::COURSE_OPTIONS, 'Course');
            }

            $profileData = [
                'last_name' => Validation::requiredString($_POST['last_name'] ?? '', 'Last name', 100),
                'first_name' => Validation::requiredString($_POST['first_name'] ?? '', 'First name', 100),
                'middle_name' => Validation::optionalString($_POST['middle_name'] ?? '', 100),
                'suffix' => Validation::optionalString($_POST['suffix'] ?? '', 10),
                'email' => Validation::email($_POST['email'] ?? ''),
                'date_of_birth' => Validation::date($_POST['date_of_birth'] ?? '', 'Date of birth'),
                'place_of_birth' => Validation::optionalString($_POST['place_of_birth'] ?? '', 150),
                'sex' => Validation::enum($_POST['sex'] ?? '', ['Male', 'Female'], 'Sex'),
                'civil_status' => Validation::enum($_POST['civil_status'] ?? '', ['Single', 'Married', 'Widowed', 'Separated'], 'Civil status'),
                'address_line' => Validation::requiredString($_POST['address_line'] ?? '', 'House No./Street/Purok', 150),
                'address_barangay' => Validation::enum($_POST['address_barangay'] ?? '', [
                    'Bagonawa',
                    'Baliwagan',
                    'Batuan',
                    'Guintorilan',
                    'Nayon',
                    'Poblacion',
                    'Sibucao',
                    'Tabao Baybay',
                    'Tabao Rizal',
                    'Tibsoc',
                ], 'Barangay'),
                'mother_name' => Validation::optionalString($_POST['mother_name'] ?? '', 150),
                'mother_is_deceased' => isset($_POST['mother_is_deceased']) ? 1 : 0,
                'mother_not_applicable' => isset($_POST['mother_not_applicable']) ? 1 : 0,
                'mother_age' => $this->optionalAge($_POST['mother_age'] ?? null, 'Mother age'),
                'mother_occupation' => Validation::optionalString($_POST['mother_occupation'] ?? '', 100),
                'mother_monthly_income' => Validation::nonNegativeDecimal($_POST['mother_monthly_income'] ?? '', 'Mother monthly income') ?? '0.00',
                'father_name' => Validation::optionalString($_POST['father_name'] ?? '', 150),
                'father_is_deceased' => isset($_POST['father_is_deceased']) ? 1 : 0,
                'father_not_applicable' => isset($_POST['father_not_applicable']) ? 1 : 0,
                'father_age' => $this->optionalAge($_POST['father_age'] ?? null, 'Father age'),
                'father_occupation' => Validation::optionalString($_POST['father_occupation'] ?? '', 100),
                'father_monthly_income' => Validation::nonNegativeDecimal($_POST['father_monthly_income'] ?? '', 'Father monthly income') ?? '0.00',
                'school_type' => Validation::enum($_POST['school_type'] ?? '', ['Public', 'Private'], 'School type'),
                'school_name' => $resolvedSchoolName,
                'course' => $resolvedCourse,
                'year_level' => Validation::enum($_POST['year_level'] ?? '', ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'], 'Year level'),
            ];

            if ($profileData['mother_is_deceased'] === 1 && $profileData['mother_not_applicable'] === 1) {
                $profileData['mother_not_applicable'] = 0;
            }

            if ($profileData['father_is_deceased'] === 1 && $profileData['father_not_applicable'] === 1) {
                $profileData['father_not_applicable'] = 0;
            }

            if ($profileData['mother_is_deceased'] === 1) {
                $profileData['mother_name'] = 'N/A';
                $profileData['mother_age'] = null;
                $profileData['mother_occupation'] = 'N/A';
                $profileData['mother_monthly_income'] = '0.00';
            } elseif ($profileData['mother_not_applicable'] === 1) {
                $profileData['mother_name'] = 'N/A';
                $profileData['mother_age'] = null;
                $profileData['mother_occupation'] = 'N/A';
                $profileData['mother_monthly_income'] = '0.00';
            }

            if ($profileData['father_is_deceased'] === 1) {
                $profileData['father_name'] = 'N/A';
                $profileData['father_age'] = null;
                $profileData['father_occupation'] = 'N/A';
                $profileData['father_monthly_income'] = '0.00';
            } elseif ($profileData['father_not_applicable'] === 1) {
                $profileData['father_name'] = 'N/A';
                $profileData['father_age'] = null;
                $profileData['father_occupation'] = 'N/A';
                $profileData['father_monthly_income'] = '0.00';
            }

            $existingEmailUser = User::findByEmail($profileData['email']);
            if ($existingEmailUser && (int) $existingEmailUser['id'] !== (int) $userId) {
                throw new ValidationException('Email address is already assigned to another account.');
            }

            $allowedUploads = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
            ];
            $allowedImageUploads = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
            ];

            $gradesExtension = Validation::fileUpload($_FILES['grades_file'] ?? [], 'Grades file', $allowedUploads, 2 * 1024 * 1024);
            $residencyExtension = Validation::fileUpload($_FILES['residency_file'] ?? [], 'Barangay Residency file', $allowedUploads, 2 * 1024 * 1024);
            $idPictureUpload = $_FILES['id_picture_upload'] ?? [];
            $idPictureUploadProvided = (($idPictureUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
            $idPicture = null;
            $idPictureUploadExtension = null;
            if ($idPictureUploadProvided) {
                $idPictureUploadExtension = Validation::fileUpload($idPictureUpload, '2x2 ID picture', $allowedImageUploads, 2 * 1024 * 1024);
            } else {
                $idPicture = Validation::base64Image($_POST['id_picture_base64'] ?? '', '2x2 ID picture', ['jpg']);
            }
            $signatureUpload = $_FILES['signature_upload'] ?? [];
            $signatureUploadProvided = (($signatureUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
            $signature = null;
            $signatureUploadExtension = null;

            if ($signatureUploadProvided) {
                $signatureUploadExtension = Validation::fileUpload($signatureUpload, 'Digital signature', $allowedImageUploads, 2 * 1024 * 1024);
            } else {
                $signature = Validation::base64Image($_POST['e_signature_base64'] ?? '', 'Digital signature', ['png']);
            }

            // START THE TRANSACTION
            $db->beginTransaction();

            if (!User::updateRecoveryContact((int) $userId, null, $profileData['email'])) {
                throw new ValidationException('Applicant email could not be saved right now.');
            }

            if ($idPictureUploadProvided && $idPictureUploadExtension !== null) {
                $profileData['id_picture_path'] = $this->persistUploadedFile(
                    $idPictureUpload,
                    'photos',
                    'id_photo_',
                    $idPictureUploadExtension,
                    $storedFiles
                );
            } else {
                $profileData['id_picture_path'] = $this->persistBase64Asset(
                    $idPicture['binary'],
                    'photos',
                    'id_photo_',
                    $idPicture['extension'],
                    $storedFiles
                );
            }
            if ($signatureUploadProvided && $signatureUploadExtension !== null) {
                $profileData['e_signature_path'] = $this->persistUploadedFile(
                    $signatureUpload,
                    'signatures',
                    'signature_',
                    $signatureUploadExtension,
                    $storedFiles
                );
            } else {
                $profileData['e_signature_path'] = $this->persistBase64Asset(
                    $signature['binary'],
                    'signatures',
                    'signature_',
                    $signature['extension'],
                    $storedFiles
                );
            }

            // 1. Insert/Update Permanent Profile Data
            $stmt = $db->prepare("INSERT INTO student_profiles 
                (user_id, last_name, first_name, middle_name, suffix, date_of_birth, place_of_birth, sex, civil_status, address_line, address_barangay, mother_name, mother_is_deceased, mother_age, mother_occupation, mother_monthly_income, father_name, father_is_deceased, father_age, father_occupation, father_monthly_income, school_type, school_name, course, year_level, e_signature_path, id_picture_path) 
                VALUES (:uid, :lname, :fname, :mname, :suffix, :dob, :place_of_birth, :sex, :civil, :address_line, :brgy, :mother_name, :mother_is_deceased, :mother_age, :mother_occupation, :mother_income, :father_name, :father_is_deceased, :father_age, :father_occupation, :father_income, :stype, :sname, :course, :ylvl, :sig_path, :photo_path)");

            $stmt->execute([
                'uid' => $userId,
                'lname' => $profileData['last_name'],
                'fname' => $profileData['first_name'],
                'mname' => $profileData['middle_name'],
                'suffix' => $profileData['suffix'],
                'dob' => $profileData['date_of_birth'],
                'place_of_birth' => $profileData['place_of_birth'],
                'sex' => $profileData['sex'],
                'civil' => $profileData['civil_status'],
                'address_line' => $profileData['address_line'],
                'brgy' => $profileData['address_barangay'],
                'mother_name' => $profileData['mother_name'],
                'mother_is_deceased' => $profileData['mother_is_deceased'],
                'mother_age' => $profileData['mother_age'],
                'mother_occupation' => $profileData['mother_occupation'],
                'mother_income' => $profileData['mother_monthly_income'],
                'father_name' => $profileData['father_name'],
                'father_is_deceased' => $profileData['father_is_deceased'],
                'father_age' => $profileData['father_age'],
                'father_occupation' => $profileData['father_occupation'],
                'father_income' => $profileData['father_monthly_income'],
                'stype' => $profileData['school_type'],
                'sname' => $profileData['school_name'],
                'course' => $profileData['course'],
                'ylvl' => $profileData['year_level'],
                'sig_path' => $profileData['e_signature_path'],
                'photo_path' => $profileData['id_picture_path'],
            ]);

            $studentId = $db->lastInsertId();

            // 2. Create the Application Record for this Semester
            $stmtApp = $db->prepare("INSERT INTO applications (student_id, school_year, semester, application_type, siblings_json, education_json, grants_json, privacy_consent_at, status) VALUES (:sid, :sy, :sem, :application_type, :siblings_json, :education_json, :grants_json, :privacy_consent_at, 'Submitted')");
            // In a real scenario, you'd pull SY and Sem from an Admin settings table, but we hardcode for the example
            $stmtApp->execute([
                'sid' => $studentId,
                'sy' => $currentSchoolYear,
                'sem' => $currentSemester,
                'application_type' => $applicationType,
                'siblings_json' => json_encode($siblings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'education_json' => json_encode($education, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'grants_json' => json_encode($grants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'privacy_consent_at' => date('Y-m-d H:i:s'),
            ]);

            $appId = $db->lastInsertId();

            $gradesPath = $this->persistUploadedFile($_FILES['grades_file'], 'documents', 'grades_', $gradesExtension, $storedFiles);
            $residencyPath = $this->persistUploadedFile($_FILES['residency_file'], 'documents', 'residency_', $residencyExtension, $storedFiles);

            $stmtDoc = $db->prepare("INSERT INTO documents (application_id, document_type, file_path, status) VALUES (:aid, :type, :path, 'Pending')");
            $stmtDoc->execute(['aid' => $appId, 'type' => 'Grades', 'path' => $gradesPath]);
            $gradesDocumentId = (int) $db->lastInsertId();
            DocumentVersion::createSnapshot(
                $gradesDocumentId,
                $gradesPath,
                'Pending',
                null,
                (int) $userId,
                'Initial Upload'
            );
            $stmtDoc->execute(['aid' => $appId, 'type' => 'Barangay Residency', 'path' => $residencyPath]);
            $residencyDocumentId = (int) $db->lastInsertId();
            DocumentVersion::createSnapshot(
                $residencyDocumentId,
                $residencyPath,
                'Pending',
                null,
                (int) $userId,
                'Initial Upload'
            );

            // COMMIT ALL CHANGES (If everything above worked perfectly)
            $db->commit();

            Notification::create(
                (int) $userId,
                'Application Submitted',
                'Your scholarship application was submitted successfully and is now waiting for review.',
                'student/dashboard'
            );
            Notification::createForRoles(
                ['Staff', 'Admin'],
                'New Scholarship Application',
                $profileData['first_name'] . ' ' . $profileData['last_name'] . ' submitted a new scholarship application.',
                'staff/dashboard'
            );

            AuditLog::recordCurrentUser(
                'application.submitted',
                'application',
                (int) $appId,
                'Student submitted a scholarship application.',
                [
                    'application_type' => $applicationType,
                    'school_year' => $currentSchoolYear,
                    'semester' => $currentSemester,
                    'student_profile_id' => (int) $studentId,
                ]
            );

            // Redirect to dashboard with success message
            redirect_with_flash('student/dashboard', 'success', 'Application submitted successfully.');

        } catch (ValidationException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->cleanupFiles($storedFiles);
            AuditLog::recordCurrentUser(
                'application.submission_failed_validation',
                'application',
                null,
                'Student application submission failed validation.',
                ['message' => $e->getMessage()]
            );
            redirect_with_flash('student/apply', 'error', $e->getMessage());
        } catch (Exception $e) {
            // ROLLBACK EVERYTHING if any query or upload failed
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->cleanupFiles($storedFiles);
            error_log('Application submission failed: ' . $e->getMessage());
            AuditLog::recordCurrentUser(
                'application.submission_failed_system',
                'application',
                null,
                'Student application submission failed due to a system error.',
                ['message' => $e->getMessage()]
            );
            redirect_with_flash('student/apply', 'error', 'Application failed to submit. Please try again.');
        }
    }

    private function persistUploadedFile(array $file, string $directory, string $prefix, string $extension, array &$storedFiles): string
    {
        $targetDirectory = $this->ensureStorageDirectory($directory);
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $prefix . uniqid('', true) . '.' . $extension;

        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            throw new ValidationException('A file upload could not be saved.');
        }

        $storedFiles[] = $targetPath;

        return $targetPath;
    }

    private function persistBase64Asset(string $binary, string $directory, string $prefix, string $extension, array &$storedFiles): string
    {
        $targetDirectory = $this->ensureStorageDirectory($directory);
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $prefix . uniqid('', true) . '.' . $extension;

        if (file_put_contents($targetPath, $binary) === false) {
            throw new ValidationException('A captured image could not be saved.');
        }

        $storedFiles[] = $targetPath;

        return $targetPath;
    }

    private function ensureStorageDirectory(string $directory): string
    {
        $fullPath = storage_path($directory);
        if (!is_dir($fullPath) && !mkdir($fullPath, 0775, true) && !is_dir($fullPath)) {
            throw new ValidationException('Storage directory is not available.');
        }

        return $fullPath;
    }

    private function cleanupFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function decodeJsonRows(string $json, array $fallback): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) && $decoded !== [] ? array_values($decoded) : $fallback;
    }

    private function optionalAge(mixed $value, string $field): ?int
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (!ctype_digit($normalized)) {
            throw new ValidationException($field . ' must be a valid whole number.');
        }

        $age = (int) $normalized;
        if ($age < 0 || $age > 120) {
            throw new ValidationException($field . ' must be between 0 and 120.');
        }

        return $age;
    }

    private function findOwnedApplicationRecord(int $userId, int $applicationId): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT
                a.*,
                p.*,
                u.email,
                u.phone_number,
                p.user_id
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE a.id = :application_id AND p.user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            'application_id' => $applicationId,
            'user_id' => $userId,
        ]);
        $application = $stmt->fetch();

        if (!$application) {
            return null;
        }

        $documentsStmt = $db->prepare("
            SELECT id, document_type, file_path, status, rejection_remarks, uploaded_at, updated_at
            FROM documents
            WHERE application_id = :application_id
            ORDER BY id ASC
        ");
        $documentsStmt->execute(['application_id' => $applicationId]);
        $documents = $documentsStmt->fetchAll();

        $siblings = json_decode((string) ($application['siblings_json'] ?? '[]'), true);
        $education = json_decode((string) ($application['education_json'] ?? '[]'), true);
        $grants = json_decode((string) ($application['grants_json'] ?? '[]'), true);

        return [
            'application' => $application,
            'documents' => is_array($documents) ? $documents : [],
            'siblings' => is_array($siblings) ? $siblings : [],
            'education' => is_array($education) ? $education : [],
            'grants' => is_array($grants) ? $grants : [],
        ];
    }

    private function toDataUri(string $path): string
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '' || !is_file($normalizedPath)) {
            return '';
        }

        $mimeType = mime_content_type($normalizedPath) ?: 'application/octet-stream';
        $binary = file_get_contents($normalizedPath);
        if ($binary === false) {
            return '';
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
    }

    private function buildStudentApplicationTimeline(array $application, array $documents): array
    {
        $timeline = [];

        $timeline[] = [
            'time' => (string) ($application['created_at'] ?? ''),
            'icon' => 'fa-file-circle-plus',
            'badge_class' => 'text-bg-primary',
            'title' => 'Application submitted',
            'details' => trim((string) ($application['application_type'] ?? 'New') . ' application'),
        ];

        foreach ($documents as $document) {
            $documentType = \document_type_label((string) ($document['document_type'] ?? 'Document'));
            $status = (string) ($document['status'] ?? 'Pending');
            $eventTime = (string) ($document['updated_at'] ?? $document['uploaded_at'] ?? '');
            $details = match ($status) {
                'Verified' => $documentType . ' was verified.',
                'Rejected' => $documentType . ' was rejected. ' . (string) ($document['rejection_remarks'] ?? 'Please review the remarks and upload a corrected file.'),
                default => $documentType . ' was submitted and is waiting for review.',
            };

            $timeline[] = [
                'time' => $eventTime,
                'icon' => $status === 'Rejected' ? 'fa-file-circle-xmark' : ($status === 'Verified' ? 'fa-file-circle-check' : 'fa-file-arrow-up'),
                'badge_class' => $status === 'Rejected' ? 'text-bg-danger' : ($status === 'Verified' ? 'text-bg-success' : 'text-bg-secondary'),
                'title' => $documentType . ' ' . strtolower($status === 'Pending' ? 'submitted' : $status),
                'details' => $details,
            ];
        }

        if (!empty($application['interview_schedule'])) {
            $timeline[] = [
                'time' => (string) $application['interview_schedule'],
                'icon' => 'fa-calendar-check',
                'badge_class' => 'text-bg-info',
                'title' => 'Interview scheduled',
                'details' => trim('Venue: ' . (string) ($application['interview_venue'] ?? 'Municipal Hall')),
            ];
        }

        if (!empty($application['interview_result'])) {
            $result = (string) $application['interview_result'];
            $timeline[] = [
                'time' => (string) ($application['interview_result_at'] ?? $application['updated_at'] ?? ''),
                'icon' => $result === 'Passed' ? 'fa-circle-check' : 'fa-circle-xmark',
                'badge_class' => $result === 'Passed' ? 'text-bg-success' : 'text-bg-danger',
                'title' => 'Interview result recorded',
                'details' => 'Result: ' . $result,
            ];
        }

        if (!empty($application['soa_deadline'])) {
            $timeline[] = [
                'time' => (string) $application['soa_deadline'],
                'icon' => 'fa-hourglass-half',
                'badge_class' => 'text-bg-warning',
                'title' => 'SOA deadline set',
                'details' => 'Statement of Account deadline for this application.',
            ];
        }

        if (!empty($application['payout_schedule'])) {
            $timeline[] = [
                'time' => (string) $application['payout_schedule'],
                'icon' => 'fa-money-check-dollar',
                'badge_class' => 'text-bg-success',
                'title' => 'Payout scheduled',
                'details' => trim('Venue: ' . (string) ($application['payout_venue'] ?? 'Municipal Hall')),
            ];
        }

        $timeline = array_values(array_filter(
            $timeline,
            static fn(array $entry): bool => trim((string) ($entry['time'] ?? '')) !== ''
        ));

        usort($timeline, static function (array $left, array $right): int {
            return strtotime((string) ($right['time'] ?? '')) <=> strtotime((string) ($left['time'] ?? ''));
        });

        return $timeline;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        $precision = $unitIndex === 0 ? 0 : 2;

        return number_format($size, $precision) . ' ' . $units[$unitIndex];
    }

    private function getResubmissionDocuments(int $applicationId): array
    {
        if ($applicationId <= 0) {
            return [];
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT id, document_type, file_path, status, rejection_remarks, updated_at
            FROM documents
            WHERE application_id = :application_id
              AND document_type IN ('Grades', 'Residency', 'Barangay Residency')
            ORDER BY FIELD(document_type, 'Grades', 'Barangay Residency', 'Residency'), id DESC
        ");
        $stmt->execute(['application_id' => $applicationId]);
        $documents = $stmt->fetchAll();

        $byType = [];
        foreach ($documents as $document) {
            $documentType = \canonical_document_type((string) ($document['document_type'] ?? ''));
            if ($documentType !== '' && !isset($byType[$documentType])) {
                $byType[$documentType] = $document;
            }
        }

        return $byType;
    }

    private function handleInitialDocumentResubmission(
        \PDO $db,
        int $userId,
        array $existingApplicationRow,
        string $currentSchoolYear,
        string $currentSemester,
        array &$storedFiles
    ): void {
        $applicationId = (int) ($existingApplicationRow['id'] ?? 0);
        if ($applicationId <= 0) {
            throw new ValidationException('Invalid application for document resubmission.');
        }

        $documents = $this->getResubmissionDocuments($applicationId);
        if ($documents === []) {
            throw new ValidationException('No application documents were found for resubmission.');
        }

        $allowedUploads = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
        ];

        $replacedDocuments = [];
        $db->beginTransaction();

        try {
            foreach ($documents as $documentType => $document) {
                if ((string) ($document['status'] ?? '') !== 'Rejected') {
                    continue;
                }

                $inputName = $documentType === 'Grades' ? 'grades_file' : 'residency_file';
                $documentLabel = $documentType === 'Grades' ? 'Previous grades / report card' : 'Certificate of barangay residency';
                $extension = Validation::fileUpload($_FILES[$inputName] ?? [], $documentLabel, $allowedUploads, 2 * 1024 * 1024);
                $newFilePath = $this->persistUploadedFile($_FILES[$inputName], 'documents', strtolower($documentType) . '_', $extension, $storedFiles);

                $updateStmt = $db->prepare("
                    UPDATE documents
                    SET file_path = :file_path,
                        status = 'Pending',
                        rejection_remarks = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    'file_path' => $newFilePath,
                    'id' => (int) ($document['id'] ?? 0),
                ]);
                DocumentVersion::createSnapshot(
                    (int) ($document['id'] ?? 0),
                    $newFilePath,
                    'Pending',
                    null,
                    $userId,
                    'Resubmission'
                );

                $replacedDocuments[] = [
                    'type' => $documentType,
                    'old_path' => (string) ($document['file_path'] ?? ''),
                    'new_path' => $newFilePath,
                ];
            }

            if ($replacedDocuments === []) {
                throw new ValidationException('There are no rejected initial documents that need to be resubmitted right now.');
            }

            $applicationUpdateStmt = $db->prepare("
                UPDATE applications
                SET status = 'Initial_Review'
                WHERE id = :application_id
            ");
            $applicationUpdateStmt->execute(['application_id' => $applicationId]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        foreach ($replacedDocuments as $replacement) {
            $oldPath = $replacement['old_path'];
            $newPath = $replacement['new_path'];
            if ($oldPath !== '' && $oldPath !== $newPath && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        Notification::create(
            $userId,
            'Documents Resubmitted',
            'Your corrected application documents were submitted successfully and are now waiting for review.',
            'student/dashboard'
        );
        Notification::createForRoles(
            ['Staff', 'Admin'],
            'Corrected Documents Submitted',
            'A student resubmitted corrected application documents for review.',
            'staff/dashboard'
        );

        AuditLog::recordCurrentUser(
            'application.documents_resubmitted',
            'application',
            $applicationId,
            'Student resubmitted rejected initial documents.',
            [
                'documents' => array_column($replacedDocuments, 'type'),
                'school_year' => $currentSchoolYear,
                'semester' => $currentSemester,
            ]
        );

        redirect_with_flash('student/dashboard', 'success', 'Corrected documents submitted successfully. Your application is back in review.');
    }
}
