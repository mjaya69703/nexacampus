<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeLeaveType extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'approval_template_id',
        'name',
        'code',
        'default_days_per_year',
        'requires_approval',
        'is_paid',
        'is_active',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'default_days_per_year' => 'decimal:2',
            'requires_approval' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee_leave_type')
            ->logOnly(['name', 'code', 'default_days_per_year', 'requires_approval', 'is_paid', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function approvalTemplate(): BelongsTo
    {
        return $this->belongsTo(ApprovalTemplate::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }
}
