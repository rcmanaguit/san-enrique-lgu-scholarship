<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Application Queue';
$applications = [];
$documentsByApplication = [];
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$queueFilter = trim((string) ($_GET['queue'] ?? 'under_review'));
$isAdmin = is_admin();
$allowedStatus = application_status_options();
$approvedPhaseStatuses = approved_phase_statuses();
$bulkStatusButtons = [];
$queueMap = [
    'under_review' => ['under_review', 'needs_resubmission'],
    'for_interview' => ['for_interview'],
    'for_soa' => ['interview_passed', 'for_soa'],
    'interview_passed' => ['soa_received', 'awaiting_payout'],
    'rejected' => ['rejected'],
    'completed' => ['disbursed'],
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

if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}
if (!array_key_exists($queueFilter, $queueMap)) {
    $queueFilter = 'under_review';
}
if ($statusFilter !== '') {
    $queueFilter = $statusToQueue($statusFilter);
}

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
$queueBulkStatusMap = [
    'under_review' => [],
    'for_interview' => ['interview_passed', 'rejected'],
    'for_soa' => ['soa_received'],
    'interview_passed' => ['disbursed'],
    'rejected' => [],
    'completed' => [],
    'all' => ['under_review'],
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
$secondaryStatusByStatus = [
    'for_soa' => 'interview_passed',
];
$bulkKey = $statusFilter !== '' ? $statusFilter : '__default__';
$candidateBulkStatuses = $bulkStatusMap[$bulkKey] ?? ($queueBulkStatusMap[$queueFilter] ?? $bulkStatusMap['__default__']);
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
if ($statusFilter !== '') {
    $redirectQuery['status'] = $statusFilter;
}
if ($queueFilter !== '' && $queueFilter !== 'under_review') {
    $redirectQuery['queue'] = $queueFilter;
}
$redirectUrl = 'applications.php' . ($redirectQuery ? '?' . http_build_query($redirectQuery) : '');
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
$statusSmsTemplateConfig = [
    'under_review' => [
        'template' => 'Application Under Review',
        'fallback' => 'San Enrique LGU Scholarship: Application [Application No] is currently under review.',
    ],
    'for_interview' => [
        'template' => 'Documents Verified',
        'fallback' => 'San Enrique LGU Scholarship: Application [Application No] is scheduled for interview.',
    ],
    'interview_passed' => [
        'template' => 'Interview Passed',
        'fallback' => 'San Enrique LGU Scholarship: Application [Application No] has passed the interview stage.',
    ],
    'for_soa' => [
        'template' => 'SOA Submission Required',
        'fallback' => 'San Enrique LGU Scholarship: Please submit the SOA for application [Application No] on or before [Deadline].',
    ],
    'soa_received' => [
        'template' => 'SOA Submitted Confirmation',
        'fallback' => 'San Enrique LGU Scholarship: The SOA for application [Application No] has been received.',
    ],
    'awaiting_payout' => [
        'template' => 'Awaiting Approval',
        'fallback' => 'San Enrique LGU Scholarship: Application [Application No] is awaiting final approval.',
    ],
    'disbursed' => [
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
    'for_interview' => 'Application [Application No] for interview.',
    'interview_passed' => 'Application [Application No] interview passed.',
    'for_soa' => 'Submit SOA for application [Application No] by [Deadline].',
    'soa_received' => 'SOA received for application [Application No].',
    'awaiting_payout' => 'Application [Application No] awaiting approval.',
    'disbursed' => 'Payout released for application [Application No].',
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
                "SELECT a.application_no, a.status, u.id AS user_id, u.phone
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
                $templateBody = $resolveSmsTemplate(
                    $conn,
                    'Document Resubmission Required',
                    'San Enrique LGU Scholarship: Application [Application No] requires resubmission of the following: [Missing Documents].'
                );
                $message = $renderSmsTemplate($templateBody, [
                    '[Application No]' => (string) ($current['application_no'] ?? ''),
                    '[Missing Documents]' => $missingListText,
                ]);
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
                    'San Enrique LGU Scholarship: Application [Application No] is scheduled for interview.'
                );
                $message = $renderSmsTemplate($templateBody, [
                    '[Application No]' => (string) ($current['application_no'] ?? ''),
                ]);
                sms_send((string) ($current['phone'] ?? ''), $message, (int) ($current['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($current['user_id'] ?? 0),
                    'Document Review Passed',
                    'Application ' . (string) ($current['application_no'] ?? '') . ' for interview.',
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
                    "SELECT a.application_no, a.status, a.soa_submission_deadline, a.soa_submitted_at, a.interview_date, a.interview_location, u.id AS user_id, u.phone
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

                $currentStatus = (string) ($current['status'] ?? '');
                if (in_array($currentStatus, ['under_review', 'needs_resubmission'], true)) {
                    $skippedCount++;
                    continue;
                }
                $hasInterviewSchedule = trim((string) ($current['interview_date'] ?? '')) !== ''
                    && trim((string) ($current['interview_location'] ?? '')) !== '';
                if ($newStatus === 'interview_passed' && ($currentStatus !== 'for_interview' || !$hasInterviewSchedule)) {
                    $skippedCount++;
                    continue;
                }
                if ($newStatus === 'for_soa') {
                    if (!$isAdmin || !in_array($currentStatus, ['interview_passed', 'for_soa'], true)) {
                        $skippedCount++;
                        continue;
                    }
                }
                if ($newStatus === 'soa_received' && !in_array($currentStatus, ['for_soa', 'soa_received'], true)) {
                    $skippedCount++;
                    continue;
                }
                if ($newStatus === 'disbursed' && !in_array($currentStatus, ['soa_received', 'awaiting_payout'], true)) {
                    $skippedCount++;
                    continue;
                }
                $currentDeadline = trim((string) ($current['soa_submission_deadline'] ?? ''));
                if ($newStatus === 'soa_received' && $currentDeadline === '') {
                    $skippedCount++;
                    continue;
                }
                if ($currentStatus === 'soa_received' && $newStatus === 'interview_passed') {
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
                } elseif ($newStatus === 'soa_received' && $soaSubmittedAt === null) {
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
                    if ($newStatus === 'interview_passed') {
                        $message .= ' Approve requires interview date/time and location to be set first.';
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
                    "SELECT a.application_no, a.status, u.id AS user_id, u.phone
                     FROM applications a
                     INNER JOIN users u ON u.id = a.user_id
                     WHERE a.id = ?
                     LIMIT 1"
                );
                $stmtCurrent->bind_param('i', $applicationId);
                $stmtCurrent->execute();
                $current = $stmtCurrent->get_result()->fetch_assoc();
                $stmtCurrent->close();

                if (!$current || (string) ($current['status'] ?? '') !== 'for_interview') {
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
                $stmtGlobalDeadline = $conn->prepare(
                    "UPDATE applications
                     SET status = CASE
                        WHEN status = 'interview_passed' THEN 'for_soa'
                        ELSE status
                     END,
                     soa_submission_deadline = ?,
                     updated_at = NOW()
                     WHERE status IN ('interview_passed', 'for_soa')"
                );
                if ($stmtGlobalDeadline) {
                    $stmtGlobalDeadline->bind_param('s', $soaDeadlineInput);
                    $stmtGlobalDeadline->execute();
                    $globalDeadlineUpdated = max(0, (int) $stmtGlobalDeadline->affected_rows);
                    $stmtGlobalDeadline->close();
                }
            }

            $stmtTargets = $conn->prepare(
                "SELECT a.id, a.application_no, a.soa_submission_deadline, u.id AS user_id, u.phone
                 FROM applications a
                 INNER JOIN users u ON u.id = a.user_id
                 WHERE a.status = 'for_soa'"
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
                $message = 'SOA reminder sent to all For SOA records (' . $sentCount . ').';
                if ($soaDeadlineInput !== '') {
                    $message .= ' Global SOA deadline set to ' . date('M d, Y', strtotime($soaDeadlineInput)) . '.';
                }
                set_flash('success', $message);
            } else {
                if ($soaDeadlineInput !== '') {
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
                    "SELECT a.application_no, a.status, u.id AS user_id, u.phone
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

                $currentStatus = (string) ($current['status'] ?? '');
                if (!in_array($currentStatus, ['interview_passed', 'for_soa'], true)) {
                    $skippedCount++;
                    continue;
                }

                $updatedStatus = $currentStatus === 'interview_passed' ? 'for_soa' : $currentStatus;
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
                "SELECT a.application_no, a.status, a.soa_submitted_at, u.id AS user_id, u.phone
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
            if (!in_array((string) $current['status'], $approvedPhaseStatuses, true)) {
                set_flash('danger', 'SOA deadline can only be set after the application is approved.');
                redirect($redirectUrl);
            }

            $updatedStatus = (string) $current['status'];
            $soaSubmittedAt = (string) ($current['soa_submitted_at'] ?? '');
            if ($updatedStatus === 'interview_passed') {
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
                "SELECT a.application_no, a.status, a.soa_submitted_at, u.id AS user_id, u.phone
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
            if (!in_array((string) $current['status'], ['for_soa', 'soa_received'], true)) {
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
            $newStatus = 'soa_received';
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
                    'SOA Received',
                    'Your SOA/Student\'s Copy for application ' . (string) ($current['application_no'] ?? '') . ' has been received by the scholarship office.',
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
                'Application marked as SOA submitted.',
                [
                    'application_no' => (string) ($current['application_no'] ?? ''),
                    'previous_status' => (string) ($current['status'] ?? ''),
                ]
            );

            set_flash('success', 'Application marked as SOA submitted.');
            redirect($redirectUrl);
        }

        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $reviewNotes = trim((string) ($_POST['review_notes'] ?? ''));
        $soaDeadlineRaw = trim((string) ($_POST['soa_submission_deadline'] ?? ''));
        $soaDeadline = $soaDeadlineRaw !== '' ? $soaDeadlineRaw : null;

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
            "SELECT a.application_no, a.status, a.soa_submission_deadline, a.soa_submitted_at, a.interview_date, a.interview_location, u.id AS user_id, u.phone
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

        $currentStatus = (string) ($current['status'] ?? '');
        $allowedTransitions = array_values(array_filter(
            $bulkStatusMap[$currentStatus] ?? [],
            static fn($status): bool => in_array((string) $status, $allowedStatus, true)
        ));
        if ($newStatus !== $currentStatus && !in_array($newStatus, $allowedTransitions, true)) {
            set_flash('danger', 'Invalid status transition for the current application state.');
            redirect($redirectUrl);
        }
        if ($newStatus === 'interview_passed') {
            $hasInterviewSchedule = trim((string) ($current['interview_date'] ?? '')) !== ''
                && trim((string) ($current['interview_location'] ?? '')) !== '';
            if ($currentStatus !== 'for_interview' || !$hasInterviewSchedule) {
                set_flash('danger', 'Approve is only allowed after interview date/time and location are set.');
                redirect($redirectUrl);
            }
        }

        if ($newStatus === 'for_soa') {
            if (!$isAdmin) {
                set_flash('danger', 'Only admin can move application to SOA submission stage.');
                redirect($redirectUrl);
            }
            if (!in_array($currentStatus, ['interview_passed', 'for_soa'], true)) {
                set_flash('danger', 'Only interview-passed applications can be moved to SOA submission stage.');
                redirect($redirectUrl);
            }
        }
        if ($newStatus === 'soa_received' && !in_array($currentStatus, ['for_soa', 'soa_received'], true)) {
            set_flash('danger', 'SOA can only be marked submitted after approval and SOA request.');
            redirect($redirectUrl);
        }
        if ($newStatus === 'disbursed' && !in_array($currentStatus, ['soa_received', 'awaiting_payout'], true)) {
            set_flash('danger', 'Only SOA Received or Awaiting Approval applications can be marked as disbursed.');
            redirect($redirectUrl);
        }
        if ($newStatus === 'soa_received' && trim((string) ($current['soa_submission_deadline'] ?? '')) === '') {
            set_flash('danger', 'Set SOA deadline first before marking SOA submitted.');
            redirect($redirectUrl);
        }
        if ($currentStatus === 'soa_received' && $newStatus === 'interview_passed') {
            set_flash('danger', 'SOA received applications must be moved to awaiting approval, not interview passed.');
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
        } elseif ($newStatus === 'soa_received' && $soaSubmittedAt === null) {
            $soaSubmittedAt = date('Y-m-d H:i:s');
        }

        $stmt = $conn->prepare(
            "UPDATE applications
             SET status = ?, review_notes = ?, soa_submission_deadline = ?, soa_submitted_at = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ssssi', $newStatus, $reviewNotes, $deadlineToSave, $soaSubmittedAt, $applicationId);
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
                $message .= ' Deadline: ' . date('M d, Y', strtotime($deadlineToSave))
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
    $baseSql = "SELECT a.id, a.application_no, a.applicant_type, a.school_name, a.school_type, a.semester, a.school_year,
                       a.status, a.review_notes, a.interview_date, a.interview_location, a.soa_submission_deadline, a.soa_submitted_at, a.updated_at,
                       u.first_name, u.last_name, u.email, u.phone
                FROM applications a
                INNER JOIN users u ON u.id = a.user_id";

    if ($statusFilter !== '') {
        $baseSql .= " WHERE a.status = ?";
    }
    $baseSql .= " ORDER BY a.updated_at DESC";

    if ($statusFilter !== '') {
        $stmt = $conn->prepare($baseSql);
        $stmt->bind_param('s', $statusFilter);
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

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Application Queue</h1>
    <div class="d-flex gap-2 align-items-center">
        <span class="small text-muted">Single queue workflow enabled</span>
    </div>
</div>

<div class="card card-soft shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2" id="applicationQueueTabs" role="tablist" aria-label="Application Queues">
            <button type="button" class="btn btn-sm btn-outline-primary<?= $queueFilter === 'under_review' ? ' active' : '' ?>" data-queue-tab="under_review">
                For Review (<?= number_format((int) ($queueCounts['under_review'] ?? 0)) ?>)
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary<?= $queueFilter === 'for_interview' ? ' active' : '' ?>" data-queue-tab="for_interview">
                For Interview (<?= number_format((int) ($queueCounts['for_interview'] ?? 0)) ?>)
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary<?= $queueFilter === 'for_soa' ? ' active' : '' ?>" data-queue-tab="for_soa">
                For SOA (<?= number_format((int) ($queueCounts['for_soa'] ?? 0)) ?>)
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary<?= $queueFilter === 'interview_passed' ? ' active' : '' ?>" data-queue-tab="interview_passed">
                Approved (<?= number_format((int) ($queueCounts['interview_passed'] ?? 0)) ?>)
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary<?= $queueFilter === 'rejected' ? ' active' : '' ?>" data-queue-tab="rejected">
                Rejected (<?= number_format((int) ($queueCounts['rejected'] ?? 0)) ?>)
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary<?= $queueFilter === 'completed' ? ' active' : '' ?>" data-queue-tab="completed">
                Completed (<?= number_format((int) ($queueCounts['completed'] ?? 0)) ?>)
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary<?= $queueFilter === 'all' ? ' active' : '' ?>" data-queue-tab="all">
                All (<?= number_format((int) ($queueCounts['all'] ?? 0)) ?>)
            </button>
        </div>
    </div>
</div>

<?php if (!$applications): ?>
    <div class="card card-soft"><div class="card-body text-muted">No application records found.</div></div>
<?php else: ?>
    <div data-live-table class="card card-soft shadow-sm">
        <?php if ($isAdmin): ?>
            <div class="card-body border-bottom">
                <form method="post" id="bulkSoaReminderForm" class="row g-2 align-items-end mb-2 d-none" data-bulk-special="for_soa" data-crud-modal="1" data-crud-title="Send SOA Reminders?" data-crud-message="Send SOA reminder SMS to all applicants currently in For SOA Submission?" data-crud-confirm-text="Send Reminders" data-crud-kind="warning">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="bulk_send_soa_reminder">
                    <div class="col-12 col-md-6">
                        <label class="form-label form-label-sm">SOA Deadline (Global for all For SOA records)</label>
                        <input type="date" name="soa_submission_deadline" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="small text-muted">Reminder will be sent to all records under For SOA queue. If set, deadline is applied globally.</div>
                    </div>
                    <div class="col-12 col-md-3 d-grid">
                        <button type="submit" class="btn btn-sm btn-outline-warning" data-bulk-special-submit="for_soa">Send SOA Reminder (All For SOA)</button>
                    </div>
                    <div class="bulk-selection-inputs"></div>
                </form>

                <form method="post" id="bulkStatusForm" class="row g-2 align-items-end" data-crud-modal="1" data-crud-title="Update Application Status?" data-crud-message="Update status for selected applications?" data-crud-confirm-text="Update Status" data-crud-kind="primary">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="bulk_update_status">
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-1">Bulk Actions</label>
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
                                    <?= e((string) $bulkButton['label']) ?> (0 selected)
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="bulkStatusSelectionInputs" class="bulk-selection-inputs"></div>
                </form>
                <form method="post" id="bulkInterviewForm" class="row g-2 align-items-end mt-2 d-none" data-bulk-special="for_interview" data-crud-modal="1" data-crud-title="Schedule Interviews?" data-crud-message="Set interview schedule for selected For Interview applications?" data-crud-confirm-text="Schedule Interviews" data-crud-kind="primary">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="bulk_schedule_interview">
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm">Interview Date</label>
                        <input type="date" name="interview_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm">Interview Time</label>
                        <input type="time" name="interview_time" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-sm">Location</label>
                        <input type="text" name="interview_location" class="form-control form-control-sm" value="Mayor's Office, San Enrique" maxlength="180">
                    </div>
                    <div class="col-12 col-md-3 d-grid">
                        <button type="submit" class="btn btn-sm btn-primary" data-bulk-special-submit="for_interview">Schedule Interview (0 selected)</button>
                    </div>
                    <div class="bulk-selection-inputs"></div>
                </form>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="bulkSelectAllApplications">
                    <label class="form-check-label small" for="bulkSelectAllApplications">Select all listed applications</label>
                </div>
            </div>
        <?php endif; ?>
        <div class="card-body border-bottom table-controls">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm">Live Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search app no, name, school">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm">Rows</label>
                    <select data-table-per-page class="form-select form-select-sm">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="col-12 col-md-5 text-md-end">
                    <span class="page-legend" data-table-summary></span>
                </div>
            </div>
            <input type="hidden" id="applicationLiveQueueFilter" data-table-filter data-filter-key="queue" value="">
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 44px;">Pick</th>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $row): ?>
                    <?php
                    $search = strtolower(implode(' ', [
                        $row['application_no'],
                        $row['first_name'],
                        $row['last_name'],
                        $row['school_name'],
                        $row['status'],
                    ]));
                    $modalId = 'applicationStatusModal' . (int) $row['id'];
                    ?>
                    <tr
                        data-search="<?= e($search) ?>"
                        data-filter="<?= e((string) $row['status']) ?>"
                        data-queue="<?= e($statusToQueue((string) ($row['status'] ?? ''))) ?>"
                        class="js-application-row"
                        data-app-modal-id="<?= e($modalId) ?>"
                        style="cursor:pointer;"
                    >
                        <td>
                            <input type="checkbox" class="form-check-input bulk-application-checkbox" value="<?= (int) $row['id'] ?>" aria-label="Select application <?= e((string) $row['application_no']) ?>">
                        </td>
                        <td>
                            <strong><?= e((string) $row['application_no']) ?></strong>
                            <div class="small text-muted">#<?= (int) $row['id'] ?> | <?= e((string) $row['semester']) ?> / <?= e((string) $row['school_year']) ?></div>
                        </td>
                        <td>
                            <?= e((string) $row['last_name']) ?>, <?= e((string) $row['first_name']) ?>
                            <div class="small text-muted"><?= e((string) $row['email']) ?> | <?= e((string) $row['phone']) ?></div>
                        </td>
                        <td>
                            <?php
                            $rowStatusValue = (string) ($row['status'] ?? '');
                            $secondaryStatus = $secondaryStatusByStatus[$rowStatusValue] ?? '';
                            ?>
                            <?php if ($secondaryStatus !== ''): ?>
                                <span class="badge <?= status_badge_class($secondaryStatus) ?>">
                                    <?= e(strtoupper($secondaryStatus)) ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge <?= status_badge_class($rowStatusValue) ?>">
                                <?= e(strtoupper($rowStatusValue)) ?>
                            </span>
                            <?php if ((string) $row['status'] === 'for_soa' && !empty($row['soa_submission_deadline'])): ?>
                                <div class="small text-muted mt-1">
                                    SOA Deadline: <?= date('M d, Y', strtotime((string) $row['soa_submission_deadline'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ((string) $row['status'] === 'for_interview'): ?>
                                <?php
                                $hasInterviewScheduleBadge = trim((string) ($row['interview_date'] ?? '')) !== ''
                                    && trim((string) ($row['interview_location'] ?? '')) !== '';
                                ?>
                                <?php if ($hasInterviewScheduleBadge): ?>
                                    <div class="small text-success mt-1">
                                        Interview Scheduled:
                                        <?= date('M d, Y h:i A', strtotime((string) $row['interview_date'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="small text-warning mt-1">Interview Not Scheduled</div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ((string) $row['status'] === 'soa_received' && !empty($row['soa_submitted_at'])): ?>
                                <div class="small text-muted mt-1">
                                    SOA Received: <?= date('M d, Y h:i A', strtotime((string) $row['soa_submitted_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, Y h:i A', strtotime((string) $row['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($applications as $row): ?>
            <?php $modalId = 'applicationStatusModal' . (int) $row['id']; ?>
            <div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Application <?= e((string) $row['application_no']) ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2 mb-3">
                                <div class="col-12 col-md-6">
                                    <div class="small text-muted">Applicant</div>
                                    <div><?= e((string) $row['last_name']) ?>, <?= e((string) $row['first_name']) ?></div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="small text-muted">School</div>
                                    <div><?= e((string) $row['school_name']) ?> (<?= e(strtoupper((string) $row['school_type'])) ?>)</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="small text-muted">Contact</div>
                                    <div><?= e((string) $row['email']) ?> | <?= e((string) $row['phone']) ?></div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="small text-muted">Current Status</div>
                                    <?php
                                    $modalStatusValue = (string) ($row['status'] ?? '');
                                    $modalSecondaryStatus = $secondaryStatusByStatus[$modalStatusValue] ?? '';
                                    ?>
                                    <?php if ($modalSecondaryStatus !== ''): ?>
                                        <span class="badge <?= status_badge_class($modalSecondaryStatus) ?>"><?= e(strtoupper($modalSecondaryStatus)) ?></span>
                                    <?php endif; ?>
                                    <span class="badge <?= status_badge_class($modalStatusValue) ?>"><?= e(strtoupper($modalStatusValue)) ?></span>
                                </div>
                            </div>

                            <?php
                            $rowCurrentStatus = (string) ($row['status'] ?? '');
                            $applicationDocuments = $documentsByApplication[(int) ($row['id'] ?? 0)] ?? [];
                            ?>
                            <?php if (in_array($rowCurrentStatus, ['under_review', 'needs_resubmission'], true)): ?>
                                <form method="post" class="row g-2">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="review_documents">
                                    <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                                    <div class="col-12">
                                        <label class="form-label form-label-sm">Document Checklist</label>
                                        <?php if (!$applicationDocuments): ?>
                                            <div class="alert alert-warning py-2 mb-0 small">No uploaded documents found for this application.</div>
                                        <?php else: ?>
                                            <div class="border rounded p-2">
                                                <?php foreach ($applicationDocuments as $doc): ?>
                                                    <?php
                                                    $docId = (int) ($doc['id'] ?? 0);
                                                    $isChecked = (string) ($doc['verification_status'] ?? '') === 'verified';
                                                    $docFilePath = trim((string) ($doc['file_path'] ?? ''));
                                                    $safeDocPath = str_replace('\\', '/', $docFilePath);
                                                    $canViewDoc = $safeDocPath !== ''
                                                        && !str_contains($safeDocPath, '..')
                                                        && preg_match('/^uploads\//', $safeDocPath) === 1;
                                                    ?>
                                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input" type="checkbox" name="doc_verified[]" value="<?= $docId ?>" id="docVerify<?= $docId ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="docVerify<?= $docId ?>">
                                                                <?= e((string) ($doc['requirement_name'] ?? ('Document #' . $docId))) ?>
                                                            </label>
                                                        </div>
                                                        <?php if ($canViewDoc): ?>
                                                            <div class="d-flex align-items-center gap-1">
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-secondary py-0 px-2 js-open-doc-preview"
                                                                    data-preview-src="<?= e($safeDocPath) ?>"
                                                                    data-preview-title="<?= e((string) ($doc['requirement_name'] ?? ('Document #' . $docId))) ?>"
                                                                >
                                                                    <i class="fa-regular fa-file-lines me-1"></i>View File
                                                                </button>
                                                                <a
                                                                    href="../<?= e($safeDocPath) ?>"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                                    title="Open in new tab"
                                                                    aria-label="Open file in new tab"
                                                                >
                                                                    <i class="fa-solid fa-up-right-from-square"></i>
                                                                </a>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="small text-muted">No file</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                If all checked: status becomes <strong>For Interview</strong>. If one or more unchecked: status becomes <strong>For Resubmission</strong> and applicant is notified.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label form-label-sm">Review Notes</label>
                                        <input type="text" name="review_notes" class="form-control form-control-sm" value="<?= e((string) ($row['review_notes'] ?? '')) ?>" placeholder="Optional review note">
                                    </div>
                                    <div class="col-12 d-flex justify-content-between flex-wrap gap-2 mt-2">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary js-open-print-preview"
                                            data-preview-title="Printable Form Preview"
                                            data-preview-url="../print-application.php?id=<?= (int) $row['id'] ?>&embed=1"
                                        >
                                            <i class="fa-solid fa-print me-1"></i>Print Form
                                        </button>
                                        <button type="submit" class="btn btn-sm btn-primary" <?= $applicationDocuments ? '' : 'disabled' ?>>
                                            <i class="fa-solid fa-floppy-disk me-1"></i>Finalize Document Review
                                        </button>
                                    </div>
                                </form>
                                <form method="post" class="row g-2 mt-2">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label form-label-sm">Rejection Notes</label>
                                        <input type="text" name="review_notes" class="form-control form-control-sm" value="<?= e((string) ($row['review_notes'] ?? '')) ?>" placeholder="Reason for rejection (recommended)">
                                    </div>
                                    <div class="col-12 col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" name="status" value="rejected">
                                            <i class="fa-solid fa-ban me-1"></i>Reject Application
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <form method="post" class="row g-2">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                                    <?php
                                    $isInterviewScheduled = trim((string) ($row['interview_date'] ?? '')) !== ''
                                        && trim((string) ($row['interview_location'] ?? '')) !== '';
                                    $rowTransitionOptions = array_values(array_filter(
                                        $bulkStatusMap[$rowCurrentStatus] ?? [],
                                        static function ($status) use ($allowedStatus, $rowCurrentStatus, $isInterviewScheduled): bool {
                                            $statusValue = (string) $status;
                                            if (!in_array($statusValue, $allowedStatus, true)) {
                                                return false;
                                            }
                                            if ($rowCurrentStatus === 'for_interview' && $statusValue === 'interview_passed' && !$isInterviewScheduled) {
                                                return false;
                                            }
                                            return true;
                                        }
                                    ));
                                    $hasRowTransitionOptions = count($rowTransitionOptions) > 0;
                                    ?>
                                    <div class="col-12">
                                        <label class="form-label form-label-sm">Quick Actions</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($rowTransitionOptions as $status): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-primary" name="status" value="<?= e((string) $status) ?>">
                                                    <?= e($statusActionLabels[(string) $status] ?? ucwords(str_replace('_', ' ', (string) $status))) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if ($rowCurrentStatus === 'for_interview' && !$isInterviewScheduled): ?>
                                            <div class="small text-warning mt-1">Set interview date/time and location first before approval.</div>
                                        <?php endif; ?>
                                        <?php if (!$hasRowTransitionOptions): ?>
                                            <div class="small text-muted mt-1">No status transitions available for this current state. You can still save notes.</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php $showModalNoteDeadlineFields = !in_array($rowCurrentStatus, ['interview_passed', 'for_soa'], true); ?>
                                    <?php if ($showModalNoteDeadlineFields): ?>
                                        <div class="col-12 col-md-8">
                                            <label class="form-label form-label-sm">Review Notes</label>
                                            <input type="text" name="review_notes" class="form-control form-control-sm" value="<?= e((string) ($row['review_notes'] ?? '')) ?>" placeholder="Optional review note">
                                        </div>
                                        <?php if ($isAdmin && in_array((string) $row['status'], $approvedPhaseStatuses, true)): ?>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label form-label-sm">SOA Deadline</label>
                                                <input type="date" name="soa_submission_deadline" class="form-control form-control-sm" value="<?= e((string) ($row['soa_submission_deadline'] ?? '')) ?>">
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="col-12 d-flex justify-content-between flex-wrap gap-2 mt-2">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary js-open-print-preview"
                                            data-preview-title="Printable Form Preview"
                                            data-preview-url="../print-application.php?id=<?= (int) $row['id'] ?>&embed=1"
                                        >
                                            <i class="fa-solid fa-print me-1"></i>Print Form
                                        </button>
                                        <?php if ($showModalNoteDeadlineFields): ?>
                                            <button type="submit" class="btn btn-sm btn-primary" name="status" value="<?= e($rowCurrentStatus) ?>">
                                                <i class="fa-solid fa-floppy-disk me-1"></i>Save Notes / Deadline
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="card-body border-top d-flex justify-content-end">
            <div class="d-flex gap-2" data-table-pager></div>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="adminDocPreviewModal" tabindex="-1" aria-labelledby="adminDocPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 m-0" id="adminDocPreviewModalLabel">Document Preview</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe
                    id="adminDocPreviewFrame"
                    src="about:blank"
                    title="Document Preview"
                    style="border:0;width:100%;height:100%;background:#fff;"
                ></iframe>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="#" id="adminDocPreviewNewTab" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-up-right-from-square me-1"></i>Open in New Tab
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

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

    if (!selectionInputsWrap || !bulkForm) {
        return;
    }

    const bulkStatusMap = {
        "__default__": ["under_review"],
        "draft": ["under_review"],
        "under_review": ["for_interview", "rejected"],
        "needs_resubmission": ["under_review", "rejected"],
        "for_interview": ["interview_passed", "rejected"],
        "interview_passed": ["for_soa"],
        "for_soa": ["soa_received"],
        "soa_received": ["awaiting_payout", "disbursed"],
        "awaiting_payout": ["disbursed"],
        "disbursed": [],
        "rejected": []
    };

    const statusLabelMap = {
        "draft": "Mark Draft",
        "under_review": "Mark Under Review",
        "needs_resubmission": "Request Resubmission",
        "for_interview": "Move to Interview",
        "interview_passed": "Approve",
        "for_soa": "Move to SOA Submission",
        "soa_received": "SOA Submitted",
        "disbursed": "Mark Disbursed",
        "rejected": "Reject",
        "awaiting_payout": "Mark Awaiting Approval"
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
        const selectedIds = getRowCheckboxes()
            .filter(function (checkbox) { return checkbox.checked; })
            .map(function (checkbox) { return String(checkbox.value || '').trim(); })
            .filter(function (value) { return value !== ''; });

        const markup = selectedIds
            .map(function (id) { return '<input type="hidden" name="application_ids[]" value="' + id + '">'; })
            .join('');
        selectionInputsWrap.innerHTML = markup;
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
            const label = String(button.getAttribute('data-status-label') || 'Status').trim();
            button.textContent = label + ' (' + selectedCount + ' selected)';
        });
        bulkSpecialSubmitButtons.forEach(function (button) {
            const parentForm = button.closest('form');
            if (!parentForm || parentForm.classList.contains('d-none')) {
                return;
            }
            const specialKey = String(button.getAttribute('data-bulk-special-submit') || '').trim();
            if (specialKey === 'for_interview') {
                button.textContent = 'Schedule Interview (' + selectedCount + ' selected)';
                return;
            }
            if (specialKey === 'for_soa') {
                button.textContent = 'Send SOA Reminder (All For SOA)';
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

    function updateSpecialBulkSections() {
        const queueValue = liveQueueFilter ? String(liveQueueFilter.value || '').trim() : '';
        bulkSpecialSections.forEach(function (section) {
            const requiredQueue = String(section.getAttribute('data-bulk-special') || '').trim();
            const shouldShow = requiredQueue !== '' && requiredQueue === queueValue;
            section.classList.toggle('d-none', !shouldShow);
        });
    }

    function applyBulkActionsForFilter() {
        const queueValue = liveQueueFilter ? String(liveQueueFilter.value || '').trim() : '';
        let actions = bulkStatusMap.__default__;
        if (queueValue === 'under_review') {
            actions = [];
        } else if (queueValue === 'for_interview') {
            actions = ['interview_passed', 'rejected'];
        } else if (queueValue === 'for_soa') {
            actions = ['soa_received'];
        } else if (queueValue === 'interview_passed') {
            actions = ['disbursed'];
        } else if (queueValue === 'rejected') {
            actions = [];
        } else if (queueValue === 'completed') {
            actions = [];
        }

        if (bulkForm) {
            bulkForm.classList.toggle('d-none', queueValue === 'under_review' || queueValue === 'rejected' || queueValue === 'completed');
        }

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
            applyQueueFilter(queueValue);
            applyBulkActionsForFilter();
        });
    });

    const serverQueue = <?= json_encode($queueFilter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let initialQueue = serverQueue;
    if (initialQueue === '' || initialQueue === null) {
        initialQueue = 'under_review';
    }
    try {
        const savedQueue = String(window.localStorage.getItem('applications.activeQueue') || '').trim();
        if (savedQueue !== '' && savedQueue !== 'null') {
            initialQueue = savedQueue;
        }
    } catch (error) {
        // Ignore storage errors.
    }

    syncSelectAllState();
    renderSelectionInputs();
    applyQueueFilter(initialQueue);
    applyBulkActionsForFilter();
    updateBulkButtonText();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function openApplicationModal(modalId) {
        if (!modalId || typeof bootstrap === 'undefined') {
            return;
        }
        const modalEl = document.getElementById(modalId);
        if (!modalEl) {
            return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    document.querySelectorAll('.js-open-application-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            openApplicationModal(String(button.getAttribute('data-app-modal-id') || '').trim());
        });
    });

    document.querySelectorAll('.js-application-row').forEach(function (row) {
        row.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            if (target.closest('input, button, a, select, textarea, label')) {
                return;
            }
            openApplicationModal(String(row.getAttribute('data-app-modal-id') || '').trim());
        });
    });

    const docPreviewModalEl = document.getElementById('adminDocPreviewModal');
    const docPreviewTitleEl = document.getElementById('adminDocPreviewModalLabel');
    const docPreviewFrameEl = document.getElementById('adminDocPreviewFrame');
    const docPreviewNewTabEl = document.getElementById('adminDocPreviewNewTab');
    const docPreviewModal = (docPreviewModalEl && typeof bootstrap !== 'undefined')
        ? bootstrap.Modal.getOrCreateInstance(docPreviewModalEl)
        : null;

    const openDocumentPreview = function (title, filePath) {
        if (!docPreviewModal || !docPreviewFrameEl || !filePath) {
            return;
        }
        const cleanPath = String(filePath || '').replace(/^\/+/, '');
        if (!cleanPath) {
            return;
        }
        const previewUrl = '../preview-document.php?file=' + encodeURIComponent(cleanPath);
        if (docPreviewTitleEl) {
            docPreviewTitleEl.textContent = title || 'Document Preview';
        }
        docPreviewFrameEl.src = previewUrl;
        if (docPreviewNewTabEl) {
            docPreviewNewTabEl.href = previewUrl;
        }
        docPreviewModal.show();
    };

    const openDirectPreview = function (title, url) {
        if (!docPreviewModal || !docPreviewFrameEl || !url) {
            return;
        }
        const previewUrl = String(url || '').trim();
        if (!previewUrl) {
            return;
        }
        if (docPreviewTitleEl) {
            docPreviewTitleEl.textContent = title || 'Preview';
        }
        docPreviewFrameEl.src = previewUrl;
        if (docPreviewNewTabEl) {
            docPreviewNewTabEl.href = previewUrl;
        }
        docPreviewModal.show();
    };

    document.querySelectorAll('.js-open-doc-preview').forEach(function (button) {
        button.addEventListener('click', function () {
            const title = String(button.getAttribute('data-preview-title') || 'Document Preview');
            const src = String(button.getAttribute('data-preview-src') || '');
            openDocumentPreview(title, src);
        });
    });

    document.querySelectorAll('.js-open-print-preview').forEach(function (button) {
        button.addEventListener('click', function () {
            const title = String(button.getAttribute('data-preview-title') || 'Printable Form Preview');
            const url = String(button.getAttribute('data-preview-url') || '');
            openDirectPreview(title, url);
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

