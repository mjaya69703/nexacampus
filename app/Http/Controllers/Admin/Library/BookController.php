<?php

namespace App\Http\Controllers\Admin\Library;

use App\Enums\BookCopyStatus;
use App\Enums\BookReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Library\Book;
use App\Models\Library\BookCategory;
use App\Models\Library\BookCopy;
use App\Models\Library\BookLoan;
use App\Models\Library\BookReservation;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:book_categories,id',
            'is_active' => 'nullable|in:0,1',
            'availability' => 'nullable|in:available,unavailable',
            'sort' => 'nullable|in:title,author,publication_year,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = Book::query()
            ->with('category:id,name')
            ->withCount([
                'copies',
                'copies as available_copies_count' => fn ($sub) => $sub->where('status', BookCopyStatus::AVAILABLE->value),
                'loans as active_loans_count' => fn ($sub) => $sub->active(),
            ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")
                ->orWhere('author', 'like', "%{$q}%")
                ->orWhere('isbn', 'like', "%{$q}%"));
        }

        if (filled($validated['category_id'] ?? null)) {
            $query->where('book_category_id', $validated['category_id']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (($validated['availability'] ?? '') === 'available') {
            $query->whereHas('copies', fn ($sub) => $sub->where('status', BookCopyStatus::AVAILABLE->value));
        } elseif (($validated['availability'] ?? '') === 'unavailable') {
            $query->whereDoesntHave('copies', fn ($sub) => $sub->where('status', BookCopyStatus::AVAILABLE->value));
        }

        $sort = $validated['sort'] ?? 'title';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $books = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Library/Books/Index', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Buku'),
            'can' => [
                'create' => ActivePermission::check('book.create'),
                'update' => ActivePermission::check('book.update'),
                'delete' => ActivePermission::check('book.delete'),
            ],
            'stats' => [
                'total' => Book::count(),
                'active' => Book::where('is_active', true)->count(),
                'copies' => BookCopy::count(),
                'available' => BookCopy::where('status', BookCopyStatus::AVAILABLE->value)->count(),
                'borrowed' => BookCopy::where('status', BookCopyStatus::BORROWED->value)->count(),
            ],
            'data' => [
                'rows' => collect($books->items())->values()->map(fn (Book $book, int $i) => [
                    'id' => $book->id,
                    'no' => ($books->firstItem() ?? 0) + $i,
                    'title' => $book->title,
                    'author' => $book->author,
                    'category' => $book->category?->name,
                    'isbn' => $book->isbn,
                    'year' => $book->publication_year,
                    'shelfLocation' => $book->shelf_location,
                    'copiesCount' => (int) $book->copies_count,
                    'availableCount' => (int) $book->available_copies_count,
                    'activeLoansCount' => (int) $book->active_loans_count,
                    'isActive' => (bool) $book->is_active,
                    'coverUrl' => $this->coverUrl($book),
                    'showUrl' => route('admin.library.books.show', $book),
                    'editUrl' => route('admin.library.books.edit', $book),
                    'deleteUrl' => route('admin.library.books.destroy', $book),
                ])->all(),
                'currentPage' => $books->currentPage(),
                'lastPage' => $books->lastPage(),
                'perPage' => $books->perPage(),
                'total' => $books->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category_id' => (string) ($validated['category_id'] ?? ''),
                'is_active' => $validated['is_active'] ?? '',
                'availability' => $validated['availability'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'categories' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.library.books.index'),
                'create' => route('admin.library.books.create'),
                'bulkDestroy' => route('admin.library.books.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Library/Books/Form', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Tambah Buku'),
            'mode' => 'create',
            'book' => [
                'book_category_id' => '',
                'title' => '',
                'author' => '',
                'publisher' => '',
                'isbn' => '',
                'publication_year' => '',
                'language' => 'Indonesia',
                'edition' => '',
                'description' => '',
                'shelf_location' => '',
                'is_active' => true,
                'initial_copies' => 1,
                'coverUrl' => null,
            ],
            'categories' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.library.books.index'),
                'submit' => route('admin.library.books.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $coverPath = $request->hasFile('cover')
            ? $request->file('cover')->store('library/covers', 'public')
            : null;

        $book = Book::create([
            ...$this->bookPayload($validated),
            'cover_path' => $coverPath,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $initialCopies = (int) ($validated['initial_copies'] ?? 0);

        for ($i = 1; $i <= $initialCopies; $i++) {
            BookCopy::create([
                'book_id' => $book->id,
                'code' => $this->nextCopyCode($book->id, $i),
                'status' => BookCopyStatus::AVAILABLE->value,
                'acquired_at' => now()->toDateString(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('admin.library.books.show', $book)
            ->with('success', 'Buku berhasil ditambahkan'.($initialCopies > 0 ? " beserta {$initialCopies} eksemplar." : '.'));
    }

    public function edit(Request $request, Book $book): Response
    {
        return Inertia::render('Admin/Library/Books/Form', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Edit Buku'),
            'mode' => 'edit',
            'book' => [
                'id' => $book->id,
                'book_category_id' => $book->book_category_id,
                'title' => $book->title,
                'author' => $book->author ?? '',
                'publisher' => $book->publisher ?? '',
                'isbn' => $book->isbn ?? '',
                'publication_year' => $book->publication_year ?? '',
                'language' => $book->language ?? 'Indonesia',
                'edition' => $book->edition ?? '',
                'description' => $book->description ?? '',
                'shelf_location' => $book->shelf_location ?? '',
                'is_active' => (bool) $book->is_active,
                'initial_copies' => 0,
                'coverUrl' => $this->coverUrl($book),
            ],
            'categories' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.library.books.index'),
                'submit' => route('admin.library.books.update', $book),
            ],
        ]);
    }

    public function update(Request $request, Book $book)
    {
        $validated = $this->validatePayload($request, $book);
        $coverPath = $book->cover_path;

        if ($request->boolean('remove_cover') && $coverPath) {
            Storage::disk('public')->delete($coverPath);
            $coverPath = null;
        }

        if ($request->hasFile('cover')) {
            if ($coverPath) {
                Storage::disk('public')->delete($coverPath);
            }

            $coverPath = $request->file('cover')->store('library/covers', 'public');
        }

        $book->update([
            ...$this->bookPayload($validated),
            'cover_path' => $coverPath,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.library.books.show', $book)
            ->with('success', 'Data buku berhasil diperbarui.');
    }

    public function show(Request $request, Book $book): Response
    {
        $book->load([
            'category:id,name,code',
            'copies' => fn ($query) => $query->orderBy('code'),
            'copies.activeLoan.user:id,first_name,last_name,username',
        ]);

        $loans = BookLoan::query()
            ->whereHas('copy', fn ($query) => $query->where('book_id', $book->id))
            ->with([
                'user:id,first_name,last_name,username',
                'copy:id,book_id,code',
                'returnedBy:id,first_name,last_name',
            ])
            ->latest('borrowed_at')
            ->limit(20)
            ->get();

        $reservations = BookReservation::query()
            ->where('book_id', $book->id)
            ->with([
                'user:id,first_name,last_name,username',
                'copy:id,code',
            ])
            ->latest('reserved_at')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Library/Books/Show', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', $book->title),
            'can' => [
                'update' => ActivePermission::check('book.update'),
                'delete' => ActivePermission::check('book.delete'),
                'createLoan' => ActivePermission::check('book-loan.create'),
            ],
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
                'isActive' => (bool) $book->is_active,
                'coverUrl' => $this->coverUrl($book),
                'category' => $book->category ? [
                    'id' => $book->category->id,
                    'name' => $book->category->name,
                    'code' => $book->category->code,
                ] : null,
                'createdAt' => $book->created_at?->format('d M Y'),
                'updatedAt' => $book->updated_at?->format('d M Y'),
            ],
            'stats' => [
                'copies' => $book->copies->count(),
                'available' => $book->copies->where('status', BookCopyStatus::AVAILABLE->value)->count(),
                'borrowed' => $book->copies->where('status', BookCopyStatus::BORROWED->value)->count(),
                'reserved' => $book->copies->where('status', BookCopyStatus::RESERVED->value)->count(),
                'activeReservations' => $reservations->whereIn('status', BookReservationStatus::activeValues())->count(),
            ],
            'copies' => $book->copies->map(fn (BookCopy $copy) => [
                'id' => $copy->id,
                'code' => $copy->code,
                'status' => $copy->status,
                'statusLabel' => $copy->statusLabel(),
                'statusTone' => $copy->statusTone(),
                'conditionNotes' => $copy->condition_notes,
                'acquiredAt' => $copy->acquired_at?->format('d M Y'),
                'acquiredAtIso' => $copy->acquired_at?->format('Y-m-d'),
                'activeLoan' => $copy->activeLoan ? [
                    'id' => $copy->activeLoan->id,
                    'borrower' => trim(($copy->activeLoan->user?->first_name ?? '').' '.($copy->activeLoan->user?->last_name ?? '')),
                    'dueAt' => $copy->activeLoan->due_at?->format('d M Y H:i'),
                ] : null,
                'updateUrl' => route('admin.library.books.copies.update', $copy),
                'statusUrl' => route('admin.library.books.copies.status', $copy),
                'deleteUrl' => route('admin.library.books.copies.destroy', $copy),
                'createLoanUrl' => route('admin.library.loans.create', ['copy' => $copy->id]),
            ])->values()->all(),
            'loans' => $loans->map(fn (BookLoan $loan) => [
                'id' => $loan->id,
                'borrower' => trim(($loan->user?->first_name ?? '').' '.($loan->user?->last_name ?? '')),
                'username' => $loan->user?->username,
                'copyCode' => $loan->copy?->code,
                'borrowedAt' => $loan->borrowed_at?->format('d M Y'),
                'dueAt' => $loan->due_at?->format('d M Y'),
                'returnedAt' => $loan->returned_at?->format('d M Y'),
                'status' => $loan->status,
                'statusLabel' => $loan->statusLabel(),
                'statusTone' => $loan->statusTone(),
                'fineAmount' => (float) $loan->fine_amount,
                'finePaid' => (bool) $loan->fine_paid,
            ])->all(),
            'reservations' => $reservations->map(fn (BookReservation $reservation) => [
                'id' => $reservation->id,
                'member' => trim(($reservation->user?->first_name ?? '').' '.($reservation->user?->last_name ?? '')),
                'username' => $reservation->user?->username,
                'status' => $reservation->status,
                'statusLabel' => $reservation->statusLabel(),
                'statusTone' => $reservation->statusTone(),
                'reservedAt' => $reservation->reserved_at?->format('d M Y H:i'),
                'readyAt' => $reservation->ready_at?->format('d M Y H:i'),
                'expiresAt' => $reservation->expires_at?->format('d M Y H:i'),
                'copyCode' => $reservation->copy?->code,
            ])->all(),
            'options' => [
                'copyStatuses' => collect(BookCopyStatus::manualValues())
                    ->mapWithKeys(fn (string $status) => [$status => BookCopyStatus::from($status)->label()])
                    ->all(),
            ],
            'urls' => [
                'index' => route('admin.library.books.index'),
                'edit' => route('admin.library.books.edit', $book),
                'delete' => route('admin.library.books.destroy', $book),
                'copyStore' => route('admin.library.books.copies.store', $book),
                'loanCreate' => route('admin.library.loans.create'),
            ],
        ]);
    }

    public function destroy(Request $request, Book $book)
    {
        if ($this->hasActiveTransactions($book)) {
            return back()->with('error', 'Buku masih memiliki peminjaman atau reservasi aktif dan tidak dapat dihapus.');
        }

        $book->copies()->get()->each(function (BookCopy $copy) use ($request) {
            $copy->update(['deleted_by' => $request->user()->id]);
            $copy->delete();
        });

        $book->update(['deleted_by' => $request->user()->id]);
        $book->delete();

        return redirect()->route('admin.library.books.index')
            ->with('success', 'Buku berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:books,id',
        ]);

        $books = Book::whereIn('id', $validated['ids'])->get();
        $deleted = 0;
        $skipped = 0;

        foreach ($books as $book) {
            if ($this->hasActiveTransactions($book)) {
                $skipped++;

                continue;
            }

            $book->copies()->get()->each(function (BookCopy $copy) use ($request) {
                $copy->update(['deleted_by' => $request->user()->id]);
                $copy->delete();
            });

            $book->update(['deleted_by' => $request->user()->id]);
            $book->delete();
            $deleted++;
        }

        $message = $deleted.' buku berhasil dihapus.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' buku dilewati karena masih memiliki transaksi aktif.';
        }

        return back()->with($deleted > 0 ? 'success' : 'error', $message);
    }

    public function storeCopy(Request $request, Book $book)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('book_copies', 'code')],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
            'acquired_at' => ['nullable', 'date'],
        ]);

        BookCopy::create([
            'book_id' => $book->id,
            'code' => strtoupper(trim($validated['code'])),
            'status' => BookCopyStatus::AVAILABLE->value,
            'condition_notes' => $validated['condition_notes'] ?? null,
            'acquired_at' => $validated['acquired_at'] ?? now()->toDateString(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Eksemplar berhasil ditambahkan.');
    }

    public function updateCopy(Request $request, BookCopy $copy)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('book_copies', 'code')->ignore($copy->id)],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
            'acquired_at' => ['nullable', 'date'],
        ]);

        $copy->update([
            'code' => strtoupper(trim($validated['code'])),
            'condition_notes' => $validated['condition_notes'] ?? null,
            'acquired_at' => $validated['acquired_at'] ?? $copy->acquired_at?->format('Y-m-d'),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Data eksemplar berhasil diperbarui.');
    }

    public function updateCopyStatus(Request $request, BookCopy $copy)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(BookCopyStatus::manualValues())],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($copy->status, [BookCopyStatus::BORROWED->value, BookCopyStatus::RESERVED->value], true)) {
            return back()->with('error', 'Eksemplar sedang dipinjam atau direservasi. Selesaikan transaksi terlebih dahulu.');
        }

        $copy->update([
            'status' => $validated['status'],
            'condition_notes' => $validated['condition_notes'] ?? $copy->condition_notes,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status eksemplar berhasil diperbarui.');
    }

    public function destroyCopy(Request $request, BookCopy $copy)
    {
        if ($copy->loans()->active()->exists()) {
            return back()->with('error', 'Eksemplar masih memiliki peminjaman aktif.');
        }

        if ($copy->reservations()->active()->exists()) {
            return back()->with('error', 'Eksemplar masih terkait reservasi aktif.');
        }

        $copy->update(['deleted_by' => $request->user()->id]);
        $copy->delete();

        return back()->with('success', 'Eksemplar berhasil dihapus.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return BookCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BookCategory $category) => ['id' => $category->id, 'name' => $category->name])
            ->all();
    }

    private function coverUrl(Book $book): ?string
    {
        return $book->cover_path ? Storage::disk('public')->url($book->cover_path) : null;
    }

    private function hasActiveTransactions(Book $book): bool
    {
        return $book->loans()->active()->exists()
            || $book->reservations()->active()->exists();
    }

    private function nextCopyCode(int $bookId, int $sequence): string
    {
        $base = 'BK-'.str_pad((string) $bookId, 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        $code = $base;
        $counter = $sequence;

        while (BookCopy::withTrashed()->where('code', $code)->exists()) {
            $counter++;
            $code = 'BK-'.str_pad((string) $bookId, 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?Book $book = null): array
    {
        $request->merge([
            'isbn' => filled($request->input('isbn')) ? trim((string) $request->input('isbn')) : null,
            'language' => filled($request->input('language')) ? trim((string) $request->input('language')) : 'Indonesia',
        ]);

        $validated = $request->validate([
            'book_category_id' => ['required', 'integer', 'exists:book_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:32', Rule::unique('books', 'isbn')->ignore($book?->id)],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:'.(now()->year + 1)],
            'language' => ['required', 'string', 'max:40'],
            'edition' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:5000'],
            'shelf_location' => ['nullable', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
            'initial_copies' => [$book ? 'nullable' : 'nullable', 'integer', 'min:0', 'max:20'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = (bool) $validated['is_active'];
        $validated['publication_year'] = $validated['publication_year'] ?? null;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function bookPayload(array $validated): array
    {
        return [
            'book_category_id' => $validated['book_category_id'],
            'title' => $validated['title'],
            'author' => $validated['author'] ?? null,
            'publisher' => $validated['publisher'] ?? null,
            'isbn' => $validated['isbn'] ?? null,
            'publication_year' => $validated['publication_year'] ?? null,
            'language' => $validated['language'] ?? 'Indonesia',
            'edition' => $validated['edition'] ?? null,
            'description' => $validated['description'] ?? null,
            'shelf_location' => $validated['shelf_location'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
