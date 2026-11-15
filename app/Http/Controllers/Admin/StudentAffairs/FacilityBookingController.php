<?php

namespace App\Http\Controllers\Admin\StudentAffairs;

use App\Enums\FacilityBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Campus\RoomReservation;
use App\Models\StudentAffairs\StudentOrganization;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Booking fasilitas oleh organisasi mahasiswa.
 *
 * Memakai tabel room_reservations yang sama dengan modul Kampus agar
 * deteksi bentrok jadwal tetap satu sumber. Halaman ini hanya menampilkan
 * reservasi yang memiliki student_organization_id. Approve/reject tetap
 * memakai aturan state machine modul Kampus.
 */
class FacilityBookingController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:20',
            'organization' => 'nullable|integer|exists:student_organizations,id',
            'date' => 'nullable|date',
            'sort' => 'nullable|in:id,reservation_date,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $base = RoomReservation::query()->whereNotNull('student_organization_id');

        $query = (clone $base)->with([
            'room:id,name,capacity,building_id',
            'room.building:id,name',
            'user:id,first_name,last_name',
            'studentOrganization:id,name,type',
        ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('reservation_number', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%")
                ->orWhereHas('studentOrganization', fn ($org) => $org->where('name', 'like', "%{$q}%")));
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['organization'] ?? null)) {
            $query->where('student_organization_id', $validated['organization']);
        }

        if (filled($validated['date'] ?? null)) {
            $query->whereDate('reservation_date', $validated['date']);
        }

        $sort = $validated['sort'] ?? 'reservation_date';
        $direction = $validated['direction'] ?? 'desc';

        if ($sort === 'reservation_date') {
            $query->orderBy('reservation_date', $direction)->orderBy('start_time', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $bookings = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentAffairs/FacilityBookings/Index', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Booking Fasilitas'),
            'can' => [
                'approve' => ActivePermission::check('student-facility-booking.approve'),
                'delete' => ActivePermission::check('student-facility-booking.delete'),
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'pending' => (clone $base)->where('status', FacilityBookingStatus::PENDING->value)->count(),
                'approved' => (clone $base)->where('status', FacilityBookingStatus::APPROVED->value)->count(),
                'rejected' => (clone $base)->where('status', FacilityBookingStatus::REJECTED->value)->count(),
            ],
            'data' => [
                'rows' => collect($bookings->items())->values()->map(fn (RoomReservation $booking, int $i) => [
                    'id' => $booking->id,
                    'no' => ($bookings->firstItem() ?? 0) + $i,
                    'reservationNumber' => $booking->reservation_number,
                    'title' => $booking->title,
                    'organization' => $booking->studentOrganization?->name ?? '-',
                    'requester' => $booking->user?->name ?? '-',
                    'room' => $booking->room?->name,
                    'building' => $booking->room?->building?->name,
                    'date' => $booking->reservation_date?->format('d M Y'),
                    'time' => substr((string) $booking->start_time, 0, 5).' - '.substr((string) $booking->end_time, 0, 5),
                    'attendeeCount' => (int) $booking->attendee_count,
                    'status' => $booking->status,
                    'statusLabel' => FacilityBookingStatus::tryFrom($booking->status)?->label() ?? $booking->status,
                    'statusTone' => FacilityBookingStatus::tryFrom($booking->status)?->tone() ?? 'gray',
                    'rejectionReason' => $booking->rejection_reason,
                    'showUrl' => route('admin.campus.reservations.show', $booking->id),
                    'approveUrl' => route('admin.student-affairs.facility-bookings.approve', $booking->id),
                    'rejectUrl' => route('admin.student-affairs.facility-bookings.reject', $booking->id),
                    'cancelUrl' => route('admin.student-affairs.facility-bookings.cancel', $booking->id),
                    'deleteUrl' => route('admin.student-affairs.facility-bookings.destroy', $booking->id),
                ])->all(),
                'currentPage' => $bookings->currentPage(),
                'lastPage' => $bookings->lastPage(),
                'perPage' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'organization' => $validated['organization'] ?? '',
                'date' => $validated['date'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'statuses' => FacilityBookingStatus::options(),
                'organizations' => StudentOrganization::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($organization) => ['id' => $organization->id, 'name' => $organization->name])->all(),
            ],
            'urls' => [
                'index' => route('admin.student-affairs.facility-bookings.index'),
                'bulkDestroy' => route('admin.student-affairs.facility-bookings.bulk-destroy'),
                'campusReservations' => route('admin.campus.reservations.index'),
            ],
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $booking = RoomReservation::whereNotNull('student_organization_id')->findOrFail($id);

        if ($booking->status !== FacilityBookingStatus::PENDING->value) {
            return back()->with('error', 'Hanya booking pending yang bisa disetujui.');
        }

        if ($conflict = RoomReservation::getConflict(
            $booking->room_id,
            $booking->reservation_date?->format('Y-m-d'),
            $booking->start_time,
            $booking->end_time,
            $booking->id
        )) {
            return back()->with('error', 'Gagal menyetujui booking! '.$conflict);
        }

        $booking->update([
            'status' => FacilityBookingStatus::APPROVED->value,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Booking "'.$booking->reservation_number.'" disetujui.');
    }

    public function reject(Request $request, int $id)
    {
        $booking = RoomReservation::whereNotNull('student_organization_id')->findOrFail($id);

        if ($booking->status !== FacilityBookingStatus::PENDING->value) {
            return back()->with('error', 'Hanya booking pending yang bisa ditolak.');
        }

        $validated = $request->validate(['rejection_reason' => 'required|string|max:1000']);

        $booking->update([
            'status' => FacilityBookingStatus::REJECTED->value,
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Booking "'.$booking->reservation_number.'" ditolak.');
    }

    public function cancel(Request $request, int $id)
    {
        $booking = RoomReservation::whereNotNull('student_organization_id')->findOrFail($id);

        if (! in_array($booking->status, [FacilityBookingStatus::PENDING->value, FacilityBookingStatus::APPROVED->value], true)) {
            return back()->with('error', 'Hanya booking pending/disetujui yang bisa dibatalkan.');
        }

        $booking->update([
            'status' => FacilityBookingStatus::CANCELLED->value,
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Booking "'.$booking->reservation_number.'" dibatalkan.');
    }

    public function destroy(Request $request, int $id)
    {
        $booking = RoomReservation::whereNotNull('student_organization_id')->findOrFail($id);
        $number = $booking->reservation_number;
        $booking->update(['deleted_by' => $request->user()->id]);
        $booking->delete();

        return back()->with('success', 'Booking "'.$number.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:room_reservations,id',
        ]);

        $bookings = RoomReservation::whereNotNull('student_organization_id')
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($bookings as $booking) {
            $booking->update(['deleted_by' => $request->user()->id]);
            $booking->delete();
        }

        return back()->with('success', $bookings->count().' booking berhasil dihapus.');
    }
}
