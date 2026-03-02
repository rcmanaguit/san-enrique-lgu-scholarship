<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Returns UNIX timestamp for MAX(column) from table, optionally filtered.
 * The table/column values are trusted constants from this file.
 */
function realtime_max_timestamp(mysqli $conn, string $table, string $column, string $whereSql = ''): int
{
    if (!table_exists($conn, $table) || !table_column_exists($conn, $table, $column)) {
        return 0;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return 0;
    }

    $sql = "SELECT UNIX_TIMESTAMP(MAX({$column})) AS ts FROM {$table}";
    if ($whereSql !== '') {
        $sql .= ' WHERE ' . $whereSql;
    }

    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return max(0, (int) ($row['ts'] ?? 0));
}

/**
 * Same as realtime_max_timestamp but for a custom SQL query that returns `ts`.
 */
function realtime_custom_timestamp(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return max(0, (int) ($row['ts'] ?? 0));
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$userRole = trim((string) ($user['role'] ?? 'guest'));
$isLoggedIn = is_logged_in();

$unreadNotifications = 0;
$unreadLabel = '0';
$timestamps = [];

if (db_ready()) {
    // Publicly visible data.
    $timestamps[] = realtime_max_timestamp($conn, 'announcements', 'created_at');
    $timestamps[] = realtime_max_timestamp($conn, 'application_periods', 'updated_at');
    $timestamps[] = realtime_max_timestamp($conn, 'requirement_templates', 'created_at');

    if ($isLoggedIn && $userId > 0 && table_exists($conn, 'notifications')) {
        $unreadNotifications = unread_notification_count($conn, $userId);
        $unreadLabel = $unreadNotifications > 99 ? '99+' : (string) $unreadNotifications;

        $timestamps[] = realtime_max_timestamp($conn, 'notifications', 'created_at', 'user_id = ' . $userId);
        if (table_column_exists($conn, 'notifications', 'read_at')) {
            $timestamps[] = realtime_max_timestamp($conn, 'notifications', 'read_at', 'user_id = ' . $userId);
        }
    }

    if ($isLoggedIn && in_array($userRole, ['admin', 'staff'], true)) {
        // Admin/staff should refresh when any operational data changes.
        $timestamps[] = realtime_max_timestamp($conn, 'applications', 'updated_at');
        $timestamps[] = realtime_max_timestamp($conn, 'application_documents', 'uploaded_at');
        $timestamps[] = realtime_max_timestamp($conn, 'disbursements', 'created_at');
        $timestamps[] = realtime_max_timestamp($conn, 'qr_scan_logs', 'created_at');
        $timestamps[] = realtime_max_timestamp($conn, 'sms_logs', 'created_at');
        $timestamps[] = realtime_max_timestamp($conn, 'audit_logs', 'created_at');
    } elseif ($isLoggedIn && $userRole === 'applicant' && $userId > 0) {
        // Applicant should refresh when own data changes.
        if (table_exists($conn, 'applications') && table_column_exists($conn, 'applications', 'updated_at')) {
            $timestamps[] = realtime_max_timestamp($conn, 'applications', 'updated_at', 'user_id = ' . $userId);
        }

        if (table_exists($conn, 'disbursements') && table_exists($conn, 'applications')) {
            $timestamps[] = realtime_custom_timestamp(
                $conn,
                "SELECT UNIX_TIMESTAMP(MAX(d.created_at)) AS ts
                 FROM disbursements d
                 INNER JOIN applications a ON a.id = d.application_id
                 WHERE a.user_id = " . $userId
            );
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

