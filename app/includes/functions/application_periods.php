<?php
declare(strict_types=1);

function ensure_application_period_status_column(mysqli $conn): bool
{
    static $ensured = false;
    $columnAdded = false;
    $sessionKey = 'application_period_status_schema_checked';

    if ($ensured || !empty($_SESSION[$sessionKey])) {
        $ensured = true;
        return application_period_status_column_exists($conn);
    }

    $ensured = true;
    if (!table_exists($conn, 'application_periods')) {
        return false;
    }

    if (!application_period_status_column_exists($conn)) {
        $conn->query(
            "ALTER TABLE application_periods
             ADD COLUMN period_status ENUM('open','closed','completed') NOT NULL DEFAULT 'closed'
             AFTER is_open"
        );
        $columnAdded = true;
    }

    if (!application_period_status_column_exists($conn)) {
        return false;
    }

    if ($columnAdded) {
        $conn->query(
            "UPDATE application_periods
             SET period_status = CASE
                 WHEN is_open = 1 THEN 'open'
                 ELSE 'closed'
             END"
        );
    } else {
        $conn->query("UPDATE application_periods SET period_status = 'closed' WHERE period_status = 'draft'");
    }

    $_SESSION[$sessionKey] = 1;
    return true;
}

function application_period_status_column_exists(mysqli $conn): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    if (!table_exists($conn, 'application_periods')) {
        $cache = false;
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'application_periods'
           AND COLUMN_NAME = 'period_status'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    $cache = (int) ($row['total'] ?? 0) > 0;
    return $cache;
}

function application_period_status_label(string $status): string
{
    return match (trim($status)) {
        'open' => 'Open for Submission',
        'closed' => 'Closed for Submission',
        'completed' => 'Completed',
        default => 'Unknown',
    };
}

function application_period_status_badge_class(string $status): string
{
    return match (trim($status)) {
        'open' => 'text-bg-success',
        'closed' => 'text-bg-warning',
        'completed' => 'text-bg-dark',
        default => 'text-bg-secondary',
    };
}

function auto_close_expired_submission_periods(mysqli $conn): void
{
    static $requestChecked = false;
    $today = date('Y-m-d');
    $sessionKey = 'application_period_auto_closed_on';

    if ($requestChecked || (string) ($_SESSION[$sessionKey] ?? '') === $today) {
        return;
    }
    $requestChecked = true;

    if (!table_exists($conn, 'application_periods')) {
        return;
    }

    ensure_application_period_status_column($conn);
    if (!application_period_status_column_exists($conn)) {
        return;
    }

    $stmt = $conn->prepare(
        "UPDATE application_periods
         SET is_open = 0, period_status = 'closed', updated_at = NOW()
         WHERE period_status = 'open'
           AND end_date IS NOT NULL
           AND end_date < ?"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('s', $today);
    $stmt->execute();
    $stmt->close();
    $_SESSION[$sessionKey] = $today;
}

function current_active_application_period(mysqli $conn): ?array
{
    static $cache = [];
    $cacheKey = date('Y-m-d');
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!table_exists($conn, 'application_periods')) {
        $cache[$cacheKey] = null;
        return null;
    }

    ensure_application_period_status_column($conn);
    auto_close_expired_submission_periods($conn);

    $hasAcademicYear = table_column_exists($conn, 'application_periods', 'academic_year');
    $hasSemester = table_column_exists($conn, 'application_periods', 'semester');
    $hasPeriodStatus = application_period_status_column_exists($conn);

    $academicYearSelect = $hasAcademicYear ? ', academic_year' : '';
    $semesterSelect = $hasSemester ? ', semester' : '';
    $periodStatusSelect = $hasPeriodStatus ? ', period_status' : '';
    $stmt = $conn->prepare(
        "SELECT id, period_name, start_date, end_date, is_open, notes" . $academicYearSelect . $semesterSelect . $periodStatusSelect . "
         FROM application_periods
         WHERE " . ($hasPeriodStatus
            ? "period_status IN ('open', 'closed')"
            : "is_open = 1") . "
         ORDER BY " . ($hasPeriodStatus
            ? "FIELD(period_status, 'open', 'closed'), id DESC"
            : "is_open DESC, id DESC") . "
         LIMIT 1"
    );
    if (!$stmt) {
        $cache[$cacheKey] = null;
        return null;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? ($result->fetch_assoc() ?: null) : null;
    $stmt->close();
    $cache[$cacheKey] = $row;
    return $row;
}

function current_open_application_period(mysqli $conn): ?array
{
    static $cache = [];
    $cacheKey = date('Y-m-d');
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!table_exists($conn, 'application_periods')) {
        $cache[$cacheKey] = null;
        return null;
    }

    ensure_application_period_status_column($conn);
    auto_close_expired_submission_periods($conn);

    $hasAcademicYear = table_column_exists($conn, 'application_periods', 'academic_year');
    $hasSemester = table_column_exists($conn, 'application_periods', 'semester');
    $hasPeriodStatus = application_period_status_column_exists($conn);

    $academicYearSelect = $hasAcademicYear ? ', academic_year' : '';
    $semesterSelect = $hasSemester ? ', semester' : '';
    $periodStatusSelect = $hasPeriodStatus ? ', period_status' : '';
    $today = date('Y-m-d');
    $stmt = $conn->prepare(
        "SELECT id, period_name, start_date, end_date, is_open, notes" . $academicYearSelect . $semesterSelect . $periodStatusSelect . "
         FROM application_periods
         WHERE " . ($hasPeriodStatus ? "period_status = 'open'" : "is_open = 1") . "
           AND (start_date IS NULL OR start_date <= ?)
           AND (end_date IS NULL OR end_date >= ?)
         ORDER BY id DESC
         LIMIT 1"
    );
    if (!$stmt) {
        $cache[$cacheKey] = null;
        return null;
    }

    $stmt->bind_param('ss', $today, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? ($result->fetch_assoc() ?: null) : null;
    $stmt->close();
    $cache[$cacheKey] = $row;
    return $row;
}

function is_application_period_open(mysqli $conn): bool
{
    return current_open_application_period($conn) !== null;
}

function format_application_period(?array $period): string
{
    if (!$period) {
        return 'No application period';
    }

    $academicYear = trim((string) ($period['academic_year'] ?? ''));
    $semester = trim((string) ($period['semester'] ?? ''));
    $name = $semester !== '' && $academicYear !== ''
        ? ($semester . ' ' . $academicYear)
        : trim((string) ($period['period_name'] ?? 'Application Period'));
    $start = trim((string) ($period['start_date'] ?? ''));
    $end = trim((string) ($period['end_date'] ?? ''));

    if ($start !== '' && $end !== '') {
        return $name . ' (' . date('M d, Y', strtotime($start)) . ' - ' . date('M d, Y', strtotime($end)) . ')';
    }
    if ($start !== '') {
        return $name . ' (from ' . date('M d, Y', strtotime($start)) . ')';
    }
    if ($end !== '') {
        return $name . ' (until ' . date('M d, Y', strtotime($end)) . ')';
    }

    return $name;
}

function normalize_period_scope(string $scope, string $default = 'active'): string
{
    $scope = strtolower(trim($scope));
    $allowed = ['active', 'archived', 'all'];
    if (in_array($scope, $allowed, true)) {
        return $scope;
    }
    return in_array($default, $allowed, true) ? $default : 'active';
}

function application_is_in_active_period(
    array $application,
    ?array $activePeriod,
    bool $hasApplicationPeriodColumn = true
): bool {
    if (!$activePeriod) {
        return false;
    }

    $activePeriodId = (int) ($activePeriod['id'] ?? 0);
    if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
        $applicationPeriodId = (int) ($application['application_period_id'] ?? 0);
        return $applicationPeriodId > 0 && $applicationPeriodId === $activePeriodId;
    }

    $activeSemester = trim((string) ($activePeriod['semester'] ?? ''));
    $activeSchoolYear = trim((string) ($activePeriod['academic_year'] ?? ''));
    if ($activeSemester !== '' && $activeSchoolYear !== '') {
        return trim((string) ($application['semester'] ?? '')) === $activeSemester
            && trim((string) ($application['school_year'] ?? '')) === $activeSchoolYear;
    }

    return false;
}

function application_is_archived_for_active_period(
    array $application,
    ?array $activePeriod,
    bool $hasApplicationPeriodColumn = true
): bool {
    if (!$activePeriod) {
        return true;
    }

    return !application_is_in_active_period($application, $activePeriod, $hasApplicationPeriodColumn);
}

function application_matches_period_scope(
    array $application,
    string $scope,
    ?array $activePeriod,
    bool $hasApplicationPeriodColumn = true
): bool {
    $scope = normalize_period_scope($scope, 'active');
    if ($scope === 'all') {
        return true;
    }
    if ($scope === 'archived') {
        return application_is_archived_for_active_period($application, $activePeriod, $hasApplicationPeriodColumn);
    }
    return application_is_in_active_period($application, $activePeriod, $hasApplicationPeriodColumn);
}

function table_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    static $cache = [];

    $tableName = trim($tableName);
    $columnName = trim($columnName);
    if ($tableName === '' || $columnName === '') {
        return false;
    }

    $cacheKey = strtolower($tableName . '.' . $columnName);
    if (array_key_exists($cacheKey, $cache)) {
        return (bool) $cache[$cacheKey];
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        $cache[$cacheKey] = false;
        return false;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    $exists = (int) ($row['total'] ?? 0) > 0;
    $cache[$cacheKey] = $exists;
    return $exists;
}

function applicant_has_application_in_period(mysqli $conn, int $userId, ?array $period = null): bool
{
    if ($userId <= 0 || !table_exists($conn, 'applications')) {
        return false;
    }

    if ($period === null) {
        $period = current_open_application_period($conn);
    }
    if (!$period) {
        return false;
    }

    $periodId = (int) ($period['id'] ?? 0);
    if ($periodId > 0 && table_column_exists($conn, 'applications', 'application_period_id')) {
        $stmt = $conn->prepare(
            "SELECT id
             FROM applications
             WHERE user_id = ?
               AND application_period_id = ?
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('ii', $userId, $periodId);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
            $stmt->close();
            if ($exists) {
                return true;
            }
        }
    }

    $startDate = trim((string) ($period['start_date'] ?? ''));
    $endDate = trim((string) ($period['end_date'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) !== 1) {
        $startDate = '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) !== 1) {
        $endDate = '';
    }

    $stmt = $conn->prepare(
        "SELECT id
         FROM applications
         WHERE user_id = ?
           AND (? = '' OR DATE(COALESCE(submitted_at, created_at)) >= ?)
           AND (? = '' OR DATE(COALESCE(submitted_at, created_at)) <= ?)
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('issss', $userId, $startDate, $startDate, $endDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
    $stmt->close();

    return $exists;
}

