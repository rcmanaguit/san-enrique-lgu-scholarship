<?php
declare(strict_types=1);

return [
    // Change this to switch active gateway provider.
    'default_provider' => 'textbee',

    // Add new providers here. Then set default_provider to that key.
    'providers' => [
        'textbee' => [
            'label' => 'TextBee',
            'driver' => 'textbee',
            'enabled' => false,
            'base_url' => 'https://api.textbee.dev',
            'api_key' => '',
            'device_id' => '',
            'sender_name' => 'San Enrique LGU Scholarship',
            'request_timeout' => 20,
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
            'enabled' => false,
        ],
    ],
];
