<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Analytics';
$extraJs = ['../assets/vendor/chartjs/chart.umd.min.js'];

$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$periodScope = normalize_period_scope((string) ($_GET['period_scope'] ?? 'active'), 'active');
$periodIdFilter = (int) ($_GET['period_id'] ?? 0);

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

$fromDateTime = $fromDate !== '' ? ($fromDate . ' 00:00:00') : '';
$toDateTime = $toDate !== '' ? ($toDate . ' 23:59:59') : '';

$summary = [
    'applications_total' => 0,
    'under_review' => 0,
    'needs_resubmission' => 0,
    'for_interview' => 0,
    'for_soa' => 0,
    'approved_for_release' => 0,
    'released' => 0,
    'disbursement_amount' => 0.0,
    'disbursement_records' => 0,
    'rejected' => 0,
];
$statusBreakdown = [];
$topSchools = [];
$applicantTypeBreakdown = [];
$schoolTypeBreakdown = [];
$barangayBreakdown = [];
$periodPerformance = [];
$semesterSubmissions = [];
$semesterDisbursements = [];
$periodOptions = [];
$selectedPeriodForFilter = null;
$activePeriod = null;
$activePeriodLabel = 'No Active Period';
$scopeLabel = 'Active Period';

$semesterLabel = static function (string $semester, string $schoolYear): string {
    $semester = trim($semester);
    $schoolYear = trim($schoolYear);
    $semesterShort = match ($semester) {
        'First Semester' => '1st Sem',
        'Second Semester' => '2nd Sem',
        default => $semester,
    };
    $label = trim($semesterShort . ' ' . $schoolYear);
    return $label !== '' ? $label : 'Semester';
};

if (db_ready()) {
    $hasApplicationPeriodColumn = table_exists($conn, 'applications') && table_column_exists($conn, 'applications', 'application_period_id');
    $activePeriod = current_active_application_period($conn);
    if ($activePeriod) {
        $activePeriodLabel = format_application_period($activePeriod);
    }
    if (table_exists($conn, 'application_periods')) {
        $resultPeriods = $conn->query(
            "SELECT id, period_name, semester, academic_year, period_status
             FROM application_periods
             ORDER BY id DESC"
        );
        if ($resultPeriods instanceof mysqli_result) {
            $periodOptions = $resultPeriods->fetch_all(MYSQLI_ASSOC);
        }
        if ($periodIdFilter > 0) {
            foreach ($periodOptions as $periodOption) {
                if ((int) ($periodOption['id'] ?? 0) === $periodIdFilter) {
                    $selectedPeriodForFilter = $periodOption;
                    break;
                }
            }
        }
    }

    $buildApplicationWhere = static function (string $alias = '') use ($conn, $fromDateTime, $toDateTime, $periodScope, $periodIdFilter, $selectedPeriodForFilter, $activePeriod, $hasApplicationPeriodColumn): string {
        $q = static fn(string $value): string => "'" . $conn->real_escape_string($value) . "'";
        $prefix = trim($alias) !== '' ? trim($alias) . '.' : '';
        $whereParts = [];
        if ($fromDateTime !== '' && $toDateTime !== '') {
            $whereParts[] = "COALESCE({$prefix}submitted_at, {$prefix}created_at) BETWEEN " . $q($fromDateTime) . " AND " . $q($toDateTime);
        }

        if ($periodIdFilter > 0) {
            if ($selectedPeriodForFilter) {
                if ($hasApplicationPeriodColumn) {
                    $whereParts[] = "{$prefix}application_period_id = " . (int) $periodIdFilter;
                } else {
                    $filterSemester = trim((string) ($selectedPeriodForFilter['semester'] ?? ''));
                    $filterSchoolYear = trim((string) ($selectedPeriodForFilter['academic_year'] ?? ''));
                    if ($filterSemester !== '' && $filterSchoolYear !== '') {
                        $whereParts[] = "{$prefix}semester = " . $q($filterSemester);
                        $whereParts[] = "{$prefix}school_year = " . $q($filterSchoolYear);
                    } else {
                        $whereParts[] = '1 = 0';
                    }
                }
            } else {
                $whereParts[] = '1 = 0';
            }
        } elseif ($periodScope !== 'all') {
            $activePeriodId = (int) ($activePeriod['id'] ?? 0);
            $activeSemester = trim((string) ($activePeriod['semester'] ?? ''));
            $activeSchoolYear = trim((string) ($activePeriod['academic_year'] ?? ''));
            if ($periodScope === 'active') {
                if ($activePeriod) {
                    if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
                        $whereParts[] = "{$prefix}application_period_id = " . $activePeriodId;
                    } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
                        $whereParts[] = "{$prefix}semester = " . $q($activeSemester);
                        $whereParts[] = "{$prefix}school_year = " . $q($activeSchoolYear);
                    } else {
                        $whereParts[] = '1 = 0';
                    }
                } else {
                    $whereParts[] = '1 = 0';
                }
            } elseif ($periodScope === 'archived' && $activePeriod) {
                if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
                    $whereParts[] = "({$prefix}application_period_id IS NULL OR {$prefix}application_period_id <> " . $activePeriodId . ")";
                } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
                    $whereParts[] = "({$prefix}semester <> " . $q($activeSemester) . " OR {$prefix}school_year <> " . $q($activeSchoolYear) . ")";
                }
            }
        }

        return $whereParts ? implode(' AND ', $whereParts) : '1 = 1';
    };

    $applicationWhere = $buildApplicationWhere();
    $applicationWhereAlias = static fn(string $alias): string => $buildApplicationWhere($alias);

    if ($selectedPeriodForFilter) {
        $scopeLabel = format_application_period($selectedPeriodForFilter);
    } else {
        $scopeLabel = match ($periodScope) {
            'active' => 'Active Period',
            'archived' => 'Completed Periods',
            default => 'All Periods',
        };
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM applications
         WHERE {$applicationWhere}"
    );
    if ($stmt) {
        $stmt->execute();
        $summary['applications_total'] = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total_records, COALESCE(SUM(amount), 0) AS total_amount
         FROM disbursements d
         INNER JOIN applications a ON a.id = d.application_id
         WHERE " . $applicationWhereAlias('a')
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $summary['disbursement_records'] = (int) ($row['total_records'] ?? 0);
        $summary['disbursement_amount'] = (float) ($row['total_amount'] ?? 0);
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT status, COUNT(*) AS total
         FROM applications
         WHERE {$applicationWhere}
         GROUP BY status
         ORDER BY total DESC, status ASC"
    );
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $statusBreakdown = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
    foreach ($statusBreakdown as $row) {
        $status = (string) ($row['status'] ?? '');
        if (array_key_exists($status, $summary)) {
            $summary[$status] = (int) ($row['total'] ?? 0);
        }
    }

    $stmt = $conn->prepare(
        "SELECT school_name, COUNT(*) AS total
         FROM applications
         WHERE {$applicationWhere}
           AND school_name IS NOT NULL
           AND school_name <> ''
         GROUP BY school_name
         ORDER BY total DESC, school_name ASC
         LIMIT 8"
    );
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $topSchools = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT applicant_type, COUNT(*) AS total
         FROM applications
         WHERE {$applicationWhere}
         GROUP BY applicant_type
         ORDER BY total DESC, applicant_type ASC"
    );
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $applicantTypeBreakdown = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT school_type, COUNT(*) AS total
         FROM applications
         WHERE {$applicationWhere}
           AND TRIM(COALESCE(school_type, '')) <> ''
         GROUP BY school_type
         ORDER BY total DESC, school_type ASC"
    );
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $schoolTypeBreakdown = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT barangay, COUNT(*) AS total
         FROM applications
         WHERE {$applicationWhere}
           AND TRIM(COALESCE(barangay, '')) <> ''
         GROUP BY barangay
         ORDER BY total DESC, barangay ASC
         LIMIT 10"
    );
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $barangayBreakdown = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT
            COALESCE(a.school_year, '-') AS school_year,
            COALESCE(a.semester, '-') AS semester,
            COUNT(*) AS total_applications,
            SUM(CASE WHEN a.status = 'released' THEN 1 ELSE 0 END) AS released_count,
            SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
            SUM(CASE WHEN a.status = 'approved_for_release' THEN 1 ELSE 0 END) AS ready_for_release_count,
            COALESCE(SUM(d.amount), 0) AS total_disbursed
         FROM applications a
         LEFT JOIN disbursements d ON d.application_id = a.id
         WHERE " . $applicationWhereAlias('a') . "
         GROUP BY a.school_year, a.semester
         ORDER BY CAST(SUBSTRING_INDEX(COALESCE(a.school_year, '0-0'), '-', 1) AS UNSIGNED) DESC,
                  FIELD(a.semester, 'Second Semester', 'First Semester') DESC
         LIMIT 8"
    );
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $periodPerformance = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $result = $conn->query(
        "SELECT
            school_year,
            semester,
            COUNT(*) AS total
         FROM applications
         WHERE {$applicationWhere}
           AND TRIM(COALESCE(school_year, '')) <> ''
           AND semester IN ('First Semester', 'Second Semester')
         GROUP BY school_year, semester
         ORDER BY CAST(SUBSTRING_INDEX(school_year, '-', 1) AS UNSIGNED) DESC,
                  FIELD(semester, 'Second Semester', 'First Semester') DESC
         LIMIT 6"
    );
    if ($result instanceof mysqli_result) {
        $semesterSubmissions = array_reverse($result->fetch_all(MYSQLI_ASSOC));
    }

    $result = $conn->query(
        "SELECT
            a.school_year,
            a.semester,
            COUNT(d.id) AS total_records,
            COALESCE(SUM(d.amount), 0) AS total_amount
         FROM disbursements d
         INNER JOIN applications a ON a.id = d.application_id
         WHERE " . $applicationWhereAlias('a') . "
           AND TRIM(COALESCE(a.school_year, '')) <> ''
           AND a.semester IN ('First Semester', 'Second Semester')
         GROUP BY a.school_year, a.semester
         ORDER BY CAST(SUBSTRING_INDEX(a.school_year, '-', 1) AS UNSIGNED) DESC,
                  FIELD(a.semester, 'Second Semester', 'First Semester') DESC
         LIMIT 6"
    );
    if ($result instanceof mysqli_result) {
        $semesterDisbursements = array_reverse($result->fetch_all(MYSQLI_ASSOC));
    }
}

$statusChartLabels = [];
$statusChartData = [];
foreach ($statusBreakdown as $row) {
    $statusChartLabels[] = application_status_label((string) ($row['status'] ?? ''));
    $statusChartData[] = (int) ($row['total'] ?? 0);
}

$submissionChartLabels = [];
$submissionChartData = [];
foreach ($semesterSubmissions as $row) {
    $submissionChartLabels[] = $semesterLabel(
        (string) ($row['semester'] ?? ''),
        (string) ($row['school_year'] ?? '')
    );
    $submissionChartData[] = (int) ($row['total'] ?? 0);
}
if (!$submissionChartLabels) {
    $submissionChartLabels = ['No Semester Data'];
    $submissionChartData = [0];
}

$disbursementChartLabels = [];
$disbursementChartData = [];
foreach ($semesterDisbursements as $row) {
    $disbursementChartLabels[] = $semesterLabel(
        (string) ($row['semester'] ?? ''),
        (string) ($row['school_year'] ?? '')
    );
    $disbursementChartData[] = (float) ($row['total_amount'] ?? 0);
}
if (!$disbursementChartLabels) {
    $disbursementChartLabels = ['No Semester Data'];
    $disbursementChartData = [0];
}

$topSchoolChartLabels = [];
$topSchoolChartData = [];
foreach ($topSchools as $row) {
    $topSchoolChartLabels[] = (string) ($row['school_name'] ?? '-');
    $topSchoolChartData[] = (int) ($row['total'] ?? 0);
}
if (!$topSchoolChartLabels) {
    $topSchoolChartLabels = ['No Data'];
    $topSchoolChartData = [0];
}

$applicantTypeChartLabels = [];
$applicantTypeChartData = [];
foreach ($applicantTypeBreakdown as $row) {
    $applicantTypeChartLabels[] = ucfirst((string) ($row['applicant_type'] ?? '-'));
    $applicantTypeChartData[] = (int) ($row['total'] ?? 0);
}
if (!$applicantTypeChartLabels) {
    $applicantTypeChartLabels = ['No Data'];
    $applicantTypeChartData = [0];
}

$schoolTypeChartLabels = [];
$schoolTypeChartData = [];
foreach ($schoolTypeBreakdown as $row) {
    $schoolTypeChartLabels[] = strtoupper((string) ($row['school_type'] ?? '-'));
    $schoolTypeChartData[] = (int) ($row['total'] ?? 0);
}
if (!$schoolTypeChartLabels) {
    $schoolTypeChartLabels = ['No Data'];
    $schoolTypeChartData = [0];
}

$barangayChartLabels = [];
$barangayChartData = [];
foreach ($barangayBreakdown as $row) {
    $barangayChartLabels[] = (string) ($row['barangay'] ?? '-');
    $barangayChartData[] = (int) ($row['total'] ?? 0);
}
if (!$barangayChartLabels) {
    $barangayChartLabels = ['No Data'];
    $barangayChartData = [0];
}

$processingRate = $summary['applications_total'] > 0
    ? round((($summary['released'] + $summary['approved_for_release']) / $summary['applications_total']) * 100, 1)
    : 0.0;
$complianceRate = $summary['applications_total'] > 0
    ? round((($summary['needs_resubmission'] + $summary['for_soa']) / $summary['applications_total']) * 100, 1)
    : 0.0;
$rejectionRate = $summary['applications_total'] > 0
    ? round(($summary['rejected'] / $summary['applications_total']) * 100, 1)
    : 0.0;

include __DIR__ . '/../../includes/header.php';
 
$scopeMeta = $scopeLabel;
if ($periodScope === 'active' && $periodIdFilter <= 0 && $activePeriodLabel !== 'No Active Period') {
    $scopeMeta .= ' | Current active: ' . $activePeriodLabel;
}
$pageHeaderEyebrow = 'Monitoring';
$pageHeaderTitle = 'Analytics';
$pageHeaderDescription = '';
$pageHeaderSecondaryInfo = 'Viewing: <strong>' . e($scopeMeta) . '</strong>';
$pageHeaderActions = '';
if (is_admin()) {
    $pageHeaderActions = '
        <a href="../admin-only/export-reports.php?period_scope=' . e($periodScope) . '&period_id=' . (int) $periodIdFilter . '" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-box-archive me-1"></i>Open Export Center
        </a>
        <a href="../admin-only/export-reports.php?dataset=approved_scholars&format=xlsx&period_scope=' . e($periodScope) . '&period_id=' . (int) $periodIdFilter . '" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-file-excel me-1"></i>Released Scholars XLSX
        </a>';
}
include __DIR__ . '/../../includes/partials/page-shell-header.php';
?>

<?php
$baseQuery = http_build_query([
    'period_scope' => $periodScope,
    'period_id' => $periodIdFilter > 0 ? $periodIdFilter : null,
]);
?>

<form method="get" class="card card-soft shadow-sm mb-3 page-shell-section" data-live-filter-form data-live-filter-debounce="200">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label form-label-sm">Period Scope</label>
            <select class="form-select form-select-sm" name="period_scope">
                <option value="active" <?= $periodScope === 'active' ? 'selected' : '' ?>>Active Period</option>
                <option value="archived" <?= $periodScope === 'archived' ? 'selected' : '' ?>>Completed Periods</option>
                <option value="all" <?= $periodScope === 'all' ? 'selected' : '' ?>>All Periods</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
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
        <div class="col-12 col-md-6 d-flex gap-2 align-items-center">
            <span class="small text-muted">Scope: <?= e($scopeLabel) ?><?php if ($periodScope === 'active' && $periodIdFilter <= 0): ?> | Current active: <?= e($activePeriodLabel) ?><?php endif; ?></span>
            <a href="analytics.php" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
    </div>
</form>

<?php if (!db_ready()): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body text-muted">The system is not ready yet. Please contact the administrator.</div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Applications</p><h3><?= number_format($summary['applications_total']) ?></h3></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Under Review</p><h3><?= number_format($summary['under_review']) ?></h3></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Needs Resubmission</p><h3><?= number_format($summary['needs_resubmission']) ?></h3></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">For Interview</p><h3><?= number_format($summary['for_interview']) ?></h3></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Pending SOA</p><h3><?= number_format($summary['for_soa']) ?></h3></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Ready for Payout</p><h3><?= number_format($summary['approved_for_release']) ?></h3></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Released</p><h3><?= number_format($summary['released']) ?></h3></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Disbursement</p><h3>PHP <?= number_format($summary['disbursement_amount'], 2) ?></h3></div></div>
        </div>
    </div>

    <div class="compact-kpi-grid mb-3">
        <div class="compact-kpi-card">
            <small>Processing Progress</small>
            <strong><?= number_format($processingRate, 1) ?>%</strong>
        </div>
        <div class="compact-kpi-card">
            <small>Compliance Load</small>
            <strong><?= number_format($complianceRate, 1) ?>%</strong>
        </div>
        <div class="compact-kpi-card">
            <small>Rejection Rate</small>
            <strong><?= number_format($rejectionRate, 1) ?>%</strong>
        </div>
        <div class="compact-kpi-card">
            <small>Average Disbursement</small>
            <strong>PHP <?= number_format($summary['disbursement_records'] > 0 ? ($summary['disbursement_amount'] / $summary['disbursement_records']) : 0, 2) ?></strong>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Application Volume (Latest 6 Semesters)</h2>
                    <div class="dashboard-chart-slot dashboard-chart-slot-trend">
                        <canvas id="submissionTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Status Distribution</h2>
                    <div class="dashboard-chart-slot dashboard-chart-slot-status">
                        <canvas id="statusBreakdownChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Disbursement Amount Trend (Latest 6 Semesters)</h2>
                    <div class="dashboard-chart-slot dashboard-chart-slot-trend">
                        <canvas id="disbursementTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Top Schools</h2>
                    <div class="dashboard-chart-slot dashboard-chart-slot-trend mb-3">
                        <canvas id="topSchoolsChart"></canvas>
                    </div>
                    <?php if (!$topSchools): ?>
                        <p class="text-muted mb-0">No school data in the selected view.</p>
                    <?php else: ?>
                        <table class="table table-sm align-middle mb-0 report-table">
                            <thead>
                                <tr>
                                    <th>School</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topSchools as $row): ?>
                                    <tr>
                                        <td><?= e((string) ($row['school_name'] ?? '-')) ?></td>
                                        <td class="text-end"><?= (int) ($row['total'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Top Barangays</h2>
                    <div class="dashboard-chart-slot dashboard-chart-slot-trend mb-3">
                        <canvas id="barangayChart"></canvas>
                    </div>
                    <?php if (!$barangayBreakdown): ?>
                        <p class="text-muted mb-0">No barangay data in the selected view.</p>
                    <?php else: ?>
                        <table class="table table-sm align-middle mb-0 report-table">
                            <thead>
                                <tr>
                                    <th>Barangay</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($barangayBreakdown as $row): ?>
                                    <tr>
                                        <td><?= e((string) ($row['barangay'] ?? '-')) ?></td>
                                        <td class="text-end"><?= number_format((int) ($row['total'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Applicant Type Breakdown</h2>
                    <div class="dashboard-chart-slot dashboard-chart-slot-status mb-3">
                        <canvas id="applicantTypeChart"></canvas>
                    </div>
                    <?php if (!$applicantTypeBreakdown): ?>
                        <p class="text-muted mb-0">No applicant type data in the selected view.</p>
                    <?php else: ?>
                        <table class="table table-sm align-middle mb-0 report-table">
                            <thead>
                                <tr>
                                    <th>Applicant Type</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applicantTypeBreakdown as $row): ?>
                                    <tr>
                                        <td><?= e(ucfirst((string) ($row['applicant_type'] ?? '-'))) ?></td>
                                        <td class="text-end"><?= number_format((int) ($row['total'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">School Type Breakdown</h2>
                    <div class="dashboard-chart-slot dashboard-chart-slot-status mb-3">
                        <canvas id="schoolTypeChart"></canvas>
                    </div>
                    <?php if (!$schoolTypeBreakdown): ?>
                        <p class="text-muted mb-0">No school type data in the selected view.</p>
                    <?php else: ?>
                        <table class="table table-sm align-middle mb-0 report-table">
                            <thead>
                                <tr>
                                    <th>School Type</th>
                                    <th class="text-end">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schoolTypeBreakdown as $row): ?>
                                    <tr>
                                        <td><?= e(strtoupper((string) ($row['school_type'] ?? '-'))) ?></td>
                                        <td class="text-end"><?= number_format((int) ($row['total'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 mb-2">Period Performance Detail</h2>
            <?php if (!$periodPerformance): ?>
                <p class="text-muted mb-0">No period performance data in the selected view.</p>
            <?php else: ?>
                <div class="d-none d-lg-block">
                    <table class="table table-sm align-middle mb-0 report-table report-table-wide">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th class="text-end">Applications</th>
                                <th class="text-end">Released</th>
                                <th class="text-end">Rejected</th>
                                <th class="text-end">Ready for Release</th>
                                <th class="text-end">Total Disbursed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periodPerformance as $row): ?>
                                <tr>
                                    <td><?= e($semesterLabel((string) ($row['semester'] ?? ''), (string) ($row['school_year'] ?? ''))) ?></td>
                                    <td class="text-end"><?= number_format((int) ($row['total_applications'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int) ($row['released_count'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int) ($row['rejected_count'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int) ($row['ready_for_release_count'] ?? 0)) ?></td>
                                    <td class="text-end">PHP <?= number_format((float) ($row['total_disbursed'] ?? 0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-lg-none report-card-list">
                    <?php foreach ($periodPerformance as $row): ?>
                        <div class="report-card-item">
                            <h3 class="report-card-title"><?= e($semesterLabel((string) ($row['semester'] ?? ''), (string) ($row['school_year'] ?? ''))) ?></h3>
                            <dl class="report-card-metrics mb-0">
                                <div><dt>Applications</dt><dd><?= number_format((int) ($row['total_applications'] ?? 0)) ?></dd></div>
                                <div><dt>Released</dt><dd><?= number_format((int) ($row['released_count'] ?? 0)) ?></dd></div>
                                <div><dt>Rejected</dt><dd><?= number_format((int) ($row['rejected_count'] ?? 0)) ?></dd></div>
                                <div><dt>Ready for Release</dt><dd><?= number_format((int) ($row['ready_for_release_count'] ?? 0)) ?></dd></div>
                                <div><dt>Total Disbursed</dt><dd>PHP <?= number_format((float) ($row['total_disbursed'] ?? 0), 2) ?></dd></div>
                            </dl>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            const chartDefaults = {
                plugins: {
                    legend: { labels: { color: '#315467' } }
                },
                scales: {
                    x: { ticks: { color: '#4e6a7a' }, grid: { color: 'rgba(45, 143, 213, 0.12)' } },
                    y: { ticks: { color: '#4e6a7a' }, grid: { color: 'rgba(45, 143, 213, 0.12)' }, beginAtZero: true }
                }
            };
            const compactCircleChartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#315467', boxWidth: 12, padding: 10 } }
                }
            };

            new Chart(document.getElementById('submissionTrendChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($submissionChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        label: 'Applications per Semester',
                        data: <?= json_encode($submissionChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        borderColor: '#2d8fd5',
                        backgroundColor: 'rgba(45, 143, 213, 0.18)',
                        tension: 0.28,
                        fill: true
                    }]
                },
                options: chartDefaults
            });

            new Chart(document.getElementById('statusBreakdownChart'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($statusChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        data: <?= json_encode($statusChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        backgroundColor: ['#2d8fd5', '#78c2ff', '#ffb68a', '#4caf50', '#ff9800', '#607d8b', '#ef5350', '#8e99f3']
                    }]
                },
                options: compactCircleChartOptions
            });

            new Chart(document.getElementById('disbursementTrendChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($disbursementChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        label: 'Disbursement Amount per Semester (PHP)',
                        data: <?= json_encode($disbursementChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        backgroundColor: 'rgba(255, 182, 138, 0.65)',
                        borderColor: '#f08c57',
                        borderWidth: 1
                    }]
                },
                options: chartDefaults
            });

            new Chart(document.getElementById('topSchoolsChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($topSchoolChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        label: 'Applications',
                        data: <?= json_encode($topSchoolChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        backgroundColor: 'rgba(45, 143, 213, 0.68)',
                        borderColor: '#2d8fd5',
                        borderWidth: 1
                    }]
                },
                options: {
                    ...chartDefaults,
                    indexAxis: 'y'
                }
            });

            new Chart(document.getElementById('barangayChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($barangayChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        label: 'Applications',
                        data: <?= json_encode($barangayChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        backgroundColor: 'rgba(240, 140, 87, 0.68)',
                        borderColor: '#f08c57',
                        borderWidth: 1
                    }]
                },
                options: {
                    ...chartDefaults,
                    indexAxis: 'y'
                }
            });

            new Chart(document.getElementById('applicantTypeChart'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode($applicantTypeChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        data: <?= json_encode($applicantTypeChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        backgroundColor: ['#2d8fd5', '#ffb68a', '#78c2ff', '#8e99f3']
                    }]
                },
                options: compactCircleChartOptions
            });

            new Chart(document.getElementById('schoolTypeChart'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($schoolTypeChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        data: <?= json_encode($schoolTypeChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        backgroundColor: ['#4caf50', '#ff9800', '#607d8b', '#78c2ff']
                    }]
                },
                options: compactCircleChartOptions
            });
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
