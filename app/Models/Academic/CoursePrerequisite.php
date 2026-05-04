<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CoursePrerequisite extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'course_prerequisites';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('course-prerequisite')
            ->logOnly(['course_id', 'prerequisite_course_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'course_id',
        'prerequisite_course_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function prerequisiteCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'prerequisite_course_id');
    }
}
