<?php

namespace App\Models\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_key',
        'channel',
        'provider',
        'status',
        'user_id',
        'recipient_name',
        'recipient_phone',
        'recipient_email',
        'subject',
        'body',
        'payload',
        'source_type',
        'source_id',
        'provider_message_id',
        'provider_response',
        'error_message',
        'attempt_count',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'provider_response' => 'array',
            'attempt_count' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
