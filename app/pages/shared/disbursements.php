<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Payout Events';
$approvedApplications = [];
$disbursements = [];
$disbursementSummary = [
    'ready_candidates' => 0,
    'scheduled_records' => 0,
    'released_records' => 0,
];
$hasDisbursementTime = db_ready() && table_column_exists($conn, 'disbursements', 'disbursement_time');
$hasSchoolTypeColumn = db_ready() && table_column_exists($conn, 'applications', 'school_type');
$hasBarangayColumn = db_ready() && table_column_exists($conn, 'applications', 'barangay');
$allowedBarangays = san_enrique_barangays();
$formatPayoutSchedule = static function (string $dateValue, ?string $timeValue = null): string {
    $dateValue = trim($dateValue);
    if ($dateValue === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) !== 1) {
        return '-';
    }

    $formatted = date('M d, Y', strtotime($dateValue));
    $timeValue = trim((string) $timeValue);
    if ($timeValue !== '' && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeValue) === 1) {
        $timeTs = strtotime($timeValue);
        if ($timeTs !== false) {
            $formatted .= ' ' . date('h:i A', $timeTs);
        }
    }

    return $formatted;
};

if (is_post() && db_ready()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
    } else {
        $action = trim((string) ($_POST['action'] ?? 'create_disbursement'));

        if ($action === 'update_disbursement_date') {
            $disbursementId = (int) ($_POST['disbursement_id'] ?? 0);
            $newDate = trim((string) ($_POST['disbursement_date'] ?? ''));
            $newTime = trim((string) ($_POST['disbursement_time'] ?? ''));
            $isDateValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) === 1;
            $isTimeValid = !$hasDisbursementTime || $newTime === '' || preg_match('/^\d{2}:\d{2}$/', $newTime) === 1;
            if (
                $disbursementId <= 0
                || $newDate === ''
                || !$isDateValid
                || !$isTimeValid
            ) {
                set_flash('danger', 'Please provide a valid payout schedule date/time.');
                redirect('disbursements.php');
            }

            $currentTimeSelectSql = $hasDisbursementTime ? ', d.disbursement_time' : ', NULL AS disbursement_time';
            $stmtCurrent = $conn->prepare(
                "SELECT d.disbursement_date{$currentTimeSelectSql}, d.reference_no, a.application_no, u.id AS user_id, u.phone
                 FROM disbursements d
                 INNER JOIN applications a ON a.id = d.application_id
                 INNER JOIN users u ON u.id = a.user_id
                 WHERE d.id = ?
                 LIMIT 1"
            );
            $stmtCurrent->bind_param('i', $disbursementId);
            $stmtCurrent->execute();
            $current = $stmtCurrent->get_result()->fetch_assoc();
            $stmtCurrent->close();

            if (!$current) {
                set_flash('danger', 'Disbursement record not found.');
                redirect('disbursements.php');
            }

            if ($hasDisbursementTime) {
                $stmt = $conn->prepare(
                    "UPDATE disbursements
                     SET disbursement_date = ?, disbursement_time = NULLIF(?, '')
                     WHERE id = ?
                     LIMIT 1"
                );
                $stmt->bind_param('ssi', $newDate, $newTime, $disbursementId);
            } else {
                $stmt = $conn->prepare("UPDATE disbursements SET disbursement_date = ? WHERE id = ? LIMIT 1");
                $stmt->bind_param('si', $newDate, $disbursementId);
            }
            $stmt->execute();
            $stmt->close();

            $previousDate = (string) ($current['disbursement_date'] ?? '');
            $previousTime = trim((string) ($current['disbursement_time'] ?? ''));
            $hasScheduleChange = $previousDate !== $newDate
                || ($hasDisbursementTime && $previousTime !== $newTime);

            if ($hasScheduleChange) {
                $updatedSchedule = $formatPayoutSchedule($newDate, $hasDisbursementTime ? $newTime : null);
                $message = 'San Enrique LGU Scholarship: You have been approved. Payout schedule is '
                    . $updatedSchedule
                    . ' for application ' . $current['application_no']
                    . '. Ref No: ' . $current['reference_no'] . '.';
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'Payout Schedule Updated',
                    'Payout Schedule for application ' . (string) ($current['application_no'] ?? '') . ' is now ' . $updatedSchedule . '. Ref No: ' . (string) ($current['reference_no'] ?? '') . '.',
                    'payout',
                    'my-application.php',
                    (int) (current_user()['id'] ?? 0)
                );
            }
            audit_log(
                $conn,
                'disbursement_date_updated',
                null,
                null,
                'disbursement',
                (string) $disbursementId,
                'Disbursement payout schedule updated.',
                [
                    'application_no' => (string) ($current['application_no'] ?? ''),
                    'reference_no' => (string) ($current['reference_no'] ?? ''),
                    'previous_date' => $previousDate,
                    'previous_time' => $hasDisbursementTime ? $previousTime : '',
                    'new_date' => $newDate,
                    'new_time' => $hasDisbursementTime ? $newTime : '',
                ]
            );

            set_flash('success', 'Payout schedule updated.');
        } elseif ($action === 'create_bulk_disbursement') {
            $amount = (float) ($_POST['amount'] ?? 0);
            $date = trim((string) ($_POST['disbursement_date'] ?? ''));
            $time = trim((string) ($_POST['disbursement_time'] ?? ''));
            $referenceNo = trim((string) ($_POST['reference_no'] ?? ''));
            $location = trim((string) ($_POST['payout_location'] ?? ''));
            $remarks = trim((string) ($_POST['remarks'] ?? ''));
            $selectedSchoolTypes = $_POST['school_types'] ?? [];
            if (!is_array($selectedSchoolTypes)) {
                $selectedSchoolTypes = [];
            }
            $selectedSchoolTypes = array_values(array_unique(array_filter(array_map(
                static fn($value): string => strtolower(trim((string) $value)),
                $selectedSchoolTypes
            ), static fn($value): bool => in_array($value, ['public', 'private'], true))));
            if ($hasSchoolTypeColumn && !$selectedSchoolTypes) {
                set_flash('warning', 'Please select at least one school type for bulk payout.');
                redirect('disbursements.php');
            }

            $selectedBarangays = $_POST['barangays'] ?? [];
            if (!is_array($selectedBarangays)) {
                $selectedBarangays = [];
            }
            $selectedBarangays = array_values(array_unique(array_filter(array_map(
                static fn($value): string => trim((string) $value),
                $selectedBarangays
            ), static fn($value): bool => in_array($value, $allowedBarangays, true))));

            $isDateValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
            $isTimeValid = !$hasDisbursementTime || $time === '' || preg_match('/^\d{2}:\d{2}$/', $time) === 1;

            if ($amount <= 0 || !$isDateValid || !$isTimeValid || $referenceNo === '') {
                set_flash('danger', 'Please complete required payout schedule details.');
                redirect('disbursements.php');
            }

            $where = ["a.status = 'approved_for_release'"];
            if ($hasSchoolTypeColumn && $selectedSchoolTypes) {
                $escapedSchoolTypes = array_map(
                    static function ($value) use ($conn): string {
                        return "'" . $conn->real_escape_string((string) $value) . "'";
                    },
                    $selectedSchoolTypes
                );
                $where[] = 'a.school_type IN (' . implode(', ', $escapedSchoolTypes) . ')';
            }
            if ($hasBarangayColumn && $selectedBarangays) {
                $escapedBarangays = array_map(
                    static function ($value) use ($conn): string {
                        return "'" . $conn->real_escape_string((string) $value) . "'";
                    },
                    $selectedBarangays
                );
                $where[] = 'a.barangay IN (' . implode(', ', $escapedBarangays) . ')';
            }

            $sqlTargets = "SELECT a.id, a.application_no, a.school_type, a.barangay, u.id AS user_id, u.phone
                           FROM applications a
                           INNER JOIN users u ON u.id = a.user_id
                           WHERE " . implode(' AND ', $where) . "
                           ORDER BY a.updated_at DESC, a.id DESC";
            $resultTargets = $conn->query($sqlTargets);
            $targets = $resultTargets instanceof mysqli_result ? $resultTargets->fetch_all(MYSQLI_ASSOC) : [];

            if (!$targets) {
                set_flash('warning', 'No payout-ready applicants found for selected filters.');
                redirect('disbursements.php');
            }

            $status = 'scheduled';
            $createdCount = 0;
            $failedCount = 0;
            $currentActorId = (int) (current_user()['id'] ?? 0);
            $scheduleLabel = $formatPayoutSchedule($date, $hasDisbursementTime ? $time : null);

            if ($hasDisbursementTime) {
                $stmtInsert = $conn->prepare(
                    "INSERT INTO disbursements
                    (application_id, amount, disbursement_date, disbursement_time, reference_no, payout_location, status, remarks)
                    VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?)"
                );
            } else {
                $stmtInsert = $conn->prepare(
                    "INSERT INTO disbursements
                    (application_id, amount, disbursement_date, reference_no, payout_location, status, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
            }

            if (!$stmtInsert) {
                set_flash('danger', 'Unable to create bulk payout schedule right now.');
                redirect('disbursements.php');
            }

            foreach ($targets as $target) {
                $applicationId = (int) ($target['id'] ?? 0);
                if ($applicationId <= 0) {
                    $failedCount++;
                    continue;
                }

                $ok = false;
                if ($hasDisbursementTime) {
                    $ok = $stmtInsert->bind_param(
                        'idssssss',
                        $applicationId,
                        $amount,
                        $date,
                        $time,
                        $referenceNo,
                        $location,
                        $status,
                        $remarks
                    ) && $stmtInsert->execute();
                } else {
                    $ok = $stmtInsert->bind_param(
                        'idsssss',
                        $applicationId,
                        $amount,
                        $date,
                        $referenceNo,
                        $location,
                        $status,
                        $remarks
                    ) && $stmtInsert->execute();
                }

                if (!$ok) {
                    $failedCount++;
                    continue;
                }

                $createdCount++;
                $applicationNo = (string) ($target['application_no'] ?? '');
                $userId = (int) ($target['user_id'] ?? 0);
                $phone = (string) ($target['phone'] ?? '');
                $message = 'San Enrique LGU Scholarship: You have been approved. Payout schedule is '
                    . $scheduleLabel
                    . ' for application ' . $applicationNo
                    . '. Ref No: ' . $referenceNo . '.';

                sms_send($phone, $message, $userId, 'status_update');
                create_notification(
                    $conn,
                    $userId,
                    'Payout Schedule Created',
                    'Your payout for application ' . $applicationNo . ' is scheduled on ' . $scheduleLabel . '. Ref No: ' . $referenceNo . '.',
                    'payout',
                    'my-application.php',
                    $currentActorId
                );
            }
            $stmtInsert->close();

            audit_log(
                $conn,
                'disbursement_created',
                null,
                null,
                'disbursement',
                null,
                'Bulk payout schedule created.',
                [
                    'created_count' => $createdCount,
                    'failed_count' => $failedCount,
                    'filters' => [
                        'school_types' => $selectedSchoolTypes,
                        'barangays' => $selectedBarangays,
                    ],
                    'reference_no' => $referenceNo,
                    'amount' => $amount,
                    'date' => $date,
                    'time' => $hasDisbursementTime ? $time : '',
                ]
            );

            if ($createdCount <= 0) {
                set_flash('danger', 'No payout schedules were created. Please try again.');
            } elseif ($failedCount > 0) {
                set_flash('warning', 'Bulk payout schedule created for ' . $createdCount . ' applicant(s), with ' . $failedCount . ' failed record(s).');
            } else {
                set_flash('success', 'Bulk payout schedule created for ' . $createdCount . ' applicant(s).');
            }
        } elseif ($action === 'create_disbursement') {
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);
            $date = trim((string) ($_POST['disbursement_date'] ?? ''));
            $time = trim((string) ($_POST['disbursement_time'] ?? ''));
            $referenceNo = trim((string) ($_POST['reference_no'] ?? ''));
            $location = trim((string) ($_POST['payout_location'] ?? ''));
            $remarks = trim((string) ($_POST['remarks'] ?? ''));
            $isDateValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
            $isTimeValid = !$hasDisbursementTime || $time === '' || preg_match('/^\d{2}:\d{2}$/', $time) === 1;

            if ($applicationId <= 0 || $amount <= 0 || !$date || !$referenceNo || !$isDateValid || !$isTimeValid) {
                set_flash('danger', 'Please complete required payout schedule details.');
            } else {
                $status = 'scheduled';
                $stmtApplicant = $conn->prepare(
                    "SELECT a.application_no, u.id AS user_id, u.phone
                     FROM applications a
                     INNER JOIN users u ON u.id = a.user_id
                     WHERE a.id = ?
                     LIMIT 1"
                );
                $stmtApplicant->bind_param('i', $applicationId);
                $stmtApplicant->execute();
                $applicant = $stmtApplicant->get_result()->fetch_assoc();
                $stmtApplicant->close();

                if ($hasDisbursementTime) {
                    $stmt = $conn->prepare(
                        "INSERT INTO disbursements
                        (application_id, amount, disbursement_date, disbursement_time, reference_no, payout_location, status, remarks)
                        VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?)"
                    );
                    $stmt->bind_param('idssssss', $applicationId, $amount, $date, $time, $referenceNo, $location, $status, $remarks);
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO disbursements
                        (application_id, amount, disbursement_date, reference_no, payout_location, status, remarks)
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param('idsssss', $applicationId, $amount, $date, $referenceNo, $location, $status, $remarks);
                }
                $stmt->execute();
                $newDisbursementId = (int) $stmt->insert_id;
                $stmt->close();

                if ($applicant) {
                    $scheduledPayout = $formatPayoutSchedule($date, $hasDisbursementTime ? $time : null);
                    $message = 'San Enrique LGU Scholarship: You have been approved. Payout schedule is '
                        . $scheduledPayout
                        . ' for application ' . $applicant['application_no']
                        . '. Ref No: ' . $referenceNo . '.';
                    sms_send((string) ($applicant['phone'] ?? ''), $message, (int) ($applicant['user_id'] ?? 0), 'status_update');
                    create_notification(
                        $conn,
                        (int) ($applicant['user_id'] ?? 0),
                        'Payout Schedule Created',
                        'Your payout for application ' . (string) ($applicant['application_no'] ?? '') . ' is scheduled on ' . $scheduledPayout . '. Ref No: ' . $referenceNo . '.',
                        'payout',
                        'my-application.php',
                        (int) (current_user()['id'] ?? 0)
                    );
                }
                audit_log(
                    $conn,
                    'disbursement_created',
                    null,
                    null,
                    'disbursement',
                    (string) $newDisbursementId,
                    'New disbursement record created.',
                    [
                        'application_id' => $applicationId,
                        'application_no' => (string) ($applicant['application_no'] ?? ''),
                        'reference_no' => $referenceNo,
                        'amount' => $amount,
                        'date' => $date,
                        'time' => $hasDisbursementTime ? $time : '',
                    ]
                );
                set_flash('success', 'Payout schedule created.');
            }
        } else {
            set_flash('warning', 'Unknown payout action.');
        }
    }
    redirect('disbursements.php');
}

if (db_ready()) {
    $sql = "SELECT a.id, u.first_name, u.last_name, a.applicant_type
            FROM applications a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.status = 'approved_for_release'
            ORDER BY a.updated_at DESC";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $approvedApplications = $result->fetch_all(MYSQLI_ASSOC);
    }
    $disbursementSummary['ready_candidates'] = count($approvedApplications);

    $timeSelectSql = $hasDisbursementTime ? ', d.disbursement_time' : ', NULL AS disbursement_time';
    $timeOrderSql = $hasDisbursementTime ? ", COALESCE(d.disbursement_time, '00:00:00') DESC" : '';
    $sql = "SELECT d.id, d.amount, d.disbursement_date{$timeSelectSql}, d.reference_no, d.status,
                   u.first_name, u.last_name, a.application_no, a.applicant_type
            FROM disbursements d
            INNER JOIN applications a ON a.id = d.application_id
            INNER JOIN users u ON u.id = a.user_id
            ORDER BY d.disbursement_date DESC{$timeOrderSql}, d.id DESC";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $disbursements = $result->fetch_all(MYSQLI_ASSOC);
    }
    $disbursementSummary['scheduled_records'] = count(array_filter($disbursements, static function (array $row): bool {
        return (string) ($row['status'] ?? '') === 'scheduled';
    }));
    $disbursementSummary['released_records'] = count(array_filter($disbursements, static function (array $row): bool {
        return (string) ($row['status'] ?? '') === 'released';
    }));
}

include __DIR__ . '/../../includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Payout';
$pageHeaderTitle = 'Payout Events';
$pageHeaderDescription = 'Keep payout work event-based: schedule payout batches, schedule one-off releases, and review released history.';
$pageHeaderSecondaryInfo = 'Ready to release: <strong>' . number_format((int) ($disbursementSummary['ready_candidates'] ?? 0)) . '</strong>. Scheduled: <strong>' . number_format((int) ($disbursementSummary['scheduled_records'] ?? 0)) . '</strong>. Released: <strong>' . number_format((int) ($disbursementSummary['released_records'] ?? 0)) . '</strong>.';
include __DIR__ . '/../../includes/partials/page-shell-header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="card card-soft metric-card h-100">
            <div class="card-body">
                <p class="small text-muted mb-1">Ready to Release</p>
                <h3><?= number_format((int) ($disbursementSummary['ready_candidates'] ?? 0)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card card-soft metric-card h-100">
            <div class="card-body">
                <p class="small text-muted mb-1">Scheduled Payouts</p>
                <h3><?= number_format((int) ($disbursementSummary['scheduled_records'] ?? 0)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card card-soft metric-card h-100">
            <div class="card-body">
                <p class="small text-muted mb-1">Released History</p>
                <h3><?= number_format((int) ($disbursementSummary['released_records'] ?? 0)) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card card-soft shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2" id="disbursementWorkflowTabs">
            <button type="button" class="btn btn-sm btn-outline-primary active" data-workflow-tab="bulk">Ready to Release</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-workflow-tab="single">Single Schedule</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-workflow-tab="records">Released History</button>
        </div>
    </div>
</div>

<div id="disbursementBulkSection" data-workflow-section="bulk" class="card card-soft shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
            <h2 class="h6 mb-0">Schedule payout batch</h2>
            <span class="badge text-bg-info">Recommended for payout batches</span>
        </div>
        <p class="small text-muted mb-3">Create one payout event for multiple payout-ready applicants using school type and/or barangay filters.</p>
        <form method="post" class="row g-3" data-crud-modal="1" data-crud-title="Create Bulk Payout Schedule?" data-crud-message="Create payout schedule for all matching applicants?" data-crud-confirm-text="Create Schedule" data-crud-kind="primary">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_bulk_disbursement">

            <div class="col-6 col-md-2">
                <label class="form-label">Amount *</label>
                <input type="number" step="0.01" class="form-control" name="amount" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Date *</label>
                <input type="date" class="form-control" name="disbursement_date" required>
            </div>
            <?php if ($hasDisbursementTime): ?>
                <div class="col-6 col-md-2">
                    <label class="form-label">Time</label>
                    <input type="time" class="form-control" name="disbursement_time">
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-<?= $hasDisbursementTime ? '3' : '4' ?>">
                <label class="form-label">Payout Batch/Reference No. *</label>
                <input type="text" class="form-control" name="reference_no" required placeholder="Example: BATCH-2026-001">
                <div class="form-text">Official payout batch or transaction code from the LGU office.</div>
            </div>
            <div class="col-12 col-md-<?= $hasDisbursementTime ? '3' : '4' ?>">
                <label class="form-label">Payout Location</label>
                <input type="text" class="form-control" name="payout_location" placeholder="LGU Treasury Office">
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label d-block">School Type Filter</label>
                <?php if ($hasSchoolTypeColumn): ?>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bulkSchoolTypePublic" name="school_types[]" value="public" checked>
                            <label class="form-check-label" for="bulkSchoolTypePublic">Public</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bulkSchoolTypePrivate" name="school_types[]" value="private" checked>
                            <label class="form-check-label" for="bulkSchoolTypePrivate">Private</label>
                        </div>
                    </div>
                    <div class="form-text">Check one or both. Leave both checked to include all school types.</div>
                <?php else: ?>
                    <div class="form-text text-muted">School type filter is unavailable in current database setup.</div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">Barangay Filter</label>
                <?php if ($hasBarangayColumn): ?>
                    <div class="border rounded-3 p-3 bg-light-subtle">
                        <div class="row g-2">
                            <?php foreach ($allowedBarangays as $index => $barangay): ?>
                                <?php $checkboxId = 'bulkBarangay_' . ($index + 1); ?>
                                <div class="col-12 col-sm-6">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="<?= e($checkboxId) ?>"
                                            name="barangays[]"
                                            value="<?= e($barangay) ?>"
                                            checked
                                        >
                                        <label class="form-check-label" for="<?= e($checkboxId) ?>"><?= e($barangay) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-text">Optional. Uncheck one or more barangays to exclude them. Leave all checked to include all barangays.</div>
                <?php else: ?>
                    <div class="form-text text-muted">Barangay filter is unavailable in current database setup.</div>
                <?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label">Remarks</label>
                <input type="text" class="form-control" name="remarks">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-layer-group me-1"></i>Create Bulk Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<div id="disbursementSingleSection" data-workflow-section="single" class="card card-soft shadow-sm mb-4 d-none">
    <div class="card-body">
        <h2 class="h6">Schedule one payout record</h2>
        <form method="post" class="row g-3" data-crud-modal="1" data-crud-title="Create Single Payout Schedule?" data-crud-message="Create payout schedule for this applicant?" data-crud-confirm-text="Create Schedule" data-crud-kind="primary">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_disbursement">
            <div class="col-12 col-md-4">
                <label class="form-label">Payout-ready Application *</label>
                <select class="form-select" name="application_id" required>
                    <option value="">Select</option>
                    <?php foreach ($approvedApplications as $app): ?>
                        <option value="<?= (int) $app['id'] ?>">
                            #<?= (int) $app['id'] ?> - <?= e($app['first_name'] . ' ' . $app['last_name']) ?> (<?= e(strtoupper((string) $app['applicant_type'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Amount *</label>
                <input type="number" step="0.01" class="form-control" name="amount" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Date *</label>
                <input type="date" class="form-control" name="disbursement_date" required>
            </div>
            <?php if ($hasDisbursementTime): ?>
                <div class="col-6 col-md-2">
                    <label class="form-label">Time</label>
                    <input type="time" class="form-control" name="disbursement_time">
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-<?= $hasDisbursementTime ? '2' : '4' ?>">
                <label class="form-label">Payout Batch/Reference No. *</label>
                <input type="text" class="form-control" name="reference_no" required>
                <div class="form-text">Official payout batch or transaction code from the LGU office.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Payout Location</label>
                <input type="text" class="form-control" name="payout_location" placeholder="LGU Treasury Office">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Remarks</label>
                <input type="text" class="form-control" name="remarks">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Create Single Schedule</button>
            </div>
        </form>
    </div>
</div>

<div id="disbursementRecordsSection" data-workflow-section="records" data-live-table class="card card-soft shadow-sm">
    <div class="card-body border-bottom table-controls">
        <h2 class="h6">Payout history and scheduled records</h2>
        <?php if ($disbursements): ?>
            <div class="row g-2 align-items-end mt-1">
                <div class="col-12 col-md-5">
                    <label class="form-label form-label-sm">Live Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search scholar, application no, ref no">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm">Status</label>
                    <select data-table-filter class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="released">Released</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Rows</label>
                    <select data-table-per-page class="form-select form-select-sm">
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 text-md-end">
                    <span class="page-legend" data-table-summary></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$disbursements): ?>
            <p class="text-muted mb-0">No disbursement records yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-simple-list="1" data-simple-list-visible="3">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Applicant Type</th>
                            <th>Amount</th>
                            <th>Payout Schedule</th>
                            <th>Payout Ref No.</th>
                            <th>Status</th>
                            <th class="text-end">Update Schedule</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($disbursements as $row): ?>
                        <?php
                        $payoutSchedule = $formatPayoutSchedule(
                            (string) ($row['disbursement_date'] ?? ''),
                            $hasDisbursementTime ? (string) ($row['disbursement_time'] ?? '') : null
                        );
                        ?>
                        <?php
                        $search = strtolower(implode(' ', [
                            (string) ($row['first_name'] ?? ''),
                            (string) ($row['last_name'] ?? ''),
                            (string) ($row['application_no'] ?? ''),
                            (string) ($row['reference_no'] ?? ''),
                            (string) ($row['status'] ?? ''),
                        ]));
                        ?>
                        <tr data-search="<?= e($search) ?>" data-filter="<?= e((string) ($row['status'] ?? '')) ?>">
                            <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td>
                                <?= e(strtoupper((string) $row['applicant_type'])) ?>
                                <div class="small text-muted"><?= e((string) $row['application_no']) ?></div>
                            </td>
                            <td>PHP <?= number_format((float) $row['amount'], 2) ?></td>
                            <td><?= e($payoutSchedule) ?></td>
                            <td><?= e($row['reference_no']) ?></td>
                            <td><span class="badge <?= status_badge_class((string) $row['status']) ?>"><?= e(strtoupper((string) $row['status'])) ?></span></td>
                            <td class="text-end">
                                <form method="post" class="d-flex justify-content-end gap-1" data-application-no="<?= e((string) $row['application_no']) ?>" data-reference-no="<?= e((string) $row['reference_no']) ?>" data-crud-modal="1" data-crud-title="Update Payout Schedule?" data-crud-message="Save updated payout schedule for this applicant?" data-crud-confirm-text="Update Schedule" data-crud-kind="primary">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_disbursement_date">
                                    <input type="hidden" name="disbursement_id" value="<?= (int) $row['id'] ?>">
                                    <input type="date" class="form-control form-control-sm" name="disbursement_date" value="<?= e((string) $row['disbursement_date']) ?>" required>
                                    <?php if ($hasDisbursementTime): ?>
                                        <input type="time" class="form-control form-control-sm" name="disbursement_time" value="<?= e(substr((string) ($row['disbursement_time'] ?? ''), 0, 5)) ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-sm btn-primary">Update Schedule</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body border-top d-flex justify-content-end">
        <div class="d-flex gap-2" data-table-pager></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = Array.from(document.querySelectorAll('[data-workflow-tab]'));
    const sections = Array.from(document.querySelectorAll('[data-workflow-section]'));

    function setActiveTab(tabKey) {
        tabButtons.forEach(function (button) {
            const key = String(button.getAttribute('data-workflow-tab') || '').trim();
            button.classList.toggle('active', key === tabKey);
        });
        sections.forEach(function (section) {
            const key = String(section.getAttribute('data-workflow-section') || '').trim();
            section.classList.toggle('d-none', key !== tabKey);
        });
    }

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const key = String(button.getAttribute('data-workflow-tab') || '').trim();
            if (key === '') {
                return;
            }
            setActiveTab(key);
        });
    });

    setActiveTab('bulk');
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

