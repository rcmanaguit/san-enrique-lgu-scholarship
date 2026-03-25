<?php require __DIR__ . '/../layouts/header.php'; ?>

<?php
$overdueCount = (int) count($overdueSoa ?? []);
$pendingInterviewCount = (int) count($pendingInterviewResults ?? []);
$rejectedDocumentCount = (int) count($rejectedDocuments ?? []);
$inactiveAccountCount = (int) count($inactiveUsers ?? []);
$totalExceptions = $overdueCount + $pendingInterviewCount + $rejectedDocumentCount + $inactiveAccountCount;
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Monitoring</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-triangle-exclamation me-2"></i>Needs Attention</h1>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-body">
                    <div class="app-batch-hero">
                        <div class="app-batch-hero-copy">
                            <div class="app-stat-kicker">Snapshot</div>
                            <h2 class="app-surface-title mb-2">See what needs attention first.</h2>
                        </div>
                        <div class="app-batch-hero-stats">
                            <div class="app-info-card">
                                <div class="app-mini-stat-label">Overdue SOA</div>
                                <div class="app-mini-stat-value"><?php echo $overdueCount; ?></div>
                            </div>
                            <div class="app-info-card">
                                <div class="app-mini-stat-label">Missing Results</div>
                                <div class="app-mini-stat-value"><?php echo $pendingInterviewCount; ?></div>
                            </div>
                            <div class="app-info-card">
                                <div class="app-mini-stat-label">Rejected Docs</div>
                                <div class="app-mini-stat-value"><?php echo $rejectedDocumentCount; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-xl-6">
                    <section class="app-surface app-table-card h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Overdue SOA</h2>
                            </div>
                            <span class="app-pill-badge"><i class="fa-solid fa-hourglass-end"></i><?php echo $overdueCount; ?></span>
                        </div>
                        <div class="p-0">
                            <?php if ($overdueSoa === []): ?>
                                <div class="app-empty-state">
                                    <div class="app-empty-state-icon"><i class="fa-solid fa-circle-check"></i></div>
                                    <h3 class="app-empty-state-title">No overdue SOA submissions</h3>
                                    <p class="app-empty-state-copy">All qualified applicants are currently within the deadline or already submitted.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead><tr><th>Applicant</th><th>School</th><th>SOA Deadline</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($overdueSoa as $row): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars((string) $row['applicant_name']); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars((string) $row['address_barangay']); ?></div>
                                                    </td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars((string) $row['school_name']); ?></td>
                                                    <td class="small text-danger fw-semibold"><?php echo htmlspecialchars((string) ($row['resolved_soa_deadline'] ?? '')); ?></td>
                                                    <td><span class="app-status-badge app-status-correction"><?php echo htmlspecialchars(str_replace('_', ' ', (string) $row['status'])); ?></span></td>
                                                    <td class="text-end">
                                                        <a href="<?php echo htmlspecialchars(base_url('staff/verify-documents/' . (int) ($row['application_id'] ?? 0))); ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="col-xl-6">
                    <section class="app-surface app-table-card h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Missing Interview Results</h2>
                            </div>
                            <span class="app-pill-badge"><i class="fa-solid fa-comments"></i><?php echo $pendingInterviewCount; ?></span>
                        </div>
                        <div class="p-0">
                            <?php if ($pendingInterviewResults === []): ?>
                                <div class="app-empty-state">
                                    <div class="app-empty-state-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                                    <h3 class="app-empty-state-title">No pending interview results</h3>
                                    <p class="app-empty-state-copy">All scheduled interviews currently have a recorded outcome.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead><tr><th>Applicant</th><th>Batch</th><th>Schedule</th><th class="text-end">Action</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($pendingInterviewResults as $row): ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo htmlspecialchars((string) $row['applicant_name']); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars((string) $row['batch_name']); ?></td>
                                                    <td class="small text-muted">
                                                        <div><?php echo htmlspecialchars((string) $row['scheduled_date']); ?></div>
                                                        <div><?php echo htmlspecialchars((string) ($row['venue'] ?? '')); ?></div>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="<?php echo htmlspecialchars(base_url('admin/batch-interview')); ?>" class="btn btn-sm btn-outline-primary">Batches</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="col-xl-6">
                    <section class="app-surface app-table-card h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Returned Documents</h2>
                            </div>
                            <span class="app-pill-badge"><i class="fa-solid fa-file-circle-xmark"></i><?php echo $rejectedDocumentCount; ?></span>
                        </div>
                        <div class="p-0">
                            <?php if ($rejectedDocuments === []): ?>
                                <div class="app-empty-state">
                                    <div class="app-empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                                    <h3 class="app-empty-state-title">No rejected documents found</h3>
                                    <p class="app-empty-state-copy">There are no outstanding rejected documents waiting for correction.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead><tr><th>Applicant</th><th>Document</th><th>Remarks</th><th>Updated</th><th class="text-end">Action</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($rejectedDocuments as $row): ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo htmlspecialchars((string) $row['applicant_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(document_type_label((string) $row['document_type'])); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars((string) $row['rejection_remarks']); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars((string) ($row['updated_at'] ?? '')); ?></td>
                                                    <td class="text-end">
                                                        <a href="<?php echo htmlspecialchars(base_url('staff/verify-documents/' . (int) ($row['application_id'] ?? 0))); ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="col-xl-6">
                    <section class="app-surface app-table-card h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Inactive Accounts</h2>
                            </div>
                            <span class="app-pill-badge"><i class="fa-solid fa-user-slash"></i><?php echo $inactiveAccountCount; ?></span>
                        </div>
                        <div class="p-0">
                            <?php if ($inactiveUsers === []): ?>
                                <div class="app-empty-state">
                                    <div class="app-empty-state-icon"><i class="fa-solid fa-user-check"></i></div>
                                    <h3 class="app-empty-state-title">No inactive accounts found</h3>
                                    <p class="app-empty-state-copy">All internal user accounts are currently active.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead><tr><th>Role</th><th>Mobile</th><th>Email</th><th>Disabled Since</th><th class="text-end">Action</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($inactiveUsers as $row): ?>
                                                <tr>
                                                    <td><span class="app-status-badge app-status-default"><?php echo htmlspecialchars((string) $row['role']); ?></span></td>
                                                    <td><?php echo htmlspecialchars((string) $row['phone_number']); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars((string) $row['email']); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars((string) ($row['created_at'] ?? '')); ?></td>
                                                    <td class="text-end">
                                                        <a href="<?php echo htmlspecialchars(base_url('admin/users')); ?>" class="btn btn-sm btn-outline-primary">Users</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
