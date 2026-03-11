<?php
declare(strict_types=1);

$summaryApplication = is_array($summaryApplication ?? null) ? $summaryApplication : [];
$summaryAudience = strtolower(trim((string) ($summaryAudience ?? 'staff')));
$summaryShowSoaDeadline = (bool) ($summaryShowSoaDeadline ?? true);
$summaryStatus = trim((string) ($summaryApplication['status'] ?? ''));
$summaryNextAction = application_next_action_summary($summaryApplication, $summaryAudience);
$summaryTimelineSteps = application_timeline_steps($summaryStatus);
?>
<div class="card card-soft shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <p class="small text-muted mb-1">Current Status</p>
                <h2 class="h5 mb-1"><?= e(application_status_label($summaryStatus)) ?></h2>
                <div class="small text-muted">
                    <?= e((string) ($summaryApplication['application_no'] ?? '-')) ?> |
                    <?= e((string) ($summaryApplication['semester'] ?? '-')) ?> / <?= e((string) ($summaryApplication['school_year'] ?? '-')) ?>
                </div>
            </div>
            <span class="badge <?= status_badge_class($summaryStatus) ?>">
                <?= e(strtoupper(application_status_label($summaryStatus))) ?>
            </span>
        </div>

        <div class="applicant-next-action mb-3">
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

        <div class="row g-2 mt-2">
            <div class="col-12 col-md-6">
                <div class="applicant-detail-card">
                    <div class="small text-muted text-uppercase">Applicant</div>
                    <div><?= e(trim((string) ($summaryApplication['first_name'] ?? '') . ' ' . (string) ($summaryApplication['last_name'] ?? ''))) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="applicant-detail-card">
                    <div class="small text-muted text-uppercase">School</div>
                    <div><?= e((string) ($summaryApplication['school_name'] ?? '-')) ?></div>
                    <div class="small text-muted"><?= e(strtoupper((string) ($summaryApplication['school_type'] ?? ''))) ?></div>
                </div>
            </div>
            <?php if (!empty($summaryApplication['interview_date'])): ?>
                <div class="col-12 col-md-6">
                    <div class="applicant-detail-card">
                        <div class="small text-muted text-uppercase">Interview</div>
                        <div><?= date('M d, Y h:i A', strtotime((string) $summaryApplication['interview_date'])) ?></div>
                        <div class="small text-muted"><?= e((string) ($summaryApplication['interview_location'] ?? '')) ?></div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($summaryShowSoaDeadline && !empty($summaryApplication['soa_submission_deadline'])): ?>
                <div class="col-12 col-md-6">
                    <div class="applicant-detail-card">
                        <div class="small text-muted text-uppercase">SOA Deadline</div>
                        <div><?= date('M d, Y', strtotime((string) $summaryApplication['soa_submission_deadline'])) ?></div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($summaryApplication['soa_submitted_at'])): ?>
                <div class="col-12 col-md-6">
                    <div class="applicant-detail-card">
                        <div class="small text-muted text-uppercase">SOA Received</div>
                        <div><?= date('M d, Y h:i A', strtotime((string) $summaryApplication['soa_submitted_at'])) ?></div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-6">
                <div class="applicant-detail-card">
                    <div class="small text-muted text-uppercase">Updated</div>
                    <div><?= !empty($summaryApplication['updated_at']) ? date('M d, Y h:i A', strtotime((string) $summaryApplication['updated_at'])) : '-' ?></div>
                </div>
            </div>
        </div>

        <?php if (trim((string) ($summaryApplication['review_notes'] ?? '')) !== ''): ?>
            <div class="applicant-review-note mt-3">
                <div class="small text-muted text-uppercase mb-1">Notes</div>
                <div><?= e((string) ($summaryApplication['review_notes'] ?? '')) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
