<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <h1 class="app-page-title"><i class="fa-solid fa-magnifying-glass me-2"></i>Global Search</h1>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-body">
                    <form method="GET" action="<?php echo htmlspecialchars(base_url('search')); ?>" data-live-submit data-live-submit-delay="300">
                        <div class="input-group app-search-input">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-search"></i></span>
                            <input
                                type="search"
                                name="q"
                                class="form-control"
                                value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search master records by name, school, barangay, phone, or email"
                                autofocus
                            >
                            <a href="<?php echo htmlspecialchars(base_url('search')); ?>" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </section>

            <?php if ($search === ''): ?>
                    <section class="app-surface">
                        <div class="app-empty-state">
                            <div class="app-empty-state-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                            <h3 class="app-empty-state-title">Start typing to search the system</h3>
                            <p class="app-empty-state-copy">Search scholar and applicant master records from one page.</p>
                        </div>
                    </section>
            <?php else: ?>
                <section class="app-surface app-table-card">
                    <div class="app-surface-header">
                        <div>
                            <h2 class="app-surface-title">Master Record Results</h2>
                        </div>
                        <span class="app-pill-badge"><i class="fa-solid fa-address-card"></i><?php echo count($masterRecordResults); ?> Found</span>
                    </div>
                    <div class="p-0">
                        <?php if ($masterRecordResults === []): ?>
                            <div class="app-empty-state">
                                <div class="app-empty-state-icon"><i class="fa-regular fa-address-card"></i></div>
                                <h3 class="app-empty-state-title">No master records found</h3>
                                <p class="app-empty-state-copy">Try a broader search term for the scholar or applicant.</p>
                            </div>
                        <?php else: ?>
                            <div class="d-md-none app-mobile-record-grid">
                                <?php foreach ($masterRecordResults as $scholar): ?>
                                    <article class="app-mobile-record-card">
                                        <div class="app-list-item-head">
                                            <div>
                                                <h3 class="app-list-item-title mb-1"><?php echo htmlspecialchars((string) (($scholar['last_name'] ?? '') . ', ' . ($scholar['first_name'] ?? ''))); ?></h3>
                                                <div class="app-list-item-meta"><?php echo htmlspecialchars((string) ($scholar['school_name'] ?? '')); ?></div>
                                                <div class="app-list-item-meta"><?php echo htmlspecialchars((string) ($scholar['address_barangay'] ?? '')); ?></div>
                                            </div>
                                            <a href="<?php echo htmlspecialchars(base_url('staff/master-record/' . (int) ($scholar['profile_id'] ?? 0))); ?>" class="btn btn-sm btn-outline-primary">
                                                Open
                                            </a>
                                        </div>
                                        <div class="app-mobile-record-section">
                                            <span class="app-mobile-record-label">Contact</span>
                                            <div class="app-mobile-record-value"><?php echo htmlspecialchars((string) ($scholar['phone_number'] ?? '')); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars((string) ($scholar['email'] ?? '')); ?></div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <div class="table-responsive d-none d-md-block">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Scholar</th>
                                            <th>Contact</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($masterRecordResults as $scholar): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars((string) (($scholar['last_name'] ?? '') . ', ' . ($scholar['first_name'] ?? ''))); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($scholar['school_name'] ?? '')); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($scholar['address_barangay'] ?? '')); ?></div>
                                                </td>
                                                <td class="small">
                                                    <div><?php echo htmlspecialchars((string) ($scholar['phone_number'] ?? '')); ?></div>
                                                    <div class="text-muted"><?php echo htmlspecialchars((string) ($scholar['email'] ?? '')); ?></div>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?php echo htmlspecialchars(base_url('staff/master-record/' . (int) ($scholar['profile_id'] ?? 0))); ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
