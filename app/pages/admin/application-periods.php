<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_admin('../index.php');

$pageTitle = 'Application Periods';
$periods = [];
$openPeriod = null;
$isTableReady = table_exists($conn, 'application_periods');
$hasPeriodStatusColumn = $isTableReady && ensure_application_period_status_column($conn);
$hasAcademicYearColumn = $isTableReady && table_column_exists($conn, 'application_periods', 'academic_year');
$hasSemesterColumn = $isTableReady && table_column_exists($conn, 'application_periods', 'semester');
$supportsSemesterPeriods = $hasAcademicYearColumn && $hasSemesterColumn;
$semesterOptions = ['First Semester', 'Second Semester'];
$requirementsReady = ensure_application_period_requirements_table($conn);
$requirementTemplates = [];
$requirementsByPeriod = [];
$manageablePeriodExists = false;
$manageablePeriodLabel = '';
$postRedirectTarget = 'application-periods.php';

if (is_post()) {
    $requestedRedirect = trim((string) ($_POST['redirect_to'] ?? ''));
    if ($requestedRedirect !== '' && preg_match('/^(\.\.\/)?shared\/dashboard\.php$/', $requestedRedirect) === 1) {
        $postRedirectTarget = $requestedRedirect;
    }
}

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

    if ($action === 'requirement_create' || $action === 'requirement_update') {
        $id = (int) ($_POST['requirement_id'] ?? 0);
        $requirementName = trim((string) ($_POST['requirement_name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $applicantType = trim((string) ($_POST['applicant_type'] ?? ''));
        $schoolType = trim((string) ($_POST['school_type'] ?? ''));
        $isRequired = isset($_POST['is_required']) ? 1 : 0;

        if ($requirementName === '') {
            set_flash('danger', 'Requirement name is required.');
            redirect('application-periods.php#requirements');
        }

        $applicantType = in_array($applicantType, ['new', 'renew'], true) ? $applicantType : null;
        $schoolType = in_array($schoolType, ['public', 'private'], true) ? $schoolType : null;

        if ($action === 'requirement_update' && $id > 0) {
            $stmt = $conn->prepare(
                "UPDATE requirement_templates
                 SET requirement_name = ?, description = ?, applicant_type = ?, school_type = ?, is_required = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('ssssii', $requirementName, $description, $applicantType, $schoolType, $isRequired, $id);
            $stmt->execute();
            $stmt->close();
            audit_log(
                $conn,
                'requirement_template_updated',
                null,
                null,
                'requirement_template',
                (string) $id,
                'Requirement template updated.',
                [
                    'requirement_name' => $requirementName,
                    'is_required' => $isRequired,
                ]
            );
            set_flash('success', 'Requirement template updated.');
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO requirement_templates
                (requirement_name, description, applicant_type, school_type, is_required, is_active, sort_order)
                VALUES (?, ?, ?, ?, ?, 1, ?)"
            );
            $sortOrder = 100;
            $stmt->bind_param('ssssii', $requirementName, $description, $applicantType, $schoolType, $isRequired, $sortOrder);
            $stmt->execute();
            $newRequirementId = (int) $stmt->insert_id;
            $stmt->close();
            audit_log(
                $conn,
                'requirement_template_created',
                null,
                null,
                'requirement_template',
                (string) $newRequirementId,
                'Requirement template created.',
                [
                    'requirement_name' => $requirementName,
                    'is_required' => $isRequired,
                ]
            );
            set_flash('success', 'Requirement template created.');
        }

        redirect('application-periods.php#requirements');
    }

    if ($action === 'requirement_delete') {
        $id = (int) ($_POST['requirement_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM requirement_templates WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            audit_log(
                $conn,
                'requirement_template_deleted',
                null,
                null,
                'requirement_template',
                (string) $id,
                'Requirement template deleted.'
            );
            set_flash('success', 'Requirement template deleted.');
        }

        redirect('application-periods.php#requirements');
    }

    if ($action === 'save_period') {
        $id = (int) ($_POST['id'] ?? 0);
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
        $semester = trim((string) ($_POST['semester'] ?? ''));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $periodStatus = isset($_POST['open_immediately']) ? 'open' : 'closed';
        $isOpen = $periodStatus === 'open' ? 1 : 0;
        $selectedRequirementIds = array_map('intval', (array) ($_POST['requirement_template_ids'] ?? []));
        $userId = (int) (current_user()['id'] ?? 0);
        $periodName = trim($semester . ' ' . $academicYear);

        if ($id <= 0) {
            $stmtManageable = $conn->prepare(
                "SELECT id, period_name, academic_year, semester
                 FROM application_periods
                 WHERE period_status IN ('open', 'closed')
                 ORDER BY FIELD(period_status, 'open', 'closed'), id DESC
                 LIMIT 1"
            );
            if ($stmtManageable) {
                $stmtManageable->execute();
                $manageablePeriod = $stmtManageable->get_result()->fetch_assoc() ?: null;
                $stmtManageable->close();
                if ($manageablePeriod) {
                    $manageableLabel = trim((string) ($manageablePeriod['semester'] ?? '') . ' ' . (string) ($manageablePeriod['academic_year'] ?? ''));
                    if ($manageableLabel === '') {
                        $manageableLabel = (string) ($manageablePeriod['period_name'] ?? 'current application period');
                    }
                    set_flash('danger', 'You must complete ' . $manageableLabel . ' before creating a new application period.');
                    redirect('application-periods.php');
                }
            }
        }

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

        if ($requirementsReady) {
            $selectedRequirementIds = array_values(array_unique(array_filter(
                $selectedRequirementIds,
                static fn(int $value): bool => $value > 0
            )));

            if (!$selectedRequirementIds) {
                set_flash('danger', 'Select at least one requirement for this application period.');
                redirect('application-periods.php');
            }
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

        if ($periodStatus === 'open') {
            $conn->query("UPDATE application_periods SET is_open = 0, period_status = 'closed' WHERE period_status = 'open'");
        }

        if ($id > 0) {
            if ($supportsSemesterPeriods) {
                $stmt = $conn->prepare(
                    "UPDATE application_periods
                     SET period_name = ?, academic_year = ?, semester = ?, start_date = ?, end_date = ?, is_open = ?, period_status = ?, notes = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('sssssissi', $periodName, $academicYear, $semester, $startDate, $endDate, $isOpen, $periodStatus, $notes, $id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE application_periods
                     SET period_name = ?, start_date = ?, end_date = ?, is_open = ?, period_status = ?, notes = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('sssissi', $periodName, $startDate, $endDate, $isOpen, $periodStatus, $notes, $id);
            }
            $stmt->execute();
            $stmt->close();
            save_application_period_requirements($conn, $id, $selectedRequirementIds);
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
                    'period_status' => $periodStatus,
                    'requirement_template_ids' => $selectedRequirementIds,
                ]
            );
            if ($periodStatus === 'open') {
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
                    "INSERT INTO application_periods (period_name, academic_year, semester, start_date, end_date, is_open, period_status, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssssissi', $periodName, $academicYear, $semester, $startDate, $endDate, $isOpen, $periodStatus, $notes, $userId);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO application_periods (period_name, start_date, end_date, is_open, period_status, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssissi', $periodName, $startDate, $endDate, $isOpen, $periodStatus, $notes, $userId);
            }
            $stmt->execute();
            $newPeriodId = (int) $stmt->insert_id;
            $stmt->close();
            save_application_period_requirements($conn, $newPeriodId, $selectedRequirementIds);
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
                    'period_status' => $periodStatus,
                    'requirement_template_ids' => $selectedRequirementIds,
                ]
            );
            if ($periodStatus === 'open') {
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
            $stmtCurrent = $conn->prepare("SELECT period_status FROM application_periods WHERE id = ? LIMIT 1");
            if ($stmtCurrent) {
                $stmtCurrent->bind_param('i', $id);
                $stmtCurrent->execute();
                $currentRow = $stmtCurrent->get_result()->fetch_assoc() ?: null;
                $stmtCurrent->close();
                if ((string) ($currentRow['period_status'] ?? '') === 'completed') {
                    set_flash('danger', 'Completed periods are read-only by default.');
                    redirect('application-periods.php');
                }
            }
            $conn->query("UPDATE application_periods SET is_open = 0, period_status = 'closed' WHERE period_status = 'open'");
            $stmt = $conn->prepare("UPDATE application_periods SET is_open = 1, period_status = 'open' WHERE id = ? LIMIT 1");
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
            set_flash('success', 'Selected period is now open for submission.');
        }
        redirect('application-periods.php');
    }

    if ($action === 'close_period') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmtPeriod = $conn->prepare("SELECT period_name, academic_year, semester, is_open, period_status FROM application_periods WHERE id = ? LIMIT 1");
            $stmtPeriod->bind_param('i', $id);
            $stmtPeriod->execute();
            $period = $stmtPeriod->get_result()->fetch_assoc();
            $stmtPeriod->close();

            if ($period && (string) ($period['period_status'] ?? '') === 'open') {
                $stmt = $conn->prepare("UPDATE application_periods SET is_open = 0, period_status = 'closed' WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();

                $periodLabel = trim((string) ($period['semester'] ?? '') . ' ' . (string) ($period['academic_year'] ?? ''));
                if ($periodLabel === '') {
                    $periodLabel = (string) ($period['period_name'] ?? 'Application Period');
                }

                audit_log(
                    $conn,
                    'application_period_closed',
                    null,
                    null,
                    'application_period',
                    (string) $id,
                    'Application period closed.'
                );
                create_notifications_for_roles(
                    $conn,
                    ['applicant'],
                    'Application Period Closed',
                    $periodLabel . ' is now closed for submission.',
                    'period',
                    'index.php',
                    (int) (current_user()['id'] ?? 0)
                );
                set_flash('warning', $periodLabel . ' is now closed for submission.');
            }
        }
        redirect($postRedirectTarget);
    }

    if ($action === 'complete_period') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmtPeriod = $conn->prepare("SELECT period_name, academic_year, semester, period_status FROM application_periods WHERE id = ? LIMIT 1");
            $stmtPeriod->bind_param('i', $id);
            $stmtPeriod->execute();
            $period = $stmtPeriod->get_result()->fetch_assoc();
            $stmtPeriod->close();

            if ($period && (string) ($period['period_status'] ?? '') !== 'completed') {
                $stmt = $conn->prepare("UPDATE application_periods SET is_open = 0, period_status = 'completed' WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();

                $periodLabel = trim((string) ($period['semester'] ?? '') . ' ' . (string) ($period['academic_year'] ?? ''));
                if ($periodLabel === '') {
                    $periodLabel = (string) ($period['period_name'] ?? 'Application Period');
                }

                audit_log(
                    $conn,
                    'application_period_completed',
                    null,
                    null,
                    'application_period',
                    (string) $id,
                    'Application period marked as completed.'
                );
                set_flash('success', $periodLabel . ' marked as completed.');
            }
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
        $currentStatusRow = $conn->query("SELECT period_status FROM application_periods WHERE id = " . $id . " LIMIT 1");
        $statusRow = $currentStatusRow instanceof mysqli_result ? $currentStatusRow->fetch_assoc() : null;
        if ((string) ($statusRow['period_status'] ?? '') === 'completed') {
            set_flash('danger', 'Completed periods are read-only by default.');
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

}

if ($isTableReady) {
    if ($requirementsReady) {
        $requirementsResult = $conn->query(
            "SELECT id, requirement_name, description, applicant_type, school_type, is_required, sort_order
             FROM requirement_templates
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );
        if ($requirementsResult instanceof mysqli_result) {
            $requirementTemplates = $requirementsResult->fetch_all(MYSQLI_ASSOC);
        }
    }

    $activePeriod = current_active_application_period($conn);
    $openPeriod = current_open_application_period($conn);
    $manageablePeriodExists = $activePeriod !== null;
    if ($activePeriod) {
        $manageablePeriodLabel = trim((string) ($activePeriod['semester'] ?? '') . ' ' . (string) ($activePeriod['academic_year'] ?? ''));
        if ($manageablePeriodLabel === '') {
            $manageablePeriodLabel = (string) ($activePeriod['period_name'] ?? 'the current application period');
        }
    }
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

    if ($requirementsReady && $periods) {
        $periodIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $periods
        ), static fn(int $value): bool => $value > 0)));

        if ($periodIds) {
            $requirementsByPeriod = array_fill_keys($periodIds, []);
            $idsSql = implode(',', $periodIds);
            $periodRequirementResult = $conn->query(
                "SELECT apr.application_period_id, rt.requirement_name
                 FROM application_period_requirements apr
                 INNER JOIN requirement_templates rt ON rt.id = apr.requirement_template_id
                 WHERE apr.application_period_id IN ({$idsSql})
                 ORDER BY rt.sort_order ASC, rt.id ASC"
            );
            if ($periodRequirementResult instanceof mysqli_result) {
                while ($row = $periodRequirementResult->fetch_assoc()) {
                    $periodId = (int) ($row['application_period_id'] ?? 0);
                    if ($periodId <= 0) {
                        continue;
                    }
                    $requirementsByPeriod[$periodId][] = (string) ($row['requirement_name'] ?? '');
                }
            }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Settings';
$pageHeaderTitle = '<i class="fa-solid fa-calendar-days me-2 text-primary"></i>Application Periods';
$pageHeaderDescription = 'Manage submission windows, requirements, and the lifecycle of each application period.';
$pageHeaderActions = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPeriodModal"' . ($manageablePeriodExists ? ' disabled aria-disabled="true"' : '') . '><i class="fa-solid fa-plus me-1"></i>Create Period</button>'
    . '<a href="../shared/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>';
include __DIR__ . '/../../includes/partials/page-shell-header.php';
?>

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
            <?php if ($activePeriod): ?>
                <div class="alert alert-success mb-0">
                    <strong>Active period:</strong> <?= e(format_application_period($activePeriod)) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    No active application period.
                </div>
            <?php endif; ?>
            <?php if ($openPeriod): ?>
                <div class="alert alert-info mt-2 mb-0 small">
                    Submission: <strong>Open</strong>
                </div>
            <?php elseif ($activePeriod): ?>
                <div class="alert alert-warning mt-2 mb-0 small">
                    Submission: <strong>Closed</strong>. Admin and staff can continue processing this period until it is marked completed.
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-2 mb-0 small">
                    Applicants cannot apply until a period is opened for submission.
                </div>
            <?php endif; ?>
            <?php if ($manageablePeriodExists): ?>
                <div class="alert alert-secondary mt-2 mb-0 small">
                    Create Period is unavailable while <?= e($manageablePeriodLabel !== '' ? $manageablePeriodLabel : 'the current application period') ?> is still open or closed for processing. Complete it first.
                </div>
            <?php endif; ?>
            <?php if (!$supportsSemesterPeriods): ?>
                <div class="alert alert-warning mt-2 mb-0 small">
                    Semester settings are not ready yet. Please contact the administrator.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-soft shadow-sm">
        <div class="card-body">
            <h2 class="h6">Period History</h2>
            <?php if (!$periods): ?>
                <p class="text-muted mb-0">No application periods yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-simple-list="1" data-simple-list-visible="3" data-simple-list-title-selector="strong">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Submission Window</th>
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
                                        <?php $periodStatus = (string) ($row['period_status'] ?? ((int) ($row['is_open'] ?? 0) === 1 ? 'open' : 'closed')); ?>
                                        <span class="badge <?= e(application_period_status_badge_class($periodStatus)) ?>">
                                            <?= e(strtoupper(application_period_status_label($periodStatus))) ?>
                                        </span>
                                    </td>
                                    <td><?= e($creator !== '' ? $creator : '-') ?></td>
                                    <td class="text-end">
                                        <div class="d-flex flex-column gap-1 align-items-end">
                                            <?php if ($periodStatus === 'open'): ?>
                                                <form method="post" class="d-inline" data-period-label="<?= e($periodLabel) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="close_period">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Close Submission</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($periodStatus === 'closed'): ?>
                                                <form method="post" class="d-inline" data-period-label="<?= e($periodLabel) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="complete_period">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-dark btn-sm">Complete Period</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($periodStatus !== 'completed'): ?>
                                                <form method="post" class="d-flex gap-1" data-period-label="<?= e($periodLabel) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="extend_deadline">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e((string) ($row['end_date'] ?? '')) ?>" required>
                                                    <button type="submit" class="btn btn-outline-warning btn-sm">Extend Submission Deadline</button>
                                                </form>
                                            <?php endif; ?>
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

    <div class="card card-soft shadow-sm mt-3" id="requirements">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h6 mb-0">Requirement Templates</h2>
                    <div class="small text-muted">Maintain the master checklist here. Each application period selects from this list.</div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editRequirementModal" data-requirement-mode="create">
                    <i class="fa-solid fa-plus me-1"></i>Add Requirement
                </button>
            </div>
            <?php if (!$requirementTemplates): ?>
                <p class="text-muted mb-0">No requirement templates yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-simple-list="1" data-simple-list-visible="2" data-simple-list-title-selector="strong">
                        <thead>
                            <tr>
                                <th>Requirement</th>
                                <th>Required</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requirementTemplates as $template): ?>
                                <?php
                                $scopeParts = [];
                                if (!empty($template['applicant_type'])) {
                                    $scopeParts[] = 'Applicant: ' . ucfirst((string) $template['applicant_type']);
                                }
                                if (!empty($template['school_type'])) {
                                    $scopeParts[] = 'School: ' . ucfirst((string) $template['school_type']);
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e((string) ($template['requirement_name'] ?? 'Requirement')) ?></strong>
                                        <?php if (!empty($template['description'])): ?>
                                            <div class="small text-muted"><?= e((string) $template['description']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($scopeParts): ?>
                                            <div class="small text-muted"><?= e(implode(' • ', $scopeParts)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int) ($template['is_required'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editRequirementModal"
                                                data-requirement-mode="edit"
                                                data-requirement-id="<?= (int) ($template['id'] ?? 0) ?>"
                                                data-requirement-name="<?= e((string) ($template['requirement_name'] ?? '')) ?>"
                                                data-description="<?= e((string) ($template['description'] ?? '')) ?>"
                                                data-applicant-type="<?= e((string) ($template['applicant_type'] ?? '')) ?>"
                                                data-school-type="<?= e((string) ($template['school_type'] ?? '')) ?>"
                                                data-is-required="<?= (int) ($template['is_required'] ?? 0) ?>"
                                            >Edit</button>
                                            <form method="post" class="d-inline" data-requirement-name="<?= e((string) ($template['requirement_name'] ?? 'Requirement')) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="requirement_delete">
                                                <input type="hidden" name="requirement_id" value="<?= (int) ($template['id'] ?? 0) ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
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

<?php if ($isTableReady && !$manageablePeriodExists): ?>
    <div class="modal fade" id="createPeriodModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" data-crud-message="Save application period {period}?">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Create Application Period</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-3">Create one period for each semester and academic year. It will stay closed for submission until you open it.</p>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_period">
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label form-label-sm">Academic Year *</label>
                                <input type="text" name="academic_year" class="form-control form-control-sm" placeholder="2025-2026" pattern="\d{4}-\d{4}" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label form-label-sm">Semester *</label>
                                <select name="semester" class="form-select form-select-sm" required>
                                    <?php foreach ($semesterOptions as $semesterOption): ?>
                                        <option value="<?= e($semesterOption) ?>"><?= e($semesterOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label form-label-sm">Submission Start Date</label>
                                <input type="date" name="start_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label form-label-sm">Submission End Date</label>
                                <input type="date" name="end_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Requirements *</label>
                                <?php if (!$requirementsReady): ?>
                                    <div class="alert alert-warning py-2 mb-0 small">Requirement linking is not ready yet.</div>
                                <?php elseif (!$requirementTemplates): ?>
                                    <div class="alert alert-warning py-2 mb-0 small">
                                        No active requirement templates yet. Add a requirement in the section below first.
                                    </div>
                                <?php else: ?>
                                    <div class="border rounded p-2 bg-light-subtle">
                                        <div class="row g-2">
                                            <?php foreach ($requirementTemplates as $template): ?>
                                                <?php
                                                $scopeParts = [];
                                                if (!empty($template['applicant_type'])) {
                                                    $scopeParts[] = 'Applicant: ' . ucfirst((string) $template['applicant_type']);
                                                }
                                                if (!empty($template['school_type'])) {
                                                    $scopeParts[] = 'School: ' . ucfirst((string) $template['school_type']);
                                                }
                                                if ((int) ($template['is_required'] ?? 0) === 1) {
                                                    $scopeParts[] = 'Required';
                                                }
                                                ?>
                                                <div class="col-12 col-md-6">
                                                    <label class="border rounded p-2 d-block bg-white h-100">
                                                        <div class="form-check">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                name="requirement_template_ids[]"
                                                                value="<?= (int) ($template['id'] ?? 0) ?>"
                                                                checked
                                                            >
                                                            <span class="form-check-label fw-semibold">
                                                                <?= e((string) ($template['requirement_name'] ?? 'Requirement')) ?>
                                                            </span>
                                                        </div>
                                                        <?php if (!empty($template['description'])): ?>
                                                            <div class="small text-muted mt-1"><?= e((string) $template['description']) ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($scopeParts): ?>
                                                            <div class="small text-muted mt-1"><?= e(implode(' • ', $scopeParts)) ?></div>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="small text-muted mt-2">Manage the master requirement list in the Requirements section below.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="open_immediately" id="is_open_period">
                                    <label class="form-check-label small" for="is_open_period">Open for submission immediately</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i>Create Period</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="editRequirementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="editRequirementModalTitle">Add Requirement</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" id="editRequirementAction" value="requirement_create">
                    <input type="hidden" name="requirement_id" id="editRequirementId" value="0">
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Requirement Name *</label>
                        <input type="text" name="requirement_name" id="editRequirementName" class="form-control form-control-sm" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label form-label-sm">Applicant Type</label>
                            <select name="applicant_type" id="editApplicantType" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="new">New</option>
                                <option value="renew">Renew</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-sm">School Type</label>
                            <select name="school_type" id="editSchoolType" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="public">Public</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label form-label-sm">Description</label>
                        <input type="text" name="description" id="editDescription" class="form-control form-control-sm">
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_required" id="editIsRequired" value="1" checked>
                        <label class="form-check-label small" for="editIsRequired">Required</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="editRequirementSubmit">Save Requirement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editRequirementModal');
    if (!(editModal instanceof HTMLElement)) {
        return;
    }

    editModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        const mode = String(trigger.getAttribute('data-requirement-mode') || 'create');
        const modalTitle = document.getElementById('editRequirementModalTitle');
        const actionInput = document.getElementById('editRequirementAction');
        const idInput = document.getElementById('editRequirementId');
        const nameInput = document.getElementById('editRequirementName');
        const descriptionInput = document.getElementById('editDescription');
        const applicantTypeInput = document.getElementById('editApplicantType');
        const schoolTypeInput = document.getElementById('editSchoolType');
        const isRequiredInput = document.getElementById('editIsRequired');
        const submitButton = document.getElementById('editRequirementSubmit');

        if (modalTitle instanceof HTMLElement) {
            modalTitle.textContent = mode === 'edit' ? 'Edit Requirement' : 'Add Requirement';
        }
        if (actionInput instanceof HTMLInputElement) {
            actionInput.value = mode === 'edit' ? 'requirement_update' : 'requirement_create';
        }
        if (idInput instanceof HTMLInputElement) {
            idInput.value = mode === 'edit' ? String(trigger.getAttribute('data-requirement-id') || '0') : '0';
        }
        if (nameInput instanceof HTMLInputElement) {
            nameInput.value = mode === 'edit' ? String(trigger.getAttribute('data-requirement-name') || '') : '';
        }
        if (descriptionInput instanceof HTMLInputElement) {
            descriptionInput.value = mode === 'edit' ? String(trigger.getAttribute('data-description') || '') : '';
        }
        if (applicantTypeInput instanceof HTMLSelectElement) {
            applicantTypeInput.value = mode === 'edit' ? String(trigger.getAttribute('data-applicant-type') || '') : '';
        }
        if (schoolTypeInput instanceof HTMLSelectElement) {
            schoolTypeInput.value = mode === 'edit' ? String(trigger.getAttribute('data-school-type') || '') : '';
        }
        if (isRequiredInput instanceof HTMLInputElement) {
            isRequiredInput.checked = mode === 'edit'
                ? String(trigger.getAttribute('data-is-required') || '0') === '1'
                : true;
        }
        if (submitButton instanceof HTMLElement) {
            submitButton.textContent = mode === 'edit' ? 'Save Changes' : 'Save Requirement';
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
