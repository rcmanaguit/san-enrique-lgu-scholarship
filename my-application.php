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
$periodScope = trim((string) ($_GET['period_scope'] ?? 'active'));
$allowedPeriodScopes = ['active', 'archived', 'all'];
$hasApplicationPeriodColumn = false;
$resubmissionTargetsByAppId = [];
$periodTimeline = [];
$soaDocumentsByAppId = [];
$bodyClass = 'applicant-my-application-page';
if (!in_array($periodScope, $allowedPeriodScopes, true)) {
    $periodScope = 'active';
}

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('my-application.php?period_scope=' . urlencode($periodScope));
    }
    if (!db_ready()) {
        set_flash('danger', 'The system is not ready yet. Please contact the administrator.');
        redirect('my-application.php?period_scope=' . urlencode($periodScope));
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'resubmit_documents') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        if ($applicationId <= 0) {
            set_flash('danger', 'Invalid application selected for resubmission.');
            redirect('my-application.php?period_scope=' . urlencode($periodScope));
        }

        $stmtApplication = $conn->prepare(
            "SELECT id, application_no, status
             FROM applications
             WHERE id = ?
               AND user_id = ?
             LIMIT 1"
        );
        $userId = (int) ($user['id'] ?? 0);
        $stmtApplication->bind_param('ii', $applicationId, $userId);
        $stmtApplication->execute();
        $application = $stmtApplication->get_result()->fetch_assoc();
        $stmtApplication->close();

        if (!$application) {
            set_flash('danger', 'Application not found.');
            redirect('my-application.php?period_scope=' . urlencode($periodScope));
        }
        if ((string) ($application['status'] ?? '') !== 'needs_resubmission') {
            set_flash('danger', 'This application is not currently open for document resubmission.');
            redirect('my-application.php?period_scope=' . urlencode($periodScope));
        }

        $stmtDocs = $conn->prepare(
            "SELECT id, requirement_name, file_path, verification_status, remarks
             FROM application_documents
             WHERE application_id = ?
             ORDER BY id ASC"
        );
        $stmtDocs->bind_param('i', $applicationId);
        $stmtDocs->execute();
        $docsResult = $stmtDocs->get_result();
        $documents = $docsResult instanceof mysqli_result ? $docsResult->fetch_all(MYSQLI_ASSOC) : [];
        $stmtDocs->close();

        $resubmittableDocs = array_values(array_filter($documents, static function (array $doc): bool {
            return (string) ($doc['verification_status'] ?? '') === 'rejected';
        }));
        if (!$resubmittableDocs) {
            set_flash('danger', 'No rejected documents are available for resubmission.');
            redirect('my-application.php?period_scope=' . urlencode($periodScope));
        }

        $uploadedCount = 0;
        $conn->begin_transaction();
        try {
            foreach ($resubmittableDocs as $doc) {
                $docId = (int) ($doc['id'] ?? 0);
                if ($docId <= 0) {
                    continue;
                }

                $fieldName = 'resubmit_doc_' . $docId;
                $uploaded = upload_any_file($fieldName, __DIR__ . '/uploads/documents');
                if ($uploaded === null) {
                    continue;
                }

                $oldPath = trim((string) ($doc['file_path'] ?? ''));
                $oldAbsolute = __DIR__ . '/' . ltrim($oldPath, '/');
                if (
                    $oldPath !== ''
                    && (
                        str_starts_with($oldPath, 'uploads/documents/')
                        || str_starts_with($oldPath, 'uploads/tmp/')
                    )
                    && file_exists($oldAbsolute)
                ) {
                    @unlink($oldAbsolute);
                }

                $newRelativePath = str_replace(str_replace('\\', '/', __DIR__) . '/', '', str_replace('\\', '/', (string) $uploaded['file_path']));
                $newFileExt = (string) ($uploaded['ext'] ?? '');
                $remark = 'Resubmitted by applicant on ' . date('M d, Y h:i A');

                $stmtUpdateDoc = $conn->prepare(
                    "UPDATE application_documents
                     SET file_path = ?, file_ext = ?, verification_status = 'pending', remarks = ?, uploaded_at = NOW()
                     WHERE id = ?
                       AND application_id = ?
                     LIMIT 1"
                );
                $stmtUpdateDoc->bind_param('sssii', $newRelativePath, $newFileExt, $remark, $docId, $applicationId);
                $stmtUpdateDoc->execute();
                $stmtUpdateDoc->close();
                $uploadedCount++;
            }

            if ($uploadedCount <= 0) {
                throw new RuntimeException('Upload at least one replacement document before submitting.');
            }

            $stmtRemaining = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM application_documents
                 WHERE application_id = ?
                   AND verification_status = 'rejected'"
            );
            $stmtRemaining->bind_param('i', $applicationId);
            $stmtRemaining->execute();
            $remainingRow = $stmtRemaining->get_result()->fetch_assoc();
            $stmtRemaining->close();
            $remainingRejected = (int) ($remainingRow['total'] ?? 0);

            $newStatus = $remainingRejected === 0 ? 'under_review' : 'needs_resubmission';
            $stmtUpdateApplication = $conn->prepare(
                "UPDATE applications
                 SET status = ?, updated_at = NOW()
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmtUpdateApplication->bind_param('si', $newStatus, $applicationId);
            $stmtUpdateApplication->execute();
            $stmtUpdateApplication->close();

            create_notifications_for_roles(
                $conn,
                ['admin', 'staff'],
                'Applicant Resubmitted Documents',
                'Application ' . (string) ($application['application_no'] ?? '') . ' has replacement document uploads and is ready for review.',
                'application',
                'shared/applications.php',
                $userId
            );
            audit_log(
                $conn,
                'application_documents_resubmitted',
                $userId,
                (string) ($user['role'] ?? 'applicant'),
                'application',
                (string) $applicationId,
                'Applicant resubmitted one or more documents.',
                [
                    'application_no' => (string) ($application['application_no'] ?? ''),
                    'uploaded_count' => $uploadedCount,
                    'status_after' => $newStatus,
                ]
            );

            $conn->commit();
            if ($newStatus === 'under_review') {
                set_flash('success', 'Replacement documents uploaded successfully. Your application is back under review.');
            } else {
                set_flash('success', 'Replacement documents uploaded successfully. Upload the remaining rejected documents to complete resubmission.');
            }
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash('danger', $e->getMessage());
        }

        redirect('my-application.php?period_scope=' . urlencode($periodScope));
    }

    if ($action === 'submit_soa_digital') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        if ($applicationId <= 0) {
            set_flash('danger', 'Invalid application selected for SOA submission.');
            redirect('my-application.php?period_scope=' . urlencode($periodScope));
        }

        $stmtApplication = $conn->prepare(
            "SELECT id, application_no, status, soa_submission_deadline, soa_submitted_at
             FROM applications
             WHERE id = ?
               AND user_id = ?
             LIMIT 1"
        );
        $userId = (int) ($user['id'] ?? 0);
        $stmtApplication->bind_param('ii', $applicationId, $userId);
        $stmtApplication->execute();
        $application = $stmtApplication->get_result()->fetch_assoc();
        $stmtApplication->close();

        if (!$application) {
            set_flash('danger', 'Application not found.');
            redirect('my-application.php?period_scope=' . urlencode($periodScope));
        }
        if ((string) ($application['status'] ?? '') !== 'for_soa') {
            set_flash('danger', 'This application is not currently open for SOA submission.');
            redirect('my-application.php?period_scope=' . urlencode($periodScope));
        }

        $uploaded = upload_any_file('soa_document', __DIR__ . '/uploads/documents');
        if ($uploaded === null) {
            set_flash('danger', 'Upload your SOA file before submitting.');
            redirect('my-application.php?period_scope=' . urlencode($periodScope));
        }

        $stmtExistingDoc = $conn->prepare(
            "SELECT id, file_path
             FROM application_documents
             WHERE application_id = ?
               AND requirement_name = 'Original Student Copy / Statement of Account (SOA)'
             ORDER BY id ASC
             LIMIT 1"
        );
        $stmtExistingDoc->bind_param('i', $applicationId);
        $stmtExistingDoc->execute();
        $existingSoaDoc = $stmtExistingDoc->get_result()->fetch_assoc() ?: null;
        $stmtExistingDoc->close();

        $newRelativePath = str_replace(str_replace('\\', '/', __DIR__) . '/', '', str_replace('\\', '/', (string) ($uploaded['file_path'] ?? '')));
        $newFileExt = (string) ($uploaded['ext'] ?? '');
        $remarks = 'Submitted digitally by applicant on ' . date('M d, Y h:i A');

        $stmtRequirementTemplate = $conn->prepare(
            "SELECT id
             FROM requirement_templates
             WHERE requirement_name = 'Original Student Copy / Statement of Account (SOA)'
             ORDER BY id ASC
             LIMIT 1"
        );
        $stmtRequirementTemplate->execute();
        $templateRow = $stmtRequirementTemplate->get_result()->fetch_assoc() ?: null;
        $stmtRequirementTemplate->close();
        $soaTemplateId = (int) ($templateRow['id'] ?? 0);
        $soaTemplateId = $soaTemplateId > 0 ? $soaTemplateId : null;

        $conn->begin_transaction();
        try {
            if ($existingSoaDoc) {
                $oldPath = trim((string) ($existingSoaDoc['file_path'] ?? ''));
                $oldAbsolute = __DIR__ . '/' . ltrim($oldPath, '/');
                if (
                    $oldPath !== ''
                    && (
                        str_starts_with($oldPath, 'uploads/documents/')
                        || str_starts_with($oldPath, 'uploads/tmp/')
                    )
                    && file_exists($oldAbsolute)
                ) {
                    @unlink($oldAbsolute);
                }

                $existingDocId = (int) ($existingSoaDoc['id'] ?? 0);
                $stmtUpdateDoc = $conn->prepare(
                    "UPDATE application_documents
                     SET requirement_template_id = ?, file_path = ?, file_ext = ?, verification_status = 'pending', remarks = ?, uploaded_at = NOW()
                     WHERE id = ?
                       AND application_id = ?
                     LIMIT 1"
                );
                $stmtUpdateDoc->bind_param('isssii', $soaTemplateId, $newRelativePath, $newFileExt, $remarks, $existingDocId, $applicationId);
                $stmtUpdateDoc->execute();
                $stmtUpdateDoc->close();
            } else {
                $stmtInsertDoc = $conn->prepare(
                    "INSERT INTO application_documents
                     (application_id, requirement_template_id, requirement_name, document_type, file_path, file_ext, verification_status, remarks)
                     VALUES (?, ?, 'Original Student Copy / Statement of Account (SOA)', 'requirement', ?, ?, 'pending', ?)"
                );
                $stmtInsertDoc->bind_param('iisss', $applicationId, $soaTemplateId, $newRelativePath, $newFileExt, $remarks);
                $stmtInsertDoc->execute();
                $stmtInsertDoc->close();
            }

            $submittedAt = date('Y-m-d H:i:s');
            $stmtUpdateApplication = $conn->prepare(
                "UPDATE applications
                 SET soa_submitted_at = ?, updated_at = NOW()
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmtUpdateApplication->bind_param('si', $submittedAt, $applicationId);
            $stmtUpdateApplication->execute();
            $stmtUpdateApplication->close();

            audit_log(
                $conn,
                'application_soa_uploaded_digital',
                $userId,
                (string) ($user['role'] ?? 'applicant'),
                'application',
                (string) $applicationId,
                'Applicant uploaded SOA digitally.',
                [
                    'application_no' => (string) ($application['application_no'] ?? ''),
                ]
            );

            $conn->commit();
            set_flash('success', 'SOA uploaded successfully.');
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash('danger', 'SOA upload failed. Please try again.');
        }

        redirect('my-application.php?period_scope=' . urlencode($periodScope));
    }
}

if (db_ready()) {
    $openPeriod = current_open_application_period($conn);
    $hasApplicationPeriodColumn = table_column_exists($conn, 'applications', 'application_period_id');
    if ($openPeriod) {
        $hasApplicationThisPeriod = applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod);
    }
    $canCreateNewApplication = $openPeriod !== null && !$hasApplicationThisPeriod;

    $whereClauses = ['a.user_id = ?'];
    $paramTypes = 'i';
    $paramValues = [(int) ($user['id'] ?? 0)];
    $activePeriodId = (int) ($openPeriod['id'] ?? 0);
    $activeSemester = trim((string) ($openPeriod['semester'] ?? ''));
    $activeSchoolYear = trim((string) ($openPeriod['academic_year'] ?? ''));
    if ($periodScope === 'active') {
        if ($openPeriod) {
            if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
                $whereClauses[] = 'a.application_period_id = ?';
                $paramTypes .= 'i';
                $paramValues[] = $activePeriodId;
            } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
                $whereClauses[] = 'a.semester = ? AND a.school_year = ?';
                $paramTypes .= 'ss';
                $paramValues[] = $activeSemester;
                $paramValues[] = $activeSchoolYear;
            } else {
                $whereClauses[] = '1 = 0';
            }
        } else {
            $whereClauses[] = '1 = 0';
        }
    } elseif ($periodScope === 'archived') {
        if ($openPeriod) {
            if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
                $whereClauses[] = '(a.application_period_id IS NULL OR a.application_period_id <> ?)';
                $paramTypes .= 'i';
                $paramValues[] = $activePeriodId;
            } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
                $whereClauses[] = '(a.semester <> ? OR a.school_year <> ?)';
                $paramTypes .= 'ss';
                $paramValues[] = $activeSemester;
                $paramValues[] = $activeSchoolYear;
            }
        }
    }

    $applicationsSql =
        "SELECT a.id, a.application_no, a.qr_token, a.school_name, a.school_type, a.semester, a.school_year,
                a.status, a.review_notes, a.interview_date, a.interview_location,
                a.soa_submission_deadline, a.soa_submitted_at, a.submitted_at, a.updated_at,
                COUNT(d.id) AS document_count
         FROM applications a
         LEFT JOIN application_documents d ON d.application_id = a.id
         WHERE " . implode(' AND ', $whereClauses) . "
         GROUP BY a.id
         ORDER BY a.id DESC";
    $stmt = $conn->prepare($applicationsSql);
    $bindArgs = [$paramTypes];
    foreach ($paramValues as $index => $value) {
        $bindArgs[] = &$paramValues[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result instanceof mysqli_result) {
        $applications = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
    foreach ($applications as &$applicationRow) {
        $applicationRow['is_archived'] = application_is_archived_for_active_period($applicationRow, $openPeriod, $hasApplicationPeriodColumn) ? 1 : 0;
    }
    unset($applicationRow);
    $latestApplication = $applications[0] ?? null;

    if ($applications) {
        $applicationIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $applications);
        $applicationIds = array_values(array_filter($applicationIds, static fn(int $id): bool => $id > 0));
        if ($applicationIds) {
            $idList = implode(',', $applicationIds);
            $docsSql = "SELECT id, application_id, requirement_name, file_path, verification_status, remarks
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

    $periodTimeline = application_period_timeline_for_user($conn, (int) ($user['id'] ?? 0));
}

$applicationModalPayload = [];
foreach ($applications as $row) {
    $appId = (int) ($row['id'] ?? 0);
    if ($appId <= 0) {
        continue;
    }
    $statusCode = (string) ($row['status'] ?? '');
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
            'id' => (int) ($doc['id'] ?? 0),
            'name' => (string) ($doc['requirement_name'] ?? 'Requirement'),
            'path' => (string) ltrim($path, '/'),
            'previewable' => $isPreviewable,
            'verification_status' => (string) ($doc['verification_status'] ?? 'pending'),
            'remarks' => (string) ($doc['remarks'] ?? ''),
        ];
    }

    $resubmissionTargets = array_values(array_filter($documents, static function (array $doc): bool {
        return (string) ($doc['verification_status'] ?? '') === 'rejected';
    }));
    if ($statusCode === 'needs_resubmission' && $resubmissionTargets) {
        $resubmissionTargetsByAppId[$appId] = $resubmissionTargets;
    }
    $soaDocuments = array_values(array_filter($documents, static function (array $doc): bool {
        return trim((string) ($doc['name'] ?? '')) === 'Original Student Copy / Statement of Account (SOA)';
    }));
    if ($soaDocuments) {
        $soaDocumentsByAppId[$appId] = $soaDocuments[0];
    }

    $row['rejected_document_count'] = count($resubmissionTargets);
    $nextAction = application_next_action_summary($row, 'applicant');

    $applicationModalPayload[(string) $appId] = [
        'id' => $appId,
        'application_no' => (string) ($row['application_no'] ?? '-'),
        'period' => trim((string) (($row['semester'] ?? '-') . ' / ' . ($row['school_year'] ?? '-'))),
        'status_code' => $statusCode,
        'status_label' => application_status_label($statusCode),
        'status_badge_class' => status_badge_class($statusCode),
        'school_name' => (string) ($row['school_name'] ?? ''),
        'school_type' => strtoupper((string) ($row['school_type'] ?? '')),
        'updated' => date('M d, Y h:i A', strtotime((string) ($row['updated_at'] ?? 'now'))),
        'review_notes' => (string) ($row['review_notes'] ?? ''),
        'interview_schedule' => !empty($row['interview_date']) ? date('M d, Y h:i A', strtotime((string) $row['interview_date'])) : '',
        'interview_location' => (string) ($row['interview_location'] ?? ''),
        'soa_deadline' => !empty($row['soa_submission_deadline']) ? date('M d, Y', strtotime((string) $row['soa_submission_deadline'])) : '',
        'soa_received' => !empty($row['soa_submitted_at']) ? date('M d, Y h:i A', strtotime((string) $row['soa_submitted_at'])) : '',
        'documents' => $documents,
        'print_url' => 'print-application.php?id=' . $appId,
        'qr_url' => 'my-qr.php?id=' . $appId,
        'is_archived' => (int) ($row['is_archived'] ?? 0),
        'resubmission_count' => count($resubmissionTargets),
        'next_action_title' => (string) ($nextAction['title'] ?? ''),
        'next_action_detail' => (string) ($nextAction['detail'] ?? ''),
        'timeline' => application_timeline_steps($statusCode),
    ];
}

include __DIR__ . '/includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Application Tracker';
$pageHeaderTitle = '<i class="fa-solid fa-folder-open me-2 text-primary"></i>My Application';
$pageHeaderDescription = 'Use this page as your single source of truth after you submit. It shows your current status, next action, supporting details, and record history.';
$pageHeaderPrimaryAction = $canCreateNewApplication
    ? '<a href="apply.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>New Application</a>'
    : ($openPeriod && $hasApplicationThisPeriod
        ? '<button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock me-1"></i>Already Applied This Period</button>'
        : '<button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock me-1"></i>Application Period Closed</button>');
$pageHeaderSecondaryInfo = $openPeriod
    ? 'Active period: <strong>' . e(format_application_period($openPeriod)) . '</strong>. Old-period applications stay in archived history.'
    : 'No open period right now. Existing records stay available in archived history.';
$pageHeaderActions = '';
include __DIR__ . '/includes/partials/page-shell-header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <?php
    $activeScopeUrl = 'my-application.php?period_scope=active';
    $archivedScopeUrl = 'my-application.php?period_scope=archived';
    $allScopeUrl = 'my-application.php?period_scope=all';
    ?>
    <div class="btn-group btn-group-sm" role="group" aria-label="Application scope">
        <a href="<?= e($activeScopeUrl) ?>" class="btn <?= $periodScope === 'active' ? 'btn-primary' : 'btn-outline-primary' ?>">Active Period</a>
        <a href="<?= e($archivedScopeUrl) ?>" class="btn <?= $periodScope === 'archived' ? 'btn-primary' : 'btn-outline-primary' ?>">Archived</a>
        <a href="<?= e($allScopeUrl) ?>" class="btn <?= $periodScope === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All Records</a>
    </div>
    <div class="small text-muted">
        View scope
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
    $latestMeta = application_status_meta($latestStatus);
    $latestApplication['rejected_document_count'] = count($resubmissionTargetsByAppId[(int) ($latestApplication['id'] ?? 0)] ?? []);
    $latestNextAction = application_next_action_summary($latestApplication, 'applicant');
    $latestTimeline = application_timeline_steps($latestStatus);
    $latestSoaDoc = $soaDocumentsByAppId[(int) ($latestApplication['id'] ?? 0)] ?? null;
    ?>
    <div class="card card-soft page-shell-section mb-3">
        <div class="card-body">
            <div class="action-banner">
                <div>
                    <div class="small text-muted text-uppercase mb-1">What happens next</div>
                    <div class="action-banner__title"><?= e((string) ($latestNextAction['title'] ?? 'Check application updates.')) ?></div>
                    <?php if (trim((string) ($latestNextAction['detail'] ?? '')) !== ''): ?>
                        <div class="action-banner__detail mt-1"><?= e((string) ($latestNextAction['detail'] ?? '')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm js-open-application-modal" data-app-id="<?= (int) ($latestApplication['id'] ?? 0) ?>">
                        <i class="fa-solid fa-file-lines me-1"></i>View Details
                    </button>
                    <a href="my-qr.php?id=<?= (int) ($latestApplication['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-qrcode me-1"></i>My QR
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-soft shadow-sm mb-3 applicant-progress-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Current Application</h2>
                    <div class="small text-muted">
                        <?= e((string) ($latestApplication['application_no'] ?? '-')) ?> |
                        <?= e((string) ($latestApplication['semester'] ?? '-')) ?> / <?= e((string) ($latestApplication['school_year'] ?? '-')) ?>
                    </div>
                </div>
                <span class="badge <?= status_badge_class($latestStatus) ?>"><?= e(strtoupper(application_status_label($latestStatus))) ?></span>
            </div>
            <?php
            $timelineSteps = $latestTimeline;
            $stepperClass = 'mb-3';
            $stepperLabel = 'Current workflow progress';
            include __DIR__ . '/includes/partials/application-workflow-stepper.php';
            ?>
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <div class="applicant-detail-card">
                        <div class="small text-muted text-uppercase">Submitted</div>
                        <div><?= !empty($latestApplication['submitted_at']) ? date('M d, Y h:i A', strtotime((string) $latestApplication['submitted_at'])) : '-' ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="applicant-detail-card">
                        <div class="small text-muted text-uppercase">Updated</div>
                        <div><?= date('M d, Y h:i A', strtotime((string) ($latestApplication['updated_at'] ?? 'now'))) ?></div>
                    </div>
                </div>
                <?php if (!empty($latestApplication['interview_date']) && !empty($latestApplication['interview_location'])): ?>
                    <div class="col-12 col-md-6">
                        <div class="applicant-detail-card">
                            <div class="small text-muted text-uppercase">Interview</div>
                            <div><?= date('M d, Y h:i A', strtotime((string) $latestApplication['interview_date'])) ?></div>
                            <div class="small text-muted"><?= e((string) $latestApplication['interview_location']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($latestApplication['soa_submission_deadline'])): ?>
                    <div class="col-12 col-md-6">
                        <div class="applicant-detail-card">
                            <div class="small text-muted text-uppercase">SOA Deadline</div>
                            <div><?= date('M d, Y', strtotime((string) $latestApplication['soa_submission_deadline'])) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($latestStatus === 'needs_resubmission'): ?>
                <div class="alert alert-warning small mt-3 mb-0">
                    Replace the rejected documents below, then submit them again so your application can return to review.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($latestStatus === 'for_soa'): ?>
        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h6 mb-1">Submit SOA</h2>
                        <div class="small text-muted">
                            <?= e((string) ($latestApplication['application_no'] ?? '-')) ?>
                        </div>
                    </div>
                    <?php if (!empty($latestApplication['soa_submitted_at'])): ?>
                        <span class="badge text-bg-success">Uploaded</span>
                    <?php endif; ?>
                </div>
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="submit_soa_digital">
                    <input type="hidden" name="application_id" value="<?= (int) ($latestApplication['id'] ?? 0) ?>">
                    <div class="col-12">
                        <input type="file" name="soa_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                    <?php if (is_array($latestSoaDoc) && trim((string) ($latestSoaDoc['path'] ?? '')) !== ''): ?>
                        <div class="col-12">
                            <a href="preview-document.php?file=<?= urlencode((string) ($latestSoaDoc['path'] ?? '')) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                                View Current SOA
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-upload me-1"></i>Upload SOA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($resubmissionTargetsByAppId): ?>
    <?php foreach ($applications as $row): ?>
        <?php
        $rowId = (int) ($row['id'] ?? 0);
        $resubmissionDocs = $resubmissionTargetsByAppId[$rowId] ?? [];
        if (!$resubmissionDocs) {
            continue;
        }
        ?>
        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h6 mb-1">Document Resubmission Required</h2>
                        <div class="small text-muted">
                            <?= e((string) ($row['application_no'] ?? '-')) ?> |
                            <?= e((string) ($row['semester'] ?? '-')) ?> / <?= e((string) ($row['school_year'] ?? '-')) ?>
                        </div>
                    </div>
                    <span class="badge text-bg-warning">ACTION NEEDED</span>
                </div>
                <?php if (trim((string) ($row['review_notes'] ?? '')) !== ''): ?>
                    <div class="alert alert-secondary small">
                        <strong>Review Notes:</strong> <?= e((string) ($row['review_notes'] ?? '')) ?>
                    </div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="resubmit_documents">
                    <input type="hidden" name="application_id" value="<?= $rowId ?>">
                    <?php foreach ($resubmissionDocs as $doc): ?>
                        <?php
                        $docId = (int) ($doc['id'] ?? 0);
                        $docRemark = trim((string) ($doc['remarks'] ?? ''));
                        $docPath = trim((string) ($doc['path'] ?? ''));
                        ?>
                        <div class="col-12">
                            <label class="form-label"><?= e((string) ($doc['name'] ?? 'Requirement')) ?></label>
                                <?php if ($docRemark !== ''): ?>
                                <div class="small text-muted mb-1">Issue: <?= e($docRemark) ?></div>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <input
                                    type="file"
                                    name="resubmit_doc_<?= $docId ?>"
                                    class="form-control"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                >
                                <?php if ($docPath !== ''): ?>
                                    <a href="preview-document.php?file=<?= urlencode($docPath) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                                        View Current File
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-upload me-1"></i>Submit Replacement Documents
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (db_ready() && !$applications): ?>
    <div class="card card-soft">
        <div class="card-body">
            <p class="text-muted mb-3">No application records found for this view.</p>
            <?php if ($canCreateNewApplication): ?>
                <a href="apply.php" class="btn btn-primary">Start Application</a>
            <?php else: ?>
                <button class="btn btn-secondary" disabled>Application Period Closed</button>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (db_ready()): ?>
    <?php if ($periodTimeline): ?>
        <details class="workflow-panel workflow-panel-collapsible applicant-history-panel mb-3">
            <summary>
                <span>Application History by Period</span>
                <small><?= count($periodTimeline) ?> period<?= count($periodTimeline) === 1 ? '' : 's' ?></small>
            </summary>
            <div class="workflow-panel-body">
                <div class="scholar-period-history">
                    <?php foreach ($periodTimeline as $timelineRow): ?>
                        <span class="scholar-period-chip">
                            <span class="scholar-period-chip__label"><?= e((string) ($timelineRow['period_label'] ?? 'Period')) ?></span>
                            <span class="badge <?= e((string) ($timelineRow['badge_class'] ?? 'text-bg-light')) ?>">
                                <?= e((string) ($timelineRow['label'] ?? 'No application')) ?>
                            </span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>
    <?php endif; ?>
    <details class="workflow-panel workflow-panel-collapsible applicant-history-panel">
        <summary>
            <span>Application History</span>
            <small><?= count($applications) ?> record<?= count($applications) === 1 ? '' : 's' ?></small>
        </summary>
        <div class="workflow-panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 applicant-history-table">
                <thead>
                    <tr>
                        <th>Application Number</th>
                        <th>Period</th>
                        <th>Current Step</th>
                        <th>Next Action</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $row): ?>
                    <?php
                    $rowId = (int) ($row['id'] ?? 0);
                    $rowStatusCode = (string) ($row['status'] ?? '');
                    $row['rejected_document_count'] = count($resubmissionTargetsByAppId[$rowId] ?? []);
                    $rowNextAction = application_next_action_summary($row, 'applicant');
                    ?>
                    <tr class="applicant-history-row js-open-application-modal-row" data-app-id="<?= $rowId ?>" tabindex="0" role="button" aria-label="View details for application <?= e((string) $row['application_no']) ?>">
                        <td>
                            <div class="applicant-cell-label d-md-none">Application Number</div>
                            <strong class="applicant-app-link"><?= e((string) $row['application_no']) ?></strong>
                            <?php if ((int) ($row['is_archived'] ?? 0) === 1): ?>
                                <span class="badge text-bg-secondary ms-1">Archived</span>
                            <?php else: ?>
                                <span class="badge text-bg-success ms-1">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="applicant-cell-label d-md-none">Period</div>
                            <span><?= e((string) $row['semester']) ?> / <?= e((string) $row['school_year']) ?></span>
                        </td>
                        <td>
                            <div class="applicant-cell-label d-md-none">Current Step</div>
                            <span class="badge <?= status_badge_class($rowStatusCode) ?>">
                                <?= e(strtoupper(application_status_label($rowStatusCode))) ?>
                            </span>
                        </td>
                        <td>
                            <div class="applicant-cell-label d-md-none">Next Action</div>
                            <div class="small fw-semibold"><?= e((string) ($rowNextAction['title'] ?? 'Check status details.')) ?></div>
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
    </details>
<?php endif; ?>

<div class="modal fade" id="applicationDetailsModal" tabindex="-1" aria-labelledby="applicationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h6 m-0" id="applicationDetailsModalLabel">Application Details</h2>
                    <div class="small text-muted mt-1" id="modalApplicationMeta">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="applicant-next-action mb-3">
                    <div class="small text-muted text-uppercase mb-1">Next Action</div>
                    <div class="fw-semibold" id="modalNextActionTitle">-</div>
                    <div class="small text-muted mt-1" id="modalNextActionDetail">-</div>
                </div>
                <div class="application-stepper application-stepper-compact mb-3" id="modalTimeline"></div>
                <div class="review-kv">
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
                    <div class="review-kv-row" id="modalInterviewScheduleRow">
                        <div class="review-kv-label">Interview Schedule</div>
                        <div class="review-kv-value" id="modalInterviewSchedule">-</div>
                    </div>
                    <div class="review-kv-row" id="modalInterviewLocationRow">
                        <div class="review-kv-label">Interview Location</div>
                        <div class="review-kv-value" id="modalInterviewLocation">-</div>
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

                <details class="workflow-panel workflow-panel-collapsible mt-3" open>
                    <summary>
                        <span>Uploaded Documents</span>
                        <small id="modalDocumentsCount">0 file(s)</small>
                    </summary>
                    <div class="workflow-panel-body">
                        <div id="modalDocumentsEmpty" class="small text-muted d-none">No uploaded documents found.</div>
                        <ul class="list-group" id="modalDocumentsList"></ul>
                    </div>
                </details>
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
        const applicationMetaEl = document.getElementById('modalApplicationMeta');
        const periodEl = document.getElementById('modalPeriod');
        const statusEl = document.getElementById('modalStatusBadge');
        const schoolEl = document.getElementById('modalSchool');
        const nextActionTitleEl = document.getElementById('modalNextActionTitle');
        const nextActionDetailEl = document.getElementById('modalNextActionDetail');
        const timelineEl = document.getElementById('modalTimeline');
        const interviewScheduleEl = document.getElementById('modalInterviewSchedule');
        const interviewScheduleRowEl = document.getElementById('modalInterviewScheduleRow');
        const interviewLocationEl = document.getElementById('modalInterviewLocation');
        const interviewLocationRowEl = document.getElementById('modalInterviewLocationRow');
        const reviewNotesEl = document.getElementById('modalReviewNotes');
        const reviewNotesRowEl = document.getElementById('modalReviewNotesRow');
        const soaDeadlineEl = document.getElementById('modalSoaDeadline');
        const soaDeadlineRowEl = document.getElementById('modalSoaDeadlineRow');
        const soaSubmittedEl = document.getElementById('modalSoaSubmitted');
        const soaSubmittedRowEl = document.getElementById('modalSoaSubmittedRow');
        const docsListEl = document.getElementById('modalDocumentsList');
        const docsEmptyEl = document.getElementById('modalDocumentsEmpty');
        const docsCountEl = document.getElementById('modalDocumentsCount');
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
            if (applicationMetaEl) {
                const metaParts = [String(item.application_no || '').trim()];
                metaParts.push(Number(item.is_archived || 0) === 1 ? 'Archived Period' : 'Active Period');
                applicationMetaEl.textContent = metaParts.filter(Boolean).join(' | ') || '-';
            }
            periodEl.textContent = item.period || '-';
            statusEl.textContent = (item.status_label || '-').toUpperCase();
            statusEl.className = 'badge ' + (item.status_badge_class || 'text-bg-secondary');
            schoolEl.textContent = [item.school_name || '', item.school_type || ''].filter(Boolean).join(' | ') || '-';
            if (nextActionTitleEl) {
                nextActionTitleEl.textContent = item.next_action_title || '-';
            }
            if (nextActionDetailEl) {
                const nextActionDetail = String(item.next_action_detail || '').trim();
                nextActionDetailEl.textContent = nextActionDetail || 'No additional action details.';
                nextActionDetailEl.classList.toggle('d-none', nextActionDetail === '');
            }

            if (timelineEl) {
                timelineEl.innerHTML = '';
                const timeline = Array.isArray(item.timeline) ? item.timeline : [];
                timeline.forEach(function (step) {
                    const stepWrap = document.createElement('div');
                    stepWrap.className = 'application-step application-step-' + String(step.state || 'upcoming');

                    const stepDot = document.createElement('div');
                    stepDot.className = 'application-step-dot';
                    stepWrap.appendChild(stepDot);

                    const stepLabel = document.createElement('div');
                    stepLabel.className = 'application-step-label';
                    stepLabel.textContent = String(step.short_label || step.label || 'Step');
                    stepWrap.appendChild(stepLabel);

                    timelineEl.appendChild(stepWrap);
                });
            }

            const reviewNotes = String(item.review_notes || '').trim();
            reviewNotesRowEl.classList.toggle('d-none', reviewNotes === '');
            reviewNotesEl.textContent = reviewNotes || '-';

            const interviewSchedule = String(item.interview_schedule || '').trim();
            interviewScheduleRowEl.classList.toggle('d-none', interviewSchedule === '');
            interviewScheduleEl.textContent = interviewSchedule || '-';

            const interviewLocation = String(item.interview_location || '').trim();
            interviewLocationRowEl.classList.toggle('d-none', interviewLocation === '');
            interviewLocationEl.textContent = interviewLocation || '-';

            const soaDeadline = String(item.soa_deadline || '').trim();
            soaDeadlineRowEl.classList.toggle('d-none', soaDeadline === '');
            soaDeadlineEl.textContent = soaDeadline || '-';

            const soaSubmitted = String(item.soa_submitted || '').trim();
            soaSubmittedRowEl.classList.toggle('d-none', soaSubmitted === '');
            soaSubmittedEl.textContent = soaSubmitted || '-';

            docsListEl.innerHTML = '';
            const docs = Array.isArray(item.documents) ? item.documents : [];
            docsEmptyEl.classList.toggle('d-none', docs.length > 0);
            if (docsCountEl) {
                docsCountEl.textContent = docs.length + ' file' + (docs.length === 1 ? '' : 's');
            }
            docs.forEach(function (doc) {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap';

                const name = document.createElement('span');
                name.className = 'small';
                const verificationStatus = String(doc.verification_status || '').trim();
                const statusSuffix = verificationStatus !== '' ? ' [' + verificationStatus.toUpperCase() + ']' : '';
                name.textContent = String(doc.name || 'Requirement') + statusSuffix;
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

        document.querySelectorAll('.js-open-application-modal').forEach(function (button) {
            button.addEventListener('click', function () {
                const appId = button.getAttribute('data-app-id') || '';
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
