<?php

namespace App\Models\Organization;

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeePositionAssignment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'employee_profile_id',
        'organizational_position_id',
        'faculty_id',
        'study_program_id',
        'work_unit_id',
        'starts_at',
        'ends_at',
        'is_primary',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee_position_assignment')
            ->logOnly(['employee_profile_id', 'organizational_position_id', 'faculty_id', 'study_program_id', 'work_unit_id', 'starts_at', 'ends_at', 'is_primary', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(OrganizationalPosition::class, 'organizational_position_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function workUnit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class);
    }

    public function isCurrentlyActive(): bool
    {
        return $this->is_active && ($this->ends_at === null || $this->ends_at->isFuture() || $this->ends_at->isToday());
    }
}
