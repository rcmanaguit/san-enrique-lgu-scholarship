<?php
declare(strict_types=1);

if (!function_exists('env_value')) {
    function env_value(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }
}

$dbHost = (string) env_value('DB_HOST', '127.0.0.1');
$dbUser = (string) env_value('DB_USER', 'root');
$dbPass = (string) env_value('DB_PASS', '');
$dbName = (string) env_value('DB_NAME', 'lgu_scholarship');
$dbPort = (int) env_value('DB_PORT', 3306);
$dbSocket = env_value('DB_SOCKET', null);

$conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort, $dbSocket ? (string) $dbSocket : null);
$dbConnected = !$conn->connect_errno;

if ($dbConnected) {
    $conn->set_charset('utf8mb4');
    $dbTimezone = (string) env_value('DB_TIMEZONE', '+08:00');
    if ($dbTimezone !== '') {
        $stmtTz = $conn->prepare("SET time_zone = ?");
        if ($stmtTz) {
            $stmtTz->bind_param('s', $dbTimezone);
            $stmtTz->execute();
            $stmtTz->close();
        }
    }

    // Runtime hardening for functional correctness: normalize mobile keys,
    // enforce uniqueness when safe, and ensure sequence table exists.
    if (function_exists('table_exists') && function_exists('normalize_mobile_number')) {
        if (table_exists($conn, 'users')) {
            $stmtUsers = $conn->prepare("SELECT id, phone FROM users");
            if ($stmtUsers) {
                $stmtUsers->execute();
                $resultUsers = $stmtUsers->get_result();
                $userRows = $resultUsers instanceof mysqli_result ? $resultUsers->fetch_all(MYSQLI_ASSOC) : [];
                $stmtUsers->close();

                foreach ($userRows as $row) {
                    $userId = (int) ($row['id'] ?? 0);
                    $phone = trim((string) ($row['phone'] ?? ''));
                    if ($userId <= 0) {
                        continue;
                    }

                    $normalized = $phone === '' ? null : normalize_mobile_number($phone);
                    if ($normalized === '') {
                        $normalized = null;
                    }
                    if ((string) ($row['phone'] ?? '') === (string) ($normalized ?? '')) {
                        continue;
                    }

                    $stmtUpdatePhone = $conn->prepare("UPDATE users SET phone = ? WHERE id = ? LIMIT 1");
                    if ($stmtUpdatePhone) {
                        $stmtUpdatePhone->bind_param('si', $normalized, $userId);
                        $stmtUpdatePhone->execute();
                        $stmtUpdatePhone->close();
                    }
                }
            }

            $duplicatePhones = 0;
            $dupResult = $conn->query(
                "SELECT COUNT(*) AS total
                 FROM (
                    SELECT phone
                    FROM users
                    WHERE phone IS NOT NULL AND TRIM(phone) <> ''
                    GROUP BY phone
                    HAVING COUNT(*) > 1
                 ) dup"
            );
            if ($dupResult instanceof mysqli_result) {
                $dupRow = $dupResult->fetch_assoc();
                $duplicatePhones = (int) ($dupRow['total'] ?? 0);
            }

            if ($duplicatePhones === 0) {
                $indexExists = false;
                $stmtIndex = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'users'
                       AND INDEX_NAME = 'uq_users_phone'
                     LIMIT 1"
                );
                if ($stmtIndex) {
                    $stmtIndex->execute();
                    $indexRow = $stmtIndex->get_result()->fetch_assoc();
                    $stmtIndex->close();
                    $indexExists = (int) ($indexRow['total'] ?? 0) > 0;
                }
                if (!$indexExists) {
                    $conn->query("ALTER TABLE users ADD UNIQUE KEY uq_users_phone (phone)");
                }
            }
        }

        if (!table_exists($conn, 'application_no_sequences')) {
            $conn->query(
                "CREATE TABLE IF NOT EXISTS application_no_sequences (
                    sequence_year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
                    last_number INT UNSIGNED NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }
}

function db_ready(): bool
{
    global $dbConnected;
    return (bool) $dbConnected;
}

function db_error_message(): string
{
    global $conn;
    if (!$conn instanceof mysqli) {
        return 'Unknown database error.';
    }

    return (string) $conn->connect_error;
}
