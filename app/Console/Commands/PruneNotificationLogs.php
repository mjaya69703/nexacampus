<?php

namespace App\Console\Commands;

use App\Models\Settings\NotificationLog;
use App\Models\Settings\NotificationSetting;
use Illuminate\Console\Command;

class PruneNotificationLogs extends Command
{
    protected $signature = 'notifications:prune-logs
        {--days= : Override full log retention days}
        {--response-days= : Override provider response retention days}
        {--dry-run : Count records without changing data}
        {--force : Skip confirmation when deleting logs manually}';

    protected $description = 'Prune old notification logs and clear sensitive provider responses.';

    public function handle(): int
    {
        $setting = NotificationSetting::current();
        $logRetentionDays = $this->retentionDays('days', $setting->log_retention_days ?: 90);
        $responseRetentionDays = $this->retentionDays('response-days', $setting->provider_response_retention_days ?: 30);
        $dryRun = (bool) $this->option('dry-run');

        $responseCutoff = now()->subDays($responseRetentionDays);
        $logCutoff = now()->subDays($logRetentionDays);

        $responseQuery = NotificationLog::query()
            ->whereNotNull('provider_response')
            ->where('created_at', '<', $responseCutoff);

        $deleteQuery = NotificationLog::query()
            ->withTrashed()
            ->where('created_at', '<', $logCutoff);

        $responsesToClear = (clone $responseQuery)->count();
        $logsToDelete = (clone $deleteQuery)->count();

        if ($dryRun) {
            $this->info("Would clear {$responsesToClear} provider responses older than {$responseRetentionDays} days.");
            $this->info("Would permanently delete {$logsToDelete} notification logs older than {$logRetentionDays} days.");

            return self::SUCCESS;
        }

        if ($logsToDelete > 0 && ! $this->option('force') && ! $this->confirm("Permanently delete {$logsToDelete} notification logs?", false)) {
            $this->warn('Notification log pruning cancelled.');

            return self::FAILURE;
        }

        $clearedResponses = (clone $responseQuery)->update(['provider_response' => null]);
        $deletedLogs = 0;

        (clone $deleteQuery)
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($logs) use (&$deletedLogs): void {
                $ids = $logs->pluck('id');

                $deletedLogs += NotificationLog::query()
                    ->withTrashed()
                    ->whereKey($ids)
                    ->forceDelete();
            });

        $this->info("Cleared {$clearedResponses} provider responses older than {$responseRetentionDays} days.");
        $this->info("Permanently deleted {$deletedLogs} notification logs older than {$logRetentionDays} days.");

        return self::SUCCESS;
    }

    private function retentionDays(string $option, int $default): int
    {
        $value = $this->option($option);

        if ($value === null || $value === '') {
            return max(1, $default);
        }

        return max(1, (int) $value);
    }
}
