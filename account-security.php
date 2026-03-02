<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login('login.php');

$pageTitle = 'Account Security';
$user = current_user();
$pendingOtp = otp_state('change_mobile');
$mobilePayload = is_array($pendingOtp['payload'] ?? null) ? $pendingOtp['payload'] : null;
$otpSecondsLeft = otp_seconds_left('change_mobile');

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('account-security.php');
    }

    if (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('account-security.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    $sendChangeMobileOtp = static function (int $userId, string $newPhone, string $userRole) use ($conn): void {
        $code = otp_start('change_mobile', ['user_id' => $userId, 'new_phone' => $newPhone], 300, 5);
        $message = 'San Enrique LGU Scholarship Verification Code (OTP) for mobile change: ' . $code
            . '. Valid for 5 minutes. Do not share this code.';
        $smsResult = sms_send($newPhone, $message, $userId, 'otp');

        if (($smsResult['ok'] ?? false) === true) {
            audit_log(
                $conn,
                'change_mobile_otp_sent',
                $userId,
                $userRole,
                'user',
                (string) $userId,
                'Verification code sent for mobile number change.',
                ['new_phone' => $newPhone]
            );
            set_flash('success', 'Verification Code (OTP) sent to new mobile number ' . mask_mobile_number($newPhone) . '.');
            return;
        }

        $config = sms_active_provider_config();
        $providerLabel = trim((string) ($config['label'] ?? 'SMS provider'));
        if (!(bool) ($config['enabled'] ?? false)) {
            audit_log(
                $conn,
                'change_mobile_otp_generated_dev_mode',
                $userId,
                $userRole,
                'user',
                (string) $userId,
                $providerLabel . ' disabled. Verification code shown in flash for mobile change.'
            );
            set_flash('warning', $providerLabel . ' is disabled. Dev Verification Code (OTP): ' . $code);
            return;
        }

        audit_log(
            $conn,
            'change_mobile_otp_send_failed',
            $userId,
            $userRole,
            'user',
            (string) $userId,
            'Verification code sending failed for mobile change.'
        );
        set_flash('danger', 'Failed to send verification code. Please try again.');
    };

    if ($action === 'cancel_mobile_otp') {
        otp_clear('change_mobile');
        audit_log($conn, 'change_mobile_cancelled', (int) ($user['id'] ?? 0), (string) ($user['role'] ?? 'applicant'), 'user', (string) ($user['id'] ?? ''), 'Mobile number change cancelled.');
        set_flash('info', 'Mobile number change cancelled.');
        redirect('account-security.php');
    }

    if ($action === 'resend_mobile_otp') {
        if (!$mobilePayload) {
            set_flash('warning', 'No pending mobile change request.');
            redirect('account-security.php');
        }
        $payloadUserId = (int) ($mobilePayload['user_id'] ?? 0);
        if ($payloadUserId !== (int) ($user['id'] ?? 0)) {
            otp_clear('change_mobile');
            set_flash('danger', 'Verification code request does not match current account.');
            redirect('account-security.php');
        }
        $sendChangeMobileOtp($payloadUserId, (string) ($mobilePayload['new_phone'] ?? ''), (string) ($user['role'] ?? 'applicant'));
        redirect('account-security.php?otp=1');
    }

    if ($action === 'request_mobile_otp') {
        $newPhoneInput = trim((string) ($_POST['new_phone'] ?? ''));
        $newPhone = normalize_mobile_number($newPhoneInput);
        $currentPassword = (string) ($_POST['current_password'] ?? '');

        if (!is_valid_mobile_number($newPhoneInput)) {
            set_flash('danger', 'Please use a valid mobile number (09XXXXXXXXX).');
            redirect('account-security.php');
        }

        if ($newPhone === normalize_mobile_number((string) ($user['phone'] ?? ''))) {
            set_flash('warning', 'New mobile number is the same as your current number.');
            redirect('account-security.php');
        }

        if (mobile_number_exists($conn, $newPhone, (int) ($user['id'] ?? 0))) {
            set_flash('danger', 'That mobile number is already registered by another account.');
            redirect('account-security.php');
        }

        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $currentHash = (string) (($stmt->get_result()->fetch_assoc()['password_hash'] ?? ''));
        $stmt->close();

        if ($currentPassword === '' || !password_verify($currentPassword, $currentHash)) {
            set_flash('danger', 'Current password is incorrect.');
            redirect('account-security.php');
        }

        $sendChangeMobileOtp((int) $user['id'], $newPhone, (string) ($user['role'] ?? 'applicant'));
        redirect('account-security.php?otp=1');
    }

    if ($action === 'verify_mobile_otp') {
        $otpCode = trim((string) ($_POST['otp_code'] ?? ''));
        $verified = otp_verify('change_mobile', $otpCode);
        if (!($verified['ok'] ?? false)) {
            audit_log($conn, 'change_mobile_otp_verify_failed', (int) ($user['id'] ?? 0), (string) ($user['role'] ?? 'applicant'), 'user', (string) ($user['id'] ?? ''), (string) ($verified['error'] ?? 'Verification code check failed.'));
            set_flash('danger', (string) ($verified['error'] ?? 'Invalid verification code.'));
            redirect('account-security.php?otp=1');
        }

        $payload = is_array($verified['payload'] ?? null) ? $verified['payload'] : [];
        $payloadUserId = (int) ($payload['user_id'] ?? 0);
        $newPhone = normalize_mobile_number((string) ($payload['new_phone'] ?? ''));

        if ($payloadUserId !== (int) ($user['id'] ?? 0) || $newPhone === '') {
            set_flash('danger', 'Invalid verification request. Please request a new code.');
            redirect('account-security.php');
        }

        if (mobile_number_exists($conn, $newPhone, (int) ($user['id'] ?? 0))) {
            set_flash('danger', 'That mobile number is already in use by another account.');
            redirect('account-security.php');
        }

        $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ? LIMIT 1");
        $stmt->bind_param('si', $newPhone, $user['id']);
        $stmt->execute();
        $stmt->close();
        audit_log(
            $conn,
            'change_mobile_success',
            (int) ($user['id'] ?? 0),
            (string) ($user['role'] ?? 'applicant'),
            'user',
            (string) ($user['id'] ?? ''),
            'Mobile number updated successfully.',
            ['new_phone' => $newPhone]
        );
        create_notification(
            $conn,
            (int) ($user['id'] ?? 0),
            'Mobile Number Updated',
            'Your mobile number was updated successfully.',
            'security',
            'profile-settings.php',
            (int) ($user['id'] ?? 0)
        );

        $_SESSION['user']['phone'] = $newPhone;
        set_flash('success', 'Mobile number updated successfully.');
        redirect('account-security.php');
    }
}

$pendingOtp = otp_state('change_mobile');
$mobilePayload = is_array($pendingOtp['payload'] ?? null) ? $pendingOtp['payload'] : null;
$otpSecondsLeft = otp_seconds_left('change_mobile');

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body p-4">
                <h1 class="h4 mb-2"><i class="fa-solid fa-shield-halved me-2 text-primary"></i>Account Security</h1>
                <p class="small text-muted mb-3">Current mobile number: <strong><?= e((string) ($user['phone'] ?? '-')) ?></strong></p>
                <form method="post" class="row g-2">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="request_mobile_otp">
                    <div class="col-12 col-md-6">
                        <label class="form-label">New Mobile Number</label>
                        <input type="text" class="form-control" name="new_phone" placeholder="09XXXXXXXXX" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="current_password" id="securityCurrentPassword" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#securityCurrentPassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Send Verification Code (OTP) to New Number</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($mobilePayload): ?>
            <div class="card card-soft shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-2">Verify Mobile Number Change</h2>
                    <p class="small text-muted mb-3">
                        Verification Code (OTP) sent to <?= e(mask_mobile_number((string) ($mobilePayload['new_phone'] ?? ''))) ?>.
                        <?php if ($otpSecondsLeft > 0): ?>
                            Expires in <?= (int) ceil($otpSecondsLeft / 60) ?> minute(s).
                        <?php endif; ?>
                    </p>
                    <form method="post" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="verify_mobile_otp">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Verification Code (OTP)</label>
                            <input type="text" class="form-control" name="otp_code" maxlength="8" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Verify Code (OTP) & Update Number</button>
                        </div>
                    </form>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="resend_mobile_otp">
                            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-rotate me-1"></i>Resend Verification Code (OTP)</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="cancel_mobile_otp">
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-xmark me-1"></i>Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

