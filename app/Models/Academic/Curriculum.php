<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Curriculum extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'curriculums';

    protected $fillable = [
        'study_program_id',
        'name',
        'code',
        'start_year',
        'end_year',
        'is_active',
        'desc',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('curriculum')
            ->logOnly(['name', 'code', 'start_year', 'end_year', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function curriculumCourses(): HasMany
    {
        return $this->hasMany(CurriculumCourse::class);
    }

    public function courses()
    {
        return $this->hasManyThrough(
            Course::class,
            CurriculumCourse::class,
            'curriculum_id',
            'id',
            'id',
            'course_id'
        );
    }
}
