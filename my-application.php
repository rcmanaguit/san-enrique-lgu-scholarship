<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('login.php');
require_role(['applicant'], 'index.php');

$pageTitle = 'My Application Progress';
$user = current_user();
$applications = [];
$applicationDocumentsById = [];
$openPeriod = null;
$hasApplicationThisPeriod = false;
$canCreateNewApplication = false;
$latestApplication = null;
$statusDisplay = [
    'under_review' => 'Under Review',
    'needs_resubmission' => 'Needs Resubmission',
    'for_interview' => 'For Interview',
    'interview_passed' => 'Interview Passed',
    'for_soa' => 'For SOA',
    'soa_received' => 'SOA Received',
    'awaiting_payout' => 'Awaiting Approval',
    'disbursed' => 'Disbursed',
    'rejected' => 'Rejected',
];
$statusSimpleMessage = [
    'under_review' => 'Under review.',
    'needs_resubmission' => 'Needs resubmission.',
    'for_interview' => 'For interview.',
    'interview_passed' => 'Interview passed.',
    'for_soa' => 'Submit SOA.',
    'soa_received' => 'SOA received.',
    'awaiting_payout' => 'Awaiting approval.',
    'disbursed' => 'Payout released.',
    'rejected' => 'Not approved.',
];
$bodyClass = 'applicant-my-application-page';
if (db_ready()) {
    $openPeriod = current_open_application_period($conn);
    if ($openPeriod) {
        $hasApplicationThisPeriod = applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod);
    }
    $canCreateNewApplication = $openPeriod !== null && !$hasApplicationThisPeriod;

    $stmt = $conn->prepare(
        "SELECT a.id, a.application_no, a.qr_token, a.school_name, a.school_type, a.semester, a.school_year,
                a.status, a.review_notes, a.soa_submission_deadline, a.soa_submitted_at, a.submitted_at, a.updated_at,
                COUNT(d.id) AS document_count
         FROM applications a
         LEFT JOIN application_documents d ON d.application_id = a.id
         WHERE a.user_id = ?
         GROUP BY a.id
         ORDER BY a.id DESC"
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result instanceof mysqli_result) {
        $applications = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
    $latestApplication = $applications[0] ?? null;

    if ($applications) {
        $applicationIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $applications);
        $applicationIds = array_values(array_filter($applicationIds, static fn(int $id): bool => $id > 0));
        if ($applicationIds) {
            $idList = implode(',', $applicationIds);
            $docsSql = "SELECT application_id, requirement_name, file_path
                        FROM application_documents
                        WHERE application_id IN (" . $idList . ")
                        ORDER BY id ASC";
            $docsResult = $conn->query($docsSql);
            if ($docsResult instanceof mysqli_result) {
                while ($doc = $docsResult->fetch_assoc()) {
                    $appId = (int) ($doc['application_id'] ?? 0);
                    if ($appId <= 0) {
                        continue;
                    }
                    if (!isset($applicationDocumentsById[$appId])) {
                        $applicationDocumentsById[$appId] = [];
                    }
                    $applicationDocumentsById[$appId][] = $doc;
                }
            }
        }
    }
}

$applicationModalPayload = [];
foreach ($applications as $row) {
    $appId = (int) ($row['id'] ?? 0);
    if ($appId <= 0) {
        continue;
    }
    $statusCode = (string) ($row['status'] ?? '');
    $statusLabel = $statusDisplay[$statusCode] ?? ucwords(str_replace('_', ' ', $statusCode));
    $documents = [];
    foreach (($applicationDocumentsById[$appId] ?? []) as $doc) {
        $path = trim((string) ($doc['file_path'] ?? ''));
        $isPreviewable = $path !== '' && (
            str_starts_with($path, 'uploads/documents/')
            || str_starts_with($path, 'uploads/tmp/')
            || str_starts_with($path, '/uploads/documents/')
            || str_starts_with($path, '/uploads/tmp/')
        );
        $documents[] = [
            'name' => (string) ($doc['requirement_name'] ?? 'Requirement'),
            'path' => (string) ltrim($path, '/'),
            'previewable' => $isPreviewable,
        ];
    }

    $applicationModalPayload[(string) $appId] = [
        'id' => $appId,
        'application_no' => (string) ($row['application_no'] ?? '-'),
        'period' => trim((string) (($row['semester'] ?? '-') . ' / ' . ($row['school_year'] ?? '-'))),
        'status_code' => $statusCode,
        'status_label' => $statusLabel,
        'status_badge_class' => status_badge_class($statusCode),
        'school_name' => (string) ($row['school_name'] ?? ''),
        'school_type' => strtoupper((string) ($row['school_type'] ?? '')),
        'updated' => date('M d, Y h:i A', strtotime((string) ($row['updated_at'] ?? 'now'))),
        'review_notes' => (string) ($row['review_notes'] ?? ''),
        'soa_deadline' => !empty($row['soa_submission_deadline']) ? date('M d, Y', strtotime((string) $row['soa_submission_deadline'])) : '',
        'soa_received' => !empty($row['soa_submitted_at']) ? date('M d, Y h:i A', strtotime((string) $row['soa_submitted_at'])) : '',
        'documents' => $documents,
        'print_url' => 'print-application.php?id=' . $appId,
        'qr_url' => 'my-qr.php?id=' . $appId,
    ];
}

include __DIR__ . '/includes/header.php';
?>

<div class="card card-soft applicant-hero mb-3">
    <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <p class="text-muted small mb-1">Application Tracker</p>
            <h1 class="h4 m-0"><i class="fa-solid fa-folder-open me-2 text-primary"></i>My Application & Status</h1>
            <p class="small text-muted mb-0 mt-1">Review your current stage, pending actions, and past submissions.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap applicant-hero-actions">
    <?php if ($canCreateNewApplication): ?>
        <a href="apply.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i>New Application
        </a>
    <?php elseif ($openPeriod && $hasApplicationThisPeriod): ?>
        <button class="btn btn-secondary btn-sm" disabled>
            <i class="fa-solid fa-lock me-1"></i>Already Applied This Period
        </button>
    <?php else: ?>
        <button class="btn btn-secondary btn-sm" disabled>
            <i class="fa-solid fa-lock me-1"></i>Application Period Closed
        </button>
    <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!db_ready()): ?>
    <div class="alert alert-warning">The system is not ready yet. Please contact the administrator.</div>
<?php elseif ($openPeriod && $hasApplicationThisPeriod): ?>
    <div class="alert alert-secondary small">
        You already submitted an application in <?= e((string) ($openPeriod['period_name'] ?? 'the current period')) ?>.
        A new application is allowed only in the next open period.
    </div>
<?php endif; ?>

<?php if ($latestApplication): ?>
    <?php
    $latestStatus = (string) ($latestApplication['status'] ?? '');
    $latestStatusLabel = $statusDisplay[$latestStatus] ?? ucwords(str_replace('_', ' ', $latestStatus));
    $latestStatusMessage = $statusSimpleMessage[$latestStatus] ?? $latestStatusLabel;
    ?>
    <div class="card card-soft shadow-sm mb-3 applicant-progress-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h2 class="h6 mb-1">Current Application Progress</h2>
                    <div class="small text-muted">
                        <?= e((string) ($latestApplication['application_no'] ?? '-')) ?> |
                        <?= e((string) ($latestApplication['semester'] ?? '-')) ?> / <?= e((string) ($latestApplication['school_year'] ?? '-')) ?>
                    </div>
                </div>
                <span class="badge <?= status_badge_class($latestStatus) ?>"><?= e(strtoupper($latestStatusLabel)) ?></span>
            </div>
            <p class="small text-muted mb-0">
                <strong>Status:</strong> <?= e($latestStatusMessage) ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<?php if (db_ready() && !$applications): ?>
    <div class="card card-soft">
        <div class="card-body">
            <p class="text-muted mb-3">No application records yet.</p>
            <?php if ($canCreateNewApplication): ?>
                <a href="apply.php" class="btn btn-primary">Start Application</a>
            <?php else: ?>
                <button class="btn btn-secondary" disabled>Application Period Closed</button>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (db_ready()): ?>
    <div class="card card-soft shadow-sm applicant-history-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 applicant-history-table">
                <thead>
                    <tr>
                        <th>Application Number</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $row): ?>
                    <?php
                    $rowId = (int) ($row['id'] ?? 0);
                    $rowStatusCode = (string) ($row['status'] ?? '');
                    $rowStatusLabel = $statusDisplay[$rowStatusCode] ?? ucwords(str_replace('_', ' ', $rowStatusCode));
                    ?>
                    <tr class="applicant-history-row js-open-application-modal-row" data-app-id="<?= $rowId ?>" tabindex="0" role="button" aria-label="View details for application <?= e((string) $row['application_no']) ?>">
                        <td>
                            <div class="applicant-cell-label d-md-none">Application Number</div>
                            <strong class="applicant-app-link"><?= e((string) $row['application_no']) ?></strong>
                        </td>
                        <td>
                            <div class="applicant-cell-label d-md-none">Period</div>
                            <span><?= e((string) $row['semester']) ?> / <?= e((string) $row['school_year']) ?></span>
                        </td>
                        <td>
                            <div class="applicant-cell-label d-md-none">Status</div>
                            <span class="badge <?= status_badge_class($rowStatusCode) ?>">
                                <?= e(strtoupper($rowStatusLabel)) ?>
                            </span>
                        </td>
                        <td>
                            <div class="applicant-cell-label d-md-none">Updated</div>
                            <?= date('M d, Y h:i A', strtotime((string) $row['updated_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="applicationDetailsModal" tabindex="-1" aria-labelledby="applicationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 m-0" id="applicationDetailsModalLabel">Application Details</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="review-kv">
                    <div class="review-kv-row">
                        <div class="review-kv-label">Application Number</div>
                        <div class="review-kv-value" id="modalApplicationNo">-</div>
                    </div>
                    <div class="review-kv-row">
                        <div class="review-kv-label">Period</div>
                        <div class="review-kv-value" id="modalPeriod">-</div>
                    </div>
                    <div class="review-kv-row">
                        <div class="review-kv-label">Status</div>
                        <div class="review-kv-value"><span class="badge" id="modalStatusBadge">-</span></div>
                    </div>
                    <div class="review-kv-row">
                        <div class="review-kv-label">School</div>
                        <div class="review-kv-value" id="modalSchool">-</div>
                    </div>
                    <div class="review-kv-row">
                        <div class="review-kv-label">Updated</div>
                        <div class="review-kv-value" id="modalUpdated">-</div>
                    </div>
                    <div class="review-kv-row" id="modalReviewNotesRow">
                        <div class="review-kv-label">Review Notes</div>
                        <div class="review-kv-value" id="modalReviewNotes">-</div>
                    </div>
                    <div class="review-kv-row" id="modalSoaDeadlineRow">
                        <div class="review-kv-label">SOA Deadline</div>
                        <div class="review-kv-value" id="modalSoaDeadline">-</div>
                    </div>
                    <div class="review-kv-row" id="modalSoaSubmittedRow">
                        <div class="review-kv-label">SOA Submitted</div>
                        <div class="review-kv-value" id="modalSoaSubmitted">-</div>
                    </div>
                </div>

                <hr>

                <h3 class="h6 mb-2">Uploaded Documents</h3>
                <div id="modalDocumentsEmpty" class="small text-muted d-none">No uploaded documents found.</div>
                <ul class="list-group" id="modalDocumentsList"></ul>
            </div>
            <div class="modal-footer justify-content-between flex-wrap gap-2">
                <div class="d-flex gap-2">
                    <a href="#" class="btn btn-outline-primary btn-sm" id="modalPrintBtn">
                        <i class="fa-solid fa-print me-1"></i>Print
                    </a>
                    <a href="#" class="btn btn-outline-primary btn-sm" id="modalQrBtn">
                        <i class="fa-solid fa-qrcode me-1"></i>QR Code
                    </a>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="applicantDocPreviewModal" tabindex="-1" aria-labelledby="applicantDocPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 m-0" id="applicantDocPreviewModalLabel">Document Preview</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe
                    id="applicantDocPreviewFrame"
                    src="about:blank"
                    title="Document Preview"
                    style="border:0;width:100%;height:100%;background:#fff;"
                ></iframe>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="#" id="applicantDocPreviewNewTab" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-up-right-from-square me-1"></i>Open in New Tab
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof bootstrap === 'undefined') {
            return;
        }
        const payload = <?= json_encode($applicationModalPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const modalEl = document.getElementById('applicationDetailsModal');
        if (!modalEl) {
            return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const applicationNoEl = document.getElementById('modalApplicationNo');
        const periodEl = document.getElementById('modalPeriod');
        const statusEl = document.getElementById('modalStatusBadge');
        const schoolEl = document.getElementById('modalSchool');
        const updatedEl = document.getElementById('modalUpdated');
        const reviewNotesEl = document.getElementById('modalReviewNotes');
        const reviewNotesRowEl = document.getElementById('modalReviewNotesRow');
        const soaDeadlineEl = document.getElementById('modalSoaDeadline');
        const soaDeadlineRowEl = document.getElementById('modalSoaDeadlineRow');
        const soaSubmittedEl = document.getElementById('modalSoaSubmitted');
        const soaSubmittedRowEl = document.getElementById('modalSoaSubmittedRow');
        const docsListEl = document.getElementById('modalDocumentsList');
        const docsEmptyEl = document.getElementById('modalDocumentsEmpty');
        const printBtnEl = document.getElementById('modalPrintBtn');
        const qrBtnEl = document.getElementById('modalQrBtn');
        const docPreviewModalEl = document.getElementById('applicantDocPreviewModal');
        const docPreviewTitleEl = document.getElementById('applicantDocPreviewModalLabel');
        const docPreviewFrameEl = document.getElementById('applicantDocPreviewFrame');
        const docPreviewNewTabEl = document.getElementById('applicantDocPreviewNewTab');
        const docPreviewModal = docPreviewModalEl ? bootstrap.Modal.getOrCreateInstance(docPreviewModalEl) : null;

        const openDocumentPreview = function (title, path) {
            if (!docPreviewModal || !docPreviewFrameEl || !path) {
                return;
            }
            const previewUrl = 'preview-document.php?file=' + encodeURIComponent(String(path));
            if (docPreviewTitleEl) {
                docPreviewTitleEl.textContent = title || 'Document Preview';
            }
            docPreviewFrameEl.src = previewUrl;
            if (docPreviewNewTabEl) {
                docPreviewNewTabEl.href = previewUrl;
            }
            docPreviewModal.show();
        };

        const openModal = function (appId) {
            const item = payload[String(appId)];
            if (!item) {
                return;
            }
            applicationNoEl.textContent = item.application_no || '-';
            periodEl.textContent = item.period || '-';
            statusEl.textContent = (item.status_label || '-').toUpperCase();
            statusEl.className = 'badge ' + (item.status_badge_class || 'text-bg-secondary');
            schoolEl.textContent = [item.school_name || '', item.school_type || ''].filter(Boolean).join(' | ') || '-';
            updatedEl.textContent = item.updated || '-';

            const reviewNotes = String(item.review_notes || '').trim();
            reviewNotesRowEl.classList.toggle('d-none', reviewNotes === '');
            reviewNotesEl.textContent = reviewNotes || '-';

            const soaDeadline = String(item.soa_deadline || '').trim();
            soaDeadlineRowEl.classList.toggle('d-none', soaDeadline === '');
            soaDeadlineEl.textContent = soaDeadline || '-';

            const soaSubmitted = String(item.soa_submitted || '').trim();
            soaSubmittedRowEl.classList.toggle('d-none', soaSubmitted === '');
            soaSubmittedEl.textContent = soaSubmitted || '-';

            docsListEl.innerHTML = '';
            const docs = Array.isArray(item.documents) ? item.documents : [];
            docsEmptyEl.classList.toggle('d-none', docs.length > 0);
            docs.forEach(function (doc) {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap';

                const name = document.createElement('span');
                name.className = 'small';
                name.textContent = String(doc.name || 'Requirement');
                li.appendChild(name);

                if (doc.previewable && doc.path) {
                    const actionsWrap = document.createElement('div');
                    actionsWrap.className = 'd-flex align-items-center gap-1';

                    const viewBtn = document.createElement('button');
                    viewBtn.type = 'button';
                    viewBtn.className = 'btn btn-outline-primary btn-sm';
                    viewBtn.textContent = 'View';
                    viewBtn.addEventListener('click', function () {
                        openDocumentPreview(String(doc.name || 'Document Preview'), String(doc.path || ''));
                    });
                    actionsWrap.appendChild(viewBtn);

                    const openBtn = document.createElement('a');
                    openBtn.className = 'btn btn-outline-secondary btn-sm';
                    openBtn.href = 'preview-document.php?file=' + encodeURIComponent(String(doc.path));
                    openBtn.target = '_blank';
                    openBtn.rel = 'noopener noreferrer';
                    openBtn.title = 'Open in new tab';
                    openBtn.setAttribute('aria-label', 'Open in new tab');
                    openBtn.innerHTML = '<i class="fa-solid fa-up-right-from-square"></i>';
                    actionsWrap.appendChild(openBtn);

                    li.appendChild(actionsWrap);
                }

                docsListEl.appendChild(li);
            });

            printBtnEl.setAttribute('href', String(item.print_url || '#'));
            qrBtnEl.setAttribute('href', String(item.qr_url || '#'));
            modal.show();
        };

        document.querySelectorAll('.js-open-application-modal-row').forEach(function (row) {
            row.addEventListener('click', function () {
                const appId = row.getAttribute('data-app-id') || '';
                openModal(appId);
            });
            row.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                const appId = row.getAttribute('data-app-id') || '';
                openModal(appId);
            });
        });

        if (docPreviewModalEl) {
            docPreviewModalEl.addEventListener('hidden.bs.modal', function () {
                if (docPreviewFrameEl) {
                    docPreviewFrameEl.src = 'about:blank';
                }
                if (docPreviewNewTabEl) {
                    docPreviewNewTabEl.href = '#';
                }
            });
        }
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
