<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Forgot Password';
$hideNavbar = true;
$hideFooter = true;
$bodyClass = 'auth-page';
$pendingOtp = otp_state('forgot_password');
$resetPayload = is_array($pendingOtp['payload'] ?? null) ? $pendingOtp['payload'] : null;
$otpSecondsLeft = otp_seconds_left('forgot_password');
$mobileValue = '';
$authLogoRelativePath = 'assets/images/branding/lgu-logo.png';
$authLogoAbsolutePath = __DIR__ . '/' . $authLogoRelativePath;
$hasAuthLogo = file_exists($authLogoAbsolutePath);

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('forgot-password.php');
    }

    if (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('forgot-password.php');
    }

    $action = trim((string) ($_POST['action'] ?? 'request_otp'));
    $mobileRaw = trim((string) ($_POST['phone'] ?? ''));
    $mobileValue = preg_replace('/\D+/', '', $mobileRaw) ?? '';

    $sendResetOtp = static function (int $userId, string $phone) use ($conn): void {
        $code = otp_start('forgot_password', ['user_id' => $userId, 'phone' => $phone], 300, 5);
        $message = 'San Enrique LGU Scholarship password reset Verification Code (OTP): ' . $code
            . '. Valid for 5 minutes. Do not share this code.';
        $smsResult = sms_send($phone, $message, $userId, 'otp');

        if (($smsResult['ok'] ?? false) === true) {
            audit_log(
                $conn,
                'forgot_password_otp_sent',
                $userId,
                'applicant',
                'password_reset',
                (string) $userId,
                'Forgot-password verification code sent.',
                ['phone' => $phone]
            );
            set_flash('success', 'Verification Code (OTP) sent to ' . mask_mobile_number($phone) . '.');
            return;
        }

        $config = sms_active_provider_config();
        $providerLabel = trim((string) ($config['label'] ?? 'SMS provider'));
        if (!(bool) ($config['enabled'] ?? false)) {
            audit_log(
                $conn,
                'forgot_password_otp_generated_dev_mode',
                $userId,
                'applicant',
                'password_reset',
                (string) $userId,
                $providerLabel . ' disabled. Forgot-password verification code shown in flash.'
            );
            set_flash('warning', $providerLabel . ' is disabled. Dev Verification Code (OTP): ' . $code);
            return;
        }

        audit_log(
            $conn,
            'forgot_password_otp_send_failed',
            $userId,
            'applicant',
            'password_reset',
            (string) $userId,
            'Forgot-password verification code sending failed.'
        );
        set_flash('danger', 'Failed to send verification code. Please try again.');
    };

    if ($action === 'cancel_otp') {
        otp_clear('forgot_password');
        set_flash('info', 'Password reset cancelled.');
        redirect('forgot-password.php');
    }

    if ($action === 'resend_otp') {
        if (!$resetPayload) {
            set_flash('warning', 'No pending verification code request.');
            redirect('forgot-password.php');
        }
        $sendResetOtp((int) ($resetPayload['user_id'] ?? 0), (string) ($resetPayload['phone'] ?? ''));
        redirect('forgot-password.php?otp=1');
    }

    if ($action === 'request_otp') {
        if (!is_valid_mobile_number($mobileValue)) {
            set_flash('danger', 'Please use a valid mobile number (09XXXXXXXXX).');
            redirect('forgot-password.php');
        }

        $user = find_user_by_mobile($conn, $mobileValue);
        if (!$user || (string) ($user['status'] ?? '') !== 'active') {
            set_flash('danger', 'No active account found for that mobile number.');
            redirect('forgot-password.php');
        }

        $phone = normalize_mobile_number((string) ($user['phone'] ?? $mobileValue));
        $sendResetOtp((int) $user['id'], $phone);
        redirect('forgot-password.php?otp=1');
    }

    if ($action === 'verify_reset') {
        $otpCode = trim((string) ($_POST['otp_code'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < 8) {
            set_flash('danger', 'New password must be at least 8 characters.');
            redirect('forgot-password.php?otp=1');
        }
        if ($newPassword !== $confirmPassword) {
            set_flash('danger', 'New password and confirm password do not match.');
            redirect('forgot-password.php?otp=1');
        }

        $verified = otp_verify('forgot_password', $otpCode);
        if (!($verified['ok'] ?? false)) {
            audit_log($conn, 'forgot_password_otp_verify_failed', null, 'guest', 'password_reset', null, (string) ($verified['error'] ?? 'Verification code check failed.'));
            set_flash('danger', (string) ($verified['error'] ?? 'Invalid verification code.'));
            redirect('forgot-password.php?otp=1');
        }

        $payload = is_array($verified['payload'] ?? null) ? $verified['payload'] : [];
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            set_flash('danger', 'The verification code session is invalid. Please request a new code.');
            redirect('forgot-password.php');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $stmt->close();
        audit_log(
            $conn,
            'forgot_password_reset_success',
            $userId,
            'applicant',
            'user',
            (string) $userId,
            'Password reset completed via verification code.'
        );

        set_flash('success', 'Password updated. You can now login.');
        redirect('login.php');
    }
}

$pendingOtp = otp_state('forgot_password');
$resetPayload = is_array($pendingOtp['payload'] ?? null) ? $pendingOtp['payload'] : null;
$otpSecondsLeft = otp_seconds_left('forgot_password');

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body p-4 auth-simple-card">
                <div class="auth-logo-wrap">
                    <?php if ($hasAuthLogo): ?>
                        <img src="<?= e($authLogoRelativePath) ?>" alt="Municipality of San Enrique Official Seal" class="auth-card-logo">
                    <?php else: ?>
                        <span class="auth-logo-fallback" aria-hidden="true"><i class="fa-solid fa-shield"></i></span>
                    <?php endif; ?>
                </div>
                <p class="public-kicker text-center mb-2">Password Reset</p>
                <h1 class="h4 mb-2 text-center">Forgot your password?</h1>
                <p class="text-muted small text-center mb-4">Enter your registered mobile number to receive a verification code and set a new password.</p>
                <form method="post" class="row g-3" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="request_otp">
                    <div class="col-12">
                        <label class="form-label" for="forgotPhone">Registered Mobile Number</label>
                        <input
                            type="text"
                            class="form-control"
                            id="forgotPhone"
                            name="phone"
                            placeholder="09XXXXXXXXX"
                            required
                            value="<?= e($mobileValue) ?>"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            autocomplete="tel-national"
                            maxlength="12"
                        >
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" class="btn btn-primary">Send verification code</button>
                    </div>
                </form>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 small text-muted mt-3 mb-0">
                    <span>Remembered your password? <a href="login.php">Login here</a>.</span>
                    <a href="index.php"><i class="fa-solid fa-arrow-left me-1"></i>Back to Home</a>
                </div>
            </div>
        </div>

        <?php if ($resetPayload): ?>
            <div class="card card-soft shadow-sm">
                <div class="card-body p-4 auth-simple-card">
                    <div class="auth-logo-wrap">
                        <?php if ($hasAuthLogo): ?>
                            <img src="<?= e($authLogoRelativePath) ?>" alt="Municipality of San Enrique Official Seal" class="auth-card-logo">
                        <?php else: ?>
                            <span class="auth-logo-fallback" aria-hidden="true"><i class="fa-solid fa-shield"></i></span>
                        <?php endif; ?>
                    </div>
                    <p class="public-kicker text-center mb-2">Verify Reset Code</p>
                    <h2 class="h5 mb-2 text-center">Set a new password</h2>
                    <p class="small text-muted text-center mb-4">
                        Enter the verification code sent to <?= e(mask_mobile_number((string) ($resetPayload['phone'] ?? ''))) ?> and choose a new password.
                        <?php if ($otpSecondsLeft > 0): ?>
                            Code expires in <?= (int) ceil($otpSecondsLeft / 60) ?> minute(s).
                        <?php endif; ?>
                    </p>
                    <form method="post" class="row g-3" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="verify_reset">
                        <div class="col-12">
                            <label class="form-label">Verification Code</label>
                            <input type="text" class="form-control" name="otp_code" maxlength="8" required placeholder="Enter code">
                        </div>
                        <div class="col-12">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="new_password" id="forgotNewPassword" required>
                                <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#forgotNewPassword" aria-label="Show password">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="confirm_password" id="forgotConfirmPassword" required>
                                <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#forgotConfirmPassword" aria-label="Show password">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 d-grid">
                            <button type="submit" class="btn btn-success">Verify code and reset password</button>
                        </div>
                    </form>
                    <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="resend_otp">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Resend code</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="cancel_otp">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Start over</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mobileInput = document.getElementById('forgotPhone');
    if (!mobileInput) {
        return;
    }

    const sanitize = function () {
        mobileInput.value = String(mobileInput.value || '').replace(/\D+/g, '').slice(0, 12);
    };

    mobileInput.addEventListener('input', sanitize);
    mobileInput.addEventListener('paste', function () {
        setTimeout(sanitize, 0);
    });
    mobileInput.addEventListener('keydown', function (event) {
        const allowedKeys = [
            'Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End',
        ];
        if (allowedKeys.includes(event.key) || event.ctrlKey || event.metaKey) {
            return;
        }
        if (!/^\d$/.test(event.key)) {
            event.preventDefault();
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

