<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudyProgram extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('study-program')
            ->logOnly([
                'faculty_id',
                'name',
                'code',
                'short_name',
                'degree',
                'prefix_degree',
                'suffix_degree',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'faculty_id',
        'name',
        'code',
        'short_name',
        'degree',
        'prefix_degree',
        'suffix_degree',
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

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function courseScopes(): HasMany
    {
        return $this->hasMany(CourseScope::class, 'scope_id')
            ->where('scope_type', 'study_program');
    }

    public function curriculums(): HasMany
    {
        return $this->hasMany(Curriculum::class);
    }
}
