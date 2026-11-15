<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Scope to students who are officially enrolled in the offering.
     *
     * Draft details are used while a student is composing a KRS and must not
     * leak into lecturer rosters, attendance, or class monitoring.
     */
    public function scopeOfficiallyEnrolled(Builder $query): Builder
    {
        return $query
            ->where('study_plan_details.status', 'Taken')
            ->whereHas('studyPlan', fn (Builder $studyPlan) => $studyPlan->where('study_plans.status', 'Approved'));
    }

    /**
     * Scope to details reserving a seat while the KRS is still active.
     */
    public function scopeActiveEnrollment(Builder $query): Builder
    {
        return $query
            ->whereIn('study_plan_details.status', ['Draft', 'Taken'])
            ->whereHas('studyPlan', fn (Builder $studyPlan) => $studyPlan->whereIn('study_plans.status', ['Draft', 'Submitted', 'Approved']));
    }
}
