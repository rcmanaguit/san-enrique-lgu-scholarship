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
                    <h2 class="auth-form-title h4">Forgot Your Password?</h2>
                    <p class="auth-form-lead small">Enter your registered mobile number to receive an SMS reset code.</p>

                    <form action="<?php echo htmlspecialchars(base_url('forgot-password')); ?>" method="POST" novalidate>
                        <?php echo csrf_input(); ?>
                        <div class="mb-4">
                            <label for="phone_number" class="form-label">Registered Mobile Number</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number"
                                placeholder="09xxxxxxxxx" required pattern="[0-9]{11}" maxlength="11"
                                inputmode="numeric" data-live-filter="digits" data-exact-length="11"
                                data-field-label="Registered Mobile Number">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Send Code</button>
                    </form>

                    <div class="auth-recovery-divider"><span>Or</span></div>

                    <div class="text-center">
                        <p class="text-muted small mb-2">Lost your SIM card?</p>
                        <a href="<?php echo htmlspecialchars(base_url('recover-account')); ?>" class="btn btn-outline-secondary w-100">
                            Email OTP
                        </a>
                    </div>

                    <div class="auth-back-row small">
                        <a href="<?php echo htmlspecialchars(base_url('login')); ?>" class="auth-meta-link"><i class="fa-solid fa-arrow-left me-1"></i>Back to Login</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
