<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Accountability</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-clipboard-list me-2"></i>Audit Logs</h1>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Filter Logs</h2>
                    </div>
                </div>
                <div class="app-surface-body">
                    <form method="GET" action="<?php echo htmlspecialchars(base_url('admin/audit-logs')); ?>" class="row g-3 align-items-end" data-live-submit data-live-submit-delay="250">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Actor Role</label>
                            <select name="actor_role" class="form-select">
                                <option value="">All roles</option>
                                <?php foreach ($actorRoleOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filters['actor_role'] === $option) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Action</label>
                            <select name="action" class="form-select">
                                <option value="">All actions</option>
                                <?php foreach ($actionOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filters['action'] === $option) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Entity Type</label>
                            <select name="entity_type" class="form-select">
                                <option value="">All entity types</option>
                                <?php foreach ($entityTypeOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filters['entity_type'] === $option) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <a href="<?php echo htmlspecialchars(base_url('admin/audit-logs')); ?>" class="btn btn-outline-secondary w-100">Clear</a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="app-surface app-table-card">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Latest System Activity</h2>
                    </div>
                    <span class="app-pill-badge"><i class="fa-solid fa-clock-rotate-left"></i><?php echo count($logs); ?> Records</span>
                </div>
                <div class="p-0">
                    <?php if ($logs === []): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-state-icon"><i class="fa-regular fa-clipboard"></i></div>
                            <h3 class="app-empty-state-title">No audit logs found</h3>
                            <p class="app-empty-state-copy">Try clearing a filter or perform an action in the system to generate new logs.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Actor</th>
                                        <th>Action</th>
                                        <th>Entity</th>
                                        <th>Description</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <?php $metadata = json_decode((string) ($log['metadata_json'] ?? ''), true); ?>
                                        <tr>
                                            <td class="small text-muted"><?php echo htmlspecialchars((string) ($log['created_at'] ?? '')); ?></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars((string) ($log['actor_role'] ?? 'System')); ?></div>
                                                <div class="small text-muted">
                                                    <?php
                                                    $actorBits = array_filter([
                                                        (string) ($log['phone_number'] ?? ''),
                                                        (string) ($log['email'] ?? ''),
                                                    ]);
                                                    echo htmlspecialchars($actorBits !== [] ? implode(' | ', $actorBits) : 'System / guest action');
                                                    ?>
                                                </div>
                                            </td>
                                            <td><code><?php echo htmlspecialchars((string) ($log['action'] ?? '')); ?></code></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($log['entity_type'] ?? '')); ?></div>
                                                <div class="small text-muted">ID: <?php echo htmlspecialchars((string) ($log['entity_id'] ?? 'N/A')); ?></div>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars((string) ($log['description'] ?? '')); ?></div>
                                                <?php if (is_array($metadata) && $metadata !== []): ?>
                                                    <div class="small text-muted mt-1"><?php echo htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <div><?php echo htmlspecialchars((string) ($log['ip_address'] ?? 'N/A')); ?></div>
                                                <div><?php echo htmlspecialchars((string) ($log['user_agent'] ?? '')); ?></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
