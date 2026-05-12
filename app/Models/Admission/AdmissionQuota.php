<?php

namespace App\Models\Admission;

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdmissionQuota extends Model
{
    use LogsActivity;

    protected $fillable = [
        'admission_period_id',
        'faculty_id',
        'study_program_id',
        'class_type',
        'quota',
        'accepted_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quota' => 'integer',
            'accepted_count' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admission_quota')
            ->logOnly(['admission_period_id', 'faculty_id', 'study_program_id', 'class_type', 'quota', 'accepted_count'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }
}
