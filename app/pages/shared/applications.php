<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Application Queue';
$bodyClass = 'application-review-board-page';
$applications = [];
$documentsByApplication = [];
$queueFilter = trim((string) ($_GET['queue'] ?? 'under_review'));
$rowsPerPage = (int) ($_GET['rows'] ?? 20);
$currentPage = (int) ($_GET['page'] ?? 1);
$periodScope = trim((string) ($_GET['period_scope'] ?? 'active'));
$isAdmin = is_admin();
$periodIdFilter = 0;
$allowArchivedUpdates = $isAdmin && trim((string) ($_GET['allow_archived_updates'] ?? '')) === '1';
$allowedStatus = application_status_options();
$approvedPhaseStatuses = approved_phase_statuses();
$bulkStatusButtons = [];
$activePeriod = db_ready() ? current_active_application_period($conn) : null;
$openPeriod = db_ready() ? current_open_application_period($conn) : null;
$activePeriodLabel = format_application_period($activePeriod);
$isApplicantIntakeOpen = $openPeriod !== null
    && (int) ($openPeriod['id'] ?? 0) > 0
    && (int) ($openPeriod['id'] ?? 0) === (int) ($activePeriod['id'] ?? 0);
$hasApplicationPeriodColumn = db_ready() && table_column_exists($conn, 'applications', 'application_period_id');
$periodOptions = [];
$selectedPeriodForFilter = null;
$queueMap = [
    'under_review' => ['under_review'],
    'compliance' => ['needs_resubmission', 'for_soa'],
    'for_interview' => ['for_interview'],
    'approved_for_release' => ['approved_for_release'],
    'completed' => ['released'],
    'all' => [],
];
$statusToQueue = static function (string $status) use ($queueMap): string {
    foreach ($queueMap as $queueKey => $queueStatuses) {
        if ($queueKey === 'all') {
            continue;
        }
        if (in_array($status, $queueStatuses, true)) {
            return $queueKey;
        }
    }
    return 'all';
};
$queueCounts = array_fill_keys(array_keys($queueMap), 0);
$rowsPerPageOptions = [10, 20, 50];
if (!in_array($rowsPerPage, $rowsPerPageOptions, true)) {
    $rowsPerPage = 20;
}
if ($currentPage < 1) {
    $currentPage = 1;
}

if (!array_key_exists($queueFilter, $queueMap)) {
    $queueFilter = 'under_review';
}
$periodScope = normalize_period_scope($periodScope, 'active');
if ($periodScope !== 'active') {
    $periodScope = 'active';
}

$bulkStatusMap = [
    '__default__' => ['under_review'],
    'draft' => ['under_review'],
    'under_review' => ['for_interview', 'rejected'],
    'needs_resubmission' => ['under_review', 'rejected'],
    'for_interview' => ['for_soa'],
    'for_soa' => [],
    'approved_for_release' => ['released'],
    'released' => [],
    'rejected' => [],
];
$queueBulkStatusMap = [
    'under_review' => [],
    'compliance' => [],
    'for_interview' => ['for_soa'],
    'approved_for_release' => ['released'],
    'completed' => [],
    'all' => ['under_review'],
];
$statusActionLabels = [
    'under_review' => 'Mark Under Review',
    'for_interview' => 'Move to Interview',
    'needs_resubmission' => 'Request Resubmission',
    'for_soa' => 'Move to SOA Submission',
    'approved_for_release' => 'Approve for Payout',
    'released' => 'Mark Released',
    'rejected' => 'Reject',
    'draft' => 'Mark Draft',
];
$candidateBulkStatuses = [];
foreach ($queueBulkStatusMap as $queueStatuses) {
    foreach ((array) $queueStatuses as $candidateStatus) {
        if (!in_array($candidateStatus, $allowedStatus, true)) {
            continue;
        }
        if (in_array($candidateStatus, $candidateBulkStatuses, true)) {
            continue;
        }
        $candidateBulkStatuses[] = $candidateStatus;
    }
}
foreach ($candidateBulkStatuses as $candidateStatus) {
    if (!in_array($candidateStatus, $allowedStatus, true)) {
        continue;
    }
    $bulkStatusButtons[] = [
        'value' => $candidateStatus,
        'label' => $statusActionLabels[$candidateStatus] ?? ucwords(str_replace('_', ' ', $candidateStatus)),
    ];
}
$redirectQuery = [];
if ($queueFilter !== '' && $queueFilter !== 'under_review') {
    $redirectQuery['queue'] = $queueFilter;
}
$redirectQuery['rows'] = $rowsPerPage !== 20 ? $rowsPerPage : '';
$redirectQuery['page'] = $currentPage > 1 ? $currentPage : '';
$redirectQuery['period_scope'] = $periodScope;
$redirectQuery['period_id'] = $periodIdFilter > 0 ? $periodIdFilter : '';
if ($allowArchivedUpdates) {
    $redirectQuery['allow_archived_updates'] = '1';
}
$redirectQuery = array_filter($redirectQuery, static fn($value): bool => $value !== '');
$redirectUrl = 'applications.php' . ($redirectQuery ? '?' . http_build_query($redirectQuery) : '');
$redirectTargetFromPost = trim((string) ($_POST['redirect_to'] ?? ''));
if ($redirectTargetFromPost !== '' && preg_match('/^(applications|application-review)\.php(\?.*)?$/', $redirectTargetFromPost) === 1) {
    $redirectUrl = $redirectTargetFromPost;
}
$selectionEnabledQueues = ['for_interview', 'approved_for_release'];
$isSelectionEnabled = in_array($queueFilter, $selectionEnabledQueues, true);
$isArchivedApplication = static function (array $row) use ($activePeriod, $hasApplicationPeriodColumn): bool {
    return application_is_archived_for_active_period($row, $activePeriod, $hasApplicationPeriodColumn);
};
$resolveSmsTemplate = static function (mysqli $conn, string $templateName, string $fallbackBody): string {
    if (!table_exists($conn, 'sms_templates')) {
        return $fallbackBody;
    }
    $stmt = $conn->prepare(
        "SELECT template_body
         FROM sms_templates
         WHERE template_name = ?
           AND is_active = 1
         LIMIT 1"
    );
    if (!$stmt) {
        return $fallbackBody;
    }
    $stmt->bind_param('s', $templateName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $body = trim((string) ($row['template_body'] ?? ''));
    return $body !== '' ? $body : $fallbackBody;
};
$renderSmsTemplate = static function (string $templateBody, array $replacements): string {
    $message = $templateBody;
    foreach ($replacements as $placeholder => $value) {
        $message = str_replace((string) $placeholder, (string) $value, $message);
    }
    return trim($message);
};
$upsertSoaDeadlineAnnouncement = static function (mysqli $conn, string $deadline, ?array $periodContext = null): void {
    if (
        !db_ready()
        || !$conn instanceof mysqli
        || $conn->connect_errno
        || !table_exists($conn, 'announcements')
        || trim($deadline) === ''
    ) {
        return;
    }

    $periodLabel = '';
    if (is_array($periodContext)) {
        $periodLabel = trim(format_application_period($periodContext));
        if ($periodLabel === '') {
            $semesterText = trim((string) ($periodContext['semester'] ?? ''));
            $schoolYearText = trim((string) ($periodContext['academic_year'] ?? $periodContext['school_year'] ?? ''));
            $periodLabel = trim($semesterText . ' ' . $schoolYearText);
        }
    }

    $deadlineLabel = date('F d, Y', strtotime($deadline));
    $title = 'SOA Submission Deadline';
    if ($periodLabel !== '') {
        $title .= ' - ' . $periodLabel;
    }

    $content = 'The San Enrique LGU Scholarship Office announces that scholars/applicants who are required to submit their Original Student\'s Copy / Statement of Account (SOA) must do so on or before '
        . $deadlineLabel
        . ' at the Mayor\'s Office, San Enrique, Negros Occidental.'
        . "\n\n"
        . 'Please bring your complete SOA/Student\'s Copy and submit within the stated period for proper processing of your scholarship application.'
        . "\n\n"
        . 'For questions or clarification, please visit the Mayor\'s Office during working hours.';

    $currentUser = current_user();
    $currentUserId = (int) ($currentUser['id'] ?? 0);
    $currentUserRole = (string) ($currentUser['role'] ?? '');

    $existingId = 0;
    $lookupStmt = $conn->prepare("SELECT id FROM announcements WHERE title = ? LIMIT 1");
    if ($lookupStmt) {
        $lookupStmt->bind_param('s', $title);
        $lookupStmt->execute();
        $lookupRow = $lookupStmt->get_result()->fetch_assoc();
        $existingId = (int) ($lookupRow['id'] ?? 0);
        $lookupStmt->close();
    }

    if ($existingId > 0) {
        $updateStmt = $conn->prepare("UPDATE announcements SET content = ?, is_active = 1 WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param('si', $content, $existingId);
            $updateStmt->execute();
            $updateStmt->close();
            audit_log(
                $conn,
                'announcement_updated',
                $currentUserId > 0 ? $currentUserId : null,
                $currentUserRole !== '' ? $currentUserRole : null,
                'announcement',
                (string) $existingId,
                'SOA deadline announcement updated automatically.',
                [
                    'title' => $title,
                    'deadline' => $deadline,
                    'period_label' => $periodLabel,
                    'source' => 'soa_deadline_auto',
                ]
            );
        }
        return;
    }

    $isActive = 1;
    $createdBy = $currentUserId > 0 ? $currentUserId : null;
    $insertStmt = $conn->prepare(
        "INSERT INTO announcements (title, content, is_active, created_by) VALUES (?, ?, ?, ?)"
    );
    if ($insertStmt) {
        $insertStmt->bind_param('ssii', $title, $content, $isActive, $createdBy);
        $insertStmt->execute();
        $announcementId = (int) $insertStmt->insert_id;
        $insertStmt->close();
        audit_log(
            $conn,
            'announcement_created',
            $currentUserId > 0 ? $currentUserId : null,
            $currentUserRole !== '' ? $currentUserRole : null,
            'announcement',
            (string) $announcementId,
            'SOA deadline announcement created automatically.',
            [
                'title' => $title,
                'deadline' => $deadline,
                'period_label' => $periodLabel,
                'source' => 'soa_deadline_auto',
            ]
        );
    }
};
$statusSmsTemplateConfig = [
    'under_review' => [
        'template' => 'Application Under Review',
        'fallback' => 'San Enrique LGU Scholarship: Application [Application No] is currently under review.',
    ],
    'for_interview' => [
        'template' => 'Documents Verified',
        'fallback' => 'San Enrique LGU Scholarship: Application [Application No] documents have been verified. Please wait for further updates.',
    ],
    'for_soa' => [
        'template' => 'SOA Submission Required',
        'fallback' => 'San Enrique LGU Scholarship: Please submit the SOA for application [Application No] on or before [Deadline].',
    ],
    'approved_for_release' => [
        'template' => 'Approved for Release',
        'fallback' => 'San Enrique LGU Scholarship: Application [Application No] has completed interview and SOA review and is approved for release.',
    ],
    'released' => [
        'template' => 'Payout Released',
        'fallback' => 'San Enrique LGU Scholarship: Payout has been released for application [Application No].',
    ],
    'rejected' => [
        'template' => 'Application Not Approved',
        'fallback' => 'San Enrique LGU Scholarship: Application [Application No] was not approved.',
    ],
];
$buildStatusSmsMessage = static function (string $newStatus, array $current, ?string $deadline = null) use ($conn, $statusSmsTemplateConfig, $resolveSmsTemplate, $renderSmsTemplate): string {
    $applicationNo = (string) ($current['application_no'] ?? '');
    $statusText = strtoupper(str_replace('_', ' ', $newStatus));
    $deadlineText = 'the announced deadline';
    if ($deadline !== null && trim($deadline) !== '') {
        $deadlineText = date('M d, Y', strtotime($deadline));
    }

    $config = $statusSmsTemplateConfig[$newStatus] ?? null;
    if (is_array($config)) {
        $templateBody = $resolveSmsTemplate(
            $conn,
            (string) ($config['template'] ?? ''),
            (string) ($config['fallback'] ?? '')
        );
        return $renderSmsTemplate($templateBody, [
            '[Application No]' => $applicationNo,
            '[Status]' => $statusText,
            '[Deadline]' => $deadlineText,
        ]);
    }

    return 'San Enrique LGU Scholarship: Application ' . $applicationNo . ' has been updated.';
};
$statusNotificationConfig = [
    'under_review' => 'Application [Application No] under review.',
    'for_interview' => 'Application [Application No] documents have been verified. Please wait for further updates.',
    'for_soa' => 'Submit SOA for application [Application No] by [Deadline].',
    'approved_for_release' => 'Application [Application No] approved for release.',
    'released' => 'Payout released for application [Application No].',
    'rejected' => 'Application [Application No] not approved.',
];
$buildStatusNotificationMessage = static function (string $newStatus, array $current, ?string $deadline = null) use ($statusNotificationConfig): string {
    $applicationNo = (string) ($current['application_no'] ?? '');
    $template = (string) ($statusNotificationConfig[$newStatus] ?? 'Application [Application No] updated.');
    $deadlineText = $deadline !== null && trim($deadline) !== '' ? date('M d, Y', strtotime($deadline)) : 'the announced deadline';
    return strtr($template, [
        '[Application No]' => $applicationNo,
        '[Deadline]' => $deadlineText,
    ]);
};

if (is_post() && db_ready()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
    } else {
        $action = trim((string) ($_POST['action'] ?? 'update_status'));

        if ($action === 'review_documents') {
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            $reviewNotes = trim((string) ($_POST['review_notes'] ?? ''));
            $verifiedDocRaw = $_POST['doc_verified'] ?? [];
            if (!is_array($verifiedDocRaw)) {
                $verifiedDocRaw = [];
            }
            $verifiedDocIds = array_values(array_unique(array_filter(array_map('intval', $verifiedDocRaw), static function ($id): bool {
                return $id > 0;
            })));

            if ($applicationId <= 0) {
                set_flash('danger', 'Invalid application for document review.');
                redirect($redirectUrl);
            }

            $stmtCurrent = $conn->prepare(
                "SELECT a.application_no, a.status, a.application_period_id, a.semester, a.school_year, u.id AS user_id, u.phone
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
            if (!$allowArchivedUpdates && $isArchivedApplication($current)) {
                set_flash('warning', 'This application belongs to an archived period. Enable archived updates to modify it.');
                redirect($redirectUrl);
            }

            $currentStatus = (string) ($current['status'] ?? '');
            if (!in_array($currentStatus, ['under_review', 'needs_resubmission'], true)) {
                set_flash('danger', 'Document review action is only allowed for Under Review/Needs Resubmission status.');
                redirect($redirectUrl);
            }

            $stmtDocs = $conn->prepare(
                "SELECT id, requirement_name
                 FROM application_documents
                 WHERE application_id = ?
                 ORDER BY id ASC"
            );
            $stmtDocs->bind_param('i', $applicationId);
            $stmtDocs->execute();
            $docsResult = $stmtDocs->get_result();
            $docs = $docsResult instanceof mysqli_result ? $docsResult->fetch_all(MYSQLI_ASSOC) : [];
            $stmtDocs->close();

            if (!$docs) {
                set_flash('danger', 'No uploaded documents found for this application.');
                redirect($redirectUrl);
            }

            $missingDocuments = [];
            foreach ($docs as $doc) {
                $docId = (int) ($doc['id'] ?? 0);
                if ($docId <= 0) {
                    continue;
                }
                $isVerified = in_array($docId, $verifiedDocIds, true);
                $verificationStatus = $isVerified ? 'verified' : 'rejected';
                if (!$isVerified) {
                    $missingDocuments[] = trim((string) ($doc['requirement_name'] ?? ('Document #' . $docId)));
                }

                $stmtUpdateDoc = $conn->prepare(
                    "UPDATE application_documents
                     SET verification_status = ?, remarks = ?, uploaded_at = uploaded_at
                     WHERE id = ?
                     LIMIT 1"
                );
                if ($stmtUpdateDoc) {
                    $remark = $isVerified ? 'Verified during document review.' : 'Needs resubmission.';
                    $stmtUpdateDoc->bind_param('ssi', $verificationStatus, $remark, $docId);
                    $stmtUpdateDoc->execute();
                    $stmtUpdateDoc->close();
                }
            }

            $newStatus = count($missingDocuments) > 0 ? 'needs_resubmission' : 'for_interview';
            $stmtUpdateApp = $conn->prepare(
                "UPDATE applications
                 SET status = ?, review_notes = ?, updated_at = NOW()
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmtUpdateApp->bind_param('ssi', $newStatus, $reviewNotes, $applicationId);
            $stmtUpdateApp->execute();
            $stmtUpdateApp->close();

            $missingListText = $missingDocuments ? implode(', ', $missingDocuments) : 'None';
            if ($newStatus === 'needs_resubmission') {
                $message = 'San Enrique LGU Scholarship: Application [Application No] requires resubmission of the following: [Missing Documents].';
                $message = $renderSmsTemplate($message, [
                    '[Application No]' => (string) ($current['application_no'] ?? ''),
                    '[Missing Documents]' => $missingListText,
                ]);
                if ($reviewNotes !== '') {
                    $message .= ' Notes: ' . $reviewNotes;
                }
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'Document Resubmission Required',
                    'Application ' . (string) ($current['application_no'] ?? '') . ' needs resubmission: ' . $missingListText . '.',
                    'application_status',
                    'my-application.php',
                    (int) (current_user()['id'] ?? 0)
                );
            } else {
                $templateBody = $resolveSmsTemplate(
                    $conn,
                    'Documents Verified',
                    'San Enrique LGU Scholarship: Application [Application No] documents have been verified. Please wait for further updates.'
                );
                $message = $renderSmsTemplate($templateBody, [
                    '[Application No]' => (string) ($current['application_no'] ?? ''),
                ]);
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'Document Review Passed',
                    'Application ' . (string) ($current['application_no'] ?? '') . ' documents have been verified. Please wait for further updates.',
                    'application_status',
                    'my-application.php',
                    (int) (current_user()['id'] ?? 0)
                );
            }

            audit_log(
                $conn,
                'application_document_reviewed',
                null,
                null,
                'application',
                (string) $applicationId,
                'Document review completed with auto status routing.',
                [
                    'application_no' => (string) ($current['application_no'] ?? ''),
                    'status_before' => $currentStatus,
                    'status_after' => $newStatus,
                    'missing_documents' => $missingDocuments,
                ]
            );

            set_flash(
                'success',
                $newStatus === 'for_interview'
                    ? 'All documents verified. Application moved to For Interview.'
                    : 'Application moved to For Resubmission. Applicant notified via SMS.'
            );
            redirect($redirectUrl);
        }

        if ($action === 'bulk_update_status') {
            if (!$isAdmin) {
                set_flash('danger', 'Only admin can use bulk status update.');
                redirect($redirectUrl);
            }

            $newStatus = trim((string) ($_POST['status'] ?? ''));
            $soaDeadlineRaw = trim((string) ($_POST['soa_submission_deadline'] ?? ''));
            $soaDeadline = $soaDeadlineRaw !== '' ? $soaDeadlineRaw : null;
            $selectedIdsRaw = $_POST['application_ids'] ?? [];
            if (!is_array($selectedIdsRaw)) {
                $selectedIdsRaw = [];
            }
            $applicationIds = array_values(array_unique(array_filter(array_map('intval', $selectedIdsRaw), static function ($id): bool {
                return $id > 0;
            })));

            if ($soaDeadline !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $soaDeadline) !== 1) {
                set_flash('danger', 'Invalid SOA deadline format.');
                redirect($redirectUrl);
            }
            if ($soaDeadline !== null && !$isAdmin) {
                set_flash('danger', 'Only admin can set or extend SOA submission deadline.');
                redirect($redirectUrl);
            }
            if (!$applicationIds) {
                set_flash('danger', 'Select at least one application for bulk update.');
                redirect($redirectUrl);
            }
            if (!in_array($newStatus, $allowedStatus, true)) {
                set_flash('danger', 'Invalid target status for bulk update.');
                redirect($redirectUrl);
            }

            $updatedCount = 0;
            $skippedCount = 0;
            foreach ($applicationIds as $applicationId) {
                $stmtCurrent = $conn->prepare(
                    "SELECT a.application_no, a.status, a.soa_submission_deadline, a.soa_submitted_at, a.interview_date, a.interview_location,
                            a.application_period_id, a.semester, a.school_year, u.id AS user_id, u.phone
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
                    $skippedCount++;
                    continue;
                }
                if (!$allowArchivedUpdates && $isArchivedApplication($current)) {
                    $skippedCount++;
                    continue;
                }

                $currentStatus = (string) ($current['status'] ?? '');
                if (in_array($currentStatus, ['under_review', 'needs_resubmission'], true)) {
                    $skippedCount++;
                    continue;
                }
                $hasInterviewSchedule = trim((string) ($current['interview_date'] ?? '')) !== ''
                    && trim((string) ($current['interview_location'] ?? '')) !== '';
                if ($newStatus === 'for_soa') {
                    if (!$isAdmin || !in_array($currentStatus, ['for_interview', 'for_soa'], true) || !$hasInterviewSchedule) {
                        $skippedCount++;
                        continue;
                    }
                }
                if ($newStatus === 'approved_for_release' && $currentStatus !== 'approved_for_release') {
                    $skippedCount++;
                    continue;
                }
                if ($newStatus === 'released' && $currentStatus !== 'approved_for_release') {
                    $skippedCount++;
                    continue;
                }
                $currentDeadline = trim((string) ($current['soa_submission_deadline'] ?? ''));
                if ($newStatus === 'approved_for_release' && $currentDeadline === '') {
                    $skippedCount++;
                    continue;
                }

                $deadlineToSave = $currentDeadline !== '' ? $currentDeadline : null;
                if ($isAdmin && $soaDeadline !== null) {
                    $deadlineToSave = $soaDeadline;
                }
                if ($newStatus === 'for_soa' && $deadlineToSave === null) {
                    $skippedCount++;
                    continue;
                }

                $currentSubmittedAt = trim((string) ($current['soa_submitted_at'] ?? ''));
                $soaSubmittedAt = $currentSubmittedAt !== '' ? $currentSubmittedAt : null;
                if ($newStatus === 'for_soa') {
                    $soaSubmittedAt = null;
                } elseif ($newStatus === 'approved_for_release' && $soaSubmittedAt === null) {
                    $soaSubmittedAt = date('Y-m-d H:i:s');
                }

                $stmt = $conn->prepare(
                    "UPDATE applications
                     SET status = ?, soa_submission_deadline = ?, soa_submitted_at = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param('sssi', $newStatus, $deadlineToSave, $soaSubmittedAt, $applicationId);
                $stmt->execute();
                $stmt->close();

                $statusChanged = $currentStatus !== $newStatus;
                $deadlineChanged = $currentDeadline !== (string) ($deadlineToSave ?? '');
                if ($statusChanged || $deadlineChanged) {
                    if ($statusChanged) {
                        $message = $buildStatusSmsMessage($newStatus, $current, $deadlineToSave);
                    } else {
                        $message = 'San Enrique LGU Scholarship: SOA/Student\'s Copy deadline for application ' . $current['application_no'] . ' has been updated.';
                    }

                    if ($newStatus === 'for_soa' && $deadlineToSave !== null) {
                        $message .= ' Deadline: ' . date('M d, Y', strtotime((string) $deadlineToSave))
                            . '. Please submit your SOA/Student\'s Copy at the Mayor\'s Office.';
                    }
                    sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');

                    $notificationTitle = $statusChanged ? 'Application Status Updated' : 'SOA Deadline Updated';
                    $notificationMessage = $statusChanged
                        ? $buildStatusNotificationMessage($newStatus, $current, $deadlineToSave)
                        : 'SOA/Student\'s Copy deadline for application ' . (string) ($current['application_no'] ?? '') . ' has been updated.';
                    if ($newStatus === 'for_soa' && $deadlineToSave !== null) {
                        $notificationMessage .= ' Deadline: ' . date('M d, Y', strtotime((string) $deadlineToSave)) . '. Please submit your SOA/Student\'s Copy at the Mayor\'s Office.';
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
                }

                audit_log(
                    $conn,
                    'application_status_bulk_updated',
                    null,
                    null,
                    'application',
                    (string) $applicationId,
                    'Application status updated via bulk action.',
                    [
                        'application_no' => (string) ($current['application_no'] ?? ''),
                        'previous_status' => $currentStatus,
                        'new_status' => $newStatus,
                        'deadline' => $deadlineToSave,
                    ]
                );

                $updatedCount++;
            }

            if ($updatedCount > 0) {
                $message = 'Bulk status update complete. Updated ' . $updatedCount . ' application(s)';
                if ($skippedCount > 0) {
                    $message .= ', skipped ' . $skippedCount . '.';
                    if ($newStatus === 'for_soa') {
                        $message .= ' Move to SOA requires interview date/time and location to be set first.';
                    }
                } else {
                    $message .= '.';
                }
                set_flash('success', $message);
            } else {
                set_flash('warning', 'No applications were updated. Check selected records and status rules.');
            }
            redirect($redirectUrl);
        }

        if ($action === 'bulk_schedule_interview') {
            $interviewDate = trim((string) ($_POST['interview_date'] ?? ''));
            $interviewTime = trim((string) ($_POST['interview_time'] ?? ''));
            $interviewLocation = trim((string) ($_POST['interview_location'] ?? 'Mayor\'s Office, San Enrique'));
            $selectedIdsRaw = $_POST['application_ids'] ?? [];
            if (!is_array($selectedIdsRaw)) {
                $selectedIdsRaw = [];
            }
            $applicationIds = array_values(array_unique(array_filter(array_map('intval', $selectedIdsRaw), static function ($id): bool {
                return $id > 0;
            })));

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $interviewDate) !== 1 || preg_match('/^\d{2}:\d{2}$/', $interviewTime) !== 1) {
                set_flash('danger', 'Provide a valid interview date and time.');
                redirect($redirectUrl);
            }
            if (!$applicationIds) {
                set_flash('danger', 'Select at least one application for interview scheduling.');
                redirect($redirectUrl);
            }
            if ($interviewLocation === '') {
                $interviewLocation = 'Mayor\'s Office, San Enrique';
            }

            $interviewDateTime = $interviewDate . ' ' . $interviewTime . ':00';
            $readableDate = date('M d, Y', strtotime($interviewDate));
            $readableTime = date('h:i A', strtotime($interviewDateTime));
            $templateBody = $resolveSmsTemplate(
                $conn,
                'Interview Notice',
                'San Enrique LGU Scholarship Notice: Your interview is scheduled on [Date] at [Time], [Location]. Please arrive early and bring your valid ID.'
            );

            $scheduledCount = 0;
            $skippedCount = 0;
            foreach ($applicationIds as $applicationId) {
                $stmtCurrent = $conn->prepare(
                    "SELECT a.application_no, a.status, a.application_period_id, a.semester, a.school_year, u.id AS user_id, u.phone
                     FROM applications a
                     INNER JOIN users u ON u.id = a.user_id
                     WHERE a.id = ?
                     LIMIT 1"
                );
                $stmtCurrent->bind_param('i', $applicationId);
                $stmtCurrent->execute();
                $current = $stmtCurrent->get_result()->fetch_assoc();
                $stmtCurrent->close();

                if (
                    !$current
                    || (!$allowArchivedUpdates && $isArchivedApplication($current))
                    || (string) ($current['status'] ?? '') !== 'for_interview'
                ) {
                    $skippedCount++;
                    continue;
                }

                $stmtUpdate = $conn->prepare(
                    "UPDATE applications
                     SET interview_date = ?, interview_location = ?, updated_at = NOW()
                     WHERE id = ?
                     LIMIT 1"
                );
                $stmtUpdate->bind_param('ssi', $interviewDateTime, $interviewLocation, $applicationId);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                $message = $renderSmsTemplate($templateBody, [
                    '[Date]' => $readableDate,
                    '[Time]' => $readableTime,
                    '[Location]' => $interviewLocation,
                ]);
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'Interview Schedule Updated',
                    'Your scholarship interview is scheduled on ' . $readableDate . ' at ' . $readableTime . ', ' . $interviewLocation . '.',
                    'interview',
                    'my-application.php',
                    (int) (current_user()['id'] ?? 0)
                );
                audit_log(
                    $conn,
                    'interview_schedule_updated',
                    null,
                    null,
                    'application',
                    (string) $applicationId,
                    'Interview schedule set via bulk action.',
                    [
                        'application_no' => (string) ($current['application_no'] ?? ''),
                        'interview_date' => $interviewDateTime,
                        'interview_location' => $interviewLocation,
                    ]
                );
                $scheduledCount++;
            }

            if ($scheduledCount > 0) {
                $message = 'Interview schedule saved for ' . $scheduledCount . ' application(s).';
                if ($skippedCount > 0) {
                    $message .= ' Skipped ' . $skippedCount . ' (not in For Interview status).';
                }
                set_flash('success', $message);
            } else {
                set_flash('warning', 'No applications were scheduled. Use this action only for For Interview records.');
            }
            redirect($redirectUrl);
        }

        if ($action === 'bulk_send_soa_reminder') {
            if (!$isAdmin) {
                set_flash('danger', 'Only admin can send SOA reminders.');
                redirect($redirectUrl);
            }

            $soaDeadlineInput = trim((string) ($_POST['soa_submission_deadline'] ?? ''));

            if ($soaDeadlineInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $soaDeadlineInput) !== 1) {
                set_flash('danger', 'Provide a valid SOA deadline date or leave it blank.');
                redirect($redirectUrl);
            }

            $globalDeadlineUpdated = 0;
            if ($soaDeadlineInput !== '') {
                if ($allowArchivedUpdates || $activePeriod) {
                    $whereSql = "status = 'for_soa'";
                    $bindTypes = 's';
                    $bindValues = [$soaDeadlineInput];
                    if (!$allowArchivedUpdates && $activePeriod) {
                        $activePeriodId = (int) ($activePeriod['id'] ?? 0);
                        $activeSemester = trim((string) ($activePeriod['semester'] ?? ''));
                        $activeSchoolYear = trim((string) ($activePeriod['academic_year'] ?? ''));
                        if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
                            $whereSql .= " AND application_period_id = ?";
                            $bindTypes .= 'i';
                            $bindValues[] = $activePeriodId;
                        } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
                            $whereSql .= " AND semester = ? AND school_year = ?";
                            $bindTypes .= 'ss';
                            $bindValues[] = $activeSemester;
                            $bindValues[] = $activeSchoolYear;
                        } else {
                            $whereSql .= " AND 1 = 0";
                        }
                    }

                    $stmtGlobalDeadline = $conn->prepare(
                        "UPDATE applications
                         SET soa_submission_deadline = ?,
                         updated_at = NOW()
                         WHERE " . $whereSql
                    );
                    if ($stmtGlobalDeadline) {
                        $bindArgs = [$bindTypes];
                        foreach ($bindValues as $index => $value) {
                            $bindArgs[] = &$bindValues[$index];
                        }
                        call_user_func_array([$stmtGlobalDeadline, 'bind_param'], $bindArgs);
                        $stmtGlobalDeadline->execute();
                        $globalDeadlineUpdated = max(0, (int) $stmtGlobalDeadline->affected_rows);
                        $stmtGlobalDeadline->close();
                    }
                }
            }

            $targetWhereSql = "a.status = 'for_soa'";
            if (!$allowArchivedUpdates && $activePeriod) {
                $activePeriodId = (int) ($activePeriod['id'] ?? 0);
                $activeSemester = trim((string) ($activePeriod['semester'] ?? ''));
                $activeSchoolYear = trim((string) ($activePeriod['academic_year'] ?? ''));
                if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
                    $targetWhereSql .= " AND a.application_period_id = " . $activePeriodId;
                } elseif ($activeSemester !== '' && $activeSchoolYear !== '') {
                    $targetWhereSql .= " AND a.semester = '" . $conn->real_escape_string($activeSemester) . "'";
                    $targetWhereSql .= " AND a.school_year = '" . $conn->real_escape_string($activeSchoolYear) . "'";
                } else {
                    $targetWhereSql .= " AND 1 = 0";
                }
            }

            $stmtTargets = $conn->prepare(
                "SELECT a.id, a.application_no, a.soa_submission_deadline, a.application_period_id, a.semester, a.school_year, u.id AS user_id, u.phone
                 FROM applications a
                 INNER JOIN users u ON u.id = a.user_id
                 WHERE {$targetWhereSql}"
            );
            $targets = [];
            if ($stmtTargets) {
                $stmtTargets->execute();
                $resultTargets = $stmtTargets->get_result();
                if ($resultTargets instanceof mysqli_result) {
                    $targets = $resultTargets->fetch_all(MYSQLI_ASSOC);
                }
                $stmtTargets->close();
            }

            $templateBody = $resolveSmsTemplate(
                $conn,
                'SOA / Student Copy Reminder',
                'San Enrique LGU Scholarship Reminder: Kindly submit your SOA/Student\'s Copy at the Mayor\'s Office on or before [Deadline]. If you have already submitted it, please disregard this message.'
            );
            $hasDisregard = stripos($templateBody, 'disregard') !== false;
            $disregardText = $hasDisregard ? '' : ' If you have already submitted it, please disregard this message.';

            $sentCount = 0;
            foreach ($targets as $current) {
                if (!$allowArchivedUpdates && $isArchivedApplication($current)) {
                    continue;
                }
                $deadlineRaw = trim((string) ($current['soa_submission_deadline'] ?? ''));
                $deadlineText = $deadlineRaw !== '' ? date('M d, Y', strtotime($deadlineRaw)) : 'the announced deadline';
                $message = $renderSmsTemplate($templateBody, [
                    '[Deadline]' => $deadlineText,
                ]) . $disregardText;

                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'SOA Reminder',
                    'Please submit your SOA/Student\'s Copy on or before ' . $deadlineText . '. If already submitted, disregard this reminder.',
                    'application_status',
                    'my-application.php',
                    (int) (current_user()['id'] ?? 0)
                );
                audit_log(
                    $conn,
                    'sms_bulk_sent',
                    null,
                    null,
                    'application',
                    (string) ((int) ($current['id'] ?? 0)),
                    'SOA reminder sent via bulk action.',
                    [
                        'application_no' => (string) ($current['application_no'] ?? ''),
                        'deadline' => $deadlineRaw !== '' ? $deadlineRaw : null,
                        'deadline_set_from_form' => $soaDeadlineInput !== '' ? $soaDeadlineInput : null,
                    ]
                );
                $sentCount++;
            }

            if ($sentCount > 0) {
                if ($soaDeadlineInput !== '') {
                    $upsertSoaDeadlineAnnouncement($conn, $soaDeadlineInput, $activePeriod);
                }
                $message = 'SOA reminder sent to all For SOA records (' . $sentCount . ').';
                if ($soaDeadlineInput !== '') {
                    $message .= ' Global SOA deadline set to ' . date('M d, Y', strtotime($soaDeadlineInput)) . '.';
                }
                set_flash('success', $message);
            } else {
                if ($soaDeadlineInput !== '') {
                    $upsertSoaDeadlineAnnouncement($conn, $soaDeadlineInput, $activePeriod);
                    set_flash('success', 'Global SOA deadline set, but no records are currently in For SOA status.');
                } else {
                    set_flash('warning', 'No reminders sent. No records are currently in For SOA status.');
                }
            }
            redirect($redirectUrl);
        }

        if ($action === 'bulk_set_soa_deadline') {
            if (!$isAdmin) {
                set_flash('danger', 'Only admin can set SOA deadline in bulk.');
                redirect($redirectUrl);
            }

            $soaDeadline = trim((string) ($_POST['soa_submission_deadline'] ?? ''));
            $selectedIdsRaw = $_POST['application_ids'] ?? [];
            if (!is_array($selectedIdsRaw)) {
                $selectedIdsRaw = [];
            }
            $applicationIds = array_values(array_unique(array_filter(array_map('intval', $selectedIdsRaw), static function ($id): bool {
                return $id > 0;
            })));

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $soaDeadline) !== 1) {
                set_flash('danger', 'Provide a valid SOA deadline date.');
                redirect($redirectUrl);
            }
            if (!$applicationIds) {
                set_flash('danger', 'Select at least one application for SOA deadline update.');
                redirect($redirectUrl);
            }

            $updatedCount = 0;
            $skippedCount = 0;
            foreach ($applicationIds as $applicationId) {
                $stmtCurrent = $conn->prepare(
                    "SELECT a.application_no, a.status, a.application_period_id, a.semester, a.school_year, u.id AS user_id, u.phone
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
                    $skippedCount++;
                    continue;
                }
                if (!$allowArchivedUpdates && $isArchivedApplication($current)) {
                    $skippedCount++;
                    continue;
                }

                $currentStatus = (string) ($current['status'] ?? '');
                if (!in_array($currentStatus, ['for_interview', 'for_soa'], true)) {
                    $skippedCount++;
                    continue;
                }

                $updatedStatus = $currentStatus === 'for_interview' ? 'for_soa' : $currentStatus;
                $stmtUpdate = $conn->prepare(
                    "UPDATE applications
                     SET status = ?, soa_submission_deadline = ?, soa_submitted_at = NULL, updated_at = NOW()
                     WHERE id = ?
                     LIMIT 1"
                );
                $stmtUpdate->bind_param('ssi', $updatedStatus, $soaDeadline, $applicationId);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                $deadlineLabel = date('M d, Y', strtotime($soaDeadline));
                $message = 'San Enrique LGU Scholarship: Application ' . (string) ($current['application_no'] ?? '')
                    . ' SOA/Student\'s Copy deadline is set to ' . $deadlineLabel
                    . '. Please submit at the Mayor\'s Office.';
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'SOA Submission Deadline Set',
                    'SOA/Student\'s Copy deadline is set to ' . $deadlineLabel . ' for application ' . (string) ($current['application_no'] ?? '') . '.',
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
                    'SOA deadline set via bulk action.',
                    [
                        'application_no' => (string) ($current['application_no'] ?? ''),
                        'status_before' => $currentStatus,
                        'status_after' => $updatedStatus,
                        'deadline' => $soaDeadline,
                    ]
                );
                $updatedCount++;
            }

            if ($updatedCount > 0) {
                $upsertSoaDeadlineAnnouncement($conn, $soaDeadline, $activePeriod);
                $message = 'SOA deadline updated for ' . $updatedCount . ' application(s).';
                if ($skippedCount > 0) {
                    $message .= ' Skipped ' . $skippedCount . '.';
                }
                set_flash('success', $message);
            } else {
                set_flash('warning', 'No SOA deadlines were updated. Select approved or For SOA Submission records.');
            }
            redirect($redirectUrl);
        }

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
                "SELECT a.application_no, a.status, a.soa_submitted_at, a.application_period_id, a.semester, a.school_year, u.id AS user_id, u.phone
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
            if (!$allowArchivedUpdates && $isArchivedApplication($current)) {
                set_flash('warning', 'This application belongs to an archived period. Enable archived updates to modify it.');
                redirect($redirectUrl);
            }
            if (!in_array((string) $current['status'], $approvedPhaseStatuses, true)) {
                set_flash('danger', 'SOA deadline can only be set after the application is approved.');
                redirect($redirectUrl);
            }

            $updatedStatus = (string) $current['status'];
            $soaSubmittedAt = (string) ($current['soa_submitted_at'] ?? '');
            if ($updatedStatus === 'for_interview') {
                $updatedStatus = 'for_soa';
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
                . ' SOA/Student\'s Copy submission deadline is set to '
                . date('M d, Y', strtotime($soaDeadline))
                . '. Please submit your SOA/Student\'s Copy at the Mayor\'s Office.';
            sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
            create_notification(
                $conn,
                (int) ($current['user_id'] ?? 0),
                'SOA Submission Deadline Set',
                'Application ' . (string) ($current['application_no'] ?? '') . ' deadline: ' . date('M d, Y', strtotime($soaDeadline)) . '. Please submit your SOA/Student\'s Copy at the Mayor\'s Office.',
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

            $announcementPeriodContext = $activePeriod;
            if (!is_array($announcementPeriodContext)) {
                $announcementPeriodContext = [
                    'semester' => (string) ($current['semester'] ?? ''),
                    'school_year' => (string) ($current['school_year'] ?? ''),
                ];
            }
            $upsertSoaDeadlineAnnouncement($conn, $soaDeadline, $announcementPeriodContext);

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
                "SELECT a.application_no, a.status, a.soa_submitted_at, a.application_period_id, a.semester, a.school_year, u.id AS user_id, u.phone
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
            if (!$allowArchivedUpdates && $isArchivedApplication($current)) {
                set_flash('warning', 'This application belongs to an archived period. Enable archived updates to modify it.');
                redirect($redirectUrl);
            }
            if ((string) $current['status'] !== 'for_soa') {
                set_flash('danger', 'Application is not currently waiting for SOA submission.');
                redirect($redirectUrl);
            }
            if (trim((string) ($current['soa_submission_deadline'] ?? '')) === '') {
                set_flash('danger', 'Set SOA deadline first before marking SOA submitted.');
                redirect($redirectUrl);
            }

            $soaSubmittedAt = trim((string) ($current['soa_submitted_at'] ?? ''));
            if ($soaSubmittedAt === '') {
                $soaSubmittedAt = date('Y-m-d H:i:s');
            }
            $newStatus = 'approved_for_release';
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
                    . ' SOA/Student\'s Copy has been received by the scholarship office.';
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'Approved for Release',
                    'Your application ' . (string) ($current['application_no'] ?? '') . ' has completed SOA review and is approved for release.',
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
                'Application moved to approved for release after SOA verification.',
                [
                    'application_no' => (string) ($current['application_no'] ?? ''),
                    'previous_status' => (string) ($current['status'] ?? ''),
                ]
            );

            set_flash('success', 'Application approved for release.');
            redirect($redirectUrl);
        }

        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $reviewNotes = trim((string) ($_POST['review_notes'] ?? ''));
        $soaDeadlineRaw = trim((string) ($_POST['soa_submission_deadline'] ?? ''));
        $soaDeadline = $soaDeadlineRaw !== '' ? $soaDeadlineRaw : null;
        $interviewDateRaw = trim((string) ($_POST['interview_date'] ?? ''));
        $interviewTimeRaw = trim((string) ($_POST['interview_time'] ?? ''));
        $interviewLocationRaw = trim((string) ($_POST['interview_location'] ?? ''));

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
            "SELECT a.application_no, a.status, a.soa_submission_deadline, a.soa_submitted_at, a.interview_date, a.interview_location,
                    a.application_period_id, a.semester, a.school_year, u.id AS user_id, u.phone
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
        if (!$allowArchivedUpdates && $isArchivedApplication($current)) {
            set_flash('warning', 'This application belongs to an archived period. Enable archived updates to modify it.');
            redirect($redirectUrl);
        }

        $currentStatus = (string) ($current['status'] ?? '');
        $currentInterviewDateRaw = trim((string) ($current['interview_date'] ?? ''));
        $currentInterviewLocation = trim((string) ($current['interview_location'] ?? ''));
        $interviewDateTimeToSave = $currentInterviewDateRaw !== '' ? $currentInterviewDateRaw : null;
        $interviewLocationToSave = $currentInterviewLocation !== '' ? $currentInterviewLocation : null;

        $hasSubmittedInterviewField = $interviewDateRaw !== '' || $interviewTimeRaw !== '' || $interviewLocationRaw !== '';
        if ($hasSubmittedInterviewField) {
            if (($interviewDateRaw === '') !== ($interviewTimeRaw === '')) {
                set_flash('danger', 'Provide both interview date and time.');
                redirect($redirectUrl);
            }
            if ($interviewDateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $interviewDateRaw) !== 1) {
                set_flash('danger', 'Provide a valid interview date.');
                redirect($redirectUrl);
            }
            if ($interviewTimeRaw !== '' && preg_match('/^\d{2}:\d{2}$/', $interviewTimeRaw) !== 1) {
                set_flash('danger', 'Provide a valid interview time.');
                redirect($redirectUrl);
            }
            if ($interviewDateRaw !== '' && $interviewTimeRaw !== '') {
                $interviewDateTimeToSave = $interviewDateRaw . ' ' . $interviewTimeRaw . ':00';
                $interviewLocationToSave = $interviewLocationRaw !== '' ? $interviewLocationRaw : 'Mayor\'s Office, San Enrique';
            }
        }

        $allowedTransitions = array_values(array_filter(
            $bulkStatusMap[$currentStatus] ?? [],
            static fn($status): bool => in_array((string) $status, $allowedStatus, true)
        ));
        if ($newStatus !== $currentStatus && !in_array($newStatus, $allowedTransitions, true)) {
            set_flash('danger', 'Invalid status transition for the current application state.');
            redirect($redirectUrl);
        }
        if ($newStatus === 'for_soa') {
            if (!$isAdmin) {
                set_flash('danger', 'Only admin can move application to SOA submission stage.');
                redirect($redirectUrl);
            }
            $hasInterviewSchedule = $interviewDateTimeToSave !== null
                && trim((string) $interviewDateTimeToSave) !== ''
                && $interviewLocationToSave !== null
                && trim((string) $interviewLocationToSave) !== '';
            if (!in_array($currentStatus, ['for_interview', 'for_soa'], true) || !$hasInterviewSchedule) {
                set_flash('danger', 'Only scheduled interview applications can be moved to SOA submission stage.');
                redirect($redirectUrl);
            }
        }
        if ($newStatus === 'approved_for_release' && $currentStatus !== 'approved_for_release') {
            set_flash('danger', 'Use Confirm SOA Received before approving this application for payout.');
            redirect($redirectUrl);
        }
        if ($newStatus === 'released' && $currentStatus !== 'approved_for_release') {
            set_flash('danger', 'Only Approved for Release applications can be marked as released.');
            redirect($redirectUrl);
        }
        if ($newStatus === 'approved_for_release' && trim((string) ($current['soa_submission_deadline'] ?? '')) === '') {
            set_flash('danger', 'Set SOA deadline first before approving this application for release.');
            redirect($redirectUrl);
        }

        $currentDeadline = trim((string) ($current['soa_submission_deadline'] ?? ''));
        $deadlineToSave = $currentDeadline !== '' ? $currentDeadline : null;
        if ($isAdmin && $soaDeadline !== null) {
            $deadlineToSave = $soaDeadline;
        }
        if ($newStatus === 'for_soa' && $deadlineToSave === null) {
            set_flash('danger', 'Set an SOA submission deadline before moving to SOA submission stage.');
            redirect($redirectUrl);
        }

        $currentSubmittedAt = trim((string) ($current['soa_submitted_at'] ?? ''));
        $soaSubmittedAt = $currentSubmittedAt !== '' ? $currentSubmittedAt : null;
        if ($newStatus === 'for_soa') {
            $soaSubmittedAt = null;
        } elseif ($newStatus === 'approved_for_release' && $soaSubmittedAt === null) {
            $soaSubmittedAt = date('Y-m-d H:i:s');
        }

        $stmt = $conn->prepare(
            "UPDATE applications
             SET status = ?, review_notes = ?, interview_date = ?, interview_location = ?, soa_submission_deadline = ?, soa_submitted_at = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ssssssi', $newStatus, $reviewNotes, $interviewDateTimeToSave, $interviewLocationToSave, $deadlineToSave, $soaSubmittedAt, $applicationId);
        $stmt->execute();
        $stmt->close();

        $statusChanged = $currentStatus !== $newStatus;
        $deadlineChanged = $currentDeadline !== (string) ($deadlineToSave ?? '');
        $scheduleChanged = $currentInterviewDateRaw !== (string) ($interviewDateTimeToSave ?? '')
            || $currentInterviewLocation !== (string) ($interviewLocationToSave ?? '');
        if ($statusChanged || $deadlineChanged || $scheduleChanged) {
            if ($statusChanged) {
                $message = $buildStatusSmsMessage($newStatus, $current, $deadlineToSave);
            } elseif ($scheduleChanged && $newStatus === 'for_interview' && $interviewDateTimeToSave !== null && $interviewLocationToSave !== null) {
                $interviewTemplateBody = $resolveSmsTemplate(
                    $conn,
                    'Interview Notice',
                    'San Enrique LGU Scholarship Notice: Your interview is scheduled on [Date] at [Time], [Location]. Please arrive early and bring your valid ID.'
                );
                $message = $renderSmsTemplate($interviewTemplateBody, [
                    '[Date]' => date('M d, Y', strtotime((string) $interviewDateTimeToSave)),
                    '[Time]' => date('h:i A', strtotime((string) $interviewDateTimeToSave)),
                    '[Location]' => (string) $interviewLocationToSave,
                ]);
            } else {
                $message = 'San Enrique LGU Scholarship: SOA/Student\'s Copy deadline for application ' . $current['application_no'] . ' has been updated.';
            }

            if ($newStatus === 'for_soa' && $deadlineToSave !== null) {
                $message .= ' Deadline: ' . date('M d, Y', strtotime($deadlineToSave))
                    . '. Please submit your SOA/Student\'s Copy at the Mayor\'s Office.';
            }
            sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');

            $notificationTitle = $statusChanged ? 'Application Status Updated' : ($scheduleChanged ? 'Interview Schedule Updated' : 'SOA Deadline Updated');
            $notificationMessage = $statusChanged
                ? $buildStatusNotificationMessage($newStatus, $current, $deadlineToSave)
                : ($scheduleChanged && $newStatus === 'for_interview' && $interviewDateTimeToSave !== null && $interviewLocationToSave !== null
                    ? 'Your scholarship interview is scheduled on ' . date('M d, Y', strtotime((string) $interviewDateTimeToSave)) . ' at ' . date('h:i A', strtotime((string) $interviewDateTimeToSave)) . ', ' . (string) $interviewLocationToSave . '.'
                    : 'SOA/Student\'s Copy deadline for application ' . (string) ($current['application_no'] ?? '') . ' has been updated.');
            if ($newStatus === 'for_soa' && $deadlineToSave !== null) {
                $notificationMessage .= ' Deadline: ' . date('M d, Y', strtotime((string) $deadlineToSave)) . '. Please submit your SOA/Student\'s Copy at the Mayor\'s Office.';
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
                    'schedule_changed' => $scheduleChanged,
                    'interview_date' => $interviewDateTimeToSave,
                    'interview_location' => $interviewLocationToSave,
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
    $whereClauses = [];
    $paramTypes = '';
    $paramValues = [];

    $activePeriodId = (int) ($activePeriod['id'] ?? 0);
    $activeSemester = trim((string) ($activePeriod['semester'] ?? ''));
    $activeSchoolYear = trim((string) ($activePeriod['academic_year'] ?? ''));
    if ($periodScope === 'active') {
        if ($activePeriod) {
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
        if ($activePeriod) {
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

    $baseSql = "SELECT a.id, a.application_no, a.application_period_id, a.applicant_type, a.school_name, a.school_type, a.semester, a.school_year, a.barangay,
                       a.status, a.review_notes, a.interview_date, a.interview_location, a.soa_submission_deadline, a.soa_submitted_at, a.updated_at,
                       u.first_name, u.last_name, u.email, u.phone
                FROM applications a
                INNER JOIN users u ON u.id = a.user_id";

    if ($whereClauses) {
        $baseSql .= " WHERE " . implode(' AND ', $whereClauses);
    }
    $baseSql .= " ORDER BY a.updated_at DESC";

    if ($paramTypes !== '') {
        $stmt = $conn->prepare($baseSql);
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
    } else {
        $result = $conn->query($baseSql);
        if ($result instanceof mysqli_result) {
            $applications = $result->fetch_all(MYSQLI_ASSOC);
        }
    }

    if ($applications && table_exists($conn, 'application_documents')) {
        $applicationIds = array_values(array_unique(array_filter(array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $applications), static fn($id): bool => $id > 0)));

        if ($applicationIds) {
            $idList = implode(',', array_map('intval', $applicationIds));
            $sqlDocs = "SELECT id, application_id, requirement_name, verification_status, file_path
                        FROM application_documents
                        WHERE application_id IN ({$idList})
                        ORDER BY application_id ASC, id ASC";
            $docsResult = $conn->query($sqlDocs);
            if ($docsResult instanceof mysqli_result) {
                while ($doc = $docsResult->fetch_assoc()) {
                    $appId = (int) ($doc['application_id'] ?? 0);
                    if ($appId <= 0) {
                        continue;
                    }
                    if (!isset($documentsByApplication[$appId])) {
                        $documentsByApplication[$appId] = [];
                    }
                    $documentsByApplication[$appId][] = $doc;
                }
            }
        }
    }
}

foreach ($applications as &$row) {
    $row['is_archived'] = $isArchivedApplication($row) ? 1 : 0;
    $rowDocuments = $documentsByApplication[(int) ($row['id'] ?? 0)] ?? [];
    $row['rejected_document_count'] = count(array_filter($rowDocuments, static fn(array $doc): bool => (string) ($doc['verification_status'] ?? '') === 'rejected'));
    $row['has_missing_documents'] = $row['rejected_document_count'] > 0 ? 'yes' : 'no';
    $row['interview_scheduled_flag'] = (
        trim((string) ($row['interview_date'] ?? '')) !== ''
        && trim((string) ($row['interview_location'] ?? '')) !== ''
    ) ? 'yes' : 'no';
    $row['period_label'] = trim((string) (($row['semester'] ?? '-') . ' / ' . ($row['school_year'] ?? '-')));
}
unset($row);

foreach ($applications as $row) {
    $rowStatus = trim((string) ($row['status'] ?? ''));
    if ($rowStatus === '') {
        continue;
    }
    $queueCounts['all']++;
    $queueKey = $statusToQueue($rowStatus);
    if (!isset($queueCounts[$queueKey])) {
        $queueCounts[$queueKey] = 0;
    }
    $queueCounts[$queueKey]++;
}

$tableApplications = $applications;
if ($queueFilter !== 'all') {
    $tableApplications = array_values(array_filter(
        $applications,
        static fn(array $row): bool => $statusToQueue((string) ($row['status'] ?? '')) === $queueFilter
    ));
}
$tableTotalRecords = count($tableApplications);
$tableTotalPages = max(1, (int) ceil($tableTotalRecords / $rowsPerPage));
if ($currentPage > $tableTotalPages) {
    $currentPage = $tableTotalPages;
}
$tableOffset = ($currentPage - 1) * $rowsPerPage;
$tableApplications = array_slice($tableApplications, $tableOffset, $rowsPerPage);

$buildQueuePageUrl = static function (int $page, ?int $rows = null) use ($queueFilter, $periodScope, $allowArchivedUpdates, $rowsPerPage): string {
    $query = [];
    if ($queueFilter !== '' && $queueFilter !== 'under_review') {
        $query['queue'] = $queueFilter;
    }
    $effectiveRows = $rows ?? $rowsPerPage;
    if ($effectiveRows !== 20) {
        $query['rows'] = $effectiveRows;
    }
    if ($page > 1) {
        $query['page'] = $page;
    }
    if ($periodScope !== '') {
        $query['period_scope'] = $periodScope;
    }
    if ($allowArchivedUpdates) {
        $query['allow_archived_updates'] = '1';
    }

    return 'applications.php' . ($query ? '?' . http_build_query($query) : '');
};

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .applications-hero {
        border: 1px solid rgba(13, 110, 253, 0.12);
        background: linear-gradient(140deg, #f6f9ff 0%, #ffffff 55%, #eef5ff 100%);
    }
    .applications-hero .hero-note {
        font-size: 0.82rem;
        color: #5c6f86;
    }
    .apps-grid-stats {
        display: grid;
        gap: 0.65rem;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
    .apps-grid-stats .stat-card {
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0.75rem;
        background: #fff;
        padding: 0.6rem 0.7rem;
    }
    .apps-grid-stats .stat-card.stat-card-btn {
        width: 100%;
        text-align: left;
        cursor: pointer;
        transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
    }
    .apps-grid-stats .stat-card.stat-card-btn:hover {
        border-color: rgba(13, 110, 253, 0.18);
    }
    .apps-grid-stats .stat-card.stat-card-btn.active {
        border-color: rgba(13, 110, 253, 0.45);
        background: linear-gradient(135deg, #0d6efd 0%, #2a7fff 100%);
        color: #fff;
        box-shadow: 0 14px 24px rgba(13, 110, 253, 0.16);
    }
    .apps-grid-stats .stat-card.stat-card-btn.active .stat-label {
        color: rgba(255, 255, 255, 0.85);
    }
    .apps-grid-stats .stat-card.stat-card-btn:focus-visible,
    .applications-table-wrap .table tbody tr.application-row-link:focus-visible {
        outline: 0;
        border-color: rgba(13, 110, 253, 0.45);
    }
    .apps-grid-stats .stat-label {
        display: block;
        font-size: 0.74rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #6c757d;
    }
    .apps-grid-stats .stat-value {
        font-size: 1.05rem;
        font-weight: 700;
    }
    .applications-table-wrap .table tbody tr.application-row-link {
        cursor: pointer;
        outline: 1px solid transparent;
        outline-offset: -1px;
    }
    .applications-table-wrap .table tbody tr.application-row-link:hover,
    .applications-table-wrap .table tbody tr.application-row-link:focus-visible {
        background-color: #fbfdff;
        outline-color: rgba(13, 110, 253, 0.18);
    }
    .apps-helper-text {
        font-size: 0.82rem;
        color: #6b7280;
    }
    .scope-pill-group {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
    }
    .scope-pill {
        border: 1px solid rgba(13, 110, 253, 0.2);
        border-radius: 999px;
        padding: 0.3rem 0.7rem;
        font-size: 0.78rem;
        text-decoration: none;
        color: #0d6efd;
        background: #fff;
    }
    .scope-pill.active {
        color: #fff;
        background: #0d6efd;
        border-color: #0d6efd;
    }
    .archive-policy-note {
        border: 1px dashed rgba(13, 110, 253, 0.35);
        background: rgba(13, 110, 253, 0.05);
        border-radius: 0.65rem;
        padding: 0.55rem 0.7rem;
        font-size: 0.82rem;
        color: #35506f;
    }
    .period-badge {
        font-size: 0.68rem;
        vertical-align: middle;
    }
    .review-board-shell {
        display: grid;
        gap: 1rem;
    }
    .review-board-panel {
        border: 1px solid rgba(33, 76, 108, 0.08);
        border-radius: 1rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(247, 250, 255, 0.96));
        box-shadow: 0 14px 28px rgba(33, 76, 108, 0.05);
        overflow: hidden;
    }
    .review-board-panel-head {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        align-items: flex-start;
        flex-wrap: wrap;
        padding: 0.95rem 1rem 0.75rem;
        border-bottom: 1px solid rgba(33, 76, 108, 0.08);
    }
    .review-board-panel-title {
        font-size: 0.98rem;
        font-weight: 700;
        color: #1f3f61;
        margin: 0;
    }
    .review-board-panel-note {
        margin: 0.2rem 0 0;
        font-size: 0.82rem;
        color: #6b7280;
    }
    .review-board-panel-body {
        padding: 1rem;
    }
    .review-board-summary {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: wrap;
        font-size: 0.82rem;
        color: #48627d;
    }
    .review-board-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border: 1px solid rgba(45, 143, 213, 0.18);
        background: rgba(231, 244, 255, 0.7);
        color: #225175;
        border-radius: 999px;
        padding: 0.28rem 0.68rem;
        font-size: 0.76rem;
        font-weight: 600;
    }
    .review-board-chip strong {
        font-weight: 700;
    }
    .review-board-chip [data-table-summary] {
        font-weight: 700;
        color: #1f4c72;
    }
    .review-board-actions {
        display: grid;
        gap: 0.85rem;
    }
    .review-board-action-block {
        border: 1px dashed rgba(45, 143, 213, 0.2);
        border-radius: 0.9rem;
        padding: 0.85rem 0.9rem;
        background: rgba(255, 255, 255, 0.82);
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 0.75rem;
        align-items: end;
    }
    .review-board-action-block > * {
        grid-column: 1 / -1;
    }
    .review-board-action-block .bulk-selection-inputs {
        display: none;
    }
    .review-board-action-block .form-label.form-label-sm {
        font-weight: 700;
        color: #214c6c;
    }
    .review-board-action-block--interview {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-end;
        gap: 0.75rem;
    }
    .review-board-action-block--interview > * {
        grid-column: auto;
        flex: 0 0 auto;
        min-width: 0;
    }
    .review-board-action-block--interview .bulk-selection-inputs {
        display: none;
    }
    .review-board-action-block--interview .review-board-action-date {
        width: 140px;
    }
    .review-board-action-block--interview .review-board-action-time {
        width: 112px;
    }
    .review-board-action-block--interview .review-board-action-location {
        flex: 1 1 auto;
        width: auto;
    }
    .review-board-action-block--interview .review-board-action-submit {
        width: 190px;
        display: flex;
        justify-content: flex-end;
        align-items: stretch;
        white-space: nowrap;
    }
    .review-board-action-block--interview .review-board-action-submit .btn {
        width: 100%;
        min-height: 42px;
    }
    .review-board-action-block--interview .form-label.form-label-sm {
        margin-bottom: 0.35rem;
    }
    .review-board-filters {
        display: grid;
        gap: 0.75rem;
    }
    .review-board-filter-bar {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .review-board-filter-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .review-board-filter-grid > div {
        grid-column: span 2;
    }
    .review-board-filter-grid > .is-status {
        grid-column: span 2;
    }
    .review-board-filter-grid > .is-search {
        grid-column: span 4;
    }
    .review-board-filter-grid > .is-rows,
    .review-board-filter-grid > .is-clear {
        grid-column: span 1;
    }
    .queue-table-caption {
        padding: 0.9rem 1rem 0.3rem;
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .queue-table-caption strong {
        color: #214c6c;
    }
    .queue-table-caption .text-muted {
        font-size: 0.82rem;
    }
    .applications-table-wrap .table thead th {
        font-size: 0.74rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #5d7288;
        background: rgba(247, 250, 255, 0.96);
        border-bottom-width: 1px;
    }
    .applications-table-wrap .table td {
        vertical-align: top;
        padding-top: 0.9rem;
        padding-bottom: 0.9rem;
    }
    .queue-app-code {
        font-size: 0.95rem;
        color: #1e4263;
        font-weight: 700;
    }
    .queue-app-meta,
    .queue-app-contact,
    .queue-status-meta,
    .queue-next-detail {
        font-size: 0.8rem;
        color: #6b7280;
    }
    .queue-person-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1f3f61;
    }
    .queue-next-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #163d5d;
        margin-bottom: 0.18rem;
    }
    .queue-updated {
        white-space: nowrap;
        font-size: 0.82rem;
        color: #5f7286;
        font-weight: 600;
    }
    @media (min-width: 768px) {
        .review-board-action-block > [class*="col-md-2"] {
            grid-column: span 2;
        }
        .review-board-action-block > [class*="col-md-3"] {
            grid-column: span 3;
        }
        .review-board-action-block > [class*="col-md-4"] {
            grid-column: span 4;
        }
        .review-board-action-block > [class*="col-md-6"] {
            grid-column: span 6;
        }
    }
    @media (max-width: 991px) {
        .apps-grid-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .review-board-action-block--interview {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .review-board-action-block--interview > * {
            width: auto;
        }
        .review-board-action-block--interview .review-board-action-submit {
            width: auto;
            grid-column: 1 / -1;
        }
        .review-board-filter-grid > div,
        .review-board-filter-grid > .is-search {
            grid-column: span 3;
        }
    }
    @media (max-width: 767px) {
        .applications-hero .card-body {
            padding: 0.95rem;
        }
        .applications-hero {
            border-radius: 0.9rem;
        }
        .apps-grid-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .apps-grid-stats .stat-card {
            min-height: 82px;
        }
        .review-board-shell {
            gap: 0.85rem;
        }
        .review-board-panel {
            border-radius: 0.9rem;
        }
        .review-board-panel-head {
            flex-direction: column;
            align-items: stretch;
        }
        .review-board-summary {
            width: 100%;
        }
        .review-board-chip {
            justify-content: center;
            flex: 1 1 auto;
        }
        .review-board-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .review-board-filter-grid > div,
        .review-board-filter-grid > .is-search,
        .review-board-filter-grid > .is-rows,
        .review-board-filter-grid > .is-clear {
            grid-column: span 1;
        }
        .review-board-panel-head,
        .review-board-panel-body,
        .queue-table-caption {
            padding-left: 0.85rem;
            padding-right: 0.85rem;
        }
        .review-board-filter-bar {
            flex-direction: column;
            align-items: flex-start;
        }
        .review-board-action-block--interview {
            display: grid;
            grid-template-columns: 1fr;
        }
        .queue-table-caption {
            padding-top: 0.75rem;
            padding-bottom: 0;
        }
        .applications-table-wrap .table tbody tr.application-row-link {
            position: relative;
            overflow: hidden;
        }
        .applications-table-wrap .table tbody tr.application-row-link::after {
            content: "Tap to review";
            position: absolute;
            top: 0.75rem;
            right: 0.85rem;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #2d6ea3;
        }
        .application-review-board-page .applications-table-wrap td:first-child:not([data-pick-col]) {
            padding-top: 0.7rem;
        }
        .application-review-board-page .applications-table-wrap td {
            padding-top: 0.32rem;
            padding-bottom: 0.32rem;
        }
        .application-review-board-page .applications-table-wrap td::before {
            content: attr(data-label);
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #678099;
            margin-bottom: 0.18rem;
        }
        .application-review-board-page .applications-table-wrap td[data-pick-col]::before {
            display: none;
        }
        .queue-app-code,
        .queue-person-name,
        .queue-next-title {
            font-size: 0.92rem;
        }
        .queue-updated {
            white-space: normal;
        }
    }
</style>

<div class="card card-soft shadow-sm mb-3 applications-hero">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h1 class="h4 m-0"><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Application Queue</h1>
            <span class="hero-note">Use one queue board for the current working period</span>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="scope-pill-group" aria-label="Record scope">
                <span class="scope-pill active">Active Period</span>
            </div>
            <div class="hero-note">
                <?php if ($activePeriod): ?>
                    Current period: <strong><?= e($activePeriodLabel) ?></strong>
                    | Submission: <strong><?= $isApplicantIntakeOpen ? 'Open' : 'Closed' ?></strong>
                <?php else: ?>
                    No current working period.
                <?php endif; ?>
            </div>
        </div>
        <div class="apps-grid-stats" id="applicationQueueTabs" role="tablist" aria-label="Application Queues">
            <button type="button" class="stat-card stat-card-btn<?= $queueFilter === 'all' ? ' active' : '' ?>" data-queue-tab="all">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?= number_format((int) ($queueCounts['all'] ?? 0)) ?></span>
            </button>
            <button type="button" class="stat-card stat-card-btn<?= $queueFilter === 'under_review' ? ' active' : '' ?>" data-queue-tab="under_review">
                <span class="stat-label">Review</span>
                <span class="stat-value"><?= number_format((int) ($queueCounts['under_review'] ?? 0)) ?></span>
            </button>
            <button type="button" class="stat-card stat-card-btn<?= $queueFilter === 'compliance' ? ' active' : '' ?>" data-queue-tab="compliance">
                <span class="stat-label">Compliance</span>
                <span class="stat-value"><?= number_format((int) ($queueCounts['compliance'] ?? 0)) ?></span>
            </button>
            <button type="button" class="stat-card stat-card-btn<?= $queueFilter === 'for_interview' ? ' active' : '' ?>" data-queue-tab="for_interview">
                <span class="stat-label">Interview</span>
                <span class="stat-value"><?= number_format((int) ($queueCounts['for_interview'] ?? 0)) ?></span>
            </button>
            <button type="button" class="stat-card stat-card-btn<?= $queueFilter === 'approved_for_release' ? ' active' : '' ?>" data-queue-tab="approved_for_release">
                <span class="stat-label">Release</span>
                <span class="stat-value"><?= number_format((int) ($queueCounts['approved_for_release'] ?? 0)) ?></span>
            </button>
            <button type="button" class="stat-card stat-card-btn<?= $queueFilter === 'completed' ? ' active' : '' ?>" data-queue-tab="completed">
                <span class="stat-label">Completed</span>
                <span class="stat-value"><?= number_format((int) ($queueCounts['completed'] ?? 0)) ?></span>
            </button>
        </div>
    </div>
</div>

<div class="review-board-shell">
<?php if (!$tableApplications): ?>
    <div class="card card-soft"><div class="card-body text-muted">No applications found.</div></div>
<?php else: ?>
    <div data-live-table data-disable-sort="1" data-detach-hidden-rows="1" class="review-board-panel">
        <div class="review-board-panel-head">
            <div>
                <h2 class="review-board-panel-title">Filter And Review</h2>
                <p class="review-board-panel-note">Move from queue summary to filters, then open a record to review details, communication, and history.</p>
            </div>
            <div class="review-board-summary">
                <span class="review-board-chip"><strong><?= e(application_staff_queue_label($queueFilter === '' ? 'all' : $queueFilter)) ?></strong></span>
                <span class="review-board-chip"><span data-table-summary></span></span>
            </div>
        </div>
        <div class="review-board-panel-body table-controls">
            <div class="review-board-filters">
            <div class="review-board-filter-grid">
                <div class="is-search">
                    <label class="form-label form-label-sm">Live Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search app no, name, school">
                </div>
                <div>
                    <label class="form-label form-label-sm">Applicant Type</label>
                    <select class="form-select form-select-sm" data-table-filter data-filter-key="applicant-type">
                        <option value="">All</option>
                        <option value="new">New</option>
                        <option value="renew">Renew</option>
                    </select>
                </div>
                <div>
                    <label class="form-label form-label-sm">Barangay</label>
                    <select class="form-select form-select-sm" data-table-filter data-filter-key="barangay">
                        <option value="">All</option>
                        <?php foreach (san_enrique_barangays() as $barangayOption): ?>
                            <option value="<?= e($barangayOption) ?>"><?= e($barangayOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label form-label-sm">Interview</label>
                    <select class="form-select form-select-sm" data-table-filter data-filter-key="interview-scheduled">
                        <option value="">All</option>
                        <option value="yes">Scheduled</option>
                        <option value="no">Not Scheduled</option>
                    </select>
                </div>
                <div class="is-rows">
                    <label class="form-label form-label-sm">Rows</label>
                    <select class="form-select form-select-sm" id="reviewBoardRowsPerPage">
                        <?php foreach ($rowsPerPageOptions as $rowsOption): ?>
                            <option value="<?= (int) $rowsOption ?>" <?= $rowsPerPage === $rowsOption ? 'selected' : '' ?>><?= (int) $rowsOption ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="is-clear d-grid">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="reviewBoardReset">Clear</button>
                </div>
            </div>
            <div class="review-board-filter-bar">
            <div class="apps-helper-text mt-2">Use filters to narrow the board, then open a record to review documents, decisions, communication, and history in one place.</div>
                <div class="page-legend"></div>
            </div>
            <input type="hidden" id="applicationLiveQueueFilter" data-table-filter data-filter-key="queue" value="<?= e($queueFilter === 'all' ? '' : $queueFilter) ?>">
        </div>
        </div>

        <div class="queue-table-caption">
            <div class="text-muted">Click any row to open the full review record. The queue is ordered for quick triage and next-step decisions.</div>
        </div>

        <?php if ($isAdmin): ?>
            <div class="review-board-panel-body review-board-actions pt-0">
                <form method="post" id="bulkStatusForm" class="row g-2 align-items-end" data-crud-modal="1" data-crud-title="Update Application Status?" data-crud-message="Update status for selected applications?" data-crud-confirm-text="Update Status" data-crud-kind="primary">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="bulk_update_status">
                    <div class="col-12 review-board-action-block">
                        <div class="d-flex flex-wrap align-items-center gap-3<?= $isSelectionEnabled ? '' : ' d-none' ?>" data-bulk-selection-ui="1">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="bulkSelectAllApplications">
                                <label class="form-check-label small" for="bulkSelectAllApplications">Select all visible applications on this page</label>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($bulkStatusButtons as $bulkButton): ?>
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-primary"
                                    name="status"
                                    value="<?= e((string) $bulkButton['value']) ?>"
                                    data-bulk-status-btn="1"
                                    data-status-label="<?= e((string) $bulkButton['label']) ?>"
                                >
                                    Move selected to <?= e(strtolower(application_status_label((string) $bulkButton['value']))) ?> (0)
                                </button>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div id="bulkStatusSelectionInputs" class="bulk-selection-inputs"></div>
                </form>

                <form method="post" id="bulkInterviewForm" class="row g-2 align-items-end d-none" data-bulk-special="for_interview" data-crud-modal="1" data-crud-title="Schedule Interviews?" data-crud-message="Set interview schedule for selected For Interview applications?" data-crud-confirm-text="Schedule Interviews" data-crud-kind="primary">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="bulk_schedule_interview">
                    <div class="review-board-action-block review-board-action-block--interview">
                        <div class="col-12 col-md-3 review-board-action-date">
                            <label class="form-label form-label-sm">Interview Date</label>
                            <input type="date" name="interview_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-md-2 review-board-action-time">
                            <label class="form-label form-label-sm">Interview Time</label>
                            <input type="time" name="interview_time" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-md-4 review-board-action-location">
                            <label class="form-label form-label-sm">Location</label>
                            <input type="text" name="interview_location" class="form-control form-control-sm" value="Mayor's Office, San Enrique" maxlength="180">
                        </div>
                        <div class="col-12 col-md-3 review-board-action-submit">
                            <button type="submit" class="btn btn-sm btn-primary" data-bulk-special-submit="for_interview">Schedule selected (0)</button>
                        </div>
                        <div class="bulk-selection-inputs"></div>
                    </div>
                </form>

                <form method="post" id="bulkSoaReminderForm" class="row g-2 align-items-end d-none" data-bulk-special="compliance" data-crud-modal="1" data-crud-title="Send SOA Reminders?" data-crud-message="Send SOA reminder SMS to all applicants currently waiting for SOA submission?" data-crud-confirm-text="Send Reminders" data-crud-kind="warning">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="bulk_send_soa_reminder">
                    <div class="review-board-action-block">
                        <div class="col-12 col-md-6">
                            <label class="form-label form-label-sm">SOA Deadline (Global for waiting SOA records)</label>
                            <input type="date" name="soa_submission_deadline" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="small text-muted">Reminder will be sent only to records currently waiting for SOA. If set, deadline is applied globally.</div>
                        </div>
                        <div class="col-12 col-md-3 d-grid">
                            <button type="submit" class="btn btn-sm btn-outline-warning" data-bulk-special-submit="compliance">Send SOA Reminder</button>
                        </div>
                        <div class="bulk-selection-inputs"></div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="table-responsive applications-table-wrap">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 44px;" class="<?= $isSelectionEnabled ? '' : 'd-none' ?>" data-pick-col="1">Pick</th>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Current Step</th>
                        <th>Next Action</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tableApplications as $row): ?>
                    <?php
                    $search = strtolower(implode(' ', [
                        $row['application_no'],
                        $row['first_name'],
                        $row['last_name'],
                        $row['school_name'],
                        $row['status'],
                    ]));
                    $rowStatusValue = (string) ($row['status'] ?? '');
                    $rowNextAction = application_next_action_summary($row, 'staff');
                    $reviewUrl = 'application-review.php?id=' . (int) $row['id'] . '&return_to=' . urlencode($redirectUrl);
                    ?>
                    <tr
                        class="application-row-link"
                        data-review-url="<?= e($reviewUrl) ?>"
                        tabindex="0"
                        data-search="<?= e($search) ?>"
                        data-filter="<?= e((string) $row['status']) ?>"
                        data-queue="<?= e($statusToQueue((string) ($row['status'] ?? ''))) ?>"
                        data-status="<?= e((string) ($row['status'] ?? '')) ?>"
                        data-applicant-type="<?= e((string) ($row['applicant_type'] ?? '')) ?>"
                        data-period="<?= e((string) ($row['period_label'] ?? '')) ?>"
                        data-barangay="<?= e((string) ($row['barangay'] ?? '')) ?>"
                        data-missing-docs="<?= e((string) ($row['has_missing_documents'] ?? 'no')) ?>"
                        data-interview-scheduled="<?= e((string) ($row['interview_scheduled_flag'] ?? 'no')) ?>"
                    >
                        <td class="<?= $isSelectionEnabled ? '' : 'd-none' ?>" data-pick-col="1" data-label="Pick">
                            <input type="checkbox" class="form-check-input bulk-application-checkbox" value="<?= (int) $row['id'] ?>" aria-label="Select application <?= e((string) $row['application_no']) ?>">
                        </td>
                        <td data-label="Application">
                            <div class="queue-app-code"><?= e((string) $row['application_no']) ?></div>
                            <?php if ((int) ($row['is_archived'] ?? 0) === 1): ?>
                                <span class="badge text-bg-secondary period-badge ms-1">Archived</span>
                            <?php else: ?>
                                <span class="badge text-bg-success period-badge ms-1">Active</span>
                            <?php endif; ?>
                            <div class="queue-app-meta mt-1">#<?= (int) $row['id'] ?> | <?= e((string) $row['semester']) ?> / <?= e((string) $row['school_year']) ?></div>
                        </td>
                        <td data-label="Applicant">
                            <div class="queue-person-name"><?= e((string) $row['last_name']) ?>, <?= e((string) $row['first_name']) ?></div>
                            <div class="queue-app-contact mt-1"><?= e((string) $row['email']) ?> | <?= e((string) $row['phone']) ?></div>
                        </td>
                        <td data-label="Current Step">
                            <span class="badge <?= status_badge_class($rowStatusValue) ?>">
                                <?= e(strtoupper(application_staff_status_label($rowStatusValue))) ?>
                            </span>
                            <div class="queue-status-meta mt-1"><?= e((string) ($rowNextAction['label'] ?? application_status_label($rowStatusValue))) ?></div>
                            <?php if ((string) $row['status'] === 'for_soa' && !empty($row['soa_submission_deadline'])): ?>
                                <div class="queue-status-meta mt-1">
                                    SOA Deadline: <?= date('M d, Y', strtotime((string) $row['soa_submission_deadline'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ((string) $row['status'] === 'for_interview'): ?>
                                <?php
                                $hasInterviewScheduleBadge = trim((string) ($row['interview_date'] ?? '')) !== ''
                                    && trim((string) ($row['interview_location'] ?? '')) !== '';
                                ?>
                                <?php if ($hasInterviewScheduleBadge): ?>
                                    <div class="queue-status-meta mt-1 text-success">
                                        Interview Scheduled:
                                        <?= date('M d, Y h:i A', strtotime((string) $row['interview_date'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="queue-status-meta mt-1 text-warning">Interview Not Scheduled</div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (in_array((string) $row['status'], ['approved_for_release', 'released'], true) && !empty($row['soa_submitted_at'])): ?>
                                <div class="queue-status-meta mt-1">
                                    SOA Received: <?= date('M d, Y h:i A', strtotime((string) $row['soa_submitted_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Next Action">
                            <div class="queue-next-title"><?= e((string) ($rowNextAction['title'] ?? 'Review this application.')) ?></div>
                            <?php if (trim((string) ($rowNextAction['detail'] ?? '')) !== ''): ?>
                                <div class="queue-next-detail mt-1"><?= e((string) ($rowNextAction['detail'] ?? '')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Updated"><span class="queue-updated"><?= date('M d, Y h:i A', strtotime((string) $row['updated_at'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-body border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-muted">
                <?php if ($tableTotalRecords > 0): ?>
                    Showing <?= number_format($tableOffset + 1) ?>-<?= number_format(min($tableOffset + count($tableApplications), $tableTotalRecords)) ?> of <?= number_format($tableTotalRecords) ?> record(s)
                <?php else: ?>
                    No records found
                <?php endif; ?>
            </div>
            <?php if ($tableTotalPages > 1): ?>
                <div class="d-flex gap-2">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= e($buildQueuePageUrl($currentPage - 1, $rowsPerPage)) ?>" class="pager-button text-decoration-none d-inline-flex align-items-center justify-content-center" aria-label="Previous page">
                            <i class="fa-solid fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    <?php for ($pageNumber = 1; $pageNumber <= $tableTotalPages; $pageNumber++): ?>
                        <a href="<?= e($buildQueuePageUrl($pageNumber, $rowsPerPage)) ?>" class="pager-button text-decoration-none d-inline-flex align-items-center justify-content-center<?= $pageNumber === $currentPage ? ' active' : '' ?>">
                            <?= (int) $pageNumber ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($currentPage < $tableTotalPages): ?>
                        <a href="<?= e($buildQueuePageUrl($currentPage + 1, $rowsPerPage)) ?>" class="pager-button text-decoration-none d-inline-flex align-items-center justify-content-center" aria-label="Next page">
                            <i class="fa-solid fa-angle-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('bulkSelectAllApplications');
    const selectionInputsWrap = document.getElementById('bulkStatusSelectionInputs');
    const bulkForm = document.getElementById('bulkStatusForm');
    const bulkStatusButtons = Array.from(document.querySelectorAll('[data-bulk-status-btn]'));
    const liveQueueFilter = document.getElementById('applicationLiveQueueFilter');
    const queueTabs = Array.from(document.querySelectorAll('[data-queue-tab]'));
    const bulkSpecialSections = Array.from(document.querySelectorAll('[data-bulk-special]'));
    const bulkSpecialSubmitButtons = Array.from(document.querySelectorAll('[data-bulk-special-submit]'));
    const statusFilterWrap = document.getElementById('reviewBoardStatusFilterWrap');
    const statusFilter = document.getElementById('reviewBoardStatusFilter');
    const rowsPerPageSelect = document.getElementById('reviewBoardRowsPerPage');

    const bulkStatusMap = {
        "__default__": ["under_review"],
        "draft": ["under_review"],
        "under_review": ["for_interview", "rejected"],
        "needs_resubmission": ["under_review", "rejected"],
        "for_interview": ["for_soa"],
        "for_soa": [],
        "approved_for_release": ["released"],
        "released": [],
        "rejected": []
    };

    const statusLabelMap = {
        "draft": "Mark Draft",
        "under_review": "Mark Under Review",
        "needs_resubmission": "Request Resubmission",
        "for_interview": "Move to Interview",
        "for_soa": "Move to SOA Submission",
        "approved_for_release": "Approve for Payout",
        "released": "Mark Released",
        "rejected": "Reject"
    };
    const bulkActionTextMap = {
        "under_review": "Move selected to under review",
        "for_interview": "Move selected to interview",
        "for_soa": "Move selected to SOA submission",
        "approved_for_release": "Move selected to approved for payout",
        "released": "Move selected to released",
        "rejected": "Move selected to rejected"
    };

    function getRowCheckboxes(visibleOnly = true) {
        const all = Array.from(document.querySelectorAll('.bulk-application-checkbox'));
        if (!visibleOnly) {
            return all;
        }
        return all.filter(function (checkbox) {
            const row = checkbox.closest('tr');
            return !!row && !row.classList.contains('d-none');
        });
    }

    function renderSelectionInputs() {
        if (!selectionInputsWrap && document.querySelectorAll('.bulk-selection-inputs').length === 0) {
            return;
        }
        const selectedIds = getRowCheckboxes()
            .filter(function (checkbox) { return checkbox.checked; })
            .map(function (checkbox) { return String(checkbox.value || '').trim(); })
            .filter(function (value) { return value !== ''; });

        const markup = selectedIds
            .map(function (id) { return '<input type="hidden" name="application_ids[]" value="' + id + '">'; })
            .join('');
        if (selectionInputsWrap) {
            selectionInputsWrap.innerHTML = markup;
        }
        document.querySelectorAll('.bulk-selection-inputs').forEach(function (container) {
            container.innerHTML = markup;
        });
    }

    function syncSelectAllState() {
        if (!selectAllCheckbox) {
            return;
        }
        const rowCheckboxes = getRowCheckboxes();
        const allChecked = rowCheckboxes.length > 0 && rowCheckboxes.every(function (checkbox) { return checkbox.checked; });
        selectAllCheckbox.checked = allChecked;
    }

    function updateBulkButtonText() {
        const selectedCount = getRowCheckboxes().filter(function (checkbox) { return checkbox.checked; }).length;
        bulkStatusButtons.forEach(function (button) {
            if (button.classList.contains('d-none')) {
                return;
            }
            const statusValue = String(button.value || '').trim();
            const actionLabel = String(bulkActionTextMap[statusValue] || 'Move selected');
            button.textContent = actionLabel + ' (' + selectedCount + ')';
        });
        bulkSpecialSubmitButtons.forEach(function (button) {
            const parentForm = button.closest('form');
            if (!parentForm || parentForm.classList.contains('d-none')) {
                return;
            }
            const specialKey = String(button.getAttribute('data-bulk-special-submit') || '').trim();
            if (specialKey === 'for_interview') {
                button.textContent = 'Schedule selected (' + selectedCount + ')';
                return;
            }
            if (specialKey === 'compliance') {
                button.textContent = 'Send SOA Reminder';
            }
        });
    }

    function setActiveQueueTab(queueValue) {
        queueTabs.forEach(function (tabButton) {
            const tabValue = String(tabButton.getAttribute('data-queue-tab') || '').trim();
            tabButton.classList.toggle('active', tabValue === queueValue);
        });
    }

    function applyQueueFilter(queueValue) {
        if (!liveQueueFilter) {
            return;
        }
        liveQueueFilter.value = queueValue === 'all' ? '' : queueValue;
        const event = new Event('input', { bubbles: true });
        liveQueueFilter.dispatchEvent(event);

        // Drop selections from rows hidden by the active queue/search filter.
        getRowCheckboxes(false).forEach(function (checkbox) {
            const row = checkbox.closest('tr');
            if (row && row.classList.contains('d-none')) {
                checkbox.checked = false;
            }
        });

        setActiveQueueTab(queueValue);
        try {
            window.localStorage.setItem('applications.activeQueue', queueValue);
        } catch (error) {
            // Ignore storage errors.
        }
        syncSelectAllState();
        renderSelectionInputs();
        updateBulkButtonText();
    }

    function navigateToQueue(queueValue, resetPage = true) {
        const url = new URL(window.location.href);
        if (queueValue === 'under_review') {
            url.searchParams.delete('queue');
        } else {
            url.searchParams.set('queue', queueValue);
        }
        if (rowsPerPageSelect) {
            const rowsValue = String(rowsPerPageSelect.value || '').trim();
            if (rowsValue !== '' && rowsValue !== '20') {
                url.searchParams.set('rows', rowsValue);
            } else {
                url.searchParams.delete('rows');
            }
        }
        if (resetPage) {
            url.searchParams.delete('page');
        }
        window.location.href = url.toString();
    }

    function updateSpecialBulkSections() {
        const queueValue = liveQueueFilter ? String(liveQueueFilter.value || '').trim() : '';
        bulkSpecialSections.forEach(function (section) {
            const requiredQueue = String(section.getAttribute('data-bulk-special') || '').trim();
            const shouldShow = requiredQueue !== '' && requiredQueue === queueValue;
            section.classList.toggle('d-none', !shouldShow);
        });
    }

    function updateStatusFilterVisibility(queueValue) {
        const showStatusFilter = queueValue === '';
        if (statusFilterWrap) {
            statusFilterWrap.classList.toggle('d-none', !showStatusFilter);
        }
        if (!showStatusFilter && statusFilter) {
            statusFilter.value = '';
            statusFilter.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function setSelectionUiVisibility(queueValue) {
        const canSelect = queueValue === 'for_interview' || queueValue === 'approved_for_release';
        const selectionUi = Array.from(document.querySelectorAll('[data-bulk-selection-ui]'));
        const pickCells = Array.from(document.querySelectorAll('[data-pick-col]'));

        selectionUi.forEach(function (el) {
            el.classList.toggle('d-none', !canSelect);
        });
        pickCells.forEach(function (el) {
            el.classList.toggle('d-none', !canSelect);
        });

        if (!canSelect) {
            getRowCheckboxes(false).forEach(function (checkbox) {
                checkbox.checked = false;
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
            renderSelectionInputs();
        }
    }

    function applyBulkActionsForFilter() {
        const queueValue = liveQueueFilter ? String(liveQueueFilter.value || '').trim() : '';
        let actions = bulkStatusMap.__default__;
        if (queueValue === 'under_review') {
            actions = [];
        } else if (queueValue === 'compliance') {
            actions = [];
        } else if (queueValue === 'for_interview') {
            actions = ['for_soa'];
        } else if (queueValue === 'approved_for_release') {
            actions = ['released'];
        } else if (queueValue === 'completed') {
            actions = [];
        }

        setSelectionUiVisibility(queueValue);
        updateStatusFilterVisibility(queueValue);

        bulkStatusButtons.forEach(function (button, index) {
            const actionStatus = actions[index] || '';
            if (actionStatus === '') {
                button.classList.add('d-none');
                button.disabled = true;
                return;
            }

            const actionLabel = statusLabelMap[actionStatus] || actionStatus;
            button.classList.remove('d-none');
            button.disabled = false;
            button.value = actionStatus;
            button.setAttribute('data-status-label', actionLabel);
        });
        if (bulkForm) {
            bulkForm.classList.toggle('d-none', queueValue === '' || actions.length === 0);
        }
        updateBulkButtonText();
        updateSpecialBulkSections();
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const rowCheckboxes = getRowCheckboxes();
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            renderSelectionInputs();
            updateBulkButtonText();
        });
    }

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (target && target.classList && target.classList.contains('bulk-application-checkbox')) {
            syncSelectAllState();
            renderSelectionInputs();
            updateBulkButtonText();
        }
    });

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (target.closest('a, button, input, select, textarea, label, [data-queue-tab], [data-bulk-status-btn], [data-bulk-special-submit]')) {
            return;
        }
        const row = target.closest('tr.application-row-link');
        if (!row) {
            return;
        }
        const reviewUrl = String(row.getAttribute('data-review-url') || '').trim();
        if (reviewUrl !== '') {
            window.location.href = reviewUrl;
        }
    });

    document.addEventListener('keydown', function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const row = target.closest('tr.application-row-link');
        if (!row) {
            return;
        }
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        if (target.matches('input, select, textarea, button, a')) {
            return;
        }
        event.preventDefault();
        const reviewUrl = String(row.getAttribute('data-review-url') || '').trim();
        if (reviewUrl !== '') {
            window.location.href = reviewUrl;
        }
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (event) {
            const hasSelected = getRowCheckboxes().some(function (checkbox) { return checkbox.checked; });
            if (!hasSelected) {
                event.preventDefault();
                if (typeof window.showAlertModal === 'function') {
                    window.showAlertModal({
                        title: 'No Application Selected',
                        message: 'Select at least one application for bulk update.',
                        kind: 'warning',
                    });
                } else {
                    window.alert('Select at least one application for bulk update.');
                }
                return;
            }
            renderSelectionInputs();
        });
    }

    bulkSpecialSections.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const actionInput = form.querySelector('input[name="action"]');
            const actionValue = actionInput ? String(actionInput.value || '').trim() : '';
            if (actionValue === 'bulk_send_soa_reminder') {
                renderSelectionInputs();
                return;
            }
            const hasSelected = getRowCheckboxes().some(function (checkbox) { return checkbox.checked; });
            if (!hasSelected) {
                event.preventDefault();
                if (typeof window.showAlertModal === 'function') {
                    window.showAlertModal({
                        title: 'No Application Selected',
                        message: 'Select at least one application first.',
                        kind: 'warning',
                    });
                } else {
                    window.alert('Select at least one application first.');
                }
                return;
            }
            renderSelectionInputs();
        });
    });

    queueTabs.forEach(function (tabButton) {
        tabButton.addEventListener('click', function () {
            const queueValue = String(tabButton.getAttribute('data-queue-tab') || '').trim();
            if (queueValue === '') {
                return;
            }
            navigateToQueue(queueValue);
        });
    });

    if (!liveQueueFilter) {
        return;
    }

    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function () {
            const queueValue = liveQueueFilter ? String(liveQueueFilter.value || '').trim() : '';
            navigateToQueue(queueValue === '' ? 'all' : queueValue);
        });
    }

    const serverQueue = <?= json_encode($queueFilter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let initialQueue = serverQueue;
    if (initialQueue === '' || initialQueue === null) {
        initialQueue = 'under_review';
    }

    syncSelectAllState();
    renderSelectionInputs();
    applyQueueFilter(initialQueue);
    applyBulkActionsForFilter();
    updateBulkButtonText();
    const resetBtn = document.getElementById('reviewBoardReset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            document.querySelectorAll('[data-live-table] [data-table-filter]').forEach(function (control) {
                if (!(control instanceof HTMLElement)) {
                    return;
                }
                if (control instanceof HTMLSelectElement) {
                    control.value = '';
                    control.dispatchEvent(new Event('change', { bubbles: true }));
                    return;
                }
                if (control instanceof HTMLInputElement) {
                    control.value = '';
                    control.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            const searchInput = document.querySelector('[data-live-table] [data-table-search]');
            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

