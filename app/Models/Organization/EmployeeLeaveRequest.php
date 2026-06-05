<?php

namespace App\Models\Organization;

use App\Models\User;
use App\Support\Organization\EmployeeLeaveRequestService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeLeaveRequest extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'employee_profile_id',
        'employee_leave_type_id',
        'approval_request_id',
        'request_number',
        'starts_at',
        'ends_at',
        'total_days',
        'status',
        'reason',
        'employee_notes',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'cancelled_by',
        'cancelled_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'total_days' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('employee_leave_request')
            ->logOnly(['employee_profile_id', 'employee_leave_type_id', 'request_number', 'starts_at', 'ends_at', 'total_days', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveType::class, 'employee_leave_type_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmployeeLeaveAttachment::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function markApprovalApproved(?int $userId = null, ?string $notes = null): void
    {
        app(EmployeeLeaveRequestService::class)->approveFromApproval($this, $userId, $notes);
    }

    public function markApprovalRejected(?int $userId = null, ?string $notes = null): void
    {
        app(EmployeeLeaveRequestService::class)->rejectFromApproval($this, $userId, $notes);
    }

    public function markApprovalCancelled(?int $userId = null, ?string $notes = null): void
    {
        app(EmployeeLeaveRequestService::class)->cancel($this, $userId, $notes);
    }
}
