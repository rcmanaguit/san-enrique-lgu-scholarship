<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('login.php');
require_role(['applicant'], 'index.php');

$pageTitle = 'Applicant Dashboard';
$user = current_user();
$latestApplication = null;
$applicationCount = 0;
$releasedCount = 0;
$openPeriod = null;
$hasApplicationThisPeriod = false;
$canApply = false;
$bodyClass = 'applicant-dashboard-page';

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
           AND status = 'released'"
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $releasedCount = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT id, semester, school_year, status, submitted_at, updated_at, review_notes,
                interview_date, interview_location, soa_submission_deadline, soa_submitted_at
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
<?php
$pageHeaderEyebrow = 'Applicant Portal';
$pageHeaderTitle = 'Welcome, ' . e((string) ($user['first_name'] ?? 'Applicant'));
$pageHeaderDescription = 'Use this page only to start a new application or jump into your tracker. All status, next steps, and record details live in My Application.';
$pageHeaderPrimaryAction = $canApply
    ? '<a href="apply.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Start Application</a>'
    : ($openPeriod && $hasApplicationThisPeriod
        ? '<button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock me-1"></i>Already Applied This Period</button>'
        : '<button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock me-1"></i>Application Period Closed</button>');
$pageHeaderActions = '<a href="my-application.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-folder-open me-1"></i>Open My Application</a>';
$pageHeaderSecondaryInfo = $openPeriod
    ? 'Open period: <strong>' . e(format_application_period($openPeriod)) . '</strong>'
    : 'No open application period right now.';
include __DIR__ . '/includes/partials/page-shell-header.php';
?>

<?php if (!db_ready()): ?>
    <div class="alert alert-warning">The system is not ready yet. Please contact the administrator.</div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <?php if ($latestApplication): ?>
            <?php $latestStatus = (string) ($latestApplication['status'] ?? ''); ?>
            <div class="col-12 col-lg-8">
                <div class="card card-soft page-shell-section h-100">
                    <div class="card-body">
                        <div class="action-banner">
                            <div>
                                <div class="small text-muted text-uppercase mb-1">Current focus</div>
                                <div class="action-banner__title"><?= e((string) (application_next_action_summary($latestApplication, 'applicant')['title'] ?? 'Open your application tracker.')) ?></div>
                                <div class="action-banner__detail mt-1">Continue in <strong>My Application</strong> to see your status, schedule, notes, and history in one page.</div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="my-application.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-folder-open me-1"></i>Open Tracker</a>
                                <a href="my-qr.php?id=<?= (int) ($latestApplication['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-qrcode me-1"></i>My QR</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-12 <?= $latestApplication ? 'col-lg-4' : 'col-lg-12' ?>">
            <div class="card card-soft page-shell-section h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Quick summary</h2>
                    <div class="compact-kpi-grid">
                        <div class="compact-kpi-card">
                            <small>Total applications</small>
                            <strong><?= (int) $applicationCount ?></strong>
                        </div>
                        <div class="compact-kpi-card">
                            <small>Released</small>
                            <strong><?= (int) $releasedCount ?></strong>
                        </div>
                        <div class="compact-kpi-card">
                            <small>Current stage</small>
                            <strong><?= e($latestApplication ? application_status_label((string) ($latestApplication['status'] ?? '')) : 'No application') ?></strong>
                        </div>
                    </div>
                    <p class="page-shell-note mt-3">This dashboard is now only a shortcut page. Detailed workflow updates stay in My Application.</p>
                    <?php if (!$latestApplication): ?>
                        <div class="mt-3">
                            <?php if ($canApply): ?>
                                <a href="apply.php" class="btn btn-primary btn-sm">Apply Now</a>
                            <?php else: ?>
                                <a href="my-application.php" class="btn btn-outline-primary btn-sm">Open My Application</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($openPeriod && $hasApplicationThisPeriod): ?>
        <div class="alert alert-secondary small">
            You already submitted an application in this period. New application entry is locked until the next open period.
        </div>
    <?php elseif (!$openPeriod): ?>
        <div class="alert alert-warning small">
            Applications are currently closed. Please wait for the next open application period.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
