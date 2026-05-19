<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

// use Illuminate\Database\Eloquent\SoftDeletes;

class CourseMaterialDownload extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'course_material_downloads';

    protected $fillable = [
        'course_material_id',
        'student_profile_id',
        'downloaded_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('course_material_download')
            ->logOnly(['course_material_id', 'student_profile_id', 'downloaded_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function courseMaterial(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }
}
