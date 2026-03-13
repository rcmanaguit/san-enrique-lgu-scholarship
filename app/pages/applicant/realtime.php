<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function realtime_schema_snapshot(mysqli $conn, array $tableColumns): array
{
    $schemaKey = 'realtime_schema_snapshot_v1';
    $schemaTtl = 600;
    $now = time();
    $cached = $_SESSION[$schemaKey] ?? null;
    if (
        is_array($cached)
        && (int) ($cached['expires_at'] ?? 0) > $now
        && is_array($cached['tables'] ?? null)
        && is_array($cached['columns'] ?? null)
    ) {
        return $cached;
    }

    $tables = [];
    $columns = [];
    foreach ($tableColumns as $table => $requiredColumns) {
        $table = strtolower(trim((string) $table));
        if ($table === '') {
            continue;
        }
        $tableExists = table_exists($conn, $table);
        if ($tableExists) {
            $tables[$table] = true;
        }
        foreach ($requiredColumns as $column) {
            $column = strtolower(trim((string) $column));
            if ($column === '') {
                continue;
            }
            if ($tableExists && table_column_exists($conn, $table, $column)) {
                $columns[$table . '.' . $column] = true;
            }
        }
    }

    $snapshot = [
        'tables' => $tables,
        'columns' => $columns,
        'expires_at' => $now + $schemaTtl,
    ];
    $_SESSION[$schemaKey] = $snapshot;
    return $snapshot;
}

/**
 * Runs a trusted SQL query that returns one row of integer aggregate fields.
 */
function realtime_aggregate_row(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_assoc() ?: [];
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$userRole = trim((string) ($user['role'] ?? 'guest'));
$isLoggedIn = is_logged_in();

$unreadNotifications = 0;
$unreadLabel = '0';
$timestamps = [];

if (db_ready()) {
    $requiredColumns = [
        'announcements' => ['created_at'],
        'application_periods' => ['updated_at'],
        'requirement_templates' => ['created_at'],
    ];

    if ($isLoggedIn && $userId > 0) {
        $requiredColumns['notifications'] = ['created_at', 'is_read', 'read_at', 'user_id'];
    }

    if ($isLoggedIn && in_array($userRole, ['admin', 'staff'], true)) {
        $requiredColumns['applications'] = ['updated_at'];
        $requiredColumns['application_documents'] = ['uploaded_at'];
        $requiredColumns['disbursements'] = ['created_at'];
        $requiredColumns['sms_logs'] = ['created_at'];
        $requiredColumns['audit_logs'] = ['created_at'];
    } elseif ($isLoggedIn && $userRole === 'applicant' && $userId > 0) {
        $requiredColumns['applications'] = ['updated_at', 'user_id'];
        $requiredColumns['disbursements'] = ['application_id', 'created_at'];
    }

    $schemaSnapshot = realtime_schema_snapshot($conn, $requiredColumns);
    $existingTables = (array) ($schemaSnapshot['tables'] ?? []);
    $existingColumns = (array) ($schemaSnapshot['columns'] ?? []);

    $selects = [];
    if (isset($existingColumns['announcements.created_at'])) {
        $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM announcements) AS announcements_ts";
    }
    if (isset($existingColumns['application_periods.updated_at'])) {
        $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(updated_at)) FROM application_periods) AS periods_ts";
    }
    if (isset($existingColumns['requirement_templates.created_at'])) {
        $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM requirement_templates) AS requirements_ts";
    }

    if (
        $isLoggedIn
        && $userId > 0
        && isset($existingTables['notifications'])
        && isset($existingColumns['notifications.is_read'])
        && isset($existingColumns['notifications.user_id'])
    ) {
        $selects[] = "(SELECT SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END)
                       FROM notifications
                       WHERE user_id = {$userId}) AS unread_total";
        if (isset($existingColumns['notifications.created_at'])) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(created_at))
                           FROM notifications
                           WHERE user_id = {$userId}) AS notifications_created_ts";
        }
        if (isset($existingColumns['notifications.read_at'])) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(read_at))
                           FROM notifications
                           WHERE user_id = {$userId}) AS notifications_read_ts";
        }
    }

    if ($isLoggedIn && in_array($userRole, ['admin', 'staff'], true)) {
        if (isset($existingColumns['applications.updated_at'])) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(updated_at)) FROM applications) AS applications_ts";
        }
        if (isset($existingColumns['application_documents.uploaded_at'])) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(uploaded_at)) FROM application_documents) AS documents_ts";
        }
        if (isset($existingColumns['disbursements.created_at'])) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM disbursements) AS disbursements_ts";
        }
        if (isset($existingColumns['sms_logs.created_at'])) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM sms_logs) AS sms_logs_ts";
        }
        if (isset($existingColumns['audit_logs.created_at'])) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM audit_logs) AS audit_logs_ts";
        }
    } elseif ($isLoggedIn && $userRole === 'applicant' && $userId > 0) {
        if (isset($existingColumns['applications.updated_at'])) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(updated_at))
                           FROM applications
                           WHERE user_id = {$userId}) AS applications_ts";
        }
        if (
            isset($existingTables['applications'])
            && isset($existingTables['disbursements'])
            && isset($existingColumns['applications.user_id'])
            && isset($existingColumns['disbursements.application_id'])
            && isset($existingColumns['disbursements.created_at'])
        ) {
            $selects[] = "(SELECT UNIX_TIMESTAMP(MAX(d.created_at))
                           FROM disbursements d
                           INNER JOIN applications a ON a.id = d.application_id
                           WHERE a.user_id = {$userId}) AS disbursements_ts";
        }
    }

    if ($selects !== []) {
        $aggregateRow = realtime_aggregate_row($conn, 'SELECT ' . implode(",\n", $selects));
        $unreadNotifications = max(0, (int) ($aggregateRow['unread_total'] ?? 0));
        $unreadLabel = $unreadNotifications > 99 ? '99+' : (string) $unreadNotifications;
        foreach ($aggregateRow as $field => $value) {
            if ($field === 'unread_total') {
                continue;
            }
            $timestamps[] = max(0, (int) $value);
        }
    }
}

$maxTimestamp = 0;
foreach ($timestamps as $ts) {
    $maxTimestamp = max($maxTimestamp, (int) $ts);
}

$tokenData = [
    'role' => $userRole,
    'user_id' => $isLoggedIn ? $userId : 0,
    'max_ts' => $maxTimestamp,
    'unread' => $unreadNotifications,
];
$changeToken = hash('sha256', json_encode($tokenData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '0');

echo json_encode([
    'ok' => true,
    'logged_in' => $isLoggedIn,
    'user_role' => $userRole,
    'unread_notifications' => $unreadNotifications,
    'unread_label' => $unreadLabel,
    'max_timestamp' => $maxTimestamp,
    'change_token' => $changeToken,
    'server_time' => date('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

