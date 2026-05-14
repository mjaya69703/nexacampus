<?php

namespace App\Models\Financial;

use App\Models\Academic\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinancialHold extends Model
{
    use LogsActivity;

    protected $fillable = [
        'student_profile_id',
        'student_invoice_id',
        'financial_clearance_policy_id',
        'hold_type',
        'status',
        'reason',
        'starts_at',
        'blocked_at',
        'waived_until',
        'released_at',
        'released_by',
        'release_notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'blocked_at' => 'datetime',
            'waived_until' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('financial_hold')
            ->logOnly(['student_profile_id', 'student_invoice_id', 'hold_type', 'status', 'blocked_at', 'waived_until'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'student_invoice_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(FinancialClearancePolicy::class, 'financial_clearance_policy_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isWaived(): bool
    {
        return $this->status === 'waived'
            && $this->waived_until
            && $this->waived_until->isFuture();
    }

    public function isBlocking(): bool
    {
        return $this->status === 'active'
            && ($this->policy?->mode ?? 'blocking') === 'blocking'
            && $this->blocked_at
            && $this->blocked_at->isPast();
    }
}
