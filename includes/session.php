<?php
declare(strict_types=1);

// Keep sessions stable for multi-step forms (default: 4 hours).
$sessionLifetime = (int) (getenv('SESSION_LIFETIME') ?: 14400);
if ($sessionLifetime < 1800) {
    $sessionLifetime = 1800;
}

if (PHP_SAPI !== 'cli') {
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
