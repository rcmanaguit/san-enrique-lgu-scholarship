<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'SMS';
$previewLimit = 200;

$allowedStatus = application_status_options();
$allowedBarangays = san_enrique_barangays();
$allowedSchoolTypes = ['public', 'private'];

$smsProvider = sms_active_provider_config();
$providerLabel = sms_provider_label();
$providerDriver = strtolower(trim((string) ($smsProvider['driver'] ?? '')));
$providerEnabled = sms_provider_is_enabled();

$hasUsersTable = db_ready() && table_exists($conn, 'users');
$hasApplicationsTable = db_ready() && table_exists($conn, 'applications');
$hasSmsLogsTable = db_ready() && table_exists($conn, 'sms_logs');
$hasPeriodsTable = db_ready() && table_exists($conn, 'application_periods');
$hasSmsTemplatesTable = db_ready() && table_exists($conn, 'sms_templates');

$hasApplicationPeriodIdColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'application_period_id');
$hasApplicationStatusColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'status');
$hasApplicationBarangayColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'barangay');
$hasApplicationSchoolTypeColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'school_type');
$hasApplicationSemesterColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'semester');
$hasApplicationSchoolYearColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'school_year');
$hasApplicationNoColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'application_no');
$hasApplicationTownColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'town');
$hasApplicationProvinceColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'province');
$hasApplicationSubmittedAtColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'submitted_at');
$hasApplicationCreatedAtColumn = $hasApplicationsTable && table_column_exists($conn, 'applications', 'created_at');
$hasUserFirstNameColumn = $hasUsersTable && table_column_exists($conn, 'users', 'first_name');
$hasUserLastNameColumn = $hasUsersTable && table_column_exists($conn, 'users', 'last_name');
$hasUserRoleColumn = $hasUsersTable && table_column_exists($conn, 'users', 'role');
$hasUserStatusColumn = $hasUsersTable && table_column_exists($conn, 'users', 'status');
$hasUserPhoneColumn = $hasUsersTable && table_column_exists($conn, 'users', 'phone');

$applicationPeriods = [];
$periodMap = [];
if ($hasPeriodsTable) {
    $sqlPeriods = "SELECT id, period_name, semester, academic_year, start_date, end_date, is_open
                   FROM application_periods
                   ORDER BY academic_year DESC, FIELD(semester, 'Second Semester', 'First Semester') DESC, id DESC";
    $resultPeriods = $conn->query($sqlPeriods);
    if ($resultPeriods instanceof mysqli_result) {
        $applicationPeriods = $resultPeriods->fetch_all(MYSQLI_ASSOC);
        foreach ($applicationPeriods as $periodRow) {
            $periodId = (int) ($periodRow['id'] ?? 0);
            if ($periodId > 0) {
                $periodMap[$periodId] = $periodRow;
            }
        }
    }
}

$defaultTemplateCatalog = [
    'builtin:application_open' => [
        'label' => 'Application Period Open',
        'body' => 'San Enrique LGU Scholarship: Applications are now open for [Semester] [School Year]. Please submit your requirements on or before [Deadline].',
        'category' => 'Application',
    ],
    'builtin:requirements_reminder' => [
        'label' => 'Requirements Reminder',
        'body' => "San Enrique LGU Scholarship Reminder: Please submit your complete requirements at the Mayor's Office on or before [Deadline].",
        'category' => 'Requirements',
    ],
    'builtin:interview_notice' => [
        'label' => 'Interview Notice',
        'body' => 'San Enrique LGU Scholarship Notice: Your interview is scheduled on [Date] at [Time], [Location]. Please arrive early and bring your valid ID.',
        'category' => 'Interview',
    ],
    'builtin:soa_reminder' => [
        'label' => 'SOA / Student Copy Reminder',
        'body' => "San Enrique LGU Scholarship Reminder: Please submit your SOA/Student Copy at the Mayor's Office on or before [Deadline].",
        'category' => 'SOA',
    ],
    'builtin:payout_schedule' => [
        'label' => 'Payout Schedule Advisory',
        'body' => 'San Enrique LGU Scholarship Advisory: Your payout schedule is on [Date] at [Time], [Location]. Please bring a valid ID and keep your QR code ready.',
        'category' => 'Payout',
    ],
    'builtin:office_advisory' => [
        'label' => 'Office Advisory',
        'body' => "San Enrique LGU Scholarship Advisory: [Announcement]. For questions, please visit the Mayor's Office.",
        'category' => 'General',
    ],
];

$smsTemplates = [];
$templateCatalog = $defaultTemplateCatalog;
if ($hasSmsTemplatesTable) {
    $sqlTemplates = "SELECT id, template_name, template_body, template_category, is_active, created_at, updated_at
                     FROM sms_templates
                     ORDER BY is_active DESC, template_name ASC, id DESC";
    $resultTemplates = $conn->query($sqlTemplates);
    if ($resultTemplates instanceof mysqli_result) {
        $smsTemplates = $resultTemplates->fetch_all(MYSQLI_ASSOC);
    }

    foreach ($smsTemplates as $tpl) {
        $templateId = (int) ($tpl['id'] ?? 0);
        if ($templateId <= 0) {
            continue;
        }
        $templateKey = 'db:' . $templateId;
        $templateCatalog[$templateKey] = [
            'label' => trim((string) ($tpl['template_name'] ?? 'Template #' . $templateId)),
            'body' => trim((string) ($tpl['template_body'] ?? '')),
            'category' => trim((string) ($tpl['template_category'] ?? 'Custom')),
        ];
    }
}

$templateBodyMap = [];
foreach ($templateCatalog as $templateKey => $templateInfo) {
    $templateBodyMap[(string) $templateKey] = trim((string) ($templateInfo['body'] ?? ''));
}

$readFilters = static function (array $source) use ($allowedStatus, $allowedBarangays, $allowedSchoolTypes, $periodMap): array {
    $periodId = (int) ($source['period_id'] ?? 0);
    if ($periodId > 0 && !isset($periodMap[$periodId])) {
        $periodId = 0;
    }

    $status = trim((string) ($source['status'] ?? ''));
    if (!in_array($status, $allowedStatus, true)) {
        $status = '';
    }

    $barangay = trim((string) ($source['barangay'] ?? ''));
    if (!in_array($barangay, $allowedBarangays, true)) {
        $barangay = '';
    }

    $schoolType = trim((string) ($source['school_type'] ?? ''));
    if (!in_array($schoolType, $allowedSchoolTypes, true)) {
        $schoolType = '';
    }

    $hasApplication = trim((string) ($source['has_application'] ?? '0'));
    if (!in_array($hasApplication, ['0', '1'], true)) {
        $hasApplication = '0';
    }

    return [
        'period_id' => $periodId,
        'status' => $status,
        'barangay' => $barangay,
        'school_type' => $schoolType,
        'has_application' => $hasApplication,
    ];
};

$filters = $readFilters($_GET);

$buildRecipientWhereSql = static function (array $activeFilters) use (
    $conn,
    $hasApplicationsTable,
    $hasApplicationPeriodIdColumn,
    $hasApplicationStatusColumn,
    $hasApplicationBarangayColumn,
    $hasApplicationSchoolTypeColumn,
    $hasApplicationSemesterColumn,
    $hasApplicationSchoolYearColumn,
    $periodMap,
    $hasUserRoleColumn,
    $hasUserStatusColumn,
    $hasUserPhoneColumn
): string {
    $where = [];

    if ($hasUserRoleColumn) {
        $where[] = "u.role = 'applicant'";
    }
    if ($hasUserStatusColumn) {
        $where[] = "u.status = 'active'";
    }
    if ($hasUserPhoneColumn) {
        $where[] = "TRIM(COALESCE(u.phone, '')) <> ''";
    }

    $needsApplicationCheck = $activeFilters['has_application'] === '1'
        || (int) $activeFilters['period_id'] > 0
        || $activeFilters['status'] !== ''
        || $activeFilters['barangay'] !== ''
        || $activeFilters['school_type'] !== '';

    if ($needsApplicationCheck) {
        if (!$hasApplicationsTable) {
            $where[] = '1 = 0';
        } else {
            $appWhere = ["af.user_id = u.id"];

            if ((int) $activeFilters['period_id'] > 0) {
                $periodId = (int) $activeFilters['period_id'];
                if ($hasApplicationPeriodIdColumn) {
                    $appWhere[] = "af.application_period_id = " . $periodId;
                } else {
                    $selectedPeriod = $periodMap[$periodId] ?? null;
                    $periodSemester = trim((string) ($selectedPeriod['semester'] ?? ''));
                    $periodAcademicYear = trim((string) ($selectedPeriod['academic_year'] ?? ''));
                    if (
                        $selectedPeriod
                        && $hasApplicationSemesterColumn
                        && $hasApplicationSchoolYearColumn
                        && $periodSemester !== ''
                        && $periodAcademicYear !== ''
                    ) {
                        $appWhere[] = "af.semester = '" . $conn->real_escape_string($periodSemester) . "'";
                        $appWhere[] = "af.school_year = '" . $conn->real_escape_string($periodAcademicYear) . "'";
                    } else {
                        $appWhere[] = '1 = 0';
                    }
                }
            }

            if ($activeFilters['status'] !== '') {
                if ($hasApplicationStatusColumn) {
                    $appWhere[] = "af.status = '" . $conn->real_escape_string($activeFilters['status']) . "'";
                } else {
                    $appWhere[] = '1 = 0';
                }
            }

            if ($activeFilters['barangay'] !== '') {
                if ($hasApplicationBarangayColumn) {
                    $appWhere[] = "af.barangay = '" . $conn->real_escape_string($activeFilters['barangay']) . "'";
                } else {
                    $appWhere[] = '1 = 0';
                }
            }

            if ($activeFilters['school_type'] !== '') {
                if ($hasApplicationSchoolTypeColumn) {
                    $appWhere[] = "af.school_type = '" . $conn->real_escape_string($activeFilters['school_type']) . "'";
                } else {
                    $appWhere[] = '1 = 0';
                }
            }

            $where[] = 'EXISTS (SELECT 1 FROM applications af WHERE ' . implode(' AND ', $appWhere) . ')';
        }
    }

    if (!$where) {
        return '1 = 0';
    }

    return implode(' AND ', $where);
};

$listRecipients = static function (array $activeFilters, ?int $limit = null) use (
    $conn,
    $buildRecipientWhereSql,
    $hasApplicationsTable,
    $hasApplicationNoColumn,
    $hasApplicationStatusColumn,
    $hasApplicationSchoolYearColumn,
    $hasApplicationSemesterColumn,
    $hasApplicationBarangayColumn,
    $hasApplicationSchoolTypeColumn,
    $hasApplicationTownColumn,
    $hasApplicationProvinceColumn,
    $hasApplicationSubmittedAtColumn,
    $hasApplicationCreatedAtColumn,
    $hasUserFirstNameColumn,
    $hasUserLastNameColumn,
    $hasUserPhoneColumn
): array {
    $whereSql = $buildRecipientWhereSql($activeFilters);

    $nameSelect = trim(implode(', ', array_filter([
        'u.id',
        $hasUserFirstNameColumn ? "u.first_name" : "'' AS first_name",
        $hasUserLastNameColumn ? "u.last_name" : "'' AS last_name",
        $hasUserPhoneColumn ? "u.phone" : "'' AS phone",
        $hasApplicationNoColumn ? 'la.application_no' : "'' AS application_no",
        $hasApplicationStatusColumn ? 'la.status' : "'' AS status",
        $hasApplicationSchoolYearColumn ? 'la.school_year' : "'' AS school_year",
        $hasApplicationSemesterColumn ? 'la.semester' : "'' AS semester",
        $hasApplicationBarangayColumn ? 'la.barangay' : "'' AS barangay",
        $hasApplicationSchoolTypeColumn ? 'la.school_type' : "'' AS school_type",
        $hasApplicationTownColumn ? 'la.town' : "'" . $conn->real_escape_string(san_enrique_town()) . "' AS town",
        $hasApplicationProvinceColumn ? 'la.province' : "'" . $conn->real_escape_string(san_enrique_province()) . "' AS province",
    ])));

    $orderByTimestamp = 'ax.id DESC';
    if ($hasApplicationSubmittedAtColumn || $hasApplicationCreatedAtColumn) {
        $submittedExpr = $hasApplicationSubmittedAtColumn ? 'ax.submitted_at' : 'NULL';
        $createdExpr = $hasApplicationCreatedAtColumn ? 'ax.created_at' : 'NULL';
        $orderByTimestamp = "COALESCE({$submittedExpr}, {$createdExpr}) DESC, ax.id DESC";
    }

    $latestJoin = '';
    if ($hasApplicationsTable) {
        $latestJoin = "LEFT JOIN applications la ON la.id = (
            SELECT ax.id
            FROM applications ax
            WHERE ax.user_id = u.id
            ORDER BY {$orderByTimestamp}
            LIMIT 1
        )";
    }

    $sql = "SELECT {$nameSelect}
            FROM users u
            {$latestJoin}
            WHERE {$whereSql}
            ORDER BY u.last_name ASC, u.first_name ASC, u.id ASC";

    if ($limit !== null) {
        $safeLimit = max(1, min(2000, $limit));
        $sql .= " LIMIT " . $safeLimit;
    }

    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
};

$countRecipients = static function (array $activeFilters) use ($conn, $buildRecipientWhereSql): int {
    $whereSql = $buildRecipientWhereSql($activeFilters);
    $result = $conn->query("SELECT COUNT(*) AS total FROM users u WHERE {$whereSql}");
    if (!($result instanceof mysqli_result)) {
        return 0;
    }
    return (int) ($result->fetch_assoc()['total'] ?? 0);
};

$buildFilterQuery = static function (array $activeFilters): string {
    $query = [];
    if ((int) ($activeFilters['period_id'] ?? 0) > 0) {
        $query['period_id'] = (int) $activeFilters['period_id'];
    }
    if (trim((string) ($activeFilters['status'] ?? '')) !== '') {
        $query['status'] = trim((string) $activeFilters['status']);
    }
    if (trim((string) ($activeFilters['barangay'] ?? '')) !== '') {
        $query['barangay'] = trim((string) $activeFilters['barangay']);
    }
    if (trim((string) ($activeFilters['school_type'] ?? '')) !== '') {
        $query['school_type'] = trim((string) $activeFilters['school_type']);
    }
    if ((string) ($activeFilters['has_application'] ?? '0') === '1') {
        $query['has_application'] = '1';
    }
    return http_build_query($query);
};

if (is_post()) {
    $filters = $readFilters($_POST);
    $filterQuery = $buildFilterQuery($filters);
    $redirectTarget = 'sms.php' . ($filterQuery !== '' ? ('?' . $filterQuery) : '');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect($redirectTarget);
    }

    if (!$hasUsersTable) {
        set_flash('danger', 'SMS module is not ready yet. Please contact the administrator.');
        redirect($redirectTarget);
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $sendActions = ['send_single', 'send_selected', 'send_bulk'];
    $templateActions = ['template_create', 'template_update', 'template_delete'];
    if (!in_array($action, array_merge($sendActions, $templateActions), true)) {
        set_flash('warning', 'Unknown SMS action.');
        redirect($redirectTarget);
    }

    if (in_array($action, $templateActions, true)) {
        if (!$hasSmsTemplatesTable) {
            set_flash('danger', 'SMS template manager is not available yet. Please import the latest database schema.');
            redirect($redirectTarget);
        }

        $templateId = (int) ($_POST['template_id'] ?? 0);
        $templateName = trim((string) ($_POST['template_name'] ?? ''));
        $templateBody = trim((string) ($_POST['template_body'] ?? ''));
        $templateCategory = trim((string) ($_POST['template_category'] ?? ''));
        $templateIsActive = isset($_POST['template_is_active']) ? 1 : 0;
        $templateNameLength = function_exists('mb_strlen') ? mb_strlen($templateName) : strlen($templateName);
        $templateBodyLength = function_exists('mb_strlen') ? mb_strlen($templateBody) : strlen($templateBody);
        $templateCategoryLength = function_exists('mb_strlen') ? mb_strlen($templateCategory) : strlen($templateCategory);

        if ($action === 'template_delete') {
            if ($templateId <= 0) {
                set_flash('warning', 'Please select a template to delete.');
                redirect($redirectTarget);
            }

            $lookupStmt = $conn->prepare("SELECT template_name FROM sms_templates WHERE id = ? LIMIT 1");
            $existingName = '';
            if ($lookupStmt) {
                $lookupStmt->bind_param('i', $templateId);
                $lookupStmt->execute();
                $lookupResult = $lookupStmt->get_result();
                $lookupRow = $lookupResult instanceof mysqli_result ? ($lookupResult->fetch_assoc() ?: null) : null;
                $lookupStmt->close();
                if (is_array($lookupRow)) {
                    $existingName = trim((string) ($lookupRow['template_name'] ?? ''));
                }
            }

            if ($existingName === '') {
                set_flash('warning', 'Template not found or already deleted.');
                redirect($redirectTarget);
            }

            $deleteStmt = $conn->prepare("DELETE FROM sms_templates WHERE id = ? LIMIT 1");
            if (!$deleteStmt) {
                set_flash('danger', 'Unable to delete SMS template right now.');
                redirect($redirectTarget);
            }
            $deleteStmt->bind_param('i', $templateId);
            $deleteStmt->execute();
            $deleted = $deleteStmt->affected_rows > 0;
            $deleteStmt->close();

            if ($deleted) {
                audit_log(
                    $conn,
                    'sms_template_deleted',
                    (int) (current_user()['id'] ?? 0),
                    (string) (current_user()['role'] ?? ''),
                    'sms_template',
                    (string) $templateId,
                    'SMS template deleted.',
                    ['template_name' => $existingName]
                );
                set_flash('success', 'SMS template deleted.');
            } else {
                set_flash('danger', 'Unable to delete SMS template right now.');
            }

            redirect($redirectTarget);
        }

        if ($templateName === '' || $templateBody === '') {
            set_flash('danger', 'Template name and message are required.');
            redirect($redirectTarget);
        }
        if ($templateNameLength > 120) {
            set_flash('danger', 'Template name is too long. Keep it within 120 characters.');
            redirect($redirectTarget);
        }
        if ($templateBodyLength > 480) {
            set_flash('danger', 'Template message is too long. Keep it within 480 characters.');
            redirect($redirectTarget);
        }
        if ($templateCategoryLength > 60) {
            set_flash('danger', 'Template category is too long. Keep it within 60 characters.');
            redirect($redirectTarget);
        }

        if ($action === 'template_create') {
            $createStmt = $conn->prepare(
                "INSERT INTO sms_templates (template_name, template_body, template_category, is_active, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            if (!$createStmt) {
                set_flash('danger', 'Unable to save SMS template right now.');
                redirect($redirectTarget);
            }
            $actorId = (int) (current_user()['id'] ?? 0);
            $createStmt->bind_param('sssiii', $templateName, $templateBody, $templateCategory, $templateIsActive, $actorId, $actorId);
            $ok = $createStmt->execute();
            $newTemplateId = (int) $createStmt->insert_id;
            $errorCode = (int) $createStmt->errno;
            $createStmt->close();

            if ($ok) {
                audit_log(
                    $conn,
                    'sms_template_created',
                    $actorId,
                    (string) (current_user()['role'] ?? ''),
                    'sms_template',
                    (string) $newTemplateId,
                    'SMS template created.',
                    [
                        'template_name' => $templateName,
                        'category' => $templateCategory,
                        'is_active' => $templateIsActive,
                    ]
                );
                set_flash('success', 'SMS template saved.');
            } elseif ($errorCode === 1062) {
                set_flash('danger', 'Template name already exists. Please use a different name.');
            } else {
                set_flash('danger', 'Unable to save SMS template right now.');
            }
            redirect($redirectTarget);
        }

        if ($templateId <= 0) {
            set_flash('warning', 'Please select a template to update.');
            redirect($redirectTarget);
        }

        $actorId = (int) (current_user()['id'] ?? 0);
        $updateStmt = $conn->prepare(
            "UPDATE sms_templates
             SET template_name = ?, template_body = ?, template_category = ?, is_active = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ?
             LIMIT 1"
        );
        if (!$updateStmt) {
            set_flash('danger', 'Unable to update SMS template right now.');
            redirect($redirectTarget);
        }
        $updateStmt->bind_param('sssiii', $templateName, $templateBody, $templateCategory, $templateIsActive, $actorId, $templateId);
        $ok = $updateStmt->execute();
        $affectedRows = (int) $updateStmt->affected_rows;
        $errorCode = (int) $updateStmt->errno;
        $updateStmt->close();

        if ($ok && $affectedRows >= 0) {
            audit_log(
                $conn,
                'sms_template_updated',
                $actorId,
                (string) (current_user()['role'] ?? ''),
                'sms_template',
                (string) $templateId,
                'SMS template updated.',
                [
                    'template_name' => $templateName,
                    'category' => $templateCategory,
                    'is_active' => $templateIsActive,
                ]
            );
            set_flash('success', 'SMS template updated.');
        } elseif ($errorCode === 1062) {
            set_flash('danger', 'Template name already exists. Please use a different name.');
        } else {
            set_flash('danger', 'Unable to update SMS template right now.');
        }
        redirect($redirectTarget);
    }

    $message = trim((string) ($_POST['message'] ?? ''));
    $messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
    if ($message === '') {
        set_flash('danger', 'Please enter the text message to send.');
        redirect($redirectTarget);
    }
    if ($messageLength > 480) {
        set_flash('danger', 'SMS message is too long. Keep it within 480 characters.');
        redirect($redirectTarget);
    }
    if (!$providerEnabled) {
        set_flash('danger', 'SMS sending is currently turned off. Please contact the administrator.');
        redirect($redirectTarget);
    }

    $parseSelectedRecipientIds = static function (string $raw): array {
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    };

    $successCount = 0;
    $failedCount = 0;
    $failedNames = [];
    $totalRecipients = 0;
    $smsTypeForSend = 'bulk';
    $actionLabel = 'sms_bulk_sent';
    $actionDescription = 'Bulk SMS was sent from SMS module.';
    $recipientMetadata = [];
    $singlePhone = trim((string) ($_POST['single_phone'] ?? ''));
    $singleRecipientId = (int) ($_POST['single_recipient_id'] ?? 0);

    if ($action === 'send_single') {
        if ($singlePhone === '') {
            set_flash('danger', 'Please enter a mobile number for single SMS.');
            redirect($redirectTarget);
        }

        $smsTypeForSend = 'single';
        $actionLabel = 'sms_single_sent';
        $actionDescription = 'Single SMS was sent from SMS module.';
        $recipientMetadata[] = ['id' => $singleRecipientId, 'phone' => $singlePhone];

        $result = sms_send($singlePhone, $message, $singleRecipientId > 0 ? $singleRecipientId : null, 'single');
        $totalRecipients = 1;
        if (($result['ok'] ?? false) === true) {
            $successCount = 1;
            set_flash('success', 'Single SMS sent successfully.');
        } else {
            $failedCount = 1;
            $errorText = trim((string) ($result['error'] ?? 'Unable to send SMS.'));
            set_flash('danger', 'Single SMS failed: ' . $errorText);
        }
    } else {
        $recipients = $listRecipients($filters, null);
        if ($recipients === []) {
            set_flash('warning', 'No recipients found for the selected filters.');
            redirect($redirectTarget);
        }

        if ($action === 'send_selected') {
            $selectedIds = $parseSelectedRecipientIds((string) ($_POST['selected_recipient_ids'] ?? ''));
            if ($selectedIds === []) {
                set_flash('warning', 'Select at least one recipient for selected SMS.');
                redirect($redirectTarget);
            }

            $recipientById = [];
            foreach ($recipients as $recipientRow) {
                $recipientById[(int) ($recipientRow['id'] ?? 0)] = $recipientRow;
            }

            $selectedRecipients = [];
            foreach ($selectedIds as $selectedId) {
                if (isset($recipientById[$selectedId])) {
                    $selectedRecipients[] = $recipientById[$selectedId];
                }
            }
            if ($selectedRecipients === []) {
                set_flash('warning', 'Selected recipients are not available in the current filter.');
                redirect($redirectTarget);
            }

            $recipients = $selectedRecipients;
            $actionLabel = 'sms_selected_sent';
            $actionDescription = 'Selected-recipient SMS was sent from SMS module.';
        }

        $totalRecipients = count($recipients);

        foreach ($recipients as $recipient) {
            $recipientPhone = trim((string) ($recipient['phone'] ?? ''));
            $recipientId = (int) ($recipient['id'] ?? 0);
            $recipientMetadata[] = ['id' => $recipientId, 'phone' => $recipientPhone];
            $result = sms_send($recipientPhone, $message, $recipientId > 0 ? $recipientId : null, $smsTypeForSend);

            if (($result['ok'] ?? false) === true) {
                $successCount++;
                continue;
            }

            $failedCount++;
            if (count($failedNames) < 3) {
                $failedNames[] = trim((string) ($recipient['first_name'] ?? '') . ' ' . (string) ($recipient['last_name'] ?? ''));
            }
        }

        if ($action === 'send_selected') {
            if ($failedCount === 0) {
                set_flash('success', 'Selected SMS sent to ' . number_format($successCount) . ' recipient(s).');
            } else {
                $failedPreview = array_values(array_filter($failedNames, static fn(string $name): bool => trim($name) !== ''));
                $suffix = $failedPreview ? (' Example: ' . implode(', ', $failedPreview) . '.') : '';
                set_flash(
                    'warning',
                    'Selected SMS finished. Successful: ' . number_format($successCount) . ', failed: ' . number_format($failedCount) . '.' . $suffix
                );
            }
        } else {
            if ($failedCount === 0) {
                set_flash('success', 'Bulk SMS sent to ' . number_format($successCount) . ' recipient(s).');
            } else {
                $failedPreview = array_values(array_filter($failedNames, static fn(string $name): bool => trim($name) !== ''));
                $suffix = $failedPreview ? (' Example: ' . implode(', ', $failedPreview) . '.') : '';
                set_flash(
                    'warning',
                    'Bulk SMS finished. Successful: ' . number_format($successCount) . ', failed: ' . number_format($failedCount) . '.' . $suffix
                );
            }
        }
    }

    if ($providerDriver === 'log_only') {
        set_flash('info', 'SMS provider is currently in log-only mode. Messages are saved to logs and not delivered to phones.');
    }

    $selectedPeriod = $periodMap[(int) ($filters['period_id'] ?? 0)] ?? null;
    $periodLabel = '';
    if (is_array($selectedPeriod)) {
        $periodLabel = trim((string) ($selectedPeriod['semester'] ?? '') . ' ' . (string) ($selectedPeriod['academic_year'] ?? ''));
        if ($periodLabel === '') {
            $periodLabel = trim((string) ($selectedPeriod['period_name'] ?? ''));
        }
    }

    audit_log(
        $conn,
        $actionLabel,
        (int) (current_user()['id'] ?? 0),
        (string) (current_user()['role'] ?? ''),
        'sms',
        null,
        $actionDescription,
        [
            'mode' => $action,
            'total_recipients' => $totalRecipients,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'filters' => [
                'period' => $periodLabel !== '' ? $periodLabel : 'All',
                'status' => $filters['status'] !== '' ? $filters['status'] : 'All',
                'barangay' => $filters['barangay'] !== '' ? $filters['barangay'] : 'All',
                'school_type' => $filters['school_type'] !== '' ? $filters['school_type'] : 'All',
                'has_application' => $filters['has_application'] === '1',
            ],
            'sample_recipients' => array_slice($recipientMetadata, 0, 10),
            'message_excerpt' => excerpt($message, 160),
        ]
    );

    redirect($redirectTarget);
}

$totalRecipients = 0;
$previewRecipients = [];
if ($hasUsersTable) {
    $totalRecipients = $countRecipients($filters);
    $previewRecipients = $listRecipients($filters, $previewLimit);
}

$recentBulkSmsLogs = [];
if ($hasSmsLogsTable) {
    $sqlRecentLogs = "SELECT s.id, s.phone, s.message, s.delivery_status, s.created_at, u.first_name, u.last_name
                      FROM sms_logs s
                      LEFT JOIN users u ON u.id = s.user_id
                      WHERE s.sms_type IN ('bulk', 'single')
                      ORDER BY s.id DESC
                      LIMIT 80";
    $resultRecentLogs = $conn->query($sqlRecentLogs);
    if ($resultRecentLogs instanceof mysqli_result) {
        $recentBulkSmsLogs = $resultRecentLogs->fetch_all(MYSQLI_ASSOC);
    }
}

$filterQuery = $buildFilterQuery($filters);

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-comments me-2 text-primary"></i>SMS</h1>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge <?= $providerEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>">
            <?= e($providerEnabled ? 'SMS Ready' : 'SMS Disabled') ?>
        </span>
        <span class="small text-muted">Provider: <?= e($providerLabel) ?></span>
    </div>
</div>

<?php if (!db_ready() || !$hasUsersTable): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body text-muted">The system is not ready yet. Please contact the administrator.</div>
    </div>
<?php else: ?>
    <?php if (!$providerEnabled): ?>
        <div class="alert alert-warning">
            SMS sending is currently turned off. You can still prepare filters and message, but sending will stay disabled until the provider is enabled.
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <section class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Send SMS</h2>
                    <form id="smsSendForm" method="post" class="row g-3" data-crud-modal="1" data-crud-title="Send SMS Message?" data-crud-message="Send this message to the selected recipient scope now?" data-crud-confirm-text="Send Message">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" id="smsAction" value="send_bulk">
                        <input type="hidden" name="selected_recipient_ids" id="selectedRecipientIds" value="">
                        <input type="hidden" name="single_recipient_id" id="singleRecipientId" value="0">

                        <div class="col-12 col-md-6">
                            <label class="form-label">Application Period</label>
                            <select class="form-select" name="period_id">
                                <option value="0">All Periods</option>
                                <?php foreach ($applicationPeriods as $period): ?>
                                    <?php
                                    $periodId = (int) ($period['id'] ?? 0);
                                    $periodLabel = trim((string) ($period['semester'] ?? '') . ' ' . (string) ($period['academic_year'] ?? ''));
                                    if ($periodLabel === '') {
                                        $periodLabel = trim((string) ($period['period_name'] ?? 'Application Period'));
                                    }
                                    $isOpen = (int) ($period['is_open'] ?? 0) === 1;
                                    ?>
                                    <option value="<?= $periodId ?>" <?= (int) $filters['period_id'] === $periodId ? 'selected' : '' ?>>
                                        <?= e($periodLabel) ?><?= $isOpen ? ' (Open)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <?php foreach ($allowedStatus as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                                        <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label">School Type</label>
                            <select class="form-select" name="school_type">
                                <option value="">All</option>
                                <option value="public" <?= $filters['school_type'] === 'public' ? 'selected' : '' ?>>Public</option>
                                <option value="private" <?= $filters['school_type'] === 'private' ? 'selected' : '' ?>>Private</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Barangay</label>
                            <select class="form-select" name="barangay">
                                <option value="">All Barangays</option>
                                <?php foreach ($allowedBarangays as $barangay): ?>
                                    <option value="<?= e($barangay) ?>" <?= $filters['barangay'] === $barangay ? 'selected' : '' ?>><?= e($barangay) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Recipient Scope</label>
                            <select class="form-select" name="has_application">
                                <option value="0" <?= $filters['has_application'] === '0' ? 'selected' : '' ?>>All Active Applicants</option>
                                <option value="1" <?= $filters['has_application'] === '1' ? 'selected' : '' ?>>Applicants with Matching Application</option>
                            </select>
                            <div class="form-text">Matching Application means filters above are based on application records.</div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                <label class="form-label mb-0">Message Template</label>
                                <?php if ($hasSmsTemplatesTable): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#smsTemplateManagerModal">
                                        <i class="fa-solid fa-pen-ruler me-1"></i>Manage Templates
                                    </button>
                                <?php endif; ?>
                            </div>
                            <select class="form-select mt-2" id="smsTemplate">
                                <option value="">Custom (No Template)</option>
                                <?php foreach ($templateCatalog as $templateKey => $templateInfo): ?>
                                    <option value="<?= e((string) $templateKey) ?>">
                                        <?= e((string) ($templateInfo['label'] ?? 'Template')) ?>
                                        <?php if (str_starts_with((string) $templateKey, 'db:')): ?>
                                            [Saved]
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$hasSmsTemplatesTable): ?>
                                <div class="form-text">Template manager is unavailable until latest database schema is imported.</div>
                            <?php else: ?>
                                <div class="form-text">Select a template, or click <strong>Manage Templates</strong> to create your own.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label">SMS Message</label>
                            <textarea class="form-control" name="message" id="smsMessage" rows="5" maxlength="480" placeholder="Type your SMS here..." required><?= e(trim((string) ($_POST['message'] ?? ''))) ?></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="form-text">Use clear and short wording for applicants.</span>
                                <span class="small text-muted" id="smsCharCount">0 / 480</span>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Single SMS Mobile Number</label>
                            <input type="text" class="form-control" id="singlePhoneInput" name="single_phone" placeholder="09XXXXXXXXX or 63XXXXXXXXXX" value="<?= e(trim((string) ($_POST['single_phone'] ?? ''))) ?>">
                            <div class="form-text">Use this when sending to one recipient only.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Selected Recipients</label>
                            <div class="form-control bg-light d-flex align-items-center" style="min-height: 44px;">
                                <span id="selectedRecipientsCount">0</span>&nbsp;selected from preview list
                            </div>
                            <div class="form-text">Tick recipients below, then click <strong>Send Selected</strong>.</div>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" data-sms-send-mode="selected" <?= $providerEnabled ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-user-check me-1"></i>Send Selected
                            </button>
                            <button type="submit" class="btn btn-outline-primary" data-sms-send-mode="bulk" <?= $providerEnabled ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-users me-1"></i>Send All Filtered
                            </button>
                            <button type="submit" class="btn btn-outline-primary" data-sms-send-mode="single" <?= $providerEnabled ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-user me-1"></i>Send Single
                            </button>
                            <a href="sms.php" class="btn btn-outline-secondary">Clear Filters</a>
                            <a href="../admin-only/logs.php?log_type=sms" class="btn btn-outline-primary">
                                <i class="fa-solid fa-clipboard-list me-1"></i>View SMS Logs
                            </a>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Recipient Preview</h2>
                    <div class="mb-2">
                        <span class="badge text-bg-primary">Total Recipients: <?= number_format($totalRecipients) ?></span>
                    </div>
                    <p class="small text-muted mb-2">
                        Preview shows up to <?= number_format($previewLimit) ?> records based on your current filters.
                    </p>
                    <?php if ($filterQuery !== ''): ?>
                        <a href="sms.php?<?= e($filterQuery) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-rotate me-1"></i>Refresh Preview
                        </a>
                    <?php endif; ?>
                    <hr>
                    <div class="small text-muted">
                        <div><strong>Status:</strong> <?= e($filters['status'] !== '' ? ucwords(str_replace('_', ' ', $filters['status'])) : 'All') ?></div>
                        <div><strong>Barangay:</strong> <?= e($filters['barangay'] !== '' ? $filters['barangay'] : 'All') ?></div>
                        <div><strong>School Type:</strong> <?= e($filters['school_type'] !== '' ? ucfirst($filters['school_type']) : 'All') ?></div>
                        <div><strong>Scope:</strong> <?= $filters['has_application'] === '1' ? 'Applicants with matching application' : 'All active applicants' ?></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <?php if (!$previewRecipients): ?>
        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body text-muted">No recipients found for the selected filters.</div>
        </div>
    <?php else: ?>
        <div data-live-table class="card card-soft shadow-sm mb-3">
            <div class="card-body border-bottom table-controls">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label form-label-sm">Live Search</label>
                        <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search name, mobile, application, status, barangay">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm">Live Status Filter</label>
                        <select data-table-filter class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach ($allowedStatus as $status): ?>
                                <option value="<?= e($status) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                            <?php endforeach; ?>
                            <option value="no_status">No Status</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm">Rows</label>
                        <select data-table-per-page class="form-select form-select-sm">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 text-md-end">
                        <span class="page-legend" data-table-summary></span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 42px;">
                                <input type="checkbox" class="form-check-input" id="selectAllRecipients" title="Select all">
                            </th>
                            <th>Applicant</th>
                            <th>Mobile Number</th>
                            <th>Latest Application</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewRecipients as $recipient): ?>
                            <?php
                            $fullName = trim((string) ($recipient['last_name'] ?? '') . ', ' . (string) ($recipient['first_name'] ?? ''));
                            $status = trim((string) ($recipient['status'] ?? ''));
                            $statusForFilter = $status !== '' ? $status : 'no_status';
                            $locationParts = array_values(array_filter([
                                trim((string) ($recipient['barangay'] ?? '')),
                                trim((string) ($recipient['town'] ?? '')),
                                trim((string) ($recipient['province'] ?? '')),
                            ], static fn(string $value): bool => $value !== ''));
                            $searchText = strtolower(implode(' ', [
                                $fullName,
                                (string) ($recipient['phone'] ?? ''),
                                (string) ($recipient['application_no'] ?? ''),
                                (string) ($recipient['school_year'] ?? ''),
                                (string) ($recipient['semester'] ?? ''),
                                $status,
                                implode(', ', $locationParts),
                            ]));
                            ?>
                            <tr data-search="<?= e($searchText) ?>" data-filter="<?= e($statusForFilter) ?>">
                                <td>
                                    <input
                                        type="checkbox"
                                        class="form-check-input sms-recipient-checkbox"
                                        value="<?= (int) ($recipient['id'] ?? 0) ?>"
                                        data-phone="<?= e((string) ($recipient['phone'] ?? '')) ?>"
                                        data-name="<?= e($fullName !== ',' ? $fullName : '-') ?>"
                                    >
                                </td>
                                <td><?= e($fullName !== ',' ? $fullName : '-') ?></td>
                                <td>
                                    <?= e((string) ($recipient['phone'] ?? '-')) ?>
                                    <div class="small mt-1">
                                        <button type="button" class="btn btn-sm btn-link p-0 sms-use-single" data-phone="<?= e((string) ($recipient['phone'] ?? '')) ?>" data-recipient-id="<?= (int) ($recipient['id'] ?? 0) ?>">
                                            Use for single SMS
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php if (trim((string) ($recipient['application_no'] ?? '')) !== ''): ?>
                                        <strong><?= e((string) $recipient['application_no']) ?></strong>
                                        <div class="small text-muted">
                                            <?= e((string) ($recipient['semester'] ?? '')) ?> <?= e((string) ($recipient['school_year'] ?? '')) ?>
                                        </div>
                                        <div class="small">
                                            <span class="badge <?= status_badge_class($status) ?>"><?= e($status !== '' ? strtoupper($status) : 'NO STATUS') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No application record</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($locationParts ? implode(', ', $locationParts) : '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-body border-top d-flex justify-content-end">
                <div class="d-flex gap-2" data-table-pager></div>
            </div>
        </div>
    <?php endif; ?>

    <div data-live-table class="card card-soft shadow-sm">
        <div class="card-body border-bottom table-controls">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="h6 m-0">Recent SMS Logs (Single / Selected / Bulk)</h2>
                <a href="../admin-only/logs.php?log_type=sms" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-up-right-from-square me-1"></i>Open Full Logs
                </a>
            </div>
            <div class="row g-2 align-items-end mt-1">
                <div class="col-12 col-md-6">
                    <label class="form-label form-label-sm">Live Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search phone, message, recipient">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select data-table-filter class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                        <option value="queued">Queued</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Rows</label>
                    <select data-table-per-page class="form-select form-select-sm">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 text-md-end">
                    <span class="page-legend" data-table-summary></span>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Recipient</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th class="text-end">Use</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentBulkSmsLogs): ?>
                        <tr>
                            <td colspan="5" class="text-muted">No SMS logs yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentBulkSmsLogs as $log): ?>
                            <?php
                            $name = trim((string) ($log['first_name'] ?? '') . ' ' . (string) ($log['last_name'] ?? ''));
                            $status = trim((string) ($log['delivery_status'] ?? 'queued'));
                            $searchText = strtolower(implode(' ', [
                                (string) ($log['phone'] ?? ''),
                                (string) ($log['message'] ?? ''),
                                $name,
                                $status,
                            ]));
                            ?>
                            <tr data-search="<?= e($searchText) ?>" data-filter="<?= e($status) ?>">
                                <td><?= date('M d, Y h:i A', strtotime((string) $log['created_at'])) ?></td>
                                <td>
                                    <?= e((string) ($log['phone'] ?? '-')) ?>
                                    <div class="small text-muted"><?= e($name !== '' ? $name : '-') ?></div>
                                </td>
                                <td><?= e(excerpt((string) ($log['message'] ?? ''), 120)) ?></td>
                                <td><span class="badge <?= status_badge_class($status) ?>"><?= e(strtoupper($status)) ?></span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary sms-use-history" data-message="<?= e((string) ($log['message'] ?? '')) ?>">
                                        Use
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body border-top d-flex justify-content-end">
            <div class="d-flex gap-2" data-table-pager></div>
        </div>
    </div>

    <?php if ($hasSmsTemplatesTable): ?>
        <div class="modal fade modal-se" id="smsTemplateManagerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <div class="modal-se-title-wrap">
                            <span class="modal-se-icon is-info"><i class="fa-solid fa-pen-ruler"></i></span>
                            <div>
                                <h5 class="modal-title mb-0">SMS Template Manager</h5>
                                <small class="text-muted">Create, edit, and remove reusable templates</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <div class="card card-soft h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h3 class="h6 mb-0" id="smsTemplateFormTitle">New Template</h3>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="smsTemplateResetBtn">
                                                <i class="fa-solid fa-rotate-left me-1"></i>New
                                            </button>
                                        </div>
                                        <form method="post" id="smsTemplateForm" class="row g-2 js-crud-modal-skip" data-crud-modal="off">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" id="smsTemplateAction" value="template_create">
                                            <input type="hidden" name="template_id" id="smsTemplateId" value="0">
                                            <input type="hidden" name="period_id" value="<?= (int) ($filters['period_id'] ?? 0) ?>">
                                            <input type="hidden" name="status" value="<?= e((string) ($filters['status'] ?? '')) ?>">
                                            <input type="hidden" name="barangay" value="<?= e((string) ($filters['barangay'] ?? '')) ?>">
                                            <input type="hidden" name="school_type" value="<?= e((string) ($filters['school_type'] ?? '')) ?>">
                                            <input type="hidden" name="has_application" value="<?= e((string) ($filters['has_application'] ?? '0')) ?>">

                                            <div class="col-12">
                                                <label class="form-label">Template Name</label>
                                                <input type="text" class="form-control" name="template_name" id="smsTemplateName" maxlength="120" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Category</label>
                                                <input type="text" class="form-control" name="template_category" id="smsTemplateCategory" maxlength="60" placeholder="Example: Interview, SOA, Payout">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Template Message</label>
                                                <textarea class="form-control" name="template_body" id="smsTemplateBody" rows="6" maxlength="480" required></textarea>
                                                <div class="form-text">Keep template within 480 characters.</div>
                                            </div>
                                            <div class="col-12 form-check ms-1">
                                                <input class="form-check-input" type="checkbox" name="template_is_active" id="smsTemplateIsActive" checked>
                                                <label class="form-check-label" for="smsTemplateIsActive">Active template</label>
                                            </div>
                                            <div class="col-12 d-flex gap-2">
                                                <button type="submit" class="btn btn-primary" id="smsTemplateSubmitBtn">
                                                    <i class="fa-solid fa-floppy-disk me-1"></i>Save Template
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" id="smsTemplateCancelEditBtn">Cancel Edit</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="card card-soft h-100">
                                    <div class="card-body">
                                        <h3 class="h6 mb-2">Saved Templates</h3>
                                        <?php if (!$smsTemplates): ?>
                                            <p class="text-muted mb-0">No saved templates yet. Use the form to create one.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Category</th>
                                                            <th>Status</th>
                                                            <th class="text-end">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($smsTemplates as $tpl): ?>
                                                            <?php
                                                            $tplId = (int) ($tpl['id'] ?? 0);
                                                            $tplName = trim((string) ($tpl['template_name'] ?? 'Template'));
                                                            $tplCategory = trim((string) ($tpl['template_category'] ?? 'General'));
                                                            $tplBody = trim((string) ($tpl['template_body'] ?? ''));
                                                            $tplActive = (int) ($tpl['is_active'] ?? 0) === 1;
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?= e($tplName) ?></strong>
                                                                    <div class="small text-muted"><?= e(excerpt($tplBody, 90)) ?></div>
                                                                </td>
                                                                <td><?= e($tplCategory !== '' ? $tplCategory : 'General') ?></td>
                                                                <td>
                                                                    <span class="badge <?= $tplActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                                        <?= $tplActive ? 'Active' : 'Inactive' ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-end">
                                                                    <div class="btn-group btn-group-sm">
                                                                        <button
                                                                            type="button"
                                                                            class="btn btn-outline-primary sms-template-edit-btn"
                                                                            data-template-id="<?= $tplId ?>"
                                                                            data-template-name="<?= e($tplName) ?>"
                                                                            data-template-category="<?= e($tplCategory) ?>"
                                                                            data-template-body="<?= e($tplBody) ?>"
                                                                            data-template-active="<?= $tplActive ? '1' : '0' ?>"
                                                                        >
                                                                            Edit
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            class="btn btn-outline-danger sms-template-delete-btn"
                                                                            data-template-id="<?= $tplId ?>"
                                                                            data-template-name="<?= e($tplName) ?>"
                                                                        >
                                                                            Delete
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade modal-se" id="smsTemplateDeleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <div class="modal-se-title-wrap">
                            <span class="modal-se-icon is-danger"><i class="fa-solid fa-trash-can"></i></span>
                            <div>
                                <h5 class="modal-title mb-0">Delete Template?</h5>
                                <small class="text-muted">This action cannot be undone.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <p class="mb-0">You are about to delete <strong id="smsTemplateDeleteName">this template</strong>.</p>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" class="d-inline js-crud-modal-skip" data-crud-modal="off">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="template_delete">
                            <input type="hidden" name="template_id" id="smsTemplateDeleteId" value="0">
                            <input type="hidden" name="period_id" value="<?= (int) ($filters['period_id'] ?? 0) ?>">
                            <input type="hidden" name="status" value="<?= e((string) ($filters['status'] ?? '')) ?>">
                            <input type="hidden" name="barangay" value="<?= e((string) ($filters['barangay'] ?? '')) ?>">
                            <input type="hidden" name="school_type" value="<?= e((string) ($filters['school_type'] ?? '')) ?>">
                            <input type="hidden" name="has_application" value="<?= e((string) ($filters['has_application'] ?? '0')) ?>">
                            <button type="submit" class="btn btn-danger">Delete Template</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('smsSendForm');
    const actionInput = document.getElementById('smsAction');
    const selectedIdsInput = document.getElementById('selectedRecipientIds');
    const singlePhoneInput = document.getElementById('singlePhoneInput');
    const singleRecipientIdInput = document.getElementById('singleRecipientId');
    const sendButtons = document.querySelectorAll('[data-sms-send-mode]');
    const recipientCheckboxes = document.querySelectorAll('.sms-recipient-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllRecipients');
    const selectedCount = document.getElementById('selectedRecipientsCount');
    const useSingleButtons = document.querySelectorAll('.sms-use-single');
    const useHistoryButtons = document.querySelectorAll('.sms-use-history');

    const templateSelect = document.getElementById('smsTemplate');
    const messageBox = document.getElementById('smsMessage');
    const charCount = document.getElementById('smsCharCount');

    const templateForm = document.getElementById('smsTemplateForm');
    const templateActionInput = document.getElementById('smsTemplateAction');
    const templateIdInput = document.getElementById('smsTemplateId');
    const templateNameInput = document.getElementById('smsTemplateName');
    const templateCategoryInput = document.getElementById('smsTemplateCategory');
    const templateBodyInput = document.getElementById('smsTemplateBody');
    const templateIsActiveInput = document.getElementById('smsTemplateIsActive');
    const templateTitle = document.getElementById('smsTemplateFormTitle');
    const templateSubmitBtn = document.getElementById('smsTemplateSubmitBtn');
    const templateCancelEditBtn = document.getElementById('smsTemplateCancelEditBtn');
    const templateResetBtn = document.getElementById('smsTemplateResetBtn');
    const templateEditButtons = document.querySelectorAll('.sms-template-edit-btn');
    const templateDeleteButtons = document.querySelectorAll('.sms-template-delete-btn');
    const templateDeleteName = document.getElementById('smsTemplateDeleteName');
    const templateDeleteId = document.getElementById('smsTemplateDeleteId');
    const templateDeleteModalEl = document.getElementById('smsTemplateDeleteModal');

    if (!messageBox || !charCount || !form || !actionInput || !selectedIdsInput) {
        return;
    }

    const templates = <?= json_encode($templateBodyMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const updateCount = function () {
        const length = messageBox.value.length;
        charCount.textContent = length + " / 480";
    };

    const collectSelectedIds = function () {
        const ids = [];
        recipientCheckboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                ids.push(String(checkbox.value || '').trim());
            }
        });
        return ids.filter(function (id) { return id !== ''; });
    };

    const syncSelectedIds = function () {
        const ids = collectSelectedIds();
        selectedIdsInput.value = ids.join(',');
        if (selectedCount) {
            selectedCount.textContent = String(ids.length);
        }

        if (selectAllCheckbox) {
            const total = recipientCheckboxes.length;
            const selectedTotal = ids.length;
            selectAllCheckbox.checked = total > 0 && selectedTotal === total;
            selectAllCheckbox.indeterminate = selectedTotal > 0 && selectedTotal < total;
        }
    };

    updateCount();
    messageBox.addEventListener('input', updateCount);
    recipientCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', syncSelectedIds);
    });
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const checked = !!selectAllCheckbox.checked;
            recipientCheckboxes.forEach(function (checkbox) {
                checkbox.checked = checked;
            });
            syncSelectedIds();
        });
    }
    syncSelectedIds();

    useSingleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const phone = String(button.getAttribute('data-phone') || '').trim();
            const recipientId = String(button.getAttribute('data-recipient-id') || '').trim();
            if (singlePhoneInput) {
                singlePhoneInput.value = phone;
                singlePhoneInput.focus();
            }
            if (singleRecipientIdInput) {
                singleRecipientIdInput.value = recipientId !== '' ? recipientId : '0';
            }
        });
    });

    if (singlePhoneInput && singleRecipientIdInput) {
        singlePhoneInput.addEventListener('input', function () {
            singleRecipientIdInput.value = '0';
        });
    }

    sendButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            const mode = String(button.getAttribute('data-sms-send-mode') || '').trim();
            if (mode !== 'single' && mode !== 'selected' && mode !== 'bulk') {
                return;
            }
            actionInput.value = 'send_' + mode;
            syncSelectedIds();

            if (mode === 'single') {
                if (singlePhoneInput && String(singlePhoneInput.value || '').trim() === '') {
                    event.preventDefault();
                    if (typeof window.showAlertModal === 'function') {
                        window.showAlertModal({
                            title: 'Mobile Number Required',
                            message: 'Enter a mobile number for Single SMS.',
                            kind: 'warning',
                        });
                    } else {
                        alert('Enter a mobile number for Single SMS.');
                    }
                    return;
                }
            }

            if (mode === 'selected') {
                const selected = collectSelectedIds();
                if (selected.length === 0) {
                    event.preventDefault();
                    if (typeof window.showAlertModal === 'function') {
                        window.showAlertModal({
                            title: 'No Recipient Selected',
                            message: 'Select at least one recipient first.',
                            kind: 'warning',
                        });
                    } else {
                        alert('Select at least one recipient first.');
                    }
                    return;
                }
            }
        });
    });

    useHistoryButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const historyMessage = String(button.getAttribute('data-message') || '').trim();
            if (historyMessage === '') {
                return;
            }

            const currentText = String(messageBox.value || '').trim();
            if (currentText !== '' && currentText !== historyMessage) {
                const shouldReplace = window.confirm('Replace current message with this history message?');
                if (!shouldReplace) {
                    return;
                }
            }

            messageBox.value = historyMessage;
            updateCount();
            messageBox.focus();
        });
    });

    const resetTemplateForm = function () {
        if (!templateForm || !templateActionInput || !templateIdInput || !templateNameInput || !templateCategoryInput || !templateBodyInput || !templateIsActiveInput || !templateTitle || !templateSubmitBtn) {
            return;
        }
        templateActionInput.value = 'template_create';
        templateIdInput.value = '0';
        templateNameInput.value = '';
        templateCategoryInput.value = '';
        templateBodyInput.value = '';
        templateIsActiveInput.checked = true;
        templateTitle.textContent = 'New Template';
        templateSubmitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i>Save Template';
    };

    if (templateResetBtn) {
        templateResetBtn.addEventListener('click', function () {
            resetTemplateForm();
            if (templateNameInput) {
                templateNameInput.focus();
            }
        });
    }
    if (templateCancelEditBtn) {
        templateCancelEditBtn.addEventListener('click', function () {
            resetTemplateForm();
        });
    }

    templateEditButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!templateActionInput || !templateIdInput || !templateNameInput || !templateCategoryInput || !templateBodyInput || !templateIsActiveInput || !templateTitle || !templateSubmitBtn) {
                return;
            }

            templateActionInput.value = 'template_update';
            templateIdInput.value = String(button.getAttribute('data-template-id') || '0');
            templateNameInput.value = String(button.getAttribute('data-template-name') || '');
            templateCategoryInput.value = String(button.getAttribute('data-template-category') || '');
            templateBodyInput.value = String(button.getAttribute('data-template-body') || '');
            templateIsActiveInput.checked = String(button.getAttribute('data-template-active') || '0') === '1';
            templateTitle.textContent = 'Edit Template';
            templateSubmitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i>Update Template';
            templateNameInput.focus();
        });
    });

    let templateDeleteModal = null;
    if (typeof bootstrap !== 'undefined' && templateDeleteModalEl) {
        templateDeleteModal = new bootstrap.Modal(templateDeleteModalEl);
    }
    templateDeleteButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const id = String(button.getAttribute('data-template-id') || '0');
            const name = String(button.getAttribute('data-template-name') || 'this template');
            if (templateDeleteId) {
                templateDeleteId.value = id;
            }
            if (templateDeleteName) {
                templateDeleteName.textContent = name;
            }

            if (templateDeleteModal) {
                templateDeleteModal.show();
            } else {
                const shouldDelete = window.confirm('Delete template "' + name + '"?');
                if (!shouldDelete || !templateDeleteId) {
                    return;
                }
                const fallbackForm = templateDeleteId.closest('form');
                if (fallbackForm) {
                    fallbackForm.submit();
                }
            }
        });
    });

    if (!templateSelect) {
        return;
    }

    templateSelect.addEventListener('change', function () {
        const key = String(templateSelect.value || '').trim();
        if (!key || !templates[key]) {
            return;
        }

        const currentText = String(messageBox.value || '').trim();
        if (currentText !== '' && currentText !== templates[key]) {
            const shouldReplace = window.confirm('Replace current message with the selected template?');
            if (!shouldReplace) {
                templateSelect.value = '';
                return;
            }
        }

        messageBox.value = templates[key];
        updateCount();
        messageBox.focus();
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
