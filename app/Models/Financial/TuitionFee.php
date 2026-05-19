<?php

namespace App\Models\Financial;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TuitionFee extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'academic_year_id',
        'study_program_id',
        'semester',
        'base_fee',
        'lab_fee',
        'library_fee',
        'activity_fee',
        'late_penalty_per_day',
        'payment_deadline',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'base_fee' => 'decimal:2',
            'lab_fee' => 'decimal:2',
            'library_fee' => 'decimal:2',
            'activity_fee' => 'decimal:2',
            'late_penalty_per_day' => 'decimal:2',
            'payment_deadline' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tuition_fee')
            ->logOnly(['academic_year_id', 'study_program_id', 'semester', 'base_fee', 'payment_deadline', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalAmount(): float
    {
        return (float) $this->base_fee
            + (float) $this->lab_fee
            + (float) $this->library_fee
            + (float) $this->activity_fee;
    }
}
