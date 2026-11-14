<?php

namespace App\Http\Controllers\Admin\Library;

use App\Enums\BookCopyStatus;
use App\Enums\BookLoanStatus;
use App\Http\Controllers\Controller;
use App\Models\Library\BookCopy;
use App\Models\Library\BookLoan;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Library\LibraryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookLoanController extends Controller
{
    public function index(Request $request, LibraryService $service): Response
    {
        $service->refreshOverdueLoans();
        $service->expireReservations();

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:borrowed,returned,overdue,lost',
            'overdue' => 'nullable|in:0,1',
            'sort' => 'nullable|in:borrowed_at,due_at,status,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = BookLoan::query()->with([
            'user:id,first_name,last_name,username',
            'copy:id,book_id,code',
            'copy.book:id,title',
            'returnedBy:id,first_name,last_name',
        ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->whereHas('user', fn ($user) => $user
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%"))
                    ->orWhereHas('copy', fn ($copy) => $copy
                        ->where('code', 'like', "%{$q}%")
                        ->orWhereHas('book', fn ($book) => $book->where('title', 'like', "%{$q}%")));
            });
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (($validated['overdue'] ?? '') === '1') {
            $query->whereIn('status', BookLoanStatus::activeValues())->where('due_at', '<', now());
        }

        $sort = $validated['sort'] ?? 'borrowed_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $loans = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Library/Loans/Index', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Peminjaman'),
            'can' => [
                'create' => ActivePermission::check('book-loan.create'),
                'update' => ActivePermission::check('book-loan.update'),
                'delete' => ActivePermission::check('book-loan.delete'),
            ],
            'stats' => [
                'active' => BookLoan::active()->count(),
                'overdue' => BookLoan::active()->where('due_at', '<', now())->count(),
                'returnedThisMonth' => BookLoan::where('status', BookLoanStatus::RETURNED->value)
                    ->whereBetween('returned_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'outstandingFines' => (float) BookLoan::where('fine_paid', false)->sum('fine_amount'),
            ],
            'data' => [
                'rows' => collect($loans->items())->values()->map(function (BookLoan $loan, int $i) use ($service, $loans) {
                    $eligibility = $service->renewalEligibility($loan);

                    return [
                        'id' => $loan->id,
                        'no' => ($loans->firstItem() ?? 0) + $i,
                        'bookTitle' => $loan->copy?->book?->title,
                        'copyCode' => $loan->copy?->code,
                        'member' => trim(($loan->user?->first_name ?? '').' '.($loan->user?->last_name ?? '')),
                        'username' => $loan->user?->username,
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
                        'canRenew' => $eligibility['allowed'],
                        'renewBlockReason' => $eligibility['reason'],
                        'canReturn' => $loan->isActive(),
                        'canPayFine' => ! $loan->fine_paid && (float) $loan->fine_amount > 0,
                        'canDelete' => ! $loan->isActive(),
                        'returnUrl' => route('admin.library.loans.return', $loan),
                        'renewUrl' => route('admin.library.loans.renew', $loan),
                        'payFineUrl' => route('admin.library.loans.pay-fine', $loan),
                        'deleteUrl' => route('admin.library.loans.destroy', $loan),
                    ];
                })->all(),
                'currentPage' => $loans->currentPage(),
                'lastPage' => $loans->lastPage(),
                'perPage' => $loans->perPage(),
                'total' => $loans->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'overdue' => $validated['overdue'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'statuses' => BookLoanStatus::options(),
                'copyStatuses' => collect(BookCopyStatus::manualValues())
                    ->mapWithKeys(fn (string $status) => [$status => BookCopyStatus::from($status)->label()])
                    ->all(),
                'finePerDay' => (int) config('library.fine_per_day', 1000),
                'maxRenewals' => (int) config('library.max_renewals', 1),
                'renewalDays' => (int) config('library.renewal_days', 14),
            ],
            'urls' => [
                'index' => route('admin.library.loans.index'),
                'create' => route('admin.library.loans.create'),
                'bulkDestroy' => route('admin.library.loans.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $preselectedCopy = null;
        $copyId = $request->integer('copy');

        if ($copyId > 0) {
            $copy = BookCopy::query()
                ->with('book:id,title,author')
                ->whereIn('status', [BookCopyStatus::AVAILABLE->value, BookCopyStatus::RESERVED->value])
                ->find($copyId);

            if ($copy) {
                $preselectedCopy = [
                    'id' => $copy->id,
                    'label' => ($copy->book?->title ?? 'Buku').' — '.$copy->code,
                    'code' => $copy->code,
                    'bookTitle' => $copy->book?->title,
                    'author' => $copy->book?->author,
                ];
            }
        }

        return Inertia::render('Admin/Library/Loans/Create', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Catat Peminjaman'),
            'preselectedCopy' => $preselectedCopy,
            'defaults' => [
                'borrowed_at' => now()->format('Y-m-d'),
                'due_at' => now()->addDays((int) config('library.loan_days', 14))->format('Y-m-d'),
                'notes' => '',
            ],
            'options' => [
                'loanDays' => (int) config('library.loan_days', 14),
                'finePerDay' => (int) config('library.fine_per_day', 1000),
            ],
            'urls' => [
                'index' => route('admin.library.loans.index'),
                'submit' => route('admin.library.loans.store'),
                'searchMembers' => route('admin.library.loans.search-members'),
                'searchCopies' => route('admin.library.loans.search-copies'),
            ],
        ]);
    }

    public function store(Request $request, LibraryService $service)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'book_copy_id' => ['required', 'integer', 'exists:book_copies,id'],
            'borrowed_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:borrowed_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $borrower = User::findOrFail($validated['user_id']);
        $copy = BookCopy::findOrFail($validated['book_copy_id']);

        $service->borrow($borrower, $copy, $request->user(), [
            'borrowed_at' => $validated['borrowed_at'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.library.loans.index')
            ->with('success', 'Peminjaman berhasil dicatat.');
    }

    public function returnBook(Request $request, BookLoan $loan, LibraryService $service)
    {
        $validated = $request->validate([
            'copy_status' => ['required', Rule::in(BookCopyStatus::manualValues())],
            'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fine_paid' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $service->returnBook($loan, $request->user(), [
            'copy_status' => $validated['copy_status'],
            'fine_amount' => $validated['fine_amount'] ?? null,
            'fine_paid' => (bool) ($validated['fine_paid'] ?? false),
            'notes' => $validated['notes'] ?? null,
        ]);

        $message = 'Pengembalian berhasil dicatat.';

        if ((float) $result->fine_amount > 0) {
            $message .= ' Denda: Rp '.number_format((float) $result->fine_amount, 0, ',', '.').'.';
        }

        return back()->with('success', $message);
    }

    public function renew(Request $request, BookLoan $loan, LibraryService $service)
    {
        $service->renewLoan($loan, $request->user());

        return back()->with('success', 'Peminjaman diperpanjang sampai '.$loan->refresh()->due_at?->format('d M Y').'.');
    }

    public function payFine(Request $request, BookLoan $loan)
    {
        if ((float) $loan->fine_amount <= 0) {
            return back()->with('error', 'Peminjaman ini tidak memiliki denda.');
        }

        if ($loan->fine_paid) {
            return back()->with('error', 'Denda sudah ditandai lunas.');
        }

        $loan->update([
            'fine_paid' => true,
            'fine_paid_at' => now(),
        ]);

        return back()->with('success', 'Denda berhasil ditandai lunas.');
    }

    public function destroy(Request $request, BookLoan $loan)
    {
        if ($loan->isActive()) {
            return back()->with('error', 'Peminjaman aktif tidak dapat dihapus. Catat pengembalian terlebih dahulu.');
        }

        $loan->delete();

        return back()->with('success', 'Riwayat peminjaman berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:book_loans,id',
        ]);

        $loans = BookLoan::whereIn('id', $validated['ids'])->get();
        $deleted = 0;
        $skipped = 0;

        foreach ($loans as $loan) {
            if ($loan->isActive()) {
                $skipped++;

                continue;
            }

            $loan->delete();
            $deleted++;
        }

        $message = $deleted.' riwayat peminjaman berhasil dihapus.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' peminjaman aktif dilewati.';
        }

        return back()->with($deleted > 0 ? 'success' : 'error', $message);
    }

    public function searchMembers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with([
                'studentProfile:id,user_id,nim',
                'lecturerProfile:id,user_id,nidn',
                'employeeProfile:id,user_id,employee_number',
            ])
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhereHas('studentProfile', fn ($profile) => $profile->where('nim', 'like', "%{$q}%"))
                        ->orWhereHas('lecturerProfile', fn ($profile) => $profile->where('nidn', 'like', "%{$q}%")->orWhere('nip', 'like', "%{$q}%"))
                        ->orWhereHas('employeeProfile', fn ($profile) => $profile->where('employee_number', 'like', "%{$q}%"));
                });
            })
            ->orderBy('first_name')
            ->limit(15)
            ->get();

        return response()->json([
            'options' => $users->map(function (User $user) {
                $identity = $user->studentProfile?->nim
                    ?? $user->lecturerProfile?->nidn
                    ?? $user->employeeProfile?->employee_number;

                $role = match (true) {
                    (bool) $user->studentProfile => 'Mahasiswa',
                    (bool) $user->lecturerProfile => 'Dosen',
                    (bool) $user->employeeProfile => 'Pegawai',
                    default => 'Pengguna',
                };

                return [
                    'id' => $user->id,
                    'label' => trim($user->first_name.' '.$user->last_name).($identity ? " ({$identity})" : ''),
                    'meta' => [
                        'role' => $role,
                        'username' => $user->username,
                    ],
                ];
            })->values()->all(),
        ]);
    }

    public function searchCopies(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $copies = BookCopy::query()
            ->with('book:id,title,author,shelf_location')
            ->where('status', BookCopyStatus::AVAILABLE->value)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', "%{$q}%")
                        ->orWhereHas('book', fn ($book) => $book
                            ->where('title', 'like', "%{$q}%")
                            ->orWhere('author', 'like', "%{$q}%")
                            ->orWhere('isbn', 'like', "%{$q}%"));
                });
            })
            ->orderBy('code')
            ->limit(15)
            ->get();

        return response()->json([
            'options' => $copies->map(fn (BookCopy $copy) => [
                'id' => $copy->id,
                'label' => ($copy->book?->title ?? 'Buku').' — '.$copy->code,
                'meta' => [
                    'author' => $copy->book?->author,
                    'shelf' => $copy->book?->shelf_location,
                ],
            ])->values()->all(),
        ]);
    }
}
