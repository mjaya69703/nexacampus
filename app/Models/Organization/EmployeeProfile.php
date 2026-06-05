<?php

namespace App\Models\Organization;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeProfile extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'primary_work_unit_id',
        'employee_number',
        'employment_type',
        'employment_status',
        'join_date',
        'end_date',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee_profile')
            ->logOnly(['user_id', 'employee_number', 'employment_type', 'employment_status', 'primary_work_unit_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryWorkUnit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'primary_work_unit_id');
    }

    public function positionAssignments(): HasMany
    {
        return $this->hasMany(EmployeePositionAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(EmployeeAttendanceRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function activePositionAssignments(): HasMany
    {
        return $this->positionAssignments()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString());
            });
    }
}
