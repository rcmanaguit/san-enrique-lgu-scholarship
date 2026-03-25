<?php
require __DIR__ . '/../layouts/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ' . base_url('login'));
    exit;
}

$hasPendingPayouts = !empty($pendingPayouts);
$snapshotReadyCount = (int) ($totalReadyPayoutCount ?? count($pendingPayouts));
$scheduledBatchCount = (int) count($payoutBatches ?? []);
$scheduledTotalAmount = array_reduce($payoutBatches ?? [], static function ($carry, $batch) {
    return $carry + (float) ($batch['total_amount'] ?? 0);
}, 0.0);
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Final Approval</span>
                    <h1 class="app-page-title">Payout Batches</h1>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-body">
                    <div class="app-batch-hero">
                            <div class="app-batch-hero-copy">
                                <div class="app-stat-kicker">Payout Snapshot</div>
                                <h2 class="app-surface-title mb-2">Set the schedule and amount for each payout batch.</h2>
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
                                    <div class="app-mini-stat-label">Scheduled Amount</div>
                                    <div class="app-mini-stat-value">₱<?php echo number_format($scheduledTotalAmount, 0); ?></div>
                                </div>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($hasPendingPayouts): ?>
                <section class="app-surface mb-4">
                    <div class="app-surface-header">
                        <div>
                            <h2 class="app-surface-title">Create Batch</h2>
                        </div>
                    </div>
                    <div class="app-surface-body">
                        <form method="GET" action="<?php echo htmlspecialchars(base_url('admin/final-approval')); ?>" class="row g-3 align-items-end" data-live-submit data-live-submit-delay="250">
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
                                <div class="fw-semibold"><?php echo (int) count($pendingPayouts); ?> ready now</div>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($payoutFilterSummary ?? 'All approved scholars')); ?></div>
                            </div>
                            <div class="col-md-2">
                                <a href="<?php echo htmlspecialchars(base_url('admin/final-approval')); ?>" class="btn btn-outline-secondary w-100">Clear</a>
                            </div>
                        </form>

                        <?php if (!empty($activePayoutFilters)): ?>
                            <div class="app-active-filter-chips mt-3">
                                <?php foreach ($activePayoutFilters as $filterChip): ?>
                                    <span class="app-active-filter-chip is-static">
                                        <span><?php echo htmlspecialchars((string) $filterChip['label']); ?>: <?php echo htmlspecialchars((string) $filterChip['value']); ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="app-batch-plan-card mt-4">
                            <div>
                                <div class="app-stat-kicker">Batch Preview</div>
                                <div class="fw-bold text-dark mb-1"><?php echo (int) count($pendingPayouts); ?> scholar<?php echo count($pendingPayouts) === 1 ? '' : 's'; ?> will be included</div>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($payoutFilterSummary ?? 'Current matching scholars')); ?></div>
                            </div>
                            <div class="app-meta-chips">
                                <?php foreach (array_slice($pendingBySchoolType ?? [], 0, 3, true) as $label => $count): ?>
                                    <span class="app-meta-chip"><?php echo htmlspecialchars((string) $label); ?>: <?php echo (int) $count; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <form action="<?php echo htmlspecialchars(base_url('admin/create-payout-batch')); ?>" method="POST" id="payoutForm" class="row g-3 align-items-end mt-1">
                            <input type="hidden" name="school_type" value="<?php echo htmlspecialchars($schoolTypeFilter ?? ''); ?>">
                            <input type="hidden" name="barangay" value="<?php echo htmlspecialchars($barangayFilter ?? ''); ?>">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Batch Name</label>
                                <?php $payoutBatchName = implode(' - ', array_filter([
                                    'Payout',
                                    !empty($schoolTypeFilter) ? (string) $schoolTypeFilter : null,
                                    !empty($barangayFilter) ? (string) $barangayFilter : null,
                                ])); ?>
                                <input type="hidden" name="batch_name" id="payoutBatchName" value="<?php echo htmlspecialchars($payoutBatchName); ?>">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($payoutBatchName); ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Date & Time</label>
                                <input type="datetime-local" class="form-control" name="scheduled_date" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Venue</label>
                                <input type="text" class="form-control" name="venue" value="Municipal Treasurer's Office" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Amount Per Scholar</label>
                                <input type="number" class="form-control" name="amount_per_scholar" min="0" step="0.01" placeholder="0.00" required>
                            </div>
                            <div class="col-md-10"></div>
                            <div class="col-md-2">
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
                            <a href="<?php echo htmlspecialchars(base_url('admin/final-approval/export') . '?scope=current&format=excel'); ?>" class="btn btn-sm btn-outline-success">Export List</a>
                            <a href="<?php echo htmlspecialchars(base_url('admin/final-approval/export') . '?scope=current&format=print'); ?>" target="_blank" class="btn btn-sm btn-outline-dark">Print List</a>
                            <a href="<?php echo htmlspecialchars(base_url('admin/final-approval/export') . '?scope=approved_pending_payroll&format=print'); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Approval List</a>
                        </div>
                    </div>
                    <div class="p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Scholar</th>
                                        <th>Barangay</th>
                                        <th>School</th>
                                        <th>School Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingPayouts as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $student['last_name'] . ', ' . (string) $student['first_name']); ?></td>
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
            <?php else: ?>
                <section class="app-surface mb-4">
                    <div class="app-surface-header">
                        <div>
                            <h2 class="app-surface-title">Payout Scheduling</h2>
                        </div>
                    </div>
                    <div class="app-surface-body">
                        <div class="app-empty-state py-4">
                            <div class="app-empty-state-icon"><i class="fa-solid fa-wallet"></i></div>
                            <h3 class="app-empty-state-title">No scholars are ready for payout scheduling</h3>
                            <p class="app-empty-state-copy">The payout schedule form will appear here once there are approved scholars waiting for payroll scheduling.</p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="app-surface">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Scheduled Batches</h2>
                    </div>
                    <span class="app-pill-badge"><i class="fa-solid fa-wallet"></i><?php echo $scheduledBatchCount; ?></span>
                </div>
                <div class="app-surface-body">
                    <div class="app-list-stack">
                        <?php if (empty($payoutBatches)): ?>
                            <div class="app-empty-state">
                                <div class="app-empty-state-icon"><i class="fa-regular fa-calendar-plus"></i></div>
                                <h3 class="app-empty-state-title">No payout batches yet</h3>
                                <p class="app-empty-state-copy">Create the first payout batch once approved scholars are ready for scheduling.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($payoutBatches as $batch): ?>
                                <?php
                                $batchId = (int) ($batch['id'] ?? 0);
                                $summary = $batchSummaries[$batchId] ?? ['barangays' => [], 'school_types' => [], 'total_amount' => 0.0];
                                $participants = $batchParticipants[$batchId] ?? [];
                                ?>
                                <details class="app-batch-card" <?php echo $batchId === (int) (($payoutBatches[0]['id'] ?? 0)) ? 'open' : ''; ?>>
                                    <summary class="app-batch-card-summary">
                                        <div>
                                            <h3 class="app-list-item-title"><?php echo htmlspecialchars((string) $batch['batch_name']); ?></h3>
                                            <p class="app-list-item-meta">
                                                <?php echo date('M d, Y - h:i A', strtotime((string) $batch['scheduled_date'])); ?> |
                                                <?php echo htmlspecialchars((string) $batch['venue']); ?>
                                            </p>
                                        </div>
                                        <div class="app-batch-summary-right">
                                            <div class="fw-bold text-success">₱ <?php echo number_format((float) ($batch['total_amount'] ?? 0), 2); ?></div>
                                            <span class="app-pill-badge"><?php echo (int) ($batch['total_students'] ?? 0); ?> Scholars</span>
                                        </div>
                                    </summary>

                                    <div class="app-batch-card-body">
                                    <div class="app-actions-row">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reschedulePayoutBatchModal<?php echo $batchId; ?>">
                                            Reschedule
                                        </button>
                                        <a href="<?php echo htmlspecialchars(base_url('admin/final-approval/export') . '?scope=batch&batch_id=' . urlencode((string) $batchId) . '&format=excel'); ?>" class="btn btn-sm btn-outline-success">Export List</a>
                                        <a href="<?php echo htmlspecialchars(base_url('admin/final-approval/export') . '?scope=batch&batch_id=' . urlencode((string) $batchId) . '&format=print'); ?>" target="_blank" class="btn btn-sm btn-outline-dark">Print List</a>
                                        <a href="<?php echo htmlspecialchars(base_url('admin/final-approval/export') . '?scope=batch_signature&batch_id=' . urlencode((string) $batchId) . '&format=print'); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Signatures</a>
                                        <a href="<?php echo htmlspecialchars(base_url('admin/generate-payroll') . '?batch_id=' . urlencode((string) $batchId)); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Master Payroll</a>
                                    </div>

                                        <?php if ($participants !== []): ?>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 90px;">Payout No.</th>
                                                            <th>Scholar</th>
                                                            <th>School</th>
                                                            <th>Barangay</th>
                                                            <th class="text-end">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($participants as $participant): ?>
                                                            <tr>
                                                                <td class="fw-semibold"><?php echo (int) ($participant['payout_no'] ?? 0); ?></td>
                                                                <td>
                                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars((string) ($participant['scholar_name'] ?? '')); ?></div>
                                                                </td>
                                                                <td class="small text-muted">
                                                                    <div><?php echo htmlspecialchars((string) ($participant['school_name'] ?? '')); ?></div>
                                                                    <div><?php echo htmlspecialchars((string) ($participant['school_type'] ?? '')); ?></div>
                                                                </td>
                                                                <td class="small text-muted"><?php echo htmlspecialchars((string) ($participant['barangay'] ?? '')); ?></td>
                                                                <td class="text-end fw-semibold text-success">₱ <?php echo number_format((float) ($participant['final_grant_amount'] ?? 0), 2); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>

                                <div class="modal fade" id="reschedulePayoutBatchModal<?php echo $batchId; ?>" tabindex="-1" aria-labelledby="reschedulePayoutBatchModalLabel<?php echo $batchId; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form action="<?php echo htmlspecialchars(base_url('admin/reschedule-payout-batch')); ?>" method="POST">
                                                <input type="hidden" name="batch_id" value="<?php echo $batchId; ?>">
                                                <div class="modal-header">
                                                    <h3 class="modal-title fs-5" id="reschedulePayoutBatchModalLabel<?php echo $batchId; ?>">Reschedule Payout Batch</h3>
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
                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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
        const payoutForm = document.getElementById('payoutForm');
        const batchNameInput = document.getElementById('payoutBatchName');
        const batchNamePreview = batchNameInput ? batchNameInput.nextElementSibling : null;
        const schoolTypeSelect = document.querySelector('form[action="<?php echo htmlspecialchars(base_url('admin/final-approval')); ?>"] select[name="school_type"]');
        const barangaySelect = document.querySelector('form[action="<?php echo htmlspecialchars(base_url('admin/final-approval')); ?>"] select[name="barangay"]');

        function defaultBatchName() {
            const parts = ['Payout'];
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

        if (!payoutForm) {
            return;
        }

        payoutForm.addEventListener('submit', function (e) {
            const schoolType = payoutForm.querySelector('input[name="school_type"]').value.trim();
            const barangay = payoutForm.querySelector('input[name="barangay"]').value.trim();
            const matchingCount = <?php echo (int) count($pendingPayouts); ?>;

            if (schoolType === '' && barangay === '') {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Choose a Filter First',
                    text: 'Select at least one school type or barangay before creating the payout batch.',
                    confirmButtonColor: '#004085'
                });
                return;
            }

            if (matchingCount === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'No Matching Scholars',
                    text: 'Adjust the filter first so the schedule has scholars to include.',
                    confirmButtonColor: '#004085'
                });
            }
        });
    });
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
