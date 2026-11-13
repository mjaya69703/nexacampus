<?php

namespace App\Models\Library;

use App\Enums\BookLoanStatus;
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
        'fine_amount',
        'fine_paid',
        'fine_paid_at',
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
            'fine_amount' => 'decimal:2',
            'fine_paid' => 'boolean',
            'fine_paid_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('book_loan')
            ->logOnly(['status', 'due_at', 'returned_at', 'fine_amount', 'fine_paid'])
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
}
