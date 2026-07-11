<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kstmostofa\LaravelWhatsApp\Exceptions\SidecarException;
use Kstmostofa\LaravelWhatsApp\Web\SidecarManager;
use Kstmostofa\LaravelWhatsApp\Web\WebClient;

class StartWhatsAppSidecar extends Command
{
    protected $signature = 'nexacampus:whatsapp-sidecar-start';

    protected $description = 'Start the bundled WhatsApp sidecar with Windows-compatible process spawning.';

    public function handle(): int
    {
        if (app(WebClient::class)->ping()) {
            $this->info('WhatsApp sidecar is already reachable.');

            return self::SUCCESS;
        }

        $manager = app(SidecarManager::class);

        try {
            $pid = $manager->start();
        } catch (SidecarException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $web = config('laravel-whatsapp.web');

        for ($i = 0; $i < 30; $i++) {
            usleep(250_000);

            if (app(WebClient::class)->ping()) {
                $this->info('WhatsApp sidecar started at http://'.$web['host'].':'.$web['port']);

                return self::SUCCESS;
            }
        }

        $this->warn('Sidecar process was started (pid '.$pid.'), but health endpoint is not reachable yet. Check '.$manager->errFile());

        return self::FAILURE;
    }
}
