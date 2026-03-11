<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Verify Registration OTP';
$hideNavbar = true;
$hideFooter = true;
$bodyClass = 'auth-page';
$hasPeriodTable = db_ready() && table_exists($conn, 'application_periods');
$openPeriod = ($hasPeriodTable && db_ready()) ? current_open_application_period($conn) : null;
$isRegistrationOpen = db_ready() && $hasPeriodTable && $openPeriod !== null;
$authLogoRelativePath = 'assets/images/branding/lgu-logo.png';
$authLogoAbsolutePath = __DIR__ . '/' . $authLogoRelativePath;
$hasAuthLogo = file_exists($authLogoAbsolutePath);
$pendingOtp = otp_state('register_account');
$pendingRegistration = is_array($pendingOtp['payload']['registration'] ?? null)
    ? $pendingOtp['payload']['registration']
    : null;

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('register-otp.php');
    }

    if (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('register.php');
    }

    $action = trim((string) ($_POST['action'] ?? 'verify_otp'));
    $pendingOtp = otp_state('register_account');
    $pendingRegistration = is_array($pendingOtp['payload']['registration'] ?? null)
        ? $pendingOtp['payload']['registration']
        : null;

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

    if ($action === 'cancel_otp') {
        otp_clear('register_account');
        audit_log($conn, 'register_otp_cancelled', null, 'guest', 'registration', null, 'Pending registration verification code request was cancelled.');
        set_flash('info', 'Registration verification code request cancelled.');
        redirect('register.php');
    }

    if (!$pendingRegistration) {
        set_flash('warning', 'No pending registration request. Fill up the form first.');
        redirect('register.php');
    }

    if (!$isRegistrationOpen) {
        otp_clear('register_account');
        set_flash('danger', 'Registration is closed because the application period ended.');
        redirect('register.php');
    }

    if ($action === 'resend_otp') {
        $sendOtpForRegistration($pendingRegistration);
        redirect('register-otp.php');
    }

    if ($action === 'verify_otp') {
        $otpCode = trim((string) ($_POST['otp_code'] ?? ''));
        $verified = otp_verify('register_account', $otpCode);
        if (!($verified['ok'] ?? false)) {
            audit_log(
                $conn,
                'register_otp_verify_failed',
                null,
                'guest',
                'registration',
                null,
                (string) ($verified['error'] ?? 'Verification code check failed.')
            );
            set_flash('danger', (string) ($verified['error'] ?? 'Invalid verification code.'));
            redirect('register-otp.php');
        }

        $data = is_array($verified['payload']['registration'] ?? null) ? $verified['payload']['registration'] : [];
        if (!$data) {
            set_flash('danger', 'Missing registration data. Please register again.');
            redirect('register.php');
        }

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $middleName = trim((string) ($data['middle_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $phone = normalize_mobile_number((string) ($data['phone'] ?? ''));
        $passwordHash = trim((string) ($data['password_hash'] ?? ''));
        $schoolName = trim((string) ($data['school_name'] ?? ''));
        $schoolType = trim((string) ($data['school_type'] ?? ''));
        $course = trim((string) ($data['course'] ?? ''));

        if ($firstName === '' || $lastName === '' || $phone === '' || $passwordHash === '') {
            set_flash('danger', 'Registration data is incomplete. Please register again.');
            redirect('register.php');
        }
        if (mobile_number_exists($conn, $phone)) {
            set_flash('danger', 'Mobile number is already registered.');
            redirect('register.php');
        }

        $stmt = $conn->prepare(
            "INSERT INTO users (role, first_name, middle_name, last_name, email, phone, password_hash, school_name, school_type, course, status)
             VALUES ('applicant', ?, ?, ?, NULL, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->bind_param('ssssssss', $firstName, $middleName, $lastName, $phone, $passwordHash, $schoolName, $schoolType, $course);
        $ok = $stmt->execute();
        $errorNo = (int) ($stmt->errno ?? 0);
        $stmt->close();

        if ($ok) {
            otp_clear('register_account');
            session_regenerate_id(true);
            $newUserId = (int) $conn->insert_id;
            $_SESSION['user'] = [
                'id' => $newUserId,
                'role' => 'applicant',
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'email' => null,
                'phone' => $phone,
                'status' => 'active',
            ];
            audit_log(
                $conn,
                'register_account_created',
                $newUserId,
                'applicant',
                'user',
                (string) $newUserId,
                'Applicant account created via verification code registration.'
            );
            create_notification(
                $conn,
                $newUserId,
                'Welcome to San Enrique LGU Scholarship',
                'Your account is ready. Continue to Step 1 to complete your scholarship application.',
                'system',
                'apply.php?step=1',
                $newUserId
            );
            create_notifications_for_roles(
                $conn,
                ['admin', 'staff'],
                'New Applicant Registered',
                $firstName . ' ' . $lastName . ' created a new applicant account.',
                'application',
                'shared/applications.php',
                $newUserId
            );
            set_flash('success', 'Account created. Continue to Step 1 of your application.');
            redirect('apply.php?step=1');
        }

        if ($errorNo === 1062) {
            set_flash('danger', 'Registration conflict detected. Mobile number is already registered.');
            redirect('register.php');
        }
        set_flash('danger', 'Registration failed. Please try again.');
        redirect('register.php');
    }
}

$pendingOtp = otp_state('register_account');
$pendingRegistration = is_array($pendingOtp['payload']['registration'] ?? null)
    ? $pendingOtp['payload']['registration']
    : null;

if (!$pendingRegistration) {
    set_flash('warning', 'No pending registration request. Fill up the form first.');
    redirect('register.php');
}

if (!$isRegistrationOpen) {
    otp_clear('register_account');
    set_flash('danger', 'Registration is closed because the application period ended.');
    redirect('register.php');
}

$otpSecondsLeft = otp_seconds_left('register_account');

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
        <div class="card card-soft shadow-sm">
            <div class="card-body p-4 auth-simple-card">
                <div class="auth-logo-wrap">
                    <?php if ($hasAuthLogo): ?>
                        <img src="<?= e($authLogoRelativePath) ?>" alt="Municipality of San Enrique Official Seal" class="auth-card-logo">
                    <?php else: ?>
                        <span class="auth-logo-fallback" aria-hidden="true"><i class="fa-solid fa-shield"></i></span>
                    <?php endif; ?>
                </div>

                <p class="public-kicker text-center mb-2">Applicant Registration</p>
                <h1 class="h4 mb-2 text-center">Verify your mobile number</h1>
                <p class="small text-muted text-center mb-4">
                    Enter the verification code sent to <?= e(mask_mobile_number((string) ($pendingRegistration['phone'] ?? ''))) ?>.
                    <?php if ($otpSecondsLeft > 0): ?>
                        Code expires in <?= (int) ceil($otpSecondsLeft / 60) ?> minute(s).
                    <?php endif; ?>
                </p>

                <form method="post" class="row g-3" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="col-12">
                        <label class="form-label">Verification Code</label>
                        <input type="text" name="otp_code" class="form-control" maxlength="8" required placeholder="6-digit code">
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" class="btn btn-success">Verify and create account</button>
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

                <p class="small text-muted mt-3 mb-0 text-center">
                    Need to change details? <a href="register.php">Go back to registration</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
