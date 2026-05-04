<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Course extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('course')
            ->logOnly([
                'code',
                'name',
                'short_name',
                'credits',
                'semester_recommendation',
                'requirement_type',
                'category_type',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'credits',
        'semester_recommendation',
        'requirement_type',
        'category_type',
        'is_active',
        'desc',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'semester_recommendation' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_course_id'
        )->withTimestamps();
    }

    public function requiredByCourses(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'prerequisite_course_id',
            'course_id'
        )->withTimestamps();
    }

    public function prerequisiteRows(): HasMany
    {
        return $this->hasMany(CoursePrerequisite::class, 'course_id');
    }

    public function requiredByRows(): HasMany
    {
        return $this->hasMany(CoursePrerequisite::class, 'prerequisite_course_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(CourseScope::class, 'course_id');
    }

    public function latestScope(): HasOne
    {
        return $this->hasOne(CourseScope::class, 'course_id')->latestOfMany();
    }

    public function curriculumCourses(): HasMany
    {
        return $this->hasMany(CurriculumCourse::class);
    }

    public function curriculums()
    {
        return $this->hasManyThrough(
            Curriculum::class,
            CurriculumCourse::class,
            'course_id',
            'id',
            'id',
            'curriculum_id'
        );
    }
}
