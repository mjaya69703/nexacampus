<?php

namespace App\Models\StudentService;

use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\StudentInvoice;
use App\Models\Organization\ApprovalRequest;
use App\Support\StudentService\StudentTransferRequestService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentTransferRequest extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'request_number',
        'approval_request_id',
        'student_profile_id',
        'from_study_program_id',
        'to_study_program_id',
        'current_semester',
        'recommended_semester',
        'transfer_type',
        'from_class_type',
        'to_class_type',
        'reason',
        'attachment_path',
        'attachment_name',
        'status',
        'student_notes',
        'admin_notes',
        'academic_evaluation_notes',
        'credit_mapping_notes',
        'finance_notes',
        'transfer_fee_amount',
        'transfer_fee_due_date',
        'transfer_fee_invoice_id',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'applied_by',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'current_semester' => 'integer',
            'recommended_semester' => 'integer',
            'transfer_fee_amount' => 'decimal:2',
            'transfer_fee_due_date' => 'date',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_transfer_request')
            ->logOnly(['request_number', 'student_profile_id', 'from_study_program_id', 'to_study_program_id', 'recommended_semester', 'transfer_type', 'from_class_type', 'to_class_type', 'status', 'transfer_fee_amount', 'transfer_fee_invoice_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function fromStudyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'from_study_program_id');
    }

    public function toStudyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'to_study_program_id');
    }

    public function transferFeeInvoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'transfer_fee_invoice_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StudentTransferStatusHistory::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function markApprovalApproved(?int $userId = null, ?string $notes = null): void
    {
        app(StudentTransferRequestService::class)->approveFromApproval($this, $userId, $notes);
    }

    public function markApprovalRejected(?int $userId = null, ?string $notes = null): void
    {
        app(StudentTransferRequestService::class)->rejectFromApproval($this, $userId, $notes);
    }

    public function markApprovalCancelled(?int $userId = null, ?string $notes = null): void
    {
        app(StudentTransferRequestService::class)->requestRevisionFromApproval($this, $userId, $notes ?: 'Approval dibatalkan.');
    }

    public function markInvoicePaid(?int $userId = null): void
    {
        app(StudentTransferRequestService::class)->markFeePaid($this, $userId);
    }

    public function approvalReadinessError(): ?string
    {
        return $this->reviewed_at
            ? null
            : 'Pengajuan pindah harus direview dari halaman detail pindah untuk melengkapi evaluasi dan biaya sebelum approval.';
    }
}
