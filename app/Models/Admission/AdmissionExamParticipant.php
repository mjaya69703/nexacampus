<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdmissionExamParticipant extends Model
{
    use LogsActivity;

    protected $fillable = [
        'admission_exam_schedule_id',
        'admission_application_id',
        'attendance_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admission_exam_participant')
            ->logOnly(['admission_exam_schedule_id', 'admission_application_id', 'attendance_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AdmissionExamSchedule::class, 'admission_exam_schedule_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
