<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AttendanceSession extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'attendance_sessions';

    protected $fillable = [
        'course_offering_id',
        'course_schedule_id',
        'lecturer_profile_id',
        'meeting_no',
        'meeting_date',
        'start_time',
        'end_time',
        'topic',
        'notes',
        'status',
        'attendance_method',
        'qr_secret',
        'qr_interval_seconds',
        'opened_at',
        'closed_at',
        'late_after_minutes',
        'geo_required',
        'photo_required',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'geo_required' => 'boolean',
        'photo_required' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly($this->fillable);
    }

    // Relations
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function courseSchedule(): BelongsTo
    {
        return $this->belongsTo(CourseSchedule::class);
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
