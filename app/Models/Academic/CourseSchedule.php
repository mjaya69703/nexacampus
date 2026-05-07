<?php

namespace App\Models\Academic;

use App\Models\Campus\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CourseSchedule extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'course_schedules';

    protected $fillable = [
        'course_offering_id',
        'lecturer_profile_id',
        'room_id',
        'day_of_week',
        'start_time',
        'end_time',
        'session_type',
        'delivery_mode',
        'meeting_link',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('course_schedule')
            ->logOnly(['course_offering_id', 'lecturer_profile_id', 'day_of_week', 'start_time', 'end_time', 'session_type', 'delivery_mode', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
