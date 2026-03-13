<?php
declare(strict_types=1);

function in_array_safe(string $needle, array $haystack): bool
{
    return in_array($needle, $haystack, true);
}

function request_ip_address(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['HTTP_CLIENT_IP'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') {
            continue;
        }
        $parts = explode(',', $candidate);
        foreach ($parts as $part) {
            $ip = trim($part);
            if ($ip !== '') {
                return $ip;
            }
        }
    }

    return '';
}

function audit_logs_table_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = table_exists($conn, 'audit_logs');
    return $ready;
}

function audit_log(
    mysqli $conn,
    string $action,
    ?int $userId = null,
    ?string $userRole = null,
    ?string $entityType = null,
    ?string $entityId = null,
    ?string $description = null,
    array $metadata = []
): void {
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno) {
        return;
    }
    if (!audit_logs_table_ready($conn)) {
        return;
    }

    $action = trim($action);
    if ($action === '') {
        return;
    }

    $currentUser = current_user();
    if ($userId === null && is_array($currentUser)) {
        $userId = (int) ($currentUser['id'] ?? 0);
        if ($userId <= 0) {
            $userId = null;
        }
    }
    if ($userRole === null && is_array($currentUser)) {
        $userRole = trim((string) ($currentUser['role'] ?? ''));
    }

    $userRole = trim((string) ($userRole ?? ''));
    $entityType = trim((string) ($entityType ?? ''));
    $entityId = trim((string) ($entityId ?? ''));
    $description = trim((string) ($description ?? ''));

    if ($userRole !== '') {
        $userRole = function_exists('mb_substr') ? mb_substr($userRole, 0, 20) : substr($userRole, 0, 20);
    } else {
        $userRole = null;
    }
    if ($entityType !== '') {
        $entityType = function_exists('mb_substr') ? mb_substr($entityType, 0, 80) : substr($entityType, 0, 80);
    } else {
        $entityType = null;
    }
    if ($entityId !== '') {
        $entityId = function_exists('mb_substr') ? mb_substr($entityId, 0, 80) : substr($entityId, 0, 80);
    } else {
        $entityId = null;
    }
    if ($description !== '') {
        $description = function_exists('mb_substr') ? mb_substr($description, 0, 255) : substr($description, 0, 255);
    } else {
        $description = null;
    }

    $ipAddress = request_ip_address();
    if ($ipAddress !== '') {
        $ipAddress = function_exists('mb_substr') ? mb_substr($ipAddress, 0, 45) : substr($ipAddress, 0, 45);
    } else {
        $ipAddress = null;
    }
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent !== '') {
        $userAgent = function_exists('mb_substr') ? mb_substr($userAgent, 0, 255) : substr($userAgent, 0, 255);
    } else {
        $userAgent = null;
    }

    $metadataJson = null;
    if ($metadata !== []) {
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metadataJson === false) {
            $metadataJson = null;
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO audit_logs
         (user_id, user_role, action, entity_type, entity_id, description, metadata_json, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        'issssssss',
        $userId,
        $userRole,
        $action,
        $entityType,
        $entityId,
        $description,
        $metadataJson,
        $ipAddress,
        $userAgent
    );
    $stmt->execute();
    $stmt->close();
}

function notifications_table_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = table_exists($conn, 'notifications');
    return $ready;
}

function create_notification(
    mysqli $conn,
    int $userId,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $relatedUrl = null,
    ?int $createdByUserId = null
): void {
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno) {
        return;
    }
    if (!notifications_table_ready($conn) || $userId <= 0) {
        return;
    }

    $title = trim($title);
    $message = trim($message);
    $notificationType = trim(strtolower($notificationType));
    $relatedUrl = trim((string) $relatedUrl);

    if ($title === '' || $message === '') {
        return;
    }

    if ($notificationType === '') {
        $notificationType = 'system';
    }

    $title = function_exists('mb_substr') ? mb_substr($title, 0, 180) : substr($title, 0, 180);
    $notificationType = function_exists('mb_substr') ? mb_substr($notificationType, 0, 40) : substr($notificationType, 0, 40);
    if ($relatedUrl !== '') {
        $relatedUrl = function_exists('mb_substr') ? mb_substr($relatedUrl, 0, 255) : substr($relatedUrl, 0, 255);
    } else {
        $relatedUrl = null;
    }

    $createdBy = ($createdByUserId !== null && $createdByUserId > 0) ? $createdByUserId : null;

    if ($createdBy === null) {
        $stmt = $conn->prepare(
            "INSERT INTO notifications
             (user_id, title, message, notification_type, related_url, created_by)
             VALUES (?, ?, ?, ?, ?, NULL)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('issss', $userId, $title, $message, $notificationType, $relatedUrl);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO notifications
             (user_id, title, message, notification_type, related_url, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('issssi', $userId, $title, $message, $notificationType, $relatedUrl, $createdBy);
    }

    $stmt->execute();
    $stmt->close();
}

function create_notifications_for_roles(
    mysqli $conn,
    array $roles,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $relatedUrl = null,
    ?int $createdByUserId = null
): int {
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno) {
        return 0;
    }
    if (!notifications_table_ready($conn)) {
        return 0;
    }

    $allowedRoles = ['admin', 'staff', 'applicant'];
    $roles = array_values(array_unique(array_filter(
        $roles,
        static fn($role): bool => in_array((string) $role, $allowedRoles, true)
    )));
    if ($roles === []) {
        return 0;
    }

    $quotedRoles = [];
    foreach ($roles as $role) {
        $quotedRoles[] = "'" . $conn->real_escape_string((string) $role) . "'";
    }
    $sql = "SELECT id FROM users WHERE status = 'active' AND role IN (" . implode(', ', $quotedRoles) . ")";
    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        return 0;
    }

    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $targetUserId = (int) ($row['id'] ?? 0);
        if ($targetUserId <= 0) {
            continue;
        }
        create_notification(
            $conn,
            $targetUserId,
            $title,
            $message,
            $notificationType,
            $relatedUrl,
            $createdByUserId
        );
        $count++;
    }

    return $count;
}

function unread_notification_count(mysqli $conn, int $userId): int
{
    static $cache = [];

    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno || $userId <= 0) {
        return 0;
    }
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    if (!notifications_table_ready($conn)) {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM notifications
         WHERE user_id = ?
           AND is_read = 0"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    $cache[$userId] = (int) ($row['total'] ?? 0);
    return $cache[$userId];
}

function list_notifications(mysqli $conn, int $userId, int $limit = 30, bool $unreadOnly = false): array
{
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno || $userId <= 0) {
        return [];
    }
    if (!notifications_table_ready($conn)) {
        return [];
    }

    $limit = max(1, min(200, $limit));

    if ($unreadOnly) {
        $stmt = $conn->prepare(
            "SELECT id, title, message, notification_type, related_url, is_read, read_at, created_at
             FROM notifications
             WHERE user_id = ?
               AND is_read = 0
             ORDER BY created_at DESC, id DESC
             LIMIT " . (int) $limit
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT id, title, message, notification_type, related_url, is_read, read_at, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT " . (int) $limit
        );
    }
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function mark_notification_read(mysqli $conn, int $notificationId, int $userId): bool
{
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno || $notificationId <= 0 || $userId <= 0) {
        return false;
    }
    if (!notifications_table_ready($conn)) {
        return false;
    }

    $stmt = $conn->prepare(
        "UPDATE notifications
         SET is_read = 1,
             read_at = COALESCE(read_at, NOW())
         WHERE id = ?
           AND user_id = ?
           AND is_read = 0
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows > 0;
    $stmt->close();
    return $affected;
}

function mark_all_notifications_read(mysqli $conn, int $userId): int
{
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno || $userId <= 0) {
        return 0;
    }
    if (!notifications_table_ready($conn)) {
        return 0;
    }

    $stmt = $conn->prepare(
        "UPDATE notifications
         SET is_read = 1,
             read_at = COALESCE(read_at, NOW())
         WHERE user_id = ?
           AND is_read = 0"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $affected = max(0, (int) $stmt->affected_rows);
    $stmt->close();
    return $affected;
}

function notification_type_badge_class(string $type): string
{
    return match (strtolower(trim($type))) {
        'application', 'application_status' => 'text-bg-primary',
        'interview' => 'text-bg-warning',
        'payout', 'disbursement' => 'text-bg-success',
        'period' => 'text-bg-info',
        'security' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
}

function audit_action_label(string $action): string
{
    $action = trim($action);
    if ($action === '') {
        return '-';
    }

    return match ($action) {
        'announcement_created' => 'Announcement Created',
        'announcement_deleted' => 'Announcement Deleted',
        'announcement_sms_broadcast' => 'Announcement SMS Broadcast',
        'announcement_status_changed' => 'Announcement Status Changed',
        'announcement_updated' => 'Announcement Updated',
        'application_mark_soa_submitted' => 'Application Marked SOA Submitted',
        'application_period_close_all' => 'All Application Periods Closed',
        'application_period_created' => 'Application Period Created',
        'application_period_extended' => 'Application Period Deadline Extended',
        'application_period_set_open' => 'Application Period Set Open',
        'application_period_updated' => 'Application Period Updated',
        'application_set_soa_deadline' => 'SOA Deadline Set',
        'application_status_updated' => 'Application Status Updated',
        'application_submit_failed' => 'Application Submission Failed',
        'application_submitted' => 'Application Submitted',
        'change_mobile_cancelled' => 'Mobile Number Change Cancelled',
        'change_mobile_otp_generated_dev_mode' => 'Mobile Change Verification Code Generated (Dev Mode)',
        'change_mobile_otp_send_failed' => 'Mobile Change Verification Code Send Failed',
        'change_mobile_otp_sent' => 'Mobile Change Verification Code Sent',
        'change_mobile_otp_verify_failed' => 'Mobile Change Verification Code Check Failed',
        'change_mobile_success' => 'Mobile Number Changed',
        'disbursement_created' => 'Payout Schedule Created',
        'disbursement_date_updated' => 'Payout Schedule Updated',
        'forgot_password_otp_generated_dev_mode' => 'Forgot Password Verification Code Generated (Dev Mode)',
        'forgot_password_otp_send_failed' => 'Forgot Password Verification Code Send Failed',
        'forgot_password_otp_sent' => 'Forgot Password Verification Code Sent',
        'forgot_password_otp_verify_failed' => 'Forgot Password Verification Code Check Failed',
        'forgot_password_reset_success' => 'Password Reset Successful',
        'interview_schedule_updated' => 'Interview Schedule Updated',
        'login_blocked_inactive' => 'Login Blocked (Inactive Account)',
        'login_failed' => 'Login Failed',
        'login_success' => 'Login Successful',
        'logout' => 'Logout',
        'password_changed' => 'Password Changed',
        'profile_updated' => 'Profile Updated',
        'register_account_created' => 'Account Registered',
        'register_otp_cancelled' => 'Registration Verification Code Cancelled',
        'register_otp_generated_dev_mode' => 'Registration Verification Code Generated (Dev Mode)',
        'register_otp_send_failed' => 'Registration Verification Code Send Failed',
        'register_otp_sent' => 'Registration Verification Code Sent',
        'register_otp_verify_failed' => 'Registration Verification Code Check Failed',
        'requirement_template_created' => 'Requirement Template Created',
        'requirement_template_status_changed' => 'Requirement Template Status Changed',
        'sms_bulk_sent' => 'Bulk SMS Sent',
        'sms_selected_sent' => 'Selected SMS Sent',
        'sms_single_sent' => 'Single SMS Sent',
        'sms_template_created' => 'SMS Template Created',
        'sms_template_deleted' => 'SMS Template Deleted',
        'sms_template_updated' => 'SMS Template Updated',
        'staff_account_created' => 'Staff Account Created',
        'staff_account_updated' => 'Staff Account Updated',
        'staff_account_deleted' => 'Staff Account Deleted',
        default => ucwords(str_replace('_', ' ', $action)),
    };
}

function audit_entity_label(?string $entityType): string
{
    $entityType = trim((string) $entityType);
    if ($entityType === '') {
        return 'System';
    }

    return match ($entityType) {
        'application_period' => 'Application Period',
        'application' => 'Application',
        'disbursement' => 'Payout Schedule',
        'registration' => 'Registration',
        'password_reset' => 'Password Reset',
        'auth' => 'Authentication',
        'sms' => 'SMS',
        'sms_template' => 'SMS Template',
        'user' => 'User Account',
        default => ucwords(str_replace('_', ' ', $entityType)),
    };
}
