<?php

namespace App\Models\Alumni;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AlumniEvent extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'event_type',
        'event_date',
        'end_date',
        'location',
        'is_online',
        'meeting_url',
        'max_participants',
        'registration_deadline',
        'poster_path',
        'is_published',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'end_date' => 'datetime',
            'registration_deadline' => 'datetime',
            'is_online' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('alumni_event')
            ->logOnly(['title', 'event_type', 'event_date', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AlumniEventParticipant::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function availableSlots(): ?int
    {
        if (! $this->max_participants) {
            return null;
        }

        return max(0, $this->max_participants - $this->participants()->count());
    }
}
