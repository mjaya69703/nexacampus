<?php

namespace App\Http\Controllers\Student;

use App\Enums\BookCopyStatus;
use App\Enums\BookLoanStatus;
use App\Enums\BookReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Library\Book;
use App\Models\Library\BookCategory;
use App\Models\Library\BookCopy;
use App\Models\Library\BookLoan;
use App\Models\Library\BookReservation;
use App\Support\Inertia\ShellProps;
use App\Support\Library\LibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StudentLibraryController extends Controller
{
    public function index(Request $request, LibraryService $service): Response
    {
        $service->refreshOverdueLoans();
        $service->expireReservations();

        $user = $request->user();

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:book_categories,id',
            'availability' => 'nullable|in:available,unavailable',
            'sort' => 'nullable|in:title,publication_year,created_at',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = Book::query()
            ->with('category:id,name')
            ->withCount([
                'copies',
                'copies as available_copies_count' => fn ($sub) => $sub->where('status', BookCopyStatus::AVAILABLE->value),
                'reservations as active_reservations_count' => fn ($sub) => $sub->where('status', BookReservationStatus::PENDING->value),
            ])
            ->where('is_active', true);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")
                ->orWhere('author', 'like', "%{$q}%")
                ->orWhere('publisher', 'like', "%{$q}%")
                ->orWhere('isbn', 'like', "%{$q}%"));
        }

        if (filled($validated['category_id'] ?? null)) {
            $query->where('book_category_id', $validated['category_id']);
        }

        if (($validated['availability'] ?? '') === 'available') {
            $query->whereHas('copies', fn ($sub) => $sub->where('status', BookCopyStatus::AVAILABLE->value));
        } elseif (($validated['availability'] ?? '') === 'unavailable') {
            $query->whereDoesntHave('copies', fn ($sub) => $sub->where('status', BookCopyStatus::AVAILABLE->value));
        }

        $sort = $validated['sort'] ?? 'title';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $books = $query->paginate(12)->withQueryString();

        $bookIds = collect($books->items())->pluck('id')->all();

        $loanEstimates = BookLoan::query()
            ->join('book_copies', 'book_copies.id', '=', 'book_loans.book_copy_id')
            ->whereIn('book_copies.book_id', $bookIds)
            ->whereIn('book_loans.status', BookLoanStatus::activeValues())
            ->whereNull('book_loans.deleted_at')
            ->groupBy('book_copies.book_id')
            ->selectRaw('book_copies.book_id as book_id, MIN(book_loans.due_at) as due_at')
            ->pluck('due_at', 'book_id');

        $holdEstimates = BookReservation::query()
            ->whereIn('book_id', $bookIds)
            ->where('status', BookReservationStatus::READY->value)
            ->groupBy('book_id')
            ->selectRaw('book_id, MIN(expires_at) as expires_at')
            ->pluck('expires_at', 'book_id');

        $estimatedReadyFor = function (int $bookId, int $availableCount) use ($loanEstimates, $holdEstimates): ?Carbon {
            if ($availableCount > 0) {
                return now();
            }

            return collect([$loanEstimates[$bookId] ?? null, $holdEstimates[$bookId] ?? null])
                ->filter()
                ->map(fn ($value) => Carbon::parse($value))
                ->sort()
                ->first();
        };

        $myLoans = BookLoan::query()
            ->where('user_id', $user->id)
            ->with(['copy:id,book_id,code', 'copy.book:id,title,author'])
            ->latest('borrowed_at')
            ->limit(20)
            ->get();

        $myReservations = BookReservation::query()
            ->where('user_id', $user->id)
            ->with(['book:id,title,author', 'copy:id,code', 'loan:id,due_at'])
            ->latest('reserved_at')
            ->limit(20)
            ->get();

        $activeLoanIds = $myLoans
            ->filter(fn (BookLoan $loan) => in_array($loan->status, BookLoanStatus::activeValues(), true))
            ->pluck('copy.book_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $activeReservationBookIds = $myReservations
            ->filter(fn (BookReservation $reservation) => in_array($reservation->status, BookReservationStatus::activeValues(), true))
            ->pluck('book_id')
            ->unique()
            ->values()
            ->all();

        return Inertia::render('Student/Library/Index', [
            'shell' => ShellProps::make($user, 'Perpustakaan', 'Katalog Perpustakaan'),
            'stats' => [
                'activeLoans' => $myLoans->filter(fn (BookLoan $loan) => in_array($loan->status, BookLoanStatus::activeValues(), true))->count(),
                'overdue' => $myLoans->filter(fn (BookLoan $loan) => $loan->isActive() && $loan->due_at?->isPast())->count(),
                'outstandingFine' => (float) $myLoans->sum(fn (BookLoan $loan) => $loan->outstandingFine()),
                'activeReservations' => $myReservations->filter(fn (BookReservation $reservation) => in_array($reservation->status, BookReservationStatus::activeValues(), true))->count(),
            ],
            'books' => [
                'rows' => collect($books->items())->values()->map(function (Book $book) use ($activeLoanIds, $activeReservationBookIds, $estimatedReadyFor) {
                    $availableCount = (int) $book->available_copies_count;
                    $estimatedReady = $estimatedReadyFor((int) $book->id, $availableCount);

                    return [
                        'id' => $book->id,
                        'title' => $book->title,
                        'author' => $book->author,
                        'publisher' => $book->publisher,
                        'year' => $book->publication_year,
                        'category' => $book->category?->name,
                        'shelfLocation' => $book->shelf_location,
                        'description' => $book->description,
                        'coverUrl' => $book->cover_path ? Storage::disk('public')->url($book->cover_path) : null,
                        'copiesCount' => (int) $book->copies_count,
                        'availableCount' => $availableCount,
                        'queueCount' => (int) $book->active_reservations_count,
                        'estimatedReadyAt' => $estimatedReady?->format('d M Y'),
                        'hasActiveLoan' => in_array($book->id, $activeLoanIds, true),
                        'hasActiveReservation' => in_array($book->id, $activeReservationBookIds, true),
                        'canReserve' => ! in_array($book->id, $activeLoanIds, true)
                            && ! in_array($book->id, $activeReservationBookIds, true),
                        'detailUrl' => route('student.library.books.show', $book),
                    ];
                })->all(),
                'currentPage' => $books->currentPage(),
                'lastPage' => $books->lastPage(),
                'perPage' => $books->perPage(),
                'total' => $books->total(),
            ],
            'myLoans' => $myLoans->map(function (BookLoan $loan) use ($service) {
                $eligibility = $service->renewalEligibility($loan);

                return [
                    'id' => $loan->id,
                    'bookTitle' => $loan->copy?->book?->title,
                    'author' => $loan->copy?->book?->author,
                    'copyCode' => $loan->copy?->code,
                    'borrowedAt' => $loan->borrowed_at?->format('d M Y'),
                    'dueAt' => $loan->due_at?->format('d M Y'),
                    'returnedAt' => $loan->returned_at?->format('d M Y'),
                    'status' => $loan->status,
                    'statusLabel' => $loan->statusLabel(),
                    'statusTone' => $loan->statusTone(),
                    'daysLate' => $loan->isActive() ? $loan->daysLate() : 0,
                    'fineAmount' => (float) $loan->fine_amount,
                    'finePaid' => (bool) $loan->fine_paid,
                    'renewalCount' => (int) $loan->renewal_count,
                    'maxRenewals' => (int) config('library.max_renewals', 1),
                    'renewalDays' => (int) config('library.renewal_days', 14),
                    'canRenew' => $eligibility['allowed'],
                    'renewBlockReason' => $eligibility['reason'],
                    'renewUrl' => route('student.library.loans.renew', $loan),
                ];
            })->all(),
            'myReservations' => $myReservations->map(function (BookReservation $reservation) use ($service) {
                $estimatedReady = $reservation->status === BookReservationStatus::PENDING->value && $reservation->book
                    ? $service->estimatedReadyAt($reservation->book)
                    : null;

                return [
                    'id' => $reservation->id,
                    'bookTitle' => $reservation->book?->title,
                    'author' => $reservation->book?->author,
                    'copyCode' => $reservation->copy?->code,
                    'status' => $reservation->status,
                    'statusLabel' => $reservation->statusLabel(),
                    'statusTone' => $reservation->statusTone(),
                    'reservedAt' => $reservation->reserved_at?->format('d M Y H:i'),
                    'pickupPlannedAt' => $reservation->pickup_planned_at?->format('d M Y'),
                    'readyAt' => $reservation->ready_at?->format('d M Y H:i'),
                    'expiresAt' => $reservation->expires_at?->format('d M Y H:i'),
                    'queuePosition' => $service->queuePosition($reservation),
                    'estimatedReadyAt' => $estimatedReady?->format('d M Y'),
                    'loanDueAt' => $reservation->loan?->due_at?->format('d M Y'),
                    'canCancel' => $reservation->isActive(),
                    'cancelUrl' => route('student.library.reservations.cancel', $reservation),
                ];
            })->all(),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category_id' => (string) ($validated['category_id'] ?? ''),
                'availability' => $validated['availability'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'categories' => BookCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (BookCategory $category) => ['id' => $category->id, 'name' => $category->name])
                ->all(),
            'options' => [
                'loanDays' => (int) config('library.loan_days', 14),
                'finePerDay' => (int) config('library.fine_per_day', 1000),
                'holdDays' => (int) config('library.reservation_hold_days', 3),
                'pickupPlanDays' => (int) config('library.pickup_plan_days', 7),
                'maxRenewals' => (int) config('library.max_renewals', 1),
                'renewalDays' => (int) config('library.renewal_days', 14),
            ],
            'urls' => [
                'index' => route('student.library.index'),
                'reserve' => route('student.library.reservations.store'),
            ],
        ]);
    }

    public function showBook(Request $request, Book $book, LibraryService $service): Response
    {
        $service->refreshOverdueLoans();
        $service->expireReservations();

        abort_unless($book->is_active, 404);

        $user = $request->user();

        $book->load('category:id,name,code');

        $copies = $book->copies()
            ->with('activeLoan:id,book_copy_id,user_id,due_at,status')
            ->orderBy('code')
            ->get();

        $myLoan = BookLoan::query()
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('copy', fn ($query) => $query->where('book_id', $book->id))
            ->with('copy:id,book_id,code')
            ->latest('borrowed_at')
            ->first();

        $myReservation = BookReservation::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->active()
            ->latest('reserved_at')
            ->first();

        $availableCount = $copies->where('status', BookCopyStatus::AVAILABLE->value)->count();
        $borrowedCount = $copies->where('status', BookCopyStatus::BORROWED->value)->count();
        $reservedCount = $copies->where('status', BookCopyStatus::RESERVED->value)->count();

        $estimatedReady = $service->estimatedReadyAt($book);
        $queueCount = $service->queueSize($book);
        $queuePosition = $myReservation ? $service->queuePosition($myReservation) : 0;
        $loanEligibility = $myLoan ? $service->renewalEligibility($myLoan) : null;

        $related = Book::query()
            ->where('book_category_id', $book->book_category_id)
            ->whereKeyNot($book->id)
            ->where('is_active', true)
            ->withCount([
                'copies as available_copies_count' => fn ($query) => $query->where('status', BookCopyStatus::AVAILABLE->value),
            ])
            ->orderBy('title')
            ->limit(4)
            ->get();

        return Inertia::render('Student/Library/Show', [
            'shell' => ShellProps::make($user, 'Perpustakaan', $book->title),
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'publisher' => $book->publisher,
                'isbn' => $book->isbn,
                'publicationYear' => $book->publication_year,
                'language' => $book->language,
                'edition' => $book->edition,
                'description' => $book->description,
                'shelfLocation' => $book->shelf_location,
                'coverUrl' => $book->cover_path ? Storage::disk('public')->url($book->cover_path) : null,
                'category' => $book->category ? [
                    'id' => $book->category->id,
                    'name' => $book->category->name,
                    'code' => $book->category->code,
                ] : null,
            ],
            'stats' => [
                'copies' => $copies->count(),
                'available' => $availableCount,
                'borrowed' => $borrowedCount,
                'reserved' => $reservedCount,
                'queue' => $queueCount,
                'estimatedReadyAt' => $estimatedReady?->format('d M Y'),
            ],
            'copies' => $copies->map(fn (BookCopy $copy) => [
                'id' => $copy->id,
                'code' => $copy->code,
                'status' => $copy->status,
                'statusLabel' => $copy->statusLabel(),
                'statusTone' => $copy->statusTone(),
                'dueAt' => $copy->activeLoan?->due_at?->format('d M Y'),
            ])->all(),
            'myLoan' => $myLoan ? [
                'id' => $myLoan->id,
                'copyCode' => $myLoan->copy?->code,
                'borrowedAt' => $myLoan->borrowed_at?->format('d M Y'),
                'dueAt' => $myLoan->due_at?->format('d M Y'),
                'statusLabel' => $myLoan->statusLabel(),
                'statusTone' => $myLoan->statusTone(),
                'renewalCount' => (int) $myLoan->renewal_count,
                'maxRenewals' => (int) config('library.max_renewals', 1),
                'canRenew' => (bool) ($loanEligibility['allowed'] ?? false),
                'renewBlockReason' => $loanEligibility['reason'] ?? null,
                'renewUrl' => route('student.library.loans.renew', $myLoan),
            ] : null,
            'myReservation' => $myReservation ? [
                'id' => $myReservation->id,
                'status' => $myReservation->status,
                'statusLabel' => $myReservation->statusLabel(),
                'statusTone' => $myReservation->statusTone(),
                'queuePosition' => $queuePosition,
                'reservedAt' => $myReservation->reserved_at?->format('d M Y H:i'),
                'pickupPlannedAt' => $myReservation->pickup_planned_at?->format('d M Y'),
                'readyAt' => $myReservation->ready_at?->format('d M Y H:i'),
                'expiresAt' => $myReservation->expires_at?->format('d M Y H:i'),
                'cancelUrl' => route('student.library.reservations.cancel', $myReservation),
            ] : null,
            'related' => $related->map(fn (Book $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'author' => $item->author,
                'coverUrl' => $item->cover_path ? Storage::disk('public')->url($item->cover_path) : null,
                'availableCount' => (int) $item->available_copies_count,
                'detailUrl' => route('student.library.books.show', $item),
            ])->all(),
            'options' => [
                'loanDays' => (int) config('library.loan_days', 14),
                'finePerDay' => (int) config('library.fine_per_day', 1000),
                'holdDays' => (int) config('library.reservation_hold_days', 3),
                'pickupPlanDays' => (int) config('library.pickup_plan_days', 7),
                'maxRenewals' => (int) config('library.max_renewals', 1),
                'renewalDays' => (int) config('library.renewal_days', 14),
            ],
            'urls' => [
                'index' => route('student.library.index'),
                'reserve' => route('student.library.reservations.store'),
            ],
        ]);
    }

    public function storeReservation(Request $request, LibraryService $service)
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'pickup_planned_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $book = Book::findOrFail($validated['book_id']);

        $pickupPlannedAt = filled($validated['pickup_planned_at'] ?? null)
            ? Carbon::parse($validated['pickup_planned_at'])
            : null;

        $service->createReservation($request->user(), $book, $validated['notes'] ?? null, $pickupPlannedAt);

        return back()->with('success', 'Reservasi berhasil diajukan. Pantau posisi antrean dan batas pengambilan di tab Reservasi Saya.');
    }

    public function renewLoan(Request $request, BookLoan $loan, LibraryService $service)
    {
        abort_unless((int) $loan->user_id === (int) $request->user()->id, 403);

        $service->renewLoan($loan, $request->user());

        return back()->with('success', 'Peminjaman berhasil diperpanjang sampai '.$loan->refresh()->due_at?->format('d M Y').'.');
    }

    public function cancelReservation(Request $request, BookReservation $reservation, LibraryService $service)
    {
        abort_unless((int) $reservation->user_id === (int) $request->user()->id, 403);

        $service->cancelReservation($reservation, $request->user(), 'Dibatalkan oleh mahasiswa.');

        return back()->with('success', 'Reservasi berhasil dibatalkan.');
    }
}
