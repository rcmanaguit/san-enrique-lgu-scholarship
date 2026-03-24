<?php
$summaryApplication = is_array($summaryApplication ?? null) ? $summaryApplication : [];
$summaryApplicationNumber = trim((string) ($summaryApplicationNumber ?? ''));
$summaryNextAction = is_array($summaryNextAction ?? null) ? $summaryNextAction : ['title' => 'Review this application', 'detail' => ''];
$summaryWorkflowSteps = is_array($summaryWorkflowSteps ?? null) ? $summaryWorkflowSteps : [];
$summaryStatus = trim((string) ($summaryApplication['status'] ?? ''));
$summaryStatusLabel = $summaryStatus !== '' ? str_replace('_', ' ', $summaryStatus) : 'No Status';
$summaryPeriodLabel = trim((string) (($summaryApplication['school_year'] ?? '') . ' | ' . ($summaryApplication['semester'] ?? '')));
$summaryApplicantName = trim((string) (($summaryApplication['last_name'] ?? '') . ', ' . ($summaryApplication['first_name'] ?? '')));
if ($summaryApplicantName === ',') {
    $summaryApplicantName = 'Applicant';
}
$summaryLifecycle = trim((string) ($summaryApplication['record_lifecycle'] ?? 'Active'));
$summaryApplicantAge = '';
try {
    $summaryBirthDate = trim((string) ($summaryApplication['date_of_birth'] ?? ''));
    if ($summaryBirthDate !== '') {
        $summaryApplicantAge = (string) ((new DateTimeImmutable($summaryBirthDate))->diff(new DateTimeImmutable('today'))->y);
    }
} catch (Throwable $exception) {
    $summaryApplicantAge = '';
}
?>
<section class="app-surface app-review-summary-card mb-4">
    <div class="app-surface-body">
        <div class="app-review-summary-top">
            <div>
                <div class="app-page-eyebrow mb-2">Application Record</div>
                <h2 class="app-review-summary-title"><?php echo htmlspecialchars($summaryApplicantName); ?></h2>
                <div class="app-review-summary-meta">
                    <span><?php echo htmlspecialchars($summaryApplicationNumber !== '' ? $summaryApplicationNumber : 'Application'); ?></span>
                    <?php if ($summaryPeriodLabel !== '|' && $summaryPeriodLabel !== ''): ?>
                        <span><?php echo htmlspecialchars($summaryPeriodLabel); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($summaryApplication['application_type'])): ?>
                        <span><?php echo htmlspecialchars((string) $summaryApplication['application_type']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-2">
                    <?php echo htmlspecialchars((string) ($summaryApplication['school_name'] ?? '-')); ?>
                    <?php if (!empty($summaryApplication['school_type'])): ?>
                        | <?php echo htmlspecialchars((string) $summaryApplication['school_type']); ?>
                    <?php endif; ?>
                    <?php if (!empty($summaryApplication['address_barangay'])): ?>
                        | <?php echo htmlspecialchars((string) $summaryApplication['address_barangay']); ?>
                    <?php endif; ?>
                    <?php if ($summaryApplicantAge !== ''): ?>
                        | <?php echo htmlspecialchars('Age: ' . $summaryApplicantAge); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="app-review-summary-status">
                <span class="app-pill-badge"><?php echo htmlspecialchars($summaryStatusLabel); ?></span>
                <span class="badge text-bg-secondary"><?php echo htmlspecialchars($summaryLifecycle); ?></span>
            </div>
        </div>

        <div class="app-next-action-panel mt-3">
            <div class="small text-muted text-uppercase mb-1">Next Action</div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($summaryNextAction['title'] ?? 'Review this application')); ?></div>
            <?php if (trim((string) ($summaryNextAction['detail'] ?? '')) !== ''): ?>
                <div class="small text-muted mt-1"><?php echo htmlspecialchars((string) $summaryNextAction['detail']); ?></div>
            <?php endif; ?>
        </div>

        <?php
        $workflowSteps = $summaryWorkflowSteps;
        $workflowLabel = 'Application workflow progress';
        require __DIR__ . '/application_workflow_stepper.php';
        ?>
    </div>
</section>
