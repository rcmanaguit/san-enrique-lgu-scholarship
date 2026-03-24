<?php

namespace App\Support;

use Exception;
use GuzzleHttp\Client;

class Sms
{
    public static function sendTextbee(string $phoneNumber, string $message): void
    {
        $deviceId = trim((string) ($_ENV['TEXTBEE_DEVICE_ID'] ?? ''));
        $apiKey = trim((string) ($_ENV['TEXTBEE_API_KEY'] ?? ''));

        if ($deviceId === '' || $apiKey === '') {
            error_log('SMS failed to send because Textbee settings are incomplete.');
            return;
        }

        try {
            $client = new Client();
            $client->request('POST', 'https://api.textbee.dev/api/v1/gateway/devices/' . rawurlencode($deviceId) . '/send-sms', [
                'headers' => [
                    'x-api-key' => $apiKey,
                ],
                'json' => [
                    'receivers' => [$phoneNumber],
                    'smsBody' => $message,
                ],
            ]);
        } catch (Exception $e) {
            error_log('SMS failed to send: ' . $e->getMessage());
        }
    }
}
