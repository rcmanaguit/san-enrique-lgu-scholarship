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

                    <p class="auth-eyebrow">Applicant Registration</p>
                    <h2 class="auth-form-title h4">Create Your Account</h2>
                    <p class="auth-form-lead small">Create your login first, then verify your mobile number to start your scholarship application.</p>

                    <form action="<?php echo htmlspecialchars(base_url('register')); ?>" method="POST" novalidate>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                    maxlength="100" required data-field-label="First Name">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                    maxlength="100" required data-field-label="Last Name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone_number" class="form-label">Mobile Number</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number"
                                placeholder="09xxxxxxxxx" required pattern="[0-9]{11}" maxlength="11"
                                inputmode="numeric" data-live-filter="digits" data-exact-length="11"
                                data-field-label="Mobile Number">
                            <div class="form-text">We will send a 6-digit verification code to this number.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Create Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" minlength="8"
                                    required data-validate="password" data-field-label="Create Password">
                                <button type="button" class="input-group-text bg-white password-toggle" data-target="password"
                                    aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" required
                                    data-match-field="#password" data-match-message="Confirm Password must match your password."
                                    data-field-label="Confirm Password">
                                <button type="button" class="input-group-text bg-white password-toggle"
                                    data-target="confirm_password" aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Verify OTP</button>
                    </form>

                    <div class="auth-secondary-links small">
                        Already have an account? <a href="<?php echo htmlspecialchars(base_url('login')); ?>" class="auth-meta-link">Log in here</a>
                    </div>

                    <div class="auth-back-row small">
                        <a href="<?php echo htmlspecialchars(base_url()); ?>" class="auth-meta-link"><i class="fa-solid fa-arrow-left me-1"></i>Back to Home</a>
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
