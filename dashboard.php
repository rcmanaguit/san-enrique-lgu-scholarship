<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login('login.php');
require_role(['applicant'], 'index.php');

$pageTitle = 'Applicant Dashboard';
$user = current_user();
$latestApplication = null;
$applicationCount = 0;
$approvedCount = 0;
$openPeriod = null;
$hasApplicationThisPeriod = false;
$canApply = false;

if (db_ready()) {
    $openPeriod = current_open_application_period($conn);
    if ($openPeriod) {
        $hasApplicationThisPeriod = applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod);
    }
    $canApply = $openPeriod !== null && !$hasApplicationThisPeriod;

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM applications WHERE user_id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $applicationCount = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM applications
         WHERE user_id = ?
           AND status IN ('approved', 'for_soa_submission', 'soa_submitted')"
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $approvedCount = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT id, scholarship_type, semester, school_year, status, submitted_at, updated_at
         FROM applications
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $latestApplication = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0">Welcome, <?= e($user['first_name']) ?></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="notifications.php" class="btn btn-outline-primary btn-sm"><i class="fa-regular fa-bell me-1"></i>Notifications</a>
        <a href="profile-settings.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-user-gear me-1"></i>Profile / Settings</a>
        <?php if ($canApply): ?>
            <a href="apply.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Submit New Application</a>
        <?php elseif ($openPeriod && $hasApplicationThisPeriod): ?>
            <button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock me-1"></i>Already Applied This Period</button>
        <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock me-1"></i>Application Period Closed</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!db_ready()): ?>
    <div class="alert alert-warning">The system is not ready yet. Please contact the administrator.</div>
<?php elseif ($openPeriod): ?>
    <div class="alert alert-info small">
        <strong>Open Application Period:</strong> <?= e(format_application_period($openPeriod)) ?>
    </div>
    <?php if ($hasApplicationThisPeriod): ?>
        <div class="alert alert-secondary small">
            You already submitted an application in this period. New application entry is locked until the next open period.
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-warning small">
        Applications are currently closed. Please wait for the next open application period.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card card-soft metric-card">
            <div class="card-body">
                <p class="small text-muted mb-1">Total Applications</p>
                <h3><?= $applicationCount ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-soft metric-card">
            <div class="card-body">
                <p class="small text-muted mb-1">Approved</p>
                <h3><?= $approvedCount ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card card-soft h-100">
            <div class="card-body">
                <p class="small text-muted mb-1">Current Status</p>
                <?php if ($latestApplication): ?>
                    <span class="badge <?= status_badge_class((string) $latestApplication['status']) ?>">
                        <?= e(strtoupper((string) $latestApplication['status'])) ?>
                    </span>
                    <p class="small text-muted mt-2 mb-0">
                        Last update:
                        <?= date('F d, Y h:i A', strtotime((string) $latestApplication['updated_at'])) ?>
                    </p>
                <?php else: ?>
                    <p class="mb-0 text-muted">No application yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($latestApplication): ?>
    <div class="card card-soft">
        <div class="card-body">
            <h2 class="h5">Latest Application Timeline</h2>
            <div class="status-timeline mt-3">
                <div class="status-point">
                    <strong>Submitted</strong>
                    <div class="small text-muted"><?= date('F d, Y h:i A', strtotime((string) ($latestApplication['submitted_at'] ?: $latestApplication['updated_at']))) ?></div>
                </div>
                <div class="status-point">
                    <strong>Current Stage</strong>
                    <div class="small text-muted"><?= e(ucwords(str_replace('_', ' ', (string) $latestApplication['status']))) ?></div>
                </div>
            </div>
            <a href="my-application.php" class="btn btn-outline-primary btn-sm mt-2"><i class="fa-solid fa-folder-open me-1"></i>View Full Records</a>
        </div>
    </div>
<?php else: ?>
    <div class="card card-soft">
        <div class="card-body">
            <h2 class="h5">Start your application</h2>
            <p class="text-muted mb-3">Complete the form and upload requirements to begin screening.</p>
            <?php if ($canApply): ?>
                <a href="apply.php" class="btn btn-primary">Apply Now</a>
            <?php elseif ($openPeriod && $hasApplicationThisPeriod): ?>
                <button class="btn btn-secondary" disabled>Already Applied This Period</button>
            <?php else: ?>
                <button class="btn btn-secondary" disabled>Application Period Closed</button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
