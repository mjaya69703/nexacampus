<?php

namespace App\Models\Library;

use App\Enums\BookReservationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BookReservation extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'book_id',
        'book_copy_id',
        'book_loan_id',
        'user_id',
        'status',
        'reserved_at',
        'pickup_planned_at',
        'ready_at',
        'expires_at',
        'ready_notified_at',
        'fulfilled_at',
        'cancelled_at',
        'expired_at',
        'notes',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'pickup_planned_at' => 'date',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
            'ready_notified_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('book_reservation')
            ->logOnly(['status', 'book_copy_id', 'book_loan_id', 'pickup_planned_at', 'ready_at', 'expires_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(BookLoan::class, 'book_loan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), BookReservationStatus::activeValues());
    }

    public function isActive(): bool
    {
        return in_array($this->status, BookReservationStatus::activeValues(), true);
    }

    public function statusLabel(): string
    {
        return BookReservationStatus::tryFrom((string) $this->status)?->label() ?? ucfirst((string) $this->status);
    }

    public function statusTone(): string
    {
        return BookReservationStatus::tryFrom((string) $this->status)?->tone() ?? 'gray';
    }
}
