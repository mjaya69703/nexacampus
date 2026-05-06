<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CourseOffering extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('course-offering')
            ->logOnly(['academic_year_id', 'study_program_id', 'course_id', 'label', 'code', 'semester_no', 'capacity', 'credits', 'is_required', 'delivery_mode', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'academic_year_id',
        'study_program_id',
        'curriculum_id',
        'course_id',
        'label',
        'code',
        'semester_no',
        'capacity',
        'credits',
        'total_meetings',
        'class_start_date',
        'class_end_date',
        'is_required',
        'delivery_mode',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'class_start_date' => 'date',
            'class_end_date' => 'date',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturers(): HasMany
    {
        return $this->hasMany(CourseOfferingLecturer::class);
    }

    public function courseSchedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function courseMaterials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class);
    }
}
