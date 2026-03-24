<?php
require __DIR__ . '/../layouts/header.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Staff', 'Admin'])) {
    header('Location: ' . base_url('login'));
    exit;
}

$hasInterviewCandidates = !empty($unassignedStudents);
$readyCount = (int) count($unassignedStudents);
$snapshotReadyCount = (int) ($totalReadyInterviewCount ?? $readyCount);
$scheduledBatchCount = (int) count($upcomingBatches ?? []);
$totalScheduledApplicants = 0;
foreach (($upcomingBatches ?? []) as $batch) {
    $totalScheduledApplicants += (int) ($batch['total_students'] ?? 0);
}
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Interviews</span>
                    <h1 class="app-page-title">Interview Batches</h1>
                    <p class="app-page-subtitle"><?php echo htmlspecialchars((string) ($currentPeriodLabel ?? 'No current application period configured')); ?></p>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-body">
                    <div class="app-batch-hero">
                        <div class="app-batch-hero-copy">
                            <div class="app-stat-kicker">Interview Snapshot</div>
                            <h2 class="app-surface-title mb-2">Schedule interviews and save results here.</h2>
                        </div>
                        <div class="app-batch-hero-stats">
                            <div class="app-info-card">
                                <div class="app-mini-stat-label">Ready Now</div>
                                <div class="app-mini-stat-value"><?php echo $snapshotReadyCount; ?></div>
                            </div>
                            <div class="app-info-card">
                                <div class="app-mini-stat-label">Scheduled Batches</div>
                                <div class="app-mini-stat-value"><?php echo $scheduledBatchCount; ?></div>
                            </div>
                            <div class="app-info-card">
                                <div class="app-mini-stat-label">Scheduled Applicants</div>
                                <div class="app-mini-stat-value"><?php echo $totalScheduledApplicants; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($hasInterviewCandidates && !empty($canManageInterviewSchedule)): ?>
                <?php $interviewBatchName = implode(' - ', array_filter([
                    'Interview',
                    !empty($schoolTypeFilter) ? (string) $schoolTypeFilter : null,
                    !empty($barangayFilter) ? (string) $barangayFilter : null,
                ])); ?>
                <section class="app-surface mb-4">
                    <div class="app-surface-header">
                        <div>
                            <h2 class="app-surface-title">Create Batch</h2>
                        </div>
                    </div>
                    <div class="app-surface-body">
                        <form method="GET" action="<?php echo htmlspecialchars(base_url('admin/batch-interview')); ?>" class="row g-3 align-items-end" data-live-submit data-live-submit-delay="250">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">School Type</label>
                                <select name="school_type" class="form-select">
                                    <option value="">All school types</option>
                                    <?php foreach (($schoolTypeOptions ?? []) as $schoolTypeOption): ?>
                                        <option value="<?php echo htmlspecialchars((string) $schoolTypeOption); ?>" <?php echo (($schoolTypeFilter ?? '') === (string) $schoolTypeOption) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $schoolTypeOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Barangay</label>
                                <select name="barangay" class="form-select">
                                    <option value="">All barangays</option>
                                    <?php foreach (($barangayOptions ?? []) as $barangayOption): ?>
                                        <option value="<?php echo htmlspecialchars((string) $barangayOption); ?>" <?php echo (($barangayFilter ?? '') === (string) $barangayOption) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $barangayOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="fw-semibold"><?php echo $readyCount; ?> ready to schedule</div>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($interviewFilterSummary ?? 'All interview-ready applicants')); ?></div>
                            </div>
                            <div class="col-md-2">
                                <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview')); ?>" class="btn btn-outline-secondary w-100">Clear</a>
                            </div>
                        </form>

                        <?php if (!empty($activeInterviewFilters)): ?>
                            <div class="app-active-filter-chips mt-3">
                                <?php foreach ($activeInterviewFilters as $filterChip): ?>
                                    <span class="app-active-filter-chip is-static">
                                        <span><?php echo htmlspecialchars((string) $filterChip['label']); ?>: <?php echo htmlspecialchars((string) $filterChip['value']); ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="app-batch-plan-card mt-4">
                            <div>
                                <div class="app-stat-kicker">Batch Preview</div>
                                <div class="fw-bold text-dark mb-1"><?php echo $readyCount; ?> applicant<?php echo $readyCount === 1 ? '' : 's'; ?> will be included</div>
                                <div class="small text-muted">This batch will include <?php echo htmlspecialchars((string) ($interviewFilterSummary ?? 'the current matching applicants')); ?>.</div>
                            </div>
                            <div class="app-meta-chips">
                                <?php foreach (array_slice($unassignedBySchoolType ?? [], 0, 3, true) as $label => $count): ?>
                                    <span class="app-meta-chip"><?php echo htmlspecialchars((string) $label); ?>: <?php echo (int) $count; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <form action="<?php echo htmlspecialchars(base_url('admin/create-batch')); ?>" method="POST" id="batchForm" class="row g-3 align-items-end mt-1">
                            <input type="hidden" name="school_type" value="<?php echo htmlspecialchars($schoolTypeFilter ?? ''); ?>">
                            <input type="hidden" name="barangay" value="<?php echo htmlspecialchars($barangayFilter ?? ''); ?>">
                            <div class="col-lg-4">
                                <label class="form-label fw-bold">Batch Name</label>
                                <input type="hidden" name="batch_name" id="interviewBatchName" value="<?php echo htmlspecialchars($interviewBatchName); ?>">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($interviewBatchName); ?>" readonly>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label fw-bold">Date & Time</label>
                                <input type="datetime-local" class="form-control" name="scheduled_date" required>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label fw-bold">Venue</label>
                                <input type="text" class="form-control" name="venue" value="Municipal Hall, 2nd Floor" required>
                            </div>
                            <div class="col-lg-2">
                                <button type="submit" class="btn btn-primary w-100">Create Batch</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="app-surface mb-4">
                    <div class="app-surface-header">
                        <div>
                            <h2 class="app-surface-title">Ready to Schedule</h2>
                        </div>
                        <div class="app-page-header-actions">
                            <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview/export') . '?scope=current&format=excel'); ?>" class="btn btn-sm btn-outline-success">Export List</a>
                            <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview/export') . '?scope=current&format=print'); ?>" target="_blank" class="btn btn-sm btn-outline-dark">Print List</a>
                        </div>
                    </div>
                    <div class="p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Applicant</th>
                                        <th>Barangay</th>
                                        <th>School</th>
                                        <th>School Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassignedStudents as $student): ?>
                                        <tr>
                                            <td><div class="fw-bold text-dark"><?php echo htmlspecialchars((string) $student['last_name'] . ', ' . (string) $student['first_name']); ?></div></td>
                                            <td><?php echo htmlspecialchars((string) $student['address_barangay']); ?></td>
                                            <td><?php echo htmlspecialchars((string) $student['school_name']); ?></td>
                                            <td><?php echo htmlspecialchars((string) $student['school_type']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php elseif ($hasInterviewCandidates): ?>
                <section class="app-surface mb-4">
                    <div class="app-surface-header">
                        <div>
                            <h2 class="app-surface-title">Interview Scheduling</h2>
                        </div>
                    </div>
                    <div class="app-surface-body">
                        <div class="app-empty-state py-4">
                            <div class="app-empty-state-icon"><i class="fa-solid fa-user-shield"></i></div>
                            <h3 class="app-empty-state-title">Scheduling is handled by the coordinator</h3>
                            <p class="app-empty-state-copy">There are <?php echo $readyCount; ?> interview-ready applicant<?php echo $readyCount === 1 ? '' : 's'; ?> waiting for scheduling. Staff can still review scheduled batches and save interview results below.</p>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <section class="app-surface mb-4">
                    <div class="app-surface-header">
                        <div>
                            <h2 class="app-surface-title">Interview Scheduling</h2>
                        </div>
                    </div>
                    <div class="app-surface-body">
                        <div class="app-empty-state py-4">
                            <div class="app-empty-state-icon"><i class="fa-solid fa-check-double"></i></div>
                            <h3 class="app-empty-state-title">No applicants are ready for scheduling</h3>
                            <p class="app-empty-state-copy">New interview-ready applicants will appear here once they reach the interview step and do not yet belong to a batch.</p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="app-surface">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Scheduled Batches</h2>
                    </div>
                    <span class="app-pill-badge"><i class="fa-solid fa-calendar-check"></i><?php echo $scheduledBatchCount; ?></span>
                </div>
                <div class="app-surface-body">
                    <div class="app-list-stack">
                        <?php if (empty($upcomingBatches)): ?>
                            <div class="app-empty-state">
                                <div class="app-empty-state-icon"><i class="fa-regular fa-calendar-xmark"></i></div>
                                <h3 class="app-empty-state-title">No interview batches yet</h3>
                                <p class="app-empty-state-copy">Create the first interview batch once applicants are ready for scheduling.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcomingBatches as $batch): ?>
                                <?php
                                $batchId = (int) ($batch['id'] ?? 0);
                                $participants = $batchParticipants[$batchId] ?? [];
                                $summary = $batchSummaries[$batchId] ?? ['pending' => 0, 'passed' => 0, 'failed' => 0, 'absent' => 0, 'barangays' => [], 'school_types' => []];
                                $batchHasStarted = strtotime((string) ($batch['scheduled_date'] ?? '')) <= time();
                                ?>
                                <details class="app-batch-card" <?php echo $batchId === (int) (($upcomingBatches[0]['id'] ?? 0)) ? 'open' : ''; ?>>
                                    <summary class="app-batch-card-summary">
                                        <div>
                                            <h3 class="app-list-item-title mb-1"><?php echo htmlspecialchars((string) $batch['batch_name']); ?></h3>
                                            <p class="app-list-item-meta mb-0">
                                                <?php echo date('M d, Y - h:i A', strtotime((string) $batch['scheduled_date'])); ?> | <?php echo htmlspecialchars((string) $batch['venue']); ?>
                                            </p>
                                        </div>
                                        <div class="app-batch-summary-right">
                                            <span class="app-pill-badge"><?php echo (int) ($batch['total_students'] ?? 0); ?> Applicants</span>
                                            <span class="app-meta-chip">Pending: <?php echo (int) ($summary['pending'] ?? 0); ?></span>
                                            <span class="app-meta-chip">Passed: <?php echo (int) ($summary['passed'] ?? 0); ?></span>
                                            <span class="app-meta-chip">Failed: <?php echo (int) ($summary['failed'] ?? 0); ?></span>
                                            <span class="app-meta-chip">Absent: <?php echo (int) ($summary['absent'] ?? 0); ?></span>
                                        </div>
                                    </summary>

                                    <div class="app-batch-card-body">
                                        <div class="app-actions-row">
                                            <?php if (!empty($canManageInterviewSchedule)): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rescheduleInterviewBatchModal<?php echo $batchId; ?>">
                                                    Reschedule
                                                </button>
                                        <?php endif; ?>
                                        <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview/export') . '?scope=batch&batch_id=' . urlencode((string) $batchId) . '&format=excel'); ?>" class="btn btn-sm btn-outline-success">Export List</a>
                                        <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview/export') . '?scope=batch&batch_id=' . urlencode((string) $batchId) . '&format=print'); ?>" target="_blank" class="btn btn-sm btn-outline-dark">Print List</a>
                                        <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview/export') . '?scope=batch_results&batch_id=' . urlencode((string) $batchId) . '&format=excel'); ?>" class="btn btn-sm btn-outline-primary">Results</a>
                                        <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview/export') . '?scope=batch_attendance&batch_id=' . urlencode((string) $batchId) . '&format=print'); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Attendance Sheet</a>
                                        </div>

                                        <?php if (!$batchHasStarted): ?>
                                            <div class="app-next-action-panel mt-3">
                                                <div class="app-stat-kicker">Result Entry Locked</div>
                                                <div class="small text-muted">Interview results will be available once this batch reaches its scheduled time.</div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($participants !== []): ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Applicant</th>
                                                        <th>School</th>
                                                        <th style="width: 280px;">Interview Result</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($participants as $participant): ?>
                                                        <?php
                                                        $resultValue = (string) ($participant['interview_result'] ?? '');
                                                        $resultClass = match ($resultValue) {
                                                            'Passed' => 'app-status-badge app-status-complete',
                                                            'Failed' => 'app-status-badge app-status-closed',
                                                            'Absent' => 'app-status-badge app-status-correction',
                                                            default => 'app-status-badge app-status-review',
                                                        };
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars((string) $participant['last_name'] . ', ' . (string) $participant['first_name']); ?></div>
                                                                <div class="small text-muted"><?php echo htmlspecialchars((string) $participant['address_barangay']); ?></div>
                                                            </td>
                                                            <td class="small text-muted">
                                                                <div><?php echo htmlspecialchars((string) $participant['school_name']); ?></div>
                                                                <div><?php echo htmlspecialchars((string) $participant['school_type']); ?></div>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex gap-2 flex-wrap align-items-center">
                                                                    <span class="<?php echo htmlspecialchars($resultClass); ?>">
                                                                        <?php echo htmlspecialchars($resultValue !== '' ? $resultValue : 'Pending'); ?>
                                                                    </span>
                                                                    <?php if ($batchHasStarted): ?>
                                                                        <form action="<?php echo htmlspecialchars(base_url('admin/record-interview-result')); ?>" method="POST" class="d-flex gap-2 flex-wrap app-batch-result-form">
                                                                            <input type="hidden" name="batch_id" value="<?php echo (int) $batch['id']; ?>">
                                                                            <input type="hidden" name="application_id" value="<?php echo (int) $participant['application_id']; ?>">
                                                                            <select name="interview_result" class="form-select form-select-sm" style="min-width: 120px;" required>
                                                                                <option value="">Select</option>
                                                                                <option value="Passed" <?php echo $resultValue === 'Passed' ? 'selected' : ''; ?>>Passed</option>
                                                                                <option value="Failed" <?php echo $resultValue === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                                                                                <option value="Absent" <?php echo $resultValue === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                                                            </select>
                                                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                                        </form>
                                                                    <?php else: ?>
                                                                        <span class="app-meta-chip">Available on scheduled time</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                </details>

                                <?php if (!empty($canManageInterviewSchedule)): ?>
                                    <div class="modal fade" id="rescheduleInterviewBatchModal<?php echo $batchId; ?>" tabindex="-1" aria-labelledby="rescheduleInterviewBatchModalLabel<?php echo $batchId; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form action="<?php echo htmlspecialchars(base_url('admin/reschedule-batch')); ?>" method="POST">
                                                    <input type="hidden" name="batch_id" value="<?php echo $batchId; ?>">
                                                    <div class="modal-header">
                                                        <h3 class="modal-title fs-5" id="rescheduleInterviewBatchModalLabel<?php echo $batchId; ?>">Reschedule Interview Batch</h3>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Batch</label>
                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars((string) $batch['batch_name']); ?>" readonly>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">New Date & Time</label>
                                                            <input type="datetime-local" class="form-control" name="scheduled_date" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $batch['scheduled_date']))); ?>" required>
                                                        </div>
                                                        <div class="mb-0">
                                                            <label class="form-label fw-bold">New Venue</label>
                                                            <input type="text" class="form-control" name="venue" value="<?php echo htmlspecialchars((string) $batch['venue']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const batchForm = document.getElementById('batchForm');
        const batchNameInput = document.getElementById('interviewBatchName');
        const batchNamePreview = batchNameInput ? batchNameInput.nextElementSibling : null;
        const schoolTypeSelect = document.querySelector('form[action="<?php echo htmlspecialchars(base_url('admin/batch-interview')); ?>"] select[name="school_type"]');
        const barangaySelect = document.querySelector('form[action="<?php echo htmlspecialchars(base_url('admin/batch-interview')); ?>"] select[name="barangay"]');

        function defaultBatchName() {
            const parts = ['Interview'];
            if (schoolTypeSelect && schoolTypeSelect.value.trim() !== '') {
                parts.push(schoolTypeSelect.value.trim());
            }
            if (barangaySelect && barangaySelect.value.trim() !== '') {
                parts.push(barangaySelect.value.trim());
            }
            return parts.join(' - ');
        }

        [schoolTypeSelect, barangaySelect].forEach(function (field) {
            if (!field) {
                return;
            }

            field.addEventListener('change', function () {
                if (!batchNameInput) {
                    return;
                }
                const value = defaultBatchName();
                batchNameInput.value = value;
                if (batchNamePreview) {
                    batchNamePreview.value = value;
                }
            });
        });

        if (!batchForm) {
            return;
        }

        batchForm.addEventListener('submit', function (e) {
            const schoolType = batchForm.querySelector('input[name="school_type"]').value.trim();
            const barangay = batchForm.querySelector('input[name="barangay"]').value.trim();
            const matchingCount = <?php echo (int) count($unassignedStudents); ?>;

            if (schoolType === '' && barangay === '') {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Choose a Filter First',
                    text: 'Select at least one school type or barangay before creating the interview batch.',
                    confirmButtonColor: '#004085'
                });
                return;
            }

            if (matchingCount === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'No Matching Applicants',
                    text: 'Adjust the filter first so the schedule has applicants to include.',
                    confirmButtonColor: '#004085'
                });
            }
        });
    });
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
