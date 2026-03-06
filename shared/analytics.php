<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Analytics & Reports';
$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js'];

$defaultFromDate = date('Y-m-d', strtotime('-30 days'));
$defaultToDate = date('Y-m-d');
$fromDate = trim((string) ($_GET['from_date'] ?? $defaultFromDate));
$toDate = trim((string) ($_GET['to_date'] ?? $defaultToDate));

$isValidDate = static function (string $value): bool {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
};

if (!$isValidDate($fromDate)) {
    $fromDate = $defaultFromDate;
}
if (!$isValidDate($toDate)) {
    $toDate = $defaultToDate;
}
if ($fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime = $toDate . ' 23:59:59';

$summary = [
    'applications_total' => 0,
    'applications_approved' => 0,
    'applications_pending' => 0,
    'disbursement_amount' => 0.0,
    'disbursement_records' => 0,
];
$statusBreakdown = [];
$topSchools = [];
$semesterSubmissions = [];
$semesterDisbursements = [];

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
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM applications
         WHERE COALESCE(submitted_at, created_at) BETWEEN ? AND ?"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDateTime, $toDateTime);
        $stmt->execute();
        $summary['applications_total'] = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM applications
         WHERE COALESCE(submitted_at, created_at) BETWEEN ? AND ?
           AND status IN ('interview_passed', 'for_soa', 'soa_received', 'awaiting_payout', 'disbursed')"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDateTime, $toDateTime);
        $stmt->execute();
        $summary['applications_approved'] = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM applications
         WHERE status IN ('under_review', 'needs_resubmission', 'for_interview', 'for_soa')"
    );
    if ($stmt) {
        $stmt->execute();
        $summary['applications_pending'] = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total_records, COALESCE(SUM(amount), 0) AS total_amount
         FROM disbursements
         WHERE disbursement_date BETWEEN ? AND ?"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDate, $toDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $summary['disbursement_records'] = (int) ($row['total_records'] ?? 0);
        $summary['disbursement_amount'] = (float) ($row['total_amount'] ?? 0);
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT status, COUNT(*) AS total
         FROM applications
         WHERE COALESCE(submitted_at, created_at) BETWEEN ? AND ?
         GROUP BY status
         ORDER BY total DESC, status ASC"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDateTime, $toDateTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $statusBreakdown = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT school_name, COUNT(*) AS total
         FROM applications
         WHERE COALESCE(submitted_at, created_at) BETWEEN ? AND ?
           AND school_name IS NOT NULL
           AND school_name <> ''
         GROUP BY school_name
         ORDER BY total DESC, school_name ASC
         LIMIT 8"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDateTime, $toDateTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $topSchools = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $result = $conn->query(
        "SELECT
            school_year,
            semester,
            COUNT(*) AS total
         FROM applications
         WHERE TRIM(COALESCE(school_year, '')) <> ''
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
         WHERE TRIM(COALESCE(a.school_year, '')) <> ''
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

$approvalRate = $summary['applications_total'] > 0
    ? ($summary['applications_approved'] / $summary['applications_total']) * 100
    : 0;

$statusChartLabels = [];
$statusChartData = [];
foreach ($statusBreakdown as $row) {
    $statusChartLabels[] = ucwords(str_replace('_', ' ', (string) ($row['status'] ?? '')));
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

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Analytics & Reports</h1>
    <div class="d-flex gap-2">
        <?php if (is_admin()): ?>
            <a href="../admin-only/export-reports.php?dataset=approved_scholars&format=pdf&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-file-pdf me-1"></i>Approved Scholars PDF
            </a>
            <a href="../admin-only/export-reports.php?dataset=approved_scholars&format=docx&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-file-word me-1"></i>Approved Scholars DOCX
            </a>
            <a href="../admin-only/export-reports.php?dataset=approved_scholars&format=xlsx&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-file-excel me-1"></i>Approved Scholars XLSX
            </a>
            <a href="../admin-only/logs.php?from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-clipboard-list me-1"></i>Open Logs
            </a>
        <?php endif; ?>
    </div>
</div>

<?php
$baseQuery = http_build_query([
    'from_date' => $fromDate,
    'to_date' => $toDate,
]);
?>

<?php if (is_admin()): ?>
<div class="card card-soft shadow-sm mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="h6 m-0">Report Exports (Selected Date Range)</h2>
            <p class="small text-muted mb-0">Choose report and format, then click Export.</p>
        </div>
        <form method="get" action="../admin-only/export-reports.php" class="row g-2 align-items-end">
            <input type="hidden" name="from_date" value="<?= e($fromDate) ?>">
            <input type="hidden" name="to_date" value="<?= e($toDate) ?>">
            <div class="col-12 col-md-auto">
                <label class="form-label form-label-sm mb-1">Report</label>
                <select name="dataset" class="form-select form-select-sm">
                    <option value="status_summary">Application Status Summary</option>
                    <option value="scholarship_summary">Applicant Type Summary</option>
                    <option value="monthly_disbursements">Semester Disbursement Summary</option>
                    <option value="approved_scholars">Approved Scholars</option>
                    <option value="audit_logs">Audit Logs</option>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <label class="form-label form-label-sm mb-1">Format</label>
                <select name="format" class="form-select form-select-sm">
                    <option value="pdf">PDF</option>
                    <option value="docx">DOCX</option>
                    <option value="xlsx" selected>XLSX</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-grid">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-download me-1"></i>Export
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<form method="get" class="card card-soft shadow-sm mb-3" data-live-filter-form data-live-filter-debounce="200">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label form-label-sm">From Date</label>
            <input type="date" class="form-control form-control-sm" name="from_date" value="<?= e($fromDate) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label form-label-sm">To Date</label>
            <input type="date" class="form-control form-control-sm" name="to_date" value="<?= e($toDate) ?>">
        </div>
        <div class="col-12 col-md-6 d-flex gap-2 align-items-center">
            <span class="small text-muted">Live filter enabled</span>
            <a href="analytics.php" class="btn btn-outline-secondary btn-sm">Reset</a>
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
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Approved</p><h3><?= number_format($summary['applications_approved']) ?></h3><div class="small text-muted"><?= number_format($approvalRate, 1) ?>% approval rate</div></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Disbursement</p><h3>PHP <?= number_format($summary['disbursement_amount'], 2) ?></h3><div class="small text-muted"><?= number_format($summary['disbursement_records']) ?> record(s)</div></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-soft metric-card h-100"><div class="card-body"><p class="small text-muted mb-1">Pending Queue</p><h3><?= number_format($summary['applications_pending']) ?></h3><div class="small text-muted">Current active pipeline</div></div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Application Volume (Latest 6 Semesters)</h2>
                    <canvas id="submissionTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Status Distribution (Selected Range)</h2>
                    <canvas id="statusBreakdownChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-7">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Disbursement Amount Trend (Latest 6 Semesters)</h2>
                    <canvas id="disbursementTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">Top Schools (Selected Range)</h2>
                    <?php if (!$topSchools): ?>
                        <p class="text-muted mb-0">No school data in selected range.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
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
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
                options: {
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#315467' } }
                    }
                }
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
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
