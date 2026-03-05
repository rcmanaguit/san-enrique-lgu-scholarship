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

    $columns = [
        'status' => 'Status',
        'total' => 'Total Applications',
    ];
    $title = 'San Enrique LGU Scholarship - Application Status Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_status_summary_' . date('Ymd_His');
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
    $where = "a.status = 'disbursed'";
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
        'total_disbursed' => 'Total Disbursed (PHP)',
    ];
    $title = 'San Enrique LGU Scholarship - Disbursed Scholars' . $rangeSuffix;
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
} elseif ($dataset === 'qr_scan_summary') {
    if (!table_exists($conn, 'qr_scan_logs')) {
        $rows = [];
    } else {
        $sql = "SELECT purpose, scan_status, COUNT(*) AS total
                FROM qr_scan_logs
                {$logRangeWhere}
                GROUP BY purpose, scan_status
                ORDER BY total DESC, purpose ASC";
        $result = $conn->query($sql);
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        foreach ($rows as &$row) {
            $row['purpose'] = qr_scan_purpose_label((string) ($row['purpose'] ?? ''));
        }
        unset($row);
    }

    $columns = [
        'purpose' => 'Scan Purpose',
        'scan_status' => 'Scan Status',
        'total' => 'Total Scans',
    ];
    $title = 'San Enrique LGU Scholarship - QR Scan Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_qr_scan_summary_' . date('Ymd_His');
} elseif ($dataset === 'qr_scan_logs') {
    if (!table_exists($conn, 'qr_scan_logs')) {
        $rows = [];
    } else {
        $sql = "SELECT
                    l.created_at,
                    COALESCE(a.application_no, l.scanned_application_no, '-') AS application_no,
                    COALESCE(CONCAT(a.last_name, ', ', a.first_name), '-') AS applicant_name,
                    l.purpose,
                    l.scan_status,
                    COALESCE(CONCAT(su.first_name, ' ', su.last_name), '-') AS scanned_by,
                    l.scanned_qr_token,
                    l.notes
                FROM qr_scan_logs l
                LEFT JOIN applications a ON a.id = l.application_id
                LEFT JOIN users su ON su.id = l.scanned_by_user_id
                {$logRangeWhere}
                ORDER BY l.id DESC
                LIMIT 1000";
        $result = $conn->query($sql);
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        foreach ($rows as &$row) {
            $row['purpose'] = qr_scan_purpose_label((string) ($row['purpose'] ?? ''));
        }
        unset($row);
    }

    $columns = [
        'created_at' => 'Date/Time',
        'application_no' => 'Application No',
        'applicant_name' => 'Applicant',
        'purpose' => 'Purpose',
        'scan_status' => 'Scan Status',
        'scanned_by' => 'Scanned By',
        'scanned_qr_token' => 'Scanned QR Token',
        'notes' => 'Notes',
    ];
    $title = 'San Enrique LGU Scholarship - QR Scan Logs' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_qr_scan_logs_' . date('Ymd_His');
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
