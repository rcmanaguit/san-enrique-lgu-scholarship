<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Application Review';
$bodyClass = 'application-review-page';
$isAdmin = is_admin();
$applicationId = (int) ($_GET['id'] ?? 0);
$returnTo = trim((string) ($_GET['return_to'] ?? 'applications.php'));
if ($returnTo === '' || preg_match('/^applications\.php(\?.*)?$/', $returnTo) !== 1) {
    $returnTo = 'applications.php';
}

$application = null;
$documents = [];
$historyRows = [];
$periodTimeline = [];
$allowArchivedUpdates = $isAdmin && trim((string) ($_GET['allow_archived_updates'] ?? '')) === '1';
$activePeriod = db_ready() ? current_active_application_period($conn) : null;
$openPeriod = db_ready() ? current_open_application_period($conn) : null;
$isApplicantIntakeOpen = $openPeriod !== null
    && (int) ($openPeriod['id'] ?? 0) > 0
    && (int) ($openPeriod['id'] ?? 0) === (int) ($activePeriod['id'] ?? 0);
$hasApplicationPeriodColumn = db_ready() && table_column_exists($conn, 'applications', 'application_period_id');

$bulkStatusMap = [
    '__default__' => ['under_review'],
    'draft' => ['under_review'],
    'under_review' => ['for_interview'],
    'needs_resubmission' => ['under_review'],
    'for_interview' => ['for_soa', 'rejected'],
    'for_soa' => ['approved_for_release'],
    'approved_for_release' => ['released'],
    'released' => [],
    'rejected' => [],
];
$allowedStatus = application_status_options();
$statusActionLabels = [
    'under_review' => 'Mark Under Review',
    'for_interview' => 'Move to Interview Queue',
    'needs_resubmission' => 'Request Resubmission',
    'for_soa' => 'Mark Qualified for SOA',
    'approved_for_release' => 'Approve for Payout',
    'released' => 'Mark Released',
    'rejected' => 'Mark as Not Approved',
];

$resolveSmsTemplate = static function (mysqli $conn, string $templateName, string $fallbackBody): string {
    if (!table_exists($conn, 'sms_templates')) {
        return $fallbackBody;
    }
    $stmt = $conn->prepare(
        "SELECT template_body
         FROM sms_templates
         WHERE template_name = ?
           AND is_active = 1
         LIMIT 1"
    );
    if (!$stmt) {
        return $fallbackBody;
    }
    $stmt->bind_param('s', $templateName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $body = trim((string) ($row['template_body'] ?? ''));
    return $body !== '' ? $body : $fallbackBody;
};
$renderSmsTemplate = static function (string $templateBody, array $replacements): string {
    $message = $templateBody;
    foreach ($replacements as $placeholder => $value) {
        $message = str_replace((string) $placeholder, (string) $value, $message);
    }
    return trim($message);
};
$formatApplicantReference = static function (array $current): string {
    $lastName = trim((string) ($current['last_name'] ?? ''));
    $applicationNo = trim((string) ($current['application_no'] ?? ''));

    $parts = [];
    if ($lastName !== '') {
        $parts[] = 'Mr./Ms. ' . $lastName;
    }
    if ($applicationNo !== '') {
        $parts[] = 'Application No. ' . $applicationNo;
    }

    return implode(', ', $parts);
};
$prependApplicantReference = static function (array $current, string $message) use ($formatApplicantReference): string {
    $reference = $formatApplicantReference($current);
    $message = trim($message);
    if ($reference === '' || $message === '') {
        return $message;
    }
    if (preg_match('/^(San Enrique LGU Scholarship:)\s*(.*)$/', $message, $matches) === 1) {
        return $matches[1] . ' ' . $reference . '. ' . ltrim((string) ($matches[2] ?? ''));
    }
    return $reference . ': ' . ltrim($message);
};
$statusSmsTemplateConfig = [
    'under_review' => [
        'template' => 'Application Under Review',
        'fallback' => 'San Enrique LGU Scholarship: Mr./Ms. [Last Name], Application No. [Application No]. Your application is currently under review. Please wait for further updates.',
    ],
    'for_interview' => [
        'template' => 'Documents Verified',
        'fallback' => 'San Enrique LGU Scholarship: Mr./Ms. [Last Name], Application No. [Application No]. Your documents have been verified. Please wait for further updates.',
    ],
    'for_soa' => [
        'template' => 'SOA Submission Required',
        'fallback' => 'San Enrique LGU Scholarship: Mr./Ms. [Last Name], Application No. [Application No]. Please upload your SOA online on or before [Deadline].',
    ],
    'approved_for_release' => [
        'template' => 'Approved for Release',
        'fallback' => 'San Enrique LGU Scholarship: Mr./Ms. [Last Name], Application No. [Application No]. Your application is approved for release. Please wait for the payout schedule.',
    ],
    'released' => [
        'template' => 'Payout Released',
        'fallback' => 'San Enrique LGU Scholarship: Mr./Ms. [Last Name], Application No. [Application No]. Payout has been released.',
    ],
    'rejected' => [
        'template' => 'Application Not Approved',
        'fallback' => 'San Enrique LGU Scholarship: Mr./Ms. [Last Name], Application No. [Application No]. Your application was not approved.',
    ],
];
$buildStatusSmsMessage = static function (string $newStatus, array $current, ?string $deadline = null) use ($conn, $statusSmsTemplateConfig, $resolveSmsTemplate, $renderSmsTemplate, $prependApplicantReference): string {
    $applicationNo = (string) ($current['application_no'] ?? '');
    $lastName = trim((string) ($current['last_name'] ?? ''));
    $deadlineText = ($deadline !== null && trim($deadline) !== '') ? date('M d, Y', strtotime($deadline)) : 'the announced deadline';
    $config = $statusSmsTemplateConfig[$newStatus] ?? null;
    if (!is_array($config)) {
        return $prependApplicantReference(
            $current,
            'San Enrique LGU Scholarship: Your application has been updated.'
        );
    }
    $templateBody = $resolveSmsTemplate($conn, (string) ($config['template'] ?? ''), (string) ($config['fallback'] ?? ''));
    return $renderSmsTemplate($templateBody, [
        '[Last Name]' => $lastName,
        '[Application No]' => $applicationNo,
        '[Deadline]' => $deadlineText,
    ]);
};

if (db_ready() && $applicationId > 0) {
    $stmt = $conn->prepare(
        "SELECT a.id, a.user_id, a.application_no, a.application_period_id, a.applicant_type, a.school_name, a.school_type, a.course,
                a.semester, a.school_year, a.barangay, a.status, a.review_notes, a.interview_date, a.interview_location,
                a.soa_submission_deadline, a.soa_submitted_at, a.submitted_at, a.updated_at, a.photo_path,
                u.first_name, u.last_name, u.email, u.phone
         FROM applications a
         INNER JOIN users u ON u.id = a.user_id
         WHERE a.id = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }

    if ($application && table_exists($conn, 'application_documents')) {
        $stmtDocs = $conn->prepare(
            "SELECT id, application_id, requirement_name, verification_status, file_path, remarks
             FROM application_documents
             WHERE application_id = ?
             ORDER BY id ASC"
        );
        if ($stmtDocs) {
            $stmtDocs->bind_param('i', $applicationId);
            $stmtDocs->execute();
            $resultDocs = $stmtDocs->get_result();
            $documents = $resultDocs instanceof mysqli_result ? $resultDocs->fetch_all(MYSQLI_ASSOC) : [];
            $stmtDocs->close();
        }
    }

    if ($application && table_exists($conn, 'audit_logs')) {
        $stmtLogs = $conn->prepare(
            "SELECT action, description, created_at
             FROM audit_logs
             WHERE entity_type = 'application'
               AND entity_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 12"
        );
        if ($stmtLogs) {
            $entityId = (string) $applicationId;
            $stmtLogs->bind_param('s', $entityId);
            $stmtLogs->execute();
            $resultLogs = $stmtLogs->get_result();
            $historyRows = $resultLogs instanceof mysqli_result ? $resultLogs->fetch_all(MYSQLI_ASSOC) : [];
            $stmtLogs->close();
        }
    }

    if ($application) {
        $periodTimeline = application_period_timeline_for_user($conn, (int) ($application['user_id'] ?? 0));
    }
}

if (!$application) {
    set_flash('danger', 'Application not found.');
    redirect('applications.php');
}

$isArchivedApplication = application_is_archived_for_active_period($application, $activePeriod, $hasApplicationPeriodColumn);
$updatesLocked = $isArchivedApplication && !$allowArchivedUpdates;
$application['rejected_document_count'] = count(array_filter($documents, static fn(array $doc): bool => (string) ($doc['verification_status'] ?? '') === 'rejected'));
$rowTransitionOptions = array_values(array_filter(
    $bulkStatusMap[(string) ($application['status'] ?? '')] ?? [],
    static fn($status): bool => in_array((string) $status, $allowedStatus, true)
));
$hasInterviewSchedule = trim((string) ($application['interview_date'] ?? '')) !== ''
    && trim((string) ($application['interview_location'] ?? '')) !== '';
if ((string) ($application['status'] ?? '') === 'for_interview' && !$hasInterviewSchedule) {
    $rowTransitionOptions = array_values(array_filter($rowTransitionOptions, static fn($status): bool => (string) $status !== 'for_soa'));
}
if ((string) ($application['status'] ?? '') === 'for_soa') {
    $rowTransitionOptions = array_values(array_filter($rowTransitionOptions, static fn($status): bool => (string) $status !== 'approved_for_release'));
}
$canRejectApplication = in_array('rejected', $rowTransitionOptions, true);

include __DIR__ . '/../../includes/header.php';
?>

<section class="card card-soft applicant-hero review-page-hero mb-3">
    <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <p class="small text-muted mb-1">Review Workspace</p>
            <h1 class="h4 m-0"><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Application Review</h1>
            <p class="small text-muted mb-0 mt-1">One focused workspace for summary, documents, decisions, communication, and history.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap applicant-hero-actions">
            <a href="<?= e($returnTo) ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Review Board</a>
        </div>
    </div>
</section>

<?php if ($updatesLocked): ?>
    <div class="alert alert-warning py-2 small">This record belongs to an archived period. Enable archived updates from the review board to modify it.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-5">
        <?php
        $summaryApplication = $application;
        $summaryAudience = 'staff';
        $summaryShowSoaDeadline = (string) ($application['status'] ?? '') !== 'for_soa';
        include __DIR__ . '/../../includes/partials/application-summary-card.php';
        ?>
    </div>

    <div class="col-12 col-xl-7">
        <details class="workflow-panel workflow-panel-collapsible">
            <summary>
                <span>Submitted Documents</span>
                <small><?= count($documents) ?> file<?= count($documents) === 1 ? '' : 's' ?></small>
            </summary>
            <div class="workflow-panel-body">
                <?php if (!$documents): ?>
                    <div class="alert alert-warning py-2 small mb-0">No uploaded documents found for this application.</div>
                <?php else: ?>
                    <div class="list-group workflow-document-list">
                        <?php foreach ($documents as $doc): ?>
                            <?php
                            $docId = (int) ($doc['id'] ?? 0);
                            $docPath = trim((string) ($doc['file_path'] ?? ''));
                            $safeDocPath = str_replace('\\', '/', $docPath);
                            $canViewDoc = $safeDocPath !== '' && !str_contains($safeDocPath, '..') && preg_match('/^uploads\//', $safeDocPath) === 1;
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <div class="fw-semibold"><?= e((string) ($doc['requirement_name'] ?? ('Document #' . $docId))) ?></div>
                                    <div class="small text-muted">Verification: <?= e(strtoupper(document_verification_status_label($doc))) ?></div>
                                    <?php if (trim((string) ($doc['remarks'] ?? '')) !== ''): ?>
                                        <div class="small text-muted">Notes: <?= e((string) ($doc['remarks'] ?? '')) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($canViewDoc): ?>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm js-open-doc-preview" data-preview-src="<?= e($safeDocPath) ?>" data-preview-title="<?= e((string) ($doc['requirement_name'] ?? 'Document')) ?>">View</button>
                                        <a href="../<?= e($safeDocPath) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">Open</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>

        <div class="card card-soft shadow-sm mt-3 workflow-decision-card">
            <div class="card-body">
                <h2 class="h6 mb-3">Decision & Communication</h2>

                <?php if (in_array((string) ($application['status'] ?? ''), ['under_review', 'needs_resubmission'], true)): ?>
                    <form method="post" action="applications.php" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="review_documents">
                        <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                        <input type="hidden" name="redirect_to" value="<?= e('application-review.php?id=' . (int) $application['id'] . '&return_to=' . urlencode($returnTo)) ?>">

                        <div class="col-12">
                            <label class="form-label form-label-sm">Document Checklist</label>
                            <div class="border rounded p-2">
                                <?php foreach ($documents as $doc): ?>
                                    <?php $docId = (int) ($doc['id'] ?? 0); ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="doc_verified[]" value="<?= $docId ?>" id="docVerify<?= $docId ?>" <?= (string) ($doc['verification_status'] ?? '') === 'verified' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="docVerify<?= $docId ?>"><?= e((string) ($doc['requirement_name'] ?? ('Document #' . $docId))) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text mt-2">
                                Check each document that is complete and acceptable. Leave unchecked documents that still need correction or resubmission.
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm">Optional Notes</label>
                            <input type="text" name="review_notes" class="form-control form-control-sm" value="<?= e((string) ($application['review_notes'] ?? '')) ?>" placeholder="Reason, clarification, or instruction">
                        </div>
                        <div class="col-12">
                            <div class="workflow-preview-box small">
                                <strong>SMS behavior</strong>
                                <div class="mt-1">If any document remains unchecked, the applicant receives the fixed resubmission message with the missing document names and your optional notes.</div>
                                <div class="mt-1">If all documents are checked, the applicant receives the interview-stage notification.</div>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-between flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary btn-sm" <?= ($documents && !$updatesLocked) ? '' : 'disabled' ?>>Finalize Document Review</button>
                        </div>
                    </form>
                    <?php if ($canRejectApplication): ?>
                        <form method="post" action="applications.php" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                            <input type="hidden" name="status" value="rejected">
                            <input type="hidden" name="review_notes" value="<?= e((string) ($application['review_notes'] ?? '')) ?>">
                            <input type="hidden" name="redirect_to" value="<?= e('application-review.php?id=' . (int) $application['id'] . '&return_to=' . urlencode($returnTo)) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" <?= $updatesLocked ? 'disabled' : '' ?>>Mark as Not Approved</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" action="applications.php" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                        <input type="hidden" name="redirect_to" value="<?= e('application-review.php?id=' . (int) $application['id'] . '&return_to=' . urlencode($returnTo)) ?>">

                        <?php if ((string) ($application['status'] ?? '') === 'for_interview'): ?>
                            <div class="col-12">
                                <div class="workflow-preview-box small">
                                    <strong>Interview scheduling is managed in batch.</strong>
                                    <?php if ($hasInterviewSchedule): ?>
                                        <div class="mt-1">
                                            Current schedule:
                                            <?= e(date('M d, Y h:i A', strtotime((string) ($application['interview_date'] ?? '')))) ?>
                                            at
                                            <?= e((string) ($application['interview_location'] ?? 'Mayor\'s Office, San Enrique')) ?>
                                        </div>
                                        <div class="mt-1">If the interview is already finished, you can mark it completed below.</div>
                                    <?php else: ?>
                                        <div class="mt-1">Set the interview schedule first from the Interview Queue batch action.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label form-label-sm">Optional Notes</label>
                            <input type="text" name="review_notes" class="form-control form-control-sm" value="<?= e((string) ($application['review_notes'] ?? '')) ?>" placeholder="Add decision notes or instructions">
                        </div>

                        <?php if ($rowTransitionOptions): ?>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Available Actions</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($rowTransitionOptions as $status): ?>
                                        <button type="submit" class="btn btn-outline-primary btn-sm" name="status" value="<?= e((string) $status) ?>" <?= $updatesLocked ? 'disabled' : '' ?>>
                                            <?= e($statusActionLabels[(string) $status] ?? application_status_label((string) $status)) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ((string) ($application['status'] ?? '') === 'for_soa'): ?>
                            <?php
                            $soaDeadlineRaw = trim((string) ($application['soa_submission_deadline'] ?? ''));
                            $soaDeadlineTs = $soaDeadlineRaw !== '' ? strtotime($soaDeadlineRaw) : false;
                            $todayTs = strtotime(date('Y-m-d'));
                            $deadlineState = 'Set an upload deadline to support reminders and tracking.';
                            $deadlineToneClass = 'text-muted';
                                if ($soaDeadlineTs !== false) {
                                    if ($soaDeadlineTs < $todayTs) {
                                    $deadlineState = 'Deadline passed. Review the uploaded SOA if it has already been submitted, or go back to the For SOA board to adjust the deadline.';
                                    $deadlineToneClass = 'text-danger';
                                } elseif ($soaDeadlineTs === $todayTs) {
                                    $deadlineState = 'Deadline is today. Review the uploaded SOA and confirm it once the school document has been checked.';
                                    $deadlineToneClass = 'text-warning';
                                } else {
                                    $deadlineState = 'Upload window is still open. Review the uploaded SOA and confirm it once the school document has been checked.';
                                    $deadlineToneClass = 'text-muted';
                                }
                            }
                            ?>
                            <div class="col-12">
                                <div class="workflow-preview-box workflow-deadline-card small">
                                    <div class="workflow-deadline-card__kicker">SOA Upload Window</div>
                                    <div class="workflow-deadline-card__value">
                                        <?php if ($soaDeadlineTs !== false): ?>
                                            <?= e(date('M d, Y', $soaDeadlineTs)) ?>
                                        <?php else: ?>
                                            No deadline set
                                        <?php endif; ?>
                                    </div>
                                    <div class="workflow-deadline-card__detail <?= e($deadlineToneClass) ?>"><?= e($deadlineState) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                    </form>

                    <?php if ((string) ($application['status'] ?? '') === 'for_soa'): ?>
                        <div class="mt-3 d-flex flex-column gap-2">
                            <form method="post" action="applications.php" class="row g-2 align-items-end js-soa-resubmission-form" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="request_soa_resubmission">
                                <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                                <input type="hidden" name="redirect_to" value="<?= e('application-review.php?id=' . (int) $application['id'] . '&return_to=' . urlencode($returnTo)) ?>">
                                <div class="col-12 col-md">
                                    <label class="form-label form-label-sm">Reason for SOA replacement</label>
                                    <input type="text" name="soa_review_notes" class="form-control form-control-sm js-soa-resubmission-notes" maxlength="255" placeholder="Explain what needs to be corrected" required>
                                    <div class="form-text js-soa-resubmission-feedback">Enter the correction needed before sending the request.</div>
                                </div>
                                <div class="col-12 col-md-auto">
                                    <button type="submit" class="btn btn-outline-warning btn-sm js-soa-resubmission-submit" <?= $updatesLocked ? 'disabled' : '' ?>>Request SOA Resubmission</button>
                                </div>
                            </form>
                            <form method="post" action="applications.php">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="mark_soa_submitted">
                                <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                                <input type="hidden" name="redirect_to" value="<?= e('application-review.php?id=' . (int) $application['id'] . '&return_to=' . urlencode($returnTo)) ?>">
                                <button type="submit" class="btn btn-success btn-sm" <?= $updatesLocked ? 'disabled' : '' ?>>Confirm Uploaded SOA</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <details class="workflow-panel workflow-panel-collapsible mt-3">
            <summary>
                <span>Application Form</span>
                <small>Filled form preview</small>
            </summary>
            <div class="workflow-panel-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="../print-application.php?id=<?= (int) $application['id'] ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer">
                        <i class="fa-solid fa-up-right-from-square me-1"></i>Open Full Form
                    </a>
                    <a href="../print-application.php?id=<?= (int) $application['id'] ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-print me-1"></i>Print Form
                    </a>
                </div>
                <div class="border rounded overflow-hidden bg-white" style="min-height: 720px;">
                    <iframe
                        src="../print-application.php?id=<?= (int) $application['id'] ?>&embed=1"
                        title="Application Form Preview"
                        style="border:0;width:100%;height:720px;background:#fff;"
                    ></iframe>
                </div>
            </div>
        </details>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-soa-resubmission-form').forEach(function (form) {
                var input = form.querySelector('.js-soa-resubmission-notes');
                var submit = form.querySelector('.js-soa-resubmission-submit');
                var feedback = form.querySelector('.js-soa-resubmission-feedback');
                if (!input || !submit || !feedback) {
                    return;
                }

                var validate = function () {
                    var value = String(input.value || '').trim();
                    var valid = value.length > 0;

                    input.classList.remove('is-valid', 'is-invalid');
                    if (valid) {
                        input.classList.add('is-valid');
                        feedback.textContent = 'Ready to send the SOA resubmission request.';
                        feedback.className = 'form-text text-success js-soa-resubmission-feedback';
                    } else {
                        input.classList.add('is-invalid');
                        feedback.textContent = 'Enter the correction needed before sending the request.';
                        feedback.className = 'form-text text-danger js-soa-resubmission-feedback';
                    }

                    submit.disabled = !valid || submit.hasAttribute('data-base-disabled');
                    return valid;
                };

                if (submit.disabled) {
                    submit.setAttribute('data-base-disabled', '1');
                }

                input.addEventListener('input', validate);
                input.addEventListener('blur', validate);

                form.addEventListener('submit', function (event) {
                    if (!validate()) {
                        event.preventDefault();
                        input.focus();
                    }
                });

                input.classList.remove('is-valid', 'is-invalid');
                feedback.className = 'form-text text-muted js-soa-resubmission-feedback';
                submit.disabled = true;
            });
        });
        </script>

        <details class="workflow-panel workflow-panel-collapsible mt-3">
            <summary>
                <span>Period History</span>
                <small><?= count($periodTimeline) ?> period<?= count($periodTimeline) === 1 ? '' : 's' ?></small>
            </summary>
            <div class="workflow-panel-body">
                <?php if (!$periodTimeline): ?>
                    <p class="small text-muted mb-0">No application periods available yet.</p>
                <?php else: ?>
                    <div class="period-history-list">
                        <?php foreach ($periodTimeline as $timelineRow): ?>
                            <div class="period-history-item">
                                <div>
                                    <div class="fw-semibold small"><?= e((string) ($timelineRow['period_label'] ?? 'Application Period')) ?></div>
                                    <?php if ((bool) ($timelineRow['has_application'] ?? false) && trim((string) ($timelineRow['application_no'] ?? '')) !== ''): ?>
                                        <div class="small text-muted"><?= e((string) ($timelineRow['application_no'] ?? '')) ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= e((string) ($timelineRow['badge_class'] ?? 'text-bg-light')) ?>">
                                    <?= e((string) ($timelineRow['label'] ?? 'No application')) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>

        <details class="workflow-panel workflow-panel-collapsible mt-3">
            <summary>
                <span>Scholarship History</span>
                <small><?= count($historyRows) ?> item<?= count($historyRows) === 1 ? '' : 's' ?></small>
            </summary>
            <div class="workflow-panel-body">
                <?php if (!$historyRows): ?>
                    <p class="small text-muted mb-0">No recorded history yet.</p>
                <?php else: ?>
                    <div class="workflow-history-list">
                        <?php foreach ($historyRows as $historyRow): ?>
                            <div class="workflow-history-item">
                                <div class="fw-semibold small"><?= e(audit_action_label((string) ($historyRow['action'] ?? ''))) ?></div>
                                <?php if (trim((string) ($historyRow['description'] ?? '')) !== ''): ?>
                                    <div class="small text-muted"><?= e((string) ($historyRow['description'] ?? '')) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted"><?= !empty($historyRow['created_at']) ? date('M d, Y h:i A', strtotime((string) $historyRow['created_at'])) : '-' ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>
    </div>
</div>

<div class="modal fade" id="adminDocPreviewModal" tabindex="-1" aria-labelledby="adminDocPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 m-0" id="adminDocPreviewModalLabel">Document Preview</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="adminDocPreviewFrame" src="about:blank" title="Document Preview" style="border:0;width:100%;height:100%;background:#fff;"></iframe>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="#" id="adminDocPreviewNewTab" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">Open in New Tab</a>
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('adminDocPreviewModal');
    const titleEl = document.getElementById('adminDocPreviewModalLabel');
    const frameEl = document.getElementById('adminDocPreviewFrame');
    const newTabEl = document.getElementById('adminDocPreviewNewTab');
    const modal = (modalEl && typeof bootstrap !== 'undefined') ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

    document.querySelectorAll('.js-open-doc-preview').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!modal || !frameEl) {
                return;
            }
            const src = String(button.getAttribute('data-preview-src') || '').trim();
            if (!src) {
                return;
            }
            const title = String(button.getAttribute('data-preview-title') || 'Document Preview');
            const previewUrl = '../preview-document.php?file=' + encodeURIComponent(src);
            if (titleEl) {
                titleEl.textContent = title;
            }
            frameEl.src = previewUrl;
            if (newTabEl) {
                newTabEl.href = previewUrl;
            }
            modal.show();
        });
    });

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (frameEl) {
                frameEl.src = 'about:blank';
            }
            if (newTabEl) {
                newTabEl.href = '#';
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
