<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Communications';
$previewLimit = 200;
$currentUser = current_user();
$isAdminUser = (string) ($currentUser['role'] ?? '') === 'admin';

$allowedStatus = application_status_options();
$allowedBarangays = san_enrique_barangays();
$allowedSchoolTypes = ['public', 'private'];

$smsProvider = sms_active_provider_config();
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
    ensure_application_period_status_column($conn);
    $sqlPeriods = "SELECT id, period_name, semester, academic_year, start_date, end_date, is_open, period_status
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
        'body' => 'San Enrique LGU Scholarship Advisory: Your payout schedule is on [Date] at [Time], [Location]. Please bring a valid ID and arrive on time.',
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

$announcementRequirementTemplates = [];
$adminAnnouncements = [];
$defaultManageablePeriodId = 0;
$currentManageablePeriodLabel = '';
if ($hasPeriodsTable) {
    $currentManageablePeriod = current_active_application_period($conn);
    if (is_array($currentManageablePeriod)) {
        $defaultManageablePeriodId = (int) ($currentManageablePeriod['id'] ?? 0);
        $currentManageablePeriodLabel = trim((string) ($currentManageablePeriod['semester'] ?? '') . ' ' . (string) ($currentManageablePeriod['academic_year'] ?? ''));
        if ($currentManageablePeriodLabel === '') {
            $currentManageablePeriodLabel = trim((string) ($currentManageablePeriod['period_name'] ?? 'Current active period'));
        }
    }
}

$readFilters = static function (array $source) use ($allowedStatus, $allowedBarangays, $allowedSchoolTypes, $defaultManageablePeriodId): array {
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

    $periodId = $defaultManageablePeriodId > 0 ? $defaultManageablePeriodId : 0;
    $hasApplication = ($periodId > 0 || $status !== '' || $barangay !== '' || $schoolType !== '') ? '1' : '0';

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
    if (trim((string) ($activeFilters['status'] ?? '')) !== '') {
        $query['status'] = trim((string) $activeFilters['status']);
    }
    if (trim((string) ($activeFilters['barangay'] ?? '')) !== '') {
        $query['barangay'] = trim((string) $activeFilters['barangay']);
    }
    if (trim((string) ($activeFilters['school_type'] ?? '')) !== '') {
        $query['school_type'] = trim((string) $activeFilters['school_type']);
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
    $announcementActions = ['announcement_create', 'announcement_update', 'announcement_toggle', 'announcement_delete'];

    if (in_array($action, $announcementActions, true)) {
        if (!$isAdminUser) {
            set_flash('danger', 'Only administrators can manage announcements.');
            redirect($redirectTarget);
        }

        if (!table_exists($conn, 'announcements')) {
            set_flash('danger', 'Announcement module is not available yet. Please import the latest database schema.');
            redirect($redirectTarget);
        }

        $announcementRedirectTarget = 'sms.php?panel=announcements';

        if ($action === 'announcement_toggle') {
            $announcementId = (int) ($_POST['announcement_id'] ?? 0);
            $newStatus = (int) ($_POST['new_status'] ?? 0);
            $stmt = $conn->prepare("UPDATE announcements SET is_active = ? WHERE id = ? LIMIT 1");
            $stmt->bind_param('ii', $newStatus, $announcementId);
            $stmt->execute();
            $stmt->close();
            audit_log(
                $conn,
                'announcement_status_changed',
                (int) ($currentUser['id'] ?? 0),
                (string) ($currentUser['role'] ?? ''),
                'announcement',
                (string) $announcementId,
                'Announcement active status changed.',
                ['new_status' => $newStatus]
            );
            set_flash('success', 'Announcement status updated.');
            redirect($announcementRedirectTarget);
        }

        if ($action === 'announcement_delete') {
            $announcementId = (int) ($_POST['announcement_id'] ?? 0);
            $deleteStmt = $conn->prepare("DELETE FROM announcements WHERE id = ? LIMIT 1");
            $deleteStmt->bind_param('i', $announcementId);
            $deleteStmt->execute();
            $deleted = $deleteStmt->affected_rows > 0;
            $deleteStmt->close();
            if ($deleted) {
                audit_log(
                    $conn,
                    'announcement_deleted',
                    (int) ($currentUser['id'] ?? 0),
                    (string) ($currentUser['role'] ?? ''),
                    'announcement',
                    (string) $announcementId,
                    'Announcement deleted permanently.'
                );
                set_flash('success', 'Announcement deleted.');
            } else {
                set_flash('danger', 'Unable to delete announcement.');
            }
            redirect($announcementRedirectTarget);
        }

        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sendSms = isset($_POST['send_sms']) ? 1 : 0;

        if ($title === '' || $content === '') {
            set_flash('danger', 'Title and content are required.');
            redirect($announcementRedirectTarget);
        }

        if ($action === 'announcement_update' && $announcementId > 0) {
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, is_active = ? WHERE id = ? LIMIT 1");
            $stmt->bind_param('ssii', $title, $content, $isActive, $announcementId);
            $stmt->execute();
            $stmt->close();
            audit_log(
                $conn,
                'announcement_updated',
                (int) ($currentUser['id'] ?? 0),
                (string) ($currentUser['role'] ?? ''),
                'announcement',
                (string) $announcementId,
                'Announcement updated.',
                [
                    'title' => $title,
                    'is_active' => $isActive,
                    'send_sms' => $sendSms,
                ]
            );
            $savedAnnouncementId = $announcementId;
            set_flash('success', 'Announcement updated.');
        } else {
            $stmt = $conn->prepare("INSERT INTO announcements (title, content, is_active, created_by) VALUES (?, ?, ?, ?)");
            $actorId = (int) ($currentUser['id'] ?? 0);
            $stmt->bind_param('ssii', $title, $content, $isActive, $actorId);
            $stmt->execute();
            $savedAnnouncementId = (int) $stmt->insert_id;
            $stmt->close();
            audit_log(
                $conn,
                'announcement_created',
                $actorId,
                (string) ($currentUser['role'] ?? ''),
                'announcement',
                (string) $savedAnnouncementId,
                'Announcement created.',
                [
                    'title' => $title,
                    'is_active' => $isActive,
                    'send_sms' => $sendSms,
                ]
            );
            set_flash('success', 'Announcement saved.');
        }

        if ($sendSms === 1 && $isActive === 1) {
            $phones = [];
            $resultPhones = $conn->query("SELECT phone FROM users WHERE role = 'applicant' AND status = 'active' AND phone IS NOT NULL AND phone <> ''");
            if ($resultPhones instanceof mysqli_result) {
                while ($rowPhone = $resultPhones->fetch_assoc()) {
                    $phones[] = (string) $rowPhone['phone'];
                }
            }
            $smsMessage = 'San Enrique LGU Scholarship Update: ' . $title . '. ' . excerpt($content, 120);
            sms_send_bulk($phones, $smsMessage, 'bulk');
            audit_log(
                $conn,
                'announcement_sms_broadcast',
                (int) ($currentUser['id'] ?? 0),
                (string) ($currentUser['role'] ?? ''),
                'announcement',
                (string) $savedAnnouncementId,
                'Announcement SMS broadcast initiated.',
                ['recipient_count' => count($phones)]
            );
        }

        redirect($announcementRedirectTarget);
    }

    $sendActions = ['send_single', 'send_bulk'];
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
        if ($singleRecipientId <= 0) {
            set_flash('danger', 'Please select one recipient first.');
            redirect($redirectTarget);
        }

        $singleLookupStmt = $conn->prepare("SELECT phone, first_name, last_name FROM users WHERE id = ? LIMIT 1");
        if (!$singleLookupStmt) {
            set_flash('danger', 'Unable to load the selected recipient right now.');
            redirect($redirectTarget);
        }
        $singleLookupStmt->bind_param('i', $singleRecipientId);
        $singleLookupStmt->execute();
        $singleLookupResult = $singleLookupStmt->get_result();
        $singleRecipientRow = $singleLookupResult instanceof mysqli_result ? ($singleLookupResult->fetch_assoc() ?: null) : null;
        $singleLookupStmt->close();

        $singlePhone = trim((string) ($singleRecipientRow['phone'] ?? ''));
        if ($singlePhone === '') {
            set_flash('danger', 'The selected recipient does not have a valid mobile number.');
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

if ($isAdminUser && db_ready() && table_exists($conn, 'announcements')) {
    if (table_exists($conn, 'requirement_templates')) {
        $requirementsSql = "SELECT requirement_name, description, is_required
                            FROM requirement_templates
                            WHERE is_active = 1
                            ORDER BY sort_order ASC, id ASC";
        $requirementsResult = $conn->query($requirementsSql);
        if ($requirementsResult instanceof mysqli_result) {
            $announcementRequirementTemplates = $requirementsResult->fetch_all(MYSQLI_ASSOC);
        }
    }

    $announcementSql = "SELECT a.id, a.title, a.content, a.is_active, a.created_at, u.first_name, u.last_name
                        FROM announcements a
                        LEFT JOIN users u ON u.id = a.created_by
                        ORDER BY a.created_at DESC, a.id DESC";
    $announcementResult = $conn->query($announcementSql);
    if ($announcementResult instanceof mysqli_result) {
        $adminAnnouncements = $announcementResult->fetch_all(MYSQLI_ASSOC);
    }
}

$filterQuery = $buildFilterQuery($filters);

$renderSmsRecipientPreviewCard = static function (int $recipientTotal, int $recipientPreviewLimit, array $activeFilters, array $recipientRows): string {
    ob_start();
    ?>
    <div class="mb-2">
        <span class="badge text-bg-primary" id="smsRecipientTotalBadge">Total Recipients: <?= number_format($recipientTotal) ?></span>
    </div>
    <p class="small text-muted mb-2">
        Preview shows up to <?= number_format($recipientPreviewLimit) ?> records based on your current filters.
    </p>
    <hr>
    <div class="small text-muted" id="smsRecipientFilterSummary">
        <div><strong>Status:</strong> <?= e($activeFilters['status'] !== '' ? application_status_label((string) $activeFilters['status']) : 'All') ?></div>
        <div><strong>Barangay:</strong> <?= e($activeFilters['barangay'] !== '' ? (string) $activeFilters['barangay'] : 'All') ?></div>
        <div><strong>School Type:</strong> <?= e($activeFilters['school_type'] !== '' ? ucfirst((string) $activeFilters['school_type']) : 'All') ?></div>
    </div>
    <?php if (!$recipientRows): ?>
        <div class="small text-muted mt-3">No recipients found for the selected filters.</div>
        <?php
        return (string) ob_get_clean();
    endif; ?>
    <div class="mt-3">
        <div class="small text-muted text-uppercase mb-2">Matching Recipients</div>
        <div class="d-flex flex-column gap-2" id="smsRecipientNameList">
            <?php foreach ($recipientRows as $recipient): ?>
                <?php
                $fullName = trim((string) ($recipient['last_name'] ?? '') . ', ' . (string) ($recipient['first_name'] ?? ''));
                $phone = trim((string) ($recipient['phone'] ?? ''));
                ?>
                <div class="border rounded px-3 py-2 bg-light-subtle">
                    <div class="fw-semibold"><?= e($fullName !== ',' ? $fullName : '-') ?></div>
                    <?php if ($phone !== ''): ?>
                        <div class="small text-muted"><?= e($phone) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};

$searchSingleRecipients = static function (array $activeFilters, string $term, int $limit = 8) use (
    $conn,
    $buildRecipientWhereSql,
    $hasApplicationsTable,
    $hasApplicationNoColumn,
    $hasApplicationStatusColumn,
    $hasApplicationSchoolYearColumn,
    $hasApplicationSemesterColumn,
    $hasApplicationSubmittedAtColumn,
    $hasApplicationCreatedAtColumn,
    $hasUserFirstNameColumn,
    $hasUserLastNameColumn,
    $hasUserPhoneColumn
): array {
    $term = trim($term);
    if ($term === '') {
        return [];
    }

    $whereSql = $buildRecipientWhereSql($activeFilters);
    $safeLimit = max(1, min(20, $limit));
    $like = '%' . $conn->real_escape_string($term) . '%';
    $nameClauses = [];
    if ($hasUserFirstNameColumn) {
        $nameClauses[] = "u.first_name LIKE '{$like}'";
    }
    if ($hasUserLastNameColumn) {
        $nameClauses[] = "u.last_name LIKE '{$like}'";
    }
    if ($hasUserFirstNameColumn && $hasUserLastNameColumn) {
        $nameClauses[] = "CONCAT(u.first_name, ' ', u.last_name) LIKE '{$like}'";
        $nameClauses[] = "CONCAT(u.last_name, ', ', u.first_name) LIKE '{$like}'";
    }
    if ($hasUserPhoneColumn) {
        $nameClauses[] = "u.phone LIKE '{$like}'";
    }
    if (!$nameClauses) {
        return [];
    }

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

    $sql = "SELECT u.id,
                   " . ($hasUserFirstNameColumn ? 'u.first_name' : "'' AS first_name") . ",
                   " . ($hasUserLastNameColumn ? 'u.last_name' : "'' AS last_name") . ",
                   " . ($hasUserPhoneColumn ? 'u.phone' : "'' AS phone") . ",
                   " . ($hasApplicationNoColumn ? 'la.application_no' : "'' AS application_no") . ",
                   " . ($hasApplicationStatusColumn ? 'la.status' : "'' AS status") . ",
                   " . ($hasApplicationSemesterColumn ? 'la.semester' : "'' AS semester") . ",
                   " . ($hasApplicationSchoolYearColumn ? 'la.school_year' : "'' AS school_year") . "
            FROM users u
            {$latestJoin}
            WHERE {$whereSql}
              AND (" . implode(' OR ', $nameClauses) . ")
            ORDER BY u.last_name ASC, u.first_name ASC, u.id ASC
            LIMIT {$safeLimit}";

    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
};

if (isset($_GET['preview']) && $_GET['preview'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'totalRecipients' => $totalRecipients,
        'previewHtml' => $renderSmsRecipientPreviewCard($totalRecipients, $previewLimit, $filters, $previewRecipients),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['single_lookup']) && $_GET['single_lookup'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    $term = trim((string) ($_GET['q'] ?? ''));
    $matches = $searchSingleRecipients($filters, $term, 8);
    $items = array_map(static function (array $row): array {
        $fullName = trim((string) ($row['last_name'] ?? '') . ', ' . (string) ($row['first_name'] ?? ''));
        if ($fullName === ',' || $fullName === '') {
            $fullName = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
        }
        $meta = trim((string) ($row['application_no'] ?? ''));
        if (trim((string) ($row['semester'] ?? '')) !== '' || trim((string) ($row['school_year'] ?? '')) !== '') {
            $meta = trim($meta . ' ' . trim((string) ($row['semester'] ?? '') . ' ' . (string) ($row['school_year'] ?? '')));
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $fullName !== '' ? $fullName : 'Applicant',
            'phone' => trim((string) ($row['phone'] ?? '')),
            'meta' => $meta,
        ];
    }, $matches);
    echo json_encode([
        'ok' => true,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<?php
$pageHeaderEyebrow = 'Communication';
$pageHeaderTitle = '<i class="fa-solid fa-comments me-2 text-primary"></i>Communications';
$pageHeaderDescription = 'Manage SMS sending and public announcements from one page.';
$pageHeaderSecondaryInfo = '<span class="badge ' . ($providerEnabled ? 'text-bg-success' : 'text-bg-secondary') . '">' . e($providerEnabled ? 'SMS Ready' : 'SMS Disabled') . '</span>';
include __DIR__ . '/../../includes/partials/page-shell-header.php';
?>

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
        <div class="col-12 col-xl-8" id="smsComposerCol">
            <section class="card card-soft shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Send SMS</h2>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <div class="compact-kpi-card h-100">
                                <small>Status Updates</small>
                                <div class="page-shell-note mt-2">Use for application stage changes like interview notice, SOA submission required, approved for payout, and released payout.</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="compact-kpi-card h-100">
                                <small>Reminders</small>
                                <div class="page-shell-note mt-2">Use for follow-ups like missing requirements, deadline reminders, and office advisories.</div>
                            </div>
                        </div>
                    </div>
                    <form id="smsSendForm" method="post" class="row g-3" data-crud-modal="1" data-crud-title="Send SMS Message?" data-crud-message="Send this SMS message now?" data-crud-confirm-text="Send SMS" data-preview-endpoint="sms.php?preview=1">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" id="smsAction" value="send_bulk">
                        <input type="hidden" name="single_recipient_id" id="singleRecipientId" value="0">
                        <input type="hidden" id="smsTotalRecipients" value="<?= (int) $totalRecipients ?>">
                        <input type="hidden" name="period_id" value="<?= (int) ($filters['period_id'] ?? 0) ?>">

                        <div class="col-12">
                            <label class="form-label">Who Will Receive This Message?</label>
                            <div class="d-flex flex-wrap gap-3" id="smsRecipientModeWrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recipient_mode" id="smsRecipientModeBulk" value="bulk" checked>
                                    <label class="form-check-label" for="smsRecipientModeBulk">All matching recipients</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recipient_mode" id="smsRecipientModeSingle" value="single">
                                    <label class="form-check-label" for="smsRecipientModeSingle">One recipient</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12" id="smsFilterFields">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="smsStatusFilter">
                                        <option value="">All Status</option>
                                        <?php foreach ($allowedStatus as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                                                <?= e(application_status_label($status)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label">School Type</label>
                                    <select class="form-select" name="school_type">
                                        <option value="">All</option>
                                        <option value="public" <?= $filters['school_type'] === 'public' ? 'selected' : '' ?>>Public</option>
                                        <option value="private" <?= $filters['school_type'] === 'private' ? 'selected' : '' ?>>Private</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label">Barangay</label>
                                    <select class="form-select" name="barangay">
                                        <option value="">All Barangays</option>
                                        <?php foreach ($allowedBarangays as $barangay): ?>
                                            <option value="<?= e($barangay) ?>" <?= $filters['barangay'] === $barangay ? 'selected' : '' ?>><?= e($barangay) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="form-text">Recipients are limited to the current active period only.</div>
                                </div>
                            </div>
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
                                <span class="form-text">Preview the recipient scope at the right before sending important interview, SOA, or payout messages.</span>
                                <span class="small text-muted" id="smsCharCount">0 / 480</span>
                            </div>
                        </div>

                        <div class="col-12 d-none" id="smsSingleFields">
                            <label class="form-label">One Recipient</label>
                            <input type="hidden" name="single_phone" id="singlePhoneInput" value="<?= e(trim((string) ($_POST['single_phone'] ?? ''))) ?>">
                            <div class="position-relative">
                                <input
                                    type="text"
                                    class="form-control"
                                    id="singleRecipientSearch"
                                    placeholder="Type a name to search..."
                                    autocomplete="off"
                                    value=""
                                >
                                <div class="list-group position-absolute w-100 shadow-sm d-none" id="singleRecipientResults" style="z-index: 20; max-height: 260px; overflow-y: auto;"></div>
                            </div>
                            <div class="form-text">Search and select one applicant from the current active period.</div>
                            <div class="card border-0 bg-body-tertiary mt-2 d-none" id="singleRecipientSelectedCard">
                                <div class="card-body py-2 px-3">
                                    <div class="small text-uppercase text-muted fw-semibold mb-1">Selected Recipient</div>
                                    <div class="fw-semibold text-dark" id="singleRecipientSelected"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card border-0 bg-light-subtle" id="smsSendSummaryCard">
                                <div class="card-body py-2 px-3">
                                    <div class="small text-muted text-uppercase">Send Summary</div>
                                    <div class="fw-semibold" id="smsSendSummary">This message will be sent to all matching recipients.</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" id="smsPrimarySendBtn" <?= $providerEnabled ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-paper-plane me-1"></i>Send SMS
                            </button>
                            <a href="sms.php" class="btn btn-outline-secondary">Clear Filters</a>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4" id="smsRecipientPreviewCol">
            <section class="card card-soft shadow-sm h-100">
                <div class="card-body" id="smsRecipientPreviewBody">
                    <h2 class="h6 mb-3">Recipient Preview</h2>
                    <div class="small text-muted d-none mb-2" id="smsRecipientPreviewLoading">
                        <i class="fa-solid fa-rotate fa-spin me-1"></i>Refreshing recipients...
                    </div>
                    <?= $renderSmsRecipientPreviewCard($totalRecipients, $previewLimit, $filters, $previewRecipients) ?>
                </div>
            </section>
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
                        <div class="row g-4 sms-template-modal-layout">
                            <div class="col-12 col-lg-5">
                                <div class="card card-soft h-100 sms-template-editor-panel">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                            <div>
                                                <div class="sms-template-kicker">Template Editor</div>
                                                <h3 class="h5 mb-1" id="smsTemplateFormTitle">New Template</h3>
                                                <p class="text-muted small mb-0">Create short, reusable messages for common communication tasks.</p>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="smsTemplateResetBtn">
                                                <i class="fa-solid fa-rotate-left me-1"></i>New
                                            </button>
                                        </div>
                                        <form method="post" id="smsTemplateForm" class="row g-3 js-crud-modal-skip" data-crud-modal="off">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" id="smsTemplateAction" value="template_create">
                                            <input type="hidden" name="template_id" id="smsTemplateId" value="0">
                                            <input type="hidden" name="period_id" value="<?= (int) ($filters['period_id'] ?? 0) ?>">
                                            <input type="hidden" name="status" value="<?= e((string) ($filters['status'] ?? '')) ?>">
                                            <input type="hidden" name="barangay" value="<?= e((string) ($filters['barangay'] ?? '')) ?>">
                                            <input type="hidden" name="school_type" value="<?= e((string) ($filters['school_type'] ?? '')) ?>">

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
                                                <textarea class="form-control sms-template-body-input" name="template_body" id="smsTemplateBody" rows="8" maxlength="480" required></textarea>
                                                <div class="d-flex justify-content-between align-items-center mt-1 gap-2">
                                                    <div class="form-text mb-0">Keep the message clear and within 480 characters.</div>
                                                    <div class="small text-muted" id="smsTemplateBodyCount">0 / 480</div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check sms-template-active-check">
                                                    <input class="form-check-input" type="checkbox" name="template_is_active" id="smsTemplateIsActive" checked>
                                                    <label class="form-check-label" for="smsTemplateIsActive">Active template</label>
                                                </div>
                                            </div>
                                            <div class="col-12 d-flex flex-wrap gap-2 pt-1">
                                                <button type="submit" class="btn btn-primary" id="smsTemplateSubmitBtn">
                                                    <i class="fa-solid fa-floppy-disk me-1"></i>Save Template
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" id="smsTemplateCancelEditBtn">Cancel Edit</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-7">
                                <div class="card card-soft h-100 sms-template-library-panel">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                            <div>
                                                <div class="sms-template-kicker">Template Library</div>
                                                <h3 class="h5 mb-1">Saved Templates</h3>
                                                <p class="text-muted small mb-0">Pick a template to edit, or remove ones you no longer use.</p>
                                            </div>
                                            <span class="badge text-bg-light border"><?= count($smsTemplates) ?> saved</span>
                                        </div>
                                        <?php if (!$smsTemplates): ?>
                                            <div class="sms-template-empty-state">
                                                <div class="sms-template-empty-icon"><i class="fa-solid fa-comment-dots"></i></div>
                                                <div class="fw-semibold text-dark">No saved templates yet</div>
                                                <div class="small text-muted">Use the editor to create your first reusable SMS template.</div>
                                            </div>
                                        <?php else: ?>
                                            <div class="sms-template-library-list">
                                                <?php foreach ($smsTemplates as $tpl): ?>
                                                    <?php
                                                    $tplId = (int) ($tpl['id'] ?? 0);
                                                    $tplName = trim((string) ($tpl['template_name'] ?? 'Template'));
                                                    $tplCategory = trim((string) ($tpl['template_category'] ?? 'General'));
                                                    $tplBody = trim((string) ($tpl['template_body'] ?? ''));
                                                    $tplActive = (int) ($tpl['is_active'] ?? 0) === 1;
                                                    $tplUpdatedAt = trim((string) ($tpl['updated_at'] ?? $tpl['created_at'] ?? ''));
                                                    ?>
                                                    <article class="sms-template-item">
                                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                            <div>
                                                                <h4 class="sms-template-item-title mb-1"><?= e($tplName) ?></h4>
                                                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                                                    <span class="badge text-bg-light border"><?= e($tplCategory !== '' ? $tplCategory : 'General') ?></span>
                                                                    <span class="badge <?= $tplActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                                        <?= $tplActive ? 'Active' : 'Inactive' ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <?php if ($tplUpdatedAt !== ''): ?>
                                                                <div class="small text-muted text-nowrap">Updated <?= e(date('M d, Y', strtotime($tplUpdatedAt))) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="sms-template-item-body mb-3"><?= e(excerpt($tplBody, 180)) ?></p>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline-primary btn-sm sms-template-edit-btn"
                                                                data-template-id="<?= $tplId ?>"
                                                                data-template-name="<?= e($tplName) ?>"
                                                                data-template-category="<?= e($tplCategory) ?>"
                                                                data-template-body="<?= e($tplBody) ?>"
                                                                data-template-active="<?= $tplActive ? '1' : '0' ?>"
                                                            >
                                                                <i class="fa-solid fa-pen me-1"></i>Edit
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline-danger btn-sm sms-template-delete-btn"
                                                                data-template-id="<?= $tplId ?>"
                                                                data-template-name="<?= e($tplName) ?>"
                                                            >
                                                                <i class="fa-solid fa-trash-can me-1"></i>Delete
                                                            </button>
                                                        </div>
                                                    </article>
                                                <?php endforeach; ?>
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
    const totalRecipientsInput = document.getElementById('smsTotalRecipients');
    const singlePhoneInput = document.getElementById('singlePhoneInput');
    const singleRecipientSearch = document.getElementById('singleRecipientSearch');
    const singleRecipientResults = document.getElementById('singleRecipientResults');
    const singleRecipientSelected = document.getElementById('singleRecipientSelected');
    const singleRecipientSelectedCard = document.getElementById('singleRecipientSelectedCard');
    const singleRecipientIdInput = document.getElementById('singleRecipientId');
    const recipientModeInputs = document.querySelectorAll('input[name="recipient_mode"]');
    const filterFields = document.getElementById('smsFilterFields');
    const singleFields = document.getElementById('smsSingleFields');
    const summaryText = document.getElementById('smsSendSummary');
    const primarySendBtn = document.getElementById('smsPrimarySendBtn');
    const composerCol = document.getElementById('smsComposerCol');
    const previewCol = document.getElementById('smsRecipientPreviewCol');
    const previewBody = document.getElementById('smsRecipientPreviewBody');
    const useHistoryButtons = document.querySelectorAll('.sms-use-history');
    const filterInputs = form ? form.querySelectorAll('select[name="status"], select[name="barangay"], select[name="school_type"]') : [];
    const previewEndpoint = form ? String(form.getAttribute('data-preview-endpoint') || '').trim() : '';
    const singleLookupEndpoint = form ? 'sms.php?single_lookup=1' : '';

    const templateSelect = document.getElementById('smsTemplate');
    const statusFilterSelect = document.getElementById('smsStatusFilter');
    const messageBox = document.getElementById('smsMessage');
    const charCount = document.getElementById('smsCharCount');

    const templateForm = document.getElementById('smsTemplateForm');
    const templateActionInput = document.getElementById('smsTemplateAction');
    const templateIdInput = document.getElementById('smsTemplateId');
    const templateNameInput = document.getElementById('smsTemplateName');
    const templateCategoryInput = document.getElementById('smsTemplateCategory');
    const templateBodyInput = document.getElementById('smsTemplateBody');
    const templateBodyCount = document.getElementById('smsTemplateBodyCount');
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

    if (!messageBox || !charCount || !form || !actionInput) {
        return;
    }

    const templates = <?= json_encode($templateBodyMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const statusTemplateMap = {
        needs_resubmission: 'builtin:requirements_reminder',
        for_interview: 'builtin:interview_notice',
        for_soa: 'builtin:soa_reminder',
        approved_for_release: 'builtin:payout_schedule',
        released: 'builtin:payout_schedule',
    };

    const updateCount = function () {
        const length = messageBox.value.length;
        charCount.textContent = length + " / 480";
    };

    const updateTemplateBodyCount = function () {
        if (!(templateBodyInput instanceof HTMLTextAreaElement) || !(templateBodyCount instanceof HTMLElement)) {
            return;
        }
        templateBodyCount.textContent = templateBodyInput.value.length + ' / 480';
    };

    const escapeHtml = function (value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    const getRecipientMode = function () {
        let selectedMode = 'bulk';
        recipientModeInputs.forEach(function (input) {
            if (input.checked) {
                selectedMode = String(input.value || 'bulk').trim();
            }
        });
        if (selectedMode !== 'single' && selectedMode !== 'bulk') {
            return 'bulk';
        }
        return selectedMode;
    };

    const setRecipientMode = function (mode) {
        const nextMode = (mode === 'single' || mode === 'bulk') ? mode : 'bulk';
        recipientModeInputs.forEach(function (input) {
            input.checked = String(input.value || '') === nextMode;
        });
        updateRecipientModeUi();
    };

    const updateRecipientModeUi = function () {
        const mode = getRecipientMode();
        const totalRecipients = totalRecipientsInput ? parseInt(String(totalRecipientsInput.value || '0'), 10) || 0 : 0;

        actionInput.value = 'send_' + mode;

        if (filterFields) {
            filterFields.classList.toggle('d-none', mode === 'single');
        }
        if (singleFields) {
            singleFields.classList.toggle('d-none', mode !== 'single');
        }
        if (previewCol) {
            previewCol.classList.toggle('d-none', mode === 'single');
        }
        if (composerCol) {
            composerCol.classList.toggle('col-xl-8', mode !== 'single');
            composerCol.classList.toggle('col-xl-12', mode === 'single');
        }

        if (summaryText) {
            if (mode === 'single') {
                const recipientLabel = singleRecipientSelected ? String(singleRecipientSelected.textContent || '').trim() : '';
                summaryText.textContent = recipientLabel !== ''
                    ? 'This message will be sent to: ' + recipientLabel + '.'
                    : 'Select one recipient to send this message.';
            } else {
                summaryText.textContent = 'This message will be sent to ' + totalRecipients + ' matching recipient' + (totalRecipients === 1 ? '' : 's') + '.';
            }
        }

        if (primarySendBtn) {
            if (mode === 'single') {
                primarySendBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Send To One Recipient';
            } else {
                primarySendBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Send To All Matching Recipients';
            }
        }
    };

    let previewRequestToken = 0;
    const fetchRecipientPreview = function () {
        if (!form || !previewEndpoint || !previewBody) {
            return;
        }

        const currentToken = ++previewRequestToken;
        const params = new URLSearchParams();
        filterInputs.forEach(function (input) {
            const name = String(input.getAttribute('name') || '').trim();
            if (name === '') {
                return;
            }
            params.set(name, String(input.value || ''));
        });

        previewBody.classList.add('opacity-50');
        const currentLoading = document.getElementById('smsRecipientPreviewLoading');
        if (currentLoading) {
            currentLoading.classList.remove('d-none');
        }

        fetch(previewEndpoint + '&' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Preview request failed.');
                }
                return response.json();
            })
            .then(function (payload) {
                if (currentToken !== previewRequestToken || !payload || payload.ok !== true) {
                    return;
                }
                if (typeof payload.totalRecipients === 'number' && totalRecipientsInput) {
                    totalRecipientsInput.value = String(payload.totalRecipients);
                }
                if (typeof payload.previewHtml === 'string') {
                    previewBody.innerHTML = '<h2 class="h6 mb-3">Recipient Preview</h2>'
                        + '<div class="small text-muted d-none mb-2" id="smsRecipientPreviewLoading"><i class="fa-solid fa-rotate fa-spin me-1"></i>Refreshing recipients...</div>'
                        + payload.previewHtml;
                }
                updateRecipientModeUi();
            })
            .catch(function () {
                // Keep the last rendered state if preview refresh fails.
            })
            .finally(function () {
                if (currentToken === previewRequestToken) {
                    previewBody.classList.remove('opacity-50');
                    const nextLoading = document.getElementById('smsRecipientPreviewLoading');
                    if (nextLoading) {
                        nextLoading.classList.add('d-none');
                    }
                }
            });
    };

    updateCount();
    updateTemplateBodyCount();
    messageBox.addEventListener('input', updateCount);
    if (templateBodyInput instanceof HTMLTextAreaElement) {
        templateBodyInput.addEventListener('input', updateTemplateBodyCount);
    }
    recipientModeInputs.forEach(function (input) {
        input.addEventListener('change', updateRecipientModeUi);
    });
    filterInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            fetchRecipientPreview();
        });
    });

    if (singlePhoneInput && singleRecipientIdInput) {
        const renderSingleRecipientResults = function (items) {
            if (!(singleRecipientResults instanceof HTMLElement)) {
                return;
            }
            if (!Array.isArray(items) || items.length === 0) {
                singleRecipientResults.innerHTML = '<div class="list-group-item small text-muted">No matching recipient found.</div>';
                singleRecipientResults.classList.remove('d-none');
                return;
            }

            singleRecipientResults.innerHTML = items.map(function (item) {
                const name = String(item.name || 'Applicant');
                const phone = String(item.phone || '');
                const meta = String(item.meta || '');
                return '<button type="button" class="list-group-item list-group-item-action sms-single-result"'
                    + ' data-id="' + String(item.id || 0) + '"'
                    + ' data-name="' + escapeHtml(name) + '"'
                    + ' data-phone="' + escapeHtml(phone) + '">'
                    + '<div class="fw-semibold">' + escapeHtml(name) + '</div>'
                    + (phone !== '' ? '<div class="small text-muted">' + escapeHtml(phone) + '</div>' : '')
                    + (meta !== '' ? '<div class="small text-muted">' + escapeHtml(meta) + '</div>' : '')
                    + '</button>';
            }).join('');
            singleRecipientResults.classList.remove('d-none');

            singleRecipientResults.querySelectorAll('.sms-single-result').forEach(function (button) {
                button.addEventListener('click', function () {
                    const name = String(button.getAttribute('data-name') || '').trim();
                    const phone = String(button.getAttribute('data-phone') || '').trim();
                    const id = String(button.getAttribute('data-id') || '0').trim();
                    if (singleRecipientSearch instanceof HTMLInputElement) {
                        singleRecipientSearch.value = name;
                    }
                    singleRecipientIdInput.value = id;
                    singlePhoneInput.value = phone;
                    if (singleRecipientSelected instanceof HTMLElement) {
                        singleRecipientSelected.textContent = phone !== '' ? (name + ' (' + phone + ')') : name;
                    }
                    if (singleRecipientSelectedCard instanceof HTMLElement) {
                        singleRecipientSelectedCard.classList.remove('d-none');
                    }
                    singleRecipientResults.classList.add('d-none');
                    updateRecipientModeUi();
                });
            });
        };

        let singleLookupTimer = null;
        if (singleRecipientSearch instanceof HTMLInputElement) {
            singleRecipientSearch.addEventListener('input', function () {
                singleRecipientIdInput.value = '0';
                singlePhoneInput.value = '';
                if (singleRecipientSelected instanceof HTMLElement) {
                    singleRecipientSelected.textContent = '';
                }
                if (singleRecipientSelectedCard instanceof HTMLElement) {
                    singleRecipientSelectedCard.classList.add('d-none');
                }
                updateRecipientModeUi();

                const term = String(singleRecipientSearch.value || '').trim();
                if (singleLookupTimer) {
                    clearTimeout(singleLookupTimer);
                }
                if (term.length < 2) {
                    if (singleRecipientResults instanceof HTMLElement) {
                        singleRecipientResults.classList.add('d-none');
                        singleRecipientResults.innerHTML = '';
                    }
                    return;
                }

                singleLookupTimer = setTimeout(function () {
                    const params = new URLSearchParams();
                    params.set('q', term);
                    filterInputs.forEach(function (input) {
                        const name = String(input.getAttribute('name') || '').trim();
                        if (name !== '') {
                            params.set(name, String(input.value || ''));
                        }
                    });

                    fetch(singleLookupEndpoint + '&' + params.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Lookup failed.');
                            }
                            return response.json();
                        })
                        .then(function (payload) {
                            renderSingleRecipientResults(Array.isArray(payload.items) ? payload.items : []);
                        })
                        .catch(function () {
                            renderSingleRecipientResults([]);
                        });
                }, 180);
            });

            singleRecipientSearch.addEventListener('blur', function () {
                window.setTimeout(function () {
                    if (singleRecipientResults instanceof HTMLElement) {
                        singleRecipientResults.classList.add('d-none');
                    }
                }, 160);
            });
        }
    }

    form.addEventListener('submit', function (event) {
        const mode = getRecipientMode();
        actionInput.value = 'send_' + mode;
        syncSelectedIds();

        if (mode === 'single') {
            if (singleRecipientIdInput && parseInt(String(singleRecipientIdInput.value || '0'), 10) <= 0) {
                event.preventDefault();
                if (typeof window.showAlertModal === 'function') {
                    window.showAlertModal({
                        title: 'Recipient Required',
                        message: 'Search and select one recipient first.',
                        kind: 'warning',
                    });
                } else {
                    alert('Search and select one recipient first.');
                }
                return;
            }
        }

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

    updateRecipientModeUi();

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
        updateTemplateBodyCount();
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
            updateTemplateBodyCount();
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

    const applyTemplateSelection = function (key, forceReplace) {
        if (!templateSelect || !key || !templates[key]) {
            return false;
        }

        const currentText = String(messageBox.value || '').trim();
        if (currentText !== '' && currentText !== templates[key]) {
            if (!forceReplace) {
                return false;
            }
            const shouldReplace = window.confirm('Replace current message with the selected template?');
            if (!shouldReplace) {
                return false;
            }
        }

        templateSelect.value = key;
        messageBox.value = templates[key];
        updateCount();
        messageBox.focus();
        return true;
    };

    if (statusFilterSelect instanceof HTMLSelectElement) {
        statusFilterSelect.addEventListener('change', function () {
            const statusKey = String(statusFilterSelect.value || '').trim();
            const templateKey = statusTemplateMap[statusKey] || '';
            const currentText = String(messageBox.value || '').trim();
            if (templateKey === '' || currentText !== '') {
                return;
            }
            applyTemplateSelection(templateKey, false);
        });
    }

    if (!templateSelect) {
        return;
    }

    templateSelect.addEventListener('change', function () {
        const key = String(templateSelect.value || '').trim();
        if (!key || !templates[key]) {
            return;
        }
        const applied = applyTemplateSelection(key, true);
        if (!applied) {
            templateSelect.value = '';
        }
    });
});
</script>

<?php if ($isAdminUser): ?>
    <div class="card card-soft shadow-sm mt-4" id="announcements">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h6 mb-0">Announcements</h2>
                    <div class="small text-muted">Manage public announcements from the same communications page.</div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#announcementEditorModal" data-announcement-mode="create">
                    <i class="fa-solid fa-plus me-1"></i>New Announcement
                </button>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <?php if (!$adminAnnouncements): ?>
                        <p class="text-muted mb-0">No announcements yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0" data-simple-list="1" data-simple-list-visible="3" data-simple-list-title-selector="strong">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adminAnnouncements as $announcement): ?>
                                        <?php
                                        $authorName = trim((string) (($announcement['first_name'] ?? '') . ' ' . ($announcement['last_name'] ?? '')));
                                        if ($authorName === '') {
                                            $authorName = 'System';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= e((string) ($announcement['title'] ?? 'Announcement')) ?></strong>
                                                <div class="small text-muted"><?= e(excerpt((string) ($announcement['content'] ?? ''), 110)) ?></div>
                                                <div class="small text-muted"><?= e($authorName) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?= (int) ($announcement['is_active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                    <?= (int) ($announcement['is_active'] ?? 0) === 1 ? 'Published' : 'Archived' ?>
                                                </span>
                                            </td>
                                            <td><?= e(date('M d, Y', strtotime((string) ($announcement['created_at'] ?? 'now')))) ?></td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#announcementEditorModal"
                                                        data-announcement-mode="edit"
                                                        data-announcement-id="<?= (int) ($announcement['id'] ?? 0) ?>"
                                                        data-announcement-title="<?= e((string) ($announcement['title'] ?? '')) ?>"
                                                        data-announcement-content="<?= e((string) ($announcement['content'] ?? '')) ?>"
                                                        data-announcement-active="<?= (int) ($announcement['is_active'] ?? 0) ?>"
                                                    >Edit</button>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="announcement_toggle">
                                                        <input type="hidden" name="announcement_id" value="<?= (int) ($announcement['id'] ?? 0) ?>">
                                                        <input type="hidden" name="new_status" value="<?= (int) ($announcement['is_active'] ?? 0) === 1 ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                                            <?= (int) ($announcement['is_active'] ?? 0) === 1 ? 'Archive' : 'Publish' ?>
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="announcement_delete">
                                                        <input type="hidden" name="announcement_id" value="<?= (int) ($announcement['id'] ?? 0) ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                                    </form>
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

    <div class="modal fade modal-se" id="announcementEditorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header border-0 pb-0">
                        <div class="modal-se-title-wrap">
                            <span class="modal-se-icon is-info"><i class="fa-solid fa-bullhorn"></i></span>
                            <div>
                                <h2 class="modal-title h5 mb-0" id="announcementEditorTitle">New Announcement</h2>
                                <small class="text-muted">Create a public announcement and optionally send it by SMS.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" id="announcementEditorAction" value="announcement_create">
                        <input type="hidden" name="announcement_id" id="announcementEditorId" value="0">
                        <div class="row g-3 announcement-editor-layout">
                            <div class="col-12 col-lg-5">
                                <div class="card card-soft h-100 announcement-editor-side">
                                    <div class="card-body">
                                        <div class="announcement-editor-kicker">Announcement Setup</div>
                                        <div class="mb-3">
                                            <label class="form-label">Template</label>
                                            <select class="form-select" id="announcementEditorTemplate">
                                                <option value="">Custom</option>
                                                <option value="application_open">Application Period Open</option>
                                                <option value="deadline_extension">Deadline Extension</option>
                                                <option value="requirements_update">Requirements Update</option>
                                                <option value="interview_schedule">Interview Schedule Notice</option>
                                                <option value="results_release">Results / Status Notice</option>
                                                <option value="soa_reminder">SOA / Student Copy Reminder</option>
                                                <option value="payout_advisory">Payout Schedule Advisory</option>
                                                <option value="office_advisory">Office Advisory</option>
                                            </select>
                                        </div>
                                        <div class="announcement-editor-checks">
                                            <div class="form-check announcement-editor-check">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="announcementEditorActive" checked>
                                                <label class="form-check-label" for="announcementEditorActive">Publish now</label>
                                            </div>
                                            <div class="form-check announcement-editor-check">
                                                <input class="form-check-input" type="checkbox" name="send_sms" id="announcementEditorSendSms">
                                                <label class="form-check-label" for="announcementEditorSendSms">Send SMS to applicants</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-7">
                                <div class="card card-soft h-100 announcement-editor-main">
                                    <div class="card-body">
                                        <div class="announcement-editor-kicker">Announcement Content</div>
                                        <div class="mb-3">
                                            <label class="form-label">Title</label>
                                            <input type="text" class="form-control" name="title" id="announcementEditorTitleInput" required>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label">Content</label>
                                            <textarea class="form-control announcement-editor-content" name="content" id="announcementEditorContentInput" rows="10" required></textarea>
                                            <div class="d-flex justify-content-between align-items-center mt-1 gap-2">
                                                <div class="form-text mb-0">Keep the message clear and ready for public posting.</div>
                                                <div class="small text-muted" id="announcementEditorContentCount">0 characters</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="announcementEditorSubmit">Save Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const requirementTemplates = <?= json_encode($announcementRequirementTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const editorModal = document.getElementById('announcementEditorModal');
        const templateSelect = document.getElementById('announcementEditorTemplate');
        const announcementContentInput = document.getElementById('announcementEditorContentInput');
        const announcementContentCount = document.getElementById('announcementEditorContentCount');

        function updateAnnouncementContentCount() {
            if (!(announcementContentInput instanceof HTMLTextAreaElement) || !(announcementContentCount instanceof HTMLElement)) {
                return;
            }
            const length = announcementContentInput.value.length;
            announcementContentCount.textContent = length + ' character' + (length === 1 ? '' : 's');
        }

        function buildRequirementsBlock() {
            if (!Array.isArray(requirementTemplates) || requirementTemplates.length === 0) {
                return 'Required Documents:\n- No active requirements configured yet.';
            }

            return ['Required Documents:'].concat(requirementTemplates.map(function (req, index) {
                let line = '- ' + (index + 1) + '. ' + String((req && req.requirement_name) || 'Requirement').trim();
                const description = String((req && req.description) || '').trim();
                if (Number((req && req.is_required) || 1) !== 1) {
                    line += ' (Optional)';
                }
                if (description !== '') {
                    line += ' - ' + description;
                }
                return line;
            })).join('\n');
        }

        const requirementsBlock = buildRequirementsBlock();
        const templateFactory = {
            application_open: { title: 'Application Period Open - [Semester] [School Year]', content: 'Applications are now open for [Semester] [School Year].\n\n' + requirementsBlock + '\n\nDeadline: [Date]' },
            deadline_extension: { title: 'Deadline Extension - [Semester] [School Year]', content: 'The submission deadline has been extended.\n\nPrevious Deadline: [Old Date]\nNew Deadline: [New Date]' },
            requirements_update: { title: 'Requirements Update - [Semester] [School Year]', content: 'Please review the updated checklist.\n\n' + requirementsBlock },
            interview_schedule: { title: 'Interview Schedule Notice', content: 'Interview Date: [Date]\nTime: [Time]\nVenue: [Location]' },
            results_release: { title: 'Application Status Notice', content: 'Please log in to your account to check your latest application status and next steps.' },
            soa_reminder: { title: 'SOA / Student Copy Reminder', content: 'Please submit your SOA / Student Copy on or before [Deadline].' },
            payout_advisory: { title: 'Payout Schedule Advisory', content: 'Payout Date: [Date]\nTime: [Time]\nVenue: [Location]\n\nBring a valid ID and follow your assigned schedule.' },
            office_advisory: { title: 'Office Advisory', content: 'Please be informed of the following advisory:\n\n[Details]' }
        };

        if (templateSelect instanceof HTMLSelectElement) {
            templateSelect.addEventListener('change', function () {
                const key = String(templateSelect.value || '');
                const titleInput = document.getElementById('announcementEditorTitleInput');
                const contentInput = document.getElementById('announcementEditorContentInput');
                if (!(titleInput instanceof HTMLInputElement) || !(contentInput instanceof HTMLTextAreaElement)) {
                    return;
                }
                if (!key || !templateFactory[key]) {
                    return;
                }
                titleInput.value = templateFactory[key].title;
                contentInput.value = templateFactory[key].content;
                updateAnnouncementContentCount();
            });
        }

        updateAnnouncementContentCount();
        if (announcementContentInput instanceof HTMLTextAreaElement) {
            announcementContentInput.addEventListener('input', updateAnnouncementContentCount);
        }

        if (editorModal instanceof HTMLElement) {
            editorModal.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                if (!(trigger instanceof HTMLElement)) {
                    return;
                }
                const mode = String(trigger.getAttribute('data-announcement-mode') || 'create');
                const titleText = document.getElementById('announcementEditorTitle');
                const actionInput = document.getElementById('announcementEditorAction');
                const idInput = document.getElementById('announcementEditorId');
                const titleInput = document.getElementById('announcementEditorTitleInput');
                const contentInput = document.getElementById('announcementEditorContentInput');
                const activeInput = document.getElementById('announcementEditorActive');
                const sendSmsInput = document.getElementById('announcementEditorSendSms');
                const submitButton = document.getElementById('announcementEditorSubmit');
                const templateInput = document.getElementById('announcementEditorTemplate');

                if (trigger.getAttribute('data-announcement-mode') === 'create') {
                    if (titleText) titleText.textContent = 'New Announcement';
                    if (actionInput) actionInput.value = 'announcement_create';
                    if (idInput) idInput.value = '0';
                    if (titleInput) titleInput.value = '';
                    if (contentInput) contentInput.value = '';
                    if (activeInput) activeInput.checked = true;
                    if (sendSmsInput) sendSmsInput.checked = false;
                    if (submitButton) submitButton.textContent = 'Save Announcement';
                    if (templateInput instanceof HTMLSelectElement) templateInput.value = '';
                    updateAnnouncementContentCount();
                    return;
                }

                if (titleText) titleText.textContent = mode === 'edit' ? 'Edit Announcement' : 'New Announcement';
                if (actionInput) actionInput.value = mode === 'edit' ? 'announcement_update' : 'announcement_create';
                if (idInput) idInput.value = mode === 'edit' ? String(trigger.getAttribute('data-announcement-id') || '0') : '0';
                if (titleInput) titleInput.value = mode === 'edit' ? String(trigger.getAttribute('data-announcement-title') || '') : '';
                if (contentInput) contentInput.value = mode === 'edit' ? String(trigger.getAttribute('data-announcement-content') || '') : '';
                if (activeInput) activeInput.checked = mode === 'edit' ? String(trigger.getAttribute('data-announcement-active') || '0') === '1' : true;
                if (sendSmsInput) sendSmsInput.checked = false;
                if (submitButton) submitButton.textContent = mode === 'edit' ? 'Save Changes' : 'Save Announcement';
                if (templateInput instanceof HTMLSelectElement) templateInput.value = '';
                updateAnnouncementContentCount();
            });
        }
    });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
