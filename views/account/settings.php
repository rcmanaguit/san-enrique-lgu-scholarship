<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Account</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-user-gear me-2"></i>Account Settings</h1>
                    <p class="app-page-subtitle">Update your mobile number, email address, or password using your current password for confirmation.</p>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-lg-4">
                    <section class="app-surface h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Current Account</h2>
                                <p class="app-surface-copy">Your active login and contact details.</p>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <dl class="app-definition-list">
                                <?php if (in_array((string) ($accountUser['role'] ?? ''), ['Staff', 'Admin'], true)): ?>
                                    <div>
                                        <dt>Name</dt>
                                        <dd><?php echo htmlspecialchars(trim((string) (($accountUser['first_name'] ?? '') . ' ' . ($accountUser['last_name'] ?? ''))) ?: 'Not set'); ?></dd>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <dt>Role</dt>
                                    <dd><?php echo htmlspecialchars((string) ($accountUser['role'] ?? 'User')); ?></dd>
                                </div>
                                <div>
                                    <dt>Mobile Number</dt>
                                    <dd><?php echo htmlspecialchars((string) ($accountUser['phone_number'] ?? '')); ?></dd>
                                </div>
                                <div>
                                    <dt>Email Address</dt>
                                    <dd><?php echo htmlspecialchars((string) ($accountUser['email'] ?? 'Not set')); ?></dd>
                                </div>
                                <div>
                                    <dt>Account Status</dt>
                                    <dd><?php echo ((int) ($accountUser['is_active'] ?? 1) === 1) ? 'Active' : 'Inactive'; ?></dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                </div>

                <div class="col-lg-8">
                    <section class="app-surface">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Update Details</h2>
                                <p class="app-surface-copy">Leave the new password blank if you only want to update your mobile number or email address.</p>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <form action="<?php echo htmlspecialchars(base_url('account/settings')); ?>" method="POST" novalidate>
                                <div class="row g-3">
                                    <?php if (in_array((string) ($accountUser['role'] ?? ''), ['Staff', 'Admin'], true)): ?>
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="first_name"
                                                name="first_name"
                                                value="<?php echo htmlspecialchars((string) ($accountUser['first_name'] ?? '')); ?>"
                                                maxlength="100"
                                                required
                                            >
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="last_name"
                                                name="last_name"
                                                value="<?php echo htmlspecialchars((string) ($accountUser['last_name'] ?? '')); ?>"
                                                maxlength="100"
                                                required
                                            >
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-md-6">
                                        <label for="phone_number" class="form-label">Mobile Number</label>
                                        <input
                                            type="tel"
                                            class="form-control"
                                            id="phone_number"
                                            name="phone_number"
                                            value="<?php echo htmlspecialchars((string) ($accountUser['phone_number'] ?? '')); ?>"
                                            inputmode="numeric"
                                            maxlength="11"
                                            required
                                            data-validate="phone"
                                            data-field-label="Mobile Number"
                                        >
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input
                                            type="email"
                                            class="form-control"
                                            id="email"
                                            name="email"
                                            value="<?php echo htmlspecialchars((string) ($accountUser['email'] ?? '')); ?>"
                                            data-validate="email"
                                            data-field-label="Email Address"
                                        >
                                    </div>
                                    <div class="col-12">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <input
                                                type="password"
                                                class="form-control"
                                                id="current_password"
                                                name="current_password"
                                                required
                                                data-validate="required"
                                                data-field-label="Current Password"
                                            >
                                            <button type="button" class="input-group-text bg-white password-toggle" data-target="current_password" aria-label="Show password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Required to confirm any change to your account settings.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <input
                                                type="password"
                                                class="form-control"
                                                id="new_password"
                                                name="new_password"
                                                minlength="8"
                                                data-validate="password-optional"
                                                data-field-label="New Password"
                                            >
                                            <button type="button" class="input-group-text bg-white password-toggle" data-target="new_password" aria-label="Show password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <input
                                                type="password"
                                                class="form-control"
                                                id="confirm_password"
                                                name="confirm_password"
                                                minlength="8"
                                                data-match-field="#new_password"
                                                data-match-message="Confirm New Password must match your new password."
                                                data-field-label="Confirm New Password"
                                            >
                                            <button type="button" class="input-group-text bg-white password-toggle" data-target="confirm_password" aria-label="Show password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="app-actions-row mt-4">
                                    <button type="submit" class="btn btn-primary fw-bold px-4">
                                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Account Changes
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.password-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) {
                return;
            }

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            this.innerHTML = isHidden
                ? '<i class="fa-regular fa-eye-slash"></i>'
                : '<i class="fa-regular fa-eye"></i>';
            this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
