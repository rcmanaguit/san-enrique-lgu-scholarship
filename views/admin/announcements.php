<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Communications</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-bullhorn me-2"></i>Announcements</h1>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-lg-4">
                    <section class="app-surface h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Publish Announcement</h2>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <form action="<?php echo htmlspecialchars(base_url('admin/announcements')); ?>" method="POST">
                                <?php echo csrf_input(); ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Title</label>
                                    <input type="text" class="form-control" name="title" maxlength="180" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Audience</label>
                                    <select class="form-select" name="target_audience" required>
                                        <option value="Both">Public Website and Student Portal</option>
                                        <option value="Public">Public Website Only</option>
                                        <option value="Students">Student Portal Only</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Message</label>
                                    <textarea class="form-control" name="body" rows="6" maxlength="5000" required></textarea>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_published" id="announcementPublished" checked>
                                    <label class="form-check-label" for="announcementPublished">Publish immediately</label>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 fw-bold">Save</button>
                            </form>
                        </div>
                    </section>
                </div>

                <div class="col-lg-8">
                    <section class="app-surface h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Announcement Feed</h2>
                            </div>
                            <span class="app-pill-badge"><i class="fa-solid fa-bullhorn"></i><?php echo count($announcements); ?> Items</span>
                        </div>
                        <div class="p-0">
                            <?php if ($announcements === []): ?>
                                <div class="app-empty-state">
                                    <div class="app-empty-state-icon"><i class="fa-regular fa-message"></i></div>
                                    <h3 class="app-empty-state-title">No announcements yet</h3>
                                    <p class="app-empty-state-copy">Create your first advisory to start communicating with applicants and staff.</p>
                                </div>
                            <?php else: ?>
                                <div class="app-list-stack">
                                    <?php foreach ($announcements as $announcement): ?>
                                        <?php
                                        $audienceLabel = match ((string) ($announcement['target_audience'] ?? 'Both')) {
                                            'Public' => 'Public Website Only',
                                            'Students' => 'Student Portal Only',
                                            'Both', 'All' => 'Public Website and Student Portal',
                                            default => (string) ($announcement['target_audience'] ?? 'Both'),
                                        };
                                        ?>
                                        <article class="app-list-item">
                                            <div class="app-list-item-head mb-2">
                                                <div>
                                                    <h3 class="app-list-item-title"><?php echo htmlspecialchars((string) $announcement['title']); ?></h3>
                                                    <p class="app-list-item-meta">
                                                        Audience: <?php echo htmlspecialchars($audienceLabel); ?> |
                                                        <?php echo !empty($announcement['published_at']) ? htmlspecialchars((string) $announcement['published_at']) : 'Draft'; ?>
                                                    </p>
                                                </div>
                                                <span class="badge <?php echo ((int) ($announcement['is_published'] ?? 0) === 1) ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo ((int) ($announcement['is_published'] ?? 0) === 1) ? 'Published' : 'Draft'; ?>
                                                </span>
                                            </div>
                                            <p class="mb-3 text-muted"><?php echo nl2br(htmlspecialchars((string) $announcement['body'])); ?></p>
                                            <form action="<?php echo htmlspecialchars(base_url('admin/announcements/toggle')); ?>" method="POST" class="d-inline">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="announcement_id" value="<?php echo (int) $announcement['id']; ?>">
                                                <input type="hidden" name="is_published" value="<?php echo ((int) ($announcement['is_published'] ?? 0) === 1) ? '0' : '1'; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <?php echo ((int) ($announcement['is_published'] ?? 0) === 1) ? 'Unpublish' : 'Publish'; ?>
                                                </button>
                                            </form>
                                        </article>
                                    <?php endforeach; ?>
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
