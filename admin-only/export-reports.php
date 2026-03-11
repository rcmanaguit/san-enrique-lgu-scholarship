<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_admin('../index.php');

$format = strtolower(trim((string) ($_GET['format'] ?? 'xlsx')));
$dataset = strtolower(trim((string) ($_GET['dataset'] ?? 'status_summary')));
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$periodScope = normalize_period_scope((string) ($_GET['period_scope'] ?? 'all'), 'all');
$periodIdFilter = (int) ($_GET['period_id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$queueFilter = trim((string) ($_GET['queue'] ?? ''));

$isValidDate = static function (string $value): bool {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
};
if ($fromDate !== '' && !$isValidDate($fromDate)) {
    $fromDate = '';
}
if ($toDate !== '' && !$isValidDate($toDate)) {
    $toDate = '';
}
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$rows = [];
$columns = [];
$title = 'San Enrique LGU Scholarship Report';
$filenameBase = 'san_enrique_lgu_scholarship_report_' . date('Ymd_His');

$esc = static fn(string $value): string => "'" . $conn->real_escape_string($value) . "'";
$rangeSuffix = '';
if ($fromDate !== '' && $toDate !== '') {
    $rangeSuffix = ' (' . $fromDate . ' to ' . $toDate . ')';
}

$appRangeWhere = '';
$disbursementRangeWhere = '';
$logRangeWhere = '';
if ($fromDate !== '' && $toDate !== '') {
    $fromDateTime = $fromDate . ' 00:00:00';
    $toDateTime = $toDate . ' 23:59:59';
    $appRangeWhere = " WHERE COALESCE(submitted_at, created_at) BETWEEN " . $esc($fromDateTime) . " AND " . $esc($toDateTime) . " ";
    $disbursementRangeWhere = " WHERE d.disbursement_date BETWEEN " . $esc($fromDate) . " AND " . $esc($toDate) . " ";
    $logRangeWhere = " WHERE created_at BETWEEN " . $esc($fromDateTime) . " AND " . $esc($toDateTime) . " ";
}

if ($dataset === 'status_summary') {
    $sql = "SELECT status, COUNT(*) AS total
            FROM applications
            {$appRangeWhere}
            GROUP BY status
            ORDER BY total DESC, status ASC";
    $result = $conn->query($sql);
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($rows as &$row) {
        $row['status'] = application_status_label((string) ($row['status'] ?? ''));
    }
    unset($row);

    $columns = [
        'status' => 'Status',
        'total' => 'Total Applications',
    ];
    $title = 'San Enrique LGU Scholarship - Application Status Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_status_summary_' . date('Ymd_His');
} elseif ($dataset === 'applications_scope') {
    $activePeriod = current_open_application_period($conn);
    $hasApplicationPeriodColumn = table_exists($conn, 'applications') && table_column_exists($conn, 'applications', 'application_period_id');
    $selectedPeriodForFilter = null;
    if ($periodIdFilter > 0 && table_exists($conn, 'application_periods')) {
        $stmtPeriod = $conn->prepare(
            "SELECT id, period_name, semester, academic_year
             FROM application_periods
             WHERE id = ?
             LIMIT 1"
        );
        if ($stmtPeriod) {
            $stmtPeriod->bind_param('i', $periodIdFilter);
            $stmtPeriod->execute();
            $selectedPeriodForFilter = $stmtPeriod->get_result()->fetch_assoc() ?: null;
            $stmtPeriod->close();
        }
    }

    $whereParts = [];
    if ($fromDate !== '' && $toDate !== '') {
        $whereParts[] = "COALESCE(a.submitted_at, a.created_at) BETWEEN " . $esc($fromDate . ' 00:00:00') . " AND " . $esc($toDate . ' 23:59:59');
    }
    if ($statusFilter !== '' && in_array($statusFilter, application_status_options(), true)) {
        $whereParts[] = "a.status = " . $esc($statusFilter);
    }

    $queueMap = [
        'under_review' => ['under_review', 'needs_resubmission'],
        'for_interview' => ['for_interview'],
        'for_soa' => ['for_soa'],
        'approved_for_release' => ['approved_for_release'],
        'completed' => ['released'],
    ];
    if (isset($queueMap[$queueFilter])) {
        $queueStatuses = $queueMap[$queueFilter];
        if ($queueStatuses) {
            $escapedStatuses = array_map($esc, $queueStatuses);
            $whereParts[] = "a.status IN (" . implode(', ', $escapedStatuses) . ")";
        }
    }

    if ($periodIdFilter > 0) {
        if ($selectedPeriodForFilter) {
            if ($hasApplicationPeriodColumn) {
                $whereParts[] = "a.application_period_id = " . (int) $periodIdFilter;
            } else {
                $filterSemester = trim((string) ($selectedPeriodForFilter['semester'] ?? ''));
                $filterSchoolYear = trim((string) ($selectedPeriodForFilter['academic_year'] ?? ''));
                if ($filterSemester !== '' && $filterSchoolYear !== '') {
                    $whereParts[] = "a.semester = " . $esc($filterSemester) . " AND a.school_year = " . $esc($filterSchoolYear);
                } else {
                    $whereParts[] = "1 = 0";
                }
            }
        } else {
            $whereParts[] = "1 = 0";
        }
    }

    $activePeriodId = (int) ($activePeriod['id'] ?? 0);
    $activeSemester = trim((string) ($activePeriod['semester'] ?? ''));
    $activeSchoolYear = trim((string) ($activePeriod['academic_year'] ?? ''));
    if ($periodScope === 'active') {
        if ($activePeriod) {
            if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
                $whereParts[] = "a.application_period_id = " . $activePeriodId;
            } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
                $whereParts[] = "a.semester = " . $esc($activeSemester) . " AND a.school_year = " . $esc($activeSchoolYear);
            } else {
                $whereParts[] = "1 = 0";
            }
        } else {
            $whereParts[] = "1 = 0";
        }
    } elseif ($periodScope === 'archived' && $activePeriod) {
        if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
            $whereParts[] = "(a.application_period_id IS NULL OR a.application_period_id <> " . $activePeriodId . ")";
        } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
            $whereParts[] = "(a.semester <> " . $esc($activeSemester) . " OR a.school_year <> " . $esc($activeSchoolYear) . ")";
        }
    }

    $whereSql = $whereParts ? (' WHERE ' . implode(' AND ', $whereParts)) : '';
    $sql = "SELECT
                a.application_no,
                CONCAT(u.last_name, ', ', u.first_name) AS applicant_name,
                CONCAT(COALESCE(a.semester, '-'), ' / ', COALESCE(a.school_year, '-')) AS period_label,
                a.status,
                a.school_name,
                a.applicant_type,
                a.updated_at
            FROM applications a
            INNER JOIN users u ON u.id = a.user_id
            " . $whereSql . "
            ORDER BY a.updated_at DESC
            LIMIT 5000";
    $result = $conn->query($sql);
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($rows as &$row) {
        $row['status'] = application_status_label((string) ($row['status'] ?? ''));
        $row['applicant_type'] = ucfirst((string) ($row['applicant_type'] ?? ''));
    }
    unset($row);

    $columns = [
        'application_no' => 'Application No',
        'applicant_name' => 'Applicant',
        'period_label' => 'Period',
        'status' => 'Status',
        'school_name' => 'School',
        'applicant_type' => 'Applicant Type',
        'updated_at' => 'Last Updated',
    ];
    $scopeLabel = ucfirst($periodScope);
    $title = 'San Enrique LGU Scholarship - Applications (' . $scopeLabel . ')' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_applications_' . strtolower($scopeLabel) . '_' . date('Ymd_His');
} elseif ($dataset === 'monthly_disbursements' || $dataset === 'semester_disbursements') {
    $sql = "SELECT
                CASE
                    WHEN a.semester IN ('First Semester', 'Second Semester')
                         AND TRIM(COALESCE(a.school_year, '')) <> ''
                    THEN CONCAT(
                        CASE a.semester
                            WHEN 'First Semester' THEN '1st Sem'
                            WHEN 'Second Semester' THEN '2nd Sem'
                            ELSE a.semester
                        END,
                        ' ',
                        a.school_year
                    )
                    ELSE 'Unspecified'
                END AS semester_label,
                COUNT(d.id) AS total_records,
                FORMAT(COALESCE(SUM(d.amount), 0), 2) AS total_amount,
                MAX(
                    CASE
                        WHEN a.school_year REGEXP '^[0-9]{4}-[0-9]{4}$'
                        THEN CAST(SUBSTRING_INDEX(a.school_year, '-', 1) AS UNSIGNED)
                        ELSE 0
                    END
                ) AS sort_year,
                MAX(
                    CASE a.semester
                        WHEN 'Second Semester' THEN 2
                        WHEN 'First Semester' THEN 1
                        ELSE 0
                    END
                ) AS sort_semester
            FROM disbursements d
            LEFT JOIN applications a ON a.id = d.application_id
            {$disbursementRangeWhere}
            GROUP BY semester_label
            ORDER BY sort_year DESC, sort_semester DESC, semester_label ASC";
    $result = $conn->query($sql);
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $columns = [
        'semester_label' => 'Semester',
        'total_records' => 'Records',
        'total_amount' => 'Total Amount (PHP)',
    ];
    $title = 'San Enrique LGU Scholarship - Semester Disbursement Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_semester_disbursements_' . date('Ymd_His');
} elseif ($dataset === 'approved_scholars') {
    $where = "a.status = 'released'";
    if ($fromDate !== '' && $toDate !== '') {
        $where .= " AND COALESCE(a.submitted_at, a.created_at) BETWEEN " . $esc($fromDate . ' 00:00:00') . " AND " . $esc($toDate . ' 23:59:59');
    }

    $sql = "SELECT
                a.application_no,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                a.school_name,
                a.course,
                a.applicant_type,
                a.school_year,
                FORMAT(COALESCE(SUM(d.amount), 0), 2) AS total_disbursed
            FROM applications a
            INNER JOIN users u ON u.id = a.user_id
            LEFT JOIN disbursements d ON d.application_id = a.id
            WHERE {$where}
            GROUP BY a.id
            ORDER BY a.updated_at DESC";
    $result = $conn->query($sql);
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $columns = [
        'application_no' => 'Application No',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'school_name' => 'School',
        'course' => 'Course',
        'applicant_type' => 'Applicant Type',
        'school_year' => 'School Year',
        'total_disbursed' => 'Total Released (PHP)',
    ];
    $title = 'San Enrique LGU Scholarship - Released Scholars' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_approved_scholars_' . date('Ymd_His');
} elseif ($dataset === 'scholarship_summary') {
    $sql = "SELECT applicant_type, COUNT(*) AS total
            FROM applications
            {$appRangeWhere}
            GROUP BY applicant_type
            ORDER BY total DESC, applicant_type ASC";
    $result = $conn->query($sql);
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $columns = [
        'applicant_type' => 'Applicant Type',
        'total' => 'Total Applications',
    ];
    $title = 'San Enrique LGU Scholarship - Applicant Type Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_scholarship_summary_' . date('Ymd_His');
} elseif ($dataset === 'sms_delivery_summary') {
    if (!table_exists($conn, 'sms_logs')) {
        $rows = [];
    } else {
        $sql = "SELECT delivery_status, COUNT(*) AS total
                FROM sms_logs
                {$logRangeWhere}
                GROUP BY delivery_status
                ORDER BY delivery_status ASC";
        $result = $conn->query($sql);
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    $columns = [
        'delivery_status' => 'Delivery Status',
        'total' => 'Total Messages',
    ];
    $title = 'San Enrique LGU Scholarship - SMS Delivery Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_sms_summary_' . date('Ymd_His');
} elseif ($dataset === 'sms_logs') {
    if (!table_exists($conn, 'sms_logs')) {
        $rows = [];
    } else {
        $sql = "SELECT
                    s.created_at,
                    s.phone,
                    s.sms_type,
                    s.delivery_status,
                    s.message,
                    COALESCE(CONCAT(u.first_name, ' ', u.last_name), '-') AS user_name
                FROM sms_logs s
                LEFT JOIN users u ON u.id = s.user_id
                {$logRangeWhere}
                ORDER BY s.id DESC
                LIMIT 1000";
        $result = $conn->query($sql);
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    $columns = [
        'created_at' => 'Date/Time',
        'phone' => 'Recipient Phone',
        'sms_type' => 'SMS Type',
        'delivery_status' => 'Delivery Status',
        'message' => 'Message',
        'user_name' => 'Related User',
    ];
    $title = 'San Enrique LGU Scholarship - SMS Logs' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_sms_logs_' . date('Ymd_His');
} elseif ($dataset === 'audit_logs') {
    if (!table_exists($conn, 'audit_logs')) {
        $rows = [];
    } else {
        $sql = "SELECT
                    l.created_at,
                    COALESCE(CONCAT(u.first_name, ' ', u.last_name), '-') AS user_name,
                    l.user_role,
                    l.action,
                    l.entity_type,
                    l.entity_id,
                    l.description,
                    l.ip_address
                FROM audit_logs l
                LEFT JOIN users u ON u.id = l.user_id
                {$logRangeWhere}
                ORDER BY l.id DESC
                LIMIT 500";
        $result = $conn->query($sql);
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        foreach ($rows as &$row) {
            $actionCode = trim((string) ($row['action'] ?? ''));
            $entityTypeCode = trim((string) ($row['entity_type'] ?? ''));
            $entityId = trim((string) ($row['entity_id'] ?? ''));
            $row['what_happened'] = audit_action_label($actionCode);
            $row['action_code'] = $actionCode !== '' ? $actionCode : '-';
            $row['entity_label'] = audit_entity_label($entityTypeCode);
            $row['entity_id_label'] = $entityId !== '' ? $entityId : '-';
        }
        unset($row);
    }

    $columns = [
        'created_at' => 'Date/Time',
        'user_name' => 'User',
        'user_role' => 'Role',
        'what_happened' => 'What Happened',
        'action_code' => 'Event Code',
        'entity_label' => 'Affected Record',
        'entity_id_label' => 'Record ID',
        'description' => 'Description',
        'ip_address' => 'IP Address',
    ];
    $title = 'San Enrique LGU Scholarship - Audit Logs' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_audit_logs_' . date('Ymd_His');
} else {
    http_response_code(400);
    echo 'Invalid dataset selected.';
    exit;
}

export_rows_to_format($format, $title, $columns, $rows, $filenameBase);
