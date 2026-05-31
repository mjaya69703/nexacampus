<?php

namespace App\Models\Organization;

use App\Models\Academic\LecturerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TridharmaRecord extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'funding_amount' => 'decimal:2',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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

    public function members(): HasMany
    {
        return $this->hasMany(TridharmaMember::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(TridharmaMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(TridharmaBudget::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(TridharmaOutput::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TridharmaAttachment::class);
    }

    public function userCanAccess(User $user): bool
    {
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        return $this->members()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function markApprovalApproved(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'admin_notes' => $notes ?: $this->admin_notes,
        ]);
    }

    public function markApprovalRejected(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'admin_notes' => $notes ?: $this->admin_notes,
        ]);
    }

    public function markApprovalCancelled(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'draft',
            'approval_request_id' => null,
            'admin_notes' => $notes ?: $this->admin_notes,
        ]);
    }
}
