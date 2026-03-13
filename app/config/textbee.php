<?php
declare(strict_types=1);

if (!function_exists('env_first_value')) {
    function env_first_value(array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            $name = (string) $key;
            $candidates = [
                getenv($name),
                $_ENV[$name] ?? null,
                $_SERVER[$name] ?? null,
            ];

            foreach ($candidates as $value) {
                if ($value !== false && $value !== null && $value !== '') {
                    return $value;
                }
            }
        }
        return $default;
    }
}

if (!function_exists('env_bool_value')) {
    function env_bool_value(array $keys, bool $default = false): bool
    {
        $raw = env_first_value($keys, null);
        if ($raw === null) {
            return $default;
        }

        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }
}

$requestTimeout = (int) env_first_value(['SMS_REQUEST_TIMEOUT', 'TEXTBEE_REQUEST_TIMEOUT'], 20);
if ($requestTimeout <= 0) {
    $requestTimeout = 20;
}

return [
    'enabled' => env_bool_value(['SMS_TEXTBEE_ENABLED', 'TEXTBEE_ENABLED'], false),
    'base_url' => (string) env_first_value(['SMS_TEXTBEE_BASE_URL', 'TEXTBEE_BASE_URL'], 'https://api.textbee.dev'),
    'api_key' => (string) env_first_value(['SMS_TEXTBEE_API_KEY', 'TEXTBEE_API_KEY'], ''),
    'device_id' => (string) env_first_value(['SMS_TEXTBEE_DEVICE_ID', 'TEXTBEE_DEVICE_ID'], ''),
    'sender_name' => (string) env_first_value(['SMS_SENDER_NAME', 'TEXTBEE_SENDER_NAME'], 'San Enrique LGU Scholarship'),
    'request_timeout' => $requestTimeout,
];
