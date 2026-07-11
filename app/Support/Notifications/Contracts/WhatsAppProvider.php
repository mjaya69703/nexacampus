<?php

namespace App\Support\Notifications\Contracts;

use App\Models\Settings\NotificationSetting;

interface WhatsAppProvider
{
    public function send(NotificationSetting $setting, string $recipient, string $message): array;
}
