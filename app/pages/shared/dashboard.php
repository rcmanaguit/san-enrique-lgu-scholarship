<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$adminUser = current_user();
$isAdmin = is_array($adminUser) && (($adminUser['role'] ?? '') === 'admin');
$roleLabel = $isAdmin ? 'Admin' : 'Staff';
$pageTitle = $roleLabel . ' Dashboard';
$extraJs = ['../assets/vendor/chartjs/chart.umd.min.js'];

$statusTotals = array_fill_keys(application_status_options(), 0);
$stats = [
    'applications' => 0,
    'under_review' => 0,
    'needs_resubmission' => 0,
    'compliance' => 0,
    'for_interview' => 0,
    'for_soa' => 0,
    'approved_for_release' => 0,
    'released' => 0,
];
$todayApplications = 0;
$currentSemesterApplications = 0;
$currentSemesterLabel = 'Current Semester';
$recentApplications = [];
$applicantTypeBreakdown = [];
$statusChartLabels = [];
$statusChartData = [];
$trendChartLabels = [];
$trendChartData = [];
$isApplicantIntakeOpen = false;

if (db_ready()) {
    $activePeriod = current_active_application_period($conn);
    $openPeriod = current_open_application_period($conn);
    $isApplicantIntakeOpen = $openPeriod !== null
        && (int) ($openPeriod['id'] ?? 0) > 0
        && (int) ($openPeriod['id'] ?? 0) === (int) ($activePeriod['id'] ?? 0);
    if (is_array($activePeriod)) {
        $semesterText = trim((string) ($activePeriod['semester'] ?? ''));
        $academicYearText = trim((string) ($activePeriod['academic_year'] ?? ''));
        $semesterShort = match ($semesterText) {
            'First Semester' => '1st Sem',
            'Second Semester' => '2nd Sem',
            default => $semesterText,
        };
        $labelCandidate = trim($semesterShort . ' ' . $academicYearText);
        if ($labelCandidate !== '') {
            $currentSemesterLabel = $labelCandidate;
        }
    } else {
        $currentSemesterLabel = 'No Active Period';
    }

    $periodWhereSql = '1 = 0';
    $periodId = (int) ($activePeriod['id'] ?? 0);
    if ($periodId > 0 && table_column_exists($conn, 'applications', 'application_period_id')) {
        $periodWhereSql = 'application_period_id = ' . $periodId;
    } elseif (is_array($activePeriod)) {
        $periodFilters = [];
        $semesterText = trim((string) ($activePeriod['semester'] ?? ''));
        $academicYearText = trim((string) ($activePeriod['academic_year'] ?? ''));
        if ($semesterText !== '') {
            $periodFilters[] = "semester = '" . $conn->real_escape_string($semesterText) . "'";
        }
        if ($academicYearText !== '') {
            $periodFilters[] = "school_year = '" . $conn->real_escape_string($academicYearText) . "'";
        }
        if ($periodFilters) {
            $periodWhereSql = implode(' AND ', $periodFilters);
        }
    }

    $queries = [
        'applications' => "SELECT COUNT(*) AS total FROM applications WHERE {$periodWhereSql}",
        'today_applications' => "SELECT COUNT(*) AS total FROM applications WHERE {$periodWhereSql} AND DATE(COALESCE(submitted_at, created_at)) = CURDATE()",
    ];

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        if (!($result instanceof mysqli_result)) {
            continue;
        }
        $total = (int) ($result->fetch_assoc()['total'] ?? 0);
        if ($key === 'today_applications') {
            $todayApplications = $total;
            continue;
        }
        $stats[$key] = $total;
    }

    $currentSemesterApplications = (int) $stats['applications'];

    $statusResult = $conn->query("SELECT status, COUNT(*) AS total FROM applications WHERE {$periodWhereSql} GROUP BY status");
    if ($statusResult instanceof mysqli_result) {
        while ($row = $statusResult->fetch_assoc()) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $statusTotals)) {
                $statusTotals[$status] = (int) ($row['total'] ?? 0);
            }
        }
    }

    $stats['under_review'] = (int) ($statusTotals['under_review'] ?? 0);
    $stats['needs_resubmission'] = (int) ($statusTotals['needs_resubmission'] ?? 0);
    $stats['compliance'] = $stats['needs_resubmission'] + (int) ($statusTotals['for_soa'] ?? 0);
    $stats['for_interview'] = (int) ($statusTotals['for_interview'] ?? 0);
    $stats['for_soa'] = (int) ($statusTotals['for_soa'] ?? 0);
    $stats['approved_for_release'] = (int) ($statusTotals['approved_for_release'] ?? 0);
    $stats['released'] = (int) ($statusTotals['released'] ?? 0);

    $recentSql = "SELECT a.id, a.application_no, a.applicant_type, a.status, a.updated_at, u.first_name, u.last_name
                  FROM applications a
                  INNER JOIN users u ON u.id = a.user_id
                  WHERE {$periodWhereSql}
                  ORDER BY a.updated_at DESC
                  LIMIT 8";
    $recentResult = $conn->query($recentSql);
    if ($recentResult instanceof mysqli_result) {
        $recentApplications = $recentResult->fetch_all(MYSQLI_ASSOC);
    }

    $chartTotals = [];
    foreach ($statusTotals as $status => $total) {
        if ($total <= 0) {
            continue;
        }

        $chartLabel = application_staff_status_label($status);
        $chartTotals[$chartLabel] = (int) ($chartTotals[$chartLabel] ?? 0) + $total;
    }
    foreach ($chartTotals as $chartLabel => $total) {
        $statusChartLabels[] = $chartLabel;
        $statusChartData[] = $total;
    }

    $trendResult = $conn->query(
        "SELECT school_year, semester, COUNT(*) AS total
         FROM applications
         WHERE TRIM(COALESCE(school_year, '')) <> ''
           AND semester IN ('First Semester', 'Second Semester')
         GROUP BY school_year, semester
         ORDER BY CAST(SUBSTRING_INDEX(school_year, '-', 1) AS UNSIGNED) DESC,
                  FIELD(semester, 'Second Semester', 'First Semester') DESC
         LIMIT 6"
    );
    if ($trendResult instanceof mysqli_result) {
        $trendRows = array_reverse($trendResult->fetch_all(MYSQLI_ASSOC));
        foreach ($trendRows as $row) {
            $semesterText = trim((string) ($row['semester'] ?? ''));
            $academicYearText = trim((string) ($row['school_year'] ?? ''));
            $semesterShort = match ($semesterText) {
                'First Semester' => '1st Sem',
                'Second Semester' => '2nd Sem',
                default => $semesterText,
            };
            $trendChartLabels[] = trim($semesterShort . ' ' . $academicYearText);
            $trendChartData[] = (int) ($row['total'] ?? 0);
        }
    }

    $applicantTypeBreakdown = [];
}

if (!$statusChartLabels) {
    $statusChartLabels = ['No Data'];
    $statusChartData = [1];
}

if (!$trendChartLabels) {
    $trendChartLabels = ['No Semester Data'];
    $trendChartData = [0];
}

include __DIR__ . '/../../includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Operations';
$pageHeaderTitle = e($roleLabel) . ' Dashboard';
$pageHeaderDescription = 'Monitor the current working period and open the next queue that needs action.';
$pageHeaderSecondaryInfo = 'Current period: <strong>' . e($currentSemesterLabel) . '</strong> with <strong>' . number_format($currentSemesterApplications) . '</strong> records. Submission: <strong>' . ($isApplicantIntakeOpen ? 'Open' : 'Closed') . '</strong>. Today: <strong>' . number_format($todayApplications) . '</strong> new applications.';
$primaryQueue = 'under_review';
$primaryQueueLabel = 'Review Documents';
if ((int) ($stats['compliance'] ?? 0) > 0 && (int) ($stats['under_review'] ?? 0) === 0) {
    $primaryQueue = 'compliance';
    $primaryQueueLabel = 'Open Compliance Queue';
} elseif ((int) ($stats['for_interview'] ?? 0) > 0 && (int) ($stats['under_review'] ?? 0) === 0 && (int) ($stats['compliance'] ?? 0) === 0) {
    $primaryQueue = 'for_interview';
    $primaryQueueLabel = 'Open Interview Queue';
} elseif ((int) ($stats['approved_for_release'] ?? 0) > 0 && (int) ($stats['under_review'] ?? 0) === 0 && (int) ($stats['compliance'] ?? 0) === 0 && (int) ($stats['for_interview'] ?? 0) === 0) {
    $primaryQueue = 'approved_for_release';
    $primaryQueueLabel = 'Open Release Queue';
}
$pageHeaderPrimaryAction = '<a href="applications.php?queue=' . e($primaryQueue) . '" class="btn btn-primary btn-sm"><i class="fa-solid fa-list-check me-1"></i>' . e($primaryQueueLabel) . '</a>';
$pageHeaderActions = '';
if ($isAdmin && $isApplicantIntakeOpen && (int) ($activePeriod['id'] ?? 0) > 0) {
    $pageHeaderActions = '
        <form method="post" action="../admin-only/application-periods.php" class="d-inline-flex" data-crud-modal="1" data-crud-title="Close Submission?" data-crud-message="Close submission for ' . e($currentSemesterLabel) . '?" data-crud-confirm-text="Close Submission" data-crud-kind="warning">
            <input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">
            <input type="hidden" name="action" value="close_period">
            <input type="hidden" name="id" value="' . (int) ($activePeriod['id'] ?? 0) . '">
            <input type="hidden" name="redirect_to" value="../shared/dashboard.php">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-lock me-1"></i>Close Submission</button>
        </form>
        <a href="../admin-only/application-periods.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-calendar-days me-1"></i>Manage Period</a>
    ';
} elseif ($isAdmin) {
    $pageHeaderActions = '<a href="../admin-only/application-periods.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-calendar-days me-1"></i>Manage Period</a>';
}
include __DIR__ . '/../../includes/partials/page-shell-header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3 col-xl">
        <a href="applications.php?queue=under_review" class="text-reset text-decoration-none d-block h-100">
            <article class="card card-soft dashboard-stat-card tone-cyan h-100">
                <div class="card-body">
                    <span class="dashboard-stat-icon"><i class="fa-solid fa-hourglass-half"></i></span>
                    <p class="small mb-1">Review Documents</p>
                    <h3><?= number_format($stats['under_review']) ?></h3>
                </div>
            </article>
        </a>
    </div>
    <div class="col-6 col-lg-3 col-xl">
        <a href="applications.php?queue=compliance" class="text-reset text-decoration-none d-block h-100">
            <article class="card card-soft dashboard-stat-card tone-amber h-100">
                <div class="card-body">
                    <span class="dashboard-stat-icon"><i class="fa-solid fa-file-arrow-up"></i></span>
                    <p class="small mb-1">For Compliance</p>
                    <h3><?= number_format($stats['compliance']) ?></h3>
                </div>
            </article>
        </a>
    </div>
    <div class="col-6 col-lg-3 col-xl">
        <a href="applications.php?queue=for_interview" class="text-reset text-decoration-none d-block h-100">
            <article class="card card-soft dashboard-stat-card tone-blue h-100">
                <div class="card-body">
                    <span class="dashboard-stat-icon"><i class="fa-solid fa-calendar-check"></i></span>
                    <p class="small mb-1">Schedule Interview</p>
                    <h3><?= number_format($stats['for_interview']) ?></h3>
                </div>
            </article>
        </a>
    </div>
    <div class="col-6 col-lg-3 col-xl">
        <a href="applications.php?queue=approved_for_release" class="text-reset text-decoration-none d-block h-100">
            <article class="card card-soft dashboard-stat-card tone-cyan h-100">
                <div class="card-body">
                    <span class="dashboard-stat-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                    <p class="small mb-1">For Release</p>
                    <h3><?= number_format($stats['approved_for_release']) ?></h3>
                </div>
            </article>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <section class="card card-soft shadow-sm page-shell-section dashboard-chart-panel h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h6 m-0">Application trend</h2>
                    <span class="small text-muted">Latest 6 semesters</span>
                </div>
                <div class="dashboard-chart-slot dashboard-chart-slot-trend">
                    <canvas id="dashboardTrendChart"></canvas>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="card card-soft shadow-sm page-shell-section dashboard-chart-panel h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h6 m-0">Current period status mix</h2>
                    <span class="small text-muted"><?= e($currentSemesterLabel) ?></span>
                </div>
                <div class="dashboard-chart-slot dashboard-chart-slot-status">
                    <canvas id="dashboardStatusChart"></canvas>
                </div>
            </div>
        </section>
    </div>
</div>

<section class="card card-soft shadow-sm mb-4 page-shell-section">
    <div class="card-body">
        <h2 class="h6 mb-3">Other work areas</h2>
        <div class="dashboard-action-grid">
            <a href="disbursements.php" class="dashboard-action-btn">
                <i class="fa-solid fa-money-check-dollar"></i><span>Payout Events</span>
            </a>
            <a href="sms.php" class="dashboard-action-btn">
                <i class="fa-solid fa-comments"></i><span>Communications</span>
            </a>
            <a href="analytics.php" class="dashboard-action-btn">
                <i class="fa-solid fa-chart-pie"></i><span>Analytics</span>
            </a>
            <?php if ($isAdmin): ?>
                <a href="../admin-only/application-periods.php" class="dashboard-action-btn">
                    <i class="fa-solid fa-calendar-days"></i><span>Application Periods</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="card card-soft shadow-sm page-shell-section">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="h5 m-0">Recent queue updates</h2>
            <a href="applications.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-arrow-right me-1"></i>View All</a>
        </div>
        <?php if (!$recentApplications): ?>
            <p class="text-muted mb-0">No records yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-simple-list="1" data-simple-list-visible="3">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Applicant</th>
                            <th>Applicant Type</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentApplications as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) ($row['application_no'] ?? ('#' . (int) $row['id']))) ?></strong>
                                <div class="small text-muted">#<?= (int) $row['id'] ?></div>
                            </td>
                            <td><?= e(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?></td>
                            <td><?= e(strtoupper((string) ($row['applicant_type'] ?? ''))) ?></td>
                            <td><span class="badge <?= status_badge_class((string) ($row['status'] ?? '')) ?>"><?= e(strtoupper(application_staff_status_label((string) ($row['status'] ?? ''))) ) ?></span></td>
                            <td><?= date('M d, Y h:i A', strtotime((string) ($row['updated_at'] ?? 'now'))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            return;
        }

        const textColor = '#315467';
        const gridColor = 'rgba(45, 143, 213, 0.12)';

        new Chart(document.getElementById('dashboardTrendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($trendChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?= json_encode($trendChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    borderColor: '#2d8fd5',
                    backgroundColor: 'rgba(45, 143, 213, 0.16)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ticks: { color: textColor },
                        grid: { color: gridColor }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: textColor, precision: 0 },
                        grid: { color: gridColor }
                    }
                }
            }
        });

        new Chart(document.getElementById('dashboardStatusChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statusChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                datasets: [{
                    data: <?= json_encode($statusChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    backgroundColor: ['#2d8fd5', '#78c2ff', '#ffb68a', '#4caf50', '#ff9800', '#607d8b', '#ef5350', '#8e99f3']
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            usePointStyle: true,
                            boxWidth: 10
                        }
                    }
                }
            }
        });
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
