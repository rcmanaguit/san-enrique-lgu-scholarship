<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_admin('../index.php');

$pageTitle = 'Application Periods';
$periods = [];
$openPeriod = null;
$isTableReady = table_exists($conn, 'application_periods');
$hasAcademicYearColumn = $isTableReady && table_column_exists($conn, 'application_periods', 'academic_year');
$hasSemesterColumn = $isTableReady && table_column_exists($conn, 'application_periods', 'semester');
$supportsSemesterPeriods = $hasAcademicYearColumn && $hasSemesterColumn;
$semesterOptions = ['First Semester', 'Second Semester'];

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('application-periods.php');
    }

    if (!$isTableReady) {
        set_flash('danger', 'Application period settings are not ready yet. Please contact the administrator.');
        redirect('application-periods.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'save_period') {
        $id = (int) ($_POST['id'] ?? 0);
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
        $semester = trim((string) ($_POST['semester'] ?? ''));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $isOpen = isset($_POST['is_open']) ? 1 : 0;
        $userId = (int) (current_user()['id'] ?? 0);
        $periodName = trim($semester . ' ' . $academicYear);

        if (!in_array($semester, $semesterOptions, true)) {
            set_flash('danger', 'Semester must be First Semester or Second Semester only.');
            redirect('application-periods.php');
        }

        if (preg_match('/^\d{4}-\d{4}$/', $academicYear) !== 1) {
            set_flash('danger', 'Academic year must be in YYYY-YYYY format (example: 2025-2026).');
            redirect('application-periods.php');
        }
        [$ayStart, $ayEnd] = array_map('intval', explode('-', $academicYear));
        if ($ayEnd !== $ayStart + 1) {
            set_flash('danger', 'Academic year end must be start year + 1 (example: 2025-2026).');
            redirect('application-periods.php');
        }

        $startDate = $startDate !== '' ? $startDate : null;
        $endDate = $endDate !== '' ? $endDate : null;

        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            set_flash('danger', 'Start date must be earlier than or equal to end date.');
            redirect('application-periods.php');
        }

        if ($supportsSemesterPeriods) {
            $stmtExisting = $conn->prepare(
                "SELECT id
                 FROM application_periods
                 WHERE academic_year = ?
                   AND semester = ?
                   AND id <> ?
                 LIMIT 1"
            );
            $stmtExisting->bind_param('ssi', $academicYear, $semester, $id);
        } else {
            $stmtExisting = $conn->prepare(
                "SELECT id
                 FROM application_periods
                 WHERE period_name = ?
                   AND id <> ?
                 LIMIT 1"
            );
            $stmtExisting->bind_param('si', $periodName, $id);
        }
        $stmtExisting->execute();
        $existing = $stmtExisting->get_result()->fetch_assoc();
        $stmtExisting->close();
        if ($existing) {
            set_flash('danger', 'This semester and academic year already exists. Use Extend for deadlines instead.');
            redirect('application-periods.php');
        }

        if ($isOpen === 1) {
            $conn->query("UPDATE application_periods SET is_open = 0");
        }

        if ($id > 0) {
            if ($supportsSemesterPeriods) {
                $stmt = $conn->prepare(
                    "UPDATE application_periods
                     SET period_name = ?, academic_year = ?, semester = ?, start_date = ?, end_date = ?, is_open = ?, notes = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('sssssisi', $periodName, $academicYear, $semester, $startDate, $endDate, $isOpen, $notes, $id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE application_periods
                     SET period_name = ?, start_date = ?, end_date = ?, is_open = ?, notes = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('sssisi', $periodName, $startDate, $endDate, $isOpen, $notes, $id);
            }
            $stmt->execute();
            $stmt->close();
            audit_log(
                $conn,
                'application_period_updated',
                null,
                null,
                'application_period',
                (string) $id,
                'Application period updated.',
                [
                    'period_name' => $periodName,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_open' => $isOpen,
                ]
            );
            if ($isOpen === 1) {
                create_notifications_for_roles(
                    $conn,
                    ['applicant'],
                    'Application Period Open',
                    $periodName . ' is now open for applications.',
                    'period',
                    'register.php',
                    $userId > 0 ? $userId : null
                );
            }
            set_flash('success', 'Application period updated.');
        } else {
            if ($supportsSemesterPeriods) {
                $stmt = $conn->prepare(
                    "INSERT INTO application_periods (period_name, academic_year, semester, start_date, end_date, is_open, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssssisi', $periodName, $academicYear, $semester, $startDate, $endDate, $isOpen, $notes, $userId);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO application_periods (period_name, start_date, end_date, is_open, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssisi', $periodName, $startDate, $endDate, $isOpen, $notes, $userId);
            }
            $stmt->execute();
            $newPeriodId = (int) $stmt->insert_id;
            $stmt->close();
            audit_log(
                $conn,
                'application_period_created',
                null,
                null,
                'application_period',
                (string) $newPeriodId,
                'Application period created.',
                [
                    'period_name' => $periodName,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_open' => $isOpen,
                ]
            );
            if ($isOpen === 1) {
                create_notifications_for_roles(
                    $conn,
                    ['applicant'],
                    'Application Period Open',
                    $periodName . ' is now open for applications.',
                    'period',
                    'register.php',
                    $userId > 0 ? $userId : null
                );
            }
            set_flash('success', 'Application period created.');
        }

        redirect('application-periods.php');
    }

    if ($action === 'set_open') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE application_periods SET is_open = 0");
            $stmt = $conn->prepare("UPDATE application_periods SET is_open = 1 WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $stmtPeriod = $conn->prepare("SELECT period_name, academic_year, semester FROM application_periods WHERE id = ? LIMIT 1");
            $stmtPeriod->bind_param('i', $id);
            $stmtPeriod->execute();
            $selectedPeriod = $stmtPeriod->get_result()->fetch_assoc();
            $stmtPeriod->close();
            $periodLabel = trim((string) ($selectedPeriod['semester'] ?? '') . ' ' . (string) ($selectedPeriod['academic_year'] ?? ''));
            if ($periodLabel === '') {
                $periodLabel = (string) ($selectedPeriod['period_name'] ?? 'Application Period');
            }
            audit_log(
                $conn,
                'application_period_set_open',
                null,
                null,
                'application_period',
                (string) $id,
                'Application period set as open.'
            );
            create_notifications_for_roles(
                $conn,
                ['applicant'],
                'Application Period Open',
                $periodLabel . ' is now open for applications.',
                'period',
                'register.php',
                (int) (current_user()['id'] ?? 0)
            );
            set_flash('success', 'Selected period is now open.');
        }
        redirect('application-periods.php');
    }

    if ($action === 'extend_deadline') {
        $id = (int) ($_POST['id'] ?? 0);
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        if ($id <= 0 || $endDate === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) !== 1) {
            set_flash('danger', 'Please provide a valid new end date.');
            redirect('application-periods.php');
        }

        if ($supportsSemesterPeriods) {
            $stmtPeriod = $conn->prepare("SELECT period_name, academic_year, semester, start_date FROM application_periods WHERE id = ? LIMIT 1");
        } else {
            $stmtPeriod = $conn->prepare("SELECT period_name, start_date FROM application_periods WHERE id = ? LIMIT 1");
        }
        $stmtPeriod->bind_param('i', $id);
        $stmtPeriod->execute();
        $period = $stmtPeriod->get_result()->fetch_assoc();
        $stmtPeriod->close();

        if (!$period) {
            set_flash('danger', 'Application period not found.');
            redirect('application-periods.php');
        }
        $startDate = trim((string) ($period['start_date'] ?? ''));
        if ($startDate !== '' && $endDate < $startDate) {
            set_flash('danger', 'End date cannot be earlier than the period start date.');
            redirect('application-periods.php');
        }

        $stmt = $conn->prepare("UPDATE application_periods SET end_date = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        $stmt->bind_param('si', $endDate, $id);
        $stmt->execute();
        $stmt->close();
        $periodLabel = trim((string) ($period['semester'] ?? '') . ' ' . (string) ($period['academic_year'] ?? ''));
        if ($periodLabel === '') {
            $periodLabel = (string) ($period['period_name'] ?? 'Application Period');
        }
        audit_log(
            $conn,
            'application_period_extended',
            null,
            null,
            'application_period',
            (string) $id,
            'Application period deadline extended.',
            [
                'period_name' => $periodLabel,
                'new_end_date' => $endDate,
            ]
        );
        create_notifications_for_roles(
            $conn,
            ['applicant'],
            'Application Deadline Extended',
            $periodLabel . ' deadline has been extended to ' . date('M d, Y', strtotime($endDate)) . '.',
            'period',
            'register.php',
            (int) (current_user()['id'] ?? 0)
        );

        set_flash('success', 'Application deadline updated for ' . $periodLabel . '.');
        redirect('application-periods.php');
    }

    if ($action === 'close_all') {
        $conn->query("UPDATE application_periods SET is_open = 0");
        audit_log($conn, 'application_period_close_all', null, null, 'application_period', null, 'All application periods were closed.');
        create_notifications_for_roles(
            $conn,
            ['applicant'],
            'Application Period Closed',
            'All application periods are currently closed. Please wait for the next opening announcement.',
            'period',
            'index.php',
            (int) (current_user()['id'] ?? 0)
        );
        set_flash('warning', 'All application periods are now closed.');
        redirect('application-periods.php');
    }
}

if ($isTableReady) {
    $openPeriod = current_open_application_period($conn);
    $result = $conn->query(
        "SELECT p.*, u.first_name, u.last_name
         FROM application_periods p
         LEFT JOIN users u ON u.id = p.created_by
         ORDER BY p.id DESC
         LIMIT 20"
    );
    if ($result instanceof mysqli_result) {
        $periods = $result->fetch_all(MYSQLI_ASSOC);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Application Periods</h1>
    <a href="../shared/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
</div>

<?php if (!$isTableReady): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body">
            <p class="mb-2">Application period settings are not available yet.</p>
            <p class="small text-muted mb-0">Please contact the administrator to finish setup.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 mb-2">Current Status</h2>
            <?php if ($openPeriod): ?>
                <div class="alert alert-success mb-0">
                    <strong>Open:</strong> <?= e(format_application_period($openPeriod)) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    No active application period. Applicants cannot register or apply.
                </div>
            <?php endif; ?>
            <?php if (!$supportsSemesterPeriods): ?>
                <div class="alert alert-warning mt-2 mb-0 small">
                    Semester settings are not ready yet. Please contact the administrator.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Create New Application Period</h2>
            <p class="small text-muted mb-3">Periods are semester-based per academic year (First Semester or Second Semester only).</p>
            <form method="post" class="row g-2" data-crud-message="Save application period {period}?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_period">
                <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm">Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control form-control-sm" placeholder="2025-2026" pattern="\d{4}-\d{4}" required>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label form-label-sm">Semester *</label>
                    <select name="semester" class="form-select form-select-sm" required>
                        <?php foreach ($semesterOptions as $semesterOption): ?>
                            <option value="<?= e($semesterOption) ?>"><?= e($semesterOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label form-label-sm">Notes</label>
                    <input type="text" name="notes" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-1 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_open" id="is_open_period" checked>
                        <label class="form-check-label small" for="is_open_period">Open</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i>Save Period</button>
                </div>
            </form>
            <form method="post" class="mt-2" data-crud-title="Close All Application Periods?" data-crud-message="Close all application periods now? Applicants will not be able to register or apply until a new period is opened.">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="close_all">
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-lock me-1"></i>Close All Periods</button>
            </form>
        </div>
    </div>

    <div class="card card-soft shadow-sm">
        <div class="card-body">
            <h2 class="h6">Period History</h2>
            <?php if (!$periods): ?>
                <p class="text-muted mb-0">No application periods yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Date Range</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periods as $row): ?>
                                <?php
                                $creator = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                                $startDate = !empty($row['start_date']) ? date('M d, Y', strtotime((string) $row['start_date'])) : '-';
                                $endDate = !empty($row['end_date']) ? date('M d, Y', strtotime((string) $row['end_date'])) : '-';
                                $periodLabel = trim((string) ($row['semester'] ?? '') . ' ' . (string) ($row['academic_year'] ?? ''));
                                if ($periodLabel === '') {
                                    $periodLabel = (string) ($row['period_name'] ?? 'Application Period');
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($periodLabel) ?></strong>
                                        <?php if (!empty($row['notes'])): ?>
                                            <div class="small text-muted"><?= e((string) $row['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($startDate) ?> - <?= e($endDate) ?></td>
                                    <td>
                                        <span class="badge <?= (int) $row['is_open'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= (int) $row['is_open'] === 1 ? 'OPEN' : 'CLOSED' ?>
                                        </span>
                                    </td>
                                    <td><?= e($creator !== '' ? $creator : '-') ?></td>
                                    <td class="text-end">
                                        <div class="d-flex flex-column gap-1 align-items-end">
                                            <?php if ((int) $row['is_open'] !== 1): ?>
                                                <form method="post" class="d-inline" data-period-label="<?= e($periodLabel) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="set_open">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-primary btn-sm">Set Open</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="small text-muted">Active</span>
                                            <?php endif; ?>

                                            <form method="post" class="d-flex gap-1" data-period-label="<?= e($periodLabel) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="extend_deadline">
                                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e((string) ($row['end_date'] ?? '')) ?>" required>
                                                <button type="submit" class="btn btn-outline-warning btn-sm">Extend</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
