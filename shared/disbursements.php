<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Disbursement Management';
$approvedApplications = [];
$disbursements = [];

if (is_post() && db_ready()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
    } else {
        $action = trim((string) ($_POST['action'] ?? 'create_disbursement'));

        if ($action === 'update_disbursement_date') {
            $disbursementId = (int) ($_POST['disbursement_id'] ?? 0);
            $newDate = trim((string) ($_POST['disbursement_date'] ?? ''));
            if (
                $disbursementId <= 0
                || $newDate === ''
                || preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) !== 1
            ) {
                set_flash('danger', 'Please provide a valid payout date.');
                redirect('disbursements.php');
            }

            $stmtCurrent = $conn->prepare(
                "SELECT d.disbursement_date, d.reference_no, a.application_no, u.id AS user_id, u.phone
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

            $stmt = $conn->prepare("UPDATE disbursements SET disbursement_date = ? WHERE id = ? LIMIT 1");
            $stmt->bind_param('si', $newDate, $disbursementId);
            $stmt->execute();
            $stmt->close();

            if ((string) ($current['disbursement_date'] ?? '') !== $newDate) {
                $message = 'San Enrique LGU Scholarship: Payout Schedule date for application '
                    . $current['application_no'] . ' was updated to '
                    . date('M d, Y', strtotime($newDate))
                    . '. Ref No: ' . $current['reference_no'] . '.';
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'Payout Schedule Updated',
                    'Payout Schedule date for application ' . (string) ($current['application_no'] ?? '') . ' is now ' . date('M d, Y', strtotime($newDate)) . '. Ref No: ' . (string) ($current['reference_no'] ?? '') . '.',
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
                'Disbursement date updated.',
                [
                    'application_no' => (string) ($current['application_no'] ?? ''),
                    'reference_no' => (string) ($current['reference_no'] ?? ''),
                    'previous_date' => (string) ($current['disbursement_date'] ?? ''),
                    'new_date' => $newDate,
                ]
            );

            set_flash('success', 'Payout date updated.');
        } else {
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);
            $date = trim((string) ($_POST['disbursement_date'] ?? ''));
            $referenceNo = trim((string) ($_POST['reference_no'] ?? ''));
            $location = trim((string) ($_POST['payout_location'] ?? ''));
            $remarks = trim((string) ($_POST['remarks'] ?? ''));

            if ($applicationId <= 0 || $amount <= 0 || !$date || !$referenceNo) {
                set_flash('danger', 'Please complete required disbursement details.');
            } else {
                $status = 'scheduled';
                $qrToken = bin2hex(random_bytes(12));
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

                $stmt = $conn->prepare(
                    "INSERT INTO disbursements
                    (application_id, amount, disbursement_date, reference_no, payout_location, status, qr_token, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('idssssss', $applicationId, $amount, $date, $referenceNo, $location, $status, $qrToken, $remarks);
                $stmt->execute();
                $newDisbursementId = (int) $stmt->insert_id;
                $stmt->close();

                if ($applicant) {
                    $message = 'San Enrique LGU Scholarship: Payout Schedule for application ' . $applicant['application_no']
                        . ' is scheduled on ' . date('M d, Y', strtotime($date))
                        . '. Ref No: ' . $referenceNo . '.';
                    sms_send((string) ($applicant['phone'] ?? ''), $message, (int) ($applicant['user_id'] ?? 0), 'status_update');
                    create_notification(
                        $conn,
                        (int) ($applicant['user_id'] ?? 0),
                        'Payout Schedule Created',
                        'Your payout for application ' . (string) ($applicant['application_no'] ?? '') . ' is scheduled on ' . date('M d, Y', strtotime($date)) . '. Ref No: ' . $referenceNo . '.',
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
                    ]
                );
                set_flash('success', 'Disbursement entry created.');
            }
        }
    }
    redirect('disbursements.php');
}

if (db_ready()) {
    $sql = "SELECT a.id, u.first_name, u.last_name, a.scholarship_type
            FROM applications a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.status IN ('approved', 'for_soa_submission', 'soa_submitted')
            ORDER BY a.updated_at DESC";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $approvedApplications = $result->fetch_all(MYSQLI_ASSOC);
    }

    $sql = "SELECT d.id, d.amount, d.disbursement_date, d.reference_no, d.status, d.qr_token,
                   u.first_name, u.last_name, a.application_no, a.scholarship_type
            FROM disbursements d
            INNER JOIN applications a ON a.id = d.application_id
            INNER JOIN users u ON u.id = a.user_id
            ORDER BY d.disbursement_date DESC, d.id DESC";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $disbursements = $result->fetch_all(MYSQLI_ASSOC);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0">Disbursement Management</h1>
    <a href="verify-qr.php" class="btn btn-outline-primary btn-sm">
        <i class="fa-solid fa-qrcode me-1"></i>Open QR Scanner
    </a>
</div>

<div class="card card-soft shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">Add Disbursement Record</h2>
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_disbursement">
            <div class="col-12 col-md-4">
                <label class="form-label">Approved Application *</label>
                <select class="form-select" name="application_id" required>
                    <option value="">Select</option>
                    <?php foreach ($approvedApplications as $app): ?>
                        <option value="<?= (int) $app['id'] ?>">
                            #<?= (int) $app['id'] ?> - <?= e($app['first_name'] . ' ' . $app['last_name']) ?> (<?= e($app['scholarship_type']) ?>)
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
            <div class="col-12 col-md-4">
                <label class="form-label">Reference No. *</label>
                <input type="text" class="form-control" name="reference_no" required>
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
                <button type="submit" class="btn btn-primary">Save Record</button>
            </div>
        </form>
    </div>
</div>

<div class="card card-soft shadow-sm">
    <div class="card-body">
        <h2 class="h6">Disbursement Records</h2>
        <?php if (!$disbursements): ?>
            <p class="text-muted mb-0">No disbursement records yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Scholar</th>
                            <th>Scholarship</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>QR Token</th>
                            <th class="text-end">Update Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($disbursements as $row): ?>
                        <tr>
                            <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td>
                                <?= e($row['scholarship_type']) ?>
                                <div class="small text-muted"><?= e((string) $row['application_no']) ?></div>
                            </td>
                            <td>PHP <?= number_format((float) $row['amount'], 2) ?></td>
                            <td><?= date('M d, Y', strtotime((string) $row['disbursement_date'])) ?></td>
                            <td><?= e($row['reference_no']) ?></td>
                            <td><span class="badge <?= status_badge_class((string) $row['status']) ?>"><?= e(strtoupper((string) $row['status'])) ?></span></td>
                            <td><code><?= e($row['qr_token']) ?></code></td>
                            <td class="text-end">
                                <form method="post" class="d-flex justify-content-end gap-1" data-application-no="<?= e((string) $row['application_no']) ?>" data-reference-no="<?= e((string) $row['reference_no']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_disbursement_date">
                                    <input type="hidden" name="disbursement_id" value="<?= (int) $row['id'] ?>">
                                    <input type="date" class="form-control form-control-sm" name="disbursement_date" value="<?= e((string) $row['disbursement_date']) ?>" required>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

