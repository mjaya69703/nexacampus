<?php

namespace App\Support\Notifications;

use App\Models\Settings\NotificationSetting;
use App\Support\Notifications\Contracts\WhatsAppProvider;
use Illuminate\Support\Facades\Http;
use Kstmostofa\LaravelWhatsApp\Facades\WhatsApp;
use RuntimeException;

class UnofficialWebSessionWhatsAppProvider implements WhatsAppProvider
{
    public function send(NotificationSetting $setting, string $recipient, string $message): array
    {
        $config = $setting->unofficial_config ?? [];
        $baseUrl = rtrim($config['sidecar_url'] ?? '', '/');
        $session = $config['session_name'] ?? 'main';

        if (! preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $session)) {
            throw new RuntimeException('WhatsApp session name is invalid.');
        }

        if ($baseUrl === '') {
            return $this->sendWithBundledPackage($setting, $recipient, $message, $session, $config);
        }

        $endpoint = '/sessions/'.rawurlencode($session).'/messages/text';

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

    private function sendWithBundledPackage(NotificationSetting $setting, string $recipient, string $message, string $session, array $config): array
    {
        config([
            'laravel-whatsapp.web.enabled' => true,
            'laravel-whatsapp.web.host' => $config['host'] ?? config('laravel-whatsapp.web.host', '127.0.0.1'),
            'laravel-whatsapp.web.port' => (int) ($config['port'] ?? config('laravel-whatsapp.web.port', 3000)),
            'laravel-whatsapp.web.token' => filled($config['shared_token'] ?? null)
                ? $config['shared_token']
                : config('laravel-whatsapp.web.token'),
            'laravel-whatsapp.web.timeout' => $setting->timeout_seconds ?: config('laravel-whatsapp.web.timeout', 60),
            'laravel-whatsapp.ui.default_session' => $session,
        ]);

        $payload = WhatsApp::web($session)
            ->messages()
            ->sendText($this->webRecipient($recipient), $message);

        return [
            'provider_message_id' => data_get($payload, 'id') ?? data_get($payload, 'message.id'),
            'provider_response' => $payload,
        ];
    }

    private function webRecipient(string $recipient): string
    {
        if (str_ends_with($recipient, '@c.us') || str_ends_with($recipient, '@g.us') || str_ends_with($recipient, '@broadcast')) {
            return $recipient;
        }

        return preg_replace('/\D+/', '', $recipient).'@c.us';
    }
}
