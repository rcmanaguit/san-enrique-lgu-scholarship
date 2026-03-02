<?php
declare(strict_types=1);

function sms_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $configPath = __DIR__ . '/../config/sms.php';
    if (file_exists($configPath)) {
        $loaded = require $configPath;
        if (is_array($loaded)) {
            $providers = is_array($loaded['providers'] ?? null) ? $loaded['providers'] : [];
            if ($providers !== []) {
                $defaultProvider = trim((string) ($loaded['default_provider'] ?? ''));
                if ($defaultProvider === '' || !isset($providers[$defaultProvider])) {
                    $defaultProvider = (string) array_key_first($providers);
                }
                $config = [
                    'default_provider' => $defaultProvider,
                    'providers' => $providers,
                ];
                return $config;
            }
        }
    }

    $legacyPath = __DIR__ . '/../config/textbee.php';
    $legacy = file_exists($legacyPath) ? require $legacyPath : [];
    if (!is_array($legacy)) {
        $legacy = [];
    }

    $config = [
        'default_provider' => 'textbee',
        'providers' => [
            'textbee' => [
                'label' => 'TextBee',
                'driver' => 'textbee',
                'enabled' => (bool) ($legacy['enabled'] ?? false),
                'base_url' => (string) ($legacy['base_url'] ?? 'https://api.textbee.dev'),
                'api_key' => (string) ($legacy['api_key'] ?? ''),
                'device_id' => (string) ($legacy['device_id'] ?? ''),
                'sender_name' => (string) ($legacy['sender_name'] ?? 'San Enrique LGU Scholarship'),
                'request_timeout' => (int) ($legacy['request_timeout'] ?? 20),
            ],
        ],
    ];

    return $config;
}

function sms_provider_key(?string $providerKey = null): string
{
    $config = sms_config();
    $providers = is_array($config['providers'] ?? null) ? $config['providers'] : [];
    if ($providers === []) {
        return 'textbee';
    }

    $requested = trim((string) ($providerKey ?? ''));
    if ($requested !== '' && isset($providers[$requested])) {
        return $requested;
    }

    $default = trim((string) ($config['default_provider'] ?? ''));
    if ($default !== '' && isset($providers[$default])) {
        return $default;
    }

    return (string) array_key_first($providers);
}

function sms_provider_config(?string $providerKey = null): array
{
    $config = sms_config();
    $providers = is_array($config['providers'] ?? null) ? $config['providers'] : [];
    $key = sms_provider_key($providerKey);
    $provider = is_array($providers[$key] ?? null) ? $providers[$key] : [];

    $provider['key'] = $key;
    $provider['label'] = trim((string) ($provider['label'] ?? $key));
    $provider['driver'] = strtolower(trim((string) ($provider['driver'] ?? 'textbee')));
    $provider['enabled'] = (bool) ($provider['enabled'] ?? false);
    return $provider;
}

function sms_active_provider_config(): array
{
    return sms_provider_config();
}

function sms_provider_label(?string $providerKey = null): string
{
    $provider = sms_provider_config($providerKey);
    return trim((string) ($provider['label'] ?? $provider['key'] ?? 'SMS'));
}

function sms_provider_is_enabled(?string $providerKey = null): bool
{
    $provider = sms_provider_config($providerKey);
    return (bool) ($provider['enabled'] ?? false);
}

function normalize_phone_number(string $phone): string
{
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

function sms_send(string $recipientPhone, string $message, ?int $userId = null, string $smsType = 'single'): array
{
    $provider = sms_active_provider_config();
    $providerKey = (string) ($provider['key'] ?? 'textbee');
    $phone = normalize_phone_number($recipientPhone);
    $message = trim($message);

    if ($phone === '' || $message === '') {
        $result = [
            'ok' => false,
            'provider' => $providerKey,
            'error' => 'Phone and message are required.',
        ];
        sms_log_insert($userId, $recipientPhone, $message, $smsType, json_encode($result), 'failed');
        return $result;
    }

    if (!sms_provider_is_enabled($providerKey)) {
        $result = [
            'ok' => false,
            'provider' => $providerKey,
            'error' => sms_provider_label($providerKey) . ' is disabled in config/sms.php',
        ];
        sms_log_insert($userId, $phone, $message, $smsType, json_encode($result), 'queued');
        return $result;
    }

    $driver = strtolower(trim((string) ($provider['driver'] ?? 'textbee')));
    $result = [];
    if ($driver === 'textbee') {
        $result = sms_send_via_textbee($provider, $phone, $message);
    } elseif ($driver === 'log_only') {
        $result = [
            'ok' => false,
            'provider' => $providerKey,
            'error' => 'Log-only SMS provider is active. Message was not sent to a gateway.',
        ];
    } elseif ($driver === 'custom') {
        $result = sms_send_via_custom_handler($provider, $phone, $message);
    } else {
        $result = [
            'ok' => false,
            'provider' => $providerKey,
            'error' => 'Unsupported SMS driver: ' . $driver,
        ];
    }

    if (!is_array($result)) {
        $result = [
            'ok' => false,
            'provider' => $providerKey,
            'error' => 'SMS provider returned invalid response format.',
        ];
    }
    if (!isset($result['provider'])) {
        $result['provider'] = $providerKey;
    }

    $status = ($result['ok'] ?? false) ? 'success' : 'failed';
    sms_log_insert($userId, $phone, $message, $smsType, json_encode($result), $status);
    return $result;
}

function sms_send_via_textbee(array $provider, string $phone, string $message): array
{
    $providerKey = (string) ($provider['key'] ?? 'textbee');
    $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');
    if ($baseUrl === '') {
        $baseUrl = 'https://api.textbee.dev';
    }
    $deviceId = trim((string) ($provider['device_id'] ?? ''));
    $apiKey = trim((string) ($provider['api_key'] ?? ''));
    $timeout = (int) ($provider['request_timeout'] ?? 20);
    if ($timeout <= 0) {
        $timeout = 20;
    }

    if ($deviceId === '' || $apiKey === '') {
        return [
            'ok' => false,
            'provider' => $providerKey,
            'error' => 'TextBee device_id/api_key is missing.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'provider' => $providerKey,
            'error' => 'cURL extension is not enabled in PHP.',
        ];
    }

    $url = $baseUrl . '/api/v1/gateway/devices/' . rawurlencode($deviceId) . '/send-sms';
    $payload = [
        'recipients' => $phone,
        'message' => $message,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $body = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseBody = (string) ($body ?: $curlErr);
    $ok = $httpCode >= 200 && $httpCode < 300 && $curlErr === '';

    return [
        'ok' => $ok,
        'provider' => $providerKey,
        'http_code' => $httpCode,
        'response' => $responseBody,
    ];
}

function sms_send_via_custom_handler(array $provider, string $phone, string $message): array
{
    $providerKey = (string) ($provider['key'] ?? 'custom');
    $handler = $provider['handler'] ?? null;
    if (!is_callable($handler)) {
        return [
            'ok' => false,
            'provider' => $providerKey,
            'error' => 'Custom SMS handler is missing or not callable.',
        ];
    }

    try {
        $result = call_user_func($handler, $phone, $message, $provider);
        if (!is_array($result)) {
            return [
                'ok' => false,
                'provider' => $providerKey,
                'error' => 'Custom SMS handler must return an array.',
            ];
        }
        if (!array_key_exists('ok', $result)) {
            $result['ok'] = false;
        }
        if (!array_key_exists('provider', $result)) {
            $result['provider'] = $providerKey;
        }
        return $result;
    } catch (Throwable $error) {
        return [
            'ok' => false,
            'provider' => $providerKey,
            'error' => 'Custom SMS handler error: ' . $error->getMessage(),
        ];
    }
}

function sms_send_bulk(array $phones, string $message, string $smsType = 'bulk'): array
{
    $results = [];
    foreach ($phones as $phone) {
        $results[] = sms_send((string) $phone, $message, null, $smsType);
    }
    return $results;
}

function sms_log_insert(?int $userId, string $phone, string $message, string $smsType, ?string $providerResponse, string $status): void
{
    global $conn;

    if (!$conn instanceof mysqli || $conn->connect_errno) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO sms_logs (user_id, phone, message, sms_type, provider_response, delivery_status)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('isssss', $userId, $phone, $message, $smsType, $providerResponse, $status);
    $stmt->execute();
    $stmt->close();
}

// Backward-compatible wrappers (TextBee-named calls now route to active SMS provider).
function textbee_config(): array
{
    return sms_provider_config('textbee');
}

function textbee_send_sms(string $recipientPhone, string $message, ?int $userId = null, string $smsType = 'single'): array
{
    return sms_send($recipientPhone, $message, $userId, $smsType);
}

function textbee_send_bulk_sms(array $phones, string $message, string $smsType = 'bulk'): array
{
    return sms_send_bulk($phones, $message, $smsType);
}
