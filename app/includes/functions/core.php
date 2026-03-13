<?php
declare(strict_types=1);


function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function document_verification_status_label(array $document): string
{
    $status = trim((string) ($document['verification_status'] ?? 'pending'));
    $remarks = trim((string) ($document['remarks'] ?? ''));

    if ($status === 'pending' && preg_match('/^Resubmitted by applicant\b/i', $remarks) === 1) {
        return 'Resubmitted';
    }

    return match ($status) {
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function user_has_role(array $roles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return in_array($user['role'], $roles, true);
}

function is_admin(): bool
{
    return user_has_role(['admin']);
}

function is_staff(): bool
{
    return user_has_role(['staff']);
}

function require_login(string $redirectTo = 'login.php'): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please login first.');
        redirect($redirectTo);
    }
}

function require_role(array $roles, string $redirectTo): void
{
    if (!user_has_role($roles)) {
        set_flash('danger', 'You are not allowed to access that page.');
        redirect($redirectTo);
    }
}

function require_admin(string $redirectTo): void
{
    require_role(['admin'], $redirectTo);
}

function humanize_flash_message(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return 'Please try again.';
    }

    $exactMap = [
        'Invalid request token.' => 'Your session expired. Please try again.',
        'Invalid request. Please try again.' => 'Your session expired. Please try again.',
        'Database is not connected yet.' => 'The system is not ready yet. Please try again shortly.',
        'Database is not connected yet. Import the SQL setup first.' => 'The system setup is not complete yet. Please contact the administrator.',
        'Application period setup is missing.' => 'Application period settings are not ready yet. Please contact the administrator.',
        'Application periods table is missing.' => 'Application period settings are not ready yet. Please contact the administrator.',
        'Notifications table is not available yet.' => 'Notifications are not available yet. Please contact the administrator.',
        'Invalid OTP payload. Please request a new code.' => 'The verification code request is no longer valid. Please request a new code.',
        'Invalid OTP session. Please request a new code.' => 'Your verification code has expired. Please request a new code.',
    ];

    if (isset($exactMap[$message])) {
        return $exactMap[$message];
    }

    if (preg_match('/^Submission failed:/i', $message) === 1) {
        if (user_has_role(['admin', 'staff'])) {
            return $message;
        }
        return 'We could not submit your application right now. Please try again.';
    }

    if (preg_match('/^Photo upload failed:/i', $message) === 1) {
        return 'We could not upload your photo. Please try another clear photo.';
    }

    if (preg_match('/\b(TextBee|Textbee|SMS provider)\b.*\bDev\b.*\b(OTP|Verification Code)\b/i', $message) === 1) {
        if (preg_match('/(\d{4,8})\s*$/', $message, $matches) === 1) {
            return 'SMS is not available right now. Use this verification code: ' . $matches[1];
        }
        return 'SMS is not available right now. Please try again shortly.';
    }

    return $message;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = humanize_flash_message($message);
}

function get_flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function old(string $key, string $default = ''): string
{
    if (!isset($_POST[$key])) {
        return $default;
    }

    return trim((string) $_POST[$key]);
}

function normalize_mobile_number(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '';
    }

    if (function_exists('normalize_phone_number')) {
        return normalize_phone_number($phone);
    }

    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        return '63' . substr($digits, 1);
    }
    if (str_starts_with($digits, '63') && strlen($digits) === 12) {
        return $digits;
    }
    return $digits;
}

function mobile_number_variants(string $phone): array
{
    $normalized = normalize_mobile_number($phone);
    if ($normalized === '') {
        return [];
    }

    $variants = [$normalized];
    if (str_starts_with($normalized, '63') && strlen($normalized) === 12) {
        $variants[] = '0' . substr($normalized, 2);
    } elseif (str_starts_with($normalized, '0') && strlen($normalized) === 11) {
        $variants[] = '63' . substr($normalized, 1);
    }

    $variants[] = trim($phone);
    $variants = array_values(array_unique(array_filter($variants, static fn($v) => trim((string) $v) !== '')));
    return array_slice($variants, 0, 3);
}

function is_valid_mobile_number(string $phone): bool
{
    $normalized = normalize_mobile_number($phone);
    return preg_match('/^63\d{10}$/', $normalized) === 1;
}

function mask_mobile_number(string $phone): string
{
    $normalized = normalize_mobile_number($phone);
    if ($normalized === '') {
        return '';
    }
    if (strlen($normalized) < 7) {
        return $normalized;
    }
    return substr($normalized, 0, 4) . str_repeat('*', 5) . substr($normalized, -3);
}

function find_user_by_mobile(mysqli $conn, string $phone): ?array
{
    $variants = mobile_number_variants($phone);
    if (!$variants) {
        return null;
    }

    $v1 = $variants[0] ?? '';
    $v2 = $variants[1] ?? $v1;
    $v3 = $variants[2] ?? $v1;
    $stmt = $conn->prepare(
        "SELECT id, role, first_name, middle_name, last_name, email, phone, password_hash, status
         FROM users
         WHERE phone IN (?, ?, ?)
         ORDER BY id ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('sss', $v1, $v2, $v3);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? ($result->fetch_assoc() ?: null) : null;
    $stmt->close();
    return $user;
}

function mobile_number_exists(mysqli $conn, string $phone, int $excludeUserId = 0): bool
{
    $variants = mobile_number_variants($phone);
    if (!$variants) {
        return false;
    }

    $v1 = $variants[0] ?? '';
    $v2 = $variants[1] ?? $v1;
    $v3 = $variants[2] ?? $v1;

    if ($excludeUserId > 0) {
        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE phone IN (?, ?, ?)
               AND id <> ?
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssi', $v1, $v2, $v3, $excludeUserId);
    } else {
        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE phone IN (?, ?, ?)
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sss', $v1, $v2, $v3);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
    $stmt->close();
    return $exists;
}

function is_duplicate_key_error(mysqli $conn): bool
{
    return (int) ($conn->errno ?? 0) === 1062;
}
