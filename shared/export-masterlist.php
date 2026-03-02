<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$format = strtolower(trim((string) ($_GET['format'] ?? 'xlsx')));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$schoolTypeFilter = trim((string) ($_GET['school_type'] ?? ''));
$schoolYearFilter = trim((string) ($_GET['school_year'] ?? ''));
$barangayFilter = trim((string) ($_GET['barangay'] ?? ''));

$allowedStatus = application_status_options();
$allowedBarangays = san_enrique_barangays();
$hasBarangayColumn = table_column_exists($conn, 'applications', 'barangay');
$hasTownColumn = table_column_exists($conn, 'applications', 'town');
$hasProvinceColumn = table_column_exists($conn, 'applications', 'province');
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}
if (!in_array($schoolTypeFilter, ['public', 'private'], true)) {
    $schoolTypeFilter = '';
}
if (!in_array($barangayFilter, $allowedBarangays, true)) {
    $barangayFilter = '';
}
if (!$hasBarangayColumn) {
    $barangayFilter = '';
}

$where = [];
if ($statusFilter !== '') {
    $where[] = "a.status = '" . $conn->real_escape_string($statusFilter) . "'";
}
if ($schoolTypeFilter !== '') {
    $where[] = "a.school_type = '" . $conn->real_escape_string($schoolTypeFilter) . "'";
}
if ($schoolYearFilter !== '') {
    $where[] = "a.school_year = '" . $conn->real_escape_string($schoolYearFilter) . "'";
}
if ($hasBarangayColumn && $barangayFilter !== '') {
    $where[] = "a.barangay = '" . $conn->real_escape_string($barangayFilter) . "'";
}
$whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$barangaySelect = $hasBarangayColumn ? 'a.barangay' : "'' AS barangay";
$townSelect = $hasTownColumn ? 'a.town' : "'" . $conn->real_escape_string(san_enrique_town()) . "' AS town";
$provinceSelect = $hasProvinceColumn ? 'a.province' : "'" . $conn->real_escape_string(san_enrique_province()) . "' AS province";

$sql = "SELECT
            a.application_no,
            a.status,
            a.scholarship_type,
            a.applicant_type,
            u.first_name,
            u.middle_name,
            u.last_name,
            u.phone,
            u.email,
            a.school_name,
            a.school_type,
            a.semester,
            a.school_year,
            {$barangaySelect},
            {$townSelect},
            {$provinceSelect},
            a.soa_submission_deadline,
            a.soa_submitted_at,
            a.submitted_at
        FROM applications a
        INNER JOIN users u ON u.id = a.user_id
        {$whereClause}
        ORDER BY a.id DESC";

$result = $conn->query($sql);
$rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$columns = [
    'application_no' => 'Application No',
    'status' => 'Status',
    'scholarship_type' => 'Scholarship Type',
    'applicant_type' => 'Applicant Type',
    'first_name' => 'First Name',
    'middle_name' => 'Middle Name',
    'last_name' => 'Last Name',
    'phone' => 'Phone',
    'email' => 'Email',
    'school_name' => 'School Name',
    'school_type' => 'School Type',
    'semester' => 'Semester',
    'school_year' => 'School Year',
    'barangay' => 'Barangay',
    'town' => 'Town',
    'province' => 'Province',
    'soa_submission_deadline' => 'SOA Deadline',
    'soa_submitted_at' => 'SOA Submitted At',
    'submitted_at' => 'Submitted At',
];

$title = 'San Enrique LGU Scholarship Masterlist';
$filenameBase = 'san_enrique_lgu_scholarship_masterlist_' . date('Ymd_His');
export_rows_to_format($format, $title, $columns, $rows, $filenameBase);
