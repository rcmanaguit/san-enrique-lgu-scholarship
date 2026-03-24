<?php
$verificationSentAt = (int) ($_SESSION['verification_otp_sent_at'] ?? 0);
$resendCooldown = 60;
$secondsRemaining = max(0, $resendCooldown - (time() - $verificationSentAt));
require __DIR__ . '/../layouts/header.php';
?>

<div class="auth-page-shell">
    <div class="container">
        <div class="auth-layout">
            <section class="card auth-main-card">
                <div class="auth-card-body text-center">
                    <div class="auth-logo-wrap">
                        <img src="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>"
                            alt="LGU San Enrique Logo" class="auth-card-logo">
                    </div>

                    <p class="auth-eyebrow">Verification</p>
                    <h2 class="auth-form-title h4">Verify Your Mobile Number</h2>
                    <p class="auth-form-lead small">Enter the 6-digit verification code sent to your registered mobile number.</p>

                    <form action="<?php echo htmlspecialchars(base_url('verify-otp')); ?>" method="POST" novalidate>
                        <div class="mb-4">
                            <label for="otp_code" class="form-label d-block text-start">Verification Code</label>
                            <input type="text" class="form-control form-control-lg text-center fw-bold" id="otp_code" name="otp_code"
                                placeholder="------" maxlength="6" inputmode="numeric" data-live-filter="otp"
                                data-exact-length="6" data-field-label="Verification Code"
                                style="letter-spacing: 10px; font-size: 1.5rem;"
                                required>
                        </div>

                        <button type="submit" class="btn btn-success w-100 mb-3">Verify Account</button>
                    </form>

                    <div class="mb-3">
                        <a href="<?php echo htmlspecialchars(base_url('verify-otp/cancel')); ?>" class="btn btn-outline-danger w-100">Cancel</a>
                    </div>

                    <p class="text-muted small mb-2">Didn’t receive the code?</p>
                    <form action="<?php echo htmlspecialchars(base_url('verify-otp/resend')); ?>" method="POST" class="d-inline">
                        <button id="resendBtn" type="submit" class="auth-resend-button<?php echo $secondsRemaining > 0 ? ' disabled' : ''; ?>"
                            <?php echo $secondsRemaining > 0 ? 'disabled' : ''; ?>>
                            <?php echo $secondsRemaining > 0 ? 'Resend Code (' . $secondsRemaining . 's)' : 'Resend Code'; ?>
                        </button>
                    </form>
                    <p class="text-muted small mt-2 mb-0">You can request a new code once every 60 seconds.</p>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
    let timeLeft = <?php echo (int) $secondsRemaining; ?>;
    const resendBtn = document.getElementById('resendBtn');
    if (resendBtn && timeLeft > 0) {
        const timerId = setInterval(() => {
            timeLeft--;

            if (timeLeft <= 0) {
                clearInterval(timerId);
                resendBtn.classList.remove('disabled');
                resendBtn.disabled = false;
                resendBtn.innerText = 'Resend Code';
                return;
            }

            resendBtn.innerText = `Resend Code (${timeLeft}s)`;
        }, 1000);
    }
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
