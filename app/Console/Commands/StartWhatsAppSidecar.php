<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kstmostofa\LaravelWhatsApp\Web\WebClient;
use Symfony\Component\Process\Process;

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

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->call('whatsapp:sidecar:start');

            return self::SUCCESS;
        }

        $web = config('laravel-whatsapp.web');
        $sidecar = $web['sidecar'];

        $sidecarPath = $sidecar['path'];
        $node = $sidecar['node_binary'] ?: 'node';
        $sessionDir = $sidecar['session_dir'];
        $pidFile = $sidecar['pid_file'];
        $logFile = $sidecar['log_file'];
        $errFile = $sidecar['err_file'];

        foreach ([dirname($pidFile), dirname($logFile), $sessionDir] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        if (! is_file($sidecarPath.DIRECTORY_SEPARATOR.'index.js') || ! is_dir($sidecarPath.DIRECTORY_SEPARATOR.'node_modules')) {
            $this->error('Sidecar is not installed. Run php artisan whatsapp:sidecar:install first.');

            return self::FAILURE;
        }

        $script = sprintf(
            '$env:PORT=%s; $env:HOST=%s; $env:SIDECAR_TOKEN=%s; $env:SESSION_DIR=%s; $env:SIDECAR_PID_FILE=%s; '.
            '$p = Start-Process -FilePath %s -ArgumentList @(%s) -WorkingDirectory %s -WindowStyle Hidden -PassThru; '.
            'Set-Content -LiteralPath %s -Value $p.Id;',
            $this->quote((string) $web['port']),
            $this->quote((string) $web['host']),
            $this->quote((string) ($web['token'] ?? '')),
            $this->quote($sessionDir),
            $this->quote($pidFile),
            $this->quote($node),
            $this->quote('index.js'),
            $this->quote($sidecarPath),
            $this->quote($pidFile),
        );

        $process = new Process([
            'powershell.exe',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            $script,
        ], base_path(), null, null, 30);

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput() ?: $process->getOutput()));

            return self::FAILURE;
        }

        for ($i = 0; $i < 30; $i++) {
            usleep(250_000);

            if (app(WebClient::class)->ping()) {
                $this->info('WhatsApp sidecar started at http://'.$web['host'].':'.$web['port']);

                return self::SUCCESS;
            }
        }

        $this->warn('Sidecar process was started, but health endpoint is not reachable yet. Check '.$errFile);

        return self::FAILURE;
    }

    private function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
