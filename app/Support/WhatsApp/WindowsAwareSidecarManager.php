<?php

namespace App\Support\WhatsApp;

use Kstmostofa\LaravelWhatsApp\Exceptions\SidecarException;
use Kstmostofa\LaravelWhatsApp\Web\SidecarManager;
use Kstmostofa\LaravelWhatsApp\Web\WebClient;
use Symfony\Component\Process\Process;

class WindowsAwareSidecarManager extends SidecarManager
{
    public function start(): int
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $pid = parent::start();
            $this->waitUntilReachable();
            $this->restorePersistedSessions();

            return $pid;
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

        $node = $this->resolveNodeBinary($this->config['sidecar']['node_binary'] ?: 'node');
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
                $this->waitUntilReachable();
                $this->restorePersistedSessions();

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

        if ($this->processAlive($pid) && $this->processLooksLikeSidecar($pid)) {
            (new Process(['taskkill.exe', '/PID', (string) $pid, '/T', '/F']))->run();
        }

        @unlink($this->pidFile());

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function persistedSessionIds(): array
    {
        if (! is_dir($this->sessionDir())) {
            return [];
        }

        $sessions = [];
        foreach (glob($this->sessionDir().DIRECTORY_SEPARATOR.'session-*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);

            $sessionId = substr($name, 8);

            if (str_starts_with($name, 'session-') && preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $sessionId)) {
                $sessions[] = $sessionId;
            }
        }

        sort($sessions);

        return array_values(array_filter($sessions));
    }

    /**
     * @return array<int, string>
     */
    public function restorePersistedSessions(): array
    {
        $restored = [];
        $client = new WebClient($this->host(), $this->port(), $this->token(), (int) ($this->config['timeout'] ?? 60));

        foreach ($this->persistedSessionIds() as $sessionId) {
            try {
                $client->session($sessionId)->start();
                $restored[] = $sessionId;
            } catch (\Throwable) {
                //
            }
        }

        return $restored;
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

    private function resolveNodeBinary(string $node): string
    {
        if ($node !== 'node' && is_file($node)) {
            return $node;
        }

        foreach (array_filter([
            env('NODE_BINARY'),
            env('WHATSAPP_WEB_NODE_BINARY'),
            'C:\\wumpus\\bin\\nodejs\\node-v22\\node.exe',
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
        ]) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $process = new Process(['where.exe', 'node'], base_path(), null, null, 10);
        $process->run();

        if ($process->isSuccessful()) {
            $candidate = trim(strtok($process->getOutput(), PHP_EOL) ?: '');

            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return $node;
    }

    private function processLooksLikeSidecar(int $pid): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return true;
        }

        $pathPattern = '*'.str_replace('\\', '*', $this->path()).'*index.js*';
        $command = sprintf(
            '$p = Get-CimInstance Win32_Process -Filter %s; if ($p -and $p.CommandLine -like %s) { exit 0 } exit 1',
            $this->quote('ProcessId = '.$pid),
            $this->quote($pathPattern),
        );

        $process = new Process([
            'powershell.exe',
            '-NoProfile',
            '-Command',
            $command,
        ], base_path(), null, null, 10);

        $process->run();

        return $process->isSuccessful();
    }

    private function waitUntilReachable(): bool
    {
        $client = new WebClient($this->host(), $this->port(), $this->token(), (int) ($this->config['timeout'] ?? 60));

        for ($i = 0; $i < 50; $i++) {
            usleep(200_000);

            if ($client->ping()) {
                return true;
            }
        }

        return false;
    }
}
