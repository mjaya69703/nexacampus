<?php

namespace App\Support\WhatsApp;

use Kstmostofa\LaravelWhatsApp\Exceptions\SidecarException;
use Kstmostofa\LaravelWhatsApp\Web\SidecarManager;
use Symfony\Component\Process\Process;

class WindowsAwareSidecarManager extends SidecarManager
{
    public function start(): int
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return parent::start();
        }

        if (! $this->isInstalled()) {
            throw new SidecarException(
                'Sidecar not installed. Run `php artisan whatsapp:sidecar:install` first.'
            );
        }

        if ($this->isRunning()) {
            throw new SidecarException('Sidecar already running (pid '.$this->pid().').');
        }

        $this->ensureDirectory(dirname($this->pidFile()));
        $this->ensureDirectory(dirname($this->logFile()));
        $this->ensureDirectory($this->sessionDir());

        $node = $this->config['sidecar']['node_binary'] ?: 'node';
        $entry = $this->path().DIRECTORY_SEPARATOR.'index.js';

        $script = sprintf(
            '$env:PORT=%s; $env:HOST=%s; $env:SIDECAR_TOKEN=%s; $env:SESSION_DIR=%s; $env:SIDECAR_PID_FILE=%s; '.
            '$p = Start-Process -FilePath %s -ArgumentList @(%s) -WorkingDirectory %s -WindowStyle Hidden -PassThru; '.
            'Set-Content -LiteralPath %s -Value $p.Id;',
            $this->quote((string) $this->port()),
            $this->quote($this->host()),
            $this->quote((string) ($this->token() ?? '')),
            $this->quote($this->sessionDir()),
            $this->quote($this->pidFile()),
            $this->quote($node),
            $this->quote($entry),
            $this->quote($this->path()),
            $this->quote($this->pidFile()),
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
            throw new SidecarException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Failed to spawn sidecar process.');
        }

        for ($i = 0; $i < 40; $i++) {
            usleep(100_000);

            $pid = $this->pid();
            if ($pid !== null && $this->processAlive($pid)) {
                return $pid;
            }
        }

        @unlink($this->pidFile());

        throw new SidecarException('Failed to spawn sidecar process (no live PID detected).');
    }

    public function stop(): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return parent::stop();
        }

        $pid = $this->pid();

        if ($pid === null) {
            return false;
        }

        if ($this->processAlive($pid)) {
            (new Process(['taskkill.exe', '/PID', (string) $pid, '/T', '/F']))->run();
        }

        @unlink($this->pidFile());

        return true;
    }

    protected function processAlive(int $pid): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return parent::processAlive($pid);
        }

        $process = new Process([
            'powershell.exe',
            '-NoProfile',
            '-Command',
            'if (Get-Process -Id '.$pid.' -ErrorAction SilentlyContinue) { exit 0 } exit 1',
        ], base_path(), null, null, 10);

        $process->run();

        return $process->isSuccessful();
    }

    private function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
