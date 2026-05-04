<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudyResult extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'study_results';

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'study_plan_id',
        'semester_no',
        'total_courses',
        'total_credits_taken',
        'total_credits_passed',
        'semester_gpa',
        'cumulative_gpa',
        'status',
        'finalized_at',
        'finalized_by',
        'published_at',
        'published_by',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'semester_gpa' => 'decimal:2',
            'cumulative_gpa' => 'decimal:2',
            'finalized_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('study_result')
            ->logOnly([
                'student_profile_id',
                'academic_year_id',
                'total_courses',
                'total_credits_taken',
                'total_credits_passed',
                'semester_gpa',
                'cumulative_gpa',
                'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
