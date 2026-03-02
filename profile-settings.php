<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login('login.php');

$pageTitle = 'Profile & Settings';
$sessionUser = current_user();
$userId = (int) ($sessionUser['id'] ?? 0);
$profile = $sessionUser ?: [];
$isApplicant = (($sessionUser['role'] ?? '') === 'applicant');

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('profile-settings.php');
    }

    if (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('profile-settings.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'update_profile') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $middleName = trim((string) ($_POST['middle_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $schoolName = trim((string) ($_POST['school_name'] ?? ''));
        $schoolType = trim((string) ($_POST['school_type'] ?? ''));
        $course = trim((string) ($_POST['course'] ?? ''));
        $yearLevel = trim((string) ($_POST['year_level'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if ($firstName === '' || $lastName === '' || $email === '') {
            set_flash('danger', 'First name, last name, and email are required.');
            redirect('profile-settings.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Please enter a valid email address.');
            redirect('profile-settings.php');
        }

        $stmtEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        if ($stmtEmail) {
            $stmtEmail->bind_param('si', $email, $userId);
            $stmtEmail->execute();
            $emailExists = $stmtEmail->get_result()->fetch_assoc();
            $stmtEmail->close();
            if ($emailExists) {
                set_flash('danger', 'Email is already used by another account.');
                redirect('profile-settings.php');
            }
        }

        $stmt = $conn->prepare(
            "UPDATE users
             SET first_name = ?, middle_name = ?, last_name = ?, email = ?, school_name = ?, school_type = ?, course = ?, year_level = ?, address = ?
             WHERE id = ?
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param(
                'sssssssssi',
                $firstName,
                $middleName,
                $lastName,
                $email,
                $schoolName,
                $schoolType,
                $course,
                $yearLevel,
                $address,
                $userId
            );
            $stmt->execute();
            $stmt->close();
        }

        $_SESSION['user']['first_name'] = $firstName;
        $_SESSION['user']['middle_name'] = $middleName;
        $_SESSION['user']['last_name'] = $lastName;
        $_SESSION['user']['email'] = $email;
        if ($isApplicant) {
            $_SESSION['user']['school_name'] = $schoolName;
            $_SESSION['user']['school_type'] = $schoolType;
            $_SESSION['user']['course'] = $course;
            $_SESSION['user']['year_level'] = $yearLevel;
            $_SESSION['user']['address'] = $address;
        }

        audit_log(
            $conn,
            'profile_updated',
            $userId,
            (string) ($sessionUser['role'] ?? ''),
            'user',
            (string) $userId,
            'User profile updated.'
        );
        set_flash('success', 'Profile updated successfully.');
        redirect('profile-settings.php');
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            set_flash('danger', 'Please complete all password fields.');
            redirect('profile-settings.php');
        }
        if (strlen($newPassword) < 8) {
            set_flash('danger', 'New password must be at least 8 characters.');
            redirect('profile-settings.php');
        }
        if ($newPassword !== $confirmPassword) {
            set_flash('danger', 'New password and confirm password do not match.');
            redirect('profile-settings.php');
        }

        $stmtHash = $conn->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        if (!$stmtHash) {
            set_flash('danger', 'Unable to verify password right now.');
            redirect('profile-settings.php');
        }
        $stmtHash->bind_param('i', $userId);
        $stmtHash->execute();
        $storedHash = (string) (($stmtHash->get_result()->fetch_assoc()['password_hash'] ?? ''));
        $stmtHash->close();

        if ($storedHash === '' || !password_verify($currentPassword, $storedHash)) {
            set_flash('danger', 'Current password is incorrect.');
            redirect('profile-settings.php');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmtUpdate = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
        if ($stmtUpdate) {
            $stmtUpdate->bind_param('si', $newHash, $userId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }

        audit_log(
            $conn,
            'password_changed',
            $userId,
            (string) ($sessionUser['role'] ?? ''),
            'user',
            (string) $userId,
            'User password changed from profile settings.'
        );
        create_notification(
            $conn,
            $userId,
            'Password Changed',
            'Your password was updated successfully. If this was not you, please contact the scholarship office immediately.',
            'security',
            'profile-settings.php',
            $userId
        );
        set_flash('success', 'Password updated successfully.');
        redirect('profile-settings.php');
    }
}

if (db_ready()) {
    $stmt = $conn->prepare(
        "SELECT id, role, first_name, middle_name, last_name, email, phone, school_name, school_type, course, year_level, address, created_at
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $profile = $row;
            $isApplicant = (($profile['role'] ?? '') === 'applicant');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-user-gear me-2 text-primary"></i>Profile & Settings</h1>
    <a href="<?= user_has_role(['admin', 'staff']) ? 'shared/dashboard.php' : 'dashboard.php' ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
    </a>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card card-soft shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Basic Profile</h2>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="col-12 col-md-4">
                        <label class="form-label">First Name *</label>
                        <input type="text" class="form-control" name="first_name" required value="<?= e((string) ($profile['first_name'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="middle_name" value="<?= e((string) ($profile['middle_name'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Last Name *</label>
                        <input type="text" class="form-control" name="last_name" required value="<?= e((string) ($profile['last_name'] ?? '')) ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?= e((string) ($profile['email'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" class="form-control" value="<?= e((string) ($profile['phone'] ?? '')) ?>" disabled>
                    </div>

                    <?php if ($isApplicant): ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label">School Name</label>
                            <input type="text" class="form-control" name="school_name" value="<?= e((string) ($profile['school_name'] ?? '')) ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">School Type</label>
                            <?php $selectedSchoolType = (string) ($profile['school_type'] ?? ''); ?>
                            <select class="form-select" name="school_type">
                                <option value="">Select</option>
                                <option value="public" <?= $selectedSchoolType === 'public' ? 'selected' : '' ?>>Public</option>
                                <option value="private" <?= $selectedSchoolType === 'private' ? 'selected' : '' ?>>Private</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Year Level</label>
                            <input type="text" class="form-control" name="year_level" value="<?= e((string) ($profile['year_level'] ?? '')) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-control" name="course" value="<?= e((string) ($profile['course'] ?? '')) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" value="<?= e((string) ($profile['address'] ?? '')) ?>">
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="school_name" value="<?= e((string) ($profile['school_name'] ?? '')) ?>">
                        <input type="hidden" name="school_type" value="<?= e((string) ($profile['school_type'] ?? '')) ?>">
                        <input type="hidden" name="course" value="<?= e((string) ($profile['course'] ?? '')) ?>">
                        <input type="hidden" name="year_level" value="<?= e((string) ($profile['year_level'] ?? '')) ?>">
                        <input type="hidden" name="address" value="<?= e((string) ($profile['address'] ?? '')) ?>">
                    <?php endif; ?>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">Security</h2>
                <form method="post" class="row g-2">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="col-12">
                        <label class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="current_password" id="profileCurrentPassword" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#profileCurrentPassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password" id="profileNewPassword" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#profileNewPassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="profileConfirmPassword" required>
                            <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#profileConfirmPassword" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fa-solid fa-key me-1"></i>Change Password
                        </button>
                    </div>
                </form>

                <hr>
                <a href="account-security.php" class="btn btn-outline-secondary w-100">
                    <i class="fa-solid fa-mobile-screen-button me-1"></i>Change Mobile Number (Verification Code/OTP)
                </a>
            </div>
        </div>

        <div class="card card-soft shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-2">Account Info</h2>
                <p class="small mb-1"><strong>Role:</strong> <?= e(strtoupper((string) ($profile['role'] ?? ''))) ?></p>
                <p class="small mb-0"><strong>Created:</strong> <?= !empty($profile['created_at']) ? date('M d, Y h:i A', strtotime((string) $profile['created_at'])) : '-' ?></p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
