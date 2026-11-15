<?php

namespace App\Models\Library;

use App\Enums\BookLoanStatus;
use App\Enums\FinePaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BookLoan extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'book_copy_id',
        'user_id',
        'borrowed_at',
        'due_at',
        'returned_at',
        'status',
        'renewal_count',
        'renewed_at',
        'fine_amount',
        'fine_paid',
        'fine_paid_at',
        'fine_payment_status',
        'fine_proof_path',
        'fine_proof_original_name',
        'fine_payment_notes',
        'fine_submitted_at',
        'fine_verified_at',
        'fine_verified_by',
        'fine_rejection_reason',
        'due_reminder_h3_sent_at',
        'due_reminder_h1_sent_at',
        'overdue_notified_at',
        'notes',
        'processed_by',
        'returned_by',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'renewal_count' => 'integer',
            'renewed_at' => 'datetime',
            'fine_amount' => 'decimal:2',
            'fine_paid' => 'boolean',
            'fine_paid_at' => 'datetime',
            'fine_submitted_at' => 'datetime',
            'fine_verified_at' => 'datetime',
            'due_reminder_h3_sent_at' => 'datetime',
            'due_reminder_h1_sent_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('book_loan')
            ->logOnly([
                'status', 'due_at', 'renewal_count', 'renewed_at', 'returned_at',
                'fine_amount', 'fine_paid', 'fine_payment_status', 'fine_verified_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function fineVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fine_verified_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), BookLoanStatus::activeValues());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), BookLoanStatus::BORROWED->value)
            ->where($query->qualifyColumn('due_at'), '<', now());
    }

    public function isActive(): bool
    {
        return in_array($this->status, BookLoanStatus::activeValues(), true);
    }

    public function statusLabel(): string
    {
        return BookLoanStatus::tryFrom((string) $this->status)?->label() ?? ucfirst((string) $this->status);
    }

    public function statusTone(): string
    {
        return BookLoanStatus::tryFrom((string) $this->status)?->tone() ?? 'gray';
    }

    public function daysLate(?Carbon $at = null): int
    {
        if (! $this->due_at) {
            return 0;
        }

        $at = $this->returned_at ?? $at ?? now();

        if ($at->lessThanOrEqualTo($this->due_at)) {
            return 0;
        }

        return (int) $this->due_at->copy()->startOfDay()->diffInDays($at->copy()->startOfDay());
    }

    public function outstandingFine(): float
    {
        if ($this->fine_paid) {
            return 0.0;
        }

        return (float) $this->fine_amount;
    }

    public function fineStatus(): FinePaymentStatus
    {
        return FinePaymentStatus::tryFrom((string) $this->fine_payment_status) ?? FinePaymentStatus::UNPAID;
    }

    public function fineStatusLabel(): string
    {
        if ((float) $this->fine_amount <= 0) {
            return 'Tanpa denda';
        }

        return $this->fineStatus()->label();
    }

    public function fineStatusTone(): string
    {
        if ((float) $this->fine_amount <= 0) {
            return 'gray';
        }

        return $this->fineStatus()->tone();
    }

    public function hasFine(): bool
    {
        return (float) $this->fine_amount > 0;
    }

    public function isFineProofPending(): bool
    {
        return $this->fineStatus() === FinePaymentStatus::PENDING && ! $this->fine_paid;
    }

    public function canSubmitFineProof(): bool
    {
        if (! $this->hasFine() || $this->fine_paid) {
            return false;
        }

        return in_array($this->fineStatus(), [FinePaymentStatus::UNPAID, FinePaymentStatus::REJECTED], true);
    }
}
