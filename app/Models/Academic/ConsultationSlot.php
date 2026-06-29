<?php

namespace App\Models\Academic;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ConsultationSlot extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'lecturer_profile_id',
        'title',
        'weekday',
        'start_time',
        'end_time',
        'slot_minutes',
        'capacity',
        'consultation_mode',
        'location',
        'meeting_link',
        'starts_on',
        'ends_on',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'slot_minutes' => 'integer',
            'capacity' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('consultation_slot')
            ->logOnly(['lecturer_profile_id', 'weekday', 'start_time', 'end_time', 'capacity', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ConsultationAppointment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function weekdayLabel(): string
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ][$this->weekday] ?? '-';
    }

    public function modeLabel(): string
    {
        return match ($this->consultation_mode) {
            'online' => 'Online',
            'hybrid' => 'Hybrid',
            default => 'Tatap muka',
        };
    }

    public function coversDate(CarbonInterface $date): bool
    {
        if (! $this->is_active || (int) $date->isoWeekday() !== (int) $this->weekday) {
            return false;
        }

        if ($this->starts_on && $date->lt($this->starts_on)) {
            return false;
        }

        return ! ($this->ends_on && $date->gt($this->ends_on));
    }
}
