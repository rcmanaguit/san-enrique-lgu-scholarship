<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('login.php');
require_role(['applicant'], 'index.php');

$pageTitle = 'My Application Progress';
$user = current_user();
$applications = [];
$openPeriod = null;
$hasApplicationThisPeriod = false;
$canCreateNewApplication = false;
$latestApplication = null;
$statusFlow = [
    'submitted',
    'for_review',
    'for_resubmission',
    'for_interview',
    'approved',
    'for_soa_submission',
    'soa_submitted',
    'waitlisted',
    'disbursed',
];
$statusDisplay = [
    'submitted' => 'Submitted',
    'for_review' => 'For Review',
    'for_resubmission' => 'Resubmission Required',
    'for_interview' => 'For Interview',
    'approved' => 'Approved',
    'for_soa_submission' => 'SOA Required',
    'soa_submitted' => 'SOA Submitted',
    'waitlisted' => 'Waitlisted',
    'disbursed' => 'Completed (Disbursed)',
    'rejected' => 'Rejected',
];
$nextActionByStatus = [
    'submitted' => 'Wait for staff review of your documents.',
    'for_review' => 'Wait for document verification result.',
    'for_resubmission' => 'Resubmit the missing/incorrect documents as soon as possible.',
    'for_interview' => 'Prepare for interview and wait for your interview schedule notice.',
    'approved' => 'Wait for SOA submission notice from LGU staff.',
    'for_soa_submission' => 'Submit your SOA/Student Copy before the deadline.',
    'soa_submitted' => 'Wait for final ranking/next release schedule.',
    'waitlisted' => 'Wait for final payout/slot confirmation from LGU.',
    'disbursed' => 'Your scholarship payout has been released.',
    'rejected' => 'Coordinate with LGU Scholarship Office for guidance on next cycle.',
];

if (db_ready()) {
    $openPeriod = current_open_application_period($conn);
    if ($openPeriod) {
        $hasApplicationThisPeriod = applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod);
    }
    $canCreateNewApplication = $openPeriod !== null && !$hasApplicationThisPeriod;

    $stmt = $conn->prepare(
        "SELECT a.id, a.application_no, a.qr_token, a.school_name, a.school_type, a.semester, a.school_year,
                a.status, a.review_notes, a.soa_submission_deadline, a.soa_submitted_at, a.submitted_at, a.updated_at,
                COUNT(d.id) AS document_count
         FROM applications a
         LEFT JOIN application_documents d ON d.application_id = a.id
         WHERE a.user_id = ?
         GROUP BY a.id
         ORDER BY a.id DESC"
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result instanceof mysqli_result) {
        $applications = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
    $latestApplication = $applications[0] ?? null;
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-folder-open me-2 text-primary"></i>My Application & Status</h1>
    <?php if ($canCreateNewApplication): ?>
        <a href="apply.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i>New Application
        </a>
    <?php elseif ($openPeriod && $hasApplicationThisPeriod): ?>
        <button class="btn btn-secondary btn-sm" disabled>
            <i class="fa-solid fa-lock me-1"></i>Already Applied This Period
        </button>
    <?php else: ?>
        <button class="btn btn-secondary btn-sm" disabled>
            <i class="fa-solid fa-lock me-1"></i>Application Period Closed
        </button>
    <?php endif; ?>
</div>

<?php if (!db_ready()): ?>
    <div class="alert alert-warning">The system is not ready yet. Please contact the administrator.</div>
<?php elseif ($openPeriod && $hasApplicationThisPeriod): ?>
    <div class="alert alert-secondary small">
        You already submitted an application in <?= e((string) ($openPeriod['period_name'] ?? 'the current period')) ?>.
        A new application is allowed only in the next open period.
    </div>
<?php endif; ?>

<?php if ($latestApplication): ?>
    <?php
    $latestStatus = (string) ($latestApplication['status'] ?? '');
    $latestStatusLabel = $statusDisplay[$latestStatus] ?? ucwords(str_replace('_', ' ', $latestStatus));
    $latestNextAction = $nextActionByStatus[$latestStatus] ?? 'Wait for update from LGU Scholarship Office.';
    $latestIndex = array_search($latestStatus, $statusFlow, true);
    $latestIndex = $latestIndex === false ? -1 : (int) $latestIndex;
    ?>
    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h2 class="h6 mb-1">Current Application Progress</h2>
                    <div class="small text-muted">
                        <?= e((string) ($latestApplication['application_no'] ?? '-')) ?> |
                        <?= e((string) ($latestApplication['semester'] ?? '-')) ?> / <?= e((string) ($latestApplication['school_year'] ?? '-')) ?>
                    </div>
                </div>
                <span class="badge <?= status_badge_class($latestStatus) ?>"><?= e(strtoupper($latestStatusLabel)) ?></span>
            </div>
            <div class="small mb-2"><strong>Next Action:</strong> <?= e($latestNextAction) ?></div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($statusFlow as $index => $statusCode): ?>
                    <?php
                    $label = $statusDisplay[$statusCode] ?? ucwords(str_replace('_', ' ', $statusCode));
                    $isDone = $latestIndex >= $index;
                    ?>
                    <span class="badge <?= $isDone ? 'text-bg-primary' : 'text-bg-light' ?>"><?= e($label) ?></span>
                <?php endforeach; ?>
                <?php if ($latestStatus === 'rejected'): ?>
                    <span class="badge text-bg-danger">Rejected</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (db_ready() && !$applications): ?>
    <div class="card card-soft">
        <div class="card-body">
            <p class="text-muted mb-3">No application records yet.</p>
            <?php if ($canCreateNewApplication): ?>
                <a href="apply.php" class="btn btn-primary">Start Application</a>
            <?php else: ?>
                <button class="btn btn-secondary" disabled>Application Period Closed</button>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (db_ready()): ?>
    <div data-live-table class="card card-soft shadow-sm">
        <div class="card-body border-bottom table-controls">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label form-label-sm">Live Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search application no, school, status">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Rows</label>
                    <select data-table-per-page class="form-select form-select-sm">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 text-md-end">
                    <span class="page-legend" data-table-summary></span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>School</th>
                        <th>Status</th>
                        <th>Documents</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $row): ?>
                    <?php
                    $search = strtolower(
                        implode(' ', [
                            $row['application_no'],
                            $row['school_name'],
                            $row['status'],
                            $row['school_year'],
                        ])
                    );
                    ?>
                    <tr data-search="<?= e($search) ?>" data-filter="">
                        <td>
                            <strong><?= e((string) $row['application_no']) ?></strong>
                            <div class="small text-muted">#<?= (int) $row['id'] ?> | <?= e((string) $row['semester']) ?> / <?= e((string) $row['school_year']) ?></div>
                        </td>
                        <td>
                            <div><?= e((string) $row['school_name']) ?></div>
                            <div class="small text-muted"><?= e(strtoupper((string) $row['school_type'])) ?></div>
                        </td>
                        <td>
                            <span class="badge <?= status_badge_class((string) $row['status']) ?>">
                                <?= e(strtoupper((string) $row['status'])) ?>
                            </span>
                            <?php if ((string) $row['status'] === 'for_soa_submission' && !empty($row['soa_submission_deadline'])): ?>
                                <div class="small text-muted mt-1">SOA deadline: <?= date('M d, Y', strtotime((string) $row['soa_submission_deadline'])) ?></div>
                            <?php endif; ?>
                            <?php if ((string) $row['status'] === 'soa_submitted' && !empty($row['soa_submitted_at'])): ?>
                                <div class="small text-muted mt-1">SOA received: <?= date('M d, Y h:i A', strtotime((string) $row['soa_submitted_at'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['review_notes'])): ?>
                                <div class="small text-muted mt-1"><?= e((string) $row['review_notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $row['document_count'] ?></td>
                        <td><?= date('M d, Y h:i A', strtotime((string) $row['updated_at'])) ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="print-application.php?id=<?= (int) $row['id'] ?>" class="btn btn-outline-primary" title="Print Form">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <a href="my-qr.php?id=<?= (int) $row['id'] ?>" class="btn btn-outline-primary" title="View QR">
                                    <i class="fa-solid fa-qrcode"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-body border-top d-flex justify-content-end">
            <div class="d-flex gap-2" data-table-pager></div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
