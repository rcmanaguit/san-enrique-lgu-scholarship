<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth_actions.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

auth_redirect_authenticated_user();

$pageTitle = 'Login';
$hideNavbar = true;
$hideFooter = true;
$bodyClass = 'auth-page';
$extraJs = ['assets/js/auth-forms.js'];
$authLogoRelativePath = 'assets/images/branding/lgu-logo.png';
$authLogoAbsolutePath = dirname(__DIR__, 3) . '/' . $authLogoRelativePath;
$hasAuthLogo = file_exists($authLogoAbsolutePath);
$isRegistrationOpen = auth_is_registration_open($conn);
$mobile = auth_consume_login_form_phone();

if (is_post()) {
    auth_handle_login_request($conn);
}

include __DIR__ . '/../../includes/header.php';
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
                <form method="post" novalidate data-auth-form="login">
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
                        <div class="form-text" data-feedback="login-phone">Use 11 digits starting with 09.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#password" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted" data-feedback="login-password">Enter your account password.</div>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

