<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudyPlanDetail extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'study_plan_details';

    protected $fillable = [
        'study_plan_id',
        'course_offering_id',
        'credits',
        'is_repeat',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_repeat' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('study_plan_detail')
            ->logOnly(['course_offering_id', 'credits', 'is_repeat', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function studentGrade(): HasOne
    {
        return $this->hasOne(StudentGrade::class);
    }
}
