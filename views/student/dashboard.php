<?php
require __DIR__ . '/../layouts/header.php';

use App\Config\Database;
use App\Models\ApplicationPeriod;
use App\Models\Announcement;

// Ensure the user is logged in as a student
$userId = $_SESSION['user_id'] ?? null;
if (!$userId || $_SESSION['role'] !== 'Student') {
    header('Location: ' . base_url('login'));
    exit;
}

$db = Database::connect();

// ------------------------------------------------------------------
// LIVE DATABASE QUERIES
// ------------------------------------------------------------------

// 1. Fetch the Student's Profile Data
$stmtProfile = $db->prepare("SELECT first_name FROM student_profiles WHERE user_id = :uid LIMIT 1");
$stmtProfile->execute(['uid' => $userId]);
$profile = $stmtProfile->fetch();
$studentNameValue = trim((string) ($profile['first_name'] ?? ''));
if ($studentNameValue === '') {
    $studentNameValue = trim((string) ($_SESSION['first_name'] ?? ''));
}
$studentName = $studentNameValue !== '' ? htmlspecialchars($studentNameValue) : "Scholar";

// 2. Calculate Total Grants Received
$stmtGrants = $db->prepare("
    SELECT COUNT(*) 
    FROM applications a 
    JOIN student_profiles p ON a.student_id = p.id 
    WHERE p.user_id = :uid AND a.status IN ('Approved_Pending_Payroll', 'Approved_Finished')
");
$stmtGrants->execute(['uid' => $userId]);
$totalGrants = $stmtGrants->fetchColumn();

// 3. Define the Active LGU Period
$applicationSettings = ApplicationPeriod::getCurrent();
$currentSchoolYear = (string) ($applicationSettings['school_year'] ?? '');
$currentSemester = (string) ($applicationSettings['semester'] ?? '');
$activePeriod = ApplicationPeriod::activePeriodLabel($applicationSettings);
$applicationWindowOpen = ApplicationPeriod::isAcceptingApplications($applicationSettings);
$hasConfiguredApplicationPeriod = $currentSchoolYear !== '' && $currentSemester !== '';
$studentAnnouncements = Announcement::latestForAudience('Students', 3);
$soaDeadlinePolicyLabel = ApplicationPeriod::soaDeadlinePolicyLabel($applicationSettings);

// 4. Fetch the Status of their CURRENT semester's application
$stmtStatus = $db->prepare("
    SELECT
        a.id,
        a.status,
        a.interview_result,
        a.interview_result_at,
        a.soa_deadline,
        a.created_at,
        a.updated_at,
        ib.scheduled_date AS interview_schedule,
        ib.venue AS interview_venue,
        pb.scheduled_date AS payout_schedule,
        pb.venue AS payout_venue
    FROM applications a 
    JOIN student_profiles p ON a.student_id = p.id 
    LEFT JOIN batches ib ON a.interview_batch_id = ib.id
    LEFT JOIN batches pb ON a.payout_batch_id = pb.id
    WHERE p.user_id = :uid AND a.school_year = :sy AND a.semester = :sem 
    ORDER BY a.created_at DESC LIMIT 1
");
$stmtStatus->execute([
    'uid' => $userId,
    'sy' => $currentSchoolYear,
    'sem' => $currentSemester
]);
$currentApp = $stmtStatus->fetch();
$currentStatus = $currentApp ? $currentApp['status'] : 'None';
$soaDeadline = $currentApp ? ApplicationPeriod::resolveApplicationSoaDeadline($applicationSettings, (array) $currentApp) : '';
$soaDeadlineOpen = $currentApp ? ApplicationPeriod::isApplicationSoaDeadlineOpen($applicationSettings, (array) $currentApp) : true;
$rejectedDocuments = [];
$applicationTimelines = [];
$applicationHistoryForTimeline = [];

if (!$hasConfiguredApplicationPeriod) {
    $currentStatus = 'None';
}

if ($currentApp && $currentStatus === 'Pending_Resubmission') {
    $stmtRejectedDocs = $db->prepare("
        SELECT document_type, rejection_remarks, updated_at
        FROM documents
        WHERE application_id = :application_id AND status = 'Rejected'
        ORDER BY FIELD(document_type, 'Grades', 'Barangay Residency', 'Residency', 'SOA'), id DESC
    ");
    $stmtRejectedDocs->execute(['application_id' => (int) ($currentApp['id'] ?? 0)]);
    $rejectedDocuments = $stmtRejectedDocs->fetchAll();
}

if ($userId) {
    $stmtTimelineApplications = $db->prepare("
        SELECT
            a.id,
            a.status,
            a.application_type,
            a.interview_result,
            a.created_at,
            a.updated_at,
            a.school_year,
            a.semester,
            ib.scheduled_date AS interview_schedule,
            ib.venue AS interview_venue,
            pb.scheduled_date AS payout_schedule,
            pb.venue AS payout_venue
        FROM applications a
        JOIN student_profiles p ON a.student_id = p.id
        LEFT JOIN batches ib ON a.interview_batch_id = ib.id
        LEFT JOIN batches pb ON a.payout_batch_id = pb.id
        WHERE p.user_id = :user_id
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $stmtTimelineApplications->execute(['user_id' => $userId]);
    $applicationHistoryForTimeline = $stmtTimelineApplications->fetchAll();

    foreach ($applicationHistoryForTimeline as $timelineApplication) {
        $applicationId = (int) ($timelineApplication['id'] ?? 0);
        if ($applicationId <= 0) {
            continue;
        }

        $applicationTimelines[$applicationId] = [
            'application' => $timelineApplication,
            'entries' => [],
        ];
    }

    foreach ($applicationHistoryForTimeline as $timelineApplication) {
        $applicationId = (int) ($timelineApplication['id'] ?? 0);
        if ($applicationId <= 0 || !isset($applicationTimelines[$applicationId])) {
            continue;
        }

        $termLabel = trim((string) (($timelineApplication['school_year'] ?? '') . ' | ' . ($timelineApplication['semester'] ?? '')));
        $applicationLabel = 'App ID ' . (int) ($timelineApplication['id'] ?? 0);

        $applicationTimelines[$applicationId]['entries'][] = [
            'time' => (string) ($timelineApplication['created_at'] ?? ''),
            'icon' => 'fa-file-circle-plus',
            'badge_class' => 'text-bg-primary',
            'title' => 'Application submitted',
            'details' => trim($applicationLabel . ' | ' . $termLabel . ' | ' . ((string) ($timelineApplication['application_type'] ?? 'New')) . ' application'),
        ];

        if (!empty($timelineApplication['interview_schedule'])) {
            $applicationTimelines[$applicationId]['entries'][] = [
                'time' => (string) $timelineApplication['interview_schedule'],
                'icon' => 'fa-calendar-check',
                'badge_class' => 'text-bg-info',
                'title' => 'Interview scheduled',
                'details' => trim($applicationLabel . ' | ' . $termLabel . ' | Venue: ' . (string) ($timelineApplication['interview_venue'] ?? 'Municipal Hall')),
            ];
        }

        if (!empty($timelineApplication['interview_result'])) {
            $result = (string) $timelineApplication['interview_result'];
            $applicationTimelines[$applicationId]['entries'][] = [
                'time' => (string) ($timelineApplication['updated_at'] ?? ''),
                'icon' => $result === 'Passed' ? 'fa-circle-check' : 'fa-circle-xmark',
                'badge_class' => $result === 'Passed' ? 'text-bg-success' : 'text-bg-danger',
                'title' => 'Interview result recorded',
                'details' => trim($applicationLabel . ' | ' . $termLabel . ' | Result: ' . $result),
            ];
        }

        if (!empty($timelineApplication['payout_schedule'])) {
            $applicationTimelines[$applicationId]['entries'][] = [
                'time' => (string) $timelineApplication['payout_schedule'],
                'icon' => 'fa-money-check-dollar',
                'badge_class' => 'text-bg-success',
                'title' => 'Payout scheduled',
                'details' => trim($applicationLabel . ' | ' . $termLabel . ' | Venue: ' . (string) ($timelineApplication['payout_venue'] ?? 'Municipal Hall')),
            ];
        }
    }

    $stmtTimelineDocs = $db->prepare("
        SELECT
            d.document_type,
            d.status,
            d.rejection_remarks,
            d.uploaded_at,
            d.updated_at,
            a.id AS application_id,
            a.school_year,
            a.semester
        FROM documents d
        JOIN applications a ON d.application_id = a.id
        JOIN student_profiles p ON a.student_id = p.id
        WHERE p.user_id = :user_id
        ORDER BY d.updated_at DESC, d.id DESC
    ");
    $stmtTimelineDocs->execute(['user_id' => $userId]);
    foreach ($stmtTimelineDocs->fetchAll() as $document) {
        $applicationId = (int) ($document['application_id'] ?? 0);
        if ($applicationId <= 0 || !isset($applicationTimelines[$applicationId])) {
            continue;
        }

        $documentType = (string) ($document['document_type'] ?? 'Document');
        $status = (string) ($document['status'] ?? 'Pending');
        $eventTime = (string) ($document['updated_at'] ?? $document['uploaded_at'] ?? '');
        $termLabel = trim((string) (($document['school_year'] ?? '') . ' | ' . ($document['semester'] ?? '')));
        $details = $status === 'Rejected'
            ? trim('App ID ' . (int) ($document['application_id'] ?? 0) . ' | ' . $termLabel . ' | Staff remarks: ' . (string) ($document['rejection_remarks'] ?? 'Please review the remarks and upload a corrected file.'))
            : ($status === 'Verified'
                ? trim('App ID ' . (int) ($document['application_id'] ?? 0) . ' | ' . $termLabel . ' | This document was reviewed and verified.')
                : trim('App ID ' . (int) ($document['application_id'] ?? 0) . ' | ' . $termLabel . ' | This document is currently in the review queue.'));

        $applicationTimelines[$applicationId]['entries'][] = [
            'time' => $eventTime,
            'icon' => $status === 'Rejected' ? 'fa-file-circle-xmark' : ($status === 'Verified' ? 'fa-file-circle-check' : 'fa-file-arrow-up'),
            'badge_class' => $status === 'Rejected' ? 'text-bg-danger' : ($status === 'Verified' ? 'text-bg-success' : 'text-bg-secondary'),
            'title' => $documentType . ' ' . strtolower($status === 'Pending' ? 'submitted or updated' : $status),
            'details' => $details,
        ];
    }

    foreach ($applicationTimelines as &$timelineGroup) {
        $timelineGroup['entries'] = array_values(array_filter(
            $timelineGroup['entries'],
            static fn(array $entry): bool => trim((string) ($entry['time'] ?? '')) !== ''
        ));

        usort($timelineGroup['entries'], static function (array $left, array $right): int {
            return strtotime((string) ($right['time'] ?? '')) <=> strtotime((string) ($left['time'] ?? ''));
        });
    }
    unset($timelineGroup);
}

// ------------------------------------------------------------------
// CURRENT APPLICATION CARD LOGIC
// ------------------------------------------------------------------
$formatDashboardDate = static function (?string $value): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($normalized))->format('F j, Y g:i A');
    } catch (Throwable $exception) {
        return $normalized;
    }
};

$currentStatusLabel = str_replace('_', ' ', (string) $currentStatus);
$currentApplicationNumber = '';
if ($currentApp) {
    $currentApplicationNumber = 'SELGU-APP-' . date('Y', strtotime((string) ($currentApp['created_at'] ?? 'now'))) . '-' . str_pad((string) ($currentApp['id'] ?? 0), 5, '0', STR_PAD_LEFT);
}
$currentStatusBadgeClass = match ($currentStatus) {
    'Submitted', 'Initial_Review' => 'app-status-badge app-status-review',
    'Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue' => 'app-status-badge app-status-correction',
    'For_Interview' => 'app-status-badge app-status-interview',
    'Eligible_Awaiting_SOA', 'SOA_Under_Review' => 'app-status-badge app-status-soa',
    'Approved_Pending_Payroll' => 'app-status-badge app-status-payout',
    'Approved_Finished' => 'app-status-badge app-status-complete',
    'Not_Eligible', 'Forfeited' => 'app-status-badge app-status-closed',
    default => 'app-status-badge app-status-default',
};

$currentApplicationAction = [
    'label' => '',
    'href' => '',
    'class' => 'btn btn-primary fw-bold',
];
$currentApplicationHeadline = 'Your current scholarship application is in progress.';
$currentApplicationDetail = 'Check the latest status below and follow the next required step, if any.';

switch ($currentStatus) {
    case 'Submitted':
    case 'Initial_Review':
        $currentApplicationHeadline = 'Your application is under document review.';
        $currentApplicationDetail = 'LGU staff is checking your submitted requirements.';
        break;

    case 'Pending_Resubmission':
        $currentApplicationHeadline = 'Document correction required.';
        $currentApplicationDetail = 'One or more uploaded documents were rejected and must be replaced.';
        $currentApplicationAction = [
            'label' => 'Fix Documents',
            'href' => base_url('student/apply'),
            'class' => 'btn btn-danger fw-bold',
        ];
        break;

    case 'For_Interview':
        $currentApplicationHeadline = 'Your application is waiting for interview.';
        $currentApplicationDetail = 'Check your SMS and the schedule details below for your interview notice.';
        break;

    case 'Eligible_Awaiting_SOA':
        $currentApplicationHeadline = 'You passed the interview and must upload your SOA.';
        $currentApplicationDetail = $soaDeadline !== ''
            ? 'Submit your Statement of Account on or before ' . $soaDeadline . '.'
            : 'Submit your Statement of Account to continue to the payout stage.';
        $currentApplicationAction = [
            'label' => 'Upload SOA',
            'href' => base_url('student/apply'),
            'class' => 'btn btn-warning fw-bold' . (($soaDeadline !== '' && !$soaDeadlineOpen) ? ' disabled' : ''),
        ];
        break;

    case 'SOA_Under_Review':
        $currentApplicationHeadline = 'Your SOA is under review.';
        $currentApplicationDetail = 'Wait for staff review and watch for follow-up notifications.';
        break;

    case 'SOA_Resubmission_Required':
        $currentApplicationHeadline = 'SOA correction required.';
        $currentApplicationDetail = $soaDeadline !== ''
            ? 'Your SOA was rejected. Upload a corrected copy before ' . $soaDeadline . '.'
            : 'Your SOA was rejected. Upload a corrected copy to continue.';
        $currentApplicationAction = [
            'label' => 'Fix SOA',
            'href' => base_url('student/apply'),
            'class' => 'btn btn-danger fw-bold' . (($soaDeadline !== '' && !$soaDeadlineOpen) ? ' disabled' : ''),
        ];
        break;

    case 'SOA_Overdue':
        $currentApplicationHeadline = 'SOA deadline passed.';
        $currentApplicationDetail = 'Please contact the LGU office for further instructions.';
        break;

    case 'Approved_Pending_Payroll':
        $currentApplicationHeadline = 'Your application is approved for payout scheduling.';
        $currentApplicationDetail = 'Wait for the official payout announcement and keep your registered number active.';
        break;

    case 'Approved_Finished':
        $currentApplicationHeadline = 'Your current application has been completed.';
        $currentApplicationDetail = 'This term is already finished. You can still review the saved record below.';
        break;

    case 'Not_Eligible':
        $currentApplicationHeadline = 'Your current application was marked not eligible.';
        $currentApplicationDetail = (($currentApp['interview_result'] ?? null) === 'Absent')
            ? 'The recorded interview result is absent.'
            : 'The recorded interview result is failed.';
        break;
}
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">

        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">

            <div class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Applicant Portal</span>
                    <h1 class="app-page-title">Welcome back, <?php echo $studentName; ?>!</h1>
                </div>
                <div class="app-page-header-actions">
                    <div class="badge bg-primary fs-6 p-2 shadow-sm">
                        <i class="fa-solid fa-calendar-check me-1"></i> Active Period:
                        <?php echo $activePeriod; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="app-surface mb-4">
                        <div class="app-surface-header">
                            <div>
                                <h5 class="app-surface-title"><i class="fa-solid fa-id-card me-2 text-primary"></i>
                                    Current Application</h5>
                            </div>
                        </div>
                        <div class="app-surface-body">

                            <?php if ($currentStatus === 'None'): ?>
                                <div class="app-empty-state">
                                    <div class="app-empty-state-icon"><i class="fa-solid fa-folder-open"></i></div>
                                    <?php if (!$hasConfiguredApplicationPeriod): ?>
                                        <h5 class="app-empty-state-title">No Application Period Yet</h5>
                                        <p class="app-empty-state-copy">The LGU has not configured the scholarship application period yet. Please wait for the official announcement.</p>
                                        <button type="button" class="btn btn-secondary fw-bold" disabled>Unavailable</button>
                                    <?php elseif (!$applicationWindowOpen): ?>
                                        <h5 class="app-empty-state-title">Applications Are Closed</h5>
                                        <p class="app-empty-state-copy">There is currently no open application window for <?php echo htmlspecialchars($activePeriod); ?>.</p>
                                        <button type="button" class="btn btn-secondary fw-bold" disabled>Applications Closed</button>
                                    <?php else: ?>
                                        <h5 class="app-empty-state-title">No Active Application</h5>
                                        <p class="app-empty-state-copy">You have not submitted an application for the current semester.</p>
                                        <a href="<?php echo htmlspecialchars(base_url('student/apply')); ?>" class="btn btn-primary fw-bold">Apply Now</a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <section class="application-section-card">
                                    <div class="application-section-head justify-content-between flex-wrap">
                                        <div>
                                            <h6 class="application-section-title mb-1"><?php echo htmlspecialchars($currentApplicationNumber !== '' ? $currentApplicationNumber : 'Application'); ?></h6>
                                            <p class="application-section-copy mb-0"><?php echo htmlspecialchars($activePeriod); ?></p>
                                        </div>
                                        <span class="<?php echo htmlspecialchars($currentStatusBadgeClass); ?>">
                                            <?php echo htmlspecialchars($currentStatusLabel); ?>
                                        </span>
                                    </div>

                                    <h4 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($currentApplicationHeadline); ?></h4>
                                    <p class="text-muted mb-4"><?php echo htmlspecialchars($currentApplicationDetail); ?></p>

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <div class="application-review-card h-100">
                                                <div class="small text-uppercase text-muted fw-bold mb-1">Submitted On</div>
                                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($formatDashboardDate((string) ($currentApp['created_at'] ?? ''))); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="application-review-card h-100">
                                                <div class="small text-uppercase text-muted fw-bold mb-1">Last Updated</div>
                                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($formatDashboardDate((string) ($currentApp['updated_at'] ?? ''))); ?></div>
                                            </div>
                                        </div>

                                        <?php if (!empty($currentApp['interview_schedule'])): ?>
                                            <div class="col-md-6">
                                                <div class="application-review-card h-100">
                                                    <div class="small text-uppercase text-muted fw-bold mb-1">Interview Schedule</div>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($formatDashboardDate((string) $currentApp['interview_schedule'])); ?></div>
                                                    <div class="small text-muted mt-1">Venue: <?php echo htmlspecialchars((string) ($currentApp['interview_venue'] ?? 'Municipal Hall')); ?></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($currentApp['payout_schedule'])): ?>
                                            <div class="col-md-6">
                                                <div class="application-review-card h-100">
                                                    <div class="small text-uppercase text-muted fw-bold mb-1">Payout Schedule</div>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($formatDashboardDate((string) $currentApp['payout_schedule'])); ?></div>
                                                    <div class="small text-muted mt-1">Venue: <?php echo htmlspecialchars((string) ($currentApp['payout_venue'] ?? 'Municipal Hall')); ?></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($soaDeadline !== ''): ?>
                                            <div class="col-md-6">
                                                <div class="application-review-card h-100">
                                                    <div class="small text-uppercase text-muted fw-bold mb-1">SOA Deadline</div>
                                                    <div class="fw-semibold <?php echo $soaDeadlineOpen ? 'text-dark' : 'text-danger'; ?>">
                                                        <?php echo htmlspecialchars($soaDeadline); ?>
                                                    </div>
                                                    <?php if (!$soaDeadlineOpen): ?>
                                                        <div class="small text-danger mt-1">Deadline passed</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (($currentApp['interview_result'] ?? null) !== null): ?>
                                            <div class="col-md-6">
                                                <div class="application-review-card h-100">
                                                    <div class="small text-uppercase text-muted fw-bold mb-1">Interview Result</div>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars((string) $currentApp['interview_result']); ?></div>
                                                    <?php if (!empty($currentApp['interview_result_at'])): ?>
                                                        <div class="small text-muted mt-1">Recorded <?php echo htmlspecialchars($formatDashboardDate((string) $currentApp['interview_result_at'])); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($rejectedDocuments !== []): ?>
                                        <div class="application-review-card mb-4 border-danger-subtle">
                                            <div class="small text-uppercase text-danger fw-bold mb-2">Rejected Documents</div>
                                            <div class="d-grid gap-3">
                                                <?php foreach ($rejectedDocuments as $rejectedDocument): ?>
                                                    <div>
                                                        <div class="fw-semibold text-dark">
                                                            <?php echo htmlspecialchars(document_type_label((string) ($rejectedDocument['document_type'] ?? 'Document'))); ?>
                                                        </div>
                                                        <div class="small text-muted mb-1">
                                                            Reviewed <?php echo htmlspecialchars($formatDashboardDate((string) ($rejectedDocument['updated_at'] ?? ''))); ?>
                                                        </div>
                                                        <div class="small text-dark">
                                                            <?php echo nl2br(htmlspecialchars((string) ($rejectedDocument['rejection_remarks'] ?? 'Please review the remarks and upload a corrected file.'))); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($currentApplicationAction['label'] !== '' && $currentApplicationAction['href'] !== ''): ?>
                                            <a href="<?php echo htmlspecialchars((string) $currentApplicationAction['href']); ?>" class="<?php echo htmlspecialchars((string) $currentApplicationAction['class']); ?>">
                                                <?php echo htmlspecialchars((string) $currentApplicationAction['label']); ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo htmlspecialchars(base_url('student/application/' . (int) ($currentApp['id'] ?? 0))); ?>" class="btn btn-outline-primary fw-bold">
                                            View Record
                                        </a>
                                    </div>
                                </section>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="app-stat-card mb-4" style="--stat-accent: #0d5b89;">
                        <div class="app-stat-card-body">
                            <div>
                            <div class="app-stat-kicker">Grant History</div>
                            <h2 class="app-stat-value">
                                <?php echo $totalGrants; ?>
                            </h2>
                            <div class="app-stat-note">Total grants received</div>
                            <a href="<?php echo htmlspecialchars(base_url('student/history')); ?>" class="btn btn-sm btn-outline-light mt-3 rounded-pill px-3">View
                                Full History</a>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                        </div>
                    </div>

                    <div class="app-surface">
                        <div class="app-surface-header">
                            <div>
                            <h6 class="app-surface-title"><i class="fa-solid fa-bullhorn me-2 text-warning"></i>
                                Important Reminders</h6>
                            <p class="app-surface-copy">Keep these reminders in mind while your application is active.</p>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <ul class="list-unstyled small mb-0">
                                <li class="mb-3">
                                    <strong class="text-dark">Physical SOA Required</strong><br>
                                    <span class="text-muted">You must bring your physical, original SOA during payout day for verification.</span>
                                </li>
                                <li>
                                    <strong class="text-dark">Keep your Number Active</strong><br>
                                    <span class="text-muted">All interview schedules and payout announcements will be
                                        sent via SMS to your registered number.</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="app-surface mt-4">
                        <div class="app-surface-header">
                            <div>
                            <h6 class="app-surface-title"><i class="fa-solid fa-bullhorn me-2 text-primary"></i>
                                Announcements</h6>
                            <p class="app-surface-copy">Latest updates and reminders from the scholarship office.</p>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <?php if ($studentAnnouncements === []): ?>
                                <p class="text-muted small mb-0">No student announcements yet.</p>
                            <?php else: ?>
                                <?php foreach ($studentAnnouncements as $announcementIndex => $announcement): ?>
                                    <div class="<?php echo $announcementIndex < count($studentAnnouncements) - 1 ? 'mb-3 pb-3 border-bottom' : ''; ?>">
                                        <div class="fw-bold text-dark mb-1"><?php echo htmlspecialchars((string) $announcement['title']); ?></div>
                                        <div class="small text-muted"><?php echo nl2br(htmlspecialchars((string) $announcement['body'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
