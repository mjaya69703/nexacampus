<?php

namespace App\Models\StudentService;

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudyProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GraduationBatch extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'academic_period_id',
        'study_program_id',
        'name',
        'code',
        'yudisium_date',
        'sk_number',
        'sk_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'yudisium_date' => 'date',
            'sk_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('graduation_batch')
            ->logOnly(['academic_period_id', 'study_program_id', 'name', 'code', 'yudisium_date', 'sk_number', 'sk_date', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(GraduationApplication::class);
    }
}
