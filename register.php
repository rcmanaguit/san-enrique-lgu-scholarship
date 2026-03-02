<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Applicant Registration';
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

    if ($action === 'cancel_otp') {
        otp_clear('register_account');
        audit_log($conn, 'register_otp_cancelled', null, 'guest', 'registration', null, 'Pending registration verification code request was cancelled.');
        set_flash('info', 'Registration verification code request cancelled.');
        redirect('register.php');
    }

    if ($action === 'resend_otp') {
        if (!$pendingRegistration) {
            set_flash('warning', 'No pending registration request. Fill up the form first.');
            redirect('register.php');
        }

        if (!$isRegistrationOpen) {
            set_flash('danger', 'Registration is closed because there is no open application period.');
            redirect('register.php');
        }

        $sendOtpForRegistration($pendingRegistration);
        redirect('register.php?otp=1');
    }

    if ($action === 'verify_otp') {
        if (!$pendingRegistration) {
            set_flash('warning', 'No pending registration request. Please request a verification code first.');
            redirect('register.php');
        }

        if (!$isRegistrationOpen) {
            otp_clear('register_account');
            set_flash('danger', 'Registration is closed because the application period ended.');
            redirect('register.php');
        }

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
            redirect('register.php?otp=1');
        }

        $data = is_array($verified['payload']['registration'] ?? null) ? $verified['payload']['registration'] : [];
        if (!$data) {
            set_flash('danger', 'Missing registration data. Please register again.');
            redirect('register.php');
        }

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $middleName = trim((string) ($data['middle_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = normalize_mobile_number((string) ($data['phone'] ?? ''));
        $passwordHash = trim((string) ($data['password_hash'] ?? ''));
        $schoolName = trim((string) ($data['school_name'] ?? ''));
        $schoolType = trim((string) ($data['school_type'] ?? ''));
        $course = trim((string) ($data['course'] ?? ''));
        $yearLevel = trim((string) ($data['year_level'] ?? ''));

        if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $passwordHash === '') {
            set_flash('danger', 'Registration data is incomplete. Please register again.');
            redirect('register.php');
        }

        $stmtEmail = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmtEmail->bind_param('s', $email);
        $stmtEmail->execute();
        $emailExists = $stmtEmail->get_result()->fetch_assoc();
        $stmtEmail->close();

        if ($emailExists) {
            set_flash('danger', 'Email is already registered.');
            redirect('register.php');
        }

        if (mobile_number_exists($conn, $phone)) {
            set_flash('danger', 'Mobile number is already registered.');
            redirect('register.php');
        }

        $stmt = $conn->prepare(
            "INSERT INTO users (role, first_name, middle_name, last_name, email, phone, password_hash, school_name, school_type, course, year_level, status)
             VALUES ('applicant', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->bind_param('ssssssssss', $firstName, $middleName, $lastName, $email, $phone, $passwordHash, $schoolName, $schoolType, $course, $yearLevel);
        $ok = $stmt->execute();
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
                'email' => $email,
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

        set_flash('danger', 'Registration failed. Please try again.');
        redirect('register.php');
    }

    if ($action === 'request_otp') {
        if (!$isRegistrationOpen) {
            set_flash('danger', 'Registration is currently closed. There is no open application period.');
            redirect('register.php');
        }

        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $middleName = trim((string) ($_POST['middle_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phoneInput = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $schoolName = trim((string) ($_POST['school_name'] ?? ''));
        $schoolType = trim((string) ($_POST['school_type'] ?? ''));
        $course = trim((string) ($_POST['course'] ?? ''));
        $yearLevel = trim((string) ($_POST['year_level'] ?? ''));
        $phone = normalize_mobile_number($phoneInput);

        if (!$firstName || !$lastName || !$email || !$phoneInput || !$password || !$confirmPassword) {
            set_flash('danger', 'Please fill in all required fields.');
            redirect('register.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Please use a valid email address.');
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

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $emailExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($emailExists) {
            set_flash('danger', 'Email is already registered.');
            redirect('register.php');
        }

        if (mobile_number_exists($conn, $phone)) {
            set_flash('danger', 'Mobile number is already registered.');
            redirect('register.php');
        }

        $registrationPayload = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'school_name' => $schoolName,
            'school_type' => $schoolType,
            'course' => $course,
            'year_level' => $yearLevel,
        ];

        $sendOtpForRegistration($registrationPayload);
        redirect('register.php?otp=1');
    }
}

$pendingOtp = otp_state('register_account');
$pendingRegistration = is_array($pendingOtp['payload']['registration'] ?? null)
    ? $pendingOtp['payload']['registration']
    : null;
$otpSecondsLeft = otp_seconds_left('register_account');

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10">
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
            <div class="card-body p-4">
                <div class="auth-logo-wrap">
                    <?php if ($hasAuthLogo): ?>
                        <img src="<?= e($authLogoRelativePath) ?>" alt="Municipality of San Enrique Official Seal" class="auth-card-logo">
                    <?php else: ?>
                        <span class="auth-logo-fallback" aria-hidden="true"><i class="fa-solid fa-shield"></i></span>
                    <?php endif; ?>
                </div>
                <h1 class="h4 mb-3">Create Applicant Account</h1>
                <form method="post" class="row g-3" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="request_otp">

                    <div class="col-12 col-md-6">
                        <label class="form-label">First Name *</label>
                        <input type="text" class="form-control" name="first_name" required value="<?= e(old('first_name', (string) ($pendingRegistration['first_name'] ?? ''))) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Last Name *</label>
                        <input type="text" class="form-control" name="last_name" required value="<?= e(old('last_name', (string) ($pendingRegistration['last_name'] ?? ''))) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="middle_name" value="<?= e(old('middle_name', (string) ($pendingRegistration['middle_name'] ?? ''))) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required value="<?= e(old('email', (string) ($pendingRegistration['email'] ?? ''))) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Mobile Number *</label>
                        <input type="text" class="form-control" name="phone" required placeholder="09XXXXXXXXX" value="<?= e(old('phone', (string) ($pendingRegistration['phone'] ?? ''))) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="registerPassword" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#registerPassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Confirm Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="registerConfirmPassword" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#registerConfirmPassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">School Name</label>
                        <input type="text" class="form-control" name="school_name" value="<?= e(old('school_name', (string) ($pendingRegistration['school_name'] ?? ''))) ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">School Type</label>
                        <?php $selectedSchoolType = old('school_type', (string) ($pendingRegistration['school_type'] ?? '')); ?>
                        <select class="form-select" name="school_type">
                            <option value="">Select</option>
                            <option value="public" <?= $selectedSchoolType === 'public' ? 'selected' : '' ?>>Public</option>
                            <option value="private" <?= $selectedSchoolType === 'private' ? 'selected' : '' ?>>Private</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Course</label>
                        <input type="text" class="form-control" name="course" value="<?= e(old('course', (string) ($pendingRegistration['course'] ?? ''))) ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Year Level</label>
                        <input type="text" class="form-control" name="year_level" value="<?= e(old('year_level', (string) ($pendingRegistration['year_level'] ?? ''))) ?>">
                    </div>

                    <div class="col-12 d-grid d-md-flex">
                        <button type="submit" class="btn btn-primary px-4" <?= !$isRegistrationOpen ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-paper-plane me-1"></i>Send Verification Code (OTP)
                        </button>
                    </div>
                </form>
                <p class="small text-muted mt-3 mb-0">
                    Already registered? <a href="login.php">Login here</a>.
                </p>
            </div>
        </div>

        <?php if ($pendingRegistration): ?>
            <div class="card card-soft shadow-sm">
                <div class="card-body p-4">
                    <div class="auth-logo-wrap">
                        <?php if ($hasAuthLogo): ?>
                            <img src="<?= e($authLogoRelativePath) ?>" alt="Municipality of San Enrique Official Seal" class="auth-card-logo">
                        <?php else: ?>
                            <span class="auth-logo-fallback" aria-hidden="true"><i class="fa-solid fa-shield"></i></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="h5 mb-2">Verify Code (OTP)</h2>
                    <p class="small text-muted mb-3">
                        Enter the verification code (OTP) sent to <?= e(mask_mobile_number((string) ($pendingRegistration['phone'] ?? ''))) ?>.
                        <?php if ($otpSecondsLeft > 0): ?>
                            Code expires in <?= (int) ceil($otpSecondsLeft / 60) ?> minute(s).
                        <?php endif; ?>
                    </p>
                    <form method="post" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="verify_otp">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Verification Code (OTP)</label>
                            <input type="text" name="otp_code" class="form-control" maxlength="8" required placeholder="6-digit code">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Verify & Create Account</button>
                        </div>
                    </form>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="resend_otp">
                            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-rotate me-1"></i>Resend Verification Code (OTP)</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="cancel_otp">
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-xmark me-1"></i>Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

