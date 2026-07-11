<?php

namespace App\Models\StudentService;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Financial\StudentInvoice;
use App\Models\Organization\ApprovalRequest;
use App\Support\StudentService\StudentLeaveApplicationService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentLeaveApplication extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'application_number',
        'approval_request_id',
        'student_profile_id',
        'academic_year_id',
        'semester',
        'duration_semesters',
        'reason_category',
        'reason',
        'attachment_path',
        'attachment_name',
        'status',
        'student_notes',
        'admin_notes',
        'leave_fee_amount',
        'leave_fee_due_date',
        'leave_fee_invoice_id',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'activated_by',
        'activated_at',
        'returned_by',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'duration_semesters' => 'integer',
            'leave_fee_amount' => 'decimal:2',
            'leave_fee_due_date' => 'date',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_leave_application')
            ->logOnly(['application_number', 'student_profile_id', 'academic_year_id', 'semester', 'duration_semesters', 'status', 'leave_fee_amount', 'leave_fee_invoice_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StudentLeaveStatusHistory::class);
    }

    public function leaveFeeInvoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'leave_fee_invoice_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function markApprovalApproved(?int $userId = null, ?string $notes = null): void
    {
        app(StudentLeaveApplicationService::class)->approveFromApproval($this, $userId, $notes);
    }

    public function markApprovalRejected(?int $userId = null, ?string $notes = null): void
    {
        app(StudentLeaveApplicationService::class)->rejectFromApproval($this, $userId, $notes);
    }

    public function markApprovalCancelled(?int $userId = null, ?string $notes = null): void
    {
        app(StudentLeaveApplicationService::class)->requestRevisionFromApproval($this, $userId, $notes ?: 'Approval dibatalkan.');
    }

    public function markInvoicePaid(?int $userId = null): void
    {
        app(StudentLeaveApplicationService::class)->markFeePaid($this, $userId);
    }

    public function approvalReadinessError(): ?string
    {
        return $this->reviewed_at
            ? null
            : 'Pengajuan cuti harus direview dari halaman detail cuti untuk menentukan biaya dan jatuh tempo sebelum approval.';
    }
}
