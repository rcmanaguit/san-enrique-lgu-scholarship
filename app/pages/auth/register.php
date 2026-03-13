<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

if (($_GET['check'] ?? '') === 'phone') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!db_ready()) {
        http_response_code(503);
        echo json_encode([
            'ok' => false,
            'available' => false,
            'message' => 'The system is not ready yet.',
        ]);
        exit;
    }

    $phone = trim((string) ($_GET['phone'] ?? ''));
    if (!is_valid_mobile_number($phone)) {
        echo json_encode([
            'ok' => true,
            'available' => false,
            'message' => 'Use a valid mobile number in 09XXXXXXXXX format.',
        ]);
        exit;
    }

    $available = !mobile_number_exists($conn, $phone);
    echo json_encode([
        'ok' => true,
        'available' => $available,
        'message' => $available
            ? 'Mobile number is available.'
            : 'Mobile number is already registered.',
    ]);
    exit;
}

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Applicant Registration';
$hideNavbar = true;
$hideFooter = true;
$bodyClass = 'auth-page';
$extraJs = ['assets/js/auth-forms.js'];
$hasPeriodTable = db_ready() && table_exists($conn, 'application_periods');
$openPeriod = ($hasPeriodTable && db_ready()) ? current_open_application_period($conn) : null;
$isRegistrationOpen = db_ready() && $hasPeriodTable && $openPeriod !== null;
$authLogoRelativePath = 'assets/images/branding/lgu-logo.png';
$authLogoAbsolutePath = dirname(__DIR__, 3) . '/' . $authLogoRelativePath;
$hasAuthLogo = file_exists($authLogoAbsolutePath);
$pendingOtp = otp_state('register_account');
$pendingRegistration = is_array($pendingOtp['payload']['registration'] ?? null)
    ? $pendingOtp['payload']['registration']
    : null;

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('register.php');
    }

    if (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('register.php');
    }

    $action = trim((string) ($_POST['action'] ?? 'request_otp'));

    $sendOtpForRegistration = static function (array $registrationPayload) use ($conn): void {
        $phone = (string) ($registrationPayload['phone'] ?? '');
        $name = trim((string) ($registrationPayload['first_name'] ?? ''));
        $code = otp_start('register_account', ['registration' => $registrationPayload], 300, 5);
        $message = 'San Enrique LGU Scholarship Verification Code (OTP): ' . $code
            . '. Complete registration within 5 minutes. Do not share this code.';
        $smsResult = sms_send($phone, $message, null, 'otp');

        if (($smsResult['ok'] ?? false) === true) {
            audit_log(
                $conn,
                'register_otp_sent',
                null,
                'guest',
                'registration',
                null,
                'Registration verification code sent successfully.',
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
                'register_otp_generated_dev_mode',
                null,
                'guest',
                'registration',
                null,
                $providerLabel . ' disabled. Verification code shown in flash for development.',
                ['phone' => $phone]
            );
            set_flash('warning', $providerLabel . ' is disabled. Dev Verification Code (OTP) for ' . $name . ': ' . $code);
            return;
        }

        audit_log(
            $conn,
            'register_otp_send_failed',
            null,
            'guest',
            'registration',
            null,
            'Registration verification code sending failed.',
            ['phone' => $phone]
        );
        set_flash('danger', 'Failed to send verification code. Please try again.');
    };

    if ($action === 'request_otp') {
        if (!$isRegistrationOpen) {
            set_flash('danger', 'Registration is currently closed. There is no open application period.');
            redirect('register.php');
        }

        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $phoneInput = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $phone = normalize_mobile_number($phoneInput);

        if (!$firstName || !$lastName || !$phoneInput || !$password || !$confirmPassword) {
            set_flash('danger', 'Please fill in all required fields.');
            redirect('register.php');
        }
        if (!is_valid_mobile_number($phoneInput)) {
            set_flash('danger', 'Please use a valid mobile number (09XXXXXXXXX).');
            redirect('register.php');
        }
        if (strlen($password) < 8) {
            set_flash('danger', 'Password must be at least 8 characters.');
            redirect('register.php');
        }
        if ($password !== $confirmPassword) {
            set_flash('danger', 'Password and confirm password do not match.');
            redirect('register.php');
        }

        if (mobile_number_exists($conn, $phone)) {
            set_flash('danger', 'Mobile number is already registered.');
            redirect('register.php');
        }

        $registrationPayload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ];

        $sendOtpForRegistration($registrationPayload);
        redirect('register-otp.php');
    }
}

$pendingOtp = otp_state('register_account');
$pendingRegistration = is_array($pendingOtp['payload']['registration'] ?? null)
    ? $pendingOtp['payload']['registration']
    : null;
include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <?php if (!$isRegistrationOpen): ?>
            <div class="alert alert-warning">
                <strong>Registration Closed:</strong>
                <?php if (!$hasPeriodTable): ?>
                    Application period settings are not ready yet. Please contact the administrator.
                <?php else: ?>
                    No open application period is available.
                <?php endif; ?>
                <?php if ($openPeriod): ?>
                    <div class="small mt-1"><?= e(format_application_period($openPeriod)) ?></div>
                <?php endif; ?>
            </div>
        <?php elseif ($openPeriod): ?>
            <div class="alert alert-info small">
                <strong>Open Application Period:</strong> <?= e(format_application_period($openPeriod)) ?>
            </div>
        <?php endif; ?>

        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body p-4 auth-simple-card">
                <div class="auth-logo-wrap">
                    <?php if ($hasAuthLogo): ?>
                        <img src="<?= e($authLogoRelativePath) ?>" alt="Municipality of San Enrique Official Seal" class="auth-card-logo">
                    <?php else: ?>
                        <span class="auth-logo-fallback" aria-hidden="true"><i class="fa-solid fa-shield"></i></span>
                    <?php endif; ?>
                </div>
                <p class="public-kicker text-center mb-2">Applicant Registration</p>
                <h1 class="h4 mb-2 text-center">Create your applicant account</h1>
                <p class="text-muted small mb-4 text-center">Use your mobile number for login. You will provide your email address in Step 2 of the application form after account creation.</p>
                <form method="post" class="row g-3" novalidate data-auth-form="register">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="request_otp">

                    <div class="col-12 col-md-6">
                        <label class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="registerFirstName" name="first_name" required value="<?= e(old('first_name', (string) ($pendingRegistration['first_name'] ?? ''))) ?>">
                        <div class="form-text" data-feedback="register-first-name">Enter your given name.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="registerLastName" name="last_name" required value="<?= e(old('last_name', (string) ($pendingRegistration['last_name'] ?? ''))) ?>">
                        <div class="form-text" data-feedback="register-last-name">Enter your family name.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Mobile Number *</label>
                        <input type="text" class="form-control" id="registerPhone" name="phone" required placeholder="09XXXXXXXXX" value="<?= e(old('phone', (string) ($pendingRegistration['phone'] ?? ''))) ?>" inputmode="numeric" pattern="[0-9]*" autocomplete="tel-national" maxlength="12">
                        <div class="form-text" data-feedback="register-phone">Use 11 digits starting with 09.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="registerPassword" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#registerPassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text" data-feedback="register-password">Use at least 8 characters.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Confirm Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="registerConfirmPassword" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#registerConfirmPassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text" data-feedback="register-confirm-password">Re-enter the same password.</div>
                    </div>

                    <div class="col-12 d-grid d-md-flex">
                        <button type="submit" class="btn btn-primary px-4 w-100" <?= !$isRegistrationOpen ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-arrow-right me-1"></i>Continue to OTP
                        </button>
                    </div>
                </form>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 small text-muted mt-3">
                    <span>Already registered? <a href="login.php">Login here</a>.</span>
                    <a href="index.php"><i class="fa-solid fa-arrow-left me-1"></i>Back to Home</a>
                </div>
            </div>
        </div>

        <?php if ($pendingRegistration): ?>
            <div class="card card-soft shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h6 mb-2">Pending OTP Verification</h2>
                    <p class="small text-muted mb-3">
                        A verification code was already requested for <?= e(mask_mobile_number((string) ($pendingRegistration['phone'] ?? ''))) ?>.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="register-otp.php" class="btn btn-success btn-sm"><i class="fa-solid fa-arrow-right me-1"></i>Go to OTP Page</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>


