<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

$user = current_user();
if (db_ready() && is_array($user)) {
    audit_log(
        $conn,
        'logout',
        (int) ($user['id'] ?? 0),
        (string) ($user['role'] ?? ''),
        'auth',
        (string) ($user['id'] ?? ''),
        'User logged out.'
    );
}

session_unset();
session_destroy();
session_start();
set_flash('success', 'You are now logged out.');
redirect('index.php');
