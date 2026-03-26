<?php

namespace App\Controllers;

use App\Config\Database;
use App\Models\ApplicationPeriod;
use App\Models\AuditLog;
use App\Models\CaseNote;
use App\Models\DocumentVersion;
use App\Models\Notification;
use App\Support\OfficeExporter;
use App\Support\Sms;
use App\Support\Validation;
use App\Support\ValidationException;
use Exception;
use PDO;
use Throwable;

class StaffController
{
    private function currentRole(): string
    {
        return (string) ($_SESSION['role'] ?? '');
    }

    private function isCoordinator(): bool
    {
        return $this->currentRole() === 'Admin';
    }

    private function ensureCoordinatorAccess(): void
    {
        if (!$this->isCoordinator()) {
            redirect_with_flash('admin/batch-interview', 'error', 'Only the LGU Scholarship Coordinator can manage interview schedules.');
        }
    }

    public function __construct()
    {
        // Security block: Only Staff (or Admins doing staff work) can access this
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Staff', 'Admin'])) {
            header('Location: ' . \base_url('login'));
            exit;
        }
    }

    // Load the main dashboard showing pending applications
    public function dashboard()
    {
        $db = Database::connect();

        // Fetch applications that need initial review or SOA review
        $stmt = $db->query("SELECT a.id, a.status, p.first_name, p.last_name, p.school_name 
                            FROM applications a 
                            JOIN student_profiles p ON a.student_id = p.id 
                            WHERE a.status IN ('Submitted', 'Initial_Review', 'SOA_Under_Review')
                            ORDER BY a.created_at ASC");
        $pendingApplications = $stmt->fetchAll();

        require __DIR__ . '/../../views/staff/dashboard.php';
    }

    public function applications()
    {
        $db = Database::connect();
        $currentPeriod = ApplicationPeriod::getCurrent();
        ApplicationPeriod::syncOverdueSoaStatuses($db, $currentPeriod);
        $queueFilter = trim((string) ($_GET['queue'] ?? 'under_review'));
        $search = trim((string) ($_GET['q'] ?? ''));
        $barangayFilter = trim((string) ($_GET['barangay'] ?? ''));
        $schoolTypeFilter = trim((string) ($_GET['school_type'] ?? ''));
        $applicationTypeFilter = trim((string) ($_GET['application_type'] ?? ''));
        $sortFilter = trim((string) ($_GET['sort'] ?? 'newest'));

        $queueMap = $this->applicationQueueMap();
        if (!array_key_exists($queueFilter, $queueMap)) {
            $queueFilter = 'under_review';
        }

        $sortOptions = [
            'newest' => 'a.updated_at DESC, a.id DESC',
            'oldest' => 'a.created_at ASC, a.id ASC',
            'name_asc' => 'p.last_name ASC, p.first_name ASC, a.id DESC',
            'barangay_asc' => 'p.address_barangay ASC, p.last_name ASC, p.first_name ASC',
        ];
        if (!array_key_exists($sortFilter, $sortOptions)) {
            $sortFilter = 'newest';
        }

        $params = [];
        $conditions = [];

        if (is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])) {
            $conditions[] = 'a.school_year = :school_year';
            $conditions[] = 'a.semester = :semester';
            $params['school_year'] = (string) $currentPeriod['school_year'];
            $params['semester'] = (string) $currentPeriod['semester'];
        }

        if ($queueFilter !== 'all') {
            $queueStatuses = $queueMap[$queueFilter]['statuses'];
            $statusPlaceholders = [];
            foreach ($queueStatuses as $index => $statusValue) {
                $paramName = 'queue_status_' . $index;
                $statusPlaceholders[] = ':' . $paramName;
                $params[$paramName] = $statusValue;
            }
            $conditions[] = 'a.status IN (' . implode(', ', $statusPlaceholders) . ')';
            if ($queueFilter === 'for_interview') {
                $conditions[] = 'a.interview_batch_id IS NULL';
            }
        }

        if ($search !== '') {
            $conditions[] = "(
                CONCAT(p.last_name, ', ', p.first_name) LIKE :search
                OR CONCAT(p.first_name, ' ', p.last_name) LIKE :search
                OR p.school_name LIKE :search
                OR p.address_barangay LIKE :search
                OR CONCAT('SELGU-APP-', YEAR(a.created_at), '-', LPAD(a.id, 5, '0')) LIKE :search
            )";
            $params['search'] = '%' . $search . '%';
        }

        if ($barangayFilter !== '') {
            $conditions[] = 'p.address_barangay = :barangay';
            $params['barangay'] = $barangayFilter;
        }

        if ($schoolTypeFilter !== '') {
            $conditions[] = 'p.school_type = :school_type';
            $params['school_type'] = $schoolTypeFilter;
        }

        if ($applicationTypeFilter !== '') {
            $conditions[] = 'a.application_type = :application_type';
            $params['application_type'] = $applicationTypeFilter;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $applicationsStmt = $db->prepare("
            SELECT
                a.id,
                a.status,
                a.application_type,
                a.school_year,
                a.semester,
                a.created_at,
                a.updated_at,
                a.interview_batch_id,
                a.payout_batch_id,
                p.first_name,
                p.last_name,
                p.address_barangay,
                p.school_name,
                p.school_type
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            $whereSql
            ORDER BY " . $sortOptions[$sortFilter] . "
        ");
        $applicationsStmt->execute($params);
        $applications = $applicationsStmt->fetchAll();

        $queueCounts = array_fill_keys(array_keys($queueMap), 0);
        $countConditions = [];
        $countParams = [];
        if (is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])) {
            $countConditions[] = 'a.school_year = :school_year';
            $countConditions[] = 'a.semester = :semester';
            $countParams['school_year'] = (string) $currentPeriod['school_year'];
            $countParams['semester'] = (string) $currentPeriod['semester'];
        }

        if ($search !== '') {
            $countConditions[] = "(
                CONCAT(p.last_name, ', ', p.first_name) LIKE :search
                OR CONCAT(p.first_name, ' ', p.last_name) LIKE :search
                OR p.school_name LIKE :search
                OR p.address_barangay LIKE :search
                OR CONCAT('SELGU-APP-', YEAR(a.created_at), '-', LPAD(a.id, 5, '0')) LIKE :search
            )";
            $countParams['search'] = '%' . $search . '%';
        }

        if ($barangayFilter !== '') {
            $countConditions[] = 'p.address_barangay = :barangay';
            $countParams['barangay'] = $barangayFilter;
        }

        if ($schoolTypeFilter !== '') {
            $countConditions[] = 'p.school_type = :school_type';
            $countParams['school_type'] = $schoolTypeFilter;
        }

        if ($applicationTypeFilter !== '') {
            $countConditions[] = 'a.application_type = :application_type';
            $countParams['application_type'] = $applicationTypeFilter;
        }

        $countWhereSql = $countConditions !== [] ? 'WHERE ' . implode(' AND ', $countConditions) : '';
        $statusCountStmt = $db->prepare("
            SELECT status, interview_batch_id, COUNT(*) AS total
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            $countWhereSql
            GROUP BY a.status, a.interview_batch_id
        ");
        $statusCountStmt->execute($countParams);
        foreach ($statusCountStmt->fetchAll() as $statusRow) {
            $status = (string) ($statusRow['status'] ?? '');
            $count = (int) ($statusRow['total'] ?? 0);
            $hasInterviewBatch = !empty($statusRow['interview_batch_id']);
            foreach ($queueMap as $queueKey => $queueConfig) {
                if ($queueKey === 'all') {
                    $queueCounts[$queueKey] += $count;
                    continue;
                }
                if ($queueKey === 'for_interview' && $status === 'For_Interview' && $hasInterviewBatch) {
                    continue;
                }
                if (in_array($status, $queueConfig['statuses'], true)) {
                    $queueCounts[$queueKey] += $count;
                }
            }
        }

        foreach ($applications as &$application) {
            $application['application_number'] = $this->formatApplicationNumber((int) ($application['id'] ?? 0), (string) ($application['created_at'] ?? ''));
            $application['queue_key'] = $this->resolveApplicationQueue((string) ($application['status'] ?? ''));
            $application['queue_label'] = $this->applicationQueueLabel((string) $application['queue_key']);
            $application['next_action'] = $this->applicationNextAction((string) ($application['status'] ?? ''));
            $application['workflow_steps'] = $this->buildWorkflowSteps((string) ($application['status'] ?? ''));
            $application['status_badge'] = $this->applicationStatusBadge((string) ($application['status'] ?? ''));
            $application['primary_action_label'] = $this->applicationPrimaryActionLabel((string) ($application['status'] ?? ''));
            $application['updated_time_human'] = $this->formatRelativeTime((string) ($application['updated_at'] ?? $application['created_at'] ?? ''));
            $application['urgency_label'] = $this->applicationUrgencyLabel((string) ($application['updated_at'] ?? $application['created_at'] ?? ''));
        }
        unset($application);

        $selectedApplicationId = filter_var($_GET['selected_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$selectedApplicationId && $applications !== []) {
            $selectedApplicationId = (int) ($applications[0]['id'] ?? 0);
        }

        $selectedApplication = null;
        foreach ($applications as $application) {
            if ((int) ($application['id'] ?? 0) === (int) $selectedApplicationId) {
                $selectedApplication = $application;
                break;
            }
        }

        if ($selectedApplication === null && $applications !== []) {
            $selectedApplication = $applications[0];
            $selectedApplicationId = (int) ($selectedApplication['id'] ?? 0);
        }

        $barangayOptions = [
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
        $schoolTypeOptions = ['Public', 'Private'];
        $applicationTypeOptions = ['New', 'Renewal'];
        $sortChoices = [
            'newest' => 'Newest',
            'oldest' => 'Oldest',
            'name_asc' => 'Name A-Z',
            'barangay_asc' => 'Barangay A-Z',
        ];

        require __DIR__ . '/../../views/staff/applications.php';
    }

    public function archive()
    {
        $db = Database::connect();
        $search = trim((string) ($_GET['q'] ?? ''));
        $statusFilter = trim((string) ($_GET['status'] ?? ''));
        $barangayFilter = trim((string) ($_GET['barangay'] ?? ''));
        $lifecycleFilter = trim((string) ($_GET['lifecycle'] ?? ''));
        $schoolYearFilter = trim((string) ($_GET['school_year'] ?? ''));
        $semesterFilter = trim((string) ($_GET['semester'] ?? ''));
        $schoolTypeFilter = trim((string) ($_GET['school_type'] ?? ''));
        $applicationTypeFilter = trim((string) ($_GET['application_type'] ?? ''));
        $selectedProfileId = filter_var($_GET['profile_id'] ?? null, FILTER_VALIDATE_INT);
        $lifecycleSql = $this->applicationLifecycleSql('a');

        $conditions = ['1=1'];
        $params = [];

        if ($search !== '') {
            $conditions[] = "(
                CONCAT(p.last_name, ' ', p.first_name, ' ', COALESCE(p.middle_name, '')) LIKE :search_name
                OR CONCAT(p.first_name, ' ', p.last_name) LIKE :search_name_reverse
                OR p.school_name LIKE :search_school
                OR p.course LIKE :search_course
                OR p.address_barangay LIKE :search_barangay
                OR u.phone_number LIKE :search_phone
                OR COALESCE(u.email, '') LIKE :search_email
                OR CAST(a.id AS CHAR) LIKE :search_id
                OR CONCAT('SELGU-APP-', YEAR(a.created_at), '-', LPAD(a.id, 5, '0')) LIKE :search_application
            )";
            $searchLike = '%' . $search . '%';
            $params['search_name'] = $searchLike;
            $params['search_name_reverse'] = $searchLike;
            $params['search_school'] = $searchLike;
            $params['search_course'] = $searchLike;
            $params['search_barangay'] = $searchLike;
            $params['search_phone'] = $searchLike;
            $params['search_email'] = $searchLike;
            $params['search_id'] = $searchLike;
            $params['search_application'] = $searchLike;
        }

        if ($statusFilter !== '') {
            $conditions[] = 'a.status = :status';
            $params['status'] = $statusFilter;
        }

        if ($barangayFilter !== '') {
            $conditions[] = 'p.address_barangay = :barangay';
            $params['barangay'] = $barangayFilter;
        }

        if ($schoolYearFilter !== '') {
            $conditions[] = 'a.school_year = :school_year';
            $params['school_year'] = $schoolYearFilter;
        }

        if ($semesterFilter !== '') {
            $conditions[] = 'a.semester = :semester';
            $params['semester'] = $semesterFilter;
        }

        if ($schoolTypeFilter !== '') {
            $conditions[] = 'p.school_type = :school_type';
            $params['school_type'] = $schoolTypeFilter;
        }

        if ($applicationTypeFilter !== '') {
            $conditions[] = 'a.application_type = :application_type';
            $params['application_type'] = $applicationTypeFilter;
        }

        if ($lifecycleFilter !== '') {
            $conditions[] = $lifecycleSql . ' = :lifecycle';
            $params['lifecycle'] = $lifecycleFilter;
        }

        $whereSql = implode(' AND ', $conditions);

        $stmt = $db->prepare("
            SELECT
                p.id AS profile_id,
                p.user_id,
                p.last_name,
                p.first_name,
                p.middle_name,
                p.school_name,
                p.course,
                p.address_barangay,
                u.phone_number,
                u.email,
                MAX(a.created_at) AS latest_application_at,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(a.status ORDER BY a.created_at DESC, a.id DESC SEPARATOR '||'),
                    '||',
                    1
                ) AS latest_status,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(a.id ORDER BY a.created_at DESC, a.id DESC SEPARATOR '||'),
                    '||',
                    1
                ) AS latest_application_id,
                SUBSTRING_INDEX(
                    GROUP_CONCAT((" . $lifecycleSql . ") ORDER BY a.created_at DESC, a.id DESC SEPARATOR '||'),
                    '||',
                    1
                ) AS latest_lifecycle,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(a.school_year ORDER BY a.created_at DESC, a.id DESC SEPARATOR '||'),
                    '||',
                    1
                ) AS latest_school_year,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(a.semester ORDER BY a.created_at DESC, a.id DESC SEPARATOR '||'),
                    '||',
                    1
                ) AS latest_semester,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(a.application_type ORDER BY a.created_at DESC, a.id DESC SEPARATOR '||'),
                    '||',
                    1
                ) AS latest_application_type,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(p.school_type ORDER BY a.created_at DESC, a.id DESC SEPARATOR '||'),
                    '||',
                    1
                ) AS latest_school_type,
                COUNT(a.id) AS total_applications
            FROM student_profiles p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN applications a ON a.student_id = p.id
            WHERE $whereSql
            GROUP BY
                p.id, p.user_id, p.last_name, p.first_name, p.middle_name,
                p.school_name, p.course, p.address_barangay, u.phone_number, u.email
            ORDER BY latest_application_at DESC, p.last_name ASC, p.first_name ASC
            LIMIT 100
        ");
        $stmt->execute($params);
        $searchResults = $stmt->fetchAll();

        $selectedRecord = null;
        $applicationHistory = [];
        $documentHistory = [];
        $notificationHistory = [];
        $auditHistory = [];
        $caseNotes = [];
        $latestApplication = null;
        $applicationTimelines = [];
        $documentVersionHistory = [];

        if ($selectedProfileId) {
            extract($this->loadMasterRecordData($selectedProfileId), EXTR_OVERWRITE);
        }

        $statusOptions = [
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
            'Forfeited',
        ];
        $lifecycleOptions = ['Current', 'Completed', 'Archived'];
        $schoolYearOptions = $db->query("
            SELECT DISTINCT school_year
            FROM applications
            WHERE school_year IS NOT NULL AND school_year <> ''
            ORDER BY school_year DESC
        ")->fetchAll(\PDO::FETCH_COLUMN);
        $semesterOptions = ['1st Semester', '2nd Semester'];
        $schoolTypeOptions = ['Public', 'Private'];
        $applicationTypeOptions = ['New', 'Renewal'];
        $barangayOptions = [
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

        require __DIR__ . '/../../views/staff/archive.php';
    }

    public function masterRecord($profileId)
    {
        $selectedProfileId = (int) $profileId;
        if ($selectedProfileId <= 0) {
            redirect_with_flash('staff/archive', 'error', 'Record not found.');
        }

        $allowedSections = ['profile', 'applications', 'documents', 'notes', 'timelines'];
        $selectedSection = trim((string) ($_GET['section'] ?? 'profile'));
        if (!in_array($selectedSection, $allowedSections, true)) {
            $selectedSection = 'profile';
        }

        $search = '';
        $statusFilter = '';
        $barangayFilter = '';
        $lifecycleFilter = '';
        $schoolYearFilter = '';
        $semesterFilter = '';
        $schoolTypeFilter = '';
        $applicationTypeFilter = '';
        $searchResults = [];
        $recordOnlyView = true;

        extract($this->loadMasterRecordData($selectedProfileId), EXTR_OVERWRITE);

        if (!$selectedRecord) {
            redirect_with_flash('staff/archive', 'error', 'Record not found.');
        }

        $statusOptions = [];
        $lifecycleOptions = [];
        $schoolYearOptions = [];
        $semesterOptions = [];
        $schoolTypeOptions = [];
        $applicationTypeOptions = [];
        $barangayOptions = [];

        require __DIR__ . '/../../views/staff/archive.php';
    }

    private function loadMasterRecordData(int $selectedProfileId): array
    {
        $db = Database::connect();

        $selectedRecord = null;
        $applicationHistory = [];
        $documentHistory = [];
        $notificationHistory = [];
        $auditHistory = [];
        $caseNotes = [];
        $latestApplication = null;
        $applicationTimelines = [];
        $documentVersionHistory = [];

        if ($selectedProfileId > 0) {
            $profileStmt = $db->prepare("
                SELECT
                    p.*,
                    u.phone_number,
                    u.email,
                    u.role,
                    u.is_verified
                FROM student_profiles p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = :profile_id
                LIMIT 1
            ");
            $profileStmt->execute(['profile_id' => $selectedProfileId]);
            $selectedRecord = $profileStmt->fetch();

            if ($selectedRecord) {
                $historyStmt = $db->prepare("
                    SELECT
                        a.*,
                        " . $this->applicationLifecycleSql('a') . " AS record_lifecycle,
                        ib.batch_name AS interview_batch_name,
                        ib.scheduled_date AS interview_schedule,
                        ib.venue AS interview_venue,
                        pb.batch_name AS payout_batch_name,
                        pb.scheduled_date AS payout_schedule,
                        pb.venue AS payout_venue
                    FROM applications a
                    LEFT JOIN batches ib ON a.interview_batch_id = ib.id
                    LEFT JOIN batches pb ON a.payout_batch_id = pb.id
                    WHERE a.student_id = :student_id
                    ORDER BY a.created_at DESC, a.id DESC
                ");
                $historyStmt->execute(['student_id' => (int) $selectedRecord['id']]);
                $applicationHistory = $historyStmt->fetchAll();
                $latestApplication = $applicationHistory[0] ?? null;

                if ($applicationHistory !== []) {
                    $applicationIds = array_map(static fn ($row) => (int) $row['id'], $applicationHistory);
                    $batchIds = [];
                    foreach ($applicationHistory as $applicationRow) {
                        $interviewBatchId = (int) ($applicationRow['interview_batch_id'] ?? 0);
                        $payoutBatchId = (int) ($applicationRow['payout_batch_id'] ?? 0);
                        if ($interviewBatchId > 0) {
                            $batchIds[] = $interviewBatchId;
                        }
                        if ($payoutBatchId > 0) {
                            $batchIds[] = $payoutBatchId;
                        }
                    }
                    $batchIds = array_values(array_unique($batchIds));
                    $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));

                    $documentStmt = $db->prepare("
                        SELECT
                            d.*,
                            a.school_year,
                            a.semester,
                            a.status AS application_status
                        FROM documents d
                        JOIN applications a ON d.application_id = a.id
                        WHERE d.application_id IN ($placeholders)
                        ORDER BY d.updated_at DESC, d.id DESC
                    ");
                    $documentStmt->execute($applicationIds);
                    $documentHistory = $documentStmt->fetchAll();
                    foreach ($documentHistory as &$documentRow) {
                        $filePath = trim((string) ($documentRow['file_path'] ?? ''));
                        $fileSizeBytes = ($filePath !== '' && is_file($filePath)) ? (int) filesize($filePath) : 0;
                        $documentRow['file_size_bytes'] = $fileSizeBytes;
                        $documentRow['file_size_label'] = $this->formatFileSize($fileSizeBytes);
                    }
                    unset($documentRow);

                    $auditConditions = ['user_id = ?'];
                    $auditParams = [(string) $selectedRecord['user_id']];

                    if ($applicationIds !== []) {
                        $applicationAuditIds = array_map('strval', $applicationIds);
                        $applicationAuditPlaceholders = implode(',', array_fill(0, count($applicationAuditIds), '?'));
                        $auditConditions[] = "(entity_type = ? AND entity_id IN ($applicationAuditPlaceholders))";
                        $auditParams[] = 'application';
                        array_push($auditParams, ...$applicationAuditIds);
                    }

                    if ($batchIds !== []) {
                        $batchAuditIds = array_map('strval', $batchIds);
                        $batchAuditPlaceholders = implode(',', array_fill(0, count($batchAuditIds), '?'));
                        $auditConditions[] = "(entity_type = ? AND entity_id IN ($batchAuditPlaceholders))";
                        $auditParams[] = 'batch';
                        array_push($auditParams, ...$batchAuditIds);
                    }

                    $auditStmt = $db->prepare("
                        SELECT *
                        FROM audit_logs
                        WHERE " . implode(' OR ', $auditConditions) . "
                        ORDER BY created_at DESC, id DESC
                        LIMIT 120
                    ");
                    $auditStmt->execute($auditParams);
                    $auditHistory = $auditStmt->fetchAll();
                    $caseNotes = CaseNote::latestForApplications($applicationIds, 40);
                    $documentVersionHistory = DocumentVersion::historyForApplicationIds($applicationIds);
                    foreach ($documentVersionHistory as &$versionRow) {
                        $filePath = trim((string) ($versionRow['file_path'] ?? ''));
                        $fileSizeBytes = ($filePath !== '' && is_file($filePath)) ? (int) filesize($filePath) : 0;
                        $versionRow['file_size_bytes'] = $fileSizeBytes;
                        $versionRow['file_size_label'] = $this->formatFileSize($fileSizeBytes);
                    }
                    unset($versionRow);
                } else {
                    $auditStmt = $db->prepare("
                        SELECT *
                        FROM audit_logs
                        WHERE user_id = :user_id
                        ORDER BY created_at DESC, id DESC
                        LIMIT 80
                    ");
                    $auditStmt->execute(['user_id' => (int) $selectedRecord['user_id']]);
                    $auditHistory = $auditStmt->fetchAll();
                }

                $notificationStmt = $db->prepare("
                    SELECT *
                    FROM notifications
                    WHERE user_id = :user_id
                    ORDER BY created_at DESC, id DESC
                    LIMIT 20
                ");
                $notificationStmt->execute(['user_id' => (int) $selectedRecord['user_id']]);
                $notificationHistory = $notificationStmt->fetchAll();

                $applicationTimelines = $this->buildApplicationTimelines(
                    $applicationHistory,
                    $documentHistory,
                    $documentVersionHistory,
                    $auditHistory,
                    $caseNotes
                );
            }
        }

        return compact(
            'selectedProfileId',
            'selectedRecord',
            'applicationHistory',
            'documentHistory',
            'notificationHistory',
            'auditHistory',
            'caseNotes',
            'latestApplication',
            'applicationTimelines',
            'documentVersionHistory'
        );
    }

    public function globalSearch()
    {
        $db = Database::connect();
        $search = trim((string) ($_GET['q'] ?? ''));
        $masterRecordResults = [];

        if ($search !== '') {
            $masterRecordResults = $this->fetchGlobalSearchScholars($db, $search, 20);
        }

        require __DIR__ . '/../../views/staff/global_search.php';
    }

    public function searchSuggestions()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $db = Database::connect();
            $search = trim((string) ($_GET['q'] ?? ''));

            if ($search === '' || mb_strlen($search) < 2) {
                echo json_encode([
                    'applications' => [],
                    'scholars' => [],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }

            $masterRecordResults = array_map(function (array $scholar): array {
                return [
                    'title' => trim((string) (($scholar['last_name'] ?? '') . ', ' . ($scholar['first_name'] ?? '')), ', '),
                    'subtitle' => trim((string) (($scholar['school_name'] ?? '') . ' | ' . ($scholar['address_barangay'] ?? ''))),
                    'meta' => trim((string) (($scholar['phone_number'] ?? '') . (!empty($scholar['email']) ? ' | ' . $scholar['email'] : ''))),
                    'url' => base_url('staff/master-record/' . (int) ($scholar['profile_id'] ?? 0)),
                ];
            }, $this->fetchGlobalSearchScholars($db, $search, 5));

            echo json_encode([
                'applications' => [],
                'scholars' => $masterRecordResults,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'applications' => [],
                'scholars' => [],
                'error' => 'Unable to load suggestions.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function fetchGlobalSearchApplications(PDO $db, string $search, int $limit): array
    {
        $searchLike = '%' . $search . '%';
        $applicationStmt = $db->prepare("
            SELECT
                a.id,
                a.status,
                a.application_type,
                a.school_year,
                a.semester,
                a.created_at,
                CONCAT(p.last_name, ', ', p.first_name) AS scholar_name,
                p.address_barangay,
                p.school_name,
                p.school_type
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE
                CONCAT(p.last_name, ' ', p.first_name, ' ', COALESCE(p.middle_name, '')) LIKE :app_search_name
                OR CONCAT(p.first_name, ' ', p.last_name) LIKE :app_search_name_reverse
                OR p.school_name LIKE :app_search_school
                OR p.course LIKE :app_search_course
                OR p.address_barangay LIKE :app_search_barangay
                OR u.phone_number LIKE :app_search_phone
                OR COALESCE(u.email, '') LIKE :app_search_email
                OR CAST(a.id AS CHAR) LIKE :app_search_id
                OR CONCAT('SELGU-APP-', YEAR(a.created_at), '-', LPAD(a.id, 5, '0')) LIKE :app_search_application
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT " . max(1, (int) $limit) . "
        ");
        $applicationStmt->execute([
            'app_search_name' => $searchLike,
            'app_search_name_reverse' => $searchLike,
            'app_search_school' => $searchLike,
            'app_search_course' => $searchLike,
            'app_search_barangay' => $searchLike,
            'app_search_phone' => $searchLike,
            'app_search_email' => $searchLike,
            'app_search_id' => $searchLike,
            'app_search_application' => $searchLike,
        ]);

        return $applicationStmt->fetchAll();
    }

    private function fetchGlobalSearchScholars(PDO $db, string $search, int $limit): array
    {
        $searchLike = '%' . $search . '%';
        $scholarStmt = $db->prepare("
            SELECT
                p.id AS profile_id,
                p.user_id,
                p.last_name,
                p.first_name,
                p.middle_name,
                p.school_name,
                p.course,
                p.school_type,
                p.address_barangay,
                u.phone_number,
                u.email,
                MAX(a.created_at) AS latest_application_at,
                COUNT(a.id) AS total_applications
            FROM student_profiles p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN applications a ON a.student_id = p.id
            WHERE
                CONCAT(p.last_name, ' ', p.first_name, ' ', COALESCE(p.middle_name, '')) LIKE :scholar_search_name
                OR CONCAT(p.first_name, ' ', p.last_name) LIKE :scholar_search_name_reverse
                OR p.school_name LIKE :scholar_search_school
                OR p.course LIKE :scholar_search_course
                OR p.address_barangay LIKE :scholar_search_barangay
                OR u.phone_number LIKE :scholar_search_phone
                OR COALESCE(u.email, '') LIKE :scholar_search_email
            GROUP BY
                p.id, p.user_id, p.last_name, p.first_name, p.middle_name,
                p.school_name, p.course, p.school_type, p.address_barangay, u.phone_number, u.email
            ORDER BY latest_application_at DESC, p.last_name ASC, p.first_name ASC
            LIMIT " . max(1, (int) $limit) . "
        ");
        $scholarStmt->execute([
            'scholar_search_name' => $searchLike,
            'scholar_search_name_reverse' => $searchLike,
            'scholar_search_school' => $searchLike,
            'scholar_search_course' => $searchLike,
            'scholar_search_barangay' => $searchLike,
            'scholar_search_phone' => $searchLike,
            'scholar_search_email' => $searchLike,
        ]);

        return $scholarStmt->fetchAll();
    }

    private function applicationNumberLabel(array $application): string
    {
        return 'SELGU-APP-' . date('Y', strtotime((string) ($application['created_at'] ?? 'now'))) . '-' . str_pad((string) ($application['id'] ?? 0), 5, '0', STR_PAD_LEFT);
    }

    public function toggleArchiveApplication()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $db = Database::connect();

        try {
            $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
            $archiveAction = Validation::enum($_POST['archive_action'] ?? '', ['archive', 'unarchive'], 'Archive action');
            $redirectQuery = trim((string) ($_POST['redirect_query'] ?? ''));

            if (!$applicationId) {
                throw new ValidationException('Invalid application record.');
            }

            $applicationStmt = $db->prepare("
                SELECT id, status, is_archived
                FROM applications
                WHERE id = :application_id
                LIMIT 1
            ");
            $applicationStmt->execute(['application_id' => $applicationId]);
            $application = $applicationStmt->fetch();

            if (!$application) {
                throw new ValidationException('Application record not found.');
            }

            $status = (string) ($application['status'] ?? '');
            $isArchived = (int) ($application['is_archived'] ?? 0) === 1;

            if ($archiveAction === 'archive') {
                if (!$this->canBeArchived($status)) {
                    throw new ValidationException('Only completed application records can be archived.');
                }

                if ($isArchived) {
                    throw new ValidationException('This application record is already archived.');
                }

                $updateStmt = $db->prepare("
                    UPDATE applications
                    SET is_archived = 1,
                        archived_at = NOW(),
                        archived_by_user_id = :user_id
                    WHERE id = :application_id
                ");
                $updateStmt->execute([
                    'user_id' => (int) ($_SESSION['user_id'] ?? 0),
                    'application_id' => $applicationId,
                ]);

                AuditLog::recordCurrentUser(
                    'application.archived',
                    'application',
                    $applicationId,
                    'Archived a completed scholarship application record.',
                    ['status' => $status]
                );

                redirect_with_flash('staff/archive' . $redirectQuery, 'success', 'Application record archived successfully.');
            }

            if (!$isArchived) {
                throw new ValidationException('This application record is not archived.');
            }

            $updateStmt = $db->prepare("
                UPDATE applications
                SET is_archived = 0,
                    archived_at = NULL,
                    archived_by_user_id = NULL
                WHERE id = :application_id
            ");
            $updateStmt->execute(['application_id' => $applicationId]);

            AuditLog::recordCurrentUser(
                'application.unarchived',
                'application',
                $applicationId,
                'Returned an archived scholarship application record to the completed record set.',
                ['status' => $status]
            );

            redirect_with_flash('staff/archive' . $redirectQuery, 'success', 'Application record restored from archive successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('staff/archive', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to toggle archive state: ' . $e->getMessage());
            redirect_with_flash('staff/archive', 'error', 'Unable to update the archive state right now.');
        }
    }

    private function buildApplicationTimelines(
        array $applicationHistory,
        array $documentHistory,
        array $documentVersionHistory,
        array $auditHistory,
        array $caseNotes
    ): array {
        $timelines = [];
        $interviewBatchApplications = [];
        $payoutBatchApplications = [];
        $scheduleAuditAdded = [
            'interview' => [],
            'payout' => [],
        ];

        foreach ($applicationHistory as $application) {
            $applicationId = (int) ($application['id'] ?? 0);
            if ($applicationId <= 0) {
                continue;
            }

            $interviewBatchId = (int) ($application['interview_batch_id'] ?? 0);
            if ($interviewBatchId > 0) {
                $interviewBatchApplications[$interviewBatchId][] = $applicationId;
            }

            $payoutBatchId = (int) ($application['payout_batch_id'] ?? 0);
            if ($payoutBatchId > 0) {
                $payoutBatchApplications[$payoutBatchId][] = $applicationId;
            }

            $timelines[$applicationId] = [
                'application' => $application,
                'entries' => [],
            ];
        }

        foreach ($applicationHistory as $application) {
            $applicationId = (int) ($application['id'] ?? 0);
            if ($applicationId <= 0 || !isset($timelines[$applicationId])) {
                continue;
            }

            $timelines[$applicationId]['entries'][] = [
                'time' => (string) ($application['created_at'] ?? ''),
                'icon' => 'fa-file-circle-plus',
                'badge_class' => 'text-bg-primary',
                'title' => 'Application submitted',
                'actor' => 'Student',
                'details' => 'Scholarship application record created.',
                'meta' => array_filter([
                    'Application Type' => (string) ($application['application_type'] ?? ''),
                ]),
            ];

            if (!empty($application['interview_result_at']) || !empty($application['interview_result'])) {
                $timelines[$applicationId]['entries'][] = [
                    'time' => (string) ($application['interview_result_at'] ?? $application['updated_at'] ?? $application['created_at'] ?? ''),
                    'icon' => 'fa-comments',
                    'badge_class' => ($application['interview_result'] ?? '') === 'Passed' ? 'text-bg-success' : 'text-bg-danger',
                    'title' => 'Interview result recorded',
                    'actor' => 'Staff',
                    'details' => 'Interview outcome saved for the application.',
                    'meta' => array_filter([
                        'Application ID' => $applicationId > 0 ? 'App ID ' . $applicationId : null,
                        'Result' => (string) ($application['interview_result'] ?? ''),
                        'Status After Interview' => str_replace('_', ' ', (string) ($application['status'] ?? '')),
                    ]),
                ];
            }

        }

        $documentTimelineAdded = [];
        foreach ($documentVersionHistory as $version) {
            $applicationId = (int) ($version['application_id'] ?? 0);
            if ($applicationId <= 0 || !isset($timelines[$applicationId])) {
                continue;
            }

            $entryTime = (string) ($version['created_at'] ?? '');
            if (trim($entryTime) === '') {
                continue;
            }

            $documentId = (int) ($version['document_id'] ?? 0);
            $versionNumber = (int) ($version['version_number'] ?? 0);
            $documentType = (string) ($version['document_type'] ?? 'Document');
            $status = (string) ($version['document_status'] ?? 'Pending');
            $sourceAction = (string) ($version['source_action'] ?? '');
            $entryKey = implode(':', [$applicationId, $documentId, $versionNumber, $status, $entryTime]);
            $documentTimelineAdded[$entryKey] = true;

            $actor = (string) ($version['uploader_role'] ?? '');
            if ($actor === '') {
                $actor = $status === 'Pending' ? 'Student' : 'Staff / Admin';
            }

            $title = match (true) {
                $status === 'Pending' && in_array($sourceAction, ['Initial Upload', 'SOA Upload'], true) => $documentType . ' submitted for review',
                $status === 'Pending' && in_array($sourceAction, ['Resubmission', 'SOA Resubmission'], true) => $documentType . ' resubmitted for review',
                $status === 'Verified' => $documentType . ' verified',
                $status === 'Rejected' => $documentType . ' rejected',
                default => $documentType . ' ' . strtolower($status),
            };

            $details = match (true) {
                $status === 'Pending' && in_array($sourceAction, ['Initial Upload', 'SOA Upload'], true) => 'A document was uploaded and is waiting for review.',
                $status === 'Pending' && in_array($sourceAction, ['Resubmission', 'SOA Resubmission'], true) => 'A corrected document was resubmitted and is waiting for review.',
                $status === 'Verified' => 'A document was marked as verified.',
                $status === 'Rejected' => 'A document was rejected and returned for correction.',
                default => 'A document update was recorded.',
            };

            $timelines[$applicationId]['entries'][] = [
                'time' => $entryTime,
                'icon' => $status === 'Rejected' ? 'fa-file-circle-xmark' : ($status === 'Verified' ? 'fa-file-circle-check' : 'fa-file-arrow-up'),
                'badge_class' => $status === 'Rejected' ? 'text-bg-danger' : ($status === 'Verified' ? 'text-bg-success' : 'text-bg-secondary'),
                'title' => $title,
                'actor' => $actor,
                'details' => $details,
                'meta' => array_filter([
                    'Document Type' => $documentType,
                    'Version' => $versionNumber > 0 ? 'V' . $versionNumber : null,
                    'Source Action' => $sourceAction,
                    'Remarks' => (string) ($version['rejection_remarks'] ?? ''),
                ]),
            ];
        }

        foreach ($documentHistory as $document) {
            $applicationId = (int) ($document['application_id'] ?? 0);
            if ($applicationId <= 0 || !isset($timelines[$applicationId])) {
                continue;
            }

            $documentId = (int) ($document['id'] ?? 0);
            $status = (string) ($document['status'] ?? '');
            $entryTime = (string) ($document['updated_at'] ?? '');
            $entryKey = implode(':', [$applicationId, $documentId, 0, $status, $entryTime]);
            if (isset($documentTimelineAdded[$entryKey])) {
                continue;
            }

            $documentType = (string) ($document['document_type'] ?? 'Document');
            $timelines[$applicationId]['entries'][] = [
                'time' => $entryTime,
                'icon' => $status === 'Rejected' ? 'fa-file-circle-xmark' : ($status === 'Verified' ? 'fa-file-circle-check' : 'fa-file-arrow-up'),
                'badge_class' => $status === 'Rejected' ? 'text-bg-danger' : ($status === 'Verified' ? 'text-bg-success' : 'text-bg-secondary'),
                'title' => $documentType . ' ' . strtolower($status === 'Pending' ? 'submitted for review' : $status),
                'actor' => $status === 'Pending' ? 'Student' : 'Staff / Admin',
                'details' => $status === 'Rejected'
                    ? 'A document was rejected and returned for correction.'
                    : ($status === 'Verified' ? 'A document was marked as verified.' : 'A document was uploaded or resubmitted.'),
                'meta' => array_filter([
                    'Document Type' => $documentType,
                    'Remarks' => (string) ($document['rejection_remarks'] ?? ''),
                ]),
            ];
        }

        foreach ($caseNotes as $note) {
            $applicationId = (int) ($note['application_id'] ?? 0);
            if ($applicationId <= 0 || !isset($timelines[$applicationId])) {
                continue;
            }

            $timelines[$applicationId]['entries'][] = [
                'time' => (string) ($note['created_at'] ?? ''),
                'icon' => 'fa-note-sticky',
                'badge_class' => 'text-bg-warning',
                'title' => 'Staff note added',
                'actor' => (string) ($note['author_role'] ?? 'Staff'),
                'details' => (string) ($note['note_text'] ?? ''),
                'meta' => [],
            ];
        }

        foreach ($auditHistory as $log) {
            $entityType = (string) ($log['entity_type'] ?? '');
            $action = (string) ($log['action'] ?? '');
            $metadata = json_decode((string) ($log['metadata_json'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];

            if (
                $entityType === 'batch'
                && in_array($action, ['interview_batch.created', 'interview_batch.rescheduled', 'payout_batch.created', 'payout_batch.rescheduled'], true)
            ) {
                $batchId = (int) ($log['entity_id'] ?? 0);
                $isInterview = str_starts_with($action, 'interview_batch.');
                $targetApplications = $isInterview
                    ? ($interviewBatchApplications[$batchId] ?? [])
                    : ($payoutBatchApplications[$batchId] ?? []);

                if ($targetApplications === []) {
                    continue;
                }

                $isRescheduled = str_ends_with($action, '.rescheduled');
                $entryTime = (string) (
                    $metadata[$isRescheduled ? 'new_schedule' : 'scheduled_date']
                    ?? $log['created_at']
                    ?? ''
                );
                $venue = (string) (
                    $metadata[$isRescheduled ? 'new_venue' : 'venue']
                    ?? ''
                );
                $batchName = (string) ($metadata['batch_name'] ?? '');

                foreach ($targetApplications as $applicationId) {
                    if (!isset($timelines[$applicationId])) {
                        continue;
                    }

                    $scheduleAuditAdded[$isInterview ? 'interview' : 'payout'][$applicationId] = true;
                    $timelines[$applicationId]['entries'][] = [
                        'time' => $entryTime,
                        'icon' => $isInterview ? 'fa-calendar-check' : 'fa-money-check-dollar',
                        'badge_class' => $isInterview ? 'text-bg-info' : 'text-bg-success',
                        'title' => $isInterview
                            ? ($isRescheduled ? 'Interview rescheduled' : 'Interview scheduled')
                            : ($isRescheduled ? 'Payout rescheduled' : 'Payout scheduled'),
                        'actor' => (string) ($log['actor_role'] ?? ($isInterview ? 'Staff / Admin' : 'Admin')),
                        'details' => $isInterview
                            ? ($isRescheduled ? 'Interview schedule was updated for the applicant.' : 'Interview batch assigned to the applicant.')
                            : ($isRescheduled ? 'Payout schedule was updated for the scholar.' : 'Approved scholar added to a payout batch.'),
                        'meta' => array_filter([
                            'Application ID' => 'App ID ' . $applicationId,
                            'Batch' => $batchName,
                            'Venue' => $venue,
                            'Old Schedule' => $isRescheduled ? (string) ($metadata['old_schedule'] ?? '') : null,
                            'Old Venue' => $isRescheduled ? (string) ($metadata['old_venue'] ?? '') : null,
                        ]),
                    ];
                }

                continue;
            }

            if ($entityType !== 'application') {
                continue;
            }

            if ($action === 'application.submitted') {
                continue;
            }

            $applicationId = (int) ($log['entity_id'] ?? 0);
            if ($applicationId <= 0 || !isset($timelines[$applicationId])) {
                continue;
            }

            $timelines[$applicationId]['entries'][] = [
                'time' => (string) ($log['created_at'] ?? ''),
                'icon' => 'fa-clock-rotate-left',
                'badge_class' => 'text-bg-dark',
                'title' => $this->humanizeAuditAction($action ?: 'System activity'),
                'actor' => (string) ($log['actor_role'] ?? 'System'),
                'details' => (string) ($log['description'] ?? ''),
                'meta' => $metadata,
            ];
        }

        foreach ($applicationHistory as $application) {
            $applicationId = (int) ($application['id'] ?? 0);
            if ($applicationId <= 0 || !isset($timelines[$applicationId])) {
                continue;
            }

            if (!isset($scheduleAuditAdded['interview'][$applicationId]) && !empty($application['interview_schedule'])) {
                $timelines[$applicationId]['entries'][] = [
                    'time' => (string) ($application['interview_schedule'] ?? ''),
                    'icon' => 'fa-calendar-check',
                    'badge_class' => 'text-bg-info',
                    'title' => 'Interview scheduled',
                    'actor' => 'Staff / Admin',
                    'details' => 'Interview batch assigned to the applicant.',
                    'meta' => array_filter([
                        'Application ID' => 'App ID ' . $applicationId,
                        'Batch' => (string) ($application['interview_batch_name'] ?? ''),
                        'Venue' => (string) ($application['interview_venue'] ?? ''),
                    ]),
                ];
            }

            if (!isset($scheduleAuditAdded['payout'][$applicationId]) && !empty($application['payout_schedule'])) {
                $timelines[$applicationId]['entries'][] = [
                    'time' => (string) ($application['payout_schedule'] ?? ''),
                    'icon' => 'fa-money-check-dollar',
                    'badge_class' => 'text-bg-success',
                    'title' => 'Payout scheduled',
                    'actor' => 'Admin',
                    'details' => 'Approved scholar added to a payout batch.',
                    'meta' => array_filter([
                        'Application ID' => 'App ID ' . $applicationId,
                        'Batch' => (string) ($application['payout_batch_name'] ?? ''),
                        'Venue' => (string) ($application['payout_venue'] ?? ''),
                    ]),
                ];
            }
        }

        foreach ($timelines as &$timelineGroup) {
            $timelineGroup['entries'] = array_values(array_filter(
                $timelineGroup['entries'],
                static fn(array $entry): bool => trim((string) ($entry['time'] ?? '')) !== ''
            ));

            usort($timelineGroup['entries'], static function (array $left, array $right): int {
                $leftTime = strtotime((string) ($left['time'] ?? ''));
                $rightTime = strtotime((string) ($right['time'] ?? ''));

                if ($leftTime === $rightTime) {
                    $leftIsSubmitted = (string) ($left['title'] ?? '') === 'Application submitted';
                    $rightIsSubmitted = (string) ($right['title'] ?? '') === 'Application submitted';

                    if ($leftIsSubmitted !== $rightIsSubmitted) {
                        return $leftIsSubmitted ? -1 : 1;
                    }

                    return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
                }

                return $leftTime <=> $rightTime;
            });
        }
        unset($timelineGroup);

        return $timelines;
    }

    private function applicationLifecycleSql(string $alias): string
    {
        return "CASE
            WHEN {$alias}.is_archived = 1 THEN 'Archived'
            WHEN {$alias}.status IN ('Approved_Finished', 'Not_Eligible', 'Forfeited') THEN 'Completed'
            ELSE 'Current'
        END";
    }

    private function canBeArchived(string $status): bool
    {
        return in_array($status, ['Approved_Finished', 'Not_Eligible', 'Forfeited'], true);
    }

    private function humanizeAuditAction(string $action): string
    {
        $action = trim($action);
        if ($action === '') {
            return 'System activity';
        }

        $action = str_replace(['.', '_'], ' ', $action);
        return ucwords($action);
    }

    private function applicationQueueMap(): array
    {
        return [
            'under_review' => [
                'label' => 'Under Review',
                'description' => 'Needs staff document checking',
                'empty_title' => 'No applications need review right now',
                'empty_copy' => 'New submissions and review returns will appear here once staff action is needed.',
                'statuses' => ['Submitted', 'Initial_Review'],
            ],
            'needs_resubmission' => [
                'label' => 'Needs Correction',
                'description' => 'Waiting on corrected files',
                'empty_title' => 'No applications are waiting for corrections',
                'empty_copy' => 'Applicants who need to replace rejected files will appear here.',
                'statuses' => ['Pending_Resubmission', 'SOA_Resubmission_Required'],
            ],
            'for_interview' => [
                'label' => 'For Interview',
                'description' => 'Ready for interview scheduling',
                'empty_title' => 'No applicants are waiting for interview scheduling',
                'empty_copy' => 'Eligible applicants without an interview batch will appear here.',
                'statuses' => ['For_Interview'],
            ],
            'for_soa' => [
                'label' => 'SOA Phase',
                'description' => 'Awaiting or reviewing SOA',
                'empty_title' => 'No applications are in the SOA phase',
                'empty_copy' => 'Applications waiting for a Statement of Account or under SOA review will appear here.',
                'statuses' => ['Eligible_Awaiting_SOA', 'SOA_Under_Review', 'SOA_Overdue'],
            ],
            'ready_for_payout' => [
                'label' => 'Ready for Payout',
                'description' => 'Approved and ready to finalize',
                'empty_title' => 'No approved scholars are ready for payout',
                'empty_copy' => 'Fully approved records waiting for payroll or payout scheduling will appear here.',
                'statuses' => ['Approved_Pending_Payroll'],
            ],
            'completed' => [
                'label' => 'Completed',
                'description' => 'Closed scholarship records',
                'empty_title' => 'No completed records match this view',
                'empty_copy' => 'Finished, not eligible, or forfeited records will appear here when included.',
                'statuses' => ['Approved_Finished', 'Not_Eligible', 'Forfeited'],
            ],
            'all' => [
                'label' => 'All Applications',
                'description' => 'Every application in the current period',
                'empty_title' => 'No applications match this board view',
                'empty_copy' => 'Try widening the filters or checking whether the current application period has records.',
                'statuses' => [],
            ],
        ];
    }

    private function findInterviewBatchApplicationIds(\PDO $db, string $schoolTypeFilter, string $barangayFilter, ?array $currentPeriod = null): array
    {
        $conditions = [
            "a.status = 'For_Interview'",
            'a.interview_batch_id IS NULL',
        ];
        $params = [];

        if (is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])) {
            $conditions[] = 'a.school_year = :school_year';
            $conditions[] = 'a.semester = :semester';
            $params['school_year'] = (string) $currentPeriod['school_year'];
            $params['semester'] = (string) $currentPeriod['semester'];
        }

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

    private function resolveApplicationQueue(string $status): string
    {
        foreach ($this->applicationQueueMap() as $queueKey => $queueConfig) {
            if ($queueKey === 'all') {
                continue;
            }
            if (in_array($status, $queueConfig['statuses'], true)) {
                return $queueKey;
            }
        }

        return 'all';
    }

    private function applicationQueueLabel(string $queueKey): string
    {
        return (string) ($this->applicationQueueMap()[$queueKey]['label'] ?? 'Applications');
    }

    private function applicationNextAction(string $status): array
    {
        return match ($status) {
            'Submitted', 'Initial_Review' => [
                'title' => 'Review documents',
                'detail' => 'Check the uploaded requirements, photo, and signature before moving the application forward.',
            ],
            'Pending_Resubmission' => [
                'title' => 'Await corrected files',
                'detail' => 'The applicant needs to replace rejected initial requirements before review can continue.',
            ],
            'For_Interview' => [
                'title' => 'Schedule or finalize interview',
                'detail' => 'Add the applicant to an interview batch or record the interview result if already scheduled.',
            ],
            'Eligible_Awaiting_SOA' => [
                'title' => 'Wait for SOA upload',
                'detail' => 'The applicant passed the interview and must upload the Statement of Account.',
            ],
            'SOA_Under_Review' => [
                'title' => 'Review submitted SOA',
                'detail' => 'Verify the uploaded Statement of Account and prepare the application for final approval.',
            ],
            'SOA_Resubmission_Required' => [
                'title' => 'Await corrected SOA',
                'detail' => 'The applicant needs to submit a corrected Statement of Account.',
            ],
            'SOA_Overdue' => [
                'title' => 'Resolve overdue SOA',
                'detail' => 'The SOA deadline has passed. Extend the deadline or resolve the record with the LGU office.',
            ],
            'Approved_Pending_Payroll' => [
                'title' => 'Assign to payout batch',
                'detail' => 'This approved scholar is ready for final approval export and payout scheduling.',
            ],
            'Approved_Finished' => [
                'title' => 'Record completed',
                'detail' => 'The scholarship record is complete and can be retained or archived as needed.',
            ],
            'Not_Eligible' => [
                'title' => 'Record closed as not eligible',
                'detail' => 'No further processing is needed unless the office must issue a notice or archive the record.',
            ],
            'Forfeited' => [
                'title' => 'Record closed as forfeited',
                'detail' => 'The applicant did not complete the required process and the record can move to retention/archive handling.',
            ],
            default => [
                'title' => 'Review this application',
                'detail' => 'Open the application workspace and confirm the next required step.',
            ],
        };
    }

    private function buildWorkflowSteps(string $status): array
    {
        $stepIndex = match ($status) {
            'Submitted', 'Initial_Review', 'Pending_Resubmission' => 2,
            'For_Interview', 'Not_Eligible' => 3,
            'Eligible_Awaiting_SOA', 'SOA_Under_Review', 'SOA_Resubmission_Required', 'SOA_Overdue' => 4,
            'Approved_Pending_Payroll' => 5,
            'Approved_Finished', 'Forfeited' => 6,
            default => 2,
        };

        $labels = ['Submitted', 'Review', 'Interview', 'SOA', 'Payout', 'Completed'];
        $steps = [];

        foreach ($labels as $index => $label) {
            $position = $index + 1;
            $state = 'upcoming';
            if ($position < $stepIndex) {
                $state = 'complete';
            } elseif ($position === $stepIndex) {
                $state = 'current';
            }

            $steps[] = [
                'label' => $label,
                'short_label' => $label,
                'state' => $state,
            ];
        }

        return $steps;
    }

    private function applicationStatusBadge(string $status): array
    {
        return match ($status) {
            'Submitted', 'Initial_Review' => [
                'class' => 'app-status-badge app-status-review',
                'tone' => 'Review',
            ],
            'Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue' => [
                'class' => 'app-status-badge app-status-correction',
                'tone' => 'Correction',
            ],
            'For_Interview' => [
                'class' => 'app-status-badge app-status-interview',
                'tone' => 'Interview',
            ],
            'Eligible_Awaiting_SOA', 'SOA_Under_Review' => [
                'class' => 'app-status-badge app-status-soa',
                'tone' => 'SOA',
            ],
            'Approved_Pending_Payroll' => [
                'class' => 'app-status-badge app-status-payout',
                'tone' => 'Payout',
            ],
            'Approved_Finished' => [
                'class' => 'app-status-badge app-status-complete',
                'tone' => 'Completed',
            ],
            'Not_Eligible', 'Forfeited' => [
                'class' => 'app-status-badge app-status-closed',
                'tone' => 'Closed',
            ],
            default => [
                'class' => 'app-status-badge app-status-default',
                'tone' => 'Status',
            ],
        };
    }

    private function applicationPrimaryActionLabel(string $status): string
    {
        return match ($status) {
            'Submitted', 'Initial_Review' => 'Review',
            'Pending_Resubmission', 'SOA_Resubmission_Required' => 'Check Return',
            'For_Interview' => 'Schedule',
            'Eligible_Awaiting_SOA' => 'Track SOA',
            'SOA_Under_Review' => 'Check SOA',
            'SOA_Overdue' => 'Resolve',
            'Approved_Pending_Payroll' => 'Finalize',
            'Approved_Finished', 'Not_Eligible', 'Forfeited' => 'View',
            default => 'Open',
        };
    }

    private function formatRelativeTime(string $dateTime): string
    {
        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return 'Unknown update time';
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Updated just now';
        }

        if ($diff < 3600) {
            $minutes = max(1, (int) floor($diff / 60));
            return 'Updated ' . $minutes . ' min ago';
        }

        if ($diff < 86400) {
            $hours = max(1, (int) floor($diff / 3600));
            return 'Updated ' . $hours . ' hr' . ($hours === 1 ? '' : 's') . ' ago';
        }

        $days = max(1, (int) floor($diff / 86400));
        return 'Updated ' . $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, $unitIndex === 0 ? 0 : 2) . ' ' . $units[$unitIndex];
    }

    private function applicationUrgencyLabel(string $dateTime): array
    {
        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return [
                'label' => 'Needs review',
                'class' => 'app-urgency-chip app-urgency-neutral',
            ];
        }

        $days = (time() - $timestamp) / 86400;
        if ($days >= 7) {
            return [
                'label' => 'Waiting 7+ days',
                'class' => 'app-urgency-chip app-urgency-high',
            ];
        }

        if ($days >= 3) {
            return [
                'label' => 'Waiting 3+ days',
                'class' => 'app-urgency-chip app-urgency-medium',
            ];
        }

        return [
            'label' => 'Recently updated',
            'class' => 'app-urgency-chip app-urgency-low',
        ];
    }

    private function formatApplicationNumber(int $applicationId, string $createdAt): string
    {
        $year = $createdAt !== '' ? date('Y', strtotime($createdAt)) : date('Y');
        return 'SELGU-APP-' . $year . '-' . str_pad((string) $applicationId, 5, '0', STR_PAD_LEFT);
    }

    private function loadApplicationReviewData(int $applicationId): ?array
    {
        if ($applicationId <= 0) {
            return null;
        }

        $db = Database::connect();
        $stmtApp = $db->prepare("
            SELECT
                a.*,
                p.*,
                u.email,
                u.phone_number,
                " . $this->applicationLifecycleSql('a') . " AS record_lifecycle
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmtApp->execute(['id' => $applicationId]);
        $applicant = $stmtApp->fetch();

        if (!$applicant) {
            return null;
        }

        $stmtDocs = $db->prepare("
            SELECT *
            FROM documents
            WHERE application_id = :aid
            ORDER BY
                FIELD(status, 'Pending', 'Rejected', 'Verified'),
                updated_at DESC,
                uploaded_at DESC,
                id DESC
        ");
        $stmtDocs->execute(['aid' => $applicationId]);
        $documents = $stmtDocs->fetchAll();
        $caseNotes = CaseNote::latestForApplication($applicationId, 25);

        $documentVersionHistory = [];
        $documentVersionRows = [];
        if ($documents !== []) {
            $documentIds = array_map(static fn(array $doc): int => (int) ($doc['id'] ?? 0), $documents);
            $documentVersionRows = DocumentVersion::historyForDocumentIds($documentIds);
            foreach ($documentVersionRows as $versionRow) {
                $documentVersionHistory[(int) ($versionRow['document_id'] ?? 0)][] = $versionRow;
            }
        }

        $auditStmt = $db->prepare("
            SELECT *
            FROM audit_logs
            WHERE (entity_type = 'application' AND entity_id = :application_id)
               OR (
                    entity_type = 'batch'
                    AND entity_id IN (:interview_batch_id, :payout_batch_id)
               )
            ORDER BY created_at DESC, id DESC
            LIMIT 80
        ");
        $auditStmt->execute([
            'application_id' => (string) $applicationId,
            'interview_batch_id' => (string) ((int) ($applicant['interview_batch_id'] ?? 0)),
            'payout_batch_id' => (string) ((int) ($applicant['payout_batch_id'] ?? 0)),
        ]);
        $auditHistory = $auditStmt->fetchAll();

        $timelineGroups = $this->buildApplicationTimelines(
            [$applicant],
            $documents,
            array_map(
                static function (array $row) use ($applicationId): array {
                    $row['application_id'] = $applicationId;
                    return $row;
                },
                $documentVersionRows
            ),
            $auditHistory,
            $caseNotes
        );

        $applicationTimeline = $timelineGroups[$applicationId]['entries'] ?? [];
        $nextAction = $this->applicationNextAction((string) ($applicant['status'] ?? ''));
        $workflowSteps = $this->buildWorkflowSteps((string) ($applicant['status'] ?? ''));
        $applicationNumber = $this->formatApplicationNumber($applicationId, (string) ($applicant['created_at'] ?? ''));
        $currentPeriod = ApplicationPeriod::getCurrent();

        $documentChecklist = [
            [
                'label' => 'Grades document',
                'complete' => $this->hasDocumentStatus($documents, 'Grades', 'Verified'),
                'value' => $this->documentStatusLabel($documents, 'Grades'),
            ],
            [
                'label' => 'Barangay Residency document',
                'complete' => $this->hasDocumentStatus($documents, 'Barangay Residency', 'Verified'),
                'value' => $this->documentStatusLabel($documents, 'Barangay Residency'),
            ],
            [
                'label' => 'Statement of Account',
                'complete' => $this->hasDocumentStatus($documents, 'SOA', 'Verified'),
                'value' => $this->documentStatusLabel($documents, 'SOA'),
            ],
            [
                'label' => '2x2 photo',
                'complete' => !empty($applicant['id_picture_path']),
                'value' => !empty($applicant['id_picture_path']) ? 'Saved with application' : 'No photo saved',
            ],
            [
                'label' => 'E-signature',
                'complete' => !empty($applicant['e_signature_path']),
                'value' => !empty($applicant['e_signature_path']) ? 'Saved with application' : 'No signature saved',
            ],
        ];

        $reviewBlockers = [];
        foreach ($documentChecklist as $checkItem) {
            if (!empty($checkItem['complete'])) {
                continue;
            }

            $reviewBlockers[] = [
                'label' => (string) ($checkItem['label'] ?? 'Requirement'),
                'detail' => (string) ($checkItem['value'] ?? 'Pending'),
            ];
        }

        $pendingDocumentCount = 0;
        $rejectedDocumentCount = 0;
        $firstPendingDocumentId = null;

        foreach ($documents as &$document) {
            $status = (string) ($document['status'] ?? 'Pending');
            $document['status_badge'] = $this->documentStatusBadge($status);
            $document['review_hint'] = $this->documentReviewHint($status);
            $document['relative_updated_at'] = $this->formatRelativeTime((string) ($document['updated_at'] ?? $document['uploaded_at'] ?? ''));
            $document['review_priority'] = $this->documentReviewPriority($status, (string) ($document['updated_at'] ?? $document['uploaded_at'] ?? ''));
            $document['is_pending_review'] = $status === 'Pending';
            $document['is_rejected'] = $status === 'Rejected';

            if ($status === 'Pending') {
                $pendingDocumentCount++;
                if ($firstPendingDocumentId === null) {
                    $firstPendingDocumentId = (int) ($document['id'] ?? 0);
                }
            }

            if ($status === 'Rejected') {
                $rejectedDocumentCount++;
            }
        }
        unset($document);

        $reviewSummary = [
            'pending_count' => $pendingDocumentCount,
            'rejected_count' => $rejectedDocumentCount,
            'verified_count' => max(0, count($documents) - $pendingDocumentCount - $rejectedDocumentCount),
            'blocker_count' => count($reviewBlockers),
            'next_rule' => $this->applicationCompletionRule((string) ($applicant['status'] ?? '')),
            'first_pending_document_id' => $firstPendingDocumentId,
        ];

        return compact(
            'applicationId',
            'applicant',
            'documents',
            'caseNotes',
            'documentVersionHistory',
            'applicationTimeline',
            'nextAction',
            'workflowSteps',
            'applicationNumber',
            'documentChecklist',
            'currentPeriod',
            'reviewBlockers',
            'reviewSummary'
        );
    }

    private function hasDocumentStatus(array $documents, string $documentType, string $status): bool
    {
        foreach ($documents as $document) {
            if (\document_type_matches((string) ($document['document_type'] ?? ''), $documentType) && (string) ($document['status'] ?? '') === $status) {
                return true;
            }
        }

        return false;
    }

    private function documentStatusLabel(array $documents, string $documentType): string
    {
        foreach ($documents as $document) {
            if (\document_type_matches((string) ($document['document_type'] ?? ''), $documentType)) {
                return (string) ($document['status'] ?? 'Pending');
            }
        }

        return 'Not submitted';
    }

    private function documentStatusBadge(string $status): array
    {
        return match ($status) {
            'Verified' => [
                'class' => 'app-status-badge app-status-complete',
                'label' => 'Verified',
            ],
            'Rejected' => [
                'class' => 'app-status-badge app-status-correction',
                'label' => 'Returned',
            ],
            default => [
                'class' => 'app-status-badge app-status-review',
                'label' => 'Needs Review',
            ],
        };
    }

    private function documentReviewHint(string $status): string
    {
        return match ($status) {
            'Verified' => 'This requirement is already accepted.',
            'Rejected' => 'This file was returned to the applicant and needs a corrected submission.',
            default => 'Review the file, then verify it or return it with remarks.',
        };
    }

    private function documentReviewPriority(string $status, string $updatedAt): array
    {
        if ($status === 'Rejected') {
            return [
                'label' => 'Returned to applicant',
                'class' => 'app-urgency-chip app-urgency-medium',
            ];
        }

        if ($status === 'Verified') {
            return [
                'label' => 'Completed',
                'class' => 'app-urgency-chip app-urgency-low',
            ];
        }

        return $this->applicationUrgencyLabel($updatedAt);
    }

    private function applicationCompletionRule(string $status): string
    {
        return match ($status) {
            'Submitted', 'Initial_Review', 'Pending_Resubmission' => 'Verify the required initial documents, photo, and e-signature to move this applicant forward.',
            'Eligible_Awaiting_SOA', 'SOA_Under_Review', 'SOA_Resubmission_Required' => 'Verify the Statement of Account to prepare this application for final approval.',
            'For_Interview' => 'Use this workspace to confirm every required requirement before interview handling continues.',
            default => 'Complete the remaining checklist items shown on this page to keep the workflow moving.',
        };
    }

    // Load the split-screen view to check a specific student's documents
    public function verifyDocuments($applicationId)
    {
        $reviewData = $this->loadApplicationReviewData((int) $applicationId);

        if ($reviewData === null) {
            redirect_with_flash('staff/applications', 'error', 'Application not found.');
        }

        extract($reviewData, EXTR_SKIP);

        require __DIR__ . '/../../views/staff/verification.php';
    }

    public function documentPreview($documentId)
    {
        $documentId = (int) $documentId;
        if ($documentId <= 0) {
            http_response_code(404);
            exit('Document not found.');
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT id, file_path
            FROM documents
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $documentId]);
        $document = $stmt->fetch();

        $path = trim((string) ($document['file_path'] ?? ''));
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

    public function documentVersionPreview($versionId)
    {
        $versionId = (int) $versionId;
        if ($versionId <= 0) {
            http_response_code(404);
            exit('Document version not found.');
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT id, file_path
            FROM document_versions
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $versionId]);
        $version = $stmt->fetch();

        $path = trim((string) ($version['file_path'] ?? ''));
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

    public function addCaseNote()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
            $noteText = Validation::requiredString($_POST['note_text'] ?? '', 'Case note', 3000);

            if (!$applicationId) {
                throw new ValidationException('Invalid application for case note.');
            }

            CaseNote::create($applicationId, (int) ($_SESSION['user_id'] ?? 0), $noteText);

            AuditLog::recordCurrentUser(
                'case_note.created',
                'application',
                $applicationId,
                'Added an internal case note.',
                ['note_length' => strlen($noteText)]
            );

            redirect_with_flash('staff/verify-documents/' . $applicationId, 'success', 'Case note added successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('staff/dashboard', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to add case note: ' . $e->getMessage());
            redirect_with_flash('staff/dashboard', 'error', 'Unable to save the case note right now.');
        }
    }

    // ---------------------------------------------------------
    // GRANULAR DOCUMENT VERIFICATION LOGIC
    // ---------------------------------------------------------
    public function updateDocumentStatus()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $db = Database::connect();
        $appId = (string) ($_POST['application_id'] ?? '');

        try {
            $docId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
            $appId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
            $status = Validation::enum($_POST['status'] ?? '', ['Verified', 'Rejected'], 'Document status');
            $remarks = Validation::optionalString($_POST['rejection_remarks'] ?? '', 1000);

            if (!$docId || !$appId) {
                throw new ValidationException('Invalid document request.');
            }

            $db->beginTransaction();

            // 1. Update the specific document
            $stmt = $db->prepare("UPDATE documents SET status = :status, rejection_remarks = :remarks WHERE id = :id");
            $stmt->execute(['status' => $status, 'remarks' => $remarks, 'id' => $docId]);
            DocumentVersion::appendReviewState((int) $docId, $status, $remarks, (int) ($_SESSION['user_id'] ?? 0));

            // 2. Determine what happens to the overall Application Status
            if ($status === 'Rejected') {
                // Check if it's the SOA being rejected or an initial document
                $docTypeStmt = $db->prepare("SELECT document_type FROM documents WHERE id = :id");
                $docTypeStmt->execute(['id' => $docId]);
                $docType = $docTypeStmt->fetchColumn();

                $studentStmt = $db->prepare("
                    SELECT u.id AS user_id
                    FROM applications a
                    JOIN student_profiles p ON a.student_id = p.id
                    JOIN users u ON p.user_id = u.id
                    WHERE a.id = :aid
                    LIMIT 1
                ");
                $studentStmt->execute(['aid' => $appId]);
                $studentUserId = (int) $studentStmt->fetchColumn();

                $newAppStatus = ($docType === 'SOA') ? 'SOA_Resubmission_Required' : 'Pending_Resubmission';

                $updateApp = $db->prepare("UPDATE applications SET status = :status WHERE id = :aid");
                $updateApp->execute(['status' => $newAppStatus, 'aid' => $appId]);

                if ($studentUserId > 0) {
                    $message = $docType === 'SOA'
                        ? 'Your Statement of Account was rejected. Please review the remarks and submit a corrected SOA.'
                        : 'One or more of your submitted documents were rejected. Please review the remarks and resubmit the required documents.';
                    Notification::create($studentUserId, 'Document Update', $message, 'student/dashboard');
                }

                // (Here you would trigger your Textbee SMS to alert the student about the rejection)
            } else {
                // If verified, check if ALL required documents are now verified
                $checkDocs = $db->prepare("SELECT COUNT(*) FROM documents WHERE application_id = :aid AND status != 'Verified'");
                $checkDocs->execute(['aid' => $appId]);
                $unverifiedCount = $checkDocs->fetchColumn();

                if ($unverifiedCount == 0) {
                    // All initial docs are good! Move them to the Interview queue.
                $updateApp = $db->prepare("UPDATE applications SET status = 'For_Interview' WHERE id = :aid AND status IN ('Submitted', 'Initial_Review')");
                $updateApp->execute(['aid' => $appId]);

                    if ($updateApp->rowCount() > 0) {
                        $studentStmt = $db->prepare("
                            SELECT u.id AS user_id
                            FROM applications a
                            JOIN student_profiles p ON a.student_id = p.id
                            JOIN users u ON p.user_id = u.id
                            WHERE a.id = :aid
                            LIMIT 1
                        ");
                        $studentStmt->execute(['aid' => $appId]);
                        $studentUserId = (int) $studentStmt->fetchColumn();

                        if ($studentUserId > 0) {
                            Notification::create(
                                $studentUserId,
                                'Ready for Interview',
                                'Your application passed document review and is now ready to be scheduled for interview.',
                                'student/dashboard'
                            );
                        }
                    }
                }
            }

            $db->commit();
            AuditLog::recordCurrentUser(
                'document.status_updated',
                'document',
                (int) $docId,
                'Staff updated a document status.',
                [
                    'application_id' => (int) $appId,
                    'status' => $status,
                    'remarks' => $remarks,
                ]
            );
            header('Location: ' . \base_url("staff/verify-documents/$appId") . '?success=1');
            exit;

        } catch (ValidationException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            redirect_with_flash('staff/dashboard', 'error', $e->getMessage());
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error updating document: ' . $e->getMessage());
            redirect_with_flash("staff/verify-documents/$appId", 'error', 'Unable to update the document status.');
        }
    }

    // ---------------------------------------------------------
    // BATCH INTERVIEW MANAGEMENT
    // ---------------------------------------------------------
    public function batchInterview()
    {
        $db = Database::connect();
        $canManageInterviewSchedule = $this->isCoordinator();
        $currentPeriod = ApplicationPeriod::getCurrent();
        $schoolTypeFilter = trim((string) ($_GET['school_type'] ?? ''));
        $barangayFilter = trim((string) ($_GET['barangay'] ?? ''));

        $conditions = [
            "a.status = 'For_Interview'",
            'a.interview_batch_id IS NULL',
        ];
        $params = [];

        if (is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])) {
            $conditions[] = 'a.school_year = :school_year';
            $conditions[] = 'a.semester = :semester';
            $params['school_year'] = (string) $currentPeriod['school_year'];
            $params['semester'] = (string) $currentPeriod['semester'];
        }

        if ($schoolTypeFilter !== '') {
            $conditions[] = 'p.school_type = :school_type';
            $params['school_type'] = $schoolTypeFilter;
        }

        if ($barangayFilter !== '') {
            $conditions[] = 'p.address_barangay = :barangay';
            $params['barangay'] = $barangayFilter;
        }

        // 1. Fetch students who are ready for an interview but haven't been scheduled yet
        // (Status is 'For_Interview' but interview_batch_id is NULL)
        $stmt = $db->prepare("
            SELECT a.id as application_id, p.first_name, p.last_name, p.address_barangay, p.school_name, p.school_type
            FROM applications a 
            JOIN student_profiles p ON a.student_id = p.id 
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC
        ");
        $stmt->execute($params);
        $unassignedStudents = $stmt->fetchAll();

        $totalReadyConditions = [
            "a.status = 'For_Interview'",
            'a.interview_batch_id IS NULL',
        ];
        $totalReadyParams = [];
        if (is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])) {
            $totalReadyConditions[] = 'a.school_year = :school_year';
            $totalReadyConditions[] = 'a.semester = :semester';
            $totalReadyParams['school_year'] = (string) $currentPeriod['school_year'];
            $totalReadyParams['semester'] = (string) $currentPeriod['semester'];
        }
        $totalReadyStmt = $db->prepare("
            SELECT COUNT(*)
            FROM applications a
            WHERE " . implode(' AND ', $totalReadyConditions) . "
        ");
        $totalReadyStmt->execute($totalReadyParams);
        $totalReadyInterviewCount = (int) $totalReadyStmt->fetchColumn();

        $optionConditions = [
            "a.status = 'For_Interview'",
            'a.interview_batch_id IS NULL',
        ];
        $optionParams = [];
        if (is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])) {
            $optionConditions[] = 'a.school_year = :school_year';
            $optionConditions[] = 'a.semester = :semester';
            $optionParams['school_year'] = (string) $currentPeriod['school_year'];
            $optionParams['semester'] = (string) $currentPeriod['semester'];
        }
        $optionWhereSql = 'WHERE ' . implode(' AND ', $optionConditions);

        $schoolTypeStmt = $db->prepare("
            SELECT DISTINCT p.school_type
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            $optionWhereSql
            ORDER BY p.school_type ASC
        ");
        $schoolTypeStmt->execute($optionParams);
        $schoolTypeOptions = $schoolTypeStmt->fetchAll(\PDO::FETCH_COLUMN);

        $barangayStmt = $db->prepare("
            SELECT DISTINCT p.address_barangay
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            $optionWhereSql
            ORDER BY p.address_barangay ASC
        ");
        $barangayStmt->execute($optionParams);
        $barangayOptions = $barangayStmt->fetchAll(\PDO::FETCH_COLUMN);
        $interviewFilterSummary = $this->buildBatchFilterSummary($schoolTypeFilter, $barangayFilter, 'applicant', 'applicants');
        $activeInterviewFilters = [];
        if ($schoolTypeFilter !== '') {
            $activeInterviewFilters[] = ['label' => 'School Type', 'value' => $schoolTypeFilter];
        }
        if ($barangayFilter !== '') {
            $activeInterviewFilters[] = ['label' => 'Barangay', 'value' => $barangayFilter];
        }
        $unassignedBySchoolType = [];
        $unassignedByBarangay = [];
        foreach ($unassignedStudents as $student) {
            $schoolTypeKey = trim((string) ($student['school_type'] ?? 'Unspecified'));
            $barangayKey = trim((string) ($student['address_barangay'] ?? 'Unspecified'));
            $unassignedBySchoolType[$schoolTypeKey] = ($unassignedBySchoolType[$schoolTypeKey] ?? 0) + 1;
            $unassignedByBarangay[$barangayKey] = ($unassignedByBarangay[$barangayKey] ?? 0) + 1;
        }
        arsort($unassignedBySchoolType);
        arsort($unassignedByBarangay);
        $currentPeriodLabel = is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])
            ? trim((string) $currentPeriod['school_year'] . ' | ' . (string) $currentPeriod['semester'])
            : 'No current application period configured';

        // 2. Fetch existing upcoming batches for the current application period
        $batchConditions = ["b.batch_type = 'Interview'"];
        $batchParams = [];
        if (is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])) {
            $batchConditions[] = 'a.school_year = :school_year';
            $batchConditions[] = 'a.semester = :semester';
            $batchParams['school_year'] = (string) $currentPeriod['school_year'];
            $batchParams['semester'] = (string) $currentPeriod['semester'];
        }
        $stmtBatches = $db->prepare("
            SELECT b.*, COUNT(a.id) as total_students 
            FROM batches b 
            JOIN applications a ON b.id = a.interview_batch_id 
            WHERE " . implode(' AND ', $batchConditions) . "
            GROUP BY b.id
            ORDER BY b.scheduled_date ASC
        ");
        $stmtBatches->execute($batchParams);
        $upcomingBatches = $stmtBatches->fetchAll();

        $batchParticipants = [];
        if ($upcomingBatches !== []) {
            $batchIds = array_map(static fn ($batch) => (int) ($batch['id'] ?? 0), $upcomingBatches);
            $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
            $participantConditions = ["a.interview_batch_id IN ($placeholders)"];
            $participantParams = $batchIds;
            if (is_array($currentPeriod) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])) {
                $participantConditions[] = 'a.school_year = ?';
                $participantConditions[] = 'a.semester = ?';
                $participantParams[] = (string) $currentPeriod['school_year'];
                $participantParams[] = (string) $currentPeriod['semester'];
            }
            $participantsStmt = $db->prepare("
                SELECT
                    a.id AS application_id,
                    a.interview_batch_id,
                    a.status,
                    a.interview_result,
                    a.interview_result_at,
                    p.first_name,
                    p.last_name,
                    p.address_barangay,
                    p.school_name,
                    p.school_type
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                WHERE " . implode(' AND ', $participantConditions) . "
                ORDER BY a.interview_batch_id ASC, p.school_type ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
            ");
            $participantsStmt->execute($participantParams);

            foreach ($participantsStmt->fetchAll() as $participant) {
                $batchParticipants[(int) $participant['interview_batch_id']][] = $participant;
            }
        }

        $batchSummaries = [];
        foreach ($upcomingBatches as $batch) {
            $batchId = (int) ($batch['id'] ?? 0);
            $participants = $batchParticipants[$batchId] ?? [];
            $summary = [
                'pending' => 0,
                'passed' => 0,
                'failed' => 0,
                'absent' => 0,
                'barangays' => [],
                'school_types' => [],
            ];

            foreach ($participants as $participant) {
                $resultKey = strtolower(trim((string) ($participant['interview_result'] ?? '')));
                if (isset($summary[$resultKey])) {
                    $summary[$resultKey]++;
                } else {
                    $summary['pending']++;
                }

                $barangayKey = trim((string) ($participant['address_barangay'] ?? 'Unspecified'));
                $schoolTypeKey = trim((string) ($participant['school_type'] ?? 'Unspecified'));
                $summary['barangays'][$barangayKey] = ($summary['barangays'][$barangayKey] ?? 0) + 1;
                $summary['school_types'][$schoolTypeKey] = ($summary['school_types'][$schoolTypeKey] ?? 0) + 1;
            }

            arsort($summary['barangays']);
            arsort($summary['school_types']);
            $batchSummaries[$batchId] = $summary;
        }

        require __DIR__ . '/../../views/staff/batch_interview.php';
    }

    public function recordInterviewResult()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $db = Database::connect();

        try {
            $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
            $batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
            $result = Validation::enum($_POST['interview_result'] ?? '', ['Passed', 'Failed', 'Absent'], 'Interview result');

            if (!$applicationId || !$batchId) {
                throw new ValidationException('Invalid interview result request.');
            }

            $applicationStmt = $db->prepare("
                SELECT
                    a.id,
                    a.status,
                    a.interview_batch_id,
                    b.scheduled_date,
                    u.id AS user_id,
                    u.phone_number,
                    p.last_name
                FROM applications a
                JOIN batches b ON a.interview_batch_id = b.id
                JOIN student_profiles p ON a.student_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE a.id = :application_id
                LIMIT 1
            ");
            $applicationStmt->execute(['application_id' => $applicationId]);
            $application = $applicationStmt->fetch();

            if (!$application) {
                throw new ValidationException('Application not found.');
            }

            if ((int) ($application['interview_batch_id'] ?? 0) !== $batchId) {
                throw new ValidationException('This applicant is not assigned to the selected interview batch.');
            }

            $scheduledAt = strtotime((string) ($application['scheduled_date'] ?? ''));
            if ($scheduledAt !== false && $scheduledAt > time()) {
                throw new ValidationException('Interview results can only be recorded once the scheduled interview time has started.');
            }

            $newStatus = $result === 'Passed' ? 'Eligible_Awaiting_SOA' : 'Not_Eligible';
            $applicationSettings = ApplicationPeriod::getCurrent();
            $computedSoaDeadline = null;
            if ($result === 'Passed') {
                $computedSoaDeadline = ApplicationPeriod::resolveApplicationSoaDeadline(
                    $applicationSettings,
                    ['interview_result_at' => date('Y-m-d H:i:s')]
                );
            }

            $updateStmt = $db->prepare("
                UPDATE applications
                SET status = :status,
                    interview_result = :interview_result,
                    interview_result_at = NOW(),
                    soa_deadline = :soa_deadline
                WHERE id = :application_id
            ");
            $updateStmt->execute([
                'status' => $newStatus,
                'interview_result' => $result,
                'soa_deadline' => $computedSoaDeadline !== null && $computedSoaDeadline !== '' ? ($computedSoaDeadline . ' 23:59:59') : null,
                'application_id' => $applicationId,
            ]);

            $userId = (int) ($application['user_id'] ?? 0);
            $lastName = trim((string) ($application['last_name'] ?? ''));
            $phoneNumber = trim((string) ($application['phone_number'] ?? ''));

            if ($userId > 0) {
                if ($result === 'Passed') {
                    Notification::create(
                        $userId,
                        'Interview Result',
                        'You passed the scholarship interview. Please upload your Statement of Account to continue your application.',
                        'student/dashboard'
                    );
                } elseif ($result === 'Failed') {
                    Notification::create(
                        $userId,
                        'Interview Result',
                        'Your scholarship interview result has been marked as failed. You may contact the LGU office for clarification.',
                        'student/dashboard'
                    );
                } else {
                    Notification::create(
                        $userId,
                        'Interview Result',
                        'Your scholarship interview result has been marked as absent. Please contact the LGU office if you believe this is incorrect.',
                        'student/dashboard'
                    );
                }
            }

            if ($phoneNumber !== '') {
                $message = 'San Enrique LGU Scholarship: ' . ($lastName !== '' ? $lastName . ', ' : '');
                if ($result === 'Passed') {
                    $message .= 'you passed the interview. Please upload your Statement of Account to continue your application.';
                } elseif ($result === 'Failed') {
                    $message .= 'your interview result is failed. You may contact the LGU office for clarification.';
                } else {
                    $message .= 'your interview result is absent. Please contact the LGU office if you believe this is incorrect.';
                }

                Sms::sendTextbee($phoneNumber, $message);
            }

            AuditLog::recordCurrentUser(
                'interview.result_recorded',
                'application',
                $applicationId,
                'Recorded an interview result for an applicant.',
                [
                    'batch_id' => $batchId,
                    'interview_result' => $result,
                    'application_status' => $newStatus,
                ]
            );

            redirect_with_flash('admin/batch-interview', 'success', 'Interview result recorded successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/batch-interview', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to record interview result: ' . $e->getMessage());
            redirect_with_flash('admin/batch-interview', 'error', 'Unable to save the interview result right now.');
        }
    }

    public function createBatch()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $this->ensureCoordinatorAccess();

        $db = Database::connect();

        try {
            $batchName = Validation::requiredString($_POST['batch_name'] ?? '', 'Batch name', 100);
            $scheduleDate = Validation::requiredString($_POST['scheduled_date'] ?? '', 'Scheduled date', 30);
            $venue = Validation::requiredString($_POST['venue'] ?? '', 'Venue', 150);
            $schoolTypeFilter = trim((string) ($_POST['school_type'] ?? ''));
            $barangayFilter = trim((string) ($_POST['barangay'] ?? ''));

            if ($schoolTypeFilter === '' && $barangayFilter === '') {
                throw new ValidationException('Select at least one schedule filter: school type or barangay.');
            }

            $selectedApplications = $this->findInterviewBatchApplicationIds($db, $schoolTypeFilter, $barangayFilter, ApplicationPeriod::getCurrent());

            if (empty($selectedApplications)) {
                throw new ValidationException('No interview-ready applicants matched the selected filters.');
            }

            $db->beginTransaction();

            // 1. Create the Batch Record
            $stmt = $db->prepare("INSERT INTO batches (batch_type, batch_name, scheduled_date, venue) VALUES ('Interview', :name, :sdate, :venue)");
            $stmt->execute([
                'name' => $batchName,
                'sdate' => $scheduleDate,
                'venue' => $venue
            ]);

            $batchId = $db->lastInsertId();

            // 2. Assign the selected students to this batch
            // Create a string of question marks like (?, ?, ?) based on array size for the IN clause
            $placeholders = implode(',', array_fill(0, count($selectedApplications), '?'));

            // We append the $batchId to the end of the array so it binds to the first ? in SET
            array_unshift($selectedApplications, $batchId);

            $updateApp = $db->prepare("UPDATE applications SET interview_batch_id = ? WHERE id IN ($placeholders)");
            $updateApp->execute($selectedApplications);

            $assignedApplicationIds = array_slice($selectedApplications, 1);
            if ($assignedApplicationIds !== []) {
                $notifyPlaceholders = implode(',', array_fill(0, count($assignedApplicationIds), '?'));
                $notifyStmt = $db->prepare("
                    SELECT u.id AS user_id
                    FROM applications a
                    JOIN student_profiles p ON a.student_id = p.id
                    JOIN users u ON p.user_id = u.id
                    WHERE a.id IN ($notifyPlaceholders)
                ");
                $notifyStmt->execute($assignedApplicationIds);
                $studentUserIds = $notifyStmt->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($studentUserIds as $studentUserId) {
                    Notification::create(
                        (int) $studentUserId,
                        'Interview Scheduled',
                        'Your scholarship interview has been scheduled for ' . $scheduleDate . ' at ' . $venue . '.',
                        'student/dashboard'
                    );
                }

                $smsStmt = $db->prepare("
                    SELECT u.phone_number, p.last_name
                    FROM applications a
                    JOIN student_profiles p ON a.student_id = p.id
                    JOIN users u ON p.user_id = u.id
                    WHERE a.id IN ($notifyPlaceholders)
                ");
                $smsStmt->execute($assignedApplicationIds);
                $smsRecipients = $smsStmt->fetchAll();

                foreach ($smsRecipients as $recipient) {
                    $phoneNumber = trim((string) ($recipient['phone_number'] ?? ''));
                    $lastName = trim((string) ($recipient['last_name'] ?? ''));

                    if ($phoneNumber !== '') {
                        Sms::sendTextbee(
                            $phoneNumber,
                            'San Enrique LGU Scholarship: ' . ($lastName !== '' ? $lastName . ', ' : '')
                            . 'your interview is scheduled on '
                            . date('M d, Y h:i A', strtotime($scheduleDate))
                            . ' at ' . $venue . '.'
                        );
                    }
                }
            }

            $db->commit();

            AuditLog::recordCurrentUser(
                'interview_batch.created',
                'batch',
                (int) $batchId,
                'Created an interview batch and assigned applicants.',
                [
                    'batch_name' => $batchName,
                    'scheduled_date' => $scheduleDate,
                    'venue' => $venue,
                    'application_count' => count($assignedApplicationIds),
                    'school_type' => $schoolTypeFilter !== '' ? $schoolTypeFilter : null,
                    'barangay' => $barangayFilter !== '' ? $barangayFilter : null,
                ]
            );

            // 3. (Optional but recommended) Trigger Textbee SMS here
            // Loop through the selected students, fetch their phone numbers, and blast the SMS schedule.

            header('Location: ' . \base_url('admin/batch-interview') . '?success=1');
            exit;

        } catch (ValidationException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            redirect_with_flash('admin/batch-interview', 'error', $e->getMessage());
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Failed to create interview batch: ' . $e->getMessage());
            redirect_with_flash('admin/batch-interview', 'error', 'Failed to create the interview batch.');
        }
    }

    public function rescheduleInterviewBatch()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $this->ensureCoordinatorAccess();

        $db = Database::connect();

        try {
            $batchId = filter_var($_POST['batch_id'] ?? null, FILTER_VALIDATE_INT);
            $scheduleDate = Validation::requiredString($_POST['scheduled_date'] ?? '', 'Scheduled date', 30);
            $venue = Validation::requiredString($_POST['venue'] ?? '', 'Venue', 150);

            if (!$batchId) {
                throw new ValidationException('Invalid interview batch.');
            }

            $batchStmt = $db->prepare("SELECT * FROM batches WHERE id = :id AND batch_type = 'Interview' LIMIT 1");
            $batchStmt->execute(['id' => $batchId]);
            $batch = $batchStmt->fetch();

            if (!$batch) {
                throw new ValidationException('Interview batch not found.');
            }

            $oldSchedule = (string) ($batch['scheduled_date'] ?? '');
            $oldVenue = (string) ($batch['venue'] ?? '');

            $updateStmt = $db->prepare("UPDATE batches SET scheduled_date = :scheduled_date, venue = :venue WHERE id = :id");
            $updateStmt->execute([
                'scheduled_date' => $scheduleDate,
                'venue' => $venue,
                'id' => $batchId,
            ]);

            $studentStmt = $db->prepare("
                SELECT u.id AS user_id
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE a.interview_batch_id = :batch_id
            ");
            $studentStmt->execute(['batch_id' => $batchId]);
            $studentUserIds = $studentStmt->fetchAll(\PDO::FETCH_COLUMN);

            $message = 'Your interview schedule was moved from '
                . date('M d, Y h:i A', strtotime($oldSchedule))
                . ' at ' . $oldVenue
                . ' to '
                . date('M d, Y h:i A', strtotime($scheduleDate))
                . ' at ' . $venue . '.';

            foreach ($studentUserIds as $studentUserId) {
                Notification::create(
                    (int) $studentUserId,
                    'Interview Rescheduled',
                    $message,
                    'student/dashboard'
                );
            }

            $smsStmt = $db->prepare("
                SELECT u.phone_number, p.last_name
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE a.interview_batch_id = :batch_id
            ");
            $smsStmt->execute(['batch_id' => $batchId]);
            $smsRecipients = $smsStmt->fetchAll();

            foreach ($smsRecipients as $recipient) {
                $phoneNumber = trim((string) ($recipient['phone_number'] ?? ''));
                $lastName = trim((string) ($recipient['last_name'] ?? ''));

                if ($phoneNumber !== '') {
                    Sms::sendTextbee(
                        $phoneNumber,
                        'San Enrique LGU Scholarship: ' . ($lastName !== '' ? $lastName . ', ' : '')
                        . 'your interview schedule has been moved to '
                        . date('M d, Y h:i A', strtotime($scheduleDate))
                        . ' at ' . $venue . '.'
                    );
                }
            }

            AuditLog::recordCurrentUser(
                'interview_batch.rescheduled',
                'batch',
                (int) $batchId,
                'Rescheduled an interview batch.',
                [
                    'old_schedule' => $oldSchedule,
                    'old_venue' => $oldVenue,
                    'new_schedule' => $scheduleDate,
                    'new_venue' => $venue,
                    'affected_applicants' => count($studentUserIds),
                ]
            );

            redirect_with_flash('admin/batch-interview', 'success', 'Interview batch rescheduled successfully.');
        } catch (ValidationException $e) {
            redirect_with_flash('admin/batch-interview', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to reschedule interview batch: ' . $e->getMessage());
            redirect_with_flash('admin/batch-interview', 'error', 'Unable to reschedule the interview batch right now.');
        }
    }

    public function exportInterviewList()
    {
        $db = Database::connect();

        try {
            $format = Validation::enum($_GET['format'] ?? 'print', ['excel', 'word', 'print'], 'Export format');
            $scope = Validation::enum($_GET['scope'] ?? 'current', ['current', 'batch', 'batch_results', 'batch_attendance'], 'Export scope');
            $batchId = filter_var($_GET['batch_id'] ?? null, FILTER_VALIDATE_INT);

            if (in_array($scope, ['batch', 'batch_results', 'batch_attendance'], true) && !$batchId) {
                throw new ValidationException('Invalid interview batch selected for export.');
            }

            if (in_array($scope, ['batch', 'batch_results', 'batch_attendance'], true)) {
                $batchStmt = $db->prepare("
                    SELECT id, batch_name, scheduled_date, venue
                    FROM batches
                    WHERE id = :id AND batch_type = 'Interview'
                    LIMIT 1
                ");
                $batchStmt->execute(['id' => $batchId]);
                $batch = $batchStmt->fetch();

                if (!$batch) {
                    throw new ValidationException('Interview batch not found.');
                }

                if ($scope === 'batch_attendance') {
                    $stmt = $db->prepare("
                        SELECT
                            CONCAT(p.last_name, ', ', p.first_name) AS applicant_name,
                            p.address_barangay AS barangay,
                            p.school_name
                        FROM applications a
                        JOIN student_profiles p ON a.student_id = p.id
                        WHERE a.interview_batch_id = :batch_id
                        ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
                    ");
                    $stmt->execute(['batch_id' => $batchId]);
                    $rows = $this->withSequentialNumbers($stmt->fetchAll(), 'line_no');
                    $this->streamInterviewAttendanceSheet($batch, $rows, $format);
                }

                if ($scope === 'batch_results') {
                    $stmt = $db->prepare("
                        SELECT
                            a.id AS application_id,
                            CONCAT(p.last_name, ', ', p.first_name) AS applicant_name,
                            p.address_barangay AS barangay,
                            p.school_name,
                            COALESCE(a.interview_result, 'Pending') AS interview_result,
                            a.status AS application_status
                        FROM applications a
                        JOIN student_profiles p ON a.student_id = p.id
                        WHERE a.interview_batch_id = :batch_id
                        ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
                    ");
                    $stmt->execute(['batch_id' => $batchId]);
                    $rows = $stmt->fetchAll();

                    OfficeExporter::stream(
                        'Interview Results - ' . (string) $batch['batch_name'],
                        [
                            'application_id' => 'Application ID',
                            'applicant_name' => 'Applicant Name',
                            'barangay' => 'Barangay',
                            'school_name' => 'School',
                            'interview_result' => 'Interview Result',
                            'application_status' => 'Application Status',
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

                $stmt = $db->prepare("
                    SELECT
                        a.id AS application_id,
                        CONCAT(p.last_name, ', ', p.first_name) AS applicant_name,
                        p.address_barangay AS barangay,
                        p.school_type,
                        p.school_name,
                        COALESCE(DATE_FORMAT(b.scheduled_date, '%b %d, %Y %h:%i %p'), 'Not Scheduled') AS schedule,
                        COALESCE(b.venue, 'Not Scheduled') AS venue
                    FROM applications a
                    JOIN student_profiles p ON a.student_id = p.id
                    JOIN batches b ON a.interview_batch_id = b.id
                    WHERE a.interview_batch_id = :batch_id
                    ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
                ");
                $stmt->execute(['batch_id' => $batchId]);
                $rows = $stmt->fetchAll();

                OfficeExporter::stream(
                    'Interview Batch List - ' . (string) $batch['batch_name'],
                    [
                        'application_id' => 'Application ID',
                        'applicant_name' => 'Applicant Name',
                        'barangay' => 'Barangay',
                        'school_type' => 'School Type',
                        'school_name' => 'School',
                        'schedule' => 'Interview Schedule',
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

            $stmt = $db->query("
                SELECT
                    a.id AS application_id,
                    CONCAT(p.last_name, ', ', p.first_name) AS applicant_name,
                    p.address_barangay AS barangay,
                    p.school_type,
                    p.school_name,
                    a.status AS application_status
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                WHERE a.status = 'For_Interview' AND a.interview_batch_id IS NULL
                ORDER BY p.school_type ASC, p.address_barangay ASC, p.last_name ASC, p.first_name ASC
            ");
            $rows = $stmt->fetchAll();

            OfficeExporter::stream(
                'Interview Candidate List',
                [
                    'application_id' => 'Application ID',
                    'applicant_name' => 'Applicant Name',
                    'barangay' => 'Barangay',
                    'school_type' => 'School Type',
                    'school_name' => 'School',
                    'application_status' => 'Status',
                ],
                $rows,
                $format,
                [
                    'List Type' => 'Unassigned interview candidates',
                    'Generated On' => date('M d, Y h:i A'),
                ]
            );
        } catch (ValidationException $e) {
            redirect_with_flash('admin/batch-interview', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to export interview list: ' . $e->getMessage());
            redirect_with_flash('admin/batch-interview', 'error', 'Unable to export the interview list right now.');
        }
    }

    public function printNotice($applicationId, $type)
    {
        $db = Database::connect();

        try {
            $applicationId = filter_var($applicationId, FILTER_VALIDATE_INT);
            $type = Validation::enum((string) $type, ['interview', 'disqualification', 'payout'], 'Notice type');

            if (!$applicationId) {
                throw new ValidationException('Invalid application notice request.');
            }

            $stmt = $db->prepare("
                SELECT
                    a.*,
                    u.phone_number,
                    u.email,
                    p.last_name,
                    p.first_name,
                    p.middle_name,
                    p.address_line,
                    p.address_barangay,
                    p.school_name,
                    p.course,
                    ib.batch_name AS interview_batch_name,
                    ib.scheduled_date AS interview_schedule,
                    ib.venue AS interview_venue,
                    pb.batch_name AS payout_batch_name,
                    pb.scheduled_date AS payout_schedule,
                    pb.venue AS payout_venue
                FROM applications a
                JOIN student_profiles p ON a.student_id = p.id
                JOIN users u ON p.user_id = u.id
                LEFT JOIN batches ib ON a.interview_batch_id = ib.id
                LEFT JOIN batches pb ON a.payout_batch_id = pb.id
                WHERE a.id = :application_id
                LIMIT 1
            ");
            $stmt->execute(['application_id' => $applicationId]);
            $noticeApplication = $stmt->fetch();

            if (!$noticeApplication) {
                throw new ValidationException('Application not found for notice printing.');
            }

            if ($type === 'interview' && empty($noticeApplication['interview_schedule'])) {
                throw new ValidationException('Interview notice is not available because no interview schedule is assigned yet.');
            }

            if ($type === 'payout' && empty($noticeApplication['payout_schedule'])) {
                throw new ValidationException('Payout notice is not available because no payout schedule is assigned yet.');
            }

            if ($type === 'disqualification' && (string) ($noticeApplication['status'] ?? '') !== 'Not_Eligible') {
                throw new ValidationException('Disqualification notice is only available for not eligible applications.');
            }

            require __DIR__ . '/../../views/printables/official_notice.php';
            exit;
        } catch (ValidationException $e) {
            redirect_with_flash('staff/archive', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to generate notice: ' . $e->getMessage());
            redirect_with_flash('staff/archive', 'error', 'Unable to generate the requested notice right now.');
        }
    }

    private function streamInterviewAttendanceSheet(array $batch, array $rows, string $format): never
    {
        $timestamp = date('Ymd-His');
        $title = 'Interview Attendance Sheet - ' . (string) ($batch['batch_name'] ?? 'Batch');

        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="interview-attendance-' . $timestamp . '.xls"');
        } elseif ($format === 'word') {
            header('Content-Type: application/msword; charset=UTF-8');
            header('Content-Disposition: attachment; filename="interview-attendance-' . $timestamp . '.doc"');
        } else {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #111; margin: 24px; }
            h1 { font-size: 18px; margin: 0 0 8px; }
            .meta { margin: 0 0 18px; }
            .meta div { margin-bottom: 4px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #222; padding: 8px; }
            th { background: #f0f0f0; text-align: center; }
            td:first-child, th:first-child { width: 8%; text-align: center; }
            td:nth-child(4), th:nth-child(4) { width: 30%; }
            .signature-cell { height: 34px; }
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
        echo '</div>';
        echo '<table><thead><tr><th>No.</th><th>Applicant Name</th><th>Barangay</th><th>School</th><th>Signature</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . (int) ($row['line_no'] ?? 0) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['applicant_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['barangay'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['school_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="signature-cell"></td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
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
