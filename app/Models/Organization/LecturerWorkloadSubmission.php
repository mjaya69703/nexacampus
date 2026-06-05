<?php

namespace App\Models\Organization;

use App\Models\Academic\LecturerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LecturerWorkloadSubmission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'teaching_sks' => 'decimal:2',
            'structural_sks' => 'decimal:2',
            'tridharma_sks' => 'decimal:2',
            'total_sks' => 'decimal:2',
            'generated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(LecturerWorkloadPeriod::class, 'lecturer_workload_period_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LecturerWorkloadItem::class);
    }

    public function markApprovalApproved(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'review_notes' => $notes ?: $this->review_notes,
        ]);
    }

    public function markApprovalRejected(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'review_notes' => $notes ?: $this->review_notes,
        ]);
    }

    public function markApprovalCancelled(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'revision',
            'approval_request_id' => null,
            'review_notes' => $notes ?: $this->review_notes,
        ]);
    }
}
