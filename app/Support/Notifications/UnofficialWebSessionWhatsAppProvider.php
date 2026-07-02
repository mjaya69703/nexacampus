<?php

namespace App\Support\Notifications;

use App\Models\Settings\NotificationSetting;
use App\Support\Notifications\Contracts\WhatsAppProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class UnofficialWebSessionWhatsAppProvider implements WhatsAppProvider
{
    public function send(NotificationSetting $setting, string $recipient, string $message): array
    {
        $config = $setting->unofficial_config ?? [];
        $baseUrl = rtrim($config['sidecar_url'] ?? '', '/');
        $session = $config['session_name'] ?? 'main';
        $endpoint = $config['send_endpoint'] ?? "/sessions/{$session}/messages/text";

        $response = Http::timeout($setting->timeout_seconds ?: 15)
            ->withToken($config['shared_token'] ?? '')
            ->post($baseUrl.$endpoint, [
                'to' => $recipient,
                'message' => $message,
                'text' => $message,
                'session' => $session,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp sidecar rejected the message: '.$response->body());
        }

        $payload = $response->json() ?? ['body' => $response->body()];

        return [
            'provider_message_id' => data_get($payload, 'id') ?? data_get($payload, 'message_id'),
            'provider_response' => $payload,
        ];
    }
}
