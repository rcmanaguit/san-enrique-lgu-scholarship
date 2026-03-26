<?php
require __DIR__ . '/../layouts/header.php';

$accountSettingsOldInput = is_array($accountSettingsOldInput ?? null) ? $accountSettingsOldInput : [];

$accountValue = static function (string $field, string $default = '') use ($accountSettingsOldInput, $accountUser): string {
    if (array_key_exists($field, $accountSettingsOldInput)) {
        return trim((string) $accountSettingsOldInput[$field]);
    }

    return trim((string) ($accountUser[$field] ?? $default));
};

$displayName = trim((string) (($accountUser['first_name'] ?? '') . ' ' . ($accountUser['last_name'] ?? '')));
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Account</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-user-gear me-2"></i>Account Settings</h1>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-lg-4">
                    <section class="app-surface h-100">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Current Account</h2>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <dl class="app-definition-list">
                                <div>
                                    <dt>Name</dt>
                                    <dd><?php echo htmlspecialchars($displayName !== '' ? $displayName : 'Not set'); ?></dd>
                                </div>
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

                            <div class="account-settings-summary-note mt-4">
                                <div class="account-settings-summary-title">Security Check</div>
                                <div class="account-settings-summary-copy">You will be asked for your current password before any profile or password change is saved.</div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-8">
                    <section class="app-surface">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Profile</h2>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <form action="<?php echo htmlspecialchars(base_url('account/settings')); ?>" method="POST" novalidate autocomplete="on">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="settings_section" value="profile">
                                <div class="row g-3">
                                    <?php if (in_array((string) ($accountUser['role'] ?? ''), ['Staff', 'Admin'], true)): ?>
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="first_name"
                                                name="first_name"
                                                value="<?php echo htmlspecialchars($accountValue('first_name')); ?>"
                                                maxlength="100"
                                                autocomplete="given-name"
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
                                                value="<?php echo htmlspecialchars($accountValue('last_name')); ?>"
                                                maxlength="100"
                                                autocomplete="family-name"
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
                                            value="<?php echo htmlspecialchars($accountValue('phone_number')); ?>"
                                            inputmode="numeric"
                                            maxlength="11"
                                            autocomplete="tel"
                                            required
                                            data-validate="phone"
                                            data-field-label="Mobile Number"
                                        >
                                        <div class="form-text">Use your active 11-digit mobile number.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input
                                            type="email"
                                            class="form-control"
                                            id="email"
                                            name="email"
                                            value="<?php echo htmlspecialchars($accountValue('email')); ?>"
                                            autocomplete="email"
                                            data-validate="email"
                                            data-field-label="Email Address"
                                        >
                                        <div class="form-text">Optional, but recommended for recovery and notices.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="profile_current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <input
                                                type="password"
                                                class="form-control"
                                                id="profile_current_password"
                                                name="current_password"
                                                autocomplete="current-password"
                                                required
                                                data-validate="required"
                                                data-field-label="Current Password"
                                            >
                                            <button type="button" class="input-group-text bg-white password-toggle" data-target="profile_current_password" aria-label="Show password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Required only for saving profile changes.</div>
                                    </div>
                                </div>

                                <div class="app-actions-row mt-4">
                                    <button type="submit" class="btn btn-primary fw-bold px-4">
                                        <i class="fa-solid fa-floppy-disk me-2"></i>Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <section class="app-surface mt-4">
                        <div class="app-surface-header">
                            <div>
                                <h2 class="app-surface-title">Password</h2>
                            </div>
                        </div>
                        <div class="app-surface-body">
                            <form action="<?php echo htmlspecialchars(base_url('account/settings')); ?>" method="POST" novalidate autocomplete="on">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="settings_section" value="password">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="password_current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <input
                                                type="password"
                                                class="form-control"
                                                id="password_current_password"
                                                name="current_password"
                                                autocomplete="current-password"
                                                required
                                                data-validate="required"
                                                data-field-label="Current Password"
                                            >
                                            <button type="button" class="input-group-text bg-white password-toggle" data-target="password_current_password" aria-label="Show password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
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
                                                autocomplete="new-password"
                                                data-validate="password"
                                                data-field-label="New Password"
                                                required
                                            >
                                            <button type="button" class="input-group-text bg-white password-toggle" data-target="new_password" aria-label="Show password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Use at least 8 characters.</div>
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
                                                autocomplete="new-password"
                                                data-match-field="#new_password"
                                                data-match-message="Confirm New Password must match your new password."
                                                data-field-label="Confirm New Password"
                                                required
                                            >
                                            <button type="button" class="input-group-text bg-white password-toggle" data-target="confirm_password" aria-label="Show password">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Re-enter the same password to confirm it.</div>
                                    </div>
                                </div>

                                <div class="app-actions-row mt-4">
                                    <button type="submit" class="btn btn-primary fw-bold px-4">
                                        <i class="fa-solid fa-key me-2"></i>Update
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
