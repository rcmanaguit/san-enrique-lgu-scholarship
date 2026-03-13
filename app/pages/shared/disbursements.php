<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Payout Queue';
$approvedApplications = [];
$disbursements = [];
$scheduledDisbursements = [];
$releasedDisbursements = [];
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
$formatApplicantReference = static function (array $current): string {
    $applicationNo = trim((string) ($current['application_no'] ?? ''));
    $lastName = trim((string) ($current['last_name'] ?? ''));
    if ($lastName !== '' && $applicationNo !== '') {
        return 'Mr./Ms. ' . $lastName . ', Application No. ' . $applicationNo;
    }
    if ($applicationNo !== '') {
        return 'Application No. ' . $applicationNo;
    }
    if ($lastName !== '') {
        return 'Mr./Ms. ' . $lastName;
    }
    return '';
};
$prependApplicantReference = static function (string $message, array $current) use ($formatApplicantReference): string {
    $reference = $formatApplicantReference($current);
    $message = trim($message);
    if ($reference === '' || $message === '') {
        return $message;
    }
    if (str_starts_with($message, $reference)) {
        return $message;
    }
    if (preg_match('/^(San Enrique LGU Scholarship:)\s*(.*)$/', $message, $matches) === 1) {
        return $matches[1] . ' ' . $reference . '. ' . ltrim((string) ($matches[2] ?? ''));
    }
    return $reference . ': ' . $message;
};

if (is_post() && db_ready()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
    } else {
        $action = trim((string) ($_POST['action'] ?? 'create_bulk_disbursement'));

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
                "SELECT d.disbursement_date{$currentTimeSelectSql}, d.reference_no, a.application_no, u.id AS user_id, u.phone, u.last_name
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
                    . '. Ref No: ' . $current['reference_no'] . '. Please arrive early with a valid ID. First come, first served.';
                $message = $prependApplicantReference($message, $current);
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'Payout Schedule Updated',
                    $prependApplicantReference('Payout schedule is now ' . $updatedSchedule . '. Ref No: ' . (string) ($current['reference_no'] ?? '') . '. First come, first served.', $current),
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

            $sqlTargets = "SELECT a.id, a.application_no, a.school_type, a.barangay, u.id AS user_id, u.phone, u.last_name
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
                    . '. Ref No: ' . $referenceNo . '. Please arrive early with a valid ID. First come, first served.';
                $message = $prependApplicantReference($message, $target);

                sms_send($phone, $message, $userId, 'status_update');
                create_notification(
                    $conn,
                    $userId,
                    'Payout Schedule Created',
                    $prependApplicantReference('Your payout is scheduled on ' . $scheduleLabel . '. Ref No: ' . $referenceNo . '. First come, first served.', $target),
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
            set_flash('warning', 'Payout scheduling is managed by batch on this page.');
        } else {
            set_flash('warning', 'Unknown payout action.');
        }
    }
    redirect('disbursements.php');
}

if (db_ready()) {
    $sql = "SELECT a.id, a.application_no, u.first_name, u.last_name, a.applicant_type
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
    $scheduledDisbursements = array_values(array_filter($disbursements, static function (array $row): bool {
        return (string) ($row['status'] ?? '') === 'scheduled';
    }));
    $releasedDisbursements = array_values(array_filter($disbursements, static function (array $row): bool {
        return (string) ($row['status'] ?? '') === 'released';
    }));
}

include __DIR__ . '/../../includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Payout';
$pageHeaderTitle = 'Payout Queue';
$pageHeaderDescription = 'Schedule release dates by batch for payout-ready scholars and review scheduled and released history.';
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
            <button type="button" class="btn btn-sm btn-outline-primary active" data-workflow-tab="ready">Ready for Release</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-workflow-tab="scheduled">Scheduled</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-workflow-tab="released">Released</button>
        </div>
    </div>
</div>

<div id="disbursementReadySection" data-workflow-section="ready" class="card card-soft shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h6 mb-1">Ready for Release</h2>
                <p class="small text-muted mb-0">Review payout-ready scholars here, then create one batch payout schedule for the release group.</p>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="toggleBatchSchedule">
                <i class="fa-solid fa-layer-group me-1"></i>Schedule by Batch
            </button>
        </div>
        <div id="batchSchedulePanel" class="border rounded-3 bg-light-subtle p-3 mb-3 d-none">
            <h3 class="h6 mb-3">Schedule by Batch</h3>
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
                    <label class="form-label">Reference No. *</label>
                    <input type="text" class="form-control" name="reference_no" required placeholder="Example: BATCH-2026-001">
                </div>
                <div class="col-12 col-md-<?= $hasDisbursementTime ? '3' : '4' ?>">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" name="payout_location" placeholder="LGU Treasury Office">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label d-block">School Type</label>
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
                    <?php else: ?>
                        <div class="form-text text-muted">School type filter is unavailable in current database setup.</div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Barangay</label>
                    <?php if ($hasBarangayColumn): ?>
                        <div class="border rounded-3 p-3 bg-white">
                            <div class="row g-2">
                                <?php foreach ($allowedBarangays as $index => $barangay): ?>
                                    <?php $checkboxId = 'bulkBarangay_' . ($index + 1); ?>
                                    <div class="col-12 col-sm-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="<?= e($checkboxId) ?>" name="barangays[]" value="<?= e($barangay) ?>" checked>
                                            <label class="form-check-label" for="<?= e($checkboxId) ?>"><?= e($barangay) ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
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
                        <i class="fa-solid fa-layer-group me-1"></i>Create Batch Schedule
                    </button>
                </div>
            </form>
        </div>
        <?php if (!$approvedApplications): ?>
            <p class="text-muted mb-0">No payout-ready scholars right now.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($approvedApplications as $app): ?>
                    <div class="col-12">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                <div>
                                    <h3 class="h6 mb-1"><?= e($app['first_name'] . ' ' . $app['last_name']) ?></h3>
                                    <div class="small text-muted">
                                        <?= e((string) ($app['application_no'] ?? '')) ?>
                                        <?php if (!empty($app['applicant_type'])): ?>
                                            <span class="mx-1">|</span><?= e(strtoupper((string) $app['applicant_type'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge text-bg-secondary">Ready for Release</span>
                            </div>
                            <div class="small text-muted">
                                Use <strong>Schedule by Batch</strong> above to create the payout schedule for this group.
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="disbursementScheduledSection" data-workflow-section="scheduled" class="card card-soft shadow-sm mb-4 d-none">
    <div class="card-body border-bottom">
        <h2 class="h6 mb-1">Scheduled</h2>
        <p class="small text-muted mb-0">Upcoming batch payout schedules.</p>
    </div>
    <div class="card-body">
        <?php if (!$scheduledDisbursements): ?>
            <p class="text-muted mb-0">No scheduled payout records yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Amount</th>
                            <th>Schedule</th>
                            <th>Reference No.</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($scheduledDisbursements as $row): ?>
                        <?php $payoutSchedule = $formatPayoutSchedule((string) ($row['disbursement_date'] ?? ''), $hasDisbursementTime ? (string) ($row['disbursement_time'] ?? '') : null); ?>
                        <tr>
                            <td>
                                <strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                                <div class="small text-muted"><?= e((string) $row['application_no']) ?></div>
                            </td>
                            <td>PHP <?= number_format((float) $row['amount'], 2) ?></td>
                            <td><?= e($payoutSchedule) ?></td>
                            <td><?= e($row['reference_no']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="disbursementReleasedSection" data-workflow-section="released" data-live-table class="card card-soft shadow-sm d-none">
    <div class="card-body border-bottom table-controls">
        <h2 class="h6">Released</h2>
        <?php if ($releasedDisbursements): ?>
            <div class="row g-2 align-items-end mt-1">
                <div class="col-12 col-md-5">
                    <label class="form-label form-label-sm">Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search scholar, application no, ref no">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Rows</label>
                    <select data-table-per-page class="form-select form-select-sm">
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="col-12 col-md-5 text-md-end">
                    <span class="page-legend" data-table-summary></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$releasedDisbursements): ?>
            <p class="text-muted mb-0">No released payout records yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Amount</th>
                            <th>Release Schedule</th>
                            <th>Reference No.</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($releasedDisbursements as $row): ?>
                        <?php $payoutSchedule = $formatPayoutSchedule((string) ($row['disbursement_date'] ?? ''), $hasDisbursementTime ? (string) ($row['disbursement_time'] ?? '') : null); ?>
                        <?php $search = strtolower(implode(' ', [(string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''), (string) ($row['application_no'] ?? ''), (string) ($row['reference_no'] ?? ''), (string) ($row['status'] ?? '')])); ?>
                        <tr data-search="<?= e($search) ?>">
                            <td>
                                <strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                                <div class="small text-muted"><?= e((string) $row['application_no']) ?></div>
                            </td>
                            <td>PHP <?= number_format((float) $row['amount'], 2) ?></td>
                            <td><?= e($payoutSchedule) ?></td>
                            <td><?= e($row['reference_no']) ?></td>
                            <td><span class="badge <?= status_badge_class((string) $row['status']) ?>"><?= e(strtoupper((string) $row['status'])) ?></span></td>
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
    const batchToggleButton = document.getElementById('toggleBatchSchedule');
    const batchSchedulePanel = document.getElementById('batchSchedulePanel');

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

    if (batchToggleButton && batchSchedulePanel) {
        batchToggleButton.addEventListener('click', function () {
            const shouldOpen = batchSchedulePanel.classList.contains('d-none');
            batchSchedulePanel.classList.toggle('d-none', !shouldOpen);
            batchToggleButton.classList.toggle('btn-outline-primary', !shouldOpen);
            batchToggleButton.classList.toggle('btn-primary', shouldOpen);
        });
    }

    setActiveTab('ready');
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

