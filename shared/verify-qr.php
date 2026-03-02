<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'QR Scanner & Verification';
$scannedRaw = trim((string) ($_POST['scanned_data'] ?? ''));
$scanPurposeInput = trim((string) ($_POST['scan_purpose'] ?? ''));
$scanPurpose = normalize_qr_scan_purpose($scanPurposeInput !== '' ? $scanPurposeInput : 'general_verification');
$scanNotes = trim((string) ($_POST['scan_notes'] ?? ''));
$application = null;
$disbursements = [];
$scanInfo = null;
$scanStatus = 'invalid';
$applicationScanLogs = [];
$recentScanLogs = [];
$hasQrScanLogsTable = table_exists($conn, 'qr_scan_logs');
$hasDisbursementTime = table_column_exists($conn, 'disbursements', 'disbursement_time');
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

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('verify-qr.php');
    }
    if ($scannedRaw !== '' && $scanPurposeInput === '') {
        set_flash('danger', 'Please select scan purpose before scanning.');
        redirect('verify-qr.php');
    }
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

        if ($hasQrScanLogsTable) {
            $stmtAppLogs = $conn->prepare(
                "SELECT l.id, l.purpose, l.scan_status, l.notes, l.created_at,
                        l.scanned_qr_token, l.scanned_application_no,
                        su.first_name AS scanner_first_name, su.last_name AS scanner_last_name
                 FROM qr_scan_logs l
                 LEFT JOIN users su ON su.id = l.scanned_by_user_id
                 WHERE l.application_id = ?
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT 10"
            );
            if ($stmtAppLogs) {
                $applicationId = (int) $application['id'];
                $stmtAppLogs->bind_param('i', $applicationId);
                $stmtAppLogs->execute();
                $resultAppLogs = $stmtAppLogs->get_result();
                $applicationScanLogs = $resultAppLogs instanceof mysqli_result ? $resultAppLogs->fetch_all(MYSQLI_ASSOC) : [];
                $stmtAppLogs->close();
            }
        }
    } elseif ($where !== '') {
        $scanStatus = 'not_found';
    }

    if (!$application) {
        set_flash('warning', 'QR/Application reference not found.');
    }

    if (is_post()) {
        if (!$hasQrScanLogsTable) {
            set_flash('warning', 'Scan history is not available yet.');
        } elseif ($scannerUserId <= 0) {
            set_flash('warning', 'Current scanner account is invalid. Scan history was not saved.');
        } else {
            $applicationId = $application ? (int) $application['id'] : 0;
            $applicantUserId = $application ? (int) $application['user_id'] : 0;
            $detectedQrToken = trim((string) ($scanInfo['qr_token'] ?? ''));
            $detectedApplicationNo = trim((string) ($scanInfo['application_no'] ?? ''));
            $notesForLog = $scanNotes;
            if ($notesForLog !== '') {
                $notesForLog = function_exists('mb_substr')
                    ? mb_substr($notesForLog, 0, 255)
                    : substr($notesForLog, 0, 255);
            }

            $stmtLog = $conn->prepare(
                "INSERT INTO qr_scan_logs
                 (scanned_by_user_id, application_id, applicant_user_id, purpose, scan_status, scanned_qr_token, scanned_application_no, raw_content, notes)
                 VALUES (?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, ''))"
            );

            if ($stmtLog) {
                $stmtLog->bind_param(
                    'iiissssss',
                    $scannerUserId,
                    $applicationId,
                    $applicantUserId,
                    $scanPurpose,
                    $scanStatus,
                    $detectedQrToken,
                    $detectedApplicationNo,
                    $scannedRaw,
                    $notesForLog
                );
                $stmtLog->execute();
                $stmtLog->close();
            } else {
                set_flash('danger', 'Scan history could not be saved right now. Please try again.');
            }
        }

        audit_log(
            $conn,
            'qr_scan_verified',
            $scannerUserId > 0 ? $scannerUserId : null,
            null,
            'qr_scan',
            $application ? (string) ($application['id'] ?? '') : null,
            'QR scan verification performed.',
            [
                'purpose' => $scanPurpose,
                'status' => $scanStatus,
                'application_no' => (string) ($application['application_no'] ?? ($scanInfo['application_no'] ?? '')),
                'qr_token' => (string) ($scanInfo['qr_token'] ?? ''),
            ]
        );
    }
}

if ($hasQrScanLogsTable) {
    $resultRecentLogs = $conn->query(
        "SELECT l.id, l.purpose, l.scan_status, l.notes, l.created_at, l.scanned_application_no,
                a.application_no, a.first_name, a.last_name,
                su.first_name AS scanner_first_name, su.last_name AS scanner_last_name
         FROM qr_scan_logs l
         LEFT JOIN applications a ON a.id = l.application_id
         LEFT JOIN users su ON su.id = l.scanned_by_user_id
         ORDER BY l.created_at DESC, l.id DESC
         LIMIT 12"
    );
    if ($resultRecentLogs instanceof mysqli_result) {
        $recentScanLogs = $resultRecentLogs->fetch_all(MYSQLI_ASSOC);
        $resultRecentLogs->free();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-qrcode me-2 text-primary"></i>QR Scanner & Verification</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card card-soft shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-2">Camera Scanner</h2>
                <p class="small text-muted mb-3">Select scan purpose first, then click Start Scanner.</p>
                <div id="qr-reader" style="width:100%;max-width:420px;"></div>
                <div class="small text-muted mt-2" id="scanStatus">Scanner is off. Select purpose then click Start Scanner.</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card card-soft shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-2">Scan Options</h2>
                <p class="small text-muted mb-3">Manual QR input is disabled. Use camera scanner only.</p>
                <form method="post" id="verifyForm">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="scanned_data" id="scannedDataInput" value="">

                    <label for="scanPurposeInput" class="form-label small mb-1">Scan Purpose</label>
                    <select name="scan_purpose" id="scanPurposeInput" class="form-select form-select-sm mb-2" required>
                        <option value="" <?= $scanPurposeInput === '' ? 'selected' : '' ?>>Select scan purpose</option>
                        <?php foreach (qr_scan_purpose_options() as $purposeValue => $purposeLabel): ?>
                            <option value="<?= e($purposeValue) ?>" <?= $scanPurposeInput === $purposeValue ? 'selected' : '' ?>>
                                <?= e($purposeLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="scanNotesInput" class="form-label small mb-1">Notes (Optional)</label>
                    <textarea name="scan_notes" id="scanNotesInput" class="form-control mb-2" rows="2" placeholder="Example: arrived for interview, document checked, etc."><?= e($scanNotes) ?></textarea>

                    <div class="small text-muted mb-2">
                        Scanner starts only after you click Start Scanner. Verification runs automatically after successful scan.
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-sm" id="startScannerBtn">
                            <i class="fa-solid fa-camera me-1"></i>Start Scanner
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="stopScannerBtn" disabled>
                            <i class="fa-solid fa-stop me-1"></i>Stop Scanner
                        </button>
                        <a href="verify-qr.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($application): ?>
    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h2 class="h5 mb-1"><?= e((string) $application['application_no']) ?></h2>
                    <div class="small text-muted"><?= e((string) $application['last_name']) ?>, <?= e((string) $application['first_name']) ?> <?= e((string) ($application['middle_name'] ?? '')) ?></div>
                    <div class="small text-muted"><?= e((string) $application['email']) ?> | <?= e((string) $application['phone']) ?></div>
                </div>
                <span class="badge <?= status_badge_class((string) $application['status']) ?>">
                    <?= e(strtoupper((string) $application['status'])) ?>
                </span>
            </div>

            <div class="row mt-3">
                <div class="col-12 col-md-6 small">
                    <div><strong>Scholarship:</strong> <?= e((string) $application['scholarship_type']) ?></div>
                    <div><strong>Applicant Type:</strong> <?= e(strtoupper((string) $application['applicant_type'])) ?></div>
                    <div><strong>School:</strong> <?= e((string) $application['school_name']) ?> (<?= e(strtoupper((string) $application['school_type'])) ?>)</div>
                    <div><strong>Latest Scan Purpose:</strong> <?= e(qr_scan_purpose_label($scanPurpose)) ?></div>
                </div>
                <div class="col-12 col-md-6 small">
                    <div><strong>Semester / SY:</strong> <?= e((string) $application['semester']) ?> / <?= e((string) $application['school_year']) ?></div>
                    <div><strong>QR Token:</strong> <code><?= e((string) $application['qr_token']) ?></code></div>
                    <div><strong>Submitted:</strong> <?= !empty($application['submitted_at']) ? date('M d, Y h:i A', strtotime((string) $application['submitted_at'])) : '-' ?></div>
                    <?php if ($scanNotes !== ''): ?>
                        <div><strong>Latest Scan Notes:</strong> <?= e($scanNotes) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a href="../print-application.php?id=<?= (int) $application['id'] ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-print me-1"></i>Print Form
                </a>
                <a href="../my-qr.php?id=<?= (int) $application['id'] ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-qrcode me-1"></i>View Full QR
                </a>
                <a href="applications.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-folder-tree me-1"></i>Open Applications
                </a>
            </div>
        </div>
    </div>

    <?php if ($hasQrScanLogsTable): ?>
        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body">
                <h3 class="h6">QR Scan History For This Applicant</h3>
                <?php if (!$applicationScanLogs): ?>
                    <p class="text-muted mb-0">No scan history yet for this applicant.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Purpose</th>
                                    <th>Result</th>
                                    <th>Scanned By</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applicationScanLogs as $log): ?>
                                    <?php
                                    $scannerName = trim((string) ($log['scanner_first_name'] ?? '') . ' ' . (string) ($log['scanner_last_name'] ?? ''));
                                    $notesText = trim((string) ($log['notes'] ?? ''));
                                    if ($notesText === '') {
                                        $notesText = '-';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= date('M d, Y h:i A', strtotime((string) $log['created_at'])) ?></td>
                                        <td><?= e(qr_scan_purpose_label((string) $log['purpose'])) ?></td>
                                        <td>
                                            <span class="badge <?= qr_scan_status_badge_class((string) $log['scan_status']) ?>">
                                                <?= e(strtoupper((string) $log['scan_status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e($scannerName !== '' ? $scannerName : '-') ?></td>
                                        <td><?= e($notesText) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6">Related Disbursement Records</h3>
            <?php if (!$disbursements): ?>
                <p class="text-muted mb-0">No disbursement records for this applicant yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Payout Schedule</th>
                                <th>Amount</th>
                                <th>Reference</th>
                                <th>Location</th>
                                <th>Status</th>
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
                                <tr>
                                    <td><?= e($payoutSchedule) ?></td>
                                    <td>PHP <?= number_format((float) $row['amount'], 2) ?></td>
                                    <td><?= e((string) $row['reference_no']) ?></td>
                                    <td><?= e((string) ($row['payout_location'] ?? '-')) ?></td>
                                    <td><?= e(strtoupper((string) $row['status'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($scannedRaw !== ''): ?>
    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6">Scan Result</h2>
            <p class="small text-muted mb-1">No matching applicant/application found for scanned content.</p>
            <div class="small"><strong>Scan Purpose:</strong> <?= e(qr_scan_purpose_label($scanPurpose)) ?></div>
            <?php if ($scanNotes !== ''): ?>
                <div class="small"><strong>Scan Notes:</strong> <?= e($scanNotes) ?></div>
            <?php endif; ?>
            <?php if ($scanInfo): ?>
                <div class="small"><strong>Detected QR Token:</strong> <?= e((string) ($scanInfo['qr_token'] ?? '-')) ?></div>
                <div class="small"><strong>Detected Application No:</strong> <?= e((string) ($scanInfo['application_no'] ?? '-')) ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card card-soft shadow-sm">
    <div class="card-body">
        <h3 class="h6">Recent QR Scan Activity</h3>
        <?php if (!$hasQrScanLogsTable): ?>
            <p class="text-muted mb-0">Scan history setup is not ready yet. Please contact the administrator.</p>
        <?php elseif (!$recentScanLogs): ?>
            <p class="text-muted mb-0">No QR scan activity yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Application</th>
                            <th>Applicant</th>
                            <th>Purpose</th>
                            <th>Result</th>
                            <th>Scanned By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentScanLogs as $log): ?>
                            <?php
                            $scannerName = trim((string) ($log['scanner_first_name'] ?? '') . ' ' . (string) ($log['scanner_last_name'] ?? ''));
                            $firstName = trim((string) ($log['first_name'] ?? ''));
                            $lastName = trim((string) ($log['last_name'] ?? ''));
                            $applicantName = '-';
                            if ($firstName !== '' || $lastName !== '') {
                                $applicantName = trim($lastName . ', ' . $firstName, ', ');
                            }
                            $applicationNo = trim((string) ($log['application_no'] ?? ''));
                            if ($applicationNo === '') {
                                $applicationNo = trim((string) ($log['scanned_application_no'] ?? ''));
                            }
                            $notesText = trim((string) ($log['notes'] ?? ''));
                            if ($notesText === '') {
                                $notesText = '-';
                            }
                            ?>
                            <tr>
                                <td><?= date('M d, Y h:i A', strtotime((string) $log['created_at'])) ?></td>
                                <td><?= e($applicationNo !== '' ? $applicationNo : '-') ?></td>
                                <td><?= e($applicantName) ?></td>
                                <td><?= e(qr_scan_purpose_label((string) $log['purpose'])) ?></td>
                                <td>
                                    <span class="badge <?= qr_scan_status_badge_class((string) $log['scan_status']) ?>">
                                        <?= e(strtoupper((string) $log['scan_status'])) ?>
                                    </span>
                                </td>
                                <td><?= e($scannerName !== '' ? $scannerName : '-') ?></td>
                                <td><?= e($notesText) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    (function () {
        const statusElement = document.getElementById('scanStatus');
        const inputElement = document.getElementById('scannedDataInput');
        const formElement = document.getElementById('verifyForm');
        const purposeElement = document.getElementById('scanPurposeInput');
        const startButton = document.getElementById('startScannerBtn');
        const stopButton = document.getElementById('stopScannerBtn');
        let isSubmitted = false;
        let scanner = null;
        let scannerRunning = false;

        function updateButtonState() {
            const hasPurpose = !!(purposeElement && String(purposeElement.value || '').trim() !== '');
            if (startButton) {
                startButton.disabled = !hasPurpose || scannerRunning || isSubmitted;
            }
            if (stopButton) {
                stopButton.disabled = !scannerRunning || isSubmitted;
            }
        }

        function onScanSuccess(decodedText) {
            if (isSubmitted) {
                return;
            }
            isSubmitted = true;
            if (statusElement) {
                statusElement.textContent = 'Scan detected. Verifying and saving activity...';
            }
            if (inputElement) {
                inputElement.value = decodedText;
            }
            if (formElement) {
                formElement.submit();
            }
            updateButtonState();
        }

        function onScanFailure() {
            // Keep scanning continuously.
        }

        function startScanner() {
            if (scannerRunning || isSubmitted) {
                return;
            }
            const selectedPurpose = purposeElement ? String(purposeElement.value || '').trim() : '';
            if (selectedPurpose === '') {
                if (statusElement) {
                    statusElement.textContent = 'Please select scan purpose first.';
                }
                if (purposeElement) {
                    purposeElement.focus();
                }
                updateButtonState();
                return;
            }
            if (typeof Html5QrcodeScanner === 'undefined') {
                if (statusElement) {
                    statusElement.textContent = 'Scanner library failed to load.';
                }
                return;
            }

            scanner = new Html5QrcodeScanner('qr-reader', {
                fps: 10,
                qrbox: { width: 240, height: 240 },
                rememberLastUsedCamera: true,
                supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
            }, false);
            scanner.render(onScanSuccess, onScanFailure);
            scannerRunning = true;
            if (statusElement) {
                statusElement.textContent = 'Scanner is running. Point camera to the QR code.';
            }
            updateButtonState();
        }

        function stopScanner() {
            if (!scanner || !scannerRunning || isSubmitted) {
                return;
            }
            Promise.resolve(scanner.clear())
                .catch(function () {
                    // Ignore scanner clear errors.
                })
                .finally(function () {
                    scanner = null;
                    scannerRunning = false;
                    if (statusElement) {
                        statusElement.textContent = 'Scanner stopped. Click Start Scanner to scan again.';
                    }
                    updateButtonState();
                });
        }

        if (startButton) {
            startButton.addEventListener('click', startScanner);
        }
        if (stopButton) {
            stopButton.addEventListener('click', stopScanner);
        }
        if (purposeElement) {
            purposeElement.addEventListener('change', function () {
                if (!scannerRunning && statusElement) {
                    statusElement.textContent = purposeElement.value
                        ? 'Ready. Click Start Scanner when you are prepared to scan.'
                        : 'Scanner is off. Select purpose then click Start Scanner.';
                }
                updateButtonState();
            });
        }

        updateButtonState();
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
