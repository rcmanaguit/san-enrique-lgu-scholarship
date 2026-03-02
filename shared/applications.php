<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Manage Applications';
$applications = [];
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$isAdmin = is_admin();
$allowedStatus = application_status_options();
$approvedPhaseStatuses = approved_phase_statuses();

if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}
$redirectUrl = 'applications.php' . ($statusFilter ? '?status=' . urlencode($statusFilter) : '');

if (is_post() && db_ready()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
    } else {
        $action = trim((string) ($_POST['action'] ?? 'update_status'));

        if ($action === 'set_soa_deadline') {
            if (!$isAdmin) {
                set_flash('danger', 'Only admin can set or extend SOA submission deadline.');
                redirect($redirectUrl);
            }

            $applicationId = (int) ($_POST['application_id'] ?? 0);
            $soaDeadline = trim((string) ($_POST['soa_submission_deadline'] ?? ''));
            if (
                $applicationId <= 0
                || $soaDeadline === ''
                || preg_match('/^\d{4}-\d{2}-\d{2}$/', $soaDeadline) !== 1
            ) {
                set_flash('danger', 'Please provide a valid SOA submission deadline.');
                redirect($redirectUrl);
            }

            $stmtCurrent = $conn->prepare(
                "SELECT a.application_no, a.status, a.soa_submitted_at, u.id AS user_id, u.phone
                 FROM applications a
                 INNER JOIN users u ON u.id = a.user_id
                 WHERE a.id = ?
                 LIMIT 1"
            );
            $stmtCurrent->bind_param('i', $applicationId);
            $stmtCurrent->execute();
            $current = $stmtCurrent->get_result()->fetch_assoc();
            $stmtCurrent->close();

            if (!$current) {
                set_flash('danger', 'Application not found.');
                redirect($redirectUrl);
            }
            if (!in_array((string) $current['status'], $approvedPhaseStatuses, true)) {
                set_flash('danger', 'SOA deadline can only be set after the application is approved.');
                redirect($redirectUrl);
            }

            $updatedStatus = (string) $current['status'];
            $soaSubmittedAt = (string) ($current['soa_submitted_at'] ?? '');
            if ($updatedStatus === 'approved') {
                $updatedStatus = 'for_soa_submission';
                $soaSubmittedAt = '';
            }
            $soaSubmittedAt = $soaSubmittedAt !== '' ? $soaSubmittedAt : null;

            $stmt = $conn->prepare(
                "UPDATE applications
                 SET status = ?, soa_submission_deadline = ?, soa_submitted_at = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param('sssi', $updatedStatus, $soaDeadline, $soaSubmittedAt, $applicationId);
            $stmt->execute();
            $stmt->close();

            $message = 'San Enrique LGU Scholarship: Application ' . $current['application_no']
                . ' SOA/Student Copy submission deadline is set to '
                . date('M d, Y', strtotime($soaDeadline))
                . '. Please submit your SOA/Student Copy at the Mayor\'s Office.';
            sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
            create_notification(
                $conn,
                (int) ($current['user_id'] ?? 0),
                'SOA Submission Deadline Set',
                'Application ' . (string) ($current['application_no'] ?? '') . ' deadline: ' . date('M d, Y', strtotime($soaDeadline)) . '. Please submit your SOA/Student Copy at the Mayor\'s Office.',
                'application_status',
                'my-application.php',
                (int) (current_user()['id'] ?? 0)
            );
            audit_log(
                $conn,
                'application_set_soa_deadline',
                null,
                null,
                'application',
                (string) $applicationId,
                'SOA submission deadline was set or extended.',
                [
                    'application_no' => (string) ($current['application_no'] ?? ''),
                    'deadline' => $soaDeadline,
                    'status_after' => $updatedStatus,
                ]
            );

            set_flash('success', 'SOA deadline updated.');
            redirect($redirectUrl);
        }

        if ($action === 'mark_soa_submitted') {
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            if ($applicationId <= 0) {
                set_flash('danger', 'Invalid application update.');
                redirect($redirectUrl);
            }

            $stmtCurrent = $conn->prepare(
                "SELECT a.application_no, a.status, a.soa_submitted_at, u.id AS user_id, u.phone
                 FROM applications a
                 INNER JOIN users u ON u.id = a.user_id
                 WHERE a.id = ?
                 LIMIT 1"
            );
            $stmtCurrent->bind_param('i', $applicationId);
            $stmtCurrent->execute();
            $current = $stmtCurrent->get_result()->fetch_assoc();
            $stmtCurrent->close();

            if (!$current) {
                set_flash('danger', 'Application not found.');
                redirect($redirectUrl);
            }
            if (!in_array((string) $current['status'], ['for_soa_submission', 'soa_submitted'], true)) {
                set_flash('danger', 'Application is not currently waiting for SOA submission.');
                redirect($redirectUrl);
            }

            $soaSubmittedAt = trim((string) ($current['soa_submitted_at'] ?? ''));
            if ($soaSubmittedAt === '') {
                $soaSubmittedAt = date('Y-m-d H:i:s');
            }
            $newStatus = 'soa_submitted';
            $stmt = $conn->prepare(
                "UPDATE applications
                 SET status = ?, soa_submitted_at = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param('ssi', $newStatus, $soaSubmittedAt, $applicationId);
            $stmt->execute();
            $stmt->close();

            if ((string) $current['status'] !== $newStatus) {
                $message = 'San Enrique LGU Scholarship: Application ' . $current['application_no']
                    . ' SOA/Student Copy has been received by the scholarship office.';
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'SOA Received',
                    'Your SOA/Student Copy for application ' . (string) ($current['application_no'] ?? '') . ' has been received by the scholarship office.',
                    'application_status',
                    'my-application.php',
                    (int) (current_user()['id'] ?? 0)
                );
            }
            audit_log(
                $conn,
                'application_mark_soa_submitted',
                null,
                null,
                'application',
                (string) $applicationId,
                'Application marked as SOA submitted.',
                [
                    'application_no' => (string) ($current['application_no'] ?? ''),
                    'previous_status' => (string) ($current['status'] ?? ''),
                ]
            );

            set_flash('success', 'Application marked as SOA submitted.');
            redirect($redirectUrl);
        }

        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $reviewNotes = trim((string) ($_POST['review_notes'] ?? ''));
        $soaDeadlineRaw = trim((string) ($_POST['soa_submission_deadline'] ?? ''));
        $soaDeadline = $soaDeadlineRaw !== '' ? $soaDeadlineRaw : null;

        if ($soaDeadline !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $soaDeadline) !== 1) {
            set_flash('danger', 'Invalid SOA deadline format.');
            redirect($redirectUrl);
        }
        if ($soaDeadline !== null && !$isAdmin) {
            set_flash('danger', 'Only admin can set or extend SOA submission deadline.');
            redirect($redirectUrl);
        }
        if ($applicationId <= 0 || !in_array($newStatus, $allowedStatus, true)) {
            set_flash('danger', 'Invalid application update.');
            redirect($redirectUrl);
        }

        $stmtCurrent = $conn->prepare(
            "SELECT a.application_no, a.status, a.soa_submission_deadline, a.soa_submitted_at, u.id AS user_id, u.phone
             FROM applications a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.id = ?
             LIMIT 1"
        );
        $stmtCurrent->bind_param('i', $applicationId);
        $stmtCurrent->execute();
        $current = $stmtCurrent->get_result()->fetch_assoc();
        $stmtCurrent->close();

        if (!$current) {
            set_flash('danger', 'Application not found.');
            redirect($redirectUrl);
        }

        $currentStatus = (string) ($current['status'] ?? '');
        if ($newStatus === 'for_soa_submission') {
            if (!$isAdmin) {
                set_flash('danger', 'Only admin can move application to SOA submission stage.');
                redirect($redirectUrl);
            }
            if (!in_array($currentStatus, $approvedPhaseStatuses, true)) {
                set_flash('danger', 'SOA submission stage is only available after approval.');
                redirect($redirectUrl);
            }
        }
        if ($newStatus === 'soa_submitted' && !in_array($currentStatus, ['for_soa_submission', 'soa_submitted'], true)) {
            set_flash('danger', 'SOA can only be marked submitted after approval and SOA request.');
            redirect($redirectUrl);
        }

        $currentDeadline = trim((string) ($current['soa_submission_deadline'] ?? ''));
        $deadlineToSave = $currentDeadline !== '' ? $currentDeadline : null;
        if ($isAdmin && $soaDeadline !== null) {
            $deadlineToSave = $soaDeadline;
        }
        if ($newStatus === 'for_soa_submission' && $deadlineToSave === null) {
            set_flash('danger', 'Set an SOA submission deadline before moving to SOA submission stage.');
            redirect($redirectUrl);
        }

        $currentSubmittedAt = trim((string) ($current['soa_submitted_at'] ?? ''));
        $soaSubmittedAt = $currentSubmittedAt !== '' ? $currentSubmittedAt : null;
        if ($newStatus === 'for_soa_submission') {
            $soaSubmittedAt = null;
        } elseif ($newStatus === 'soa_submitted' && $soaSubmittedAt === null) {
            $soaSubmittedAt = date('Y-m-d H:i:s');
        }

        $stmt = $conn->prepare(
            "UPDATE applications
             SET status = ?, review_notes = ?, soa_submission_deadline = ?, soa_submitted_at = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ssssi', $newStatus, $reviewNotes, $deadlineToSave, $soaSubmittedAt, $applicationId);
        $stmt->execute();
        $stmt->close();

        $statusChanged = $currentStatus !== $newStatus;
        $deadlineChanged = $currentDeadline !== (string) ($deadlineToSave ?? '');
        if ($statusChanged || $deadlineChanged) {
            if ($statusChanged) {
                $statusText = strtoupper(str_replace('_', ' ', $newStatus));
                $message = 'San Enrique LGU Scholarship: Application ' . $current['application_no'] . ' status updated to ' . $statusText . '.';
            } else {
                $message = 'San Enrique LGU Scholarship: SOA/Student Copy deadline for application ' . $current['application_no'] . ' has been updated.';
            }

            if ($newStatus === 'for_soa_submission' && $deadlineToSave !== null) {
                $message .= ' Deadline: ' . date('M d, Y', strtotime($deadlineToSave))
                    . '. Please submit your SOA/Student Copy at the Mayor\'s Office.';
            }
            sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');

            $notificationTitle = $statusChanged ? 'Application Status Updated' : 'SOA Deadline Updated';
            $notificationMessage = $statusChanged
                ? 'Application ' . (string) ($current['application_no'] ?? '') . ' status is now ' . strtoupper(str_replace('_', ' ', $newStatus)) . '.'
                : 'SOA/Student Copy deadline for application ' . (string) ($current['application_no'] ?? '') . ' has been updated.';
            if ($newStatus === 'for_soa_submission' && $deadlineToSave !== null) {
                $notificationMessage .= ' Deadline: ' . date('M d, Y', strtotime((string) $deadlineToSave)) . '. Please submit your SOA/Student Copy at the Mayor\'s Office.';
            }
            create_notification(
                $conn,
                (int) ($current['user_id'] ?? 0),
                $notificationTitle,
                $notificationMessage,
                'application_status',
                'my-application.php',
                (int) (current_user()['id'] ?? 0)
            );
            audit_log(
                $conn,
                'application_status_updated',
                null,
                null,
                'application',
                (string) $applicationId,
                'Application status/review details were updated.',
                [
                    'application_no' => (string) ($current['application_no'] ?? ''),
                    'previous_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'deadline_changed' => $deadlineChanged,
                    'deadline' => $deadlineToSave,
                ]
            );
        }

        set_flash('success', 'Application status updated.');
        redirect($redirectUrl);
    }
}

if (db_ready()) {
    $baseSql = "SELECT a.id, a.application_no, a.scholarship_type, a.applicant_type, a.school_name, a.school_type, a.semester, a.school_year,
                       a.status, a.review_notes, a.soa_submission_deadline, a.soa_submitted_at, a.updated_at,
                       u.first_name, u.last_name, u.email, u.phone
                FROM applications a
                INNER JOIN users u ON u.id = a.user_id";

    if ($statusFilter !== '') {
        $baseSql .= " WHERE a.status = ?";
    }
    $baseSql .= " ORDER BY a.updated_at DESC";

    if ($statusFilter !== '') {
        $stmt = $conn->prepare($baseSql);
        $stmt->bind_param('s', $statusFilter);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            $applications = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    } else {
        $result = $conn->query($baseSql);
        if ($result instanceof mysqli_result) {
            $applications = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Applications</h1>
    <form method="get" class="d-flex gap-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <?php foreach ($allowedStatus as $status): ?>
                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Filter</button>
    </form>
</div>

<?php if (!$applications): ?>
    <div class="card card-soft"><div class="card-body text-muted">No application records found.</div></div>
<?php else: ?>
    <div data-live-table class="card card-soft shadow-sm">
        <div class="card-body border-bottom table-controls">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label form-label-sm">Live Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search app no, name, school, scholarship">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm">Live Status</label>
                    <select data-table-filter class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach ($allowedStatus as $status): ?>
                            <option value="<?= e($status) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                        <?php endforeach; ?>
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
                <div class="col-12 col-md-2 text-md-end">
                    <span class="page-legend" data-table-summary></span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>School / Scholarship</th>
                        <th>Status / SOA</th>
                        <th>Review</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $row): ?>
                    <?php
                    $search = strtolower(implode(' ', [
                        $row['application_no'],
                        $row['first_name'],
                        $row['last_name'],
                        $row['school_name'],
                        $row['scholarship_type'],
                        $row['status'],
                    ]));
                    ?>
                    <tr data-search="<?= e($search) ?>" data-filter="<?= e((string) $row['status']) ?>">
                        <td>
                            <strong><?= e((string) $row['application_no']) ?></strong>
                            <div class="small text-muted">#<?= (int) $row['id'] ?> | <?= e((string) $row['semester']) ?> / <?= e((string) $row['school_year']) ?></div>
                        </td>
                        <td>
                            <?= e((string) $row['last_name']) ?>, <?= e((string) $row['first_name']) ?>
                            <div class="small text-muted"><?= e((string) $row['email']) ?> | <?= e((string) $row['phone']) ?></div>
                        </td>
                        <td>
                            <?= e((string) $row['school_name']) ?> (<?= e(strtoupper((string) $row['school_type'])) ?>)
                            <div class="small text-muted"><?= e((string) $row['scholarship_type']) ?> / <?= e(strtoupper((string) $row['applicant_type'])) ?></div>
                        </td>
                        <td>
                            <span class="badge <?= status_badge_class((string) $row['status']) ?>">
                                <?= e(strtoupper((string) $row['status'])) ?>
                            </span>
                            <?php if ((string) $row['status'] === 'for_soa_submission' && !empty($row['soa_submission_deadline'])): ?>
                                <div class="small text-muted mt-1">
                                    SOA Deadline: <?= date('M d, Y', strtotime((string) $row['soa_submission_deadline'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ((string) $row['status'] === 'soa_submitted' && !empty($row['soa_submitted_at'])): ?>
                                <div class="small text-muted mt-1">
                                    SOA Received: <?= date('M d, Y h:i A', strtotime((string) $row['soa_submitted_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="min-width: 360px;">
                            <form method="post" class="row g-1" data-application-no="<?= e((string) $row['application_no']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                                <div class="col-12 col-xl-3">
                                    <select name="status" class="form-select form-select-sm">
                                        <?php foreach ($allowedStatus as $status): ?>
                                            <?php
                                            if (!$isAdmin && $status === 'for_soa_submission' && $status !== (string) $row['status']) {
                                                continue;
                                            }
                                            ?>
                                            <option value="<?= e($status) ?>" <?= $status === $row['status'] ? 'selected' : '' ?>>
                                                <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-xl-5">
                                    <input type="text" name="review_notes" class="form-control form-control-sm" placeholder="Review notes" value="<?= e((string) ($row['review_notes'] ?? '')) ?>">
                                </div>
                                <?php if ($isAdmin): ?>
                                    <div class="col-12 col-xl-2">
                                        <input
                                            type="date"
                                            name="soa_submission_deadline"
                                            class="form-control form-control-sm"
                                            value="<?= e((string) ($row['soa_submission_deadline'] ?? '')) ?>"
                                            title="SOA deadline (Admin only)"
                                        >
                                    </div>
                                <?php endif; ?>
                                <div class="col-12 col-xl-2 d-grid">
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                </div>
                            </form>
                            <?php if ($isAdmin): ?>
                                <div class="small text-muted mt-1">SOA deadline can be set/extended by admin.</div>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, Y h:i A', strtotime((string) $row['updated_at'])) ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-column gap-1 align-items-end">
                                <a href="../print-application.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <?php if ($isAdmin && in_array((string) $row['status'], $approvedPhaseStatuses, true)): ?>
                                    <form method="post" class="d-flex gap-1" data-application-no="<?= e((string) $row['application_no']) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="set_soa_deadline">
                                        <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                                        <input type="date" name="soa_submission_deadline" class="form-control form-control-sm" value="<?= e((string) ($row['soa_submission_deadline'] ?? '')) ?>" required>
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Set/Extend SOA</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((string) $row['status'] === 'for_soa_submission'): ?>
                                    <form method="post" data-application-no="<?= e((string) $row['application_no']) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="mark_soa_submitted">
                                        <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Mark SOA Submitted</button>
                                    </form>
                                <?php endif; ?>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

