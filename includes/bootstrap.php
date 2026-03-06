<?php
declare(strict_types=1);

if (!function_exists('load_env_file')) {
    function load_env_file(string $filePath): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim((string) $parts[0]);
            $value = trim((string) $parts[1]);
            if ($name === '') {
                continue;
            }

            if (
                (array_key_exists($name, $_ENV) && $_ENV[$name] !== '')
                || (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== '')
                || getenv($name) !== false
            ) {
                continue;
            }

            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

$rootPath = dirname(__DIR__);
load_env_file($rootPath . '/.env');
load_env_file($rootPath . '/.env.local');

$appTimezone = (string) (getenv('APP_TIMEZONE') ?: 'Asia/Manila');
if ($appTimezone === '') {
    $appTimezone = 'Asia/Manila';
}
@date_default_timezone_set($appTimezone);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/export_service.php';
