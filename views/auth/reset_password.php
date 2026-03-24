<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="auth-page-shell">
    <div class="container">
        <div class="auth-layout">
            <section class="card auth-main-card">
                <div class="auth-card-body">
                    <div class="auth-logo-wrap">
                        <img src="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>"
                            alt="LGU San Enrique Logo" class="auth-card-logo">
                    </div>

                    <p class="auth-eyebrow">Password Reset</p>
                    <h2 class="auth-form-title h4">Reset Password</h2>
                    <p class="auth-form-lead small">Enter the 6-digit reset code sent by SMS or to the email address used in your scholarship application.</p>

                    <form action="<?php echo htmlspecialchars(base_url('reset-password')); ?>" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="otp_code" class="form-label">Reset Code</label>
                            <input type="text" class="form-control text-center fw-bold" id="otp_code" name="otp_code"
                                placeholder="------" maxlength="6" required inputmode="numeric" data-live-filter="otp"
                                data-exact-length="6" data-field-label="Reset Code">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" minlength="8" required
                                    data-validate="password" data-field-label="New Password">
                                <button type="button" class="input-group-text bg-white password-toggle" data-target="password"
                                    aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required
                                    data-match-field="#password" data-match-message="Confirm New Password must match your new password."
                                    data-field-label="Confirm New Password">
                                <button type="button" class="input-group-text bg-white password-toggle" data-target="confirm_password"
                                    aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="new_phone_number" class="form-label">Optional Replacement Mobile Number</label>
                            <input type="tel" class="form-control" id="new_phone_number" name="new_phone_number"
                                placeholder="09xxxxxxxxx" maxlength="11" inputmode="numeric" data-live-filter="digits"
                                data-exact-length="11" data-field-label="Replacement Mobile Number">
                            <div class="form-text">Lost your SIM card? Enter your new mobile number here to update your account.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </form>

                    <div class="auth-back-row small">
                        <a href="<?php echo htmlspecialchars(base_url('login')); ?>" class="auth-meta-link"><i class="fa-solid fa-arrow-left me-1"></i>Back to Login</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.password-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const input = document.getElementById(this.getAttribute('data-target'));
            const icon = this.querySelector('i');
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);
            this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
