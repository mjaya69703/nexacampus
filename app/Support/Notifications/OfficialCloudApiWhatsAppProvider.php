<?php

namespace App\Support\Notifications;

use App\Models\Settings\NotificationSetting;
use App\Support\Notifications\Contracts\WhatsAppProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OfficialCloudApiWhatsAppProvider implements WhatsAppProvider
{
    public function send(NotificationSetting $setting, string $recipient, string $message): array
    {
        $config = $setting->official_config ?? [];
        $baseUrl = rtrim($config['graph_api_base_url'] ?? 'https://graph.facebook.com/v20.0', '/');
        $phoneNumberId = $config['phone_number_id'] ?? null;
        $token = $config['access_token'] ?? null;

        $response = Http::timeout($setting->timeout_seconds ?: 15)
            ->withToken($token)
            ->post("{$baseUrl}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Meta Cloud API rejected the message: '.$response->body());
        }

        $payload = $response->json() ?? [];

        return [
            'provider_message_id' => data_get($payload, 'messages.0.id'),
            'provider_response' => $payload,
        ];
    }
}
