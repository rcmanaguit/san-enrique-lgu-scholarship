<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;
require_login('../login.php');
require_admin('../index.php');

$pageTitle = 'Manage Staff';
$staffRows = [];

$normalizeText = static function (string $v, int $len = 120): string {
    $v = trim($v);
    if ($v === '') {
        return '';
    }
    return function_exists('mb_substr') ? mb_substr($v, 0, $len) : substr($v, 0, $len);
};

$emailExists = static function (mysqli $conn, string $email, int $excludeId = 0): bool {
    if ($excludeId > 0) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param('si', $email, $excludeId);
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param('s', $email);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) $row;
};

$staffById = static function (mysqli $conn, int $id): ?array {
    if ($id <= 0) {
        return null;
    }
    $stmt = $conn->prepare(
        "SELECT id, first_name, middle_name, last_name, email, phone, status
         FROM users
         WHERE id = ? AND role = 'staff'
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
};

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Your session expired. Please try again.');
        redirect('staff.php');
    }
    if (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('staff.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $adminId = (int) (current_user()['id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $firstName = $normalizeText((string) ($_POST['first_name'] ?? ''), 100);
        $middleName = $normalizeText((string) ($_POST['middle_name'] ?? ''), 100);
        $lastName = $normalizeText((string) ($_POST['last_name'] ?? ''), 100);
        $email = strtolower($normalizeText((string) ($_POST['email'] ?? ''), 150));
        $phone = normalize_mobile_number((string) ($_POST['phone'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'active'));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $errors = [];

        if ($action === 'update' && !$staffById($conn, $staffId)) {
            $errors[] = 'Staff account not found.';
        }
        if ($firstName === '' || $lastName === '') {
            $errors[] = 'First name and last name are required.';
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!is_valid_mobile_number($phone)) {
            $errors[] = 'Please enter a valid mobile number (09XXXXXXXXX).';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }
        if ($email !== '' && $emailExists($conn, $email, $action === 'update' ? $staffId : 0)) {
            $errors[] = 'Email address is already used by another account.';
        }
        if ($phone !== '' && mobile_number_exists($conn, $phone, $action === 'update' ? $staffId : 0)) {
            $errors[] = 'Mobile number is already used by another account.';
        }

        if ($action === 'create') {
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if ($password !== $confirmPassword) {
                $errors[] = 'Password and confirm password do not match.';
            }
        } elseif ($password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }
            if ($password !== $confirmPassword) {
                $errors[] = 'New password and confirm password do not match.';
            }
        }

        if ($errors) {
            foreach ($errors as $err) {
                set_flash('danger', $err);
            }
            redirect('staff.php');
        }

        $middleName = $middleName !== '' ? $middleName : null;
        if ($action === 'create') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO users (role, first_name, middle_name, last_name, email, phone, password_hash, status)
                 VALUES ('staff', ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt) {
                set_flash('danger', 'Could not create staff account right now. Please try again.');
                redirect('staff.php');
            }
            $stmt->bind_param('sssssss', $firstName, $middleName, $lastName, $email, $phone, $hash, $status);
            $ok = $stmt->execute();
            $errorNo = (int) ($stmt->errno ?? 0);
            $newId = (int) $stmt->insert_id;
            $stmt->close();

            if (!$ok || $newId <= 0) {
                if ($errorNo === 1062) {
                    set_flash('danger', 'Mobile number or email is already used by another account.');
                    redirect('staff.php');
                }
                set_flash('danger', 'Could not create staff account right now. Please try again.');
                redirect('staff.php');
            }

            audit_log($conn, 'staff_account_created', $adminId > 0 ? $adminId : null, 'admin', 'user', (string) $newId, 'Staff account created.', [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
            ]);
            create_notification($conn, $newId, 'Staff Account Created', 'Your staff account is ready. You can now login using your mobile number and password.', 'security', 'shared/dashboard.php', $adminId > 0 ? $adminId : null);
            set_flash('success', 'Staff account added successfully.');
            redirect('staff.php');
        }

        $passwordChanged = $password !== '';
        if ($passwordChanged) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE users
                 SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, status = ?, password_hash = ?
                 WHERE id = ? AND role = 'staff' LIMIT 1"
            );
            if (!$stmt) {
                set_flash('danger', 'Could not update staff account right now. Please try again.');
                redirect('staff.php');
            }
            $stmt->bind_param('sssssssi', $firstName, $middleName, $lastName, $email, $phone, $status, $hash, $staffId);
        } else {
            $stmt = $conn->prepare(
                "UPDATE users
                 SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, status = ?
                 WHERE id = ? AND role = 'staff' LIMIT 1"
            );
            if (!$stmt) {
                set_flash('danger', 'Could not update staff account right now. Please try again.');
                redirect('staff.php');
            }
            $stmt->bind_param('ssssssi', $firstName, $middleName, $lastName, $email, $phone, $status, $staffId);
        }
        $ok = $stmt->execute();
        $errorNo = (int) ($stmt->errno ?? 0);
        $stmt->close();
        if (!$ok) {
            if ($errorNo === 1062) {
                set_flash('danger', 'Mobile number or email is already used by another account.');
                redirect('staff.php');
            }
            set_flash('danger', 'Could not update staff account right now. Please try again.');
            redirect('staff.php');
        }

        audit_log($conn, 'staff_account_updated', $adminId > 0 ? $adminId : null, 'admin', 'user', (string) $staffId, 'Staff account updated.', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'status' => $status,
            'password_changed' => $passwordChanged,
        ]);
        $msg = $status === 'inactive'
            ? 'Your staff account was set to inactive. Please contact the administrator for reactivation.'
            : 'Your staff account details were updated.';
        create_notification($conn, $staffId, 'Staff Account Updated', $msg, 'security', 'profile-settings.php', $adminId > 0 ? $adminId : null);
        set_flash('success', 'Staff account updated successfully.');
        redirect('staff.php');
    }

    if ($action === 'delete') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $staff = $staffById($conn, $staffId);
        if (!$staff) {
            set_flash('danger', 'Staff account not found.');
            redirect('staff.php');
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'staff' LIMIT 1");
        if (!$stmt) {
            set_flash('danger', 'Could not delete staff account right now. Please try again.');
            redirect('staff.php');
        }
        $stmt->bind_param('i', $staffId);
        $stmt->execute();
        $affected = (int) $stmt->affected_rows;
        $stmt->close();

        if ($affected <= 0) {
            set_flash('danger', 'Could not delete staff account right now. Please try again.');
            redirect('staff.php');
        }

        $name = trim((string) ($staff['first_name'] ?? '') . ' ' . (string) ($staff['middle_name'] ?? '') . ' ' . (string) ($staff['last_name'] ?? ''));
        audit_log($conn, 'staff_account_deleted', $adminId > 0 ? $adminId : null, 'admin', 'user', (string) $staffId, 'Staff account deleted.', [
            'name' => $name,
            'email' => (string) ($staff['email'] ?? ''),
            'phone' => (string) ($staff['phone'] ?? ''),
        ]);

        set_flash('success', 'Staff account deleted successfully.');
        redirect('staff.php');
    }

    redirect('staff.php');
}

if (db_ready()) {
    $result = $conn->query(
        "SELECT id, first_name, middle_name, last_name, email, phone, status, created_at
         FROM users
         WHERE role = 'staff'
         ORDER BY FIELD(status, 'active', 'inactive'), last_name ASC, first_name ASC, id DESC"
    );
    if ($result instanceof mysqli_result) {
        $staffRows = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$totalStaff = count($staffRows);
$activeStaff = 0;
$inactiveStaff = 0;
foreach ($staffRows as $row) {
    if ((string) ($row['status'] ?? '') === 'active') {
        $activeStaff++;
    } else {
        $inactiveStaff++;
    }
}

$displayPhone = static function (?string $phone): string {
    $normalized = normalize_mobile_number((string) $phone);
    if (preg_match('/^63\d{10}$/', $normalized) === 1) {
        return '0' . substr($normalized, 2);
    }
    $raw = trim((string) $phone);
    return $raw !== '' ? $raw : '-';
};

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-user-shield me-2 text-primary"></i>Staff Management</h1>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createStaffModal">
            <i class="fa-solid fa-user-plus me-1"></i>Add Staff
        </button>
        <a href="../shared/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
        </a>
    </div>
</div>

<?php if (!db_ready()): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body">
            <p class="mb-0 text-muted">The system is not ready yet. Please contact the administrator.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Total Staff</div>
                    <div class="h4 mb-0"><?= (int) $totalStaff ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Active</div>
                    <div class="h4 mb-0 text-success"><?= (int) $activeStaff ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Inactive</div>
                    <div class="h4 mb-0 text-secondary"><?= (int) $inactiveStaff ?></div>
                </div>
            </div>
        </div>
    </div>

    <div data-live-table class="card card-soft shadow-sm">
        <div class="card-body border-bottom table-controls">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label form-label-sm">Live Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search name, email, mobile">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm">Status Filter</label>
                    <select data-table-filter class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Rows</label>
                    <select data-table-per-page class="form-select form-select-sm">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 text-md-end"><span class="page-legend" data-table-summary></span></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffRows as $row): ?>
                        <?php
                        $fullName = trim(implode(' ', array_filter([
                            (string) ($row['first_name'] ?? ''),
                            (string) ($row['middle_name'] ?? ''),
                            (string) ($row['last_name'] ?? ''),
                        ])));
                        $searchText = strtolower(implode(' ', [
                            (string) ($row['first_name'] ?? ''),
                            (string) ($row['middle_name'] ?? ''),
                            (string) ($row['last_name'] ?? ''),
                            (string) ($row['email'] ?? ''),
                            (string) ($row['phone'] ?? ''),
                            (string) ($row['status'] ?? ''),
                        ]));
                        $rowStatus = (string) ($row['status'] ?? 'inactive');
                        $statusBadge = $rowStatus === 'active' ? 'text-bg-success' : 'text-bg-secondary';
                        ?>
                        <tr data-search="<?= e($searchText) ?>" data-filter="<?= e($rowStatus) ?>">
                            <td>
                                <strong><?= e($fullName !== '' ? $fullName : '-') ?></strong>
                                <div class="small text-muted"><?= e((string) ($row['email'] ?? '-')) ?></div>
                            </td>
                            <td><?= e($displayPhone((string) ($row['phone'] ?? ''))) ?></td>
                            <td><span class="badge <?= e($statusBadge) ?>"><?= e(strtoupper($rowStatus)) ?></span></td>
                            <td><?= !empty($row['created_at']) ? date('M d, Y h:i A', strtotime((string) $row['created_at'])) : '-' ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#editStaffModal"
                                        data-staff-id="<?= (int) ($row['id'] ?? 0) ?>"
                                        data-staff-first-name="<?= e((string) ($row['first_name'] ?? '')) ?>"
                                        data-staff-middle-name="<?= e((string) ($row['middle_name'] ?? '')) ?>"
                                        data-staff-last-name="<?= e((string) ($row['last_name'] ?? '')) ?>"
                                        data-staff-email="<?= e((string) ($row['email'] ?? '')) ?>"
                                        data-staff-phone="<?= e($displayPhone((string) ($row['phone'] ?? ''))) ?>"
                                        data-staff-status="<?= e($rowStatus) ?>"
                                        data-staff-full-name="<?= e($fullName) ?>">
                                        <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#deleteStaffModal"
                                        data-staff-id="<?= (int) ($row['id'] ?? 0) ?>"
                                        data-staff-full-name="<?= e($fullName) ?>"
                                        data-staff-email="<?= e((string) ($row['email'] ?? '')) ?>">
                                        <i class="fa-solid fa-trash-can me-1"></i>Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-body border-top d-flex justify-content-end">
            <div class="d-flex gap-2" data-table-pager></div>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade modal-se" id="createStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="modal-se-title-wrap">
                    <span class="modal-se-icon is-success"><i class="fa-solid fa-user-plus"></i></span>
                    <div>
                        <h5 class="modal-title mb-0">Add Staff Account</h5>
                        <small class="text-muted">San Enrique LGU Scholarship</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <form method="post" id="createStaffForm" class="row g-3"
                    data-crud-modal="1" data-crud-title="Add Staff Account?" data-crud-message="Create this staff account now?"
                    data-crud-confirm-text="Add Staff" data-crud-kind="success">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="col-12 col-md-4"><label class="form-label form-label-sm">First Name *</label><input type="text" name="first_name" class="form-control form-control-sm" required maxlength="100"></div>
                    <div class="col-12 col-md-4"><label class="form-label form-label-sm">Middle Name</label><input type="text" name="middle_name" class="form-control form-control-sm" maxlength="100"></div>
                    <div class="col-12 col-md-4"><label class="form-label form-label-sm">Last Name *</label><input type="text" name="last_name" class="form-control form-control-sm" required maxlength="100"></div>
                    <div class="col-12 col-md-6"><label class="form-label form-label-sm">Email *</label><input type="email" name="email" class="form-control form-control-sm" required maxlength="150" autocomplete="email"></div>
                    <div class="col-12 col-md-3"><label class="form-label form-label-sm">Mobile Number *</label><input type="text" name="phone" class="form-control form-control-sm" required maxlength="12" placeholder="09XXXXXXXXX" inputmode="numeric" pattern="[0-9]*" data-mobile-input autocomplete="tel-national"></div>
                    <div class="col-12 col-md-3"><label class="form-label form-label-sm">Status</label><select name="status" class="form-select form-select-sm"><option value="active" selected>Active</option><option value="inactive">Inactive</option></select></div>
                    <div class="col-12 col-md-6">
                        <label class="form-label form-label-sm">Password *</label>
                        <div class="input-group input-group-sm"><input type="password" name="password" id="createStaffPassword" class="form-control" required minlength="8" autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#createStaffPassword" aria-label="Show password"><i class="fa-regular fa-eye"></i></button></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label form-label-sm">Confirm Password *</label>
                        <div class="input-group input-group-sm"><input type="password" name="confirm_password" id="createStaffConfirmPassword" class="form-control" required minlength="8" autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#createStaffConfirmPassword" aria-label="Show password"><i class="fa-regular fa-eye"></i></button></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="createStaffForm" class="btn btn-primary">Add Staff</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade modal-se" id="editStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="modal-se-title-wrap">
                    <span class="modal-se-icon is-info"><i class="fa-solid fa-user-pen"></i></span>
                    <div>
                        <h5 class="modal-title mb-0">Edit Staff Account</h5>
                        <small class="text-muted" id="editStaffSubtitle">San Enrique LGU Scholarship</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <form method="post" id="editStaffForm" class="row g-3"
                    data-crud-modal="1" data-crud-title="Save Staff Changes?" data-crud-message="Save updates for {record}?"
                    data-crud-confirm-text="Save Changes" data-crud-kind="primary">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="staff_id" id="editStaffId" value="0">

                    <div class="col-12 col-md-4"><label class="form-label form-label-sm">First Name *</label><input type="text" name="first_name" id="editStaffFirstName" class="form-control form-control-sm" required maxlength="100"></div>
                    <div class="col-12 col-md-4"><label class="form-label form-label-sm">Middle Name</label><input type="text" name="middle_name" id="editStaffMiddleName" class="form-control form-control-sm" maxlength="100"></div>
                    <div class="col-12 col-md-4"><label class="form-label form-label-sm">Last Name *</label><input type="text" name="last_name" id="editStaffLastName" class="form-control form-control-sm" required maxlength="100"></div>
                    <div class="col-12 col-md-6"><label class="form-label form-label-sm">Email *</label><input type="email" name="email" id="editStaffEmail" class="form-control form-control-sm" required maxlength="150" autocomplete="email"></div>
                    <div class="col-12 col-md-3"><label class="form-label form-label-sm">Mobile Number *</label><input type="text" name="phone" id="editStaffPhone" class="form-control form-control-sm" required maxlength="12" placeholder="09XXXXXXXXX" inputmode="numeric" pattern="[0-9]*" data-mobile-input autocomplete="tel-national"></div>
                    <div class="col-12 col-md-3"><label class="form-label form-label-sm">Status</label><select name="status" id="editStaffStatus" class="form-select form-select-sm"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <div class="col-12 col-md-6">
                        <label class="form-label form-label-sm">New Password (optional)</label>
                        <div class="input-group input-group-sm"><input type="password" name="password" id="editStaffPassword" class="form-control" minlength="8" autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#editStaffPassword" aria-label="Show password"><i class="fa-regular fa-eye"></i></button></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label form-label-sm">Confirm New Password</label>
                        <div class="input-group input-group-sm"><input type="password" name="confirm_password" id="editStaffConfirmPassword" class="form-control" minlength="8" autocomplete="new-password"><button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#editStaffConfirmPassword" aria-label="Show password"><i class="fa-regular fa-eye"></i></button></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editStaffForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-se" id="deleteStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="modal-se-title-wrap">
                    <span class="modal-se-icon is-danger"><i class="fa-solid fa-trash-can"></i></span>
                    <div>
                        <h5 class="modal-title mb-0">Delete Staff Account?</h5>
                        <small class="text-muted">This action cannot be undone.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-1">You are about to delete this staff account:</p>
                <p class="mb-0 fw-semibold" id="deleteStaffName">-</p>
                <p class="mb-0 small text-muted" id="deleteStaffEmail">-</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <form method="post" id="deleteStaffForm" class="d-inline"
                    data-crud-modal="1" data-crud-title="Delete Staff Account?"
                    data-crud-message="Delete staff account {record}? This action cannot be undone."
                    data-crud-confirm-text="Delete" data-crud-kind="danger">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="staff_id" id="deleteStaffId" value="0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sanitizeMobileInput = function (input) {
        if (!(input instanceof HTMLInputElement)) return;
        input.value = String(input.value || '').replace(/\D+/g, '').slice(0, 12);
    };

    document.querySelectorAll('[data-mobile-input]').forEach(function (input) {
        if (!(input instanceof HTMLInputElement)) return;
        input.addEventListener('input', function () { sanitizeMobileInput(input); });
        input.addEventListener('paste', function () { window.setTimeout(function () { sanitizeMobileInput(input); }, 0); });
        input.addEventListener('keydown', function (event) {
            const allowedKeys = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
            if (allowedKeys.includes(event.key) || event.ctrlKey || event.metaKey) return;
            if (!/^\d$/.test(event.key)) event.preventDefault();
        });
    });

    const editModal = document.getElementById('editStaffModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!(trigger instanceof HTMLElement)) return;

            const get = function (name, fallback) {
                return String(trigger.getAttribute(name) || fallback || '');
            };
            const setValue = function (id, value) {
                const field = document.getElementById(id);
                if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) field.value = value;
            };

            const fullName = get('data-staff-full-name', '').trim();
            setValue('editStaffId', get('data-staff-id', '0'));
            setValue('editStaffFirstName', get('data-staff-first-name', ''));
            setValue('editStaffMiddleName', get('data-staff-middle-name', ''));
            setValue('editStaffLastName', get('data-staff-last-name', ''));
            setValue('editStaffEmail', get('data-staff-email', ''));
            setValue('editStaffPhone', get('data-staff-phone', ''));
            setValue('editStaffStatus', get('data-staff-status', 'active'));
            setValue('editStaffPassword', '');
            setValue('editStaffConfirmPassword', '');

            const subtitle = document.getElementById('editStaffSubtitle');
            if (subtitle) subtitle.textContent = fullName !== '' ? fullName : 'San Enrique LGU Scholarship';
            const form = document.getElementById('editStaffForm');
            if (form instanceof HTMLFormElement) form.dataset.crudRecord = fullName !== '' ? fullName : 'Staff Account';
        });
    }

    const deleteModal = document.getElementById('deleteStaffModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!(trigger instanceof HTMLElement)) return;

            const fullName = String(trigger.getAttribute('data-staff-full-name') || '').trim();
            const email = String(trigger.getAttribute('data-staff-email') || '').trim();
            const staffId = String(trigger.getAttribute('data-staff-id') || '0');

            const idField = document.getElementById('deleteStaffId');
            if (idField instanceof HTMLInputElement) idField.value = staffId;
            const nameField = document.getElementById('deleteStaffName');
            if (nameField) nameField.textContent = fullName !== '' ? fullName : 'Staff Account';
            const emailField = document.getElementById('deleteStaffEmail');
            if (emailField) emailField.textContent = email !== '' ? email : '-';
            const form = document.getElementById('deleteStaffForm');
            if (form instanceof HTMLFormElement) form.dataset.crudRecord = fullName !== '' ? fullName : 'Staff Account';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

