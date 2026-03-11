<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

if (is_logged_in()) {
    if (user_has_role(['admin', 'staff'])) {
        redirect('shared/dashboard.php');
    }
    redirect('dashboard.php');
}

$pageTitle = 'Login';
$hideNavbar = true;
$hideFooter = true;
$bodyClass = 'auth-page';
$mobile = '';
$loginFormPhoneSessionKey = 'login_form_phone';
$authLogoRelativePath = 'assets/images/branding/lgu-logo.png';
$authLogoAbsolutePath = __DIR__ . '/' . $authLogoRelativePath;
$hasAuthLogo = file_exists($authLogoAbsolutePath);
$isRegistrationOpen = db_ready() && current_open_application_period($conn) !== null;

if (isset($_SESSION[$loginFormPhoneSessionKey])) {
    $mobile = trim((string) $_SESSION[$loginFormPhoneSessionKey]);
    unset($_SESSION[$loginFormPhoneSessionKey]);
}

if (is_post()) {
    $mobileRaw = trim((string) ($_POST['phone'] ?? ''));
    $mobile = preg_replace('/\D+/', '', $mobileRaw) ?? '';
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('login.php');
    } elseif (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('login.php');
    } elseif (!$mobile || !$password) {
        $_SESSION[$loginFormPhoneSessionKey] = $mobile;
        set_flash('danger', 'Mobile number and password are required.');
        redirect('login.php');
    } else {
        $user = find_user_by_mobile($conn, $mobile);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            audit_log(
                $conn,
                'login_failed',
                null,
                'guest',
                'auth',
                null,
                'Invalid login credentials.',
                [
                    'phone_input' => normalize_mobile_number($mobile),
                ]
            );
            $_SESSION[$loginFormPhoneSessionKey] = $mobile;
            set_flash('danger', 'Invalid login credentials.');
            redirect('login.php');
        } elseif ($user['status'] !== 'active') {
            audit_log(
                $conn,
                'login_blocked_inactive',
                (int) ($user['id'] ?? 0),
                (string) ($user['role'] ?? 'applicant'),
                'auth',
                (string) ($user['id'] ?? ''),
                'Attempted login to inactive account.'
            );
            $_SESSION[$loginFormPhoneSessionKey] = $mobile;
            set_flash('danger', 'Your account is inactive. Contact LGU staff.');
            redirect('login.php');
        } else {
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            audit_log(
                $conn,
                'login_success',
                (int) ($user['id'] ?? 0),
                (string) ($user['role'] ?? 'applicant'),
                'auth',
                (string) ($user['id'] ?? ''),
                'User logged in successfully.'
            );
            set_flash('success', 'Welcome back, ' . $user['first_name'] . '.');

            if (in_array($user['role'], ['admin', 'staff'], true)) {
                redirect('shared/dashboard.php');
            }

            $resumeStep = 0;
            if (db_ready()) {
                $draft = wizard_load_persistent_draft($conn, (int) $user['id']);
                if (is_array($draft['state'] ?? null) && wizard_has_progress((array) $draft['state'])) {
                    $resumeStep = (int) ($draft['current_step'] ?? 0);
                    if ($resumeStep < 1 || $resumeStep > 6) {
                        $resumeStep = wizard_resume_step((array) $draft['state']);
                    }
                }
            }

            $openPeriod = current_open_application_period($conn);
            $canResumeApplication = $openPeriod !== null
                && !applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod);

            if ($resumeStep >= 1 && $resumeStep <= 6 && $canResumeApplication) {
                redirect('apply.php?step=' . $resumeStep);
            }

            redirect('dashboard.php');
        }
    }
}

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
                <p class="public-kicker text-center mb-2">Portal Login</p>
                <h1 class="h4 mb-2 text-center">Login to your account</h1>
                <p class="text-muted small text-center mb-4">Applicants can log in here to track their application. Staff, admin, and scholars can also use the same login page.</p>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Mobile Number</label>
                        <input
                            type="text"
                            class="form-control"
                            id="phone"
                            name="phone"
                            required
                            placeholder="09XXXXXXXXX"
                            value="<?= e($mobile) ?>"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            autocomplete="tel-national"
                            maxlength="12"
                        >
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#password" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 small text-muted mt-3 mb-0">
                    <?php if ($isRegistrationOpen): ?>
                        <a href="register.php">Create applicant account</a>
                    <?php else: ?>
                        <span class="text-muted" aria-disabled="true">Application Period Closed</span>
                    <?php endif; ?>
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    <a href="index.php"><i class="fa-solid fa-arrow-left me-1"></i>Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mobileInput = document.getElementById('phone');
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
