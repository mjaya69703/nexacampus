<?php

namespace App\Models\StudentService;

use App\Models\Academic\StudyProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GraduationPolicy extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'study_program_id',
        'minimum_semester',
        'minimum_passed_credits',
        'minimum_gpa',
        'require_active_status',
        'require_no_financial_hold',
        'require_no_incomplete_grade',
        'require_open_yudisium_period',
        'is_active',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_semester' => 'integer',
            'minimum_passed_credits' => 'integer',
            'minimum_gpa' => 'decimal:2',
            'require_active_status' => 'boolean',
            'require_no_financial_hold' => 'boolean',
            'require_no_incomplete_grade' => 'boolean',
            'require_open_yudisium_period' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('graduation_policy')
            ->logOnly(['name', 'study_program_id', 'minimum_semester', 'minimum_passed_credits', 'minimum_gpa', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }
}
