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
