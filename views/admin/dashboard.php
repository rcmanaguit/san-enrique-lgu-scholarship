<?php
require __DIR__ . '/../layouts/header.php';

use App\Config\Database;
use App\Models\ApplicationPeriod;

// Security: ONLY the Admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ' . base_url('login'));
    exit;
}

$db = Database::connect();
$applicationSettings = ApplicationPeriod::getCurrent();
$currentSchoolYear = trim((string) ($applicationSettings['school_year'] ?? ''));
$currentSemester = trim((string) ($applicationSettings['semester'] ?? ''));
$activePeriodLabel = ApplicationPeriod::activePeriodLabel($applicationSettings);
$isCurrentlyOpen = ApplicationPeriod::isAcceptingApplications($applicationSettings);
$hasConfiguredApplicationPeriod = $currentSchoolYear !== '' && $currentSemester !== '';
$periodStatusLabel = $isCurrentlyOpen ? 'Open' : 'Closed';
$periodParams = [
    'school_year' => $currentSchoolYear,
    'semester' => $currentSemester,
];

// ------------------------------------------------------------------
// BASIC DASHBOARD DATA
// ------------------------------------------------------------------

if ($hasConfiguredApplicationPeriod) {
    $kpiQuery = $db->prepare("
        SELECT
            COUNT(*) AS total_applications,
            SUM(CASE WHEN COALESCE(status, '') IN ('', 'Submitted', 'Initial_Review', 'Pending_Resubmission') THEN 1 ELSE 0 END) AS review_count,
            SUM(CASE WHEN status = 'For_Interview' THEN 1 ELSE 0 END) AS interview_count,
            SUM(CASE WHEN status IN ('Eligible_Awaiting_SOA', 'SOA_Under_Review', 'SOA_Resubmission_Required', 'SOA_Overdue') THEN 1 ELSE 0 END) AS soa_count,
            SUM(CASE WHEN status = 'Approved_Pending_Payroll' THEN 1 ELSE 0 END) AS payout_count,
            SUM(CASE WHEN status IN ('Approved_Finished', 'Not_Eligible', 'Forfeited') THEN 1 ELSE 0 END) AS closed_count,
            SUM(CASE WHEN status = 'Approved_Pending_Payroll' AND payout_batch_id IS NULL THEN 1 ELSE 0 END) AS ready_for_payout_count,
            SUM(CASE WHEN status = 'Approved_Pending_Payroll' AND payout_batch_id IS NOT NULL THEN 1 ELSE 0 END) AS scheduled_payout_count,
            SUM(CASE WHEN status = 'For_Interview' AND interview_batch_id IS NULL THEN 1 ELSE 0 END) AS ready_for_interview_count,
            SUM(CASE WHEN status = 'For_Interview' AND interview_batch_id IS NOT NULL THEN 1 ELSE 0 END) AS scheduled_interview_count,
            SUM(CASE WHEN status IN ('Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue') THEN 1 ELSE 0 END) AS returned_count
        FROM applications
        WHERE school_year = :school_year
          AND semester = :semester
    ");
    $kpiQuery->execute($periodParams);
    $kpiRow = $kpiQuery->fetch() ?: [];

    $totalApplications = (int) ($kpiRow['total_applications'] ?? 0);
    $forReviewCount = (int) ($kpiRow['review_count'] ?? 0);
    $interviewCount = (int) ($kpiRow['interview_count'] ?? 0);
    $soaCount = (int) ($kpiRow['soa_count'] ?? 0);
    $payoutCount = (int) ($kpiRow['payout_count'] ?? 0);
    $closedCount = (int) ($kpiRow['closed_count'] ?? 0);
    $readyForPayoutCount = (int) ($kpiRow['ready_for_payout_count'] ?? 0);
    $scheduledPayoutCount = (int) ($kpiRow['scheduled_payout_count'] ?? 0);
    $readyForInterviewCount = (int) ($kpiRow['ready_for_interview_count'] ?? 0);
    $scheduledInterviewCount = (int) ($kpiRow['scheduled_interview_count'] ?? 0);
    $returnedCount = (int) ($kpiRow['returned_count'] ?? 0);

    $recentApplicationsQuery = $db->prepare("
        SELECT
            a.id,
            a.status,
            a.created_at,
            p.first_name,
            p.last_name,
            p.school_name
        FROM applications a
        JOIN student_profiles p ON a.student_id = p.id
        WHERE a.school_year = :school_year
          AND a.semester = :semester
        ORDER BY a.updated_at DESC, a.id DESC
        LIMIT 5
    ");
    $recentApplicationsQuery->execute($periodParams);
    $recentApplications = $recentApplicationsQuery->fetchAll();
} else {
    $totalApplications = 0;
    $forReviewCount = 0;
    $interviewCount = 0;
    $soaCount = 0;
    $payoutCount = 0;
    $closedCount = 0;
    $readyForInterviewCount = 0;
    $scheduledInterviewCount = 0;
    $readyForPayoutCount = 0;
    $scheduledPayoutCount = 0;
    $returnedCount = 0;
    $recentApplications = [];
}
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">

        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">

            <div class="app-page-header">
                <div>
                    <h1 class="app-page-title"><i class="fa-solid fa-chart-pie me-2"></i>Admin Dashboard</h1>
                </div>
                <div class="app-page-header-actions">
                    <a href="<?php echo htmlspecialchars(base_url('admin/settings')); ?>" class="btn btn-outline-primary fw-bold shadow-sm">
                        <i class="fa-solid fa-calendar-days me-2"></i>Period
                    </a>
                    <a href="<?php echo htmlspecialchars(base_url('staff/applications')); ?>" class="btn btn-outline-primary fw-bold shadow-sm">
                        <i class="fa-solid fa-table-list me-2"></i>Applications
                    </a>
                </div>
            </div>

            <div class="alert <?php echo $isCurrentlyOpen ? 'alert-success' : 'alert-secondary'; ?> border-0 shadow-sm mb-4">
                <div class="fw-bold">Current period: <?php echo htmlspecialchars($activePeriodLabel); ?></div>
                <?php if ($hasConfiguredApplicationPeriod): ?><div class="small mb-0"><?php echo htmlspecialchars($periodStatusLabel); ?></div><?php endif; ?>
            </div>

            <div class="app-kpi-line mb-4">
                <div class="app-kpi-line-item">
                    <a href="<?php echo htmlspecialchars(base_url('staff/applications') . '?queue=under_review'); ?>" class="app-stat-card text-decoration-none d-block" style="--stat-accent: #0d6efd;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Total</div>
                                <h3 class="app-stat-value"><?php echo $totalApplications; ?></h3>
                                <div class="app-stat-note">All applications this period</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-layer-group"></i></span>
                        </div>
                    </a>
                </div>
                <div class="app-kpi-line-item">
                    <a href="<?php echo htmlspecialchars(base_url('staff/applications') . '?queue=under_review'); ?>" class="app-stat-card text-decoration-none d-block" style="--stat-accent: #f39c12;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Review</div>
                                <h3 class="app-stat-value"><?php echo $forReviewCount; ?></h3>
                                <div class="app-stat-note"><?php echo $returnedCount; ?> returned or resubmitting</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-file-circle-check"></i></span>
                        </div>
                    </a>
                </div>
                <div class="app-kpi-line-item">
                    <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview')); ?>" class="app-stat-card text-decoration-none d-block" style="--stat-accent: #c58c00;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Interview</div>
                                <h3 class="app-stat-value"><?php echo $interviewCount; ?></h3>
                                <div class="app-stat-note"><?php echo $readyForInterviewCount; ?> ready, <?php echo $scheduledInterviewCount; ?> scheduled</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-users-viewfinder"></i></span>
                        </div>
                    </a>
                </div>
                <div class="app-kpi-line-item">
                    <a href="<?php echo htmlspecialchars(base_url('staff/applications') . '?queue=for_soa'); ?>" class="app-stat-card text-decoration-none d-block" style="--stat-accent: #0f87a3;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">SOA</div>
                                <h3 class="app-stat-value"><?php echo $soaCount; ?></h3>
                                <div class="app-stat-note">Awaiting or checking SOA</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                        </div>
                    </a>
                </div>
                <div class="app-kpi-line-item">
                    <a href="<?php echo htmlspecialchars(base_url('admin/final-approval')); ?>" class="app-stat-card text-decoration-none d-block" style="--stat-accent: #1f6f43;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Payout</div>
                                <h3 class="app-stat-value"><?php echo $payoutCount; ?></h3>
                                <div class="app-stat-note"><?php echo $readyForPayoutCount; ?> ready, <?php echo $scheduledPayoutCount; ?> scheduled</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                        </div>
                    </a>
                </div>
                <div class="app-kpi-line-item">
                    <a href="<?php echo htmlspecialchars(base_url('staff/archive?lifecycle=Completed')); ?>" class="app-stat-card text-decoration-none d-block" style="--stat-accent: #5b6776;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Closed</div>
                                <h3 class="app-stat-value"><?php echo $closedCount; ?></h3>
                                <div class="app-stat-note">Finished, not eligible, or forfeited</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-flag-checkered"></i></span>
                        </div>
                    </a>
                </div>
            </div>

            <div class="app-surface">
                <div class="app-surface-header">
                    <div>
                        <h6 class="app-surface-title">Latest Activity</h6>
                    </div>
                </div>
                <div class="p-0">
                    <?php if ($recentApplications === []): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                            <h3 class="app-empty-state-title">No recent applications</h3>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Applicant</th>
                                        <th>School</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th class="text-end">Open</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentApplications as $application): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) (($application['last_name'] ?? '') . ', ' . ($application['first_name'] ?? ''))); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($application['school_name'] ?? '')); ?></td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', (string) ($application['status'] ?? ''))); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string) ($application['created_at'] ?? 'now')))); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo htmlspecialchars(base_url('admin/print-form?id=' . (int) ($application['id'] ?? 0))); ?>" class="btn btn-sm btn-outline-secondary me-2">
                                                    PDF
                                                </a>
                                                <a href="<?php echo htmlspecialchars(base_url('staff/verify-documents/' . (int) ($application['id'] ?? 0))); ?>" class="btn btn-sm btn-outline-primary">
                                                    Check
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
