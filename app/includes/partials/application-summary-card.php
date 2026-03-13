<?php
declare(strict_types=1);

$summaryApplication = is_array($summaryApplication ?? null) ? $summaryApplication : [];
$summaryAudience = strtolower(trim((string) ($summaryAudience ?? 'staff')));
$summaryShowSoaDeadline = (bool) ($summaryShowSoaDeadline ?? true);
$summaryStatus = trim((string) ($summaryApplication['status'] ?? ''));
$summaryNextAction = application_next_action_summary($summaryApplication, $summaryAudience);
$summaryTimelineSteps = application_timeline_steps($summaryStatus);
$summaryStatusLabel = $summaryAudience === 'staff'
    ? application_staff_status_label($summaryStatus)
    : application_status_label($summaryStatus);
$summaryApplicantName = trim((string) ($summaryApplication['first_name'] ?? '') . ' ' . (string) ($summaryApplication['last_name'] ?? ''));
if ($summaryApplicantName === '') {
    $summaryApplicantName = 'Applicant';
}
$summaryPeriodLabel = trim((string) ($summaryApplication['semester'] ?? '') . ' ' . (string) ($summaryApplication['school_year'] ?? ''));
$summaryPhotoPath = trim((string) ($summaryApplication['photo_path'] ?? ''));
$summaryUpdatedAt = !empty($summaryApplication['updated_at'])
    ? date('M d, Y h:i A', strtotime((string) $summaryApplication['updated_at']))
    : '-';
?>
<div class="card card-soft shadow-sm review-summary-card">
    <div class="card-body">
        <div class="review-summary-top">
            <div class="review-summary-identity">
                <div class="review-summary-photo">
                    <?php if ($summaryPhotoPath !== ''): ?>
                        <img src="<?= e($summaryPhotoPath) ?>" alt="Applicant photo">
                    <?php else: ?>
                        <div class="review-summary-photo-placeholder">
                            <i class="fa-solid fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="review-summary-heading">
                    <div class="small text-muted text-uppercase mb-1">Applicant Record</div>
                    <h2 class="h4 mb-1"><?= e($summaryApplicantName) ?></h2>
                    <div class="text-muted small">
                        <?= e((string) ($summaryApplication['application_no'] ?? '-')) ?>
                        <?php if ($summaryPeriodLabel !== ''): ?>
                            <span class="mx-1">|</span><?= e($summaryPeriodLabel) ?>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted mt-1">
                        <?= e((string) ($summaryApplication['school_name'] ?? '-')) ?>
                        <?php if (trim((string) ($summaryApplication['course'] ?? '')) !== ''): ?>
                            <span class="mx-1">|</span><?= e((string) ($summaryApplication['course'] ?? '')) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="review-summary-status">
                <span class="badge <?= status_badge_class($summaryStatus) ?>">
                    <?= e(strtoupper($summaryStatusLabel)) ?>
                </span>
                <div class="small text-muted mt-2">Updated <?= e($summaryUpdatedAt) ?></div>
            </div>
        </div>

        <div class="applicant-next-action mt-3 mb-3">
            <div class="small text-muted text-uppercase mb-1">Next Action</div>
            <div class="fw-semibold"><?= e((string) ($summaryNextAction['title'] ?? 'Review this application.')) ?></div>
            <?php if (trim((string) ($summaryNextAction['detail'] ?? '')) !== ''): ?>
                <div class="small text-muted mt-1"><?= e((string) ($summaryNextAction['detail'] ?? '')) ?></div>
            <?php endif; ?>
        </div>

        <?php
        $timelineSteps = $summaryTimelineSteps;
        $stepperClass = '';
        $stepperLabel = 'Application progress';
        include __DIR__ . '/application-workflow-stepper.php';
        ?>

        <div class="review-summary-meta-grid mt-3">
            <div class="applicant-detail-card">
                <div class="small text-muted text-uppercase">Applicant Type</div>
                <div><?= e((string) ($summaryApplication['applicant_type'] ?? '-')) ?></div>
            </div>
            <div class="applicant-detail-card">
                <div class="small text-muted text-uppercase">School Type</div>
                <div><?= e(ucfirst((string) ($summaryApplication['school_type'] ?? '-'))) ?></div>
            </div>
            <div class="applicant-detail-card">
                <div class="small text-muted text-uppercase">Barangay</div>
                <div><?= e((string) ($summaryApplication['barangay'] ?? '-')) ?></div>
            </div>
            <div class="applicant-detail-card">
                <div class="small text-muted text-uppercase">Contact</div>
                <div><?= e((string) ($summaryApplication['phone'] ?? '-')) ?></div>
                <div class="small text-muted"><?= e((string) ($summaryApplication['email'] ?? '-')) ?></div>
            </div>
            <?php if (!empty($summaryApplication['interview_date'])): ?>
                <div class="applicant-detail-card">
                    <div class="small text-muted text-uppercase">Interview</div>
                    <div><?= date('M d, Y h:i A', strtotime((string) $summaryApplication['interview_date'])) ?></div>
                    <div class="small text-muted"><?= e((string) ($summaryApplication['interview_location'] ?? '')) ?></div>
                </div>
            <?php endif; ?>
            <?php if ($summaryShowSoaDeadline && !empty($summaryApplication['soa_submission_deadline'])): ?>
                <div class="applicant-detail-card">
                    <div class="small text-muted text-uppercase">SOA Deadline</div>
                    <div><?= date('M d, Y', strtotime((string) $summaryApplication['soa_submission_deadline'])) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($summaryApplication['soa_submitted_at'])): ?>
                <div class="applicant-detail-card">
                    <div class="small text-muted text-uppercase">SOA Received</div>
                    <div><?= date('M d, Y h:i A', strtotime((string) $summaryApplication['soa_submitted_at'])) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (trim((string) ($summaryApplication['review_notes'] ?? '')) !== ''): ?>
            <div class="applicant-review-note mt-3">
                <div class="small text-muted text-uppercase mb-1">Notes</div>
                <div><?= e((string) ($summaryApplication['review_notes'] ?? '')) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
