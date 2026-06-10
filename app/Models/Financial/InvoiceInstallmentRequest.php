<?php

namespace App\Models\Financial;

use App\Models\Academic\StudentProfile;
use App\Models\Organization\ApprovalRequest;
use App\Support\Financial\InstallmentApprovalService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InvoiceInstallmentRequest extends Model
{
    use LogsActivity;

    protected $fillable = [
        'student_invoice_id',
        'approval_request_id',
        'student_profile_id',
        'requested_tenor',
        'requested_fee_amount',
        'simulated_total_amount',
        'simulation_snapshot',
        'status',
        'student_reason',
        'finance_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_tenor' => 'integer',
            'requested_fee_amount' => 'decimal:2',
            'simulated_total_amount' => 'decimal:2',
            'simulation_snapshot' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('invoice_installment_request')
            ->logOnly(['student_invoice_id', 'student_profile_id', 'requested_tenor', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'student_invoice_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function markApprovalApproved(?int $userId = null, ?string $notes = null): void
    {
        app(InstallmentApprovalService::class)->approveFromApproval($this, $userId, $notes);
    }

    public function markApprovalRejected(?int $userId = null, ?string $notes = null): void
    {
        app(InstallmentApprovalService::class)->rejectFromApproval($this, $userId, $notes);
    }

    public function markApprovalCancelled(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'finance_notes' => $notes ?: $this->finance_notes,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }
}
