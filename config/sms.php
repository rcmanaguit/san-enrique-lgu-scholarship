<?php
declare(strict_types=1);

if (!function_exists('env_first_value')) {
    function env_first_value(array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            $value = getenv((string) $key);
            if ($value !== false && $value !== '') {
                return $value;
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

$defaultProvider = trim((string) env_first_value(['SMS_DEFAULT_PROVIDER'], 'textbee'));
if ($defaultProvider === '') {
    $defaultProvider = 'textbee';
}

$requestTimeout = (int) env_first_value(['SMS_REQUEST_TIMEOUT', 'TEXTBEE_REQUEST_TIMEOUT'], 20);
if ($requestTimeout <= 0) {
    $requestTimeout = 20;
}

return [
    // Change this via env var: SMS_DEFAULT_PROVIDER (example: textbee or log_only).
    'default_provider' => $defaultProvider,

    // Add new providers here. Then set default_provider to that key.
    'providers' => [
        'textbee' => [
            'label' => 'TextBee',
            'driver' => 'textbee',
            // Enable via env var: SMS_TEXTBEE_ENABLED=true
            'enabled' => env_bool_value(['SMS_TEXTBEE_ENABLED', 'TEXTBEE_ENABLED'], false),
            'base_url' => (string) env_first_value(['SMS_TEXTBEE_BASE_URL', 'TEXTBEE_BASE_URL'], 'https://api.textbee.dev'),
            // Put real keys in environment variables, not in this file:
            // SMS_TEXTBEE_API_KEY, SMS_TEXTBEE_DEVICE_ID
            'api_key' => (string) env_first_value(['SMS_TEXTBEE_API_KEY', 'TEXTBEE_API_KEY'], ''),
            'device_id' => (string) env_first_value(['SMS_TEXTBEE_DEVICE_ID', 'TEXTBEE_DEVICE_ID'], ''),
            'sender_name' => (string) env_first_value(['SMS_SENDER_NAME', 'TEXTBEE_SENDER_NAME'], 'San Enrique LGU Scholarship'),
            'request_timeout' => $requestTimeout,
        ],

        // Example custom provider:
        // 1) Set driver => 'custom'
        // 2) Set handler => your callable function name
        // 3) Implement that function (for example in includes/sms.php or your own included file)
        // 'my_custom_provider' => [
        //     'label' => 'My SMS Gateway',
        //     'driver' => 'custom',
        //     'enabled' => false,
        //     'handler' => 'my_custom_sms_send',
        //     // Add any custom keys your handler needs (api_url, token, etc.)
        // ],

        // Safe dev option: logs SMS attempts only, no gateway calls.
        'log_only' => [
            'label' => 'Log Only (No Gateway)',
            'driver' => 'log_only',
            'enabled' => env_bool_value(['SMS_LOG_ONLY_ENABLED'], false),
        ],
    ],
];
