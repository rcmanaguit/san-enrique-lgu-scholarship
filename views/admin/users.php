<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Administration</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-users-gear me-2"></i>User Management</h1>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-lg-4">
                    <section class="app-surface h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Create Internal Account</h2>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <form action="<?php echo htmlspecialchars(base_url('admin/users/create')); ?>" method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">First Name</label>
                                        <input type="text" class="form-control" name="first_name" maxlength="100" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" maxlength="100" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Role</label>
                                    <select class="form-select" name="role" required>
                                        <option value="Staff">Staff</option>
                                        <option value="Admin">Admin</option>
                                    </select>
                                </div>
                                <div class="mb-3 mt-3">
                                    <label class="form-label fw-bold">Mobile Number</label>
                                    <input type="text" class="form-control" name="phone_number" inputmode="numeric" maxlength="11" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Email Address</label>
                                    <input type="email" class="form-control" name="email" maxlength="150">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Temporary Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 fw-bold">Create</button>
                            </form>
                        </div>
                    </section>
                </div>

                <div class="col-lg-8">
                    <section class="app-surface app-table-card">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">System Users</h2>
                            </div>
                            <span class="app-pill-badge"><i class="fa-solid fa-user-shield"></i><?php echo count($users); ?> Accounts</span>
                        </div>
                        <div class="p-0">
                            <?php if ($users === []): ?>
                                <div class="app-empty-state">
                                    <div class="app-empty-state-icon"><i class="fa-regular fa-user"></i></div>
                                    <h3 class="app-empty-state-title">No internal accounts found</h3>
                                    <p class="app-empty-state-copy">Create a staff or admin account to start managing internal access.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Account</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th style="width: 280px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars(trim((string) (($user['display_last_name'] ?? '') . ', ' . ($user['display_first_name'] ?? '')), ', ') ?: (string) $user['phone_number']); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars((string) $user['phone_number']); ?></div>
                                                        <?php if (!empty($user['email'])): ?>
                                                            <div class="small text-muted"><?php echo htmlspecialchars((string) $user['email']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars((string) $user['role']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo ((int) ($user['is_active'] ?? 1) === 1) ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo ((int) ($user['is_active'] ?? 1) === 1) ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column gap-2">
                                                            <form action="<?php echo htmlspecialchars(base_url('admin/users/status')); ?>" method="POST" class="d-flex gap-2 flex-wrap">
                                                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                                <input type="hidden" name="is_active" value="<?php echo ((int) ($user['is_active'] ?? 1) === 1) ? '0' : '1'; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                    <?php echo ((int) ($user['is_active'] ?? 1) === 1) ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                            <form action="<?php echo htmlspecialchars(base_url('admin/users/reset-password')); ?>" method="POST" class="d-flex gap-2 flex-wrap">
                                                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                                <input type="password" class="form-control form-control-sm" name="new_password" placeholder="New password" required>
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">Reset</button>
                                                            </form>
                                                        </div>
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
