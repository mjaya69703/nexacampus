<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'employee_leave_type_id',
        'year',
        'allocated_days',
        'used_days',
        'pending_days',
        'carried_over_days',
        'expires_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated_days' => 'decimal:2',
            'used_days' => 'decimal:2',
            'pending_days' => 'decimal:2',
            'carried_over_days' => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveType::class, 'employee_leave_type_id');
    }

    public function availableDays(): float
    {
        return (float) $this->allocated_days + (float) $this->carried_over_days - (float) $this->used_days - (float) $this->pending_days;
    }
}
