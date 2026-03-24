<?php
require __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">

        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">

            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Student Record</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-clock-rotate-left me-2"></i>My Applications</h1>
                    <p class="app-page-subtitle">Review your submitted applications, status history, and the total grant assistance you have received so far.</p>
                </div>
            </section>

            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="app-stat-card" style="--stat-accent: var(--lgu-primary);">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Total Grants Received</div>
                                <p class="app-stat-value"><?php echo $totalApproved; ?> Semesters</p>
                                <div class="app-stat-note">Approved scholarship cycles already granted to your account</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-user-graduate"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="app-stat-card" style="--stat-accent: #1f6f43;">
                        <div class="app-stat-card-body">
                            <div>
                                <div class="app-stat-kicker">Total Financial Assistance</div>
                                <p class="app-stat-value">₱ <?php echo number_format($totalAmount, 2); ?></p>
                                <div class="app-stat-note">Combined grant amount from approved and released assistance</div>
                            </div>
                            <span class="app-stat-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <section class="app-surface app-table-card">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Application Timeline</h2>
                        <p class="app-surface-copy">A record of your applications by school year, semester, and grant outcome.</p>
                    </div>
                </div>
                <div class="p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3">School Year / Term</th>
                                    <th class="py-3">School & Course</th>
                                    <th class="py-3">Date Applied</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3 text-end">Grant Amount</th>
                                    <th class="py-3 text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historyData)): ?>
                                    <tr>
                                        <td colspan="6" class="p-0">
                                            <div class="app-empty-state">
                                                <div class="app-empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                                                <h3 class="app-empty-state-title">No past applications found</h3>
                                                <p class="app-empty-state-copy">You can start your first scholarship application from the application page.</p>
                                                <div class="mt-3">
                                                    <a href="<?php echo htmlspecialchars(base_url('student/apply')); ?>" class="btn btn-sm btn-primary">Apply Now</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($historyData as $record): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars((string) $record['school_year']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars((string) $record['semester']); ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars((string) $record['school_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars((string) $record['course']); ?></div>
                                            </td>
                                            <td>
                                                <span class="small"><?php echo date('M d, Y', strtotime((string) $record['created_at'])); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = 'bg-secondary';
                                                $statusText = str_replace('_', ' ', (string) $record['status']);

                                                if (($record['status'] ?? '') === 'Approved_Finished') {
                                                    $badgeClass = 'bg-success';
                                                } elseif (in_array(($record['status'] ?? ''), ['Forfeited', 'Not_Eligible'], true)) {
                                                    $badgeClass = 'bg-danger';
                                                } elseif (($record['status'] ?? '') === 'Approved_Pending_Payroll') {
                                                    $badgeClass = 'bg-primary';
                                                } else {
                                                    $badgeClass = 'bg-warning text-dark';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?> px-2 py-1"><?php echo htmlspecialchars($statusText); ?></span>
                                            </td>
                                            <td class="text-end fw-bold text-success pe-4">
                                                <?php echo ((float) $record['final_grant_amount'] > 0) ? '₱ ' . number_format((float) $record['final_grant_amount'], 2) : '--'; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="<?php echo htmlspecialchars(base_url('student/application/' . (int) ($record['id'] ?? 0))); ?>" class="btn btn-sm btn-outline-primary">View Record</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
