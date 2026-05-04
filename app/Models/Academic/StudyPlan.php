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

class StudyPlan extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'study_plans';

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'student_registration_id',
        'semester_no',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('study_plan')
            ->logOnly(['student_profile_id', 'academic_year_id', 'semester_no', 'status', 'submitted_at', 'approved_at', 'approved_by'])
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

    public function studentRegistration(): BelongsTo
    {
        return $this->belongsTo(StudentRegistration::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(StudyPlanDetail::class);
    }
}
