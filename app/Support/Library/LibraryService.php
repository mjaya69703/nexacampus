<?php

namespace App\Support\Library;

use App\Enums\BookCopyStatus;
use App\Enums\BookLoanStatus;
use App\Enums\BookReservationStatus;
use App\Models\Library\Book;
use App\Models\Library\BookCopy;
use App\Models\Library\BookLoan;
use App\Models\Library\BookReservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan sirkulasi perpustakaan.
 *
 * Service ini menjaga konsistensi status eksemplar, peminjaman, dan
 * reservasi agar tidak tersebar di controller:
 * pinjam → jatuh tempo → pengembalian/denda → antrean reservasi.
 */
class LibraryService
{
    public function refreshOverdueLoans(): int
    {
        return BookLoan::query()
            ->where('status', BookLoanStatus::BORROWED->value)
            ->where('due_at', '<', now())
            ->update([
                'status' => BookLoanStatus::OVERDUE->value,
                'updated_at' => now(),
            ]);
    }

    public function expireReservations(): int
    {
        $expired = 0;

        $reservations = BookReservation::query()
            ->where('status', BookReservationStatus::READY->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($reservations as $reservation) {
            DB::transaction(function () use ($reservation) {
                if ($reservation->book_copy_id) {
                    $copy = BookCopy::query()->lockForUpdate()->find($reservation->book_copy_id);

                    if ($copy && $copy->status === BookCopyStatus::RESERVED->value) {
                        $copy->update(['status' => BookCopyStatus::AVAILABLE->value]);
                    }
                }

                $reservation->update([
                    'status' => BookReservationStatus::EXPIRED->value,
                    'expired_at' => now(),
                ]);
            });

            $this->promoteNextReservation((int) $reservation->book_id);
            $expired++;
        }

        return $expired;
    }

    /**
     * @param  array{borrowed_at?: mixed, due_at?: mixed, notes?: ?string}  $options
     *
     * @throws ValidationException
     */
    public function borrow(User $borrower, BookCopy $copy, User $actor, array $options = []): BookLoan
    {
        $this->refreshOverdueLoans();
        $this->expireReservations();

        $loan = DB::transaction(function () use ($borrower, $copy, $actor, $options) {
            $lockedCopy = BookCopy::query()->lockForUpdate()->with('book')->findOrFail($copy->id);

            if (! $lockedCopy->book?->is_active) {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'Buku ini sedang tidak aktif dan tidak dapat dipinjam.',
                ]);
            }

            $allowedStatuses = [BookCopyStatus::AVAILABLE->value, BookCopyStatus::RESERVED->value];

            if (! in_array($lockedCopy->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'Eksemplar '.$lockedCopy->code.' tidak tersedia untuk dipinjam.',
                ]);
            }

            $reservation = null;

            if ($lockedCopy->status === BookCopyStatus::RESERVED->value) {
                $reservation = BookReservation::query()
                    ->where('book_copy_id', $lockedCopy->id)
                    ->where('user_id', $borrower->id)
                    ->where('status', BookReservationStatus::READY->value)
                    ->first();

                if (! $reservation) {
                    throw ValidationException::withMessages([
                        'book_copy_id' => 'Eksemplar ini sedang dipegang untuk reservasi pengguna lain.',
                    ]);
                }
            }

            $activeLoans = BookLoan::query()
                ->where('user_id', $borrower->id)
                ->active()
                ->count();

            if ($activeLoans >= $this->maxLoansFor($borrower)) {
                throw ValidationException::withMessages([
                    'user_id' => 'Batas maksimal peminjaman aktif untuk anggota ini sudah tercapai.',
                ]);
            }

            $hasUnpaidFine = BookLoan::query()
                ->where('user_id', $borrower->id)
                ->where('fine_paid', false)
                ->where('fine_amount', '>', 0)
                ->exists();

            if ($hasUnpaidFine) {
                throw ValidationException::withMessages([
                    'user_id' => 'Anggota masih memiliki denda yang belum dibayar.',
                ]);
            }

            $hasSameBook = BookLoan::query()
                ->where('user_id', $borrower->id)
                ->active()
                ->whereHas('copy', fn ($query) => $query->where('book_id', $lockedCopy->book_id))
                ->exists();

            if ($hasSameBook) {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'Anggota masih meminjam buku yang sama.',
                ]);
            }

            $borrowedAt = filled($options['borrowed_at'] ?? null)
                ? Carbon::parse($options['borrowed_at'])
                : now();

            $dueAt = filled($options['due_at'] ?? null)
                ? Carbon::parse($options['due_at'])
                : $borrowedAt->copy()->addDays((int) config('library.loan_days', 14))->endOfDay();

            if ($dueAt->lessThanOrEqualTo($borrowedAt)) {
                throw ValidationException::withMessages([
                    'due_at' => 'Tanggal jatuh tempo harus setelah tanggal pinjam.',
                ]);
            }

            $loan = BookLoan::create([
                'book_copy_id' => $lockedCopy->id,
                'user_id' => $borrower->id,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'status' => BookLoanStatus::BORROWED->value,
                'notes' => $options['notes'] ?? null,
                'processed_by' => $actor->id,
            ]);

            $lockedCopy->update(['status' => BookCopyStatus::BORROWED->value]);

            if ($reservation) {
                $reservation->update([
                    'status' => BookReservationStatus::FULFILLED->value,
                    'fulfilled_at' => now(),
                    'processed_by' => $actor->id,
                ]);
            } else {
                BookReservation::query()
                    ->where('book_id', $lockedCopy->book_id)
                    ->where('user_id', $borrower->id)
                    ->where('status', BookReservationStatus::READY->value)
                    ->update([
                        'status' => BookReservationStatus::FULFILLED->value,
                        'fulfilled_at' => now(),
                        'processed_by' => $actor->id,
                    ]);
            }

            return $loan;
        });

        return $loan->load(['copy.book', 'user']);
    }

    /**
     * @param  array{copy_status?: string, fine_amount?: mixed, fine_paid?: bool, notes?: ?string}  $options
     *
     * @throws ValidationException
     */
    public function returnBook(BookLoan $loan, User $actor, array $options = []): BookLoan
    {
        $this->refreshOverdueLoans();

        $returned = DB::transaction(function () use ($loan, $actor, $options) {
            $lockedLoan = BookLoan::query()->lockForUpdate()->with('copy.book')->findOrFail($loan->id);

            if (! $lockedLoan->isActive()) {
                throw ValidationException::withMessages([
                    'loan' => 'Peminjaman ini sudah dikembalikan atau tidak aktif.',
                ]);
            }

            $copyStatus = $options['copy_status'] ?? BookCopyStatus::AVAILABLE->value;

            if (! in_array($copyStatus, BookCopyStatus::manualValues(), true)) {
                throw ValidationException::withMessages([
                    'copy_status' => 'Kondisi eksemplar tidak valid.',
                ]);
            }

            $fineAmount = array_key_exists('fine_amount', $options) && $options['fine_amount'] !== null
                ? max(0, (float) $options['fine_amount'])
                : $this->calculateFine($lockedLoan);

            $finePaid = (bool) ($options['fine_paid'] ?? false);
            $isLost = $copyStatus === BookCopyStatus::LOST->value;

            $lockedLoan->update([
                'returned_at' => now(),
                'status' => $isLost ? BookLoanStatus::LOST->value : BookLoanStatus::RETURNED->value,
                'fine_amount' => $fineAmount,
                'fine_paid' => $fineAmount > 0 ? $finePaid : false,
                'fine_paid_at' => $fineAmount > 0 && $finePaid ? now() : null,
                'notes' => $options['notes'] ?? $lockedLoan->notes,
                'returned_by' => $actor->id,
            ]);

            $copy = BookCopy::query()->lockForUpdate()->findOrFail($lockedLoan->book_copy_id);
            $copy->update([
                'status' => $copyStatus,
                'condition_notes' => $options['notes'] ?? $copy->condition_notes,
            ]);

            return $lockedLoan;
        });

        if (($options['copy_status'] ?? BookCopyStatus::AVAILABLE->value) === BookCopyStatus::AVAILABLE->value) {
            $this->promoteNextReservation((int) $returned->copy->book_id);
        }

        return $returned->refresh()->load(['copy.book', 'user']);
    }

    /**
     * @throws ValidationException
     */
    public function createReservation(User $user, Book $book, ?string $notes = null): BookReservation
    {
        $this->expireReservations();

        if (! $book->is_active) {
            throw ValidationException::withMessages([
                'book_id' => 'Buku ini sedang tidak aktif dan tidak dapat direservasi.',
            ]);
        }

        $hasActiveLoan = BookLoan::query()
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('copy', fn ($query) => $query->where('book_id', $book->id))
            ->exists();

        if ($hasActiveLoan) {
            throw ValidationException::withMessages([
                'book_id' => 'Anda masih meminjam buku ini.',
            ]);
        }

        $hasActiveReservation = BookReservation::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->active()
            ->exists();

        if ($hasActiveReservation) {
            throw ValidationException::withMessages([
                'book_id' => 'Anda sudah memiliki reservasi aktif untuk buku ini.',
            ]);
        }

        $activeReservations = BookReservation::query()
            ->where('user_id', $user->id)
            ->active()
            ->count();

        if ($activeReservations >= (int) config('library.max_active_reservations', 3)) {
            throw ValidationException::withMessages([
                'book_id' => 'Batas maksimal reservasi aktif sudah tercapai.',
            ]);
        }

        return BookReservation::create([
            'book_id' => $book->id,
            'user_id' => $user->id,
            'status' => BookReservationStatus::PENDING->value,
            'reserved_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function markReservationReady(BookReservation $reservation, BookCopy $copy, User $actor): BookReservation
    {
        $this->expireReservations();

        return DB::transaction(function () use ($reservation, $copy, $actor) {
            $lockedReservation = BookReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($lockedReservation->status !== BookReservationStatus::PENDING->value) {
                throw ValidationException::withMessages([
                    'reservation' => 'Hanya reservasi yang masih menunggu dapat disiapkan.',
                ]);
            }

            $lockedCopy = BookCopy::query()->lockForUpdate()->findOrFail($copy->id);

            if ((int) $lockedCopy->book_id !== (int) $lockedReservation->book_id) {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'Eksemplar tidak sesuai dengan buku yang direservasi.',
                ]);
            }

            if ($lockedCopy->status !== BookCopyStatus::AVAILABLE->value) {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'Eksemplar yang dipilih tidak tersedia.',
                ]);
            }

            $lockedCopy->update(['status' => BookCopyStatus::RESERVED->value]);

            $lockedReservation->update([
                'book_copy_id' => $lockedCopy->id,
                'status' => BookReservationStatus::READY->value,
                'ready_at' => now(),
                'expires_at' => now()->addDays((int) config('library.reservation_hold_days', 3))->endOfDay(),
                'processed_by' => $actor->id,
            ]);

            return $lockedReservation->refresh();
        });
    }

    /**
     * @throws ValidationException
     */
    public function fulfillReservation(BookReservation $reservation, User $actor): BookLoan
    {
        $reservation->loadMissing('copy');

        if ($reservation->status !== BookReservationStatus::READY->value || ! $reservation->copy) {
            throw ValidationException::withMessages([
                'reservation' => 'Reservasi belum siap diserahkan.',
            ]);
        }

        return $this->borrow($reservation->user, $reservation->copy, $actor);
    }

    public function cancelReservation(BookReservation $reservation, User $actor, ?string $reason = null): BookReservation
    {
        $cancelled = DB::transaction(function () use ($reservation, $actor, $reason) {
            $lockedReservation = BookReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if (! $lockedReservation->isActive()) {
                return $lockedReservation;
            }

            if ($lockedReservation->book_copy_id) {
                $copy = BookCopy::query()->lockForUpdate()->find($lockedReservation->book_copy_id);

                if ($copy && $copy->status === BookCopyStatus::RESERVED->value) {
                    $copy->update(['status' => BookCopyStatus::AVAILABLE->value]);
                }
            }

            $notes = trim(collect([$lockedReservation->notes, $reason])->filter()->implode("\n"));

            $lockedReservation->update([
                'status' => BookReservationStatus::CANCELLED->value,
                'cancelled_at' => now(),
                'notes' => $notes !== '' ? $notes : null,
                'processed_by' => $actor->id,
            ]);

            return $lockedReservation;
        });

        if ($cancelled->status === BookReservationStatus::CANCELLED->value) {
            $this->promoteNextReservation((int) $cancelled->book_id, $actor);
        }

        return $cancelled->refresh();
    }

    public function promoteNextReservation(int $bookId, ?User $actor = null): ?BookReservation
    {
        $reservation = BookReservation::query()
            ->where('book_id', $bookId)
            ->where('status', BookReservationStatus::PENDING->value)
            ->orderBy('reserved_at')
            ->first();

        if (! $reservation) {
            return null;
        }

        $copy = BookCopy::query()
            ->where('book_id', $bookId)
            ->where('status', BookCopyStatus::AVAILABLE->value)
            ->orderBy('id')
            ->first();

        if (! $copy) {
            return null;
        }

        $copy->update(['status' => BookCopyStatus::RESERVED->value]);

        $reservation->update([
            'book_copy_id' => $copy->id,
            'status' => BookReservationStatus::READY->value,
            'ready_at' => now(),
            'expires_at' => now()->addDays((int) config('library.reservation_hold_days', 3))->endOfDay(),
            'processed_by' => $actor?->id,
        ]);

        return $reservation->refresh();
    }

    public function maxLoansFor(User $user): int
    {
        if ($user->hasRole('student')) {
            return (int) config('library.max_active_loans.student', 3);
        }

        if ($user->hasRole('lecturer')) {
            return (int) config('library.max_active_loans.lecturer', 5);
        }

        return (int) config('library.max_active_loans.default', 3);
    }

    public function outstandingFine(User $user): float
    {
        return (float) BookLoan::query()
            ->where('user_id', $user->id)
            ->where('fine_paid', false)
            ->sum('fine_amount');
    }

    public function calculateFine(BookLoan $loan, ?Carbon $at = null): float
    {
        return $loan->daysLate($at) * (int) config('library.fine_per_day', 1000);
    }
}
