<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'QR Scanner & Verification';
$scannedRaw = trim((string) ($_POST['scanned_data'] ?? ''));
$scanPurpose = 'general_verification';
$application = null;
$disbursements = [];
$applicationDocuments = [];
$scanInfo = null;
$scanStatus = 'invalid';
$hasDisbursementTime = table_column_exists($conn, 'disbursements', 'disbursement_time');
$isAjaxRequest = is_post()
    && (
        trim((string) ($_POST['ajax'] ?? '')) === '1'
        || strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest'
    );
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
$currentUser = current_user();
$scannerUserId = (int) ($currentUser['id'] ?? 0);
$scannerIsAdmin = is_admin();
$allowedStatus = application_status_options();
$bulkStatusMap = [
    '__default__' => ['under_review'],
    'draft' => ['under_review'],
    'under_review' => ['for_interview', 'rejected'],
    'needs_resubmission' => ['under_review', 'rejected'],
    'for_interview' => ['interview_passed', 'rejected'],
    'interview_passed' => ['for_soa'],
    'for_soa' => ['soa_received'],
    'soa_received' => ['awaiting_payout', 'disbursed'],
    'awaiting_payout' => ['disbursed'],
    'disbursed' => [],
    'rejected' => [],
];
$statusActionLabels = [
    'under_review' => 'Mark Under Review',
    'for_interview' => 'Move to Interview',
    'needs_resubmission' => 'Request Resubmission',
    'interview_passed' => 'Approve',
    'for_soa' => 'Move to SOA Submission',
    'soa_received' => 'SOA Submitted',
    'disbursed' => 'Mark Disbursed',
    'awaiting_payout' => 'Mark Awaiting Approval',
    'rejected' => 'Reject',
    'draft' => 'Mark Draft',
];
$quickActionsForStatus = static function (string $status) use ($bulkStatusMap, $allowedStatus, $statusActionLabels): array {
    $actions = [];
    $next = $bulkStatusMap[$status] ?? [];
    foreach ($next as $candidateStatus) {
        if (!in_array($candidateStatus, $allowedStatus, true)) {
            continue;
        }
        $actions[] = [
            'value' => $candidateStatus,
            'label' => $statusActionLabels[$candidateStatus] ?? ucwords(str_replace('_', ' ', $candidateStatus)),
        ];
    }
    return $actions;
};

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        if ($isAjaxRequest) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Invalid request token.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        set_flash('danger', 'Invalid request token.');
        redirect('verify-qr.php');
    }
}

if ($isAjaxRequest && trim((string) ($_POST['action'] ?? '')) === 'update_status_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $newStatus = trim((string) ($_POST['status'] ?? ''));
    $reviewNotes = trim((string) ($_POST['review_notes'] ?? ''));
    $soaDeadline = trim((string) ($_POST['soa_submission_deadline'] ?? ''));

    if ($applicationId <= 0 || $newStatus === '') {
        echo json_encode(['ok' => false, 'error' => 'Invalid update payload.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (!in_array($newStatus, $allowedStatus, true)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid target status.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($soaDeadline !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $soaDeadline) !== 1) {
        echo json_encode(['ok' => false, 'error' => 'Invalid SOA deadline format.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($soaDeadline !== '' && !$scannerIsAdmin) {
        echo json_encode(['ok' => false, 'error' => 'Only admin can set or extend SOA deadline.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmtCurrent = $conn->prepare(
        "SELECT id, application_no, status, soa_submission_deadline, interview_date, interview_location
         FROM applications
         WHERE id = ?
         LIMIT 1"
    );
    if (!$stmtCurrent) {
        echo json_encode(['ok' => false, 'error' => 'Unable to read application.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $stmtCurrent->bind_param('i', $applicationId);
    $stmtCurrent->execute();
    $current = $stmtCurrent->get_result()->fetch_assoc() ?: null;
    $stmtCurrent->close();
    if (!$current) {
        echo json_encode(['ok' => false, 'error' => 'Application not found.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $currentStatus = (string) ($current['status'] ?? '');
    $allowedNext = $bulkStatusMap[$currentStatus] ?? [];
    if ($newStatus !== $currentStatus && !in_array($newStatus, $allowedNext, true)) {
        echo json_encode(['ok' => false, 'error' => 'Status transition is not allowed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($newStatus === 'interview_passed') {
        $hasInterviewSchedule = trim((string) ($current['interview_date'] ?? '')) !== ''
            && trim((string) ($current['interview_location'] ?? '')) !== '';
        if ($currentStatus !== 'for_interview' || !$hasInterviewSchedule) {
            echo json_encode(['ok' => false, 'error' => 'Approve is only allowed after interview date/time and location are set.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    if ($newStatus === 'for_soa') {
        if (!$scannerIsAdmin) {
            echo json_encode(['ok' => false, 'error' => 'Only admin can move application to SOA stage.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if (!in_array($currentStatus, ['interview_passed', 'for_soa'], true)) {
            echo json_encode(['ok' => false, 'error' => 'Only interview-passed applications can be moved to SOA stage.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    if ($newStatus === 'soa_received' && !in_array($currentStatus, ['for_soa', 'soa_received'], true)) {
        echo json_encode(['ok' => false, 'error' => 'SOA can only be marked submitted after SOA request stage.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($newStatus === 'disbursed' && !in_array($currentStatus, ['soa_received', 'awaiting_payout'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Only SOA Received or Awaiting Approval applications can be marked as disbursed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $currentDeadline = trim((string) ($current['soa_submission_deadline'] ?? ''));
    $deadlineToSave = $currentDeadline;
    if ($scannerIsAdmin && $soaDeadline !== '') {
        $deadlineToSave = $soaDeadline;
    }
    if ($newStatus === 'for_soa' && $deadlineToSave === '') {
        echo json_encode(['ok' => false, 'error' => 'Set SOA deadline first before moving to SOA stage.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($newStatus === 'soa_received' && $deadlineToSave === '') {
        echo json_encode(['ok' => false, 'error' => 'Set SOA deadline first before marking SOA submitted.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $hasSoaDeadline = table_column_exists($conn, 'applications', 'soa_submission_deadline');
    if ($hasSoaDeadline) {
        $stmtUpdate = $conn->prepare(
            "UPDATE applications
             SET status = ?, review_notes = ?, soa_submission_deadline = NULLIF(?, ''), updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmtUpdate) {
            echo json_encode(['ok' => false, 'error' => 'Unable to update application.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $stmtUpdate->bind_param('sssi', $newStatus, $reviewNotes, $deadlineToSave, $applicationId);
    } else {
        $stmtUpdate = $conn->prepare(
            "UPDATE applications
             SET status = ?, review_notes = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmtUpdate) {
            echo json_encode(['ok' => false, 'error' => 'Unable to update application.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $stmtUpdate->bind_param('ssi', $newStatus, $reviewNotes, $applicationId);
    }
    $stmtUpdate->execute();
    $stmtUpdate->close();

    audit_log(
        $conn,
        'application_status_updated',
        $scannerUserId > 0 ? $scannerUserId : null,
        null,
        'application',
        (string) $applicationId,
        'Application status updated from QR modal.',
        [
            'from_status' => $currentStatus,
            'to_status' => $newStatus,
        ]
    );

    echo json_encode([
        'ok' => true,
        'status' => $newStatus,
        'status_label' => strtoupper($newStatus),
        'quick_actions' => $quickActionsForStatus($newStatus),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($scannedRaw !== '') {
    $scanInfo = extract_qr_identifiers($scannedRaw);

    $where = '';
    $value = '';
    if (!empty($scanInfo['qr_token'])) {
        $where = 'a.qr_token = ?';
        $value = (string) $scanInfo['qr_token'];
    } elseif (!empty($scanInfo['application_no'])) {
        $where = 'a.application_no = ?';
        $value = (string) $scanInfo['application_no'];
    }

    if ($where !== '') {
        $stmt = $conn->prepare(
            "SELECT a.*, u.email, u.phone
             FROM applications a
             INNER JOIN users u ON u.id = a.user_id
             WHERE {$where}
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $application = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
    }

    if ($application) {
        $scanStatus = 'matched';

        $disbursementTimeSelectSql = $hasDisbursementTime ? ', disbursement_time' : ', NULL AS disbursement_time';
        $disbursementTimeOrderSql = $hasDisbursementTime ? ", COALESCE(disbursement_time, '00:00:00') DESC" : '';
        $stmtDis = $conn->prepare(
            "SELECT id, amount, disbursement_date{$disbursementTimeSelectSql}, reference_no, payout_location, status, remarks
             FROM disbursements
             WHERE application_id = ?
             ORDER BY disbursement_date DESC{$disbursementTimeOrderSql}, id DESC"
        );
        if ($stmtDis) {
            $applicationId = (int) $application['id'];
            $stmtDis->bind_param('i', $applicationId);
            $stmtDis->execute();
            $resultDis = $stmtDis->get_result();
            $disbursements = $resultDis instanceof mysqli_result ? $resultDis->fetch_all(MYSQLI_ASSOC) : [];
            $stmtDis->close();
        }

        if (table_exists($conn, 'application_documents')) {
            $stmtDocs = $conn->prepare(
                "SELECT id, requirement_name, verification_status, file_path
                 FROM application_documents
                 WHERE application_id = ?
                 ORDER BY id ASC"
            );
            if ($stmtDocs) {
                $applicationId = (int) $application['id'];
                $stmtDocs->bind_param('i', $applicationId);
                $stmtDocs->execute();
                $resultDocs = $stmtDocs->get_result();
                $applicationDocuments = $resultDocs instanceof mysqli_result ? $resultDocs->fetch_all(MYSQLI_ASSOC) : [];
                $stmtDocs->close();
            }
        }
    } elseif ($where !== '') {
        $scanStatus = 'not_found';
    }

    if (!$application && !$isAjaxRequest) {
        set_flash('warning', 'QR/Application reference not found.');
    }

    if (is_post()) {
        audit_log(
            $conn,
            'qr_scan_verified',
            $scannerUserId > 0 ? $scannerUserId : null,
            null,
            'qr_scan',
            $application ? (string) ($application['id'] ?? '') : null,
            'QR scan verification performed.',
            [
                'status' => $scanStatus,
                'application_no' => (string) ($application['application_no'] ?? ($scanInfo['application_no'] ?? '')),
                'qr_token' => (string) ($scanInfo['qr_token'] ?? ''),
            ]
        );
    }
}

if ($isAjaxRequest) {
    header('Content-Type: application/json; charset=utf-8');
    if ($scannedRaw === '') {
        echo json_encode([
            'ok' => false,
            'error' => 'Empty scan data.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $documentsPayload = [];
    foreach ($applicationDocuments as $doc) {
        $docName = (string) ($doc['requirement_name'] ?? ('Document #' . (int) ($doc['id'] ?? 0)));
        $docStatus = (string) ($doc['verification_status'] ?? 'pending');
        $docFilePath = trim((string) ($doc['file_path'] ?? ''));
        $safeDocPath = str_replace('\\', '/', $docFilePath);
        $canViewDoc = $safeDocPath !== ''
            && !str_contains($safeDocPath, '..')
            && preg_match('/^uploads\//', $safeDocPath) === 1;
        $documentsPayload[] = [
            'name' => $docName,
            'status' => $docStatus,
            'can_view' => $canViewDoc,
            'preview_url' => $canViewDoc ? ('../preview-document.php?file=' . rawurlencode($safeDocPath)) : '',
        ];
    }

    $latestDisbursementPayload = null;
    $latestDisbursement = $disbursements[0] ?? null;
    if (is_array($latestDisbursement)) {
        $latestDisbursementPayload = [
            'schedule' => $formatPayoutSchedule(
                (string) ($latestDisbursement['disbursement_date'] ?? ''),
                $hasDisbursementTime ? (string) ($latestDisbursement['disbursement_time'] ?? '') : null
            ),
            'status' => strtoupper((string) ($latestDisbursement['status'] ?? '-')),
        ];
    }

    echo json_encode([
        'ok' => true,
        'scan_status' => $scanStatus,
        'application' => $application ? [
            'id' => (int) ($application['id'] ?? 0),
            'application_no' => (string) ($application['application_no'] ?? ''),
            'first_name' => (string) ($application['first_name'] ?? ''),
            'middle_name' => (string) ($application['middle_name'] ?? ''),
            'last_name' => (string) ($application['last_name'] ?? ''),
            'email' => (string) ($application['email'] ?? ''),
            'phone' => (string) ($application['phone'] ?? ''),
            'school_name' => (string) ($application['school_name'] ?? ''),
            'school_type' => strtoupper((string) ($application['school_type'] ?? '')),
            'status' => (string) ($application['status'] ?? ''),
            'status_label' => strtoupper((string) ($application['status'] ?? '')),
            'quick_actions' => $quickActionsForStatus((string) ($application['status'] ?? '')),
            'review_notes' => (string) ($application['review_notes'] ?? ''),
            'soa_submission_deadline' => (string) ($application['soa_submission_deadline'] ?? ''),
            'semester' => (string) ($application['semester'] ?? ''),
            'school_year' => (string) ($application['school_year'] ?? ''),
            'print_url' => '../print-application.php?id=' . (int) ($application['id'] ?? 0),
            'qr_url' => '../my-qr.php?id=' . (int) ($application['id'] ?? 0),
        ] : null,
        'documents' => $documentsPayload,
        'latest_disbursement' => $latestDisbursementPayload,
        'scan_info' => [
            'qr_token' => (string) ($scanInfo['qr_token'] ?? ''),
            'application_no' => (string) ($scanInfo['application_no'] ?? ''),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>
<style>
    .qr-ewallet-shell {
        position: relative;
        width: 100%;
        max-width: 420px;
        margin: 0 auto;
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid rgba(13, 110, 253, 0.22);
        background: radial-gradient(circle at 30% 20%, #f5f9ff 0%, #e7f0ff 42%, #dce8ff 100%);
    }

    .qr-ewallet-shell #qr-reader {
        width: 100%;
        min-height: 340px;
    }

    .qr-ewallet-shell #qr-reader video,
    .qr-ewallet-shell #qr-reader canvas {
        object-fit: cover;
    }

    .qr-ewallet-overlay {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }

    .qr-ewallet-overlay::before {
        content: '';
        position: absolute;
        width: min(72vw, 250px);
        height: min(72vw, 250px);
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        border-radius: 14px;
        box-shadow: 0 0 0 9999px rgba(7, 16, 40, 0.28);
    }

    .qr-frame {
        position: absolute;
        width: min(72vw, 250px);
        height: min(72vw, 250px);
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        border-radius: 14px;
        border: 2px solid rgba(255, 255, 255, 0.86);
    }

    .qr-frame::before {
        content: '';
        position: absolute;
        left: 8%;
        right: 8%;
        top: 50%;
        height: 2px;
        border-radius: 99px;
        background: rgba(13, 110, 253, 0.95);
        box-shadow: 0 0 12px rgba(13, 110, 253, 0.55);
        animation: qr-scan-line 1.9s ease-in-out infinite alternate;
    }

    .qr-frame-corner {
        position: absolute;
        width: 26px;
        height: 26px;
        border-color: #0d6efd;
        border-style: solid;
        border-width: 0;
    }

    .qr-frame-corner.tl {
        left: -2px;
        top: -2px;
        border-left-width: 4px;
        border-top-width: 4px;
        border-top-left-radius: 8px;
    }

    .qr-frame-corner.tr {
        right: -2px;
        top: -2px;
        border-right-width: 4px;
        border-top-width: 4px;
        border-top-right-radius: 8px;
    }

    .qr-frame-corner.bl {
        left: -2px;
        bottom: -2px;
        border-left-width: 4px;
        border-bottom-width: 4px;
        border-bottom-left-radius: 8px;
    }

    .qr-frame-corner.br {
        right: -2px;
        bottom: -2px;
        border-right-width: 4px;
        border-bottom-width: 4px;
        border-bottom-right-radius: 8px;
    }

    .qr-hint {
        position: absolute;
        left: 50%;
        bottom: 16px;
        transform: translateX(-50%);
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        color: #0b3a75;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(13, 110, 253, 0.3);
    }

    @keyframes qr-scan-line {
        from { transform: translateY(-82px); }
        to { transform: translateY(82px); }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-qrcode me-2 text-primary"></i>Scan QR</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
    </a>
</div>

<div class="card card-soft shadow-sm mx-auto" style="max-width: 560px;" id="scannerCard">
    <div class="card-body">
        <form method="post" id="verifyForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="scanned_data" id="scannedDataInput" value="">

            <div class="qr-ewallet-shell">
                <div id="qr-reader"></div>
                <div class="qr-ewallet-overlay">
                    <div class="qr-frame">
                        <span class="qr-frame-corner tl"></span>
                        <span class="qr-frame-corner tr"></span>
                        <span class="qr-frame-corner bl"></span>
                        <span class="qr-frame-corner br"></span>
                    </div>
                    <div class="qr-hint">Align QR inside frame</div>
                </div>
            </div>
            <div class="small text-muted mt-2 text-center" id="scanStatus">Starting camera...</div>
        </form>
    </div>
</div>

    <div class="modal fade" id="scanResultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 m-0" id="scanResultTitle">Scan Result</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="scanResultBody">
                    <p class="small text-muted mb-0">Waiting for scan result...</p>
                </div>
                <div class="modal-footer">
                    <a href="#" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm d-none" id="scanResultPrintBtn">
                        <i class="fa-solid fa-print me-1"></i>Print Form
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" id="scanResultCloseBtn">Scan Again</button>
                </div>
            </div>
        </div>
    </div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    (function () {
        const statusElement = document.getElementById('scanStatus');
        const formElement = document.getElementById('verifyForm');
        const scannerCard = document.getElementById('scannerCard');
        const csrfInput = formElement ? formElement.querySelector('input[name="csrf_token"]') : null;
        const modalEl = document.getElementById('scanResultModal');
        const modalTitleEl = document.getElementById('scanResultTitle');
        const modalBodyEl = document.getElementById('scanResultBody');
        const printBtn = document.getElementById('scanResultPrintBtn');
        const closeBtn = document.getElementById('scanResultCloseBtn');
        const bootstrapApi = (typeof window !== 'undefined' && window.bootstrap && window.bootstrap.Modal)
            ? window.bootstrap
            : null;
        const scanResultModal = (modalEl && bootstrapApi)
            ? new bootstrapApi.Modal(modalEl)
            : null;
        let fallbackBackdrop = null;
        let isSubmitted = false;
        let scanner = null;
        let scannerStarting = false;
        let scannerRunning = false;

        function updateButtonState() {
            // Scanner is automatic; no manual controls.
        }

        function onScanFailure() {
            // Keep scanning continuously.
        }

        function getPreferredCamera(cameras) {
            if (!Array.isArray(cameras) || cameras.length === 0) {
                return null;
            }
            const preferred = cameras.find(function (camera) {
                const label = String(camera.label || '').toLowerCase();
                return label.includes('back') || label.includes('rear') || label.includes('environment');
            });
            return preferred || cameras[0];
        }

        function startScanner() {
            if (scannerRunning || scannerStarting || isSubmitted) {
                return;
            }
            if (typeof Html5QrcodeScanner === 'undefined') {
                if (statusElement) {
                    statusElement.textContent = 'Scanner library failed to load.';
                }
                return;
            }

            if (typeof Html5Qrcode === 'undefined') {
                if (statusElement) {
                    statusElement.textContent = 'Scanner library failed to load.';
                }
                return;
            }

            scannerStarting = true;
            updateButtonState();
            if (statusElement) {
                statusElement.textContent = 'Starting camera...';
            }

            scanner = new Html5Qrcode('qr-reader');
            Html5Qrcode.getCameras()
                .then(function (cameras) {
                    const selectedCamera = getPreferredCamera(cameras);
                    if (!selectedCamera) {
                        throw new Error('No available camera.');
                    }
                    return scanner.start(
                        selectedCamera.id,
                        { fps: 10, qrbox: { width: 260, height: 260 }, aspectRatio: 1 },
                        onScanSuccess,
                        onScanFailure
                    );
                })
                .then(function () {
                    scannerRunning = true;
                    if (statusElement) {
                        statusElement.textContent = 'Scanner is running. Point camera to the QR.';
                    }
                })
                .catch(function () {
                    if (statusElement) {
                        statusElement.textContent = 'Unable to start camera. Check camera permissions.';
                    }
                    scanner = null;
                })
                .finally(function () {
                    scannerStarting = false;
                    updateButtonState();
                });
        }

        function stopScanner(forceStop) {
            if (!scanner || !scannerRunning || (isSubmitted && !forceStop)) {
                return;
            }
            Promise.resolve(scanner.stop())
                .catch(function () {
                    // Ignore scanner stop errors.
                })
                .finally(function () {
                    Promise.resolve(scanner.clear()).catch(function () {
                        // Ignore scanner clear errors.
                    });
                    scanner = null;
                    scannerRunning = false;
                    if (statusElement) {
                        statusElement.textContent = 'Scanner stopped.';
                    }
                    updateButtonState();
                });
        }

        function statusBadgeClass(status) {
            const value = String(status || '').toLowerCase();
            if (value === 'under_review' || value === 'needs_resubmission' || value === 'for_interview') {
                return 'text-bg-warning';
            }
            if (value === 'interview_passed' || value === 'for_soa' || value === 'soa_received' || value === 'disbursed') {
                return 'text-bg-success';
            }
            if (value === 'rejected') {
                return 'text-bg-danger';
            }
            return 'text-bg-secondary';
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderResultModal(data) {
            if (!modalBodyEl || !modalTitleEl) {
                return;
            }
            const app = data && data.application ? data.application : null;
            if (app) {
                modalTitleEl.textContent = 'Application ' + String(app.application_no || '');
                const fullName = [app.last_name, app.first_name, app.middle_name].filter(Boolean).join(', ').replace(', ,', ',');
                const docs = Array.isArray(data.documents) ? data.documents : [];
                const docsHtml = docs.length === 0
                    ? '<div class="alert alert-warning py-2 mb-0 small">No uploaded documents found for this application.</div>'
                    : '<div class="border rounded p-2">' + docs.map(function (doc) {
                        const canView = !!doc.can_view && String(doc.preview_url || '') !== '';
                        const actionHtml = canView
                            ? '<a href="' + escapeHtml(String(doc.preview_url)) + '" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary"><i class="fa-regular fa-file-lines me-1"></i>View File</a>'
                            : '<span class="small text-muted">No file</span>';
                        return '<div class="d-flex align-items-center justify-content-between gap-2 mb-1">'
                            + '<div><div>' + escapeHtml(String(doc.name || '-')) + '</div><div class="small text-muted">Status: ' + escapeHtml(String(doc.status || 'pending').toUpperCase()) + '</div></div>'
                            + actionHtml
                            + '</div>';
                    }).join('') + '</div>';

                let payoutHtml = '';
                if (data.latest_disbursement && data.latest_disbursement.schedule) {
                    payoutHtml = '<div class="small text-muted">Latest Payout</div>'
                        + '<div class="mb-1">' + escapeHtml(String(data.latest_disbursement.schedule)) + '</div>'
                        + '<div class="small text-muted mb-0">Payout Status: ' + escapeHtml(String(data.latest_disbursement.status || '-')) + '</div>';
                }

                modalBodyEl.innerHTML =
                    '<div class="row g-2 mb-3">'
                    + '<div class="col-12 col-md-6"><div class="small text-muted">Applicant</div><div>' + escapeHtml(String(fullName || '-')) + '</div></div>'
                    + '<div class="col-12 col-md-6"><div class="small text-muted">School</div><div>' + escapeHtml(String(app.school_name || '-')) + ' (' + escapeHtml(String(app.school_type || '-')) + ')</div></div>'
                    + '<div class="col-12 col-md-6"><div class="small text-muted">Contact</div><div>' + escapeHtml(String(app.email || '-')) + ' | ' + escapeHtml(String(app.phone || '-')) + '</div></div>'
                    + '<div class="col-12 col-md-6"><div class="small text-muted">Current Status</div><span class="badge ' + statusBadgeClass(app.status) + '" id="scanCurrentStatusBadge">' + escapeHtml(String(app.status_label || '').toUpperCase()) + '</span></div>'
                    + '<div class="col-12 col-md-6"><div class="small text-muted">Semester / School Year</div><div>' + escapeHtml(String(app.semester || '-')) + ' / ' + escapeHtml(String(app.school_year || '-')) + '</div></div>'
                    + '</div>'
                    + '<div class="mb-3"><label class="form-label form-label-sm mb-1">Uploaded Documents</label>' + docsHtml + '</div>'
                    + payoutHtml
                    + '<div class="mt-3">'
                    + '<label class="form-label form-label-sm">Quick Actions</label>'
                    + '<div class="d-flex flex-wrap gap-2 mb-2" id="scanQuickActions"></div>'
                    + '<div class="row g-2">'
                    + '<div class="col-12 col-md-8"><label class="form-label form-label-sm">Review Notes</label><input type="text" class="form-control form-control-sm" id="scanReviewNotesInput" value="' + escapeHtml(String(app.review_notes || '')) + '" placeholder="Optional review note"></div>'
                    + '<div class="col-12 col-md-4"><label class="form-label form-label-sm">SOA Deadline</label><input type="date" class="form-control form-control-sm" id="scanSoaDeadlineInput" value="' + escapeHtml(String(app.soa_submission_deadline || '')) + '"></div>'
                    + '<div class="col-12 d-flex justify-content-between flex-wrap gap-2 mt-2">'
                    + '<button type="button" class="btn btn-sm btn-outline-secondary" id="scanPrintInlineBtn"><i class="fa-solid fa-print me-1"></i>Print Form</button>'
                    + '<button type="button" class="btn btn-sm btn-primary" id="scanSaveNotesBtn"><i class="fa-solid fa-floppy-disk me-1"></i>Save Notes / Deadline</button>'
                    + '</div>'
                    + '</div>'
                    + '</div>';

                const quickActionsWrap = modalBodyEl.querySelector('#scanQuickActions');
                const reviewNotesInput = modalBodyEl.querySelector('#scanReviewNotesInput');
                const soaDeadlineInput = modalBodyEl.querySelector('#scanSoaDeadlineInput');
                const saveNotesBtn = modalBodyEl.querySelector('#scanSaveNotesBtn');
                const printInlineBtn = modalBodyEl.querySelector('#scanPrintInlineBtn');
                const currentStatusBadge = modalBodyEl.querySelector('#scanCurrentStatusBadge');

                function applyQuickActions(actions) {
                    if (!quickActionsWrap) {
                        return;
                    }
                    quickActionsWrap.dataset.currentStatus = String(app.status || '');
                    const list = Array.isArray(actions) ? actions : [];
                    if (list.length === 0) {
                        quickActionsWrap.innerHTML = '<span class="small text-muted">No status transitions available for this current state.</span>';
                        return;
                    }
                    quickActionsWrap.innerHTML = list.map(function (action) {
                        return '<button type="button" class="btn btn-sm btn-outline-primary js-scan-status-action" data-next-status="' + escapeHtml(String(action.value || '')) + '">' + escapeHtml(String(action.label || 'Update')) + '</button>';
                    }).join('');
                    quickActionsWrap.querySelectorAll('.js-scan-status-action').forEach(function (button) {
                        button.addEventListener('click', function () {
                            const nextStatus = String(button.getAttribute('data-next-status') || '');
                            if (nextStatus) {
                                updateStatusFromScanModal(app.id, nextStatus, reviewNotesInput, soaDeadlineInput, quickActionsWrap, currentStatusBadge);
                            }
                        });
                    });
                }
                applyQuickActions(app.quick_actions || []);

                if (saveNotesBtn) {
                    saveNotesBtn.addEventListener('click', function () {
                        const currentStatus = quickActionsWrap ? String(quickActionsWrap.dataset.currentStatus || app.status || '') : String(app.status || '');
                        updateStatusFromScanModal(app.id, currentStatus, reviewNotesInput, soaDeadlineInput, quickActionsWrap, currentStatusBadge, true);
                    });
                }
                if (printInlineBtn) {
                    printInlineBtn.addEventListener('click', function () {
                        const url = String(app.print_url || '#');
                        if (url && url !== '#') {
                            window.open(url, '_blank', 'noopener');
                        }
                    });
                }

                if (printBtn) {
                    printBtn.classList.add('d-none');
                    printBtn.href = '#';
                }
            } else {
                modalTitleEl.textContent = 'No Match Found';
                const info = data && data.scan_info ? data.scan_info : {};
                modalBodyEl.innerHTML =
                    '<p class="small text-muted mb-1">No applicant/application was found for this QR content.</p>'
                    + '<div class="small mb-1"><strong>Detected Token:</strong> ' + escapeHtml(String(info.qr_token || '-')) + '</div>'
                    + '<div class="small"><strong>Detected App No:</strong> ' + escapeHtml(String(info.application_no || '-')) + '</div>';
                if (printBtn) {
                    printBtn.classList.add('d-none');
                    printBtn.href = '#';
                }
            }
        }

        function updateStatusFromScanModal(appId, nextStatus, reviewNotesInput, soaDeadlineInput, quickActionsWrap, currentStatusBadge, isNoteSaveOnly) {
            const csrf = csrfInput ? String(csrfInput.value || '') : '';
            const payload = new URLSearchParams();
            payload.set('ajax', '1');
            payload.set('action', 'update_status_ajax');
            payload.set('csrf_token', csrf);
            payload.set('application_id', String(appId || '0'));
            payload.set('status', String(nextStatus || ''));
            payload.set('review_notes', reviewNotesInput ? String(reviewNotesInput.value || '') : '');
            payload.set('soa_submission_deadline', soaDeadlineInput ? String(soaDeadlineInput.value || '') : '');

            fetch('verify-qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { ok: false, error: 'Invalid server response.' };
                    });
                })
                .then(function (data) {
                    if (!data || data.ok !== true) {
                        throw new Error(data && data.error ? data.error : 'Unable to update status.');
                    }
                    if (currentStatusBadge) {
                        currentStatusBadge.className = 'badge ' + statusBadgeClass(data.status || '');
                        currentStatusBadge.textContent = String(data.status_label || '').toUpperCase();
                    }
                    if (quickActionsWrap) {
                        quickActionsWrap.dataset.currentStatus = String(data.status || '');
                        const actions = Array.isArray(data.quick_actions) ? data.quick_actions : [];
                        if (actions.length === 0) {
                            quickActionsWrap.innerHTML = '<span class="small text-muted">No status transitions available for this current state.</span>';
                        } else {
                            quickActionsWrap.innerHTML = actions.map(function (action) {
                                return '<button type="button" class="btn btn-sm btn-outline-primary js-scan-status-action" data-next-status="' + escapeHtml(String(action.value || '')) + '">' + escapeHtml(String(action.label || 'Update')) + '</button>';
                            }).join('');
                            quickActionsWrap.querySelectorAll('.js-scan-status-action').forEach(function (button) {
                                button.addEventListener('click', function () {
                                    const next = String(button.getAttribute('data-next-status') || '');
                                    if (next) {
                                        updateStatusFromScanModal(appId, next, reviewNotesInput, soaDeadlineInput, quickActionsWrap, currentStatusBadge);
                                    }
                                });
                            });
                        }
                    }
                    if (statusElement) {
                        statusElement.textContent = isNoteSaveOnly
                            ? 'Notes saved.'
                            : 'Status updated successfully.';
                    }
                })
                .catch(function (error) {
                    if (statusElement) {
                        statusElement.textContent = error && error.message
                            ? error.message
                            : 'Status update failed.';
                    }
                });
        }

        function openResultModal() {
            if (!modalEl) {
                return;
            }
            if (scanResultModal) {
                scanResultModal.show();
                return;
            }
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            modalEl.removeAttribute('aria-hidden');
            modalEl.setAttribute('aria-modal', 'true');
            if (!fallbackBackdrop) {
                fallbackBackdrop = document.createElement('div');
                fallbackBackdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(fallbackBackdrop);
            }
            document.body.classList.add('modal-open');
        }

        function closeResultModal() {
            if (!modalEl) {
                return;
            }
            if (scanResultModal) {
                scanResultModal.hide();
                return;
            }
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.removeAttribute('aria-modal');
            if (fallbackBackdrop && fallbackBackdrop.parentNode) {
                fallbackBackdrop.parentNode.removeChild(fallbackBackdrop);
            }
            fallbackBackdrop = null;
            document.body.classList.remove('modal-open');
            if (scannerCard) {
                scannerCard.classList.remove('d-none');
            }
            isSubmitted = false;
            if (statusElement) {
                statusElement.textContent = 'Ready. Point camera to the QR.';
            }
            if (!scannerRunning) {
                startScanner();
            }
        }

        function verifyScan(decodedText) {
            const csrf = csrfInput ? String(csrfInput.value || '') : '';
            const payload = new URLSearchParams();
            payload.set('ajax', '1');
            payload.set('csrf_token', csrf);
            payload.set('scanned_data', String(decodedText || ''));

            fetch('verify-qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { ok: false, error: 'Invalid server response.' };
                    });
                })
                .then(function (data) {
                    if (!data || data.ok !== true) {
                        throw new Error(data && data.error ? data.error : 'Verification failed.');
                    }
                    renderResultModal(data);
                    if (data.application) {
                        stopScanner(true);
                        if (scannerCard) {
                            scannerCard.classList.add('d-none');
                        }
                    } else {
                        stopScanner(true);
                    }
                    if (statusElement) {
                        statusElement.textContent = data.application
                            ? 'Matched application found.'
                            : 'No match found.';
                    }
                    openResultModal();
                })
                .catch(function (error) {
                    if (statusElement) {
                        statusElement.textContent = error && error.message
                            ? error.message
                            : 'Verification failed. Please try again.';
                    }
                    isSubmitted = false;
                    if (!scannerRunning) {
                        startScanner();
                    }
                });
        }

        function onScanSuccess(decodedText) {
            if (isSubmitted) {
                return;
            }
            isSubmitted = true;
            if (statusElement) {
                statusElement.textContent = 'Scan detected. Verifying...';
            }
            verifyScan(decodedText);
            updateButtonState();
        }

        updateButtonState();
        startScanner();
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                closeResultModal();
            });
        }
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                if (scannerCard) {
                    scannerCard.classList.remove('d-none');
                }
                isSubmitted = false;
                if (statusElement) {
                    statusElement.textContent = 'Ready. Point camera to the QR.';
                }
                if (!scannerRunning) {
                    startScanner();
                }
            });
            modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!scanResultModal) {
                        closeResultModal();
                    }
                });
            });
        }
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
