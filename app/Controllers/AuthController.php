<?php

namespace App\Controllers;

use App\Config\Database;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Sms;
use App\Support\Validation;
use App\Support\ValidationException;
use Exception;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

class AuthController
{
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;

    public function accountSettings()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \base_url('login'));
            exit;
        }

        $accountUser = User::findById((int) $_SESSION['user_id']);
        if (!$accountUser) {
            session_unset();
            session_destroy();
            header('Location: ' . \base_url('login'));
            exit;
        }

        $accountSettingsOldInput = $_SESSION['_account_settings_old_input'] ?? [];
        unset($_SESSION['_account_settings_old_input']);
        if (!is_array($accountSettingsOldInput)) {
            $accountSettingsOldInput = [];
        }

        require __DIR__ . '/../../views/account/settings.php';
    }

    public function updateAccountSettings()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \base_url('login'));
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $user = User::findById($userId);
        if (!$user) {
            session_unset();
            session_destroy();
            header('Location: ' . \base_url('login'));
            exit;
        }

        try {
            $isInternalUser = in_array((string) ($user['role'] ?? ''), ['Staff', 'Admin'], true);
            $currentPhone = trim((string) ($user['phone_number'] ?? ''));
            $currentEmail = trim((string) ($user['email'] ?? ''));
            $currentFirstName = trim((string) ($user['first_name'] ?? ''));
            $currentLastName = trim((string) ($user['last_name'] ?? ''));
            $settingsSection = trim((string) ($_POST['settings_section'] ?? 'profile'));
            if (!in_array($settingsSection, ['profile', 'password'], true)) {
                $settingsSection = 'profile';
            }

            if ($settingsSection === 'password') {
                $currentPassword = Validation::requiredString($_POST['current_password'] ?? '', 'Current password', 255);
                if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
                    throw new ValidationException('Current password is incorrect.');
                }

                $newPasswordInput = (string) ($_POST['new_password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
                if ($newPasswordInput === '') {
                    throw new ValidationException('Enter a new password first.');
                }

                $newPassword = Validation::password($newPasswordInput);
                if (!hash_equals($newPassword, $confirmPassword)) {
                    throw new ValidationException('Confirm New Password must match your new password.');
                }

                User::updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
                User::clearOtp($userId);

                AuditLog::recordCurrentUser(
                    'account.password_updated',
                    'user',
                    $userId,
                    'User updated their password.',
                    ['password_changed' => true]
                );

                redirect_with_flash('account/settings', 'success', 'Your password was updated successfully.');
            }

            $currentPassword = Validation::requiredString($_POST['current_password'] ?? '', 'Current password', 255);
            if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
                throw new ValidationException('Current password is incorrect.');
            }

            $newFirstName = $isInternalUser
                ? Validation::requiredString($_POST['first_name'] ?? '', 'First name', 100)
                : trim((string) ($user['first_name'] ?? ''));
            $newLastName = $isInternalUser
                ? Validation::requiredString($_POST['last_name'] ?? '', 'Last name', 100)
                : trim((string) ($user['last_name'] ?? ''));
            $newPhoneInput = trim((string) ($_POST['phone_number'] ?? ''));
            $newEmailInput = trim((string) ($_POST['email'] ?? ''));

            $normalizedPhone = Validation::phone($newPhoneInput);
            $normalizedEmail = $newEmailInput !== '' ? Validation::email($newEmailInput) : null;

            $nameChanged = $isInternalUser && ($newFirstName !== $currentFirstName || $newLastName !== $currentLastName);
            $phoneChanged = $normalizedPhone !== $currentPhone;
            $emailChanged = ($normalizedEmail ?? '') !== $currentEmail;

            if (!$nameChanged && !$phoneChanged && !$emailChanged) {
                throw new ValidationException('No account changes were provided.');
            }

            if ($phoneChanged) {
                $existingPhoneUser = User::findByPhone($normalizedPhone);
                if ($existingPhoneUser && (int) ($existingPhoneUser['id'] ?? 0) !== $userId) {
                    throw new ValidationException('That mobile number is already used by another account.');
                }
            }

            if ($emailChanged && $normalizedEmail !== null) {
                $existingEmailUser = User::findByEmail($normalizedEmail);
                if ($existingEmailUser && (int) ($existingEmailUser['id'] ?? 0) !== $userId) {
                    throw new ValidationException('That email address is already used by another account.');
                }
            }

            if ($nameChanged) {
                User::updateName($userId, $newFirstName, $newLastName);
                $_SESSION['first_name'] = $newFirstName;
                $_SESSION['last_name'] = $newLastName;
            }

            User::updateRecoveryContact($userId, $phoneChanged ? $normalizedPhone : null, $emailChanged ? $normalizedEmail : null);

            AuditLog::recordCurrentUser(
                'account.profile_updated',
                'user',
                $userId,
                'User updated account profile details.',
                [
                    'name_changed' => $nameChanged,
                    'phone_changed' => $phoneChanged,
                    'email_changed' => $emailChanged,
                ]
            );

            redirect_with_flash('account/settings', 'success', 'Your account details were updated successfully.');
        } catch (ValidationException $e) {
            $_SESSION['_account_settings_old_input'] = [
                'settings_section' => trim((string) ($_POST['settings_section'] ?? 'profile')),
                'first_name' => trim((string) ($_POST['first_name'] ?? '')),
                'last_name' => trim((string) ($_POST['last_name'] ?? '')),
                'phone_number' => trim((string) ($_POST['phone_number'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
            ];
            redirect_with_flash('account/settings', 'error', $e->getMessage());
        } catch (Exception $e) {
            error_log('Failed to update account settings: ' . $e->getMessage());
            redirect_with_flash('account/settings', 'error', 'Unable to update your account settings right now.');
        }
    }

    // ---------------------------------------------------------
    // LOGIN LOGIC
    // ---------------------------------------------------------
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $phone = Validation::phone($_POST['phone_number'] ?? '');
            $password = Validation::password($_POST['password'] ?? '');
        } catch (ValidationException $e) {
            redirect_with_flash('login', 'error', $e->getMessage());
        }

        $db = Database::connect();

        // Secure Prepared Statement to prevent SQL Injection
        $stmt = $db->prepare("SELECT * FROM users WHERE phone_number = :phone LIMIT 1");
        $stmt->execute(['phone' => $phone]);
        $user = $stmt->fetch();

        // Verify password and check if they exist
        if ($user && password_verify($password, $user['password_hash'])) {
            if ((int) ($user['is_active'] ?? 1) !== 1) {
                AuditLog::record((int) $user['id'], (string) ($user['role'] ?? 'Student'), 'auth.login_blocked_inactive', 'user', (int) $user['id'], 'Login blocked because the account is inactive.');
                redirect_with_flash('login', 'error', 'This account is inactive. Please contact the LGU office.');
            }

            // Check if OTP verified
            if ($user['is_verified'] == 0) {
                $_SESSION['temp_user_id'] = $user['id'];
                AuditLog::record((int) $user['id'], (string) ($user['role'] ?? 'Student'), 'auth.login_pending_verification', 'user', (int) $user['id'], 'Login blocked because OTP verification is still required.');
                header('Location: ' . \base_url('verify-otp'));
                exit;
            }

            // Set Session Variables
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = (string) ($user['first_name'] ?? '');
            $_SESSION['last_name'] = (string) ($user['last_name'] ?? '');

            // Route them to their correct dashboard
            if ($user['role'] === 'Admin')
                header('Location: ' . \base_url('admin/dashboard'));
            elseif ($user['role'] === 'Staff')
                header('Location: ' . \base_url('staff/dashboard'));
            else
                header('Location: ' . \base_url('student/dashboard'));

            AuditLog::record((int) $user['id'], (string) ($user['role'] ?? 'Student'), 'auth.login_success', 'user', (int) $user['id'], 'User logged in successfully.');
            exit;

        } else {
            AuditLog::record(null, 'Guest', 'auth.login_failed', 'user', null, 'Login failed due to invalid credentials.', ['phone_number' => $phone]);
            redirect_with_flash('login', 'error', 'Invalid credentials. Please try again.');
        }
    }

    // ---------------------------------------------------------
    // REGISTRATION & OTP LOGIC
    // ---------------------------------------------------------
    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $firstName = Validation::requiredString($_POST['first_name'] ?? '', 'First name', 100);
            $lastName = Validation::requiredString($_POST['last_name'] ?? '', 'Last name', 100);
            $phone = Validation::phone($_POST['phone_number'] ?? '');
            $password = Validation::password($_POST['password'] ?? '');
        } catch (ValidationException $e) {
            redirect_with_flash('register', 'error', $e->getMessage());
        }

        // Hash the password securely!
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Generate a 6-digit OTP
        $otp = (string) random_int(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $db = Database::connect();

        try {
            $stmt = $db->prepare("
                INSERT INTO users (role, first_name, last_name, phone_number, password_hash, otp_code, otp_expires_at)
                VALUES ('Student', :first_name, :last_name, :phone, :pass, :otp, :expires)
            ");
            $stmt->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'pass' => $hashed_password,
                'otp' => $otp,
                'expires' => $expires_at,
            ]);

            $userId = $db->lastInsertId();
            $_SESSION['temp_user_id'] = $userId; // Temporarily log them in for the OTP screen
            $_SESSION['verification_otp_sent_at'] = time();

            // ==========================================
            // TEXTBEE API INTEGRATION (Trigger SMS)
            // ==========================================
            Sms::sendTextbee($phone, "San Enrique LGU Scholarship: Your verification code is $otp. Expires in 5 minutes.");

            AuditLog::record((int) $userId, 'Student', 'auth.registered', 'user', (int) $userId, 'Student account registered and OTP sent.', ['phone_number' => $phone]);

            header('Location: ' . \base_url('verify-otp'));
            exit;

        } catch (Exception $e) {
            AuditLog::record(null, 'Guest', 'auth.registration_failed', 'user', null, 'Registration failed.', ['phone_number' => $phone]);
            redirect_with_flash('register', 'error', 'Registration failed. That number may already be registered.');
        }
    }

    public function verifyOtp()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $userId = $_SESSION['temp_user_id'] ?? null;
        try {
            $otpCode = Validation::otp($_POST['otp_code'] ?? '');
        } catch (ValidationException $e) {
            redirect_with_flash('verify-otp', 'error', $e->getMessage());
        }

        if (!$userId || $otpCode === '') {
            redirect_with_flash('register', 'error', 'Verification session expired. Please register or log in again.');
        }

        if (!User::verifyOTP($userId, $otpCode)) {
            AuditLog::record((int) $userId, 'Student', 'auth.otp_verification_failed', 'user', (int) $userId, 'OTP verification failed.');
            redirect_with_flash('verify-otp', 'error', 'Invalid or expired OTP code. Please try again.');
        }

        $user = User::findById($userId);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = (string) ($user['first_name'] ?? '');
        $_SESSION['last_name'] = (string) ($user['last_name'] ?? '');
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['verification_otp_sent_at']);

        AuditLog::record((int) $user['id'], (string) ($user['role'] ?? 'Student'), 'auth.otp_verified', 'user', (int) $user['id'], 'OTP verified and account activated.');

        header('Location: ' . \base_url('student/dashboard'));
        exit;
    }

    public function resendVerificationOtp()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $userId = $_SESSION['temp_user_id'] ?? null;
        if (!$userId) {
            redirect_with_flash('register', 'error', 'Your verification session expired. Please register again.');
        }

        $lastSentAt = (int) ($_SESSION['verification_otp_sent_at'] ?? 0);
        $secondsRemaining = self::OTP_RESEND_COOLDOWN_SECONDS - (time() - $lastSentAt);
        if ($lastSentAt > 0 && $secondsRemaining > 0) {
            redirect_with_flash('verify-otp', 'error', "Please wait {$secondsRemaining} seconds before requesting a new code.");
        }

        $user = User::findById((int) $userId);
        if (!$user || (int) ($user['is_verified'] ?? 0) === 1) {
            unset($_SESSION['temp_user_id'], $_SESSION['verification_otp_sent_at']);
            redirect_with_flash('login', 'error', 'This account no longer needs OTP verification. Please log in.');
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        if (!User::storeOtp((int) $userId, $otp, $expiresAt)) {
            redirect_with_flash('verify-otp', 'error', 'Unable to resend the verification code right now.');
        }

        $_SESSION['verification_otp_sent_at'] = time();
        Sms::sendTextbee((string) $user['phone_number'], "San Enrique LGU Scholarship: Your verification code is $otp. Expires in 5 minutes.");

        AuditLog::record((int) $userId, (string) ($user['role'] ?? 'Student'), 'auth.otp_resent', 'user', (int) $userId, 'Verification OTP was resent.');

        redirect_with_flash('verify-otp', 'success', 'A new verification code was sent to your mobile number.');
    }

    public function cancelVerificationOtp()
    {
        unset($_SESSION['temp_user_id'], $_SESSION['verification_otp_sent_at']);
        redirect_with_flash('login', 'success', 'OTP verification was cancelled. You can log in or register again anytime.');
    }

    public function forgotPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $phone = Validation::phone($_POST['phone_number'] ?? '');
        } catch (ValidationException $e) {
            redirect_with_flash('forgot-password', 'error', $e->getMessage());
        }

        $user = User::findByPhone($phone);
        if (!$user) {
            redirect_with_flash('forgot-password', 'error', 'No account was found for that mobile number.');
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        User::storeOtp((int) $user['id'], $otp, $expiresAt);

        $_SESSION['password_reset_user_id'] = $user['id'];
        Sms::sendTextbee($phone, "San Enrique LGU Scholarship: Your password reset code is $otp. Expires in 5 minutes.");

        AuditLog::record((int) $user['id'], (string) ($user['role'] ?? 'Student'), 'auth.password_reset_otp_sent', 'user', (int) $user['id'], 'Password reset OTP sent via mobile number.');

        redirect_with_flash('reset-password', 'success', 'A password reset code was sent to your mobile number.');
    }

    public function resetPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $userId = $_SESSION['password_reset_user_id'] ?? null;
        if (!$userId) {
            redirect_with_flash('forgot-password', 'error', 'Password reset session expired. Please request a new code.');
        }

        try {
            $otpCode = Validation::otp($_POST['otp_code'] ?? '');
            $password = Validation::password($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            $newPhone = trim((string) ($_POST['new_phone_number'] ?? ''));
        } catch (ValidationException $e) {
            redirect_with_flash('reset-password', 'error', $e->getMessage());
        }

        if (!hash_equals($password, $confirmPassword)) {
            redirect_with_flash('reset-password', 'error', 'Passwords do not match.');
        }

        if (!User::hasValidOtp((int) $userId, $otpCode)) {
            AuditLog::record((int) $userId, 'Student', 'auth.password_reset_failed', 'user', (int) $userId, 'Password reset failed because OTP was invalid or expired.');
            redirect_with_flash('reset-password', 'error', 'Invalid or expired reset code.');
        }

        $normalizedNewPhone = null;
        if ($newPhone !== '') {
            try {
                $normalizedNewPhone = Validation::phone($newPhone);
            } catch (ValidationException $e) {
                redirect_with_flash('reset-password', 'error', $e->getMessage());
            }

            $existingPhoneUser = User::findByPhone($normalizedNewPhone);
            if ($existingPhoneUser && (int) $existingPhoneUser['id'] !== (int) $userId) {
                redirect_with_flash('reset-password', 'error', 'That mobile number is already used by another account.');
            }
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        User::updatePassword((int) $userId, $passwordHash);
        if ($normalizedNewPhone !== null) {
            User::updateRecoveryContact((int) $userId, $normalizedNewPhone, null);
        }
        User::clearOtp((int) $userId);
        unset($_SESSION['password_reset_user_id']);

        AuditLog::record((int) $userId, 'Student', 'auth.password_reset_completed', 'user', (int) $userId, 'Password reset completed.', ['mobile_number_replaced' => $normalizedNewPhone !== null]);

        redirect_with_flash('login', 'success', 'Your password has been reset. You can now log in.');
    }

    public function recoverAccount()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            $email = Validation::email($_POST['email'] ?? '');
        } catch (ValidationException $e) {
            redirect_with_flash('recover-account', 'error', $e->getMessage());
        }

        $user = User::findByEmail($email);
        if (!$user) {
            redirect_with_flash('recover-account', 'error', 'No account matched the provided email address.');
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        User::storeOtp((int) $user['id'], $otp, $expiresAt);
        $_SESSION['password_reset_user_id'] = $user['id'];

        if (!$this->sendRecoveryEmail((string) $user['email'], $otp)) {
            AuditLog::record((int) $user['id'], (string) ($user['role'] ?? 'Student'), 'auth.email_recovery_failed', 'user', (int) $user['id'], 'Email recovery OTP could not be sent.');
            redirect_with_flash('recover-account', 'error', 'Unable to send the recovery code by email right now.');
        }

        AuditLog::record((int) $user['id'], (string) ($user['role'] ?? 'Student'), 'auth.email_recovery_otp_sent', 'user', (int) $user['id'], 'Password reset OTP sent via email.');

        redirect_with_flash('reset-password', 'success', 'A password reset code was sent to your email address.');
    }

    public function logout()
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $role = isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
        if ($userId !== null) {
            AuditLog::record($userId, $role, 'auth.logout', 'user', $userId, 'User logged out.');
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();

        header('Location: ' . \base_url('login'));
        exit;
    }

    private function sendRecoveryEmail(string $emailAddress, string $otpCode): bool
    {
        $mailHost = trim((string) ($_ENV['MAIL_HOST'] ?? ''));
        $mailPort = (int) ($_ENV['MAIL_PORT'] ?? 0);
        $mailUsername = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
        $mailPassword = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $mailFromAddress = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? ''));
        $mailFromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'San Enrique LGU Scholarship'));
        $mailEncryption = strtolower(trim((string) ($_ENV['MAIL_ENCRYPTION'] ?? 'tls')));

        if (
            $mailHost === ''
            || $mailPort <= 0
            || $mailUsername === ''
            || $mailPassword === ''
            || $mailFromAddress === ''
        ) {
            error_log('Email recovery code failed to send because SMTP settings are incomplete.');
            return false;
        }

        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $mailHost;
            $mailer->Port = $mailPort;
            $mailer->SMTPAuth = true;
            $mailer->Username = $mailUsername;
            $mailer->Password = $mailPassword;
            $mailer->CharSet = 'UTF-8';

            if ($mailEncryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($mailEncryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mailer->setFrom($mailFromAddress, $mailFromName);
            $mailer->addAddress($emailAddress);
            $mailer->isHTML(false);
            $mailer->Subject = 'San Enrique LGU Scholarship Password Reset Code';
            $mailer->Body = "Your password reset code is {$otpCode}. It will expire in 5 minutes.";
            $mailer->send();
            return true;
        } catch (MailException $e) {
            error_log('Email recovery code failed to send to ' . $emailAddress . ': ' . $e->getMessage());
            return false;
        }
    }
}
