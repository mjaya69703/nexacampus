<?php

namespace App\Support\Notifications;

use App\Models\Settings\NotificationSetting;
use App\Support\Notifications\Contracts\WhatsAppProvider;

class WhatsAppProviderManager
{
    public function setting(): NotificationSetting
    {
        return NotificationSetting::current();
    }

    public function health(NotificationSetting $setting): array
    {
        $issues = $this->configurationIssues($setting);

        if (! $setting->whatsapp_enabled) {
            return [
                'status' => 'disabled',
                'message' => 'WhatsApp notification channel is disabled.',
                'issues' => [],
            ];
        }

        return [
            'status' => $issues === [] ? 'ready' : 'needs_configuration',
            'message' => $issues === []
                ? 'Provider configuration is ready for adapter wiring.'
                : 'Provider configuration is incomplete.',
            'issues' => $issues,
        ];
    }

    public function configurationIssues(NotificationSetting $setting): array
    {
        if (! $setting->whatsapp_enabled) {
            return [];
        }

        $provider = $setting->whatsapp_provider;
        $config = $provider === NotificationSetting::PROVIDER_UNOFFICIAL
            ? ($setting->unofficial_config ?? [])
            : ($setting->official_config ?? []);

        $required = $provider === NotificationSetting::PROVIDER_UNOFFICIAL
            ? ['sidecar_url', 'session_name', 'shared_token']
            : ['access_token', 'phone_number_id', 'business_account_id', 'app_secret', 'verify_token'];

        $labels = [
            'access_token' => 'Access token',
            'phone_number_id' => 'Phone number ID',
            'business_account_id' => 'Business account ID',
            'app_secret' => 'App secret',
            'verify_token' => 'Verify token',
            'sidecar_url' => 'Sidecar URL',
            'session_name' => 'Session name',
            'shared_token' => 'Shared token',
        ];

        $issues = [];

        foreach ($required as $key) {
            if (blank($config[$key] ?? null)) {
                $issues[] = ($labels[$key] ?? $key).' belum diisi.';
            }
        }

        return $issues;
    }

    public function persistHealth(NotificationSetting $setting): array
    {
        $health = $this->health($setting);

        $setting->forceFill([
            'last_health_status' => $health['status'],
            'last_health_message' => $health['message'],
            'last_health_checked_at' => now(),
        ])->save();

        return $health;
    }

    public function sendText(NotificationSetting $setting, string $recipient, string $message): array
    {
        return $this->provider($setting)->send($setting, $recipient, $message);
    }

    private function provider(NotificationSetting $setting): WhatsAppProvider
    {
        return $setting->whatsapp_provider === NotificationSetting::PROVIDER_UNOFFICIAL
            ? app(UnofficialWebSessionWhatsAppProvider::class)
            : app(OfficialCloudApiWhatsAppProvider::class);
    }
}
