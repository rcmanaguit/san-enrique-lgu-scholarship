<?php
declare(strict_types=1);

function my_application_load_page_data(mysqli $conn, array $user, string $periodScope): array
{
    $applications = [];
    $applicationDocumentsById = [];
    $openPeriod = null;
    $hasApplicationThisPeriod = false;
    $canCreateNewApplication = false;
    $latestApplication = null;
    $hasApplicationPeriodColumn = false;
    $resubmissionTargetsByAppId = [];
    $periodTimeline = [];
    $soaDocumentsByAppId = [];
    $applicationModalPayload = [];

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
            "SELECT a.id, a.application_no, a.school_name, a.school_type, a.semester, a.school_year,
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
            'soa_received' => !empty($row['soa_submitted_at']) ? date('M d, Y h:i A', strtotime((string) ($row['soa_submitted_at']))) : '',
            'documents' => $documents,
            'print_url' => 'print-application.php?id=' . $appId,
            'is_archived' => (int) ($row['is_archived'] ?? 0),
            'resubmission_count' => count($resubmissionTargets),
            'next_action_title' => (string) ($nextAction['title'] ?? ''),
            'next_action_detail' => (string) ($nextAction['detail'] ?? ''),
            'timeline' => application_timeline_steps($statusCode),
        ];
    }

    return [
        'applications' => $applications,
        'applicationDocumentsById' => $applicationDocumentsById,
        'openPeriod' => $openPeriod,
        'hasApplicationThisPeriod' => $hasApplicationThisPeriod,
        'canCreateNewApplication' => $canCreateNewApplication,
        'latestApplication' => $latestApplication,
        'hasApplicationPeriodColumn' => $hasApplicationPeriodColumn,
        'resubmissionTargetsByAppId' => $resubmissionTargetsByAppId,
        'periodTimeline' => $periodTimeline,
        'soaDocumentsByAppId' => $soaDocumentsByAppId,
        'applicationModalPayload' => $applicationModalPayload,
    ];
}
