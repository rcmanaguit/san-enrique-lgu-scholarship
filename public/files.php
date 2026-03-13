<?php
declare(strict_types=1);

$relativePath = trim((string) ($_GET['path'] ?? ''));
$relativePath = str_replace('\\', '/', rawurldecode($relativePath));
$relativePath = ltrim($relativePath, '/');

if ($relativePath === '' || str_contains($relativePath, '..')) {
    http_response_code(404);
    exit;
}

$uploadsRoot = realpath(dirname(__DIR__) . '/uploads');
if ($uploadsRoot === false) {
    http_response_code(404);
    exit;
}

$target = realpath($uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
if ($target === false || !str_starts_with(str_replace('\\', '/', $target), str_replace('\\', '/', $uploadsRoot) . '/')) {
    http_response_code(404);
    exit;
}

if (!is_file($target)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
if (in_array($ext, ['php', 'phtml', 'php3', 'php4', 'php5', 'phar'], true)) {
    http_response_code(403);
    exit;
}

$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $detected = mime_content_type($target);
    if (is_string($detected) && trim($detected) !== '') {
        $mime = $detected;
    }
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($target));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($target);
exit;
