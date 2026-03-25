<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Support/helpers.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

use App\Config\Database;

const DEMO_EMAIL_DOMAIN = 'seed.demo.local';
const DEMO_BATCH_PREFIX = 'Demo Seed';
const DEMO_PASSWORD = 'student123';

const BARANGAYS = [
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
];

const SCHOOL_NAMES = [
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

const OTHER_SCHOOLS = [
    'West Visayas Maritime Academy',
    'Negros Occidental Polytechnic Institute',
    'Kabankalan City Community College',
    'Philippine State College of Aeronautics - Bacolod Extension',
];

const COURSES = [
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

const OTHER_COURSES = [
    'BS Office Administration',
    'Bachelor of Public Administration',
    'BS Entrepreneurship',
    'BS Social Work',
];

const FIRST_NAMES = [
    'Juan Miguel', 'Maria Luisa', 'Carlo', 'Alyssa', 'Jhon Mark', 'Princess Mae', 'Renz', 'Kimberly',
    'Paolo', 'Christine Joy', 'Mark Anthony', 'Lovely Ann', 'Reymart', 'Angelica', 'Joshua', 'Charlene',
    'Jeric', 'Mary Grace', 'Kenneth', 'Joyce Anne', 'Rafael', 'Aira Mae', 'Vincent', 'Shaina', 'Bryan',
    'Rica Mae', 'Janine', 'Leomar', 'Rose Ann', 'Arnel', 'Jessa Mae', 'Michael John', 'Irish Mae',
    'Roderick', 'Camille', 'Jaypee', 'Maricel', 'Noel', 'Krisha', 'Aldrin', 'Jennifer',
];

const MIDDLE_NAMES = [
    'Santos', 'Bautista', 'Ramos', 'Reyes', 'Garcia', 'Flores', 'Torres', 'Lopez', 'Mendoza', 'Castro',
    'Aquino', 'Fernandez', 'Navarro', 'Morales', 'Delos Santos', 'Vargas', 'Abad', 'Mercado', 'Tolentino', 'De Lara',
];

const LAST_NAMES = [
    'Dela Cruz', 'Villanueva', 'Mendoza', 'Garcia', 'Santos', 'Ramos', 'Reyes', 'Fernandez', 'Aquino', 'Torres',
    'Flores', 'Rivera', 'Navarro', 'Morales', 'Castillo', 'Lopez', 'Caballero', 'Rosales', 'Gonzales', 'Salvador',
    'Bantayan', 'Del Rosario', 'Pajares', 'Arriola', 'Lacson', 'Benedicto', 'Montinola', 'Yulo', 'Jalandoni', 'Suarez',
];

const OCCUPATIONS = [
    'Farmer', 'Vendor', 'Driver', 'Housekeeper', 'Carpenter', 'Fisherfolk', 'Factory Worker', 'Barangay Worker',
    'Small Business Owner', 'Government Employee',
];

const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
const SEMESTER = '2nd Semester';
const SCHOOL_YEAR = '2025-2026';

$statusCounts = [
    'Submitted' => 10,
    'Initial_Review' => 10,
    'Pending_Resubmission' => 10,
    'For_Interview' => 10,
    'Not_Eligible' => 10,
    'Eligible_Awaiting_SOA' => 10,
    'SOA_Under_Review' => 10,
    'SOA_Resubmission_Required' => 10,
    'Approved_Pending_Payroll' => 10,
    'Approved_Finished' => 10,
    'Forfeited' => 10,
];

main();

function main(): void
{
    global $statusCounts;

    bootstrapDatabaseSchemaIfNeeded();
    $db = Database::connect();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ensureStorageDirectories();
    [$adminId, $staffId] = ensureDefaultOfficeUsers($db);
    $createdFiles = [];

    try {
        cleanupPreviousDemoData($db);

        $db->beginTransaction();
        upsertApplicationSettings($db);
        $batches = createDemoBatches($db);

        $passwordHash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Failed to create password hash for demo accounts.');
        }

        $userStmt = $db->prepare('
            INSERT INTO users (role, first_name, last_name, phone_number, email, password_hash, is_verified, is_active)
            VALUES (\'Student\', :first_name, :last_name, :phone_number, :email, :password_hash, 1, 1)
        ');
        $profileStmt = $db->prepare('
            INSERT INTO student_profiles (
                user_id, last_name, first_name, middle_name, suffix, date_of_birth, sex, civil_status,
                address_line, address_barangay, mother_name, mother_is_deceased, mother_occupation, mother_monthly_income,
                father_name, father_is_deceased, father_occupation, father_monthly_income,
                school_type, school_name, course, year_level, e_signature_path, id_picture_path,
                created_at, updated_at
            ) VALUES (
                :user_id, :last_name, :first_name, :middle_name, :suffix, :date_of_birth, :sex, :civil_status,
                :address_line, :address_barangay, :mother_name, :mother_is_deceased, :mother_occupation, :mother_monthly_income,
                :father_name, :father_is_deceased, :father_occupation, :father_monthly_income,
                :school_type, :school_name, :course, :year_level, :e_signature_path, :id_picture_path,
                :created_at, :updated_at
            )
        ');
        $applicationStmt = $db->prepare('
            INSERT INTO applications (
                student_id, school_year, semester, application_type, status, siblings_json, education_json, grants_json,
                privacy_consent_at, interview_result, interview_result_at, interview_batch_id, payout_batch_id, soa_deadline,
                final_grant_amount, is_archived, archived_at, archived_by_user_id, archive_notes, created_at, updated_at
            ) VALUES (
                :student_id, :school_year, :semester, :application_type, :status, :siblings_json, :education_json, :grants_json,
                :privacy_consent_at, :interview_result, :interview_result_at, :interview_batch_id, :payout_batch_id, :soa_deadline,
                :final_grant_amount, :is_archived, :archived_at, :archived_by_user_id, :archive_notes, :created_at, :updated_at
            )
        ');
        $documentStmt = $db->prepare('
            INSERT INTO documents (
                application_id, document_type, file_path, status, rejection_remarks, uploaded_at, updated_at
            ) VALUES (
                :application_id, :document_type, :file_path, :status, :rejection_remarks, :uploaded_at, :updated_at
            )
        ');
        $documentVersionStmt = $db->prepare('
            INSERT INTO document_versions (
                document_id, version_number, file_path, document_status, rejection_remarks, source_action, uploaded_by_user_id, created_at
            ) VALUES (
                :document_id, :version_number, :file_path, :document_status, :rejection_remarks, :source_action, :uploaded_by_user_id, :created_at
            )
        ');
        $notificationStmt = $db->prepare('
            INSERT INTO notifications (user_id, title, message, link_path, is_read, created_at)
            VALUES (:user_id, :title, :message, :link_path, :is_read, :created_at)
        ');
        $caseNoteStmt = $db->prepare('
            INSERT INTO case_notes (application_id, user_id, note_text, created_at)
            VALUES (:application_id, :user_id, :note_text, :created_at)
        ');

        $sequence = 0;
        foreach ($statusCounts as $status => $count) {
            for ($offset = 0; $offset < $count; $offset++) {
                $sequence++;
                $profile = buildProfile($sequence);
                $createdAt = applicationCreatedAt($sequence, $status, $offset);
                $updatedAt = $createdAt->modify('+2 days');

                $photoPath = createPhotoAsset($profile, $createdFiles);
                $signaturePath = createSignatureAsset($profile, $sequence, $createdFiles);

                $email = sprintf('student%03d@%s', $sequence, DEMO_EMAIL_DOMAIN);
                $phoneNumber = sprintf('09%09d', 800000000 + $sequence);

                $userStmt->execute([
                    'first_name' => $profile['first_name'],
                    'last_name' => $profile['last_name'],
                    'phone_number' => $phoneNumber,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                ]);
                $userId = (int) $db->lastInsertId();

                $profileStmt->execute([
                    'user_id' => $userId,
                    'last_name' => $profile['last_name'],
                    'first_name' => $profile['first_name'],
                    'middle_name' => $profile['middle_name'],
                    'suffix' => $profile['suffix'],
                    'date_of_birth' => $profile['date_of_birth'],
                    'sex' => $profile['sex'],
                    'civil_status' => $profile['civil_status'],
                    'address_line' => $profile['address_line'],
                    'address_barangay' => $profile['address_barangay'],
                    'mother_name' => $profile['mother_name'],
                    'mother_is_deceased' => $profile['mother_is_deceased'],
                    'mother_occupation' => $profile['mother_occupation'],
                    'mother_monthly_income' => $profile['mother_monthly_income'],
                    'father_name' => $profile['father_name'],
                    'father_is_deceased' => $profile['father_is_deceased'],
                    'father_occupation' => $profile['father_occupation'],
                    'father_monthly_income' => $profile['father_monthly_income'],
                    'school_type' => $profile['school_type'],
                    'school_name' => $profile['school_name'],
                    'course' => $profile['course'],
                    'year_level' => $profile['year_level'],
                    'e_signature_path' => $signaturePath,
                    'id_picture_path' => $photoPath,
                    'created_at' => formatDateTime($createdAt),
                    'updated_at' => formatDateTime($updatedAt),
                ]);
                $studentId = (int) $db->lastInsertId();

                $applicationBlueprint = buildApplicationBlueprint($status, $sequence, $offset, $batches, $adminId);
                $applicationStmt->execute([
                    'student_id' => $studentId,
                    'school_year' => SCHOOL_YEAR,
                    'semester' => SEMESTER,
                    'application_type' => $sequence % 3 === 0 ? 'Renewal' : 'New',
                    'status' => $status,
                    'siblings_json' => json_encode(buildSiblings($sequence), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'education_json' => json_encode(buildEducation($profile, $sequence), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'grants_json' => json_encode(buildGrants($sequence), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'privacy_consent_at' => formatDateTime($createdAt),
                    'interview_result' => $applicationBlueprint['interview_result'],
                    'interview_result_at' => $applicationBlueprint['interview_result_at'],
                    'interview_batch_id' => $applicationBlueprint['interview_batch_id'],
                    'payout_batch_id' => $applicationBlueprint['payout_batch_id'],
                    'soa_deadline' => $applicationBlueprint['soa_deadline'],
                    'final_grant_amount' => $applicationBlueprint['final_grant_amount'],
                    'is_archived' => $applicationBlueprint['is_archived'],
                    'archived_at' => $applicationBlueprint['archived_at'],
                    'archived_by_user_id' => $applicationBlueprint['archived_by_user_id'],
                    'archive_notes' => $applicationBlueprint['archive_notes'],
                    'created_at' => formatDateTime($createdAt),
                    'updated_at' => $applicationBlueprint['updated_at'],
                ]);
                $applicationId = (int) $db->lastInsertId();

                foreach (buildDocumentBlueprints($status, $profile, $sequence, $createdFiles) as $documentBlueprint) {
                    $versions = $documentBlueprint['versions'];
                    $latest = $versions[count($versions) - 1];

                    $documentStmt->execute([
                        'application_id' => $applicationId,
                        'document_type' => $documentBlueprint['document_type'],
                        'file_path' => $latest['file_path'],
                        'status' => $latest['document_status'],
                        'rejection_remarks' => $latest['rejection_remarks'],
                        'uploaded_at' => $versions[0]['created_at'],
                        'updated_at' => $latest['created_at'],
                    ]);
                    $documentId = (int) $db->lastInsertId();

                    foreach ($versions as $versionIndex => $version) {
                        $documentVersionStmt->execute([
                            'document_id' => $documentId,
                            'version_number' => $versionIndex + 1,
                            'file_path' => $version['file_path'],
                            'document_status' => $version['document_status'],
                            'rejection_remarks' => $version['rejection_remarks'],
                            'source_action' => $version['source_action'],
                            'uploaded_by_user_id' => $userId,
                            'created_at' => $version['created_at'],
                        ]);
                    }
                }

                foreach (buildNotifications($status, $userId, $applicationBlueprint, $offset) as $notification) {
                    $notificationStmt->execute($notification);
                }

                $caseNote = buildCaseNote($status, $applicationId, $staffId, $adminId, $profile, $applicationBlueprint);
                if ($caseNote !== null) {
                    $caseNoteStmt->execute($caseNote);
                }
            }
        }

        $db->commit();

        echo "Demo seed data created successfully.\n";
        echo "Applicants inserted: 110\n";
        foreach ($statusCounts as $status => $count) {
            echo sprintf("- %s: %d\n", $status, $count);
        }
        echo "Demo student password: " . DEMO_PASSWORD . "\n";
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        cleanupCreatedFiles($createdFiles);
        fwrite(STDERR, "Seed failed: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

function bootstrapDatabaseSchemaIfNeeded(): void
{
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = (int) ($_ENV['DB_PORT'] ?? 3306);
    $dbName = $_ENV['DB_DATABASE'] ?? 'lgu_san_enrique_scholarship';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $bootstrap = mysqli_init();
    if ($bootstrap === false) {
        throw new RuntimeException('Unable to initialize MySQL bootstrap connection.');
    }

    if (!mysqli_real_connect($bootstrap, $host, $username, $password, null, $port, null)) {
        throw new RuntimeException('Unable to connect to MySQL for schema bootstrap: ' . mysqli_connect_error());
    }

    $safeDbName = mysqli_real_escape_string($bootstrap, $dbName);
    $existsQuery = mysqli_query(
        $bootstrap,
        "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$safeDbName}' LIMIT 1"
    );
    if ($existsQuery === false) {
        mysqli_close($bootstrap);
        throw new RuntimeException('Unable to inspect existing databases.');
    }

    $exists = mysqli_fetch_assoc($existsQuery) !== null;
    mysqli_free_result($existsQuery);

    if ($exists) {
        mysqli_close($bootstrap);
        return;
    }

    $schemaPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database_schema.sql';
    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql === false) {
        mysqli_close($bootstrap);
        throw new RuntimeException('Unable to read database schema file.');
    }

    if (!mysqli_multi_query($bootstrap, $schemaSql)) {
        $error = mysqli_error($bootstrap);
        mysqli_close($bootstrap);
        throw new RuntimeException('Unable to import database schema: ' . $error);
    }

    while (mysqli_more_results($bootstrap)) {
        if (!mysqli_next_result($bootstrap)) {
            $error = mysqli_error($bootstrap);
            mysqli_close($bootstrap);
            throw new RuntimeException('Database schema import failed: ' . $error);
        }
        $result = mysqli_store_result($bootstrap);
        if ($result instanceof mysqli_result) {
            mysqli_free_result($result);
        }
    }

    mysqli_close($bootstrap);
}

function ensureStorageDirectories(): void
{
    foreach (['documents', 'photos', 'signatures'] as $directory) {
        $path = storage_path($directory);
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create storage directory: ' . $path);
        }
    }
}

function ensureDefaultOfficeUsers(PDO $db): array
{
    $adminId = ensureOfficeUser(
        $db,
        'Admin',
        'System',
        'Administrator',
        '09123456789',
        '$2y$10$U1fDFzi7O85ugx6414Kj3.RKn/yz3WkUlJqlfiaLD8RtelyMV9cL2'
    );
    $staffId = ensureOfficeUser(
        $db,
        'Staff',
        'Default',
        'Staff',
        '09987654321',
        '$2y$10$UtGNxUckJ7G/cywjAQSw1Od75q/F3aylFJBelEoRepPyBbD.zNO82'
    );

    return [$adminId, $staffId];
}

function ensureOfficeUser(PDO $db, string $role, string $firstName, string $lastName, string $phoneNumber, string $passwordHash): int
{
    $select = $db->prepare('SELECT id FROM users WHERE phone_number = :phone_number LIMIT 1');
    $select->execute(['phone_number' => $phoneNumber]);
    $existingId = $select->fetchColumn();
    if ($existingId !== false) {
        return (int) $existingId;
    }

    $insert = $db->prepare('
        INSERT INTO users (role, first_name, last_name, phone_number, password_hash, is_verified, is_active)
        VALUES (:role, :first_name, :last_name, :phone_number, :password_hash, 1, 1)
    ');
    $insert->execute([
        'role' => $role,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone_number' => $phoneNumber,
        'password_hash' => $passwordHash,
    ]);

    return (int) $db->lastInsertId();
}

function cleanupPreviousDemoData(PDO $db): void
{
    $pathQuery = $db->query("
        SELECT sp.e_signature_path AS path
        FROM users u
        JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.email LIKE '%@" . DEMO_EMAIL_DOMAIN . "'
        UNION
        SELECT sp.id_picture_path AS path
        FROM users u
        JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.email LIKE '%@" . DEMO_EMAIL_DOMAIN . "'
        UNION
        SELECT d.file_path AS path
        FROM users u
        JOIN student_profiles sp ON sp.user_id = u.id
        JOIN applications a ON a.student_id = sp.id
        JOIN documents d ON d.application_id = a.id
        WHERE u.email LIKE '%@" . DEMO_EMAIL_DOMAIN . "'
    ");
    $paths = $pathQuery->fetchAll(PDO::FETCH_COLUMN);
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    $deleteUsers = $db->prepare('DELETE FROM users WHERE email LIKE :email_pattern');
    $deleteUsers->execute(['email_pattern' => '%@' . DEMO_EMAIL_DOMAIN]);

    $deleteBatches = $db->prepare('DELETE FROM batches WHERE batch_name LIKE :batch_name');
    $deleteBatches->execute(['batch_name' => DEMO_BATCH_PREFIX . '%']);
}

function upsertApplicationSettings(PDO $db): void
{
    $stmt = $db->prepare('
        INSERT INTO application_settings (
            id, school_year, semester, application_start_date, application_end_date, soa_deadline_mode,
            soa_deadline_days_after_passed, soa_deadline, auto_archive_completed_records, is_open
        ) VALUES (
            1, :school_year, :semester, :start_date, :end_date, :soa_deadline_mode,
            :soa_deadline_days_after_passed, :soa_deadline, 1, 1
        )
        ON DUPLICATE KEY UPDATE
            school_year = VALUES(school_year),
            semester = VALUES(semester),
            application_start_date = VALUES(application_start_date),
            application_end_date = VALUES(application_end_date),
            soa_deadline_mode = VALUES(soa_deadline_mode),
            soa_deadline_days_after_passed = VALUES(soa_deadline_days_after_passed),
            soa_deadline = VALUES(soa_deadline),
            auto_archive_completed_records = VALUES(auto_archive_completed_records),
            is_open = VALUES(is_open)
    ');
    $stmt->execute([
        'school_year' => SCHOOL_YEAR,
        'semester' => SEMESTER,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'soa_deadline_mode' => 'AfterPassed',
        'soa_deadline_days_after_passed' => 15,
        'soa_deadline' => '2026-12-31',
    ]);
}

function createDemoBatches(PDO $db): array
{
    $stmt = $db->prepare('INSERT INTO batches (batch_type, batch_name, scheduled_date, venue) VALUES (:batch_type, :batch_name, :scheduled_date, :venue)');

    $interviewIds = [];
    $payoutIds = [];
    $batchSpecs = [
        ['Interview', DEMO_BATCH_PREFIX . ' Interview Batch A', '2026-04-15 09:00:00', 'San Enrique Municipal Hall'],
        ['Interview', DEMO_BATCH_PREFIX . ' Interview Batch B', '2026-04-16 09:00:00', 'San Enrique Municipal Hall'],
        ['Interview', DEMO_BATCH_PREFIX . ' Interview Batch C', '2026-04-17 13:30:00', 'Old Session Hall'],
        ['Payout', DEMO_BATCH_PREFIX . ' Payout Batch A', '2026-05-10 08:00:00', 'San Enrique Gymnasium'],
        ['Payout', DEMO_BATCH_PREFIX . ' Payout Batch B', '2026-05-11 08:00:00', 'San Enrique Gymnasium'],
        ['Payout', DEMO_BATCH_PREFIX . ' Payout Batch C', '2026-05-12 08:00:00', 'Barangay Tibsoc Covered Court'],
    ];

    foreach ($batchSpecs as [$batchType, $batchName, $scheduledDate, $venue]) {
        $stmt->execute([
            'batch_type' => $batchType,
            'batch_name' => $batchName,
            'scheduled_date' => $scheduledDate,
            'venue' => $venue,
        ]);
        $batchId = (int) $db->lastInsertId();
        if ($batchType === 'Interview') {
            $interviewIds[] = $batchId;
        } else {
            $payoutIds[] = $batchId;
        }
    }

    return ['interview' => $interviewIds, 'payout' => $payoutIds];
}

function buildProfile(int $sequence): array
{
    $firstName = FIRST_NAMES[($sequence - 1) % count(FIRST_NAMES)];
    $middleName = MIDDLE_NAMES[($sequence - 1) % count(MIDDLE_NAMES)];
    $lastName = LAST_NAMES[($sequence - 1) % count(LAST_NAMES)];
    $barangay = BARANGAYS[($sequence - 1) % count(BARANGAYS)];
    $schoolType = $sequence % 2 === 0 ? 'Public' : 'Private';

    if ($sequence % 11 === 0) {
        $schoolName = OTHER_SCHOOLS[(int) floor($sequence / 11) % count(OTHER_SCHOOLS)];
    } else {
        $schoolName = SCHOOL_NAMES[($sequence - 1) % count(SCHOOL_NAMES)];
    }

    if ($sequence % 13 === 0) {
        $course = OTHER_COURSES[(int) floor($sequence / 13) % count(OTHER_COURSES)];
    } else {
        $course = COURSES[($sequence - 1) % count(COURSES)];
    }

    $motherDeceased = $sequence % 9 === 0 ? 1 : 0;
    $fatherDeceased = $sequence % 10 === 0 ? 1 : 0;

    return [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'suffix' => $sequence % 27 === 0 ? 'Jr.' : null,
        'date_of_birth' => (new DateTimeImmutable('2004-01-01'))->modify('+' . (($sequence * 37) % 1500) . ' days')->format('Y-m-d'),
        'sex' => $sequence % 2 === 0 ? 'Female' : 'Male',
        'civil_status' => 'Single',
        'address_line' => sprintf('Purok %d, %s Street', (($sequence - 1) % 7) + 1, $barangay),
        'address_barangay' => $barangay,
        'mother_name' => 'Maria ' . $lastName,
        'mother_is_deceased' => $motherDeceased,
        'mother_occupation' => $motherDeceased ? 'N/A' : OCCUPATIONS[$sequence % count(OCCUPATIONS)],
        'mother_monthly_income' => $motherDeceased ? '0.00' : number_format(3500 + ($sequence % 8) * 1250, 2, '.', ''),
        'father_name' => 'Jose ' . $lastName,
        'father_is_deceased' => $fatherDeceased,
        'father_occupation' => $fatherDeceased ? 'N/A' : OCCUPATIONS[($sequence + 3) % count(OCCUPATIONS)],
        'father_monthly_income' => $fatherDeceased ? '0.00' : number_format(4000 + ($sequence % 10) * 1400, 2, '.', ''),
        'school_type' => $schoolType,
        'school_name' => $schoolName,
        'course' => $course,
        'year_level' => YEAR_LEVELS[($sequence - 1) % count(YEAR_LEVELS)],
    ];
}

function buildApplicationBlueprint(string $status, int $sequence, int $offset, array $batches, int $adminId): array
{
    $createdAt = applicationCreatedAt($sequence, $status, $offset);
    $updatedAt = $createdAt->modify('+3 days');
    $interviewAt = $createdAt->modify('+12 days')->format('Y-m-d H:i:s');
    $soaFuture = $createdAt->modify('+27 days')->format('Y-m-d 23:59:59');
    $soaPast = $createdAt->modify('+7 days')->format('Y-m-d 23:59:59');
    $grantAmount = number_format(4000 + (($sequence + $offset) % 6) * 500, 2, '.', '');

    $blueprint = [
        'interview_result' => null,
        'interview_result_at' => null,
        'interview_batch_id' => null,
        'payout_batch_id' => null,
        'soa_deadline' => null,
        'final_grant_amount' => null,
        'is_archived' => 0,
        'archived_at' => null,
        'archived_by_user_id' => null,
        'archive_notes' => null,
        'updated_at' => formatDateTime($updatedAt),
    ];

    switch ($status) {
        case 'For_Interview':
            $blueprint['interview_batch_id'] = $offset < 5 ? $batches['interview'][$offset % count($batches['interview'])] : null;
            break;
        case 'Not_Eligible':
            $blueprint['interview_batch_id'] = $batches['interview'][$offset % count($batches['interview'])];
            $blueprint['interview_result'] = $offset % 2 === 0 ? 'Failed' : 'Absent';
            $blueprint['interview_result_at'] = $interviewAt;
            $blueprint['updated_at'] = (new DateTimeImmutable($interviewAt))->modify('+1 day')->format('Y-m-d H:i:s');
            if ($offset >= 5) {
                $blueprint['is_archived'] = 1;
                $blueprint['archived_at'] = (new DateTimeImmutable($interviewAt))->modify('+20 days')->format('Y-m-d H:i:s');
                $blueprint['archived_by_user_id'] = $adminId;
                $blueprint['archive_notes'] = 'Archived after interview disqualification.';
            }
            break;
        case 'Eligible_Awaiting_SOA':
            $blueprint['interview_batch_id'] = $batches['interview'][$offset % count($batches['interview'])];
            $blueprint['interview_result'] = 'Passed';
            $blueprint['interview_result_at'] = $interviewAt;
            $blueprint['soa_deadline'] = $soaFuture;
            $blueprint['updated_at'] = (new DateTimeImmutable($interviewAt))->modify('+1 day')->format('Y-m-d H:i:s');
            break;
        case 'SOA_Under_Review':
        case 'SOA_Resubmission_Required':
            $blueprint['interview_batch_id'] = $batches['interview'][$offset % count($batches['interview'])];
            $blueprint['interview_result'] = 'Passed';
            $blueprint['interview_result_at'] = $interviewAt;
            $blueprint['soa_deadline'] = $soaFuture;
            $blueprint['updated_at'] = (new DateTimeImmutable($interviewAt))->modify('+5 days')->format('Y-m-d H:i:s');
            break;
        case 'Approved_Pending_Payroll':
            $blueprint['interview_batch_id'] = $batches['interview'][$offset % count($batches['interview'])];
            $blueprint['interview_result'] = 'Passed';
            $blueprint['interview_result_at'] = $interviewAt;
            $blueprint['soa_deadline'] = $soaFuture;
            $blueprint['final_grant_amount'] = $grantAmount;
            $blueprint['payout_batch_id'] = $offset < 5 ? $batches['payout'][$offset % count($batches['payout'])] : null;
            $blueprint['updated_at'] = (new DateTimeImmutable($interviewAt))->modify('+10 days')->format('Y-m-d H:i:s');
            break;
        case 'Approved_Finished':
            $blueprint['interview_batch_id'] = $batches['interview'][$offset % count($batches['interview'])];
            $blueprint['interview_result'] = 'Passed';
            $blueprint['interview_result_at'] = $interviewAt;
            $blueprint['soa_deadline'] = $soaFuture;
            $blueprint['final_grant_amount'] = $grantAmount;
            $blueprint['payout_batch_id'] = $batches['payout'][$offset % count($batches['payout'])];
            $blueprint['updated_at'] = (new DateTimeImmutable($interviewAt))->modify('+18 days')->format('Y-m-d H:i:s');
            if ($offset >= 5) {
                $blueprint['is_archived'] = 1;
                $blueprint['archived_at'] = (new DateTimeImmutable($interviewAt))->modify('+40 days')->format('Y-m-d H:i:s');
                $blueprint['archived_by_user_id'] = $adminId;
                $blueprint['archive_notes'] = 'Completed payout record archived for reference.';
            }
            break;
        case 'Forfeited':
            $blueprint['interview_batch_id'] = $batches['interview'][$offset % count($batches['interview'])];
            $blueprint['interview_result'] = 'Passed';
            $blueprint['interview_result_at'] = $interviewAt;
            $blueprint['soa_deadline'] = $soaPast;
            $blueprint['updated_at'] = (new DateTimeImmutable($interviewAt))->modify('+16 days')->format('Y-m-d H:i:s');
            if ($offset >= 5) {
                $blueprint['is_archived'] = 1;
                $blueprint['archived_at'] = (new DateTimeImmutable($interviewAt))->modify('+35 days')->format('Y-m-d H:i:s');
                $blueprint['archived_by_user_id'] = $adminId;
                $blueprint['archive_notes'] = 'Application forfeited after missing the SOA deadline.';
            }
            break;
    }

    return $blueprint;
}

function buildDocumentBlueprints(string $status, array $profile, int $sequence, array &$createdFiles): array
{
    $baseDate = applicationCreatedAt($sequence, $status, 0);
    $documents = [];
    $verified = ['document_status' => 'Verified', 'rejection_remarks' => null, 'source_action' => 'Initial Upload'];
    $pending = ['document_status' => 'Pending', 'rejection_remarks' => null, 'source_action' => 'Initial Upload'];

    if (in_array($status, ['Submitted', 'Initial_Review'], true)) {
        $documents[] = makeDocument('Grades', $profile, $sequence, $createdFiles, [$pending], $baseDate);
        $documents[] = makeDocument('Barangay Residency', $profile, $sequence, $createdFiles, [$pending], $baseDate->modify('+1 hour'));
        return $documents;
    }

    if ($status === 'Pending_Resubmission') {
        $documents[] = makeDocument('Grades', $profile, $sequence, $createdFiles, [[
            'document_status' => 'Rejected',
            'rejection_remarks' => 'Uploaded grade sheet is blurred. Please submit a clearer copy.',
            'source_action' => 'Initial Upload',
        ]], $baseDate);
        $documents[] = makeDocument('Barangay Residency', $profile, $sequence, $createdFiles, [[
            'document_status' => $sequence % 2 === 0 ? 'Verified' : 'Rejected',
            'rejection_remarks' => $sequence % 2 === 0 ? null : 'Barangay Residency certificate is missing the barangay captain signature.',
            'source_action' => 'Initial Upload',
        ]], $baseDate->modify('+1 hour'));
        return $documents;
    }

    $documents[] = makeDocument('Grades', $profile, $sequence, $createdFiles, [$verified], $baseDate);
    $documents[] = makeDocument('Barangay Residency', $profile, $sequence, $createdFiles, [$verified], $baseDate->modify('+1 hour'));

    if (in_array($status, ['SOA_Under_Review', 'SOA_Resubmission_Required', 'Approved_Pending_Payroll', 'Approved_Finished'], true)) {
        $documents[] = makeDocument('SOA', $profile, $sequence, $createdFiles, [[
            'document_status' => match ($status) {
                'SOA_Under_Review' => 'Pending',
                'SOA_Resubmission_Required' => 'Rejected',
                default => 'Verified',
            },
            'rejection_remarks' => $status === 'SOA_Resubmission_Required'
                ? 'SOA total amount does not match the registrar assessment.'
                : null,
            'source_action' => 'SOA Upload',
        ]], $baseDate->modify('+9 days'));
    }

    return $documents;
}

function makeDocument(string $documentType, array $profile, int $sequence, array &$createdFiles, array $versionsSpec, DateTimeImmutable $baseDate): array
{
    $versions = [];
    foreach ($versionsSpec as $index => $spec) {
        $createdAt = $baseDate->modify('+' . $index . ' days')->format('Y-m-d H:i:s');
        $filePath = createDocumentAsset($documentType, $profile, $spec['document_status'], $createdFiles);
        $versions[] = [
            'file_path' => $filePath,
            'document_status' => $spec['document_status'],
            'rejection_remarks' => $spec['rejection_remarks'],
            'source_action' => $spec['source_action'],
            'created_at' => $createdAt,
        ];
    }

    return [
        'document_type' => $documentType,
        'versions' => $versions,
    ];
}

function buildNotifications(string $status, int $userId, array $applicationBlueprint, int $offset): array
{
    $notifications = [[
        'user_id' => $userId,
        'title' => 'Application Record Ready',
        'message' => 'Your demo scholarship record is available for testing and review.',
        'link_path' => 'student/dashboard',
        'is_read' => 0,
        'created_at' => $applicationBlueprint['updated_at'],
    ]];

    switch ($status) {
        case 'Submitted':
            $notifications[] = notificationRow($userId, 'Application Submitted', 'Your scholarship application was submitted successfully and is now waiting for review.', 'student/dashboard', 0, $applicationBlueprint['updated_at']);
            break;
        case 'Initial_Review':
            $notifications[] = notificationRow($userId, 'Application Under Review', 'Your submitted documents are currently being checked by the scholarship office.', 'student/dashboard', 0, $applicationBlueprint['updated_at']);
            break;
        case 'Pending_Resubmission':
            $notifications[] = notificationRow($userId, 'Document Update', 'One or more of your submitted documents were rejected. Please review the remarks and resubmit the required documents.', 'student/dashboard', 0, $applicationBlueprint['updated_at']);
            break;
        case 'For_Interview':
            $notifications[] = notificationRow(
                $userId,
                $applicationBlueprint['interview_batch_id'] !== null ? 'Interview Schedule Ready' : 'Ready for Interview',
                $applicationBlueprint['interview_batch_id'] !== null
                    ? 'Your interview schedule has been assigned. Please check the dashboard for batch details.'
                    : 'Your application passed document review and is now waiting to be scheduled for interview.',
                'student/dashboard',
                0,
                $applicationBlueprint['updated_at']
            );
            break;
        case 'Not_Eligible':
            $notifications[] = notificationRow($userId, 'Interview Result', 'Your application was marked not eligible after the interview evaluation.', 'student/dashboard', 0, $applicationBlueprint['updated_at']);
            break;
        case 'Eligible_Awaiting_SOA':
            $notifications[] = notificationRow($userId, 'SOA Submission Required', 'You passed the interview. Please upload your Statement of Account before the deadline.', 'student/dashboard', 0, $applicationBlueprint['updated_at']);
            break;
        case 'SOA_Under_Review':
            $notifications[] = notificationRow($userId, 'SOA Submitted', 'Your Statement of Account was submitted successfully and is now waiting for review.', 'student/dashboard', 0, $applicationBlueprint['updated_at']);
            break;
        case 'SOA_Resubmission_Required':
            $notifications[] = notificationRow($userId, 'Document Update', 'Your Statement of Account was rejected. Please review the remarks and submit a corrected SOA.', 'student/dashboard', 0, $applicationBlueprint['updated_at']);
            break;
        case 'Approved_Pending_Payroll':
            $notifications[] = notificationRow(
                $userId,
                $applicationBlueprint['payout_batch_id'] !== null ? 'Payout Schedule Ready' : 'Approved Pending Payroll',
                $applicationBlueprint['payout_batch_id'] !== null
                    ? 'Your scholarship payout was scheduled. Please check your dashboard for release details.'
                    : 'Your scholarship was approved and is waiting for payout scheduling.',
                'student/dashboard',
                $offset % 2,
                $applicationBlueprint['updated_at']
            );
            break;
        case 'Approved_Finished':
            $notifications[] = notificationRow($userId, 'Scholarship Released', 'Your approved scholarship record has been completed successfully.', 'student/history', $offset % 2, $applicationBlueprint['updated_at']);
            break;
        case 'Forfeited':
            $notifications[] = notificationRow($userId, 'Application Forfeited', 'Your application was forfeited because the required next step was not completed before the deadline.', 'student/dashboard', 0, $applicationBlueprint['updated_at']);
            break;
    }

    return $notifications;
}

function notificationRow(int $userId, string $title, string $message, string $linkPath, int $isRead, string $createdAt): array
{
    return [
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'link_path' => $linkPath,
        'is_read' => $isRead,
        'created_at' => $createdAt,
    ];
}

function buildCaseNote(string $status, int $applicationId, int $staffId, int $adminId, array $profile, array $applicationBlueprint): ?array
{
    return match ($status) {
        'Pending_Resubmission' => [
            'application_id' => $applicationId,
            'user_id' => $staffId,
            'note_text' => 'Requested resubmission of unclear initial requirement for ' . $profile['last_name'] . '.',
            'created_at' => $applicationBlueprint['updated_at'],
        ],
        'SOA_Resubmission_Required' => [
            'application_id' => $applicationId,
            'user_id' => $staffId,
            'note_text' => 'SOA amount needs correction before final approval.',
            'created_at' => $applicationBlueprint['updated_at'],
        ],
        'Not_Eligible' => [
            'application_id' => $applicationId,
            'user_id' => $staffId,
            'note_text' => 'Interview result recorded as ' . ($applicationBlueprint['interview_result'] ?? 'Failed') . '.',
            'created_at' => $applicationBlueprint['updated_at'],
        ],
        'Approved_Pending_Payroll', 'Approved_Finished' => [
            'application_id' => $applicationId,
            'user_id' => $adminId,
            'note_text' => 'Approved scholarship amount set to PHP ' . number_format((float) ($applicationBlueprint['final_grant_amount'] ?? 0), 2) . '.',
            'created_at' => $applicationBlueprint['updated_at'],
        ],
        'Forfeited' => [
            'application_id' => $applicationId,
            'user_id' => $adminId,
            'note_text' => 'Application tagged forfeited after missing the SOA deadline.',
            'created_at' => $applicationBlueprint['updated_at'],
        ],
        default => null,
    };
}

function buildSiblings(int $sequence): array
{
    return [
        [
            'name' => 'Angela ' . LAST_NAMES[($sequence + 2) % count(LAST_NAMES)],
            'age' => (string) (12 + ($sequence % 6)),
            'education' => 'High School',
            'occupation' => 'Student',
            'income' => '0.00',
        ],
        [
            'name' => 'Jerome ' . LAST_NAMES[($sequence + 5) % count(LAST_NAMES)],
            'age' => (string) (18 + ($sequence % 5)),
            'education' => 'College',
            'occupation' => 'Part-time Worker',
            'income' => number_format(1200 + ($sequence % 4) * 300, 2, '.', ''),
        ],
    ];
}

function buildEducation(array $profile, int $sequence): array
{
    return [
        [
            'level' => 'Elementary',
            'school' => 'San Enrique Central School',
            'course' => '',
            'year' => (string) (2016 + ($sequence % 3)),
            'honors' => $sequence % 4 === 0 ? 'With Honors' : '',
        ],
        [
            'level' => 'High School',
            'school' => 'San Enrique National High School',
            'course' => '',
            'year' => (string) (2020 + ($sequence % 3)),
            'honors' => $sequence % 5 === 0 ? 'With High Honors' : '',
        ],
        [
            'level' => 'College',
            'school' => $profile['school_name'],
            'course' => $profile['course'],
            'year' => (string) (2024 + ($sequence % 2)),
            'honors' => '',
        ],
    ];
}

function buildGrants(int $sequence): array
{
    if ($sequence % 4 === 0) {
        return [
            ['program' => 'School Academic Discount', 'period' => 'Current Semester'],
        ];
    }

    return [
        ['program' => 'None', 'period' => 'N/A'],
    ];
}

function applicationCreatedAt(int $sequence, string $status, int $offset): DateTimeImmutable
{
    $statusBaseDays = [
        'Submitted' => 2,
        'Initial_Review' => 7,
        'Pending_Resubmission' => 15,
        'For_Interview' => 20,
        'Not_Eligible' => 28,
        'Eligible_Awaiting_SOA' => 30,
        'SOA_Under_Review' => 34,
        'SOA_Resubmission_Required' => 38,
        'Approved_Pending_Payroll' => 46,
        'Approved_Finished' => 60,
        'Forfeited' => 55,
    ];

    $daysAgo = $statusBaseDays[$status] + ($offset * 2);
    return (new DateTimeImmutable('2026-03-22 09:00:00'))->modify('-' . $daysAgo . ' days');
}

function formatDateTime(DateTimeImmutable $dateTime): string
{
    return $dateTime->format('Y-m-d H:i:s');
}

function cleanupCreatedFiles(array $createdFiles): void
{
    foreach ($createdFiles as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}

function createPhotoAsset(array $profile, array &$createdFiles): string
{
    $path = newStorageFilePath('photos', 'id_photo_', 'png');
    $initials = mb_substr((string) $profile['first_name'], 0, 1) . mb_substr((string) $profile['last_name'], 0, 1);
    renderPngCard(
        $path,
        480,
        480,
        [
            '2x2 ID Picture',
            trim($profile['first_name'] . ' ' . $profile['last_name']),
            $profile['address_barangay'],
            $profile['school_name'],
        ],
        $initials,
        false
    );
    $createdFiles[] = $path;
    return $path;
}

function createSignatureAsset(array $profile, int $sequence, array &$createdFiles): string
{
    $path = newStorageFilePath('signatures', 'signature_', 'png');
    renderSignaturePng($path, trim($profile['first_name'] . ' ' . $profile['last_name']), $sequence);
    $createdFiles[] = $path;
    return $path;
}

function createDocumentAsset(string $documentType, array $profile, string $documentStatus, array &$createdFiles): string
{
    $prefix = match ($documentType) {
        'Grades' => 'grades_',
        'Barangay Residency' => 'residency_',
        default => 'soa_',
    };
    $path = newStorageFilePath('documents', $prefix, 'png');
    renderPngCard(
        $path,
        900,
        620,
        [
            $documentType . ' Document',
            trim($profile['first_name'] . ' ' . $profile['last_name']),
            $profile['address_barangay'],
            $profile['school_name'],
            $profile['course'],
            'Status: ' . $documentStatus,
        ],
        'LGU',
        true
    );
    $createdFiles[] = $path;
    return $path;
}

function newStorageFilePath(string $directory, string $prefix, string $extension): string
{
    return storage_path($directory) . DIRECTORY_SEPARATOR . $prefix . uniqid('', true) . '.' . $extension;
}

function renderPngCard(string $path, int $width, int $height, array $lines, string $badge, bool $wide): void
{
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Unable to create GD image.');
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $gray = imagecolorallocate($image, 230, 230, 230);

    imagefill($image, 0, 0, $white);
    imagerectangle($image, 12, 12, $width - 13, $height - 13, $black);
    imagerectangle($image, 24, 24, $width - 25, $height - 25, $black);

    if ($wide) {
        imagefilledrectangle($image, 36, 40, $width - 36, 105, $gray);
        imagerectangle($image, 36, 40, $width - 36, 105, $black);
        imagestring($image, 5, 48, 60, $badge, $black);
    } else {
        imagefilledellipse($image, (int) floor($width / 2), 130, 150, 150, $gray);
        imageellipse($image, (int) floor($width / 2), 130, 150, 150, $black);
        imagestring($image, 5, (int) floor($width / 2) - 18, 120, $badge, $black);
    }

    $y = $wide ? 145 : 235;
    foreach ($lines as $index => $line) {
        imagestring($image, $index === 0 ? 5 : 4, 48, $y, $line, $black);
        $y += $index === 0 ? 42 : 32;
    }

    if (!imagepng($image, $path)) {
        imagedestroy($image);
        throw new RuntimeException('Unable to write image file: ' . $path);
    }

    imagedestroy($image);
}

function renderSignaturePng(string $path, string $fullName, int $sequence): void
{
    $width = 760;
    $height = 220;
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Unable to create signature image.');
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $white);
    imagerectangle($image, 12, 12, $width - 13, $height - 13, $black);

    $startX = 40;
    $baseY = 100;
    for ($i = 0; $i < 8; $i++) {
        $x1 = $startX + ($i * 80);
        $y1 = $baseY + (($i % 2 === 0 ? -1 : 1) * (($sequence + $i) % 18));
        $x2 = $x1 + 40;
        $y2 = $baseY + (($i % 3 === 0 ? 1 : -1) * (($sequence + $i * 2) % 22));
        imageline($image, $x1, $y1, $x2, $y2, $black);
        imagearc($image, $x2 + 20, $baseY, 60, 28, 180, 360, $black);
    }

    imageline($image, 40, 170, 710, 170, $black);
    imagestring($image, 4, 40, 182, $fullName, $black);

    if (!imagepng($image, $path)) {
        imagedestroy($image);
        throw new RuntimeException('Unable to write signature image: ' . $path);
    }

    imagedestroy($image);
}
