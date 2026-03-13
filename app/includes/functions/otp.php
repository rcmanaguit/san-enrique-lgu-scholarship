<?php
declare(strict_types=1);

function otp_session_key(string $flow): string
{
    $flow = strtolower(trim($flow));
    $flow = preg_replace('/[^a-z0-9_]+/', '_', $flow) ?? 'generic';
    $flow = trim($flow, '_');
    if ($flow === '') {
        $flow = 'generic';
    }
    return 'otp_' . $flow;
}

function generate_otp_code(int $length = 6): string
{
    $length = max(4, min(8, $length));
    $max = (10 ** $length) - 1;
    return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
}

function otp_start(string $flow, array $payload = [], int $ttlSeconds = 300, int $maxAttempts = 5): string
{
    $code = generate_otp_code();
    $_SESSION[otp_session_key($flow)] = [
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => time() + max(60, $ttlSeconds),
        'attempts' => 0,
        'max_attempts' => max(1, $maxAttempts),
        'payload' => $payload,
        'created_at' => time(),
    ];
    return $code;
}

function otp_state(string $flow): ?array
{
    $key = otp_session_key($flow);
    $state = $_SESSION[$key] ?? null;
    if (!is_array($state)) {
        return null;
    }

    if ((int) ($state['expires_at'] ?? 0) < time()) {
        unset($_SESSION[$key]);
        return null;
    }

    return $state;
}

function otp_seconds_left(string $flow): int
{
    $state = otp_state($flow);
    if (!$state) {
        return 0;
    }
    return max(0, (int) ($state['expires_at'] ?? 0) - time());
}

function otp_clear(string $flow): void
{
    unset($_SESSION[otp_session_key($flow)]);
}

function otp_verify(string $flow, string $code): array
{
    $key = otp_session_key($flow);
    $state = otp_state($flow);
    if (!$state) {
        return ['ok' => false, 'error' => 'Verification code (OTP) is missing or expired. Please request a new code.'];
    }

    $cleanCode = preg_replace('/\D+/', '', trim($code)) ?? '';
    if ($cleanCode === '') {
        return ['ok' => false, 'error' => 'Please enter the verification code (OTP).'];
    }

    $attempts = (int) ($state['attempts'] ?? 0) + 1;
    $maxAttempts = (int) ($state['max_attempts'] ?? 5);
    $state['attempts'] = $attempts;
    $_SESSION[$key] = $state;

    if (!password_verify($cleanCode, (string) ($state['code_hash'] ?? ''))) {
        $remaining = max(0, $maxAttempts - $attempts);
        if ($remaining <= 0) {
            unset($_SESSION[$key]);
            return ['ok' => false, 'error' => 'Verification failed too many times. Please request a new code.'];
        }
        return ['ok' => false, 'error' => 'Incorrect verification code. Attempts left: ' . $remaining . '.'];
    }

    $payload = is_array($state['payload'] ?? null) ? $state['payload'] : [];
    unset($_SESSION[$key]);
    return ['ok' => true, 'payload' => $payload];
}

