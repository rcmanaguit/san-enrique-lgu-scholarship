<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_admin('../index.php');

$format = strtolower(trim((string) ($_GET['format'] ?? 'xlsx')));
$dataset = strtolower(trim((string) ($_GET['dataset'] ?? '')));
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$periodScope = normalize_period_scope((string) ($_GET['period_scope'] ?? 'all'), 'all');
$periodIdFilter = (int) ($_GET['period_id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$queueFilter = trim((string) ($_GET['queue'] ?? ''));
$showExportCenter = $dataset === '';

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

$activePeriod = current_active_application_period($conn);
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

$buildApplicationScopeWhere = static function (string $alias = 'applications') use ($esc, $periodIdFilter, $selectedPeriodForFilter, $periodScope, $activePeriod, $hasApplicationPeriodColumn): array {
    $prefix = trim($alias) !== '' ? trim($alias) . '.' : '';
    $whereParts = [];

    if ($periodIdFilter > 0) {
        if ($selectedPeriodForFilter) {
            if ($hasApplicationPeriodColumn) {
                $whereParts[] = "{$prefix}application_period_id = " . (int) $periodIdFilter;
            } else {
                $filterSemester = trim((string) ($selectedPeriodForFilter['semester'] ?? ''));
                $filterSchoolYear = trim((string) ($selectedPeriodForFilter['academic_year'] ?? ''));
                if ($filterSemester !== '' && $filterSchoolYear !== '') {
                    $whereParts[] = "{$prefix}semester = " . $esc($filterSemester);
                    $whereParts[] = "{$prefix}school_year = " . $esc($filterSchoolYear);
                } else {
                    $whereParts[] = "1 = 0";
                }
            }
        } else {
            $whereParts[] = "1 = 0";
        }
        return $whereParts;
    }

    $activePeriodId = (int) ($activePeriod['id'] ?? 0);
    $activeSemester = trim((string) ($activePeriod['semester'] ?? ''));
    $activeSchoolYear = trim((string) ($activePeriod['academic_year'] ?? ''));
    if ($periodScope === 'active') {
        if ($activePeriod) {
            if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
                $whereParts[] = "{$prefix}application_period_id = " . $activePeriodId;
            } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
                $whereParts[] = "{$prefix}semester = " . $esc($activeSemester);
                $whereParts[] = "{$prefix}school_year = " . $esc($activeSchoolYear);
            } else {
                $whereParts[] = "1 = 0";
            }
        } else {
            $whereParts[] = "1 = 0";
        }
    } elseif ($periodScope === 'archived' && $activePeriod) {
        if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
            $whereParts[] = "({$prefix}application_period_id IS NULL OR {$prefix}application_period_id <> " . $activePeriodId . ")";
        } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
            $whereParts[] = "({$prefix}semester <> " . $esc($activeSemester) . " OR {$prefix}school_year <> " . $esc($activeSchoolYear) . ")";
        }
    }

    return $whereParts;
};

if ($showExportCenter) {
    $pageTitle = 'Export Center';
    $periodOptions = [];
    if (table_exists($conn, 'application_periods')) {
        $resultPeriods = $conn->query(
            "SELECT id, period_name, semester, academic_year, period_status
             FROM application_periods
             ORDER BY id DESC"
        );
        if ($resultPeriods instanceof mysqli_result) {
            $periodOptions = $resultPeriods->fetch_all(MYSQLI_ASSOC);
        }
    }

    $scopeLabel = $selectedPeriodForFilter
        ? format_application_period($selectedPeriodForFilter)
        : match ($periodScope) {
            'active' => 'Active Period',
            'archived' => 'Completed Periods',
            default => 'All Periods',
        };

    $reportCatalog = [
        ['value' => 'status_summary', 'label' => 'Application Status Summary', 'detail' => 'Counts per application workflow status.'],
        ['value' => 'scholarship_summary', 'label' => 'Applicant Type Summary', 'detail' => 'Breakdown of new and renewal applications.'],
        ['value' => 'school_summary', 'label' => 'School Summary', 'detail' => 'Applications grouped by school and school type.'],
        ['value' => 'barangay_summary', 'label' => 'Barangay Summary', 'detail' => 'Applications grouped by barangay.'],
        ['value' => 'period_performance', 'label' => 'Period Performance Summary', 'detail' => 'Applications, released, rejected, ready for release, and disbursed totals per period.'],
        ['value' => 'monthly_disbursements', 'label' => 'Semester Disbursement Summary', 'detail' => 'Released amounts and record counts per semester.'],
        ['value' => 'approved_scholars', 'label' => 'Released Scholars', 'detail' => 'Detailed list of scholars with released payouts.'],
        ['value' => 'audit_logs', 'label' => 'Audit Logs', 'detail' => 'System activity and tracking history.'],
        ['value' => 'sms_logs', 'label' => 'SMS Logs', 'detail' => 'Sent message history and delivery status.'],
    ];
    $formatCatalog = [
        ['value' => 'pdf', 'label' => 'PDF', 'detail' => 'Best for printing and final documents.'],
        ['value' => 'docx', 'label' => 'DOCX', 'detail' => 'Best for editable reports.'],
        ['value' => 'xlsx', 'label' => 'XLSX', 'detail' => 'Best for spreadsheets and analysis.'],
    ];

    include __DIR__ . '/../../includes/header.php';
    $pageHeaderEyebrow = 'Reports';
    $pageHeaderTitle = 'Export Center';
    $pageHeaderDescription = 'Generate downloadable reports using the current period filters. Choose a report, choose a file format, then export.';
    $pageHeaderSecondaryInfo = 'Current scope: <strong>' . e($scopeLabel) . '</strong>';
    $pageHeaderActions = '<a href="../shared/analytics.php?period_scope=' . e($periodScope) . '&period_id=' . (int) $periodIdFilter . '" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Analytics</a>';
    include __DIR__ . '/../../includes/partials/page-shell-header.php';
    ?>

    <div class="card card-soft shadow-sm mb-3 page-shell-section">
        <div class="card-body">
            <h2 class="h6 mb-2">View Scope</h2>
            <p class="small text-muted mb-3">Exports use the same period filter logic as Analytics. Change the scope here if you want a different report context.</p>
            <form method="get" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm">Period Scope</label>
                    <select class="form-select form-select-sm" name="period_scope">
                        <option value="active" <?= $periodScope === 'active' ? 'selected' : '' ?>>Active Period</option>
                        <option value="archived" <?= $periodScope === 'archived' ? 'selected' : '' ?>>Completed Periods</option>
                        <option value="all" <?= $periodScope === 'all' ? 'selected' : '' ?>>All Periods</option>
                    </select>
                </div>
                <div class="col-6 col-md-5">
                    <label class="form-label form-label-sm">Specific Period</label>
                    <select class="form-select form-select-sm" name="period_id">
                        <option value="0">All</option>
                        <?php foreach ($periodOptions as $periodOption): ?>
                            <?php $optionLabel = format_application_period($periodOption); ?>
                            <option value="<?= (int) ($periodOption['id'] ?? 0) ?>" <?= (int) ($periodOption['id'] ?? 0) === $periodIdFilter ? 'selected' : '' ?>>
                                <?= e($optionLabel !== '' ? $optionLabel : ((string) ($periodOption['period_name'] ?? 'Application Period'))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Apply Scope</button>
                    <a href="export-reports.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card card-soft shadow-sm h-100 page-shell-section">
                <div class="card-body">
                    <h2 class="h6 mb-2">Choose Report</h2>
                    <p class="small text-muted mb-3">Each report focuses on a different operational question. Pick the one that matches what you need to submit or review.</p>
                    <div class="list-group list-group-flush">
                        <?php foreach ($reportCatalog as $reportItem): ?>
                            <div class="list-group-item px-0">
                                <div class="fw-semibold"><?= e($reportItem['label']) ?></div>
                                <div class="small text-muted"><?= e($reportItem['detail']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card card-soft shadow-sm h-100 page-shell-section">
                <div class="card-body">
                    <h2 class="h6 mb-2">Generate Export</h2>
                    <p class="small text-muted mb-3">Choose a report and file format, then export it using the current scope.</p>
                    <form method="get" class="row g-3">
                        <input type="hidden" name="period_scope" value="<?= e($periodScope) ?>">
                        <input type="hidden" name="period_id" value="<?= (int) $periodIdFilter ?>">
                        <div class="col-12">
                            <label class="form-label form-label-sm">Report</label>
                            <select name="dataset" class="form-select">
                                <?php foreach ($reportCatalog as $reportItem): ?>
                                    <option value="<?= e($reportItem['value']) ?>"><?= e($reportItem['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm">Format</label>
                            <select name="format" class="form-select">
                                <?php foreach ($formatCatalog as $formatItem): ?>
                                    <option value="<?= e($formatItem['value']) ?>" <?= $formatItem['value'] === 'xlsx' ? 'selected' : '' ?>><?= e($formatItem['label']) ?> - <?= e($formatItem['detail']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-download me-1"></i>Export Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php
    include __DIR__ . '/../../includes/footer.php';
    return;
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
    $activePeriod = current_active_application_period($conn);
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
    $whereParts = ["a.status = 'released'"];
    if ($fromDate !== '' && $toDate !== '') {
        $whereParts[] = "COALESCE(a.submitted_at, a.created_at) BETWEEN " . $esc($fromDate . ' 00:00:00') . " AND " . $esc($toDate . ' 23:59:59');
    }
    $whereParts = array_merge($whereParts, $buildApplicationScopeWhere('a'));
    $where = implode(' AND ', $whereParts);

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
    $whereParts = [];
    if ($fromDate !== '' && $toDate !== '') {
        $whereParts[] = "COALESCE(submitted_at, created_at) BETWEEN " . $esc($fromDate . ' 00:00:00') . " AND " . $esc($toDate . ' 23:59:59');
    }
    $whereParts = array_merge($whereParts, $buildApplicationScopeWhere());
    $whereSql = $whereParts ? (' WHERE ' . implode(' AND ', $whereParts)) : '';
    $sql = "SELECT applicant_type, COUNT(*) AS total
            FROM applications
            {$whereSql}
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
} elseif ($dataset === 'school_summary') {
    $whereParts = [];
    if ($fromDate !== '' && $toDate !== '') {
        $whereParts[] = "COALESCE(submitted_at, created_at) BETWEEN " . $esc($fromDate . ' 00:00:00') . " AND " . $esc($toDate . ' 23:59:59');
    }
    $whereParts = array_merge($whereParts, $buildApplicationScopeWhere());
    $whereParts[] = "TRIM(COALESCE(school_name, '')) <> ''";
    $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
    $sql = "SELECT school_name, school_type, COUNT(*) AS total
            FROM applications
            {$whereSql}
            GROUP BY school_name, school_type
            ORDER BY total DESC, school_name ASC";
    $result = $conn->query($sql);
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($rows as &$row) {
        $row['school_type'] = strtoupper((string) ($row['school_type'] ?? '-'));
    }
    unset($row);

    $columns = [
        'school_name' => 'School',
        'school_type' => 'School Type',
        'total' => 'Applications',
    ];
    $title = 'San Enrique LGU Scholarship - School Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_school_summary_' . date('Ymd_His');
} elseif ($dataset === 'barangay_summary') {
    $whereParts = [];
    if ($fromDate !== '' && $toDate !== '') {
        $whereParts[] = "COALESCE(submitted_at, created_at) BETWEEN " . $esc($fromDate . ' 00:00:00') . " AND " . $esc($toDate . ' 23:59:59');
    }
    $whereParts = array_merge($whereParts, $buildApplicationScopeWhere());
    $whereParts[] = "TRIM(COALESCE(barangay, '')) <> ''";
    $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
    $sql = "SELECT barangay, COUNT(*) AS total
            FROM applications
            {$whereSql}
            GROUP BY barangay
            ORDER BY total DESC, barangay ASC";
    $result = $conn->query($sql);
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $columns = [
        'barangay' => 'Barangay',
        'total' => 'Applications',
    ];
    $title = 'San Enrique LGU Scholarship - Barangay Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_barangay_summary_' . date('Ymd_His');
} elseif ($dataset === 'period_performance') {
    $whereParts = [];
    if ($fromDate !== '' && $toDate !== '') {
        $whereParts[] = "COALESCE(a.submitted_at, a.created_at) BETWEEN " . $esc($fromDate . ' 00:00:00') . " AND " . $esc($toDate . ' 23:59:59');
    }
    $whereParts = array_merge($whereParts, $buildApplicationScopeWhere('a'));
    $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
    $sql = "SELECT
                COALESCE(a.school_year, '-') AS school_year,
                COALESCE(a.semester, '-') AS semester,
                COUNT(*) AS total_applications,
                SUM(CASE WHEN a.status = 'released' THEN 1 ELSE 0 END) AS released_count,
                SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                SUM(CASE WHEN a.status = 'approved_for_release' THEN 1 ELSE 0 END) AS ready_for_release_count,
                FORMAT(COALESCE(SUM(d.amount), 0), 2) AS total_disbursed
            FROM applications a
            LEFT JOIN disbursements d ON d.application_id = a.id
            {$whereSql}
            GROUP BY a.school_year, a.semester
            ORDER BY CAST(SUBSTRING_INDEX(COALESCE(a.school_year, '0-0'), '-', 1) AS UNSIGNED) DESC,
                     FIELD(a.semester, 'Second Semester', 'First Semester') DESC";
    $result = $conn->query($sql);
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($rows as &$row) {
        $semester = trim((string) ($row['semester'] ?? ''));
        $semesterShort = match ($semester) {
            'First Semester' => '1st Sem',
            'Second Semester' => '2nd Sem',
            default => $semester,
        };
        $row['period_label'] = trim($semesterShort . ' ' . (string) ($row['school_year'] ?? ''));
    }
    unset($row);

    $columns = [
        'period_label' => 'Period',
        'total_applications' => 'Applications',
        'released_count' => 'Released',
        'rejected_count' => 'Rejected',
        'ready_for_release_count' => 'Ready for Release',
        'total_disbursed' => 'Total Disbursed (PHP)',
    ];
    $title = 'San Enrique LGU Scholarship - Period Performance Summary' . $rangeSuffix;
    $filenameBase = 'san_enrique_lgu_scholarship_period_performance_' . date('Ymd_His');
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
