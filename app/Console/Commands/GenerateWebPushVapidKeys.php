<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateWebPushVapidKeys extends Command
{
    protected $signature = 'notifications:web-push-vapid';

    protected $description = 'Generate VAPID keys for browser push notifications.';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('WEB_PUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEB_PUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
