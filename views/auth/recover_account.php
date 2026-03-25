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

                    <p class="auth-eyebrow">Email Recovery</p>
                    <h2 class="auth-form-title h4">Send a Reset Code by Email</h2>
                    <p class="auth-form-lead small">Enter the email address used in your scholarship application.</p>

                    <form action="<?php echo htmlspecialchars(base_url('recover-account')); ?>" method="POST" novalidate>
                        <div class="mb-4">
                            <label for="email" class="form-label">Application Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                placeholder="name@example.com" required data-live-filter="email"
                                data-field-label="Application Email Address">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Send Code</button>
                    </form>

                    <div class="auth-back-row small">
                        <a href="<?php echo htmlspecialchars(base_url('forgot-password')); ?>" class="auth-meta-link"><i class="fa-solid fa-arrow-left me-1"></i>Back to Recovery Options</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
