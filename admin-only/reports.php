<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_admin('../index.php');

$pageTitle = 'Reports';

$defaultFromDate = date('Y-m-d', strtotime('-30 days'));
$defaultToDate = date('Y-m-d');
$fromDate = trim((string) ($_GET['from_date'] ?? $defaultFromDate));
$toDate = trim((string) ($_GET['to_date'] ?? $defaultToDate));

$isValidDate = static function (string $value): bool {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
};
if (!$isValidDate($fromDate)) {
    $fromDate = $defaultFromDate;
}
if (!$isValidDate($toDate)) {
    $toDate = $defaultToDate;
}
if ($fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime = $toDate . ' 23:59:59';

$summary = [
    'applications_total' => 0,
    'applications_approved' => 0,
    'disbursement_records' => 0,
    'disbursement_amount' => 0.0,
    'sms_total' => 0,
    'sms_success' => 0,
    'qr_total' => 0,
];

$statusSummary = [];
$scholarshipSummary = [];
$semesterDisbursements = [];
$smsDeliverySummary = [];
$qrPurposeSummary = [];

$hasSmsLogs = db_ready() && table_exists($conn, 'sms_logs');
$hasQrLogs = db_ready() && table_exists($conn, 'qr_scan_logs');

if (db_ready()) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM applications
         WHERE COALESCE(submitted_at, created_at) BETWEEN ? AND ?"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDateTime, $toDateTime);
        $stmt->execute();
        $summary['applications_total'] = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM applications
         WHERE COALESCE(submitted_at, created_at) BETWEEN ? AND ?
           AND status IN ('approved', 'for_soa_submission', 'soa_submitted')"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDateTime, $toDateTime);
        $stmt->execute();
        $summary['applications_approved'] = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total_records, COALESCE(SUM(amount), 0) AS total_amount
         FROM disbursements
         WHERE disbursement_date BETWEEN ? AND ?"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDate, $toDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $summary['disbursement_records'] = (int) ($row['total_records'] ?? 0);
        $summary['disbursement_amount'] = (float) ($row['total_amount'] ?? 0);
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT status, COUNT(*) AS total
         FROM applications
         WHERE COALESCE(submitted_at, created_at) BETWEEN ? AND ?
         GROUP BY status
         ORDER BY total DESC, status ASC"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDateTime, $toDateTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $statusSummary = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT scholarship_type, COUNT(*) AS total
         FROM applications
         WHERE COALESCE(submitted_at, created_at) BETWEEN ? AND ?
         GROUP BY scholarship_type
         ORDER BY total DESC, scholarship_type ASC"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDateTime, $toDateTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $scholarshipSummary = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT
            CASE
                WHEN a.semester IN ('First Semester', 'Second Semester')
                     AND TRIM(COALESCE(a.school_year, '')) <> ''
                THEN CONCAT(
                    CASE a.semester
                        WHEN 'First Semester' THEN '1st Sem'
                        WHEN 'Second Semester' THEN '2nd Sem'
                        ELSE a.semester
                    END,
                    ' ',
                    a.school_year
                )
                ELSE 'Unspecified'
            END AS semester_label,
            COUNT(d.id) AS total_records,
            COALESCE(SUM(d.amount), 0) AS total_amount,
            MAX(
                CASE
                    WHEN a.school_year REGEXP '^[0-9]{4}-[0-9]{4}$'
                    THEN CAST(SUBSTRING_INDEX(a.school_year, '-', 1) AS UNSIGNED)
                    ELSE 0
                END
            ) AS sort_year,
            MAX(
                CASE a.semester
                    WHEN 'Second Semester' THEN 2
                    WHEN 'First Semester' THEN 1
                    ELSE 0
                END
            ) AS sort_semester
         FROM disbursements d
         LEFT JOIN applications a ON a.id = d.application_id
         WHERE d.disbursement_date BETWEEN ? AND ?
         GROUP BY semester_label
         ORDER BY sort_year DESC, sort_semester DESC, semester_label ASC"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $fromDate, $toDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $semesterDisbursements = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    if ($hasSmsLogs) {
        $stmt = $conn->prepare(
            "SELECT delivery_status, COUNT(*) AS total
             FROM sms_logs
             WHERE created_at BETWEEN ? AND ?
             GROUP BY delivery_status
             ORDER BY delivery_status ASC"
        );
        if ($stmt) {
            $stmt->bind_param('ss', $fromDateTime, $toDateTime);
            $stmt->execute();
            $result = $stmt->get_result();
            $smsDeliverySummary = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }
        foreach ($smsDeliverySummary as $row) {
            $status = (string) ($row['delivery_status'] ?? '');
            $total = (int) ($row['total'] ?? 0);
            $summary['sms_total'] += $total;
            if ($status === 'success') {
                $summary['sms_success'] = $total;
            }
        }
    }

    if ($hasQrLogs) {
        $stmt = $conn->prepare(
            "SELECT purpose, COUNT(*) AS total
             FROM qr_scan_logs
             WHERE created_at BETWEEN ? AND ?
             GROUP BY purpose
             ORDER BY total DESC, purpose ASC"
        );
        if ($stmt) {
            $stmt->bind_param('ss', $fromDateTime, $toDateTime);
            $stmt->execute();
            $result = $stmt->get_result();
            $qrPurposeSummary = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }
        foreach ($qrPurposeSummary as $row) {
            $summary['qr_total'] += (int) ($row['total'] ?? 0);
        }
    }
}

$approvalRate = $summary['applications_total'] > 0
    ? ($summary['applications_approved'] / $summary['applications_total']) * 100
    : 0.0;
$smsSuccessRate = $summary['sms_total'] > 0
    ? ($summary['sms_success'] / $summary['sms_total']) * 100
    : 0.0;

$baseQuery = http_build_query([
    'from_date' => $fromDate,
    'to_date' => $toDate,
]);

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>System Reports</h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="export-reports.php?dataset=approved_scholars&format=xlsx&<?= e($baseQuery) ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-excel me-1"></i>Approved Scholars XLSX</a>
        <a href="../shared/analytics.php?<?= e($baseQuery) ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-chart-pie me-1"></i>Analytics</a>
        <a href="logs.php?<?= e($baseQuery) ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-clipboard-list me-1"></i>Logs</a>
    </div>
</div>

<form method="get" class="card card-soft shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label form-label-sm">From Date</label>
            <input type="date" class="form-control form-control-sm" name="from_date" value="<?= e($fromDate) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label form-label-sm">To Date</label>
            <input type="date" class="form-control form-control-sm" name="to_date" value="<?= e($toDate) ?>">
        </div>
        <div class="col-12 col-md-6 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Apply Range</button>
            <a href="reports.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </div>
</form>

<?php if (!db_ready()): ?>
    <div class="card card-soft shadow-sm"><div class="card-body text-muted">The system is not ready yet. Please contact the administrator.</div></div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card card-soft metric-card"><div class="card-body"><p class="small text-muted mb-1">Applications</p><h3><?= number_format($summary['applications_total']) ?></h3></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-soft metric-card"><div class="card-body"><p class="small text-muted mb-1">Approved</p><h3><?= number_format($summary['applications_approved']) ?></h3><div class="small text-muted"><?= number_format($approvalRate, 1) ?>%</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-soft metric-card"><div class="card-body"><p class="small text-muted mb-1">Disbursements</p><h3>PHP <?= number_format($summary['disbursement_amount'], 2) ?></h3></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-soft metric-card"><div class="card-body"><p class="small text-muted mb-1">QR / SMS</p><h3><?= number_format($summary['qr_total']) ?> / <?= number_format($summary['sms_total']) ?></h3><div class="small text-muted">SMS success <?= number_format($smsSuccessRate, 1) ?>%</div></div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h2 class="h6 m-0">Application Status Summary</h2>
                        <div class="btn-group btn-group-sm">
                            <a href="export-reports.php?dataset=status_summary&format=pdf&<?= e($baseQuery) ?>" class="btn btn-outline-primary">PDF</a>
                            <a href="export-reports.php?dataset=status_summary&format=docx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">DOCX</a>
                            <a href="export-reports.php?dataset=status_summary&format=xlsx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">XLSX</a>
                        </div>
                    </div>
                    <?php if (!$statusSummary): ?>
                        <p class="text-muted mb-0">No data for selected range.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Status</th><th class="text-end">Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($statusSummary as $row): ?>
                                        <tr>
                                            <td><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></td>
                                            <td class="text-end"><?= (int) $row['total'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h2 class="h6 m-0">Scholarship Type Summary</h2>
                        <div class="btn-group btn-group-sm">
                            <a href="export-reports.php?dataset=scholarship_summary&format=pdf&<?= e($baseQuery) ?>" class="btn btn-outline-primary">PDF</a>
                            <a href="export-reports.php?dataset=scholarship_summary&format=docx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">DOCX</a>
                            <a href="export-reports.php?dataset=scholarship_summary&format=xlsx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">XLSX</a>
                        </div>
                    </div>
                    <?php if (!$scholarshipSummary): ?>
                        <p class="text-muted mb-0">No data for selected range.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Scholarship Type</th><th class="text-end">Applications</th></tr></thead>
                                <tbody>
                                    <?php foreach ($scholarshipSummary as $row): ?>
                                        <tr>
                                            <td><?= e((string) ($row['scholarship_type'] ?? '-')) ?></td>
                                            <td class="text-end"><?= (int) ($row['total'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card card-soft shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h2 class="h6 m-0">Semester Disbursement Summary</h2>
                        <div class="btn-group btn-group-sm">
                            <a href="export-reports.php?dataset=monthly_disbursements&format=pdf&<?= e($baseQuery) ?>" class="btn btn-outline-primary">PDF</a>
                            <a href="export-reports.php?dataset=monthly_disbursements&format=docx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">DOCX</a>
                            <a href="export-reports.php?dataset=monthly_disbursements&format=xlsx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">XLSX</a>
                        </div>
                    </div>
                    <?php if (!$semesterDisbursements): ?>
                        <p class="text-muted mb-0">No disbursement records in selected range.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Semester</th><th class="text-end">Records</th><th class="text-end">Total Amount</th></tr></thead>
                                <tbody>
                                    <?php foreach ($semesterDisbursements as $row): ?>
                                        <tr>
                                            <td><?= e((string) ($row['semester_label'] ?? 'Unspecified')) ?></td>
                                            <td class="text-end"><?= (int) ($row['total_records'] ?? 0) ?></td>
                                            <td class="text-end">PHP <?= number_format((float) ($row['total_amount'] ?? 0), 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($hasSmsLogs): ?>
            <div class="col-12 col-lg-6">
                <div class="card card-soft shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <h2 class="h6 m-0">SMS Delivery Summary</h2>
                            <div class="btn-group btn-group-sm">
                                <a href="export-reports.php?dataset=sms_delivery_summary&format=pdf&<?= e($baseQuery) ?>" class="btn btn-outline-primary">PDF</a>
                                <a href="export-reports.php?dataset=sms_delivery_summary&format=docx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">DOCX</a>
                                <a href="export-reports.php?dataset=sms_delivery_summary&format=xlsx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">XLSX</a>
                            </div>
                        </div>
                        <?php if (!$smsDeliverySummary): ?>
                            <p class="text-muted mb-0">No SMS logs in selected range.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Status</th><th class="text-end">Total</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($smsDeliverySummary as $row): ?>
                                            <tr>
                                                <td><?= e(strtoupper((string) ($row['delivery_status'] ?? 'UNKNOWN'))) ?></td>
                                                <td class="text-end"><?= (int) ($row['total'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasQrLogs): ?>
            <div class="col-12 col-lg-6">
                <div class="card card-soft shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <h2 class="h6 m-0">QR Scan Purpose Summary</h2>
                            <div class="btn-group btn-group-sm">
                                <a href="export-reports.php?dataset=qr_scan_summary&format=pdf&<?= e($baseQuery) ?>" class="btn btn-outline-primary">PDF</a>
                                <a href="export-reports.php?dataset=qr_scan_summary&format=docx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">DOCX</a>
                                <a href="export-reports.php?dataset=qr_scan_summary&format=xlsx&<?= e($baseQuery) ?>" class="btn btn-outline-primary">XLSX</a>
                            </div>
                        </div>
                        <?php if (!$qrPurposeSummary): ?>
                            <p class="text-muted mb-0">No QR scan logs in selected range.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Purpose</th><th class="text-end">Total</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($qrPurposeSummary as $row): ?>
                                            <tr>
                                                <td><?= e(qr_scan_purpose_label((string) ($row['purpose'] ?? ''))) ?></td>
                                                <td class="text-end"><?= (int) ($row['total'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
