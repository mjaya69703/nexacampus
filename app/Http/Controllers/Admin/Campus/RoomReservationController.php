<?php

namespace App\Http\Controllers\Admin\Campus;

use App\Http\Controllers\Controller;
use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Peminjaman Ruangan — memakai kit CRUD shared (pola menu academic).
 *
 * State machine: create selalu pending; approved/rejected hanya lewat
 * endpoint approve/reject yang dikawal permission room-reservation.approve
 * (Blade lama tanpa gate server); rejected wajib alasan; cancel
 * membersihkan approved_by/at. Konflik dicek saat approve maupun saat
 * jadwal approved diubah. Tanpa import (data transaksional konflik).
 */
class RoomReservationController extends Controller
{
    public const STATUSES = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
            'room' => 'nullable|integer|exists:rooms,id',
            'date' => 'nullable|date',
            'sort' => 'nullable|in:id,reservation_date,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = RoomReservation::query()
            ->with(['room:id,name', 'user:id,first_name,last_name']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('reservation_number', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%"));
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['room'] ?? null)) {
            $query->where('room_id', $validated['room']);
        }

        if (filled($validated['date'] ?? null)) {
            $query->whereDate('reservation_date', $validated['date']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';

        if ($sort === 'reservation_date') {
            $query->orderBy('reservation_date', $direction)->orderBy('start_time', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $reservations = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Campus/Reservation/Index', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Daftar Peminjaman Ruangan'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('room-reservation.create'),
                'update' => ActivePermission::check('room-reservation.update'),
                'delete' => ActivePermission::check('room-reservation.delete'),
                'restore' => ActivePermission::any(['room-reservation.update', 'room-reservation.delete']),
                'approve' => ActivePermission::check('room-reservation.approve'),
                'view' => ActivePermission::check('room-reservation.view'),
            ],
            'stats' => [
                'total' => RoomReservation::count(),
                'pending' => RoomReservation::where('status', 'pending')->count(),
                'approved' => RoomReservation::where('status', 'approved')->count(),
                'rejected' => RoomReservation::where('status', 'rejected')->count(),
                'trashed' => RoomReservation::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($reservations->items())->values()->map(fn ($r, $i) => [
                    'id' => $r->id,
                    'no' => ($reservations->firstItem() ?? 0) + $i,
                    'reservationNumber' => $r->reservation_number,
                    'title' => $r->title,
                    'room' => $r->room?->name,
                    'requester' => $r->user?->name ?? '-',
                    'date' => $r->reservation_date?->format('d M Y'),
                    'time' => substr((string) $r->start_time, 0, 5).' - '.substr((string) $r->end_time, 0, 5),
                    'status' => $r->status,
                    'statusLabel' => self::STATUSES[$r->status] ?? $r->status,
                    'createdAt' => $r->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.campus.reservations.edit', $r->id),
                    'showUrl' => route('admin.campus.reservations.show', $r->id),
                    'deleteUrl' => route('admin.campus.reservations.destroy', $r->id),
                    'restoreUrl' => route('admin.campus.reservations.restore', $r->id),
                    'forceUrl' => route('admin.campus.reservations.force-destroy', $r->id),
                    'approveUrl' => route('admin.campus.reservations.approve', $r->id),
                    'rejectUrl' => route('admin.campus.reservations.reject', $r->id),
                ])->all(),
                'currentPage' => $reservations->currentPage(),
                'lastPage' => $reservations->lastPage(),
                'perPage' => $reservations->perPage(),
                'total' => $reservations->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'room' => $validated['room'] ?? '',
                'date' => $validated['date'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'statusOptions' => self::STATUSES,
            'roomOptions' => Room::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all(),
            'urls' => [
                'index' => route('admin.campus.reservations.index'),
                'create' => route('admin.campus.reservations.create'),
                'export' => route('admin.campus.reservations.export'),
                'exportPdf' => route('admin.campus.exports.pdf', ['resource' => 'room-reservations']),
                'bulkDestroy' => route('admin.campus.reservations.bulk-destroy'),
                'bulkRestore' => route('admin.campus.reservations.bulk-restore'),
                'bulkForceDestroy' => route('admin.campus.reservations.bulk-force-destroy'),
            ],
        ]);
    }

    public function show(Request $request, RoomReservation $reservation): Response
    {
        $reservation->loadMissing(['room.building:id,name', 'user:id,first_name,last_name', 'approver:id,first_name,last_name']);

        return Inertia::render('Admin/Campus/Reservation/Show', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Detail Reservasi'),
            'reservation' => [
                'id' => $reservation->id,
                'reservationNumber' => $reservation->reservation_number,
                'title' => $reservation->title,
                'purpose' => $reservation->purpose,
                'room' => $reservation->room?->name,
                'building' => $reservation->room?->building?->name,
                'requester' => $reservation->user?->name ?? '-',
                'date' => $reservation->reservation_date?->format('d M Y'),
                'time' => substr((string) $reservation->start_time, 0, 5).' - '.substr((string) $reservation->end_time, 0, 5),
                'attendeeCount' => (int) $reservation->attendee_count,
                'status' => $reservation->status,
                'statusLabel' => self::STATUSES[$reservation->status] ?? $reservation->status,
                'rejectionReason' => $reservation->rejection_reason,
                'approver' => $reservation->approver?->name,
                'approvedAt' => $reservation->approved_at?->format('d M Y H:i'),
                'conflict' => $reservation->status === 'pending' ? RoomReservation::getConflict(
                    $reservation->room_id,
                    $reservation->reservation_date?->format('Y-m-d'),
                    $reservation->start_time,
                    $reservation->end_time,
                    $reservation->id
                ) : null,
                'createdAt' => $reservation->created_at?->format('d M Y H:i'),
            ],
            'can' => [
                'update' => ActivePermission::check('room-reservation.update'),
                'delete' => ActivePermission::check('room-reservation.delete'),
                'approve' => ActivePermission::check('room-reservation.approve'),
            ],
            'urls' => [
                'index' => route('admin.campus.reservations.index'),
                'edit' => route('admin.campus.reservations.edit', $reservation),
                'destroy' => route('admin.campus.reservations.destroy', $reservation),
                'approve' => route('admin.campus.reservations.approve', $reservation),
                'reject' => route('admin.campus.reservations.reject', $reservation),
                'cancel' => route('admin.campus.reservations.cancel', $reservation),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Campus/Reservation/Form', [
            'shell' => ShellProps::make($user, 'Kampus', 'Buat Reservasi'),
            'mode' => 'create',
            'reservation' => [
                'room_id' => null, 'user_id' => $user->id,
                'user_label' => $user->name,
                'title' => '', 'purpose' => '',
                'reservation_date' => now()->format('Y-m-d'),
                'start_time' => '08:00', 'end_time' => '10:00',
                'attendee_count' => 10,
            ],
            'roomOptions' => $this->roomOptions(),
            'urls' => [
                'index' => route('admin.campus.reservations.index'),
                'submit' => route('admin.campus.reservations.store'),
                'searchUsers' => route('admin.campus.reservations.search-users'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'user_id' => 'required|integer|exists:users,id',
            'title' => 'required|string|max:255',
            'purpose' => 'nullable|string',
            'reservation_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'attendee_count' => 'required|integer|min:1|max:100000',
        ]);

        $this->assertCapacity($validated['room_id'], (int) $validated['attendee_count']);

        $reservation = RoomReservation::create([
            'reservation_number' => $this->generateNumber(),
            'room_id' => $validated['room_id'],
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'purpose' => ($validated['purpose'] ?? null) ?: null,
            'reservation_date' => $validated['reservation_date'],
            'start_time' => $validated['start_time'].':00',
            'end_time' => $validated['end_time'].':00',
            'attendee_count' => (int) $validated['attendee_count'],
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        $conflict = RoomReservation::getConflict(
            $reservation->room_id,
            $reservation->reservation_date->format('Y-m-d'),
            $reservation->start_time,
            $reservation->end_time,
            $reservation->id
        );

        return redirect()->route('admin.campus.reservations.index')
            ->with('success', 'Permohonan "'.$reservation->reservation_number.'" berhasil dibuat (pending).'
                .($conflict ? ' Perhatian: '.$conflict : ''));
    }

    public function edit(Request $request, RoomReservation $reservation): Response
    {
        $reservation->loadMissing('user:id,first_name,last_name');

        return Inertia::render('Admin/Campus/Reservation/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Edit Reservasi'),
            'mode' => 'edit',
            'reservation' => [
                'id' => $reservation->id,
                'room_id' => $reservation->room_id,
                'user_id' => $reservation->user_id,
                'user_label' => $reservation->user?->name ?? '-',
                'title' => $reservation->title,
                'purpose' => $reservation->purpose ?? '',
                'reservation_date' => $reservation->reservation_date?->format('Y-m-d') ?? '',
                'start_time' => substr((string) $reservation->start_time, 0, 5),
                'end_time' => substr((string) $reservation->end_time, 0, 5),
                'attendee_count' => (int) $reservation->attendee_count,
                'status' => $reservation->status,
                'statusLabel' => self::STATUSES[$reservation->status] ?? $reservation->status,
            ],
            'roomOptions' => $this->roomOptions(),
            'urls' => [
                'index' => route('admin.campus.reservations.index'),
                'submit' => route('admin.campus.reservations.update', $reservation),
                'searchUsers' => route('admin.campus.reservations.search-users'),
            ],
        ]);
    }

    public function update(Request $request, RoomReservation $reservation)
    {
        $validated = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'user_id' => 'required|integer|exists:users,id',
            'title' => 'required|string|max:255',
            'purpose' => 'nullable|string',
            'reservation_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'attendee_count' => 'required|integer|min:1|max:100000',
        ]);

        $this->assertCapacity($validated['room_id'], (int) $validated['attendee_count']);

        $reservation->update([
            'room_id' => $validated['room_id'],
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'purpose' => ($validated['purpose'] ?? null) ?: null,
            'reservation_date' => $validated['reservation_date'],
            'start_time' => $validated['start_time'].':00',
            'end_time' => $validated['end_time'].':00',
            'attendee_count' => (int) $validated['attendee_count'],
            'updated_by' => $request->user()->id,
        ]);

        if ($reservation->status === 'approved' && $conflict = RoomReservation::getConflict(
            $reservation->room_id,
            $reservation->reservation_date->format('Y-m-d'),
            $reservation->start_time,
            $reservation->end_time,
            $reservation->id
        )) {
            return redirect()->route('admin.campus.reservations.index')
                ->with('warning', 'Reservasi diperbarui, tetapi kini '.$conflict);
        }

        return redirect()->route('admin.campus.reservations.index')
            ->with('success', 'Reservasi "'.$reservation->reservation_number.'" berhasil diperbarui.');
    }

    public function approve(Request $request, RoomReservation $reservation)
    {
        if ($reservation->status !== 'pending') {
            return back()->with('error', 'Hanya reservasi pending yang bisa disetujui.');
        }

        if ($conflict = RoomReservation::getConflict(
            $reservation->room_id,
            $reservation->reservation_date?->format('Y-m-d'),
            $reservation->start_time,
            $reservation->end_time,
            $reservation->id
        )) {
            return back()->with('error', 'Gagal menyetujui reservasi! '.$conflict);
        }

        $reservation->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Reservasi "'.$reservation->reservation_number.'" disetujui.');
    }

    public function reject(Request $request, RoomReservation $reservation)
    {
        if ($reservation->status !== 'pending') {
            return back()->with('error', 'Hanya reservasi pending yang bisa ditolak.');
        }

        $validated = $request->validate(['rejection_reason' => 'required|string|max:1000']);

        $reservation->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Reservasi "'.$reservation->reservation_number.'" ditolak.');
    }

    public function cancel(Request $request, RoomReservation $reservation)
    {
        if (! in_array($reservation->status, ['pending', 'approved'], true)) {
            return back()->with('error', 'Hanya reservasi pending/approved yang bisa dibatalkan.');
        }

        $reservation->update([
            'status' => 'cancelled',
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Reservasi "'.$reservation->reservation_number.'" dibatalkan.');
    }

    public function destroy(RoomReservation $reservation)
    {
        $number = $reservation->reservation_number;
        $reservation->delete();

        return redirect()->route('admin.campus.reservations.index')
            ->with('success', 'Reservasi "'.$number.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:room_reservations,id',
        ]);

        $count = RoomReservation::whereIn('id', $validated['ids'])->count();
        RoomReservation::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' reservasi berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $reservation = RoomReservation::withTrashed()->findOrFail($id);
        $reservation->restore();

        return back()->with('success', 'Reservasi "'.$reservation->reservation_number.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $reservation = RoomReservation::withTrashed()->findOrFail($id);

        $number = $reservation->reservation_number;
        $reservation->forceDelete();

        return back()->with('success', 'Reservasi "'.$number.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:room_reservations,id',
        ]);

        $count = 0;

        foreach (RoomReservation::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $reservation) {
            $reservation->restore();
            $count++;
        }

        return back()->with('success', $count.' reservasi berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:room_reservations,id',
        ]);

        $count = RoomReservation::withTrashed()->whereIn('id', $validated['ids'])->count();
        RoomReservation::withTrashed()->whereIn('id', $validated['ids'])->forceDelete();

        return back()->with('success', $count.' reservasi dihapus permanen.');
    }

    public function searchUsers(Request $request)
    {
        $validated = $request->validate(['q' => 'nullable|string|max:100']);
        $q = $validated['q'] ?? '';

        $users = \App\Models\User::query()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->when(filled($q), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('first_name')
            ->limit(8)
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name.' ('.$u->email.')'])
            ->all();

        return response()->json(['options' => $users]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = RoomReservation::query()->with(['room:id,name', 'user:id,first_name,last_name']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('reservation_number', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%"));
        }

        $rows = $query->orderByDesc('reservation_date')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nomor', 'Ruangan', 'Judul', 'Pemohon', 'Tanggal', 'Jam', 'Status', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $reservation) {
            $sheet->fromArray([
                $reservation->reservation_number,
                $reservation->room?->name,
                $reservation->title,
                $reservation->user?->name,
                $reservation->reservation_date?->format('Y-m-d'),
                substr((string) $reservation->start_time, 0, 5).' - '.substr((string) $reservation->end_time, 0, 5),
                self::STATUSES[$reservation->status] ?? $reservation->status,
                $reservation->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'room-reservations-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    private function roomOptions(): array
    {
        return Room::query()->where('is_active', true)
            ->with('building:id,name')
            ->orderBy('name')->get(['id', 'name', 'building_id', 'capacity'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name.($r->building ? ' ('.$r->building->name.')' : ''),
                'capacity' => $r->capacity,
            ])->all();
    }

    private function assertCapacity(int $roomId, int $attendees): void
    {
        $capacity = Room::where('id', $roomId)->value('capacity');

        if ($capacity && $attendees > (int) $capacity) {
            abort(back()->withErrors([
                'attendee_count' => 'Peserta ('.$attendees.') melebihi kapasitas ruangan ('.$capacity.').',
            ])->withInput());
        }
    }

    private function generateNumber(): string
    {
        do {
            $number = 'RES-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (RoomReservation::where('reservation_number', $number)->exists());

        return $number;
    }
}
