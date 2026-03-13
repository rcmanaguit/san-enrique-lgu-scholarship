<?php
declare(strict_types=1);

if (!$latestApplication) {
    return;
}

$latestStatus = (string) ($latestApplication['status'] ?? '');
$latestMeta = application_status_meta($latestStatus);
$latestApplication['rejected_document_count'] = count($resubmissionTargetsByAppId[(int) ($latestApplication['id'] ?? 0)] ?? []);
$latestNextAction = application_next_action_summary($latestApplication, 'applicant');
$latestTimeline = application_timeline_steps($latestStatus);
$latestSoaDoc = $soaDocumentsByAppId[(int) ($latestApplication['id'] ?? 0)] ?? null;
$latestSoaRejected = is_array($latestSoaDoc) && (string) ($latestSoaDoc['verification_status'] ?? '') === 'rejected';
$latestSoaRemarks = trim((string) ($latestSoaDoc['remarks'] ?? ''));
?>
<div class="card card-soft page-shell-section mb-3">
    <div class="card-body">
        <div class="action-banner">
            <div>
                <div class="small text-muted text-uppercase mb-1">What happens next</div>
                <div class="action-banner__title"><?= e((string) ($latestNextAction['title'] ?? 'Check application updates.')) ?></div>
                <?php if (trim((string) ($latestNextAction['detail'] ?? '')) !== ''): ?>
                    <div class="action-banner__detail mt-1"><?= e((string) ($latestNextAction['detail'] ?? '')) ?></div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm js-open-application-modal" data-app-id="<?= (int) ($latestApplication['id'] ?? 0) ?>">
                    <i class="fa-solid fa-file-lines me-1"></i>View Details
                </button>
            </div>
        </div>
    </div>
</div>
<div class="card card-soft shadow-sm mb-3 applicant-progress-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Current Application</h2>
                <div class="small text-muted">
                    <?= e((string) ($latestApplication['application_no'] ?? '-')) ?> |
                    <?= e((string) ($latestApplication['semester'] ?? '-')) ?> / <?= e((string) ($latestApplication['school_year'] ?? '-')) ?>
                </div>
            </div>
            <span class="badge <?= status_badge_class($latestStatus) ?>"><?= e(strtoupper(application_status_label($latestStatus))) ?></span>
        </div>
        <?php
        $timelineSteps = $latestTimeline;
        $stepperClass = 'mb-3';
        $stepperLabel = 'Current workflow progress';
        include __DIR__ . '/../application-workflow-stepper.php';
        ?>
        <div class="row g-2">
            <div class="col-12 col-md-6">
                <div class="applicant-detail-card">
                    <div class="small text-muted text-uppercase">Submitted</div>
                    <div><?= !empty($latestApplication['submitted_at']) ? date('M d, Y h:i A', strtotime((string) ($latestApplication['submitted_at']))) : '-' ?></div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="applicant-detail-card">
                    <div class="small text-muted text-uppercase">Updated</div>
                    <div><?= date('M d, Y h:i A', strtotime((string) ($latestApplication['updated_at'] ?? 'now'))) ?></div>
                </div>
            </div>
            <?php if (!empty($latestApplication['interview_date']) && !empty($latestApplication['interview_location'])): ?>
                <div class="col-12 col-md-6">
                    <div class="applicant-detail-card">
                        <div class="small text-muted text-uppercase">Interview</div>
                        <div><?= date('M d, Y h:i A', strtotime((string) $latestApplication['interview_date'])) ?></div>
                        <div class="small text-muted"><?= e((string) $latestApplication['interview_location']) ?></div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($latestApplication['soa_submission_deadline'])): ?>
                <div class="col-12 col-md-6">
                    <div class="applicant-detail-card">
                        <div class="small text-muted text-uppercase">SOA Deadline</div>
                        <div><?= date('M d, Y', strtotime((string) $latestApplication['soa_submission_deadline'])) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($latestStatus === 'needs_resubmission'): ?>
            <div class="alert alert-warning small mt-3 mb-0">
                Replace the rejected documents below, then submit them again so your application can return to review.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($latestStatus === 'for_soa'): ?>
    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h6 mb-1">Upload SOA</h2>
                    <div class="small text-muted">
                        <?= e((string) ($latestApplication['application_no'] ?? '-')) ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Upload your SOA or Student Copy online. The scholarship office will review your file after submission.
                    </div>
                </div>
                <?php if ($latestSoaRejected): ?>
                    <span class="badge text-bg-danger">Needs Replacement</span>
                <?php elseif (!empty($latestApplication['soa_submitted_at'])): ?>
                    <span class="badge text-bg-success">Uploaded</span>
                <?php endif; ?>
            </div>
            <?php if ($latestSoaRejected): ?>
                <div class="alert alert-warning small mb-3">
                    <div class="fw-semibold mb-1">Your uploaded SOA needs correction.</div>
                    <div>Replace the current file with a corrected SOA so the scholarship office can review it again.</div>
                    <?php if ($latestSoaRemarks !== ''): ?>
                        <div class="mt-2"><strong>Staff note:</strong> <?= e($latestSoaRemarks) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="submit_soa_digital">
                <input type="hidden" name="application_id" value="<?= (int) ($latestApplication['id'] ?? 0) ?>">
                <div class="col-12">
                    <input type="file" name="soa_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="form-text">Accepted formats: PDF, JPG, JPEG, or PNG.</div>
                </div>
                <?php if (is_array($latestSoaDoc) && trim((string) ($latestSoaDoc['path'] ?? '')) !== ''): ?>
                    <div class="col-12">
                        <a href="preview-document.php?file=<?= urlencode((string) ($latestSoaDoc['path'] ?? '')) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                            View Current Upload
                        </a>
                    </div>
                <?php endif; ?>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-upload me-1"></i><?= is_array($latestSoaDoc) && trim((string) ($latestSoaDoc['path'] ?? '')) !== '' ? 'Replace SOA' : 'Upload SOA' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
