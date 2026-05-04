<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncResources extends Command
{
    protected $signature = 'resources:sync';

    protected $description = 'Sync permissions and menus from config/resources.php';

    public function handle(): int
    {
        $this->call('permissions:sync');
        $this->call('menus:sync');

        $this->info('Resources sync completed.');

        return self::SUCCESS;
    }
}
