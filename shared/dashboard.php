<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$adminUser = current_user();
$isAdmin = is_array($adminUser) && (($adminUser['role'] ?? '') === 'admin');
$roleLabel = $isAdmin ? 'Admin' : 'Staff';
$pageTitle = $roleLabel . ' Dashboard';
$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js'];

$statusTotals = array_fill_keys(application_status_options(), 0);
$stats = [
    'applicants' => 0,
    'applications' => 0,
    'in_progress' => 0,
    'approved' => 0,
    'for_soa_submission' => 0,
    'rejected_waitlisted' => 0,
];
$todayApplications = 0;
$currentSemesterApplications = 0;
$currentSemesterLabel = 'Current Semester';
$approvalRate = 0.0;
$processingRate = 0.0;
$recentApplications = [];
$applicantTypeBreakdown = [];

$trendLabels = [];
$trendData = [];

if (db_ready()) {
    $queries = [
        'applicants' => "SELECT COUNT(*) AS total FROM users WHERE role = 'applicant'",
        'applications' => "SELECT COUNT(*) AS total FROM applications",
        'today_applications' => "SELECT COUNT(*) AS total FROM applications WHERE DATE(COALESCE(submitted_at, created_at)) = CURDATE()",
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

    $openPeriod = current_open_application_period($conn);
    if (is_array($openPeriod)) {
        $semesterText = trim((string) ($openPeriod['semester'] ?? ''));
        $academicYearText = trim((string) ($openPeriod['academic_year'] ?? ''));
        $semesterShort = match ($semesterText) {
            'First Semester' => '1st Sem',
            'Second Semester' => '2nd Sem',
            default => $semesterText,
        };
        $labelCandidate = trim($semesterShort . ' ' . $academicYearText);
        if ($labelCandidate !== '') {
            $currentSemesterLabel = $labelCandidate;
        }

        $periodId = (int) ($openPeriod['id'] ?? 0);
        if ($periodId > 0 && table_column_exists($conn, 'applications', 'application_period_id')) {
            $semesterCountSql = "SELECT COUNT(*) AS total FROM applications WHERE application_period_id = " . $periodId;
        } else {
            $semesterFilters = [];
            if ($semesterText !== '') {
                $semesterFilters[] = "semester = '" . $conn->real_escape_string($semesterText) . "'";
            }
            if ($academicYearText !== '') {
                $semesterFilters[] = "school_year = '" . $conn->real_escape_string($academicYearText) . "'";
            }
            $semesterWhere = $semesterFilters ? ('WHERE ' . implode(' AND ', $semesterFilters)) : '';
            $semesterCountSql = "SELECT COUNT(*) AS total FROM applications {$semesterWhere}";
        }

        $semesterCountResult = $conn->query($semesterCountSql);
        if ($semesterCountResult instanceof mysqli_result) {
            $currentSemesterApplications = (int) ($semesterCountResult->fetch_assoc()['total'] ?? 0);
        }
    }

    $statusResult = $conn->query("SELECT status, COUNT(*) AS total FROM applications GROUP BY status");
    if ($statusResult instanceof mysqli_result) {
        while ($row = $statusResult->fetch_assoc()) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $statusTotals)) {
                $statusTotals[$status] = (int) ($row['total'] ?? 0);
            }
        }
    }

    $stats['in_progress'] = ($statusTotals['submitted'] ?? 0)
        + ($statusTotals['for_review'] ?? 0)
        + ($statusTotals['for_interview'] ?? 0);
    $stats['approved'] = ($statusTotals['approved'] ?? 0)
        + ($statusTotals['for_soa_submission'] ?? 0)
        + ($statusTotals['soa_submitted'] ?? 0)
        + ($statusTotals['waitlisted'] ?? 0)
        + ($statusTotals['disbursed'] ?? 0);
    $stats['for_soa_submission'] = (int) ($statusTotals['for_soa_submission'] ?? 0);
    $stats['rejected_waitlisted'] = (int) ($statusTotals['rejected'] ?? 0);

    $recentSql = "SELECT a.id, a.application_no, a.applicant_type, a.status, a.updated_at, u.first_name, u.last_name
                  FROM applications a
                  INNER JOIN users u ON u.id = a.user_id
                  ORDER BY a.updated_at DESC
                  LIMIT 8";
    $recentResult = $conn->query($recentSql);
    if ($recentResult instanceof mysqli_result) {
        $recentApplications = $recentResult->fetch_all(MYSQLI_ASSOC);
    }

    $scholarshipSql = "SELECT applicant_type, COUNT(*) AS total
                       FROM applications
                       GROUP BY applicant_type
                       ORDER BY total DESC
                       LIMIT 6";
    $scholarshipResult = $conn->query($scholarshipSql);
    if ($scholarshipResult instanceof mysqli_result) {
        $applicantTypeBreakdown = $scholarshipResult->fetch_all(MYSQLI_ASSOC);
    }

    $trendSql = "SELECT school_year, semester, COUNT(*) AS total
                 FROM applications
                 WHERE TRIM(COALESCE(school_year, '')) <> ''
                   AND semester IN ('First Semester', 'Second Semester')
                 GROUP BY school_year, semester
                 ORDER BY CAST(SUBSTRING_INDEX(school_year, '-', 1) AS UNSIGNED) DESC,
                          FIELD(semester, 'Second Semester', 'First Semester') DESC
                 LIMIT 6";
    $trendResult = $conn->query($trendSql);
    if ($trendResult instanceof mysqli_result) {
        $semesterRows = array_reverse($trendResult->fetch_all(MYSQLI_ASSOC));
        foreach ($semesterRows as $row) {
            $semesterText = trim((string) ($row['semester'] ?? ''));
            $academicYearText = trim((string) ($row['school_year'] ?? ''));
            $semesterShort = match ($semesterText) {
                'First Semester' => '1st Sem',
                'Second Semester' => '2nd Sem',
                default => $semesterText,
            };
            $label = trim($semesterShort . ' ' . $academicYearText);
            $trendLabels[] = $label !== '' ? $label : 'Semester';
            $trendData[] = (int) ($row['total'] ?? 0);
        }
    }
}

if ($stats['applications'] > 0) {
    $approvalRate = round(($stats['approved'] / $stats['applications']) * 100, 1);
    $processingRate = round(($stats['in_progress'] / $stats['applications']) * 100, 1);
}

$statusChartLabels = [];
$statusChartData = [];
foreach ($statusTotals as $status => $total) {
    if ($total <= 0) {
        continue;
    }
    $statusChartLabels[] = ucwords(str_replace('_', ' ', $status));
    $statusChartData[] = $total;
}
if (!$statusChartLabels) {
    $statusChartLabels = ['No Data'];
    $statusChartData = [1];
}

$scholarshipChartLabels = [];
$scholarshipChartData = [];
foreach ($applicantTypeBreakdown as $row) {
    $label = strtoupper(trim((string) ($row['applicant_type'] ?? '')));
    if ($label === '') {
        $label = 'Unspecified';
    }
    $scholarshipChartLabels[] = $label;
    $scholarshipChartData[] = (int) ($row['total'] ?? 0);
}
if (!$scholarshipChartLabels) {
    $scholarshipChartLabels = ['No Data'];
    $scholarshipChartData = [1];
}

if (!$trendLabels) {
    $trendLabels = ['No Semester Data'];
    $trendData = [0];
}

$trendChartData = $trendData;

include __DIR__ . '/../includes/header.php';
?>

<section class="dashboard-hero card card-soft shadow-sm mb-4">
    <div class="card-body p-4 p-lg-5">
        <div class="row g-4 align-items-center">
            <div class="col-12 col-xl-8">
                <span class="dashboard-hero-badge"><i class="fa-solid fa-bolt me-1"></i>Live Operations</span>
                <h1 class="h3 mt-3 mb-2"><?= e($roleLabel) ?> Command Dashboard</h1>
                <p class="mb-3 text-muted">
                    San Enrique LGU Scholarship real-time summary with application flow, status mix, and priority actions.
                </p>
                <div class="dashboard-kpi-strip">
                    <div class="dashboard-kpi-item">
                        <span>Today</span>
                        <strong><?= number_format($todayApplications) ?></strong>
                    </div>
                    <div class="dashboard-kpi-item">
                        <span><?= e($currentSemesterLabel) ?></span>
                        <strong><?= number_format($currentSemesterApplications) ?></strong>
                    </div>
                    <div class="dashboard-kpi-item">
                        <span>Approval Rate</span>
                        <strong><?= number_format($approvalRate, 1) ?>%</strong>
                    </div>
                    <div class="dashboard-kpi-item">
                        <span>Processing Rate</span>
                        <strong><?= number_format($processingRate, 1) ?>%</strong>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="d-grid gap-2">
                    <a href="applications.php" class="btn btn-primary">
                        <i class="fa-solid fa-folder-tree me-1"></i>Open Application Queue
                    </a>
                    <a href="disbursements.php" class="btn btn-outline-primary">
                        <i class="fa-solid fa-money-check-dollar me-1"></i>Open Payout Queue
                    </a>
                    <a href="sms.php" class="btn btn-outline-primary">
                        <i class="fa-solid fa-comments me-1"></i>Open SMS
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <article class="card card-soft dashboard-stat-card tone-blue h-100">
            <div class="card-body">
                <span class="dashboard-stat-icon"><i class="fa-solid fa-user-graduate"></i></span>
                <p class="small mb-1">Applicants</p>
                <h3><?= number_format($stats['applicants']) ?></h3>
            </div>
        </article>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <article class="card card-soft dashboard-stat-card tone-cyan h-100">
            <div class="card-body">
                <span class="dashboard-stat-icon"><i class="fa-solid fa-file-circle-check"></i></span>
                <p class="small mb-1">Applications</p>
                <h3><?= number_format($stats['applications']) ?></h3>
            </div>
        </article>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <article class="card card-soft dashboard-stat-card tone-amber h-100">
            <div class="card-body">
                <span class="dashboard-stat-icon"><i class="fa-solid fa-hourglass-half"></i></span>
                <p class="small mb-1">In Progress</p>
                <h3><?= number_format($stats['in_progress']) ?></h3>
            </div>
        </article>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <article class="card card-soft dashboard-stat-card tone-green h-100">
            <div class="card-body">
                <span class="dashboard-stat-icon"><i class="fa-solid fa-circle-check"></i></span>
                <p class="small mb-1">Approved</p>
                <h3><?= number_format($stats['approved']) ?></h3>
            </div>
        </article>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <article class="card card-soft dashboard-stat-card tone-peach h-100">
            <div class="card-body">
                <span class="dashboard-stat-icon"><i class="fa-solid fa-file-signature"></i></span>
                <p class="small mb-1">For SOA</p>
                <h3><?= number_format($stats['for_soa_submission']) ?></h3>
            </div>
        </article>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <article class="card card-soft dashboard-stat-card tone-red h-100">
            <div class="card-body">
                <span class="dashboard-stat-icon"><i class="fa-solid fa-circle-xmark"></i></span>
                <p class="small mb-1">Rejected</p>
                <h3><?= number_format($stats['rejected_waitlisted']) ?></h3>
            </div>
        </article>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <section class="card card-soft dashboard-chart-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h6 m-0"><i class="fa-solid fa-chart-line me-1 text-primary"></i>Applications Trend (Latest Semesters)</h2>
                    <span class="badge badge-soft">Live snapshot</span>
                </div>
                <div class="dashboard-chart-canvas">
                    <canvas id="applicationsTrendChart"></canvas>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="card card-soft dashboard-chart-card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="fa-solid fa-chart-pie me-1 text-primary"></i>Status Breakdown</h2>
                <div class="dashboard-chart-canvas dashboard-chart-canvas-compact">
                    <canvas id="statusBreakdownChart"></canvas>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12">
        <section class="card card-soft dashboard-chart-card">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="fa-solid fa-chart-column me-1 text-primary"></i>Top Applicant Types</h2>
                <div class="dashboard-chart-canvas dashboard-chart-canvas-wide">
                    <canvas id="scholarshipTypeChart"></canvas>
                </div>
            </div>
        </section>
    </div>
</div>

<section class="card card-soft shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 mb-3">Workflow Shortcuts</h2>
        <div class="dashboard-action-grid">
            <a href="applications.php" class="dashboard-action-btn">
                <i class="fa-solid fa-folder-tree"></i><span>Application Queue</span>
            </a>
            <a href="disbursements.php" class="dashboard-action-btn">
                <i class="fa-solid fa-money-check-dollar"></i><span>Payout Queue</span>
            </a>
            <a href="sms.php" class="dashboard-action-btn">
                <i class="fa-solid fa-comments"></i><span>SMS</span>
            </a>
            <a href="analytics.php" class="dashboard-action-btn">
                <i class="fa-solid fa-chart-pie"></i><span>Reports</span>
            </a>
            <a href="../admin-only/announcements.php" class="dashboard-action-btn">
                <i class="fa-regular fa-newspaper"></i><span>Announcements</span>
            </a>
            <?php if ($isAdmin): ?>
                <a href="../admin-only/application-periods.php" class="dashboard-action-btn">
                    <i class="fa-solid fa-calendar-days"></i><span>Application Periods</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="card card-soft shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="h5 m-0">Recent Application Updates</h2>
            <a href="applications.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-arrow-right me-1"></i>View All</a>
        </div>
        <?php if (!$recentApplications): ?>
            <p class="text-muted mb-0">No records yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                            <td><span class="badge <?= status_badge_class((string) ($row['status'] ?? '')) ?>"><?= e(strtoupper((string) ($row['status'] ?? ''))) ?></span></td>
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

        const axisColor = '#36566d';
        const gridColor = 'rgba(45, 143, 213, 0.12)';
        const lineGradientColors = ['#2d8fd5', '#78c2ff', '#ffb68a', '#1c4f74', '#59b28a', '#ff8f78'];

        const trendChart = document.getElementById('applicationsTrendChart');
        if (trendChart) {
            const trendContext = trendChart.getContext('2d');
            const gradient = trendContext.createLinearGradient(0, 0, 0, 280);
            gradient.addColorStop(0, 'rgba(45, 143, 213, 0.35)');
            gradient.addColorStop(1, 'rgba(45, 143, 213, 0.04)');

            new Chart(trendChart, {
                type: 'line',
                data: {
                    labels: <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        label: 'Applications per Semester',
                        data: <?= json_encode($trendChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        tension: 0.35,
                        borderWidth: 3,
                        fill: true,
                        borderColor: '#2d8fd5',
                        backgroundColor: gradient,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#2d8fd5',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: { color: axisColor },
                            grid: { color: gridColor }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: axisColor },
                            grid: { color: gridColor }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        const statusChart = document.getElementById('statusBreakdownChart');
        if (statusChart) {
            new Chart(statusChart, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($statusChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        data: <?= json_encode($statusChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        backgroundColor: ['#2d8fd5', '#5fa9dd', '#78c2ff', '#ffb68a', '#ff8f78', '#1c4f74', '#7a8ea0', '#76b18b'],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 10,
                                color: axisColor
                            }
                        }
                    }
                }
            });
        }

        const scholarshipChart = document.getElementById('scholarshipTypeChart');
        if (scholarshipChart) {
            new Chart(scholarshipChart, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($scholarshipChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [{
                        label: 'Applicants',
                        data: <?= json_encode($scholarshipChartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                        backgroundColor: lineGradientColors,
                        borderRadius: 10,
                        borderSkipped: false
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: { color: axisColor, maxRotation: 20, minRotation: 0 },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: axisColor },
                            grid: { color: gridColor }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
