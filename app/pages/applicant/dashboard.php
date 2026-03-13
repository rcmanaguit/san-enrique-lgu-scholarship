<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

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
$resumeUrl = '';
$hasDraftToResume = false;
$bodyClass = 'applicant-dashboard-page';

if (db_ready()) {
    $openPeriod = current_open_application_period($conn);
    if ($openPeriod) {
        $hasApplicationThisPeriod = applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod);
    }
    $canApply = $openPeriod !== null && !$hasApplicationThisPeriod;

    $draft = wizard_load_persistent_draft($conn, (int) ($user['id'] ?? 0));
    if (is_array($draft['state'] ?? null) && wizard_has_progress((array) ($draft['state'] ?? [])) && $canApply) {
        $resumeStep = (int) ($draft['current_step'] ?? 0);
        if ($resumeStep < 1 || $resumeStep > 6) {
            $resumeStep = wizard_resume_step((array) ($draft['state'] ?? []));
        }
        if ($resumeStep >= 1 && $resumeStep <= 6) {
            $hasDraftToResume = true;
            $resumeUrl = 'apply.php?step=' . $resumeStep;
        }
    }

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

include __DIR__ . '/../../includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Applicant Portal';
$pageHeaderTitle = 'Welcome, ' . e((string) ($user['first_name'] ?? 'Applicant'));
$pageHeaderDescription = 'Use this page to start, continue, or open your application.';
$pageHeaderPrimaryAction = $hasDraftToResume
    ? '<a href="' . e($resumeUrl) . '" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen-to-square me-1"></i>Continue Application</a>'
    : ($canApply
    ? '<a href="apply.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Start Application</a>'
    : ($openPeriod && $hasApplicationThisPeriod
        ? '<a href="my-application.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-folder-open me-1"></i>View My Application</a>'
        : '<a href="my-application.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-folder-open me-1"></i>View My Application</a>'));
$pageHeaderActions = '';
$submissionDeadlineText = '';
if (!empty($openPeriod['end_date'])) {
    $submissionDeadlineText = ' Submission closes on <strong>' . e(date('M d, Y', strtotime((string) $openPeriod['end_date']))) . '</strong>.';
}
$pageHeaderSecondaryInfo = $openPeriod
    ? 'Current period: <strong>' . e(format_application_period($openPeriod)) . '</strong>.' . $submissionDeadlineText
    : 'No open application period right now.';
include __DIR__ . '/../../includes/partials/page-shell-header.php';
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
                                <div class="small text-muted text-uppercase mb-1">What to do next</div>
                                <div class="action-banner__title"><?= e((string) (application_next_action_summary($latestApplication, 'applicant')['title'] ?? 'Open your application tracker.')) ?></div>
                                <div class="action-banner__detail mt-1">Open <strong>My Application</strong> to check your latest status, notes, and schedule.</div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="my-application.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-folder-open me-1"></i>Open Tracker</a>
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
                    <p class="page-shell-note mt-3">Use <strong>My Application</strong> for full details and record history.</p>
                    <?php if (!$latestApplication): ?>
                        <div class="mt-3">
                            <?php if ($hasDraftToResume): ?>
                                <a href="<?= e($resumeUrl) ?>" class="btn btn-primary btn-sm">Continue Application</a>
                            <?php elseif ($canApply): ?>
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

    <?php if ($hasDraftToResume): ?>
        <div class="alert alert-info small">
            You have an unfinished application draft for the current period. Continue it from this page.
        </div>
    <?php elseif ($openPeriod && $hasApplicationThisPeriod): ?>
        <div class="alert alert-secondary small">
            You already submitted an application in this period. New application entry is locked until the next open period.
        </div>
    <?php elseif (!$openPeriod): ?>
        <div class="alert alert-warning small">
            Applications are currently closed. Please wait for the next open application period.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

