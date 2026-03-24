<?php

namespace App\Controllers;

use App\Config\Database;
use App\Models\ApplicationPeriod;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Support\OfficeExporter;
use App\Support\Sms;
use App\Models\User;
use App\Support\Validation;
use App\Support\ValidationException;
use Exception;

class AdminController
{

    public function __construct()
    {
        // Strict security: ONLY the Admin can access these routes
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
            header('Location: ' . \base_url('login'));
            exit;
        }
    }

    // ---------------------------------------------------------
    // THE MAYOR's EXECUTIVE DASHBOARD
    // ---------------------------------------------------------
    public function dashboard()
    {
        $db = Database::connect();

        // Data for Chart.js: Count scholars per Barangay (Equitable Distribution)
        $brgyData = $db->query("
            SELECT p.address_barangay, COUNT(a.id) as total_scholars 
            FROM applications a 
            JOIN student_profiles p ON a.student_id = p.id 
            WHERE a.status = 'Approved_Pending_Payroll' OR a.status = 'Approved_Finished'
            GROUP BY p.address_barangay
        ")->fetchAll();

        // Data for Chart.js: Public vs Private School distribution
        $schoolData = $db->query("
            SELECT p.school_type, COUNT(a.id) as total 
            FROM applications a 
            JOIN student_profiles p ON a.student_id = p.id 
            WHERE a.status IN ('Approved_Pending_Payroll', 'Approved_Finished')
            GROUP BY p.school_type
        ")->fetchAll();

        // Calculate total budget consumed by checking verified SOAs
        $budgetQuery = $db->query("SELECT SUM(final_grant_amount) as total_budget FROM applications WHERE status IN ('Approved_Pending_Payroll', 'Approved_Finished')");
        $totalBudget = $budgetQuery->fetchColumn();

        // Pass these variables to the View to be rendered in the charts
        require __DIR__ . '/../../views/admin/dashboard.php';
    }

    // ---------------------------------------------------------
    // MASTER PAYROLL GENERATOR (COA COMPLIANT)
    // ---------------------------------------------------------
    public function generatePayroll()
    {
        $db = Database::connect();

        try {
            $filterType = Validation::enum($_GET['school_type'] ?? 'Public', ['Public', 'Private'], 'School type');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/dashboard', 'error', $e->getMessage());
        }

        // Fetch approved scholars waiting for payout for the selected school type.
        $stmt = $db->prepare("
            SELECT p.last_name, p.first_name, p.middle_name, p.address_barangay, p.school_name, a.final_grant_amount 
            FROM applications a 
            JOIN student_profiles p ON a.student_id = p.id 
            WHERE a.status = 'Approved_Pending_Payroll' AND p.school_type = :type
            ORDER BY p.last_name ASC
        ");
        $stmt->execute(['type' => $filterType]);
        $approvedScholars = $stmt->fetchAll();

        // Load the HTML view that will be formatted by CSS Print Media Queries
        // The view will loop through $approvedScholars and create the table rows
        require __DIR__ . '/../../views/printables/master_payroll.php';
    }

    // ---------------------------------------------------------
    // FINAL APPROVAL & PAYOUT BATCHING
    // ---------------------------------------------------------
    public function finalApproval()
    {
        $db = Database::connect();
        $schoolTypeFilter = trim((string) ($_GET['school_type'] ?? ''));
        $barangayFilter = trim((string) ($_GET['barangay'] ?? ''));

        $conditions = [
            "a.status = 'Approved_Pending_Payroll'",
            'a.payout_batch_id IS NULL',
        ];
        $params = [];

        if ($schoolTypeFilter !== '') {
            $conditions[] = 'p.school_type = :school_type';
            $params['school_type'] = $schoolTypeFilter;
        }

        if ($barangayFilter !== '') {
            $conditions[] = 'p.address_barangay = :barangay';
            $params['barangay'] = $barangayFilter;
        }

        // Fetch students who have verified SOAs but are NOT yet assigned to a payout date
        $stmt = $db->prepare("
            SELECT a.id as application_id, p.first_name, p.last_name, p.address_barangay, p.school_name, p.school_type, a.final_grant_amount 
            FROM applications a 
            JOIN student_profiles p ON a.student_id = p.id 
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC
        ");
        $stmt->execute($params);
        $pendingPayouts = $stmt->fetchAll();

        $totalReadyStmt = $db->query("
            SELECT COUNT(*)
            FROM applications a
            WHERE a.status = 'Approved_Pending_Payroll' AND a.payout_batch_id IS NULL
        ");
        $totalReadyPayoutCount = (int) $totalReadyStmt->fetchColumn();

        $schoolTypeOptions = $db->query("
            SELECT DISTINCT p.school_type
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE a.status = 'Approved_Pending_Payroll' AND a.payout_batch_id IS NULL
            ORDER BY p.school_type ASC
        ")->fetchAll(\PDO::FETCH_COLUMN);
        $barangayOptions = $db->query("
            SELECT DISTINCT p.address_barangay
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE a.status = 'Approved_Pending_Payroll' AND a.payout_batch_id IS NULL
            ORDER BY p.address_barangay ASC
        ")->fetchAll(\PDO::FETCH_COLUMN);
        if ($schoolTypeFilter !== '' && !in_array($schoolTypeFilter, $schoolTypeOptions, true)) {
            $schoolTypeOptions[] = $schoolTypeFilter;
            sort($schoolTypeOptions, SORT_NATURAL | SORT_FLAG_CASE);
        }
        if ($barangayFilter !== '' && !in_array($barangayFilter, $barangayOptions, true)) {
            $barangayOptions[] = $barangayFilter;
            sort($barangayOptions, SORT_NATURAL | SORT_FLAG_CASE);
        }
        $payoutFilterSummary = $this->buildBatchFilterSummary($schoolTypeFilter, $barangayFilter, 'scholar', 'scholars');
        $activePayoutFilters = [];
        if ($schoolTypeFilter !== '') {
            $activePayoutFilters[] = ['label' => 'School Type', 'value' => $schoolTypeFilter];
        }
        if ($barangayFilter !== '') {
            $activePayoutFilters[] = ['label' => 'Barangay', 'value' => $barangayFilter];
        }
        $pendingBySchoolType = [];
        $pendingByBarangay = [];
        foreach ($pendingPayouts as $scholar) {
            $schoolTypeKey = trim((string) ($scholar['school_type'] ?? 'Unspecified'));
            $barangayKey = trim((string) ($scholar['address_barangay'] ?? 'Unspecified'));
            $pendingBySchoolType[$schoolTypeKey] = ($pendingBySchoolType[$schoolTypeKey] ?? 0) + 1;
            $pendingByBarangay[$barangayKey] = ($pendingByBarangay[$barangayKey] ?? 0) + 1;
        }
        arsort($pendingBySchoolType);
        arsort($pendingByBarangay);

        // Fetch existing upcoming payout batches
        $stmtBatches = $db->query("
            SELECT b.*, COUNT(a.id) as total_students, SUM(a.final_grant_amount) as total_amount
            FROM batches b 
            LEFT JOIN applications a ON b.id = a.payout_batch_id 
            WHERE b.batch_type = 'Payout'
            GROUP BY b.id
            ORDER BY b.scheduled_date DESC
        ");
        $payoutBatches = $stmtBatches->fetchAll();

        $batchSummaries = [];
        $batchParticipants = [];
        if ($payoutBatches !== []) {
            $batchIds = array_map(static fn($batch) => (int) ($batch['id'] ?? 0), $payoutBatches);
            $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
            $participantsStmt = $db->prepare("
                SELECT
                    a.payout_batch_id,
                    a.final_grant_amount,
                    p.address_barangay,
                    p.school_type
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                WHERE a.payout_batch_id IN ($placeholders)
            ");
            $participantsStmt->execute($batchIds);

            foreach ($participantsStmt->fetchAll() as $participant) {
                $batchId = (int) ($participant['payout_batch_id'] ?? 0);
                if (!isset($batchSummaries[$batchId])) {
                    $batchSummaries[$batchId] = [
                        'barangays' => [],
                        'school_types' => [],
                        'total_amount' => 0.0,
                    ];
                }

                $batchSummaries[$batchId]['total_amount'] += (float) ($participant['final_grant_amount'] ?? 0);
                $barangayKey = trim((string) ($participant['address_barangay'] ?? 'Unspecified'));
                $schoolTypeKey = trim((string) ($participant['school_type'] ?? 'Unspecified'));
                $batchSummaries[$batchId]['barangays'][$barangayKey] = ($batchSummaries[$batchId]['barangays'][$barangayKey] ?? 0) + 1;
                $batchSummaries[$batchId]['school_types'][$schoolTypeKey] = ($batchSummaries[$batchId]['school_types'][$schoolTypeKey] ?? 0) + 1;
            }

            foreach ($batchSummaries as &$summary) {
                arsort($summary['barangays']);
                arsort($summary['school_types']);
            }
            unset($summary);

            foreach ($payoutBatches as $batch) {
                $batchId = (int) ($batch['id'] ?? 0);
                if ($batchId <= 0) {
                    continue;
                }

                $batchParticipants[$batchId] = $this->fetchPayoutBatchScholars($db, $batchId);
            }
        }

        require __DIR__ . '/../../views/admin/final_approval.php';
    }

    public function accountRecovery()
    {
        require __DIR__ . '/../../views/admin/account_recovery.php';
    }

    public function accountRecoveryLookup()
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'message' => 'Unauthorized.',
            ]);
            exit;
        }

        $lookupPhone = trim((string) ($_GET['lookup_phone_number'] ?? ''));
        $lookupEmail = trim((string) ($_GET['lookup_email'] ?? ''));

        if ($lookupPhone === '' && $lookupEmail === '') {
            echo json_encode([
                'ok' => true,
                'match' => null,
            ]);
            exit;
        }

        $normalizedPhone = null;
        $normalizedEmail = null;

        if ($lookupPhone !== '') {
            $digitsOnlyPhone = preg_replace('/\D+/', '', $lookupPhone) ?? '';
            if (strlen($digitsOnlyPhone) === 11) {
                $normalizedPhone = $digitsOnlyPhone;
            } else {
                echo json_encode([
                    'ok' => true,
                    'match' => null,
                    'message' => 'Enter the full 11-digit mobile number to search.',
                ]);
                exit;
            }
        }

        if ($lookupEmail !== '') {
            $candidateEmail = filter_var($lookupEmail, FILTER_SANITIZE_EMAIL);
            if ($candidateEmail !== false && filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
                $normalizedEmail = strtolower((string) $candidateEmail);
            } else {
                echo json_encode([
                    'ok' => true,
                    'match' => null,
                    'message' => 'Enter a valid email address to search.',
                ]);
                exit;
            }
        }

        $match = $this->findRecoveryApplicant($normalizedPhone, $normalizedEmail);

        echo json_encode([
            'ok' => true,
            'match' => $match,
        ]);
        exit;
    }

    public function settings()
    {
        $settings = ApplicationPeriod::getCurrent();
        $isCurrentlyOpen = ApplicationPeriod::isAcceptingApplications($settings);
        $activePeriodLabel = ApplicationPeriod::activePeriodLabel($settings);
        $soaDeadlinePolicyLabel = ApplicationPeriod::soaDeadlinePolicyLabel($settings);
        $today = date('Y-m-d');
        $applicationStartDate = trim((string) ($settings['application_start_date'] ?? ''));
        $applicationEndDate = trim((string) ($settings['application_end_date'] ?? ''));
        $soaDeadline = trim((string) ($settings['soa_deadline'] ?? ''));
        $soaDeadlineMode = (string) ($settings['soa_deadline_mode'] ?? 'Manual');
        $soaDeadlineDaysAfterPassed = (int) ($settings['soa_deadline_days_after_passed'] ?? 0);
        $isSwitchEnabled = (int) ($settings['is_open'] ?? 0) === 1;

        if ($applicationStartDate === '' || $applicationEndDate === '') {
            $applicationStatusHeading = 'Application period not configured';
            $applicationStatusCopy = 'Set the school year, semester, and submission dates before opening applications.';
        } elseif (!$isSwitchEnabled) {
            $applicationStatusHeading = 'Applications are manually closed';
            $applicationStatusCopy = 'Student submissions are blocked because the application-period switch is turned off.';
        } elseif ($today < $applicationStartDate) {
            $applicationStatusHeading = 'Applications have not opened yet';
            $applicationStatusCopy = 'Student submissions will open on ' . $applicationStartDate . '.';
        } elseif ($today > $applicationEndDate) {
            $applicationStatusHeading = 'Application window has ended';
            $applicationStatusCopy = 'Student submissions closed on ' . $applicationEndDate . ', but staff and admin processing may continue.';
        } else {
            $applicationStatusHeading = 'Applications are currently open';
            $applicationStatusCopy = 'Students may submit applications until ' . $applicationEndDate . '.';
        }

        $settingsWarnings = [];
        if ($soaDeadlineMode === 'Manual' && $soaDeadline !== '' && $soaDeadline < $today) {
            $settingsWarnings[] = 'The global SOA deadline has already passed.';
        }
        if ($soaDeadlineMode === 'Manual' && $soaDeadline !== '' && $applicationEndDate !== '' && $soaDeadline < $applicationEndDate) {
            $settingsWarnings[] = 'The SOA deadline is earlier than the application end date. This is unusual for the current workflow.';
        }
        if ($soaDeadlineMode === 'AfterPassed' && $soaDeadlineDaysAfterPassed <= 0) {
            $settingsWarnings[] = 'The rolling SOA deadline policy needs a valid number of days after the interview is passed.';
        }

        require __DIR__ . '/../../views/admin/settings.php';
    }

    public function auditLogs()
    {
        $filters = [
            'actor_role' => trim((string) ($_GET['actor_role'] ?? '')),
            'action' => trim((string) ($_GET['action'] ?? '')),
            'entity_type' => trim((string) ($_GET['entity_type'] ?? '')),
        ];
        $logs = AuditLog::latest($filters, 200);
        $actorRoleOptions = AuditLog::distinctValues('actor_role');
        $actionOptions = AuditLog::distinctValues('action');
        $entityTypeOptions = AuditLog::distinctValues('entity_type');

        require __DIR__ . '/../../views/admin/audit_logs.php';
    }

    public function exceptions()
    {
        $db = Database::connect();
        $settings = ApplicationPeriod::getCurrent();
        ApplicationPeriod::syncOverdueSoaStatuses($db, $settings);
        $overdueSoa = [];
        $stmt = $db->query("
            SELECT
                a.id AS application_id,
                a.status,
                a.interview_result_at,
                a.soa_deadline,
                CONCAT(p.last_name, ', ', p.first_name) AS applicant_name,
                p.address_barangay,
                p.school_name
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE a.status IN ('Eligible_Awaiting_SOA', 'SOA_Resubmission_Required', 'SOA_Overdue')
            ORDER BY p.last_name ASC, p.first_name ASC
        ");
        foreach ($stmt->fetchAll() as $soaApplication) {
            $resolvedDeadline = ApplicationPeriod::resolveApplicationSoaDeadline($settings, $soaApplication);
            if ($resolvedDeadline !== '' && $resolvedDeadline < date('Y-m-d')) {
                $soaApplication['resolved_soa_deadline'] = $resolvedDeadline;
                $overdueSoa[] = $soaApplication;
            }
        }

        $pendingInterviewResults = $db->query("
            SELECT
                a.id AS application_id,
                CONCAT(p.last_name, ', ', p.first_name) AS applicant_name,
                b.batch_name,
                b.scheduled_date,
                b.venue
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            JOIN batches b ON a.interview_batch_id = b.id
            WHERE b.batch_type = 'Interview'
              AND b.scheduled_date < NOW()
              AND a.interview_result IS NULL
            ORDER BY b.scheduled_date ASC, p.last_name ASC, p.first_name ASC
        ")->fetchAll();

        $rejectedDocuments = $db->query("
            SELECT
                d.application_id,
                CONCAT(p.last_name, ', ', p.first_name) AS applicant_name,
                d.document_type,
                d.rejection_remarks,
                d.updated_at
            FROM documents d
            JOIN applications a ON d.application_id = a.id
            JOIN student_profiles p ON a.student_id = p.id
            WHERE d.status = 'Rejected'
            ORDER BY d.updated_at DESC, d.id DESC
        ")->fetchAll();

        $inactiveUsers = $db->query("
            SELECT id, role, phone_number, email, created_at
            FROM users
            WHERE is_active = 0
            ORDER BY created_at DESC
        ")->fetchAll();

        require __DIR__ . '/../../views/admin/exceptions.php';
    }

    public function announcements()
    {
        $announcements = Announcement::all();
        require __DIR__ . '/../../views/admin/announcements.php';
    }

    public function saveAnnouncement()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $announcementId = filter_var($_POST['announcement_id'] ?? null, FILTER_VALIDATE_INT);
            $title = Validation::requiredString($_POST['title'] ?? '', 'Title', 180);
            $body = Validation::requiredString($_POST['body'] ?? '', 'Body', 5000);
            $targetAudience = Validation::enum($_POST['target_audience'] ?? '', ['Public', 'Students', 'Both'], 'Target audience');
            $isPublished = isset($_POST['is_published']) ? 1 : 0;

            $payload = [
                'title' => $title,
                'body' => $body,
                'target_audience' => $targetAudience,
                'is_published' => $isPublished,
                'published_at' => $isPublished ? date('Y-m-d H:i:s') : null,
                'created_by' => (int) ($_SESSION['user_id'] ?? 0),
            ];

            if ($announcementId) {
                Announcement::update($announcementId, $payload);
                AuditLog::recordCurrentUser('announcement.updated', 'announcement', $announcementId, 'Updated an announcement.', [
                    'target_audience' => $targetAudience,
                    'is_published' => $isPublished,
                ]);
                redirect_with_flash('admin/announcements', 'success', 'Announcement updated successfully.');
            }

            $newId = Announcement::create($payload);
            AuditLog::recordCurrentUser('announcement.created', 'announcement', $newId, 'Created an announcement.', [
                'target_audience' => $targetAudience,
                'is_published' => $isPublished,
            ]);
            redirect_with_flash('admin/announcements', 'success', 'Announcement published successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/announcements', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to save announcement: ' . $e->getMessage());
            redirect_with_flash('admin/announcements', 'error', 'Unable to save the announcement right now.');
        }
    }

    public function toggleAnnouncement()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $announcementId = filter_var($_POST['announcement_id'] ?? null, FILTER_VALIDATE_INT);
            $isPublished = isset($_POST['is_published']) ? 1 : 0;

            if (!$announcementId) {
                throw new ValidationException('Invalid announcement.');
            }

            Announcement::togglePublished($announcementId, (bool) $isPublished);
            AuditLog::recordCurrentUser('announcement.toggled', 'announcement', $announcementId, 'Changed announcement publish status.', [
                'is_published' => $isPublished,
            ]);
            redirect_with_flash('admin/announcements', 'success', 'Announcement visibility updated.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/announcements', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to toggle announcement: ' . $e->getMessage());
            redirect_with_flash('admin/announcements', 'error', 'Unable to update the announcement right now.');
        }
    }

    public function users()
    {
        $users = User::allWithStats();
        require __DIR__ . '/../../views/admin/users.php';
    }

    public function createUser()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $role = Validation::enum($_POST['role'] ?? '', ['Staff', 'Admin'], 'Role');
            $firstName = Validation::requiredString($_POST['first_name'] ?? '', 'First name', 100);
            $lastName = Validation::requiredString($_POST['last_name'] ?? '', 'Last name', 100);
            $phone = Validation::phone($_POST['phone_number'] ?? '');
            $emailInput = trim((string) ($_POST['email'] ?? ''));
            $email = $emailInput !== '' ? Validation::email($emailInput) : null;
            $password = Validation::password($_POST['password'] ?? '');

            if (User::findByPhone($phone)) {
                throw new ValidationException('That mobile number is already registered.');
            }

            if ($email !== null && User::findByEmail($email)) {
                throw new ValidationException('That email address is already registered.');
            }

            $userId = User::createAccount($role, $firstName, $lastName, $phone, $email, password_hash($password, PASSWORD_BCRYPT), 1);

            AuditLog::recordCurrentUser('user.created', 'user', $userId, 'Created a new internal user account.', [
                'role' => $role,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => $phone,
                'email' => $email,
            ]);
            redirect_with_flash('admin/users', 'success', ucfirst(strtolower($role)) . ' account created successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/users', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to create user: ' . $e->getMessage());
            redirect_with_flash('admin/users', 'error', 'Unable to create the user account right now.');
        }
    }

    public function updateUserStatus()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (!$userId) {
                throw new ValidationException('Invalid user account.');
            }

            $user = User::findById($userId);
            if (!$user || !in_array((string) ($user['role'] ?? ''), ['Staff', 'Admin'], true)) {
                throw new ValidationException('Only internal staff and admin accounts can be managed here.');
            }

            if ($userId === (int) ($_SESSION['user_id'] ?? 0) && $isActive === 0) {
                throw new ValidationException('You cannot deactivate your own account while logged in.');
            }

            User::setActiveStatus($userId, (bool) $isActive);
            AuditLog::recordCurrentUser('user.status_updated', 'user', $userId, 'Updated user active status.', [
                'is_active' => $isActive,
            ]);
            redirect_with_flash('admin/users', 'success', 'User status updated successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/users', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to update user status: ' . $e->getMessage());
            redirect_with_flash('admin/users', 'error', 'Unable to update the user status right now.');
        }
    }

    public function resetUserPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $password = Validation::password($_POST['new_password'] ?? '');

            if (!$userId) {
                throw new ValidationException('Invalid user account.');
            }

            $user = User::findById($userId);
            if (!$user || !in_array((string) ($user['role'] ?? ''), ['Staff', 'Admin'], true)) {
                throw new ValidationException('Only internal staff and admin accounts can be managed here.');
            }

            User::updatePassword($userId, password_hash($password, PASSWORD_BCRYPT));
            User::clearOtp($userId);

            AuditLog::recordCurrentUser('user.password_reset', 'user', $userId, 'Admin reset a user password.');
            redirect_with_flash('admin/users', 'success', 'User password reset successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/users', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to reset user password: ' . $e->getMessage());
            redirect_with_flash('admin/users', 'error', 'Unable to reset the user password right now.');
        }
    }

    public function saveSettings()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $actionMode = Validation::enum($_POST['action_mode'] ?? 'open_new', ['open_new', 'extend_current', 'close_submissions'], 'Action');
            $currentSettings = ApplicationPeriod::getCurrent();

            if ($actionMode === 'close_submissions') {
                if (trim((string) ($currentSettings['school_year'] ?? '')) === '' || trim((string) ($currentSettings['semester'] ?? '')) === '') {
                    throw new ValidationException('There is no active application period to close yet.');
                }

                ApplicationPeriod::save([
                    'school_year' => (string) ($currentSettings['school_year'] ?? ''),
                    'semester' => (string) ($currentSettings['semester'] ?? ''),
                    'application_start_date' => (string) ($currentSettings['application_start_date'] ?? ''),
                    'application_end_date' => (string) ($currentSettings['application_end_date'] ?? ''),
                    'soa_deadline_mode' => (string) ($currentSettings['soa_deadline_mode'] ?? 'Manual'),
                    'soa_deadline_days_after_passed' => $currentSettings['soa_deadline_days_after_passed'] ?? null,
                    'soa_deadline' => (string) ($currentSettings['soa_deadline'] ?? ''),
                    'auto_archive_completed_records' => 1,
                    'is_open' => 0,
                ]);

                AuditLog::recordCurrentUser(
                    'settings.submissions_closed',
                    'application_settings',
                    1,
                    'Closed student submissions for the current application period.'
                );

                redirect_with_flash('admin/settings', 'success', 'Student submissions are now closed for the current application period.');
            }

            if ($actionMode === 'extend_current') {
                $currentSchoolYear = trim((string) ($currentSettings['school_year'] ?? ''));
                $currentSemester = trim((string) ($currentSettings['semester'] ?? ''));
                $currentStartDate = trim((string) ($currentSettings['application_start_date'] ?? ''));
                $currentEndDate = trim((string) ($currentSettings['application_end_date'] ?? ''));

                if ($currentSchoolYear === '' || $currentSemester === '' || $currentStartDate === '' || $currentEndDate === '') {
                    throw new ValidationException('Set up a current application period first before extending the submission deadline.');
                }

                $newEndDate = Validation::date($_POST['extended_application_end_date'] ?? '', 'New application end date');
                if ($newEndDate < $currentEndDate) {
                    throw new ValidationException('The new application end date must be later than or equal to the current end date.');
                }

                ApplicationPeriod::save([
                    'school_year' => $currentSchoolYear,
                    'semester' => $currentSemester,
                    'application_start_date' => $currentStartDate,
                    'application_end_date' => $newEndDate,
                    'soa_deadline_mode' => (string) ($currentSettings['soa_deadline_mode'] ?? 'Manual'),
                    'soa_deadline_days_after_passed' => $currentSettings['soa_deadline_days_after_passed'] ?? null,
                    'soa_deadline' => (string) ($currentSettings['soa_deadline'] ?? ''),
                    'auto_archive_completed_records' => 1,
                    'is_open' => 1,
                ]);

                AuditLog::recordCurrentUser(
                    'settings.deadline_extended',
                    'application_settings',
                    1,
                    'Extended the student submission deadline for the current application period.',
                    [
                        'old_application_end_date' => $currentEndDate,
                        'new_application_end_date' => $newEndDate,
                    ]
                );

                redirect_with_flash('admin/settings', 'success', 'The student submission deadline was extended successfully.');
            }

            $schoolYear = Validation::requiredString($_POST['school_year'] ?? '', 'School year', 20);
            $semester = Validation::enum($_POST['semester'] ?? '', ['1st Semester', '2nd Semester'], 'Semester');
            $startDate = Validation::date($_POST['application_start_date'] ?? '', 'Application start date');
            $endDate = Validation::date($_POST['application_end_date'] ?? '', 'Application end date');
            $soaDeadlineMode = Validation::enum($_POST['soa_deadline_mode'] ?? '', ['Manual', 'AfterPassed'], 'SOA deadline policy');
            $soaDeadlineDaysAfterPassed = null;
            if ($soaDeadlineMode === 'AfterPassed') {
                $soaDeadlineDaysAfterPassed = filter_var($_POST['soa_deadline_days_after_passed'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 60],
                ]);

                if ($soaDeadlineDaysAfterPassed === false) {
                    throw new ValidationException('SOA days after passed interview must be a whole number from 1 to 60.');
                }
            }
            $soaDeadlineRaw = trim((string) ($_POST['soa_deadline'] ?? ''));
            $soaDeadline = ($soaDeadlineMode === 'Manual' && $soaDeadlineRaw !== '') ? Validation::date($soaDeadlineRaw, 'SOA deadline') : '';
            $isOpen = isset($_POST['is_open']) ? 1 : 0;

            if ($endDate < $startDate) {
                throw new ValidationException('Application end date must be on or after the start date.');
            }

            if (!preg_match('/^\d{4}-\d{4}$/', $schoolYear)) {
                throw new ValidationException('School year must follow the format YYYY-YYYY, for example 2025-2026.');
            }

            [$startYear, $endYear] = array_map('intval', explode('-', $schoolYear));
            if ($endYear !== ($startYear + 1)) {
                throw new ValidationException('School year must use two consecutive years, for example 2025-2026.');
            }

            if ($soaDeadlineMode === 'Manual' && $soaDeadline !== '' && $soaDeadline < $startDate) {
                throw new ValidationException('SOA deadline must be on or after the application start date.');
            }

            ApplicationPeriod::save([
                'school_year' => $schoolYear,
                'semester' => $semester,
                'application_start_date' => $startDate,
                'application_end_date' => $endDate,
                'soa_deadline_mode' => $soaDeadlineMode,
                'soa_deadline_days_after_passed' => $soaDeadlineDaysAfterPassed,
                'soa_deadline' => $soaDeadline,
                'auto_archive_completed_records' => 1,
                'is_open' => $isOpen,
            ]);

            $archivedCount = $this->autoArchiveCompletedPastRecords($schoolYear, $semester, (int) ($_SESSION['user_id'] ?? 0));

            AuditLog::recordCurrentUser(
                'settings.saved',
                'application_settings',
                1,
                'Opened or replaced the active application period.',
                [
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                    'application_start_date' => $startDate,
                    'application_end_date' => $endDate,
                    'soa_deadline_mode' => $soaDeadlineMode,
                    'soa_deadline_days_after_passed' => $soaDeadlineDaysAfterPassed,
                    'soa_deadline' => $soaDeadline,
                    'auto_archive_completed_records' => 1,
                    'is_open' => $isOpen,
                    'archived_records_count' => $archivedCount,
                ]
            );

            $successMessage = 'The new application period is now active.';
            $successMessage .= ' ' . $archivedCount . ' old completed record(s) were auto-archived.';

            redirect_with_flash('admin/settings', 'success', $successMessage);
        } catch (ValidationException $e) {
            redirect_with_flash('admin/settings', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to save application settings: ' . $e->getMessage());
            redirect_with_flash('admin/settings', 'error', 'Unable to save application settings right now.');
        }
    }

    private function autoArchiveCompletedPastRecords(string $currentSchoolYear, string $currentSemester, int $archivedByUserId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            UPDATE applications
            SET is_archived = 1,
                archived_at = NOW(),
                archived_by_user_id = :archived_by_user_id
            WHERE is_archived = 0
              AND status IN ('Approved_Finished', 'Not_Eligible', 'Forfeited')
              AND NOT (school_year = :school_year AND semester = :semester)
        ");
        $stmt->execute([
            'archived_by_user_id' => $archivedByUserId > 0 ? $archivedByUserId : null,
            'school_year' => $currentSchoolYear,
            'semester' => $currentSemester,
        ]);

        return (int) $stmt->rowCount();
    }

    public function processAccountRecovery()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $lookupPhone = trim((string) ($_POST['lookup_phone_number'] ?? ''));
            $lookupEmail = trim((string) ($_POST['lookup_email'] ?? ''));
            $newPhone = trim((string) ($_POST['new_phone_number'] ?? ''));
            $newEmail = trim((string) ($_POST['new_email'] ?? ''));
            $newPassword = trim((string) ($_POST['new_password'] ?? ''));
            $confirmedUserId = (int) ($_POST['confirmed_user_id'] ?? 0);

            if ($lookupPhone === '' && $lookupEmail === '') {
                throw new ValidationException('Enter the current mobile number or recovery email to find the account.');
            }

            $user = null;
            if ($lookupPhone !== '') {
                $user = User::findByPhone(Validation::phone($lookupPhone));
            } elseif ($lookupEmail !== '') {
                $user = User::findByEmail(Validation::email($lookupEmail));
            }

            if (!$user) {
                throw new ValidationException('No account matched the provided recovery details.');
            }

            if ((string) ($user['role'] ?? '') !== 'Student') {
                throw new ValidationException('Only applicant accounts can be updated from this page.');
            }
            if ($confirmedUserId <= 0 || $confirmedUserId !== (int) $user['id']) {
                throw new ValidationException('Review the matched applicant first before saving the recovery update.');
            }

            $normalizedNewPhone = $newPhone !== '' ? Validation::phone($newPhone) : null;
            $normalizedNewEmail = $newEmail !== '' ? Validation::email($newEmail) : null;

            if ($normalizedNewPhone === null && $normalizedNewEmail === null && $newPassword === '') {
                throw new ValidationException('Provide at least one update: replacement mobile number, email, or new password.');
            }

            if ($normalizedNewPhone !== null) {
                $existingPhoneUser = User::findByPhone($normalizedNewPhone);
                if ($existingPhoneUser && (int) $existingPhoneUser['id'] !== (int) $user['id']) {
                    throw new ValidationException('That mobile number is already used by another account.');
                }
            }

            if ($normalizedNewEmail !== null) {
                $existingEmailUser = User::findByEmail($normalizedNewEmail);
                if ($existingEmailUser && (int) $existingEmailUser['id'] !== (int) $user['id']) {
                    throw new ValidationException('That email address is already used by another account.');
                }
            }

            User::updateRecoveryContact((int) $user['id'], $normalizedNewPhone, $normalizedNewEmail);

            if ($newPassword !== '') {
                $password = Validation::password($newPassword);
                User::updatePassword((int) $user['id'], password_hash($password, PASSWORD_BCRYPT));
            }

            User::clearOtp((int) $user['id']);
            AuditLog::recordCurrentUser(
                'account.recovered',
                'user',
                (int) $user['id'],
                'Admin updated account recovery details.',
                [
                    'lookup_phone_used' => $lookupPhone !== '',
                    'lookup_email_used' => $lookupEmail !== '',
                    'phone_replaced' => $normalizedNewPhone !== null,
                    'email_replaced' => $normalizedNewEmail !== null,
                    'password_reset' => $newPassword !== '',
                ]
            );
            redirect_with_flash('admin/account-recovery', 'success', 'Account recovery details were updated successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/account-recovery', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Admin account recovery failed: ' . $e->getMessage());
            redirect_with_flash('admin/account-recovery', 'error', 'Unable to complete account recovery right now.');
        }
    }

    public function createPayoutBatch()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $db = Database::connect();

        try {
            $batchName = Validation::requiredString($_POST['batch_name'] ?? '', 'Batch name', 100);
            $scheduleDate = Validation::requiredString($_POST['scheduled_date'] ?? '', 'Scheduled date', 30);
            $venue = Validation::requiredString($_POST['venue'] ?? '', 'Venue', 150);
            $amountPerScholar = Validation::nonNegativeDecimal($_POST['amount_per_scholar'] ?? '', 'Amount per scholar', false);
            $schoolTypeFilter = trim((string) ($_POST['school_type'] ?? ''));
            $barangayFilter = trim((string) ($_POST['barangay'] ?? ''));

            if ($schoolTypeFilter === '' && $barangayFilter === '') {
                throw new ValidationException('Select at least one schedule filter: school type or barangay.');
            }

            $selectedApplications = $this->findPayoutBatchApplicationIds($db, $schoolTypeFilter, $barangayFilter);

            if (empty($selectedApplications)) {
                throw new ValidationException('No approved scholars matched the selected filters.');
            }

            $db->beginTransaction();

            // 1. Create the Payout Batch Record
            $stmt = $db->prepare("INSERT INTO batches (batch_type, batch_name, scheduled_date, venue) VALUES ('Payout', :name, :sdate, :venue)");
            $stmt->execute([
                'name' => $batchName,
                'sdate' => $scheduleDate,
                'venue' => $venue
            ]);
            $batchId = $db->lastInsertId();

            // 2. Assign the selected students to this payout batch
            $placeholders = implode(',', array_fill(0, count($selectedApplications), '?'));
            array_unshift($selectedApplications, (float) $amountPerScholar);
            array_unshift($selectedApplications, $batchId);

            $updateApp = $db->prepare("UPDATE applications SET payout_batch_id = ?, final_grant_amount = ? WHERE id IN ($placeholders)");
            $updateApp->execute($selectedApplications);

            $assignedApplicationIds = array_slice($selectedApplications, 1);
            if ($assignedApplicationIds !== []) {
                $notifyPlaceholders = implode(',', array_fill(0, count($assignedApplicationIds), '?'));
                $scheduledScholars = $this->fetchPayoutBatchScholars($db, (int) $batchId);

                foreach ($scheduledScholars as $scholar) {
                    $studentUserId = (int) ($scholar['user_id'] ?? 0);
                    $payoutNumber = (int) ($scholar['payout_no'] ?? 0);

                    if ($studentUserId <= 0) {
                        continue;
                    }

                    Notification::create(
                        $studentUserId,
                        'Payout Schedule Ready',
                        'Your scholarship payout was scheduled for ' . $scheduleDate . ' at ' . $venue . '. Payout No.: ' . $payoutNumber . '.',
                        'student/dashboard'
                    );
                }

                foreach ($scheduledScholars as $scholar) {
                    $phoneNumber = trim((string) ($scholar['phone_number'] ?? ''));
                    $lastName = trim((string) ($scholar['last_name'] ?? ''));
                    $payoutNumber = (int) ($scholar['payout_no'] ?? 0);

                    if ($phoneNumber !== '') {
                        Sms::sendTextbee(
                            $phoneNumber,
                            'San Enrique LGU Scholarship: ' . ($lastName !== '' ? $lastName . ', ' : '')
                            . 'your payout is scheduled on '
                            . date('M d, Y h:i A', strtotime($scheduleDate))
                            . ' at ' . $venue . '. Payout No. ' . $payoutNumber . '.'
                        );
            }
        }
    }

            $db->commit();

            AuditLog::recordCurrentUser(
                'payout_batch.created',
                'batch',
                (int) $batchId,
                'Created a payout batch and assigned approved scholars.',
                [
                    'batch_name' => $batchName,
                    'amount_per_scholar' => $amountPerScholar,
                    'scheduled_date' => $scheduleDate,
                    'venue' => $venue,
                    'application_count' => count($assignedApplicationIds),
                    'school_type' => $schoolTypeFilter !== '' ? $schoolTypeFilter : null,
                    'barangay' => $barangayFilter !== '' ? $barangayFilter : null,
                ]
            );

            // 3. Trigger Textbee SMS: "Your allowance is ready. Proceed to the Municipal Hall on [Date]."

            header('Location: ' . \base_url('admin/final-approval') . '?success=1');
            exit;

        } catch (ValidationException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            redirect_with_flash('admin/final-approval', 'error', $e->getMessage());
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Failed to create payout batch: ' . $e->getMessage());
            redirect_with_flash('admin/final-approval', 'error', 'Failed to create the payout batch.');
        }
    }

    private function findRecoveryApplicant(?string $phoneNumber, ?string $email): ?array
    {
        if ($phoneNumber === null && $email === null) {
            return null;
        }

        $db = Database::connect();
        $sql = "
            SELECT
                u.id,
                u.role,
                u.phone_number,
                u.email,
                COALESCE(NULLIF(CONCAT(TRIM(sp.first_name), ' ', TRIM(sp.last_name)), ' '), CONCAT(TRIM(u.first_name), ' ', TRIM(u.last_name))) AS applicant_name,
                sp.school_name,
                sp.address_barangay
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.role = 'Student'
        ";
        $params = [];

        if ($phoneNumber !== null) {
            $sql .= " AND u.phone_number = :phone_number";
            $params['phone_number'] = $phoneNumber;
        }

        if ($email !== null) {
            $sql .= " AND u.email = :email";
            $params['email'] = $email;
        }

        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $match = $stmt->fetch();

        if (!$match) {
            return null;
        }

        return [
            'id' => (int) ($match['id'] ?? 0),
            'applicant_name' => trim((string) ($match['applicant_name'] ?? '')) !== '' ? (string) $match['applicant_name'] : 'Unnamed applicant',
            'phone_number' => (string) ($match['phone_number'] ?? ''),
            'email' => (string) ($match['email'] ?? ''),
            'school_name' => (string) ($match['school_name'] ?? ''),
            'address_barangay' => (string) ($match['address_barangay'] ?? ''),
        ];
    }

    public function reschedulePayoutBatch()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $db = Database::connect();

        try {
            $batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
            $scheduleDate = Validation::requiredString($_POST['scheduled_date'] ?? '', 'Scheduled date', 30);
            $venue = Validation::requiredString($_POST['venue'] ?? '', 'Venue', 150);

            if (!$batchId) {
                throw new ValidationException('Invalid payout batch.');
            }

            $batchStmt = $db->prepare("SELECT * FROM batches WHERE id = :id AND batch_type = 'Payout' LIMIT 1");
            $batchStmt->execute(['id' => $batchId]);
            $batch = $batchStmt->fetch();

            if (!$batch) {
                throw new ValidationException('Payout batch not found.');
            }

            $oldSchedule = (string) ($batch['scheduled_date'] ?? '');
            $oldVenue = (string) ($batch['venue'] ?? '');

            $updateStmt = $db->prepare("UPDATE batches SET scheduled_date = :scheduled_date, venue = :venue WHERE id = :id");
            $updateStmt->execute([
                'scheduled_date' => $scheduleDate,
                'venue' => $venue,
                'id' => $batchId,
            ]);

            $scheduledScholars = $this->fetchPayoutBatchScholars($db, (int) $batchId);

            $baseMessage = 'Your payout schedule was moved from '
                . date('M d, Y h:i A', strtotime($oldSchedule))
                . ' at ' . $oldVenue
                . ' to '
                . date('M d, Y h:i A', strtotime($scheduleDate))
                . ' at ' . $venue . '.';

            foreach ($scheduledScholars as $scholar) {
                $studentUserId = (int) ($scholar['user_id'] ?? 0);
                $payoutNumber = (int) ($scholar['payout_no'] ?? 0);

                if ($studentUserId <= 0) {
                    continue;
                }

                Notification::create(
                    $studentUserId,
                    'Payout Rescheduled',
                    $baseMessage . ' Payout No.: ' . $payoutNumber . '.',
                    'student/dashboard'
                );
            }

            foreach ($scheduledScholars as $scholar) {
                $phoneNumber = trim((string) ($scholar['phone_number'] ?? ''));
                $lastName = trim((string) ($scholar['last_name'] ?? ''));
                $payoutNumber = (int) ($scholar['payout_no'] ?? 0);

                if ($phoneNumber !== '') {
                    Sms::sendTextbee(
                        $phoneNumber,
                        'San Enrique LGU Scholarship: ' . ($lastName !== '' ? $lastName . ', ' : '')
                        . 'your payout schedule has been moved to '
                        . date('M d, Y h:i A', strtotime($scheduleDate))
                        . ' at ' . $venue . '. Payout No. ' . $payoutNumber . '.'
                    );
                }
            }

            AuditLog::recordCurrentUser(
                'payout_batch.rescheduled',
                'batch',
                (int) $batchId,
                'Rescheduled a payout batch.',
                [
                    'old_schedule' => $oldSchedule,
                    'old_venue' => $oldVenue,
                    'new_schedule' => $scheduleDate,
                    'new_venue' => $venue,
                    'affected_scholars' => count($scheduledScholars),
                ]
            );

            redirect_with_flash('admin/final-approval', 'success', 'Payout batch rescheduled successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/final-approval', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to reschedule payout batch: ' . $e->getMessage());
            redirect_with_flash('admin/final-approval', 'error', 'Unable to reschedule the payout batch right now.');
        }
    }

    public function exportPayoutList()
    {
        $db = Database::connect();

        try {
            $format = Validation::enum($_GET['format'] ?? 'print', ['excel', 'word', 'print'], 'Export format');
            $scope = Validation::enum($_GET['scope'] ?? 'current', ['current', 'batch', 'batch_signature', 'approved_pending_payroll'], 'Export scope');
            $batchId = filter_var($_GET['batch_id'] ?? null, FILTER_VALIDATE_INT);

            if (in_array($scope, ['batch', 'batch_signature'], true) && !$batchId) {
                throw new ValidationException('Invalid payout batch selected for export.');
            }

            if (in_array($scope, ['batch', 'batch_signature'], true)) {
                $batchStmt = $db->prepare("
                    SELECT id, batch_name, scheduled_date, venue
                    FROM batches
                    WHERE id = :id AND batch_type = 'Payout'
                    LIMIT 1
                ");
                $batchStmt->execute(['id' => $batchId]);
                $batch = $batchStmt->fetch();

                if (!$batch) {
                    throw new ValidationException('Payout batch not found.');
                }

                if ($scope === 'batch_signature') {
                    $stmt = $db->prepare("
                        SELECT
                            CONCAT(p.last_name, ', ', p.first_name, IF(COALESCE(p.middle_name, '') <> '', CONCAT(' ', p.middle_name), '')) AS scholar_name,
                            p.school_name,
                            p.address_barangay AS barangay
                        FROM applications a
                        JOIN student_profiles p ON a.student_id = p.id
                        WHERE a.payout_batch_id = :batch_id
                        ORDER BY p.school_name ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
                    ");
                    $stmt->execute(['batch_id' => $batchId]);
                    $rows = $stmt->fetchAll();

                    $this->streamPayoutSignatureSheet($batch, $rows, $format);
                }

                $stmt = $db->prepare("
                    SELECT
                        a.id AS application_id,
                        CONCAT(p.last_name, ', ', p.first_name) AS scholar_name,
                        p.address_barangay AS barangay,
                        p.school_type,
                        p.school_name,
                        COALESCE(a.final_grant_amount, 0) AS approved_amount,
                        COALESCE(DATE_FORMAT(b.scheduled_date, '%b %d, %Y %h:%i %p'), 'Not Scheduled') AS schedule,
                        COALESCE(b.venue, 'Not Scheduled') AS venue
                    FROM applications a
                    JOIN student_profiles p ON a.student_id = p.id
                    JOIN batches b ON a.payout_batch_id = b.id
                    WHERE a.payout_batch_id = :batch_id
                    ORDER BY p.school_name ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
                ");
                $stmt->execute(['batch_id' => $batchId]);
                $rows = $this->withSequentialNumbers($stmt->fetchAll(), 'payout_no');

                foreach ($rows as &$row) {
                    $row['approved_amount'] = 'PHP ' . number_format((float) $row['approved_amount'], 2);
                }
                unset($row);

                OfficeExporter::stream(
                    'Payout Batch List - ' . (string) $batch['batch_name'],
                    [
                        'payout_no' => 'Payout No.',
                        'application_id' => 'Application ID',
                        'scholar_name' => 'Scholar Name',
                        'barangay' => 'Barangay',
                        'school_type' => 'School Type',
                        'school_name' => 'School',
                        'approved_amount' => 'Approved Amount',
                        'schedule' => 'Payout Schedule',
                        'venue' => 'Venue',
                    ],
                    $rows,
                    $format,
                    [
                        'Batch Name' => (string) $batch['batch_name'],
                        'Schedule' => date('M d, Y h:i A', strtotime((string) $batch['scheduled_date'])),
                        'Venue' => (string) $batch['venue'],
                        'Generated On' => date('M d, Y h:i A'),
                    ]
                );
            }

            if ($scope === 'approved_pending_payroll') {
                $stmt = $db->query("
                    SELECT
                        a.id AS application_id,
                        CONCAT(p.last_name, ', ', p.first_name) AS scholar_name,
                        p.address_barangay AS barangay,
                        p.school_type,
                        p.school_name,
                        a.school_year,
                        a.semester,
                        COALESCE(a.final_grant_amount, 0) AS approved_amount,
                        a.status AS payout_status
                    FROM applications a
                    JOIN student_profiles p ON a.student_id = p.id
                    WHERE a.status = 'Approved_Pending_Payroll'
                    ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
                ");
                $rows = $stmt->fetchAll();

                foreach ($rows as &$row) {
                    $row['approved_amount'] = 'PHP ' . number_format((float) $row['approved_amount'], 2);
                }
                unset($row);

                OfficeExporter::stream(
                    'Approved Pending Payroll List',
                    [
                        'application_id' => 'Application ID',
                        'scholar_name' => 'Scholar Name',
                        'barangay' => 'Barangay',
                        'school_type' => 'School Type',
                        'school_name' => 'School',
                        'school_year' => 'School Year',
                        'semester' => 'Semester',
                        'approved_amount' => 'Approved Amount',
                        'payout_status' => 'Status',
                    ],
                    $rows,
                    $format,
                    [
                        'List Type' => 'For approval and payroll endorsement',
                        'Generated On' => date('M d, Y h:i A'),
                    ]
                );
            }

            $stmt = $db->query("
                SELECT
                    a.id AS application_id,
                    CONCAT(p.last_name, ', ', p.first_name) AS scholar_name,
                    p.address_barangay AS barangay,
                    p.school_type,
                    p.school_name,
                    COALESCE(a.final_grant_amount, 0) AS approved_amount,
                    a.status AS payout_status
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                WHERE a.status = 'Approved_Pending_Payroll' AND a.payout_batch_id IS NULL
                ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
            ");
            $rows = $stmt->fetchAll();

            foreach ($rows as &$row) {
                $row['approved_amount'] = 'PHP ' . number_format((float) $row['approved_amount'], 2);
            }
            unset($row);

            OfficeExporter::stream(
                'Approved Scholars Ready for Scheduling',
                [
                    'application_id' => 'Application ID',
                    'scholar_name' => 'Scholar Name',
                    'barangay' => 'Barangay',
                    'school_type' => 'School Type',
                    'school_name' => 'School',
                    'approved_amount' => 'Approved Amount',
                    'payout_status' => 'Status',
                ],
                $rows,
                $format,
                [
                    'List Type' => 'Approved scholars ready for payout scheduling',
                    'Generated On' => date('M d, Y h:i A'),
                ]
            );
        } catch (ValidationException $e) {
            redirect_with_flash('admin/final-approval', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to export payout list: ' . $e->getMessage());
            redirect_with_flash('admin/final-approval', 'error', 'Unable to export the payout list right now.');
        }
    }

    private function streamPayoutSignatureSheet(array $batch, array $rows, string $format): never
    {
        $timestamp = date('Ymd-His');
        $title = 'Payout Signature Sheet - ' . (string) ($batch['batch_name'] ?? 'Batch');

        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="payout-signature-sheet-' . $timestamp . '.xls"');
        } elseif ($format === 'word') {
            header('Content-Type: application/msword; charset=UTF-8');
            header('Content-Disposition: attachment; filename="payout-signature-sheet-' . $timestamp . '.doc"');
        } else {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #111; margin: 24px; }
            h1 { font-size: 18px; margin: 0 0 8px; }
            .meta { margin: 0 0 18px; }
            .meta div { margin-bottom: 4px; }
            .group-title { margin: 18px 0 8px; font-size: 13px; font-weight: bold; text-transform: uppercase; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
            th, td { border: 1px solid #222; padding: 8px; }
            th { background: #f0f0f0; text-align: center; }
            td:first-child, th:first-child { width: 8%; text-align: center; }
            td:nth-child(2), th:nth-child(2) { width: 42%; }
            td:nth-child(3), th:nth-child(3) { width: 50%; }
            .signature-cell { height: 34px; }
            .empty { padding: 18px; border: 1px solid #222; text-align: center; }
            @media print { body { margin: 12px; } }
        </style>';

        if ($format === 'print') {
            echo '<script>window.addEventListener("load", function () { window.print(); });</script>';
        }

        echo '</head><body>';
        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<div class="meta">';
        echo '<div><strong>Batch Name:</strong> ' . htmlspecialchars((string) ($batch['batch_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '<div><strong>Schedule:</strong> ' . htmlspecialchars(date('M d, Y h:i A', strtotime((string) ($batch['scheduled_date'] ?? 'now'))), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '<div><strong>Venue:</strong> ' . htmlspecialchars((string) ($batch['venue'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '<div><strong>Generated On:</strong> ' . htmlspecialchars(date('M d, Y h:i A'), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';

        if ($rows === []) {
            echo '<div class="empty">No scheduled scholars found for this payout batch.</div></body></html>';
            exit;
        }

        $groupedRows = [];
        foreach ($rows as $row) {
            $schoolName = trim((string) ($row['school_name'] ?? 'Unspecified School'));
            $barangay = trim((string) ($row['barangay'] ?? 'Unspecified Barangay'));
            $groupedRows[$schoolName][$barangay][] = $row;
        }

        foreach ($groupedRows as $schoolName => $barangayGroups) {
            foreach ($barangayGroups as $barangay => $groupRows) {
                echo '<div class="group-title">School: ' . htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') . ' | Barangay: ' . htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8') . '</div>';
                echo '<table><thead><tr><th>No.</th><th>Full Name</th><th>Signature</th></tr></thead><tbody>';
                foreach ($groupRows as $row) {
                    echo '<tr>';
                    echo '<td>' . (int) ($row['payout_no'] ?? 0) . '</td>';
                    echo '<td>' . htmlspecialchars((string) ($row['scholar_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td class="signature-cell"></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        }

        echo '</body></html>';
        exit;
    }

    private function fetchPayoutBatchScholars(\PDO $db, int $batchId): array
    {
        $stmt = $db->prepare("
            SELECT
                u.id AS user_id,
                u.phone_number,
                p.last_name,
                CONCAT(p.last_name, ', ', p.first_name, IF(COALESCE(p.middle_name, '') <> '', CONCAT(' ', p.middle_name), '')) AS scholar_name,
                p.school_name,
                p.address_barangay AS barangay
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE a.payout_batch_id = :batch_id
            ORDER BY p.school_name ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
        ");
        $stmt->execute(['batch_id' => $batchId]);

        return $this->withSequentialNumbers($stmt->fetchAll(), 'payout_no');
    }

    private function findPayoutBatchApplicationIds(\PDO $db, string $schoolTypeFilter, string $barangayFilter): array
    {
        $conditions = [
            "a.status = 'Approved_Pending_Payroll'",
            'a.payout_batch_id IS NULL',
        ];
        $params = [];

        if ($schoolTypeFilter !== '') {
            $conditions[] = 'p.school_type = :school_type';
            $params['school_type'] = $schoolTypeFilter;
        }

        if ($barangayFilter !== '') {
            $conditions[] = 'p.address_barangay = :barangay';
            $params['barangay'] = $barangayFilter;
        }

        $stmt = $db->prepare("
            SELECT a.id
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
        ");
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function buildBatchFilterSummary(string $schoolTypeFilter, string $barangayFilter, string $singularLabel, string $pluralLabel): string
    {
        $parts = [];
        if ($schoolTypeFilter !== '') {
            $parts[] = 'school type: ' . $schoolTypeFilter;
        }

        if ($barangayFilter !== '') {
            $parts[] = 'barangay: ' . $barangayFilter;
        }

        if ($parts === []) {
            return 'Choose a school type and/or barangay to preview the matching ' . $pluralLabel . ' for this schedule.';
        }

        return 'This schedule will apply to the filtered ' . $pluralLabel . ' for ' . implode(' and ', $parts) . '.';
    }

    private function withSequentialNumbers(array $rows, string $key): array
    {
        foreach ($rows as $index => &$row) {
            $row[$key] = $index + 1;
        }
        unset($row);

        return $rows;
    }
}
