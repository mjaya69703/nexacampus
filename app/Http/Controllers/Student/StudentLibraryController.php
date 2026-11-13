<?php

namespace App\Http\Controllers\Student;

use App\Enums\BookCopyStatus;
use App\Enums\BookLoanStatus;
use App\Enums\BookReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Library\Book;
use App\Models\Library\BookCategory;
use App\Models\Library\BookLoan;
use App\Models\Library\BookReservation;
use App\Support\Inertia\ShellProps;
use App\Support\Library\LibraryService;
use Illuminate\Http\Request;
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

        $myLoans = BookLoan::query()
            ->where('user_id', $user->id)
            ->with(['copy:id,book_id,code', 'copy.book:id,title,author'])
            ->latest('borrowed_at')
            ->limit(20)
            ->get();

        $myReservations = BookReservation::query()
            ->where('user_id', $user->id)
            ->with(['book:id,title,author', 'copy:id,code'])
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
                'rows' => collect($books->items())->values()->map(fn (Book $book) => [
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
                    'availableCount' => (int) $book->available_copies_count,
                    'hasActiveLoan' => in_array($book->id, $activeLoanIds, true),
                    'hasActiveReservation' => in_array($book->id, $activeReservationBookIds, true),
                    'canReserve' => ! in_array($book->id, $activeLoanIds, true)
                        && ! in_array($book->id, $activeReservationBookIds, true),
                ])->all(),
                'currentPage' => $books->currentPage(),
                'lastPage' => $books->lastPage(),
                'perPage' => $books->perPage(),
                'total' => $books->total(),
            ],
            'myLoans' => $myLoans->map(fn (BookLoan $loan) => [
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
            ])->all(),
            'myReservations' => $myReservations->map(fn (BookReservation $reservation) => [
                'id' => $reservation->id,
                'bookTitle' => $reservation->book?->title,
                'author' => $reservation->book?->author,
                'copyCode' => $reservation->copy?->code,
                'status' => $reservation->status,
                'statusLabel' => $reservation->statusLabel(),
                'statusTone' => $reservation->statusTone(),
                'reservedAt' => $reservation->reserved_at?->format('d M Y H:i'),
                'readyAt' => $reservation->ready_at?->format('d M Y H:i'),
                'expiresAt' => $reservation->expires_at?->format('d M Y H:i'),
                'canCancel' => $reservation->isActive(),
                'cancelUrl' => route('student.library.reservations.cancel', $reservation),
            ])->all(),
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
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $book = Book::findOrFail($validated['book_id']);

        $service->createReservation($request->user(), $book, $validated['notes'] ?? null);

        return back()->with('success', 'Reservasi berhasil diajukan. Tunggu petugas menyiapkan eksemplar.');
    }

    public function cancelReservation(Request $request, BookReservation $reservation, LibraryService $service)
    {
        abort_unless((int) $reservation->user_id === (int) $request->user()->id, 403);

        $service->cancelReservation($reservation, $request->user(), 'Dibatalkan oleh mahasiswa.');

        return back()->with('success', 'Reservasi berhasil dibatalkan.');
    }
}
