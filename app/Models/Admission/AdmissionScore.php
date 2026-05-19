<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdmissionScore extends Model
{
    use LogsActivity;

    protected $fillable = [
        'admission_application_id',
        'admission_exam_schedule_id',
        'score_type',
        'score',
        'weight',
        'notes',
        'scored_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'weight' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admission_score')
            ->logOnly(['admission_application_id', 'admission_exam_schedule_id', 'score_type', 'score', 'weight'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AdmissionExamSchedule::class, 'admission_exam_schedule_id');
    }

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
