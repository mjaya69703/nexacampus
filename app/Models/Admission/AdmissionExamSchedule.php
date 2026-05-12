<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdmissionExamSchedule extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'admission_period_id',
        'exam_type',
        'title',
        'exam_date',
        'exam_time',
        'venue',
        'meeting_link',
        'quota',
        'registered_count',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'exam_time' => 'datetime:H:i',
            'quota' => 'integer',
            'registered_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admission_exam_schedule')
            ->logOnly(['admission_period_id', 'exam_type', 'title', 'exam_date', 'exam_time', 'quota', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AdmissionExamParticipant::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AdmissionScore::class);
    }
}
