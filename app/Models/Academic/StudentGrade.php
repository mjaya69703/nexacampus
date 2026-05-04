<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentGrade extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'student_grades';

    protected $fillable = [
        'study_plan_detail_id',
        'final_score',
        'letter_grade',
        'grade_point',
        'result_status',
        'grade_status',
        'graded_at',
        'graded_by',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'graded_at' => 'datetime',
            'final_score' => 'decimal:2',
            'grade_point' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_grade')
            ->logOnly(['study_plan_detail_id', 'final_score', 'letter_grade', 'grade_point', 'result_status', 'grade_status', 'graded_at', 'graded_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studyPlanDetail(): BelongsTo
    {
        return $this->belongsTo(StudyPlanDetail::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function components(): HasMany
    {
        return $this->hasMany(StudentGradeComponent::class);
    }
}
