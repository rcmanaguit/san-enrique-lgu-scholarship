<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno) {
    fwrite(STDERR, "Database connection is not ready.\n");
    exit(1);
}

$messages = [];

if (table_exists($conn, 'users')) {
    $stmtUsers = $conn->prepare("SELECT id, phone FROM users");
    if ($stmtUsers) {
        $stmtUsers->execute();
        $resultUsers = $stmtUsers->get_result();
        $userRows = $resultUsers instanceof mysqli_result ? $resultUsers->fetch_all(MYSQLI_ASSOC) : [];
        $stmtUsers->close();

        $normalizedCount = 0;
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
                $normalizedCount++;
            }
        }
        $messages[] = "Normalized phone values for {$normalizedCount} user(s).";
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
            $messages[] = "Added unique index uq_users_phone.";
        } else {
            $messages[] = "Unique index uq_users_phone already exists.";
        }
    } else {
        $messages[] = "Skipped uq_users_phone index creation because duplicate phones still exist.";
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
    $messages[] = "Created application_no_sequences table.";
} else {
    $messages[] = "application_no_sequences table already exists.";
}

foreach ($messages as $message) {
    fwrite(STDOUT, $message . PHP_EOL);
}
