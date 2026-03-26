<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$hasConfiguredPeriod = !empty($settings['school_year']) && !empty($settings['semester']);
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Period Management</span>
                    <h1 class="app-page-title">Application Period Management</h1>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Current Application Period</h2>
                    </div>
                    <?php if ($hasConfiguredPeriod): ?>
                        <div class="app-page-header-actions">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#extendDeadlineModal">
                                Extend Deadline
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#closeSubmissionsModal">
                                Close
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="app-surface-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="app-info-card h-100">
                                <div class="app-inline-note mb-2">Current period</div>
                                <div class="fw-bold"><?php echo htmlspecialchars($activePeriodLabel); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="app-info-card h-100">
                                <div class="app-inline-note mb-2">Submission window</div>
                                <div class="fw-bold">
                                    <?php if (!empty($settings['application_start_date']) && !empty($settings['application_end_date'])): ?>
                                        <?php echo htmlspecialchars((string) $settings['application_start_date']); ?> to
                                        <?php echo htmlspecialchars((string) $settings['application_end_date']); ?>
                                    <?php else: ?>
                                        Not configured
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="app-info-card h-100">
                                <div class="app-inline-note mb-2">Student submissions</div>
                                <div class="fw-bold"><?php echo htmlspecialchars($applicationStatusHeading); ?></div>
                                <div class="small text-muted mt-1"><?php echo htmlspecialchars($applicationStatusCopy); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($settingsWarnings)): ?>
                        <div class="alert alert-warning border-0 shadow-sm mt-4 mb-0">
                            <div class="fw-bold">Attention Needed</div>
                            <ul class="mb-0 mt-2 ps-3">
                                <?php foreach ($settingsWarnings as $settingsWarning): ?>
                                    <li><?php echo htmlspecialchars((string) $settingsWarning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-12">
                    <section class="app-surface">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Open New Application Period</h2>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <form action="<?php echo htmlspecialchars(base_url('admin/settings')); ?>" method="POST" id="open-new-period-form">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="action_mode" value="open_new">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">School Year *</label>
                                        <input type="text" class="form-control" name="school_year"
                                            value="<?php echo htmlspecialchars((string) ($settings['school_year'] ?? '')); ?>"
                                            placeholder="e.g., 2026-2027" pattern="\d{4}-\d{4}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Semester *</label>
                                        <select class="form-select" name="semester" required>
                                            <option value="">Select...</option>
                                            <option value="1st Semester" <?php echo (($settings['semester'] ?? '') === '1st Semester') ? 'selected' : ''; ?>>1st Semester</option>
                                            <option value="2nd Semester" <?php echo (($settings['semester'] ?? '') === '2nd Semester') ? 'selected' : ''; ?>>2nd Semester</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Application Start Date *</label>
                                        <input type="date" class="form-control" name="application_start_date"
                                            value="<?php echo htmlspecialchars((string) ($settings['application_start_date'] ?? '')); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Application End Date *</label>
                                        <input type="date" class="form-control" name="application_end_date"
                                            value="<?php echo htmlspecialchars((string) ($settings['application_end_date'] ?? '')); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">SOA Deadline Policy</label>
                                        <select class="form-select" name="soa_deadline_mode" id="soa_deadline_mode">
                                            <option value="Manual" <?php echo (($settings['soa_deadline_mode'] ?? 'Manual') === 'Manual') ? 'selected' : ''; ?>>Manual fixed date</option>
                                            <option value="AfterPassed" <?php echo (($settings['soa_deadline_mode'] ?? '') === 'AfterPassed') ? 'selected' : ''; ?>>Days after passed interview</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 d-none" id="soa_deadline_days_group">
                                        <label class="form-label fw-bold">Days After Passed Interview</label>
                                        <input type="number" class="form-control" name="soa_deadline_days_after_passed" id="soa_deadline_days_after_passed"
                                            min="1" max="60" step="1"
                                            value="<?php echo htmlspecialchars((string) ($settings['soa_deadline_days_after_passed'] ?? '7')); ?>">
                                    </div>
                                    <div class="col-md-6" id="soa_deadline_manual_group">
                                        <label class="form-label fw-bold">Global SOA Deadline</label>
                                        <input type="date" class="form-control" name="soa_deadline" id="soa_deadline"
                                            value="<?php echo htmlspecialchars((string) ($settings['soa_deadline'] ?? '')); ?>">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_open" name="is_open" value="1" <?php echo ((int) ($settings['is_open'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="is_open">Open student submissions right away</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="app-actions-row mt-4">
                                    <button type="submit" class="btn btn-primary fw-bold px-4">
                                        Open New Period
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</div>

<?php if ($hasConfiguredPeriod): ?>
    <div class="modal fade" id="extendDeadlineModal" tabindex="-1" aria-labelledby="extendDeadlineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?php echo htmlspecialchars(base_url('admin/settings')); ?>" method="POST" id="extend-period-form">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action_mode" value="extend_current">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="extendDeadlineModalLabel">Extend Current Submission Deadline</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current End Date</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars((string) ($settings['application_end_date'] ?? 'Not configured')); ?>" readonly>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold">New End Date *</label>
                            <input type="date" class="form-control" name="extended_application_end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="closeSubmissionsModal" tabindex="-1" aria-labelledby="closeSubmissionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?php echo htmlspecialchars(base_url('admin/settings')); ?>" method="POST" id="close-period-form">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action_mode" value="close_submissions">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="closeSubmissionsModalLabel">Close Student Submissions</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">This will stop new student applications for the current period.</p>
                        <div class="alert alert-secondary border-0 mb-0">
                            Staff and admin processing will continue after submissions are closed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const soaDeadlineModeInput = document.getElementById('soa_deadline_mode');
    const soaDeadlineManualGroup = document.getElementById('soa_deadline_manual_group');
    const soaDeadlineDaysGroup = document.getElementById('soa_deadline_days_group');
    const soaDeadlineInput = document.getElementById('soa_deadline');
    const soaDeadlineDaysInput = document.getElementById('soa_deadline_days_after_passed');

    const syncSoaDeadlineMode = function () {
        if (!soaDeadlineModeInput || !soaDeadlineManualGroup || !soaDeadlineDaysGroup) {
            return;
        }

        const isManual = soaDeadlineModeInput.value === 'Manual';
        const isAfterPassed = soaDeadlineModeInput.value === 'AfterPassed';
        soaDeadlineManualGroup.classList.toggle('d-none', !isManual);
        soaDeadlineDaysGroup.classList.toggle('d-none', !isAfterPassed);
        if (soaDeadlineInput) {
            if (!isManual) {
                soaDeadlineInput.value = '';
            }
        }
        if (soaDeadlineDaysInput) {
            soaDeadlineDaysInput.required = isAfterPassed;
            if (!isAfterPassed) {
                soaDeadlineDaysInput.value = '';
            } else if (!soaDeadlineDaysInput.value) {
                soaDeadlineDaysInput.value = '7';
            }
        }
    };

    soaDeadlineModeInput?.addEventListener('change', syncSoaDeadlineMode);
    syncSoaDeadlineMode();

    document.getElementById('open-new-period-form')?.addEventListener('submit', function (event) {
        const confirmed = window.confirm(
            'Open this as the new application period?\n\n' +
            'This will replace the current period and archive completed records from older terms.'
        );
        if (!confirmed) {
            event.preventDefault();
        }
    });

    document.getElementById('extend-period-form')?.addEventListener('submit', function (event) {
        const confirmed = window.confirm('Extend the submission deadline for the current application period?');
        if (!confirmed) {
            event.preventDefault();
        }
    });

    document.getElementById('close-period-form')?.addEventListener('submit', function (event) {
        const confirmed = window.confirm('Close student submissions for the current application period?');
        if (!confirmed) {
            event.preventDefault();
        }
    });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
