<?php

namespace App\Http\Controllers\Admin\Library;

use App\Enums\BookCopyStatus;
use App\Enums\BookReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Library\BookCopy;
use App\Models\Library\BookReservation;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Library\LibraryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookReservationController extends Controller
{
    public function index(Request $request, LibraryService $service): Response
    {
        $service->refreshOverdueLoans();
        $service->expireReservations();

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,ready,fulfilled,cancelled,expired',
            'sort' => 'nullable|in:reserved_at,ready_at,expires_at,status,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = BookReservation::query()->with([
            'book:id,title,author',
            'user:id,first_name,last_name,username',
            'copy:id,code',
        ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->whereHas('user', fn ($user) => $user
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%"))
                    ->orWhereHas('book', fn ($book) => $book->where('title', 'like', "%{$q}%"))
                    ->orWhereHas('copy', fn ($copy) => $copy->where('code', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'reserved_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $reservations = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Library/Reservations/Index', [
            'shell' => ShellProps::make($request->user(), 'Perpustakaan', 'Reservasi'),
            'can' => [
                'update' => ActivePermission::check('book-reservation.update'),
                'delete' => ActivePermission::check('book-reservation.delete'),
            ],
            'stats' => [
                'pending' => BookReservation::where('status', BookReservationStatus::PENDING->value)->count(),
                'ready' => BookReservation::where('status', BookReservationStatus::READY->value)->count(),
                'fulfilledThisMonth' => BookReservation::where('status', BookReservationStatus::FULFILLED->value)
                    ->whereBetween('fulfilled_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'expired' => BookReservation::whereIn('status', [
                    BookReservationStatus::EXPIRED->value,
                    BookReservationStatus::CANCELLED->value,
                ])->count(),
            ],
            'data' => [
                'rows' => collect($reservations->items())->values()->map(fn (BookReservation $reservation, int $i) => [
                    'id' => $reservation->id,
                    'no' => ($reservations->firstItem() ?? 0) + $i,
                    'bookTitle' => $reservation->book?->title,
                    'author' => $reservation->book?->author,
                    'member' => trim(($reservation->user?->first_name ?? '').' '.($reservation->user?->last_name ?? '')),
                    'username' => $reservation->user?->username,
                    'status' => $reservation->status,
                    'statusLabel' => $reservation->statusLabel(),
                    'statusTone' => $reservation->statusTone(),
                    'reservedAt' => $reservation->reserved_at?->format('d M Y H:i'),
                    'readyAt' => $reservation->ready_at?->format('d M Y H:i'),
                    'expiresAt' => $reservation->expires_at?->format('d M Y H:i'),
                    'copyCode' => $reservation->copy?->code,
                    'notes' => $reservation->notes,
                    'canReady' => $reservation->status === BookReservationStatus::PENDING->value,
                    'canFulfill' => $reservation->status === BookReservationStatus::READY->value,
                    'canCancel' => $reservation->isActive(),
                    'canDelete' => ! $reservation->isActive(),
                    'availableCopiesUrl' => route('admin.library.reservations.available-copies', $reservation),
                    'readyUrl' => route('admin.library.reservations.ready', $reservation),
                    'fulfillUrl' => route('admin.library.reservations.fulfill', $reservation),
                    'cancelUrl' => route('admin.library.reservations.cancel', $reservation),
                    'deleteUrl' => route('admin.library.reservations.destroy', $reservation),
                ])->all(),
                'currentPage' => $reservations->currentPage(),
                'lastPage' => $reservations->lastPage(),
                'perPage' => $reservations->perPage(),
                'total' => $reservations->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'statuses' => BookReservationStatus::options(),
            ],
            'urls' => [
                'index' => route('admin.library.reservations.index'),
            ],
        ]);
    }

    public function ready(Request $request, BookReservation $reservation, LibraryService $service)
    {
        $validated = $request->validate([
            'book_copy_id' => ['required', 'integer', 'exists:book_copies,id'],
        ]);

        $copy = BookCopy::findOrFail($validated['book_copy_id']);

        $service->markReservationReady($reservation, $copy, $request->user());

        return back()->with('success', 'Reservasi disiapkan. Eksemplar ditahan untuk anggota.');
    }

    public function fulfill(Request $request, BookReservation $reservation, LibraryService $service)
    {
        $loan = $service->fulfillReservation($reservation, $request->user());

        return back()->with('success', 'Reservasi diserahkan dan peminjaman '.$loan->copy?->code.' berhasil dicatat.');
    }

    public function cancel(Request $request, BookReservation $reservation, LibraryService $service)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->cancelReservation($reservation, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Reservasi berhasil dibatalkan.');
    }

    public function availableCopies(BookReservation $reservation)
    {
        $copies = BookCopy::query()
            ->where('book_id', $reservation->book_id)
            ->where('status', BookCopyStatus::AVAILABLE->value)
            ->orderBy('code')
            ->get(['id', 'code']);

        return response()->json([
            'options' => $copies->map(fn (BookCopy $copy) => [
                'id' => $copy->id,
                'label' => $copy->code,
            ])->values()->all(),
        ]);
    }

    public function destroy(Request $request, BookReservation $reservation)
    {
        if ($reservation->isActive()) {
            return back()->with('error', 'Reservasi aktif tidak dapat dihapus. Batalkan terlebih dahulu.');
        }

        $reservation->delete();

        return back()->with('success', 'Riwayat reservasi berhasil dihapus.');
    }
}
