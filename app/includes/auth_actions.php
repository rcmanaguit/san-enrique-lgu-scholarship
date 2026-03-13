<?php
declare(strict_types=1);

function auth_login_form_phone_session_key(): string
{
    return 'login_form_phone';
}

function auth_redirect_authenticated_user(): void
{
    if (!is_logged_in()) {
        return;
    }

    if (user_has_role(['admin', 'staff'])) {
        redirect('shared/dashboard.php');
    }

    redirect('dashboard.php');
}

function auth_consume_login_form_phone(): string
{
    $sessionKey = auth_login_form_phone_session_key();
    if (!isset($_SESSION[$sessionKey])) {
        return '';
    }

    $mobile = trim((string) $_SESSION[$sessionKey]);
    unset($_SESSION[$sessionKey]);
    return $mobile;
}

function auth_remember_login_form_phone(string $mobile): void
{
    $_SESSION[auth_login_form_phone_session_key()] = $mobile;
}

function auth_is_registration_open(?mysqli $conn): bool
{
    return $conn instanceof mysqli && db_ready() && current_open_application_period($conn) !== null;
}

function auth_redirect_after_login(mysqli $conn, array $user): void
{
    if (in_array((string) ($user['role'] ?? ''), ['admin', 'staff'], true)) {
        redirect('shared/dashboard.php');
    }

    redirect('dashboard.php');
}

function auth_handle_login_request(mysqli $conn): void
{
    $mobileRaw = trim((string) ($_POST['phone'] ?? ''));
    $mobile = preg_replace('/\D+/', '', $mobileRaw) ?? '';
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request. Please try again.');
        redirect('login.php');
    }
    if (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('login.php');
    }
    if (!$mobile || !$password) {
        auth_remember_login_form_phone($mobile);
        set_flash('danger', 'Mobile number and password are required.');
        redirect('login.php');
    }

    $user = find_user_by_mobile($conn, $mobile);

    if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
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
        auth_remember_login_form_phone($mobile);
        set_flash('danger', 'Invalid login credentials.');
        redirect('login.php');
    }

    if ((string) ($user['status'] ?? '') !== 'active') {
        audit_log(
            $conn,
            'login_blocked_inactive',
            (int) ($user['id'] ?? 0),
            (string) ($user['role'] ?? 'applicant'),
            'auth',
            (string) ($user['id'] ?? ''),
            'Attempted login to inactive account.'
        );
        auth_remember_login_form_phone($mobile);
        set_flash('danger', 'Your account is inactive. Contact LGU staff.');
        redirect('login.php');
    }

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
    set_flash('success', 'Welcome back, ' . (string) ($user['first_name'] ?? 'User') . '.');

    auth_redirect_after_login($conn, $user);
}
