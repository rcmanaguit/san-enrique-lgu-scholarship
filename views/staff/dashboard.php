<?php
require __DIR__ . '/../layouts/header.php';

use App\Config\Database;
use App\Models\ApplicationPeriod;

// Security: Ensure the user is Staff or Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Staff', 'Admin'])) {
    header('Location: ' . base_url('login'));
    exit;
}

$db = Database::connect();
$applicationSettings = ApplicationPeriod::getCurrent();
$currentSchoolYear = trim((string) ($applicationSettings['school_year'] ?? ''));
$currentSemester = trim((string) ($applicationSettings['semester'] ?? ''));
$activePeriodLabel = ApplicationPeriod::activePeriodLabel($applicationSettings);
$hasConfiguredApplicationPeriod = $currentSchoolYear !== '' && $currentSemester !== '';

$periodWhereSql = '';
$periodParams = [];

if ($hasConfiguredApplicationPeriod) {
    $periodWhereSql = " AND school_year = :school_year AND semester = :semester";
    $periodParams = [
        'school_year' => $currentSchoolYear,
        'semester' => $currentSemester,
    ];
}

// ------------------------------------------------------------------
// LIVE ANALYTICS FOR STAFF (Counter Cards)
// ------------------------------------------------------------------
$workflowCountsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN COALESCE(status, '') IN ('', 'Submitted', 'Initial_Review', 'Pending_Resubmission') THEN 1 ELSE 0 END) AS review_count,
        SUM(CASE WHEN status = 'For_Interview' THEN 1 ELSE 0 END) AS interview_count,
        SUM(CASE WHEN status IN ('Eligible_Awaiting_SOA', 'SOA_Under_Review', 'SOA_Resubmission_Required', 'SOA_Overdue') THEN 1 ELSE 0 END) AS soa_count,
        SUM(CASE WHEN status = 'Approved_Pending_Payroll' THEN 1 ELSE 0 END) AS payout_count,
        SUM(CASE WHEN status IN ('Approved_Finished', 'Not_Eligible', 'Forfeited') THEN 1 ELSE 0 END) AS closed_count,
        SUM(CASE WHEN status IN ('Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue') THEN 1 ELSE 0 END) AS returned_count
    FROM applications
    WHERE 1 = 1" . $periodWhereSql
);
$workflowCountsStmt->execute($periodParams);
$workflowCounts = $workflowCountsStmt->fetch() ?: [];

$totalApplicationsCount = (int) ($workflowCounts['total_count'] ?? 0);
$reviewCount = (int) ($workflowCounts['review_count'] ?? 0);
$interviewCount = (int) ($workflowCounts['interview_count'] ?? 0);
$soaPhaseCount = (int) ($workflowCounts['soa_count'] ?? 0);
$payoutCount = (int) ($workflowCounts['payout_count'] ?? 0);
$closedCount = (int) ($workflowCounts['closed_count'] ?? 0);
$returnedCount = (int) ($workflowCounts['returned_count'] ?? 0);

// ------------------------------------------------------------------
// MAIN TASK LIST (Current Queue)
// ------------------------------------------------------------------
$stmtQueue = $db->prepare("
    SELECT a.id, a.status, a.created_at, p.first_name, p.last_name, p.address_barangay, p.school_name
    FROM applications a
    JOIN student_profiles p ON a.student_id = p.id
    WHERE a.status IN ('Submitted', 'Initial_Review', 'SOA_Under_Review', 'Pending_Resubmission')
    " . ($hasConfiguredApplicationPeriod ? "AND a.school_year = :school_year AND a.semester = :semester" : "") . "
    ORDER BY a.created_at ASC
");
$stmtQueue->execute($periodParams);
$queue = $stmtQueue->fetchAll();
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">

        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">

            <div class="app-page-header">
                <div>
                    <h1 class="app-page-title"><i class="fa-solid fa-clipboard-check me-2"></i>Staff Dashboard</h1>
                </div>
                <div class="app-page-header-actions">
                    <a href="<?php echo htmlspecialchars(base_url('staff/applications')); ?>" class="btn btn-outline-primary">
                        <i class="fa-solid fa-table-list me-2"></i>Applications
                    </a>
                </div>
            </div>

            <div class="alert <?php echo $hasConfiguredApplicationPeriod ? 'alert-info' : 'alert-secondary'; ?> border-0 shadow-sm mb-4">
                <div class="fw-bold">Current period: <?php echo htmlspecialchars($activePeriodLabel); ?></div>
            </div>

            <div class="app-kpi-line mb-4">
                <div class="app-kpi-line-item">
                    <div class="app-stat-card" style="--stat-accent: #0d5b89;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Total</div>
                                <h2 class="app-stat-value"><?php echo $totalApplicationsCount; ?></h2>
                                <div class="app-stat-note">All applications this period</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-layer-group"></i></span>
                        </div>
                    </div>
                </div>
                <div class="app-kpi-line-item">
                    <div class="app-stat-card" style="--stat-accent: #0d6efd;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Review</div>
                                <h2 class="app-stat-value"><?php echo $reviewCount; ?></h2>
                                <div class="app-stat-note"><?php echo $returnedCount; ?> returned or resubmitting</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-file-import"></i></span>
                        </div>
                    </div>
                </div>
                <div class="app-kpi-line-item">
                    <div class="app-stat-card" style="--stat-accent: #c58c00;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Interview</div>
                                <h2 class="app-stat-value"><?php echo $interviewCount; ?></h2>
                                <div class="app-stat-note">Waiting for schedule or result</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-users-rectangle"></i></span>
                        </div>
                    </div>
                </div>
                <div class="app-kpi-line-item">
                    <div class="app-stat-card" style="--stat-accent: #0f87a3;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">SOA</div>
                                <h2 class="app-stat-value"><?php echo $soaPhaseCount; ?></h2>
                                <div class="app-stat-note">Awaiting or checking SOA</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                        </div>
                    </div>
                </div>
                <div class="app-kpi-line-item">
                    <div class="app-stat-card" style="--stat-accent: #1f6f43;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Payout</div>
                                <h2 class="app-stat-value"><?php echo $payoutCount; ?></h2>
                                <div class="app-stat-note">Waiting for payout</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-wallet"></i></span>
                        </div>
                    </div>
                </div>
                <div class="app-kpi-line-item">
                    <a href="<?php echo htmlspecialchars(base_url('staff/archive?lifecycle=Completed')); ?>" class="app-stat-card text-decoration-none d-block" style="--stat-accent: #5b6776;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Closed</div>
                                <h2 class="app-stat-value"><?php echo $closedCount; ?></h2>
                                <div class="app-stat-note">Finished, not eligible, or forfeited</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-flag-checkered"></i></span>
                        </div>
                    </a>
                </div>
            </div>

            <div class="app-surface app-table-card">
                <div class="app-surface-header">
                    <div>
                    <h5 class="app-surface-title">Needs Review</h5>
                    </div>
                    <div class="input-group app-search-input" style="width: min(280px, 100%);">
                        <span class="input-group-text bg-light border-end-0"><i
                                class="fa-solid fa-magnifying-glass small"></i></span>
                        <input type="text" id="staffSearch" class="form-control form-control-sm border-start-0 bg-light"
                            placeholder="Search name or barangay...">
                    </div>
                </div>
                <div class="p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="queueTable">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3">Applicant Name</th>
                                    <th class="py-3">Barangay</th>
                                    <th class="py-3">Current Status</th>
                                    <th class="pe-4 py-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($queue)): ?>
                                    <tr>
                                        <td colspan="4" class="p-0">
                                            <div class="app-empty-state">
                                                <div class="app-empty-state-icon"><i class="fa-solid fa-check-double"></i></div>
                                                <h5 class="app-empty-state-title">All caught up!</h5>
                                                <p class="app-empty-state-copy">Nothing is waiting for review right now.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($queue as $row): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark">
                                                    <?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($row['school_name']); ?>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border">
                                                    <?php echo $row['address_barangay']; ?>
                                                </span></td>
                                            <td>
                                                <?php
                                                $statusClass = 'bg-secondary';
                                                if ($row['status'] === 'Submitted')
                                                    $statusClass = 'bg-primary';
                                                if ($row['status'] === 'SOA_Under_Review')
                                                    $statusClass = 'bg-info';
                                                if ($row['status'] === 'Pending_Resubmission')
                                                    $statusClass = 'bg-danger';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo str_replace('_', ' ', $row['status']); ?>
                                                </span>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <a href="<?php echo htmlspecialchars(base_url('staff/verify-documents/' . $row['id'])); ?>"
                                                    class="btn btn-sm btn-outline-primary fw-bold">
                                                    Open <i class="fa-solid fa-chevron-right ms-1"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
    // Simple real-time table filtering
    document.getElementById('staffSearch').addEventListener('keyup', function () {
        let filter = this.value.toUpperCase();
        let rows = document.querySelector("#queueTable tbody").rows;

        for (let i = 0; i < rows.length; i++) {
            let nameCol = rows[i].cells[0].textContent.toUpperCase();
            let brgyCol = rows[i].cells[1].textContent.toUpperCase();
            if (nameCol.indexOf(filter) > -1 || brgyCol.indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    });
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
