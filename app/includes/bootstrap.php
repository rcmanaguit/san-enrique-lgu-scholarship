<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__, 2);

$appTimezone = (string) (getenv('APP_TIMEZONE') ?: 'Asia/Manila');
if ($appTimezone === '') {
    $appTimezone = 'Asia/Manila';
}

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;

    if (class_exists(\Dotenv\Dotenv::class)) {
        $dotenv = \Dotenv\Dotenv::createMutable($rootPath, ['.env', '.env.local']);
        $dotenv->safeLoad();
        $appTimezone = (string) (getenv('APP_TIMEZONE') ?: $appTimezone);
    }
}

@date_default_timezone_set($appTimezone);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/export_service.php';
