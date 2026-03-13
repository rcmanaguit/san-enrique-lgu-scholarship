<?php
declare(strict_types=1);

require_once __DIR__ . '/my_application_data.php';

function my_application_project_root(): string
{
    return dirname(__DIR__, 2);
}

function my_application_uploads_documents_dir(): string
{
    return my_application_project_root() . '/uploads/documents';
}

function my_application_relative_path(string $absolutePath): string
{
    return str_replace(
        str_replace('\\', '/', my_application_project_root()) . '/',
        '',
        str_replace('\\', '/', $absolutePath)
    );
}

function my_application_absolute_path(string $relativePath): string
{
    return my_application_project_root() . '/' . ltrim($relativePath, '/');
}

function my_application_handle_post_request(mysqli $conn, array $user, string $periodScope): void
{
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
        my_application_handle_resubmit_documents($conn, $user, $periodScope);
    }
    if ($action === 'submit_soa_digital') {
        my_application_handle_submit_soa_digital($conn, $user, $periodScope);
    }
}

function my_application_handle_resubmit_documents(mysqli $conn, array $user, string $periodScope): void
{
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
            $uploaded = upload_any_file($fieldName, my_application_uploads_documents_dir());
            if ($uploaded === null) {
                continue;
            }

            $oldPath = trim((string) ($doc['file_path'] ?? ''));
            $oldAbsolute = my_application_absolute_path($oldPath);
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

            $newRelativePath = my_application_relative_path((string) $uploaded['file_path']);
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

function my_application_handle_submit_soa_digital(mysqli $conn, array $user, string $periodScope): void
{
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

    $uploaded = upload_any_file('soa_document', my_application_uploads_documents_dir());
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

    $newRelativePath = my_application_relative_path((string) ($uploaded['file_path'] ?? ''));
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
            $oldAbsolute = my_application_absolute_path($oldPath);
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
