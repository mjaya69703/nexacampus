<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NotificationSetting extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const PROVIDER_OFFICIAL = 'official_cloud_api';

    public const PROVIDER_UNOFFICIAL = 'unofficial_web_session';

    protected $fillable = [
        'whatsapp_enabled',
        'web_push_enabled',
        'whatsapp_provider',
        'official_config',
        'unofficial_config',
        'fallback_channel',
        'retry_attempts',
        'timeout_seconds',
        'log_retention_days',
        'provider_response_retention_days',
        'last_health_status',
        'last_health_message',
        'last_health_checked_at',
    ];

    protected $hidden = [
        'official_config',
        'unofficial_config',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_enabled' => 'boolean',
            'web_push_enabled' => 'boolean',
            'official_config' => 'encrypted:array',
            'unofficial_config' => 'encrypted:array',
            'retry_attempts' => 'integer',
            'timeout_seconds' => 'integer',
            'log_retention_days' => 'integer',
            'provider_response_retention_days' => 'integer',
            'last_health_checked_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('notification-setting')
            ->logOnly([
                'whatsapp_enabled',
                'web_push_enabled',
                'whatsapp_provider',
                'fallback_channel',
                'retry_attempts',
                'timeout_seconds',
                'log_retention_days',
                'provider_response_retention_days',
                'last_health_status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'whatsapp_enabled' => false,
            'web_push_enabled' => false,
            'whatsapp_provider' => self::PROVIDER_OFFICIAL,
            'fallback_channel' => 'in_app',
            'retry_attempts' => 3,
            'timeout_seconds' => 15,
            'log_retention_days' => 90,
            'provider_response_retention_days' => 30,
        ]);
    }
}
