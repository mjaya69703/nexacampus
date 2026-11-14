<?php

namespace App\Models\Library;

use App\Enums\BookCopyStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BookCopy extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'book_id',
        'code',
        'status',
        'condition_notes',
        'acquired_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('book_copy')
            ->logOnly(['code', 'status', 'condition_notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BookReservation::class);
    }

    public function activeLoan(): HasOne
    {
        // Sengaja tidak memakai latestOfMany(): eager loading-nya menambahkan
        // whereIn('book_copy_id') tanpa kualifikasi tabel sehingga bentrok
        // dengan kolom book_copy_id pada subquery agregat (ambiguous column).
        return $this->hasOne(BookLoan::class)
            ->whereIn('book_loans.status', ['borrowed', 'overdue'])
            ->orderByDesc('book_loans.id');
    }

    public function isAvailable(): bool
    {
        return $this->status === BookCopyStatus::AVAILABLE->value;
    }

    public function statusLabel(): string
    {
        return BookCopyStatus::tryFrom((string) $this->status)?->label() ?? ucfirst((string) $this->status);
    }

    public function statusTone(): string
    {
        return BookCopyStatus::tryFrom((string) $this->status)?->tone() ?? 'gray';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
