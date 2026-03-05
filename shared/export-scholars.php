<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$format = strtolower(trim((string) ($_GET['format'] ?? 'xlsx')));
$typeFilter = trim((string) ($_GET['type'] ?? ''));
$barangayFilter = trim((string) ($_GET['barangay'] ?? ''));
$schoolTypeFilter = trim((string) ($_GET['school_type'] ?? ''));
$periodFilter = trim((string) ($_GET['period'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$payoutStatuses = ['waitlisted'];
$disbursedStatuses = ['disbursed'];
$applicantStatuses = array_values(array_filter(application_status_options(), static function (string $status) use ($payoutStatuses, $disbursedStatuses): bool {
    return !in_array($status, $payoutStatuses, true) && !in_array($status, $disbursedStatuses, true);
}));

if ($typeFilter === 'scholar') {
    $typeFilter = 'disbursed';
}
if (!in_array($typeFilter, ['', 'applicant', 'payout', 'disbursed'], true)) {
    $typeFilter = '';
}
if (!in_array($schoolTypeFilter, ['', 'public', 'private'], true)) {
    $schoolTypeFilter = '';
}
if (!in_array($statusFilter, $applicantStatuses, true)) {
    $statusFilter = '';
}
$allowedBarangays = san_enrique_barangays();
if (!in_array($barangayFilter, $allowedBarangays, true)) {
    $barangayFilter = '';
}

$conditions = [];
if ($typeFilter === 'disbursed') {
    $safe = array_map(static fn(string $s): string => "'" . $conn->real_escape_string($s) . "'", $disbursedStatuses);
    $conditions[] = 'a.status IN (' . implode(', ', $safe) . ')';
} elseif ($typeFilter === 'payout') {
    $safe = array_map(static fn(string $s): string => "'" . $conn->real_escape_string($s) . "'", $payoutStatuses);
    $conditions[] = 'a.status IN (' . implode(', ', $safe) . ')';
} elseif ($typeFilter === 'applicant') {
    $excluded = array_values(array_unique(array_merge($payoutStatuses, $disbursedStatuses)));
    $safe = array_map(static fn(string $s): string => "'" . $conn->real_escape_string($s) . "'", $excluded);
    $conditions[] = 'a.status NOT IN (' . implode(', ', $safe) . ')';
    if ($statusFilter !== '') {
        $conditions[] = "a.status = '" . $conn->real_escape_string($statusFilter) . "'";
    }
}
if ($schoolTypeFilter !== '') {
    $conditions[] = "a.school_type = '" . $conn->real_escape_string($schoolTypeFilter) . "'";
}
if (table_column_exists($conn, 'applications', 'barangay') && $barangayFilter !== '') {
    $conditions[] = "a.barangay = '" . $conn->real_escape_string($barangayFilter) . "'";
}
if ($periodFilter !== '') {
    $parts = explode('|', $periodFilter, 2);
    $periodSemester = trim((string) ($parts[0] ?? ''));
    $periodSchoolYear = trim((string) ($parts[1] ?? ''));
    if ($periodSemester !== '' && $periodSchoolYear !== '') {
        $conditions[] = "a.semester = '" . $conn->real_escape_string($periodSemester) . "'";
        $conditions[] = "a.school_year = '" . $conn->real_escape_string($periodSchoolYear) . "'";
    }
}

$whereClause = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
$sql = "SELECT
            a.school_name,
            u.last_name,
            u.first_name
        FROM applications a
        INNER JOIN users u ON u.id = a.user_id
        {$whereClause}
        ORDER BY a.school_name ASC, u.last_name ASC, u.first_name ASC, a.id ASC";
$result = $conn->query($sql);
$records = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$rows = [];
$counter = 0;
foreach ($records as $record) {
    $counter++;
    $rows[] = [
        'no' => $counter,
        'name' => strtoupper(trim((string) (($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')))),
    ];
}

$columns = [
    'no' => 'No.',
    'name' => 'Name',
];

$title = 'San Enrique LGU Scholarship - Generated Name List';
$filenameBase = 'san_enrique_lgu_scholarship_generated_names_' . date('Ymd_His');
export_rows_to_format($format, $title, $columns, $rows, $filenameBase);
