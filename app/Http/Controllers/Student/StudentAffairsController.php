<?php

namespace App\Http\Controllers\Student;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementRank;
use App\Enums\AchievementStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\FacilityBookingStatus;
use App\Enums\MemberPosition;
use App\Enums\OrganizationType;
use App\Enums\StudentEventCategory;
use App\Http\Controllers\Controller;
use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentEvent;
use App\Models\StudentAffairs\StudentEventRegistration;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
use App\Models\StudentAffairs\StudentPointEntry;
use App\Support\Inertia\ShellProps;
use App\Support\StudentAffairs\StudentPointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentAffairsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $memberships = StudentOrganizationMember::with([
            'organization.faculty:id,name,short_name',
            'organization.studyProgram:id,name',
            'organization.advisor:id,first_name,last_name',
        ])
            ->where('user_id', $user->id)
            ->orderByDesc('is_active')
            ->orderByDesc('period')
            ->get()
            ->map(fn (StudentOrganizationMember $member) => [
                'id' => $member->id,
                'organizationId' => $member->student_organization_id,
                'organizationName' => $member->organization?->name ?? '-',
                'organizationType' => $member->organization?->type?->label(),
                'organizationTypeShort' => $member->organization?->type?->shortLabel(),
                'organizationTypeTone' => $member->organization?->type?->tone() ?? 'gray',
                'faculty' => $member->organization?->faculty?->short_name,
                'studyProgram' => $member->organization?->studyProgram?->name,
                'advisor' => $member->organization?->advisor?->name,
                'position' => $member->position->value,
                'positionLabel' => $member->position->label(),
                'positionTone' => $member->position->tone(),
                'period' => $member->period,
                'joinedAt' => $member->joined_at?->format('d M Y'),
                'leftAt' => $member->left_at?->format('d M Y'),
                'isActive' => (bool) $member->is_active,
                'notes' => $member->notes,
            ]);

        $achievements = StudentAchievement::with('organization:id,name')
            ->where('user_id', $user->id)
            ->orderByDesc('achieved_at')
            ->get()
            ->map(fn (StudentAchievement $achievement) => [
                'id' => $achievement->id,
                'title' => $achievement->title,
                'organization' => $achievement->organization?->name,
                'category' => $achievement->category->value,
                'categoryLabel' => $achievement->category->label(),
                'categoryTone' => $achievement->category->tone(),
                'level' => $achievement->level->value,
                'levelLabel' => $achievement->level->label(),
                'levelTone' => $achievement->level->tone(),
                'rankLabel' => $achievement->rank->label(),
                'rankTone' => $achievement->rank->tone(),
                'achievedAt' => $achievement->achieved_at?->format('d M Y'),
                'organizer' => $achievement->organizer,
                'description' => $achievement->description,
                'status' => $achievement->status->value,
                'statusLabel' => $achievement->status->label(),
                'statusTone' => $achievement->status->tone(),
                'rejectionReason' => $achievement->rejection_reason,
                'isFeatured' => (bool) $achievement->is_featured,
                'certificateUrl' => $achievement->certificate_path
                    ? route('student.student-affairs.certificate', $achievement->id)
                    : null,
                'certificateName' => $achievement->certificate_original_name,
            ]);

        $organizations = StudentOrganization::withCount('activeMembers')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (StudentOrganization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'typeLabel' => $organization->type->label(),
                'typeShort' => $organization->type->shortLabel(),
                'typeTone' => $organization->type->tone(),
                'description' => $organization->description,
                'activeMembersCount' => (int) $organization->active_members_count,
                'logoUrl' => $organization->logo_path ? Storage::disk('public')->url($organization->logo_path) : null,
            ]);

        return Inertia::render('Student/StudentAffairs/Index', [
            'shell' => ShellProps::make($user, 'Kemahasiswaan', 'Organisasi & Prestasi'),
            'student' => [
                'name' => $user->name,
                'nim' => $user->studentProfile?->nim,
                'program' => $user->studentProfile?->studyProgram?->name,
                'semester' => $user->studentProfile?->current_semester,
            ],
            'stats' => [
                'activeMemberships' => $memberships->where('isActive', true)->count(),
                'memberships' => $memberships->count(),
                'achievements' => $achievements->count(),
                'verified' => $achievements->where('status', AchievementStatus::VERIFIED->value)->count(),
                'organizations' => StudentOrganization::where('is_active', true)->count(),
                'points' => app(StudentPointService::class)->totalFor($user),
                'registeredEvents' => StudentEventRegistration::query()
                    ->where('user_id', $user->id)
                    ->where('status', EventRegistrationStatus::REGISTERED->value)
                    ->count(),
                'activeBookings' => RoomReservation::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('student_organization_id')
                    ->whereIn('status', [FacilityBookingStatus::PENDING->value, FacilityBookingStatus::APPROVED->value])
                    ->count(),
            ],
            'memberships' => $memberships->values(),
            'achievements' => $achievements->values(),
            'organizations' => $organizations,
            'options' => [
                'categories' => AchievementCategory::options(),
                'levels' => AchievementLevel::options(),
                'ranks' => AchievementRank::options(),
                'statuses' => AchievementStatus::options(),
                'positions' => MemberPosition::options(),
                'organizationTypes' => OrganizationType::options(),
            ],
            'urls' => [
                'publicOrganizations' => route('root.kemahasiswaan.organisasi'),
                'publicAchievements' => route('root.kemahasiswaan.prestasi'),
                'events' => route('student.student-affairs.events'),
                'leaderboard' => route('student.student-affairs.leaderboard'),
                'bookings' => route('student.student-affairs.bookings'),
            ],
        ]);
    }

    public function events(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:30',
        ]);

        $query = StudentEvent::query()
            ->with('organization:id,name')
            ->withCount([
                'registrations as registered_count' => fn ($sub) => $sub->where('status', '!=', EventRegistrationStatus::CANCELLED->value),
            ])
            ->visibleToStudents()
            ->orderBy('starts_at');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('organizer', 'like', "%{$q}%")
                ->orWhere('location', 'like', "%{$q}%"));
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category', $validated['category']);
        }

        $events = $query->paginate(9)->withQueryString();

        $registrations = StudentEventRegistration::query()
            ->where('user_id', $user->id)
            ->whereIn('student_event_id', collect($events->items())->pluck('id'))
            ->get()
            ->keyBy('student_event_id');

        $mapped = collect($events->items())->values()->map(function (StudentEvent $event, int $i) use ($events, $registrations) {
            $registration = $registrations->get($event->id);
            $activeRegistration = $registration && $registration->status !== EventRegistrationStatus::CANCELLED;
            $seatsLeft = $event->quota !== null ? max(0, $event->quota - (int) $event->registered_count) : null;

            return [
                'id' => $event->id,
                'no' => ($events->firstItem() ?? 0) + $i,
                'title' => $event->title,
                'category' => $event->category->value,
                'categoryLabel' => $event->category->label(),
                'categoryTone' => $event->category->tone(),
                'organization' => $event->organization?->name,
                'organizer' => $event->organizer,
                'location' => $event->location,
                'description' => $event->description,
                'startsAt' => $event->starts_at?->format('d M Y H:i'),
                'endsAt' => $event->ends_at?->format('d M Y H:i'),
                'registrationDeadline' => $event->registration_deadline?->format('d M Y H:i'),
                'quota' => $event->quota,
                'registeredCount' => (int) $event->registered_count,
                'seatsLeft' => $seatsLeft,
                'points' => (int) $event->points,
                'status' => $event->status->value,
                'statusLabel' => $event->status->label(),
                'statusTone' => $event->status->tone(),
                'isFeatured' => (bool) $event->is_featured,
                'coverUrl' => $event->cover_path ? Storage::disk('public')->url($event->cover_path) : null,
                'isRegistrationOpen' => $event->isRegistrationOpen(),
                'canRegister' => $event->isRegistrationOpen() && ! $activeRegistration,
                'canCancel' => $registration?->status === EventRegistrationStatus::REGISTERED,
                'myRegistration' => $registration ? [
                    'id' => $registration->id,
                    'status' => $registration->status->value,
                    'statusLabel' => $registration->status->label(),
                    'statusTone' => $registration->status->tone(),
                    'registeredAt' => $registration->registered_at?->format('d M Y H:i'),
                    'attendedAt' => $registration->attended_at?->format('d M Y H:i'),
                    'pointsAwarded' => (int) $registration->points_awarded,
                ] : null,
                'registerUrl' => route('student.student-affairs.events.register', $event->id),
                'cancelUrl' => route('student.student-affairs.events.cancel-registration', $event->id),
            ];
        });

        return Inertia::render('Student/StudentAffairs/Events', [
            'shell' => ShellProps::make($user, 'Kemahasiswaan', 'Event Mahasiswa'),
            'student' => [
                'name' => $user->name,
                'nim' => $user->studentProfile?->nim,
                'program' => $user->studentProfile?->studyProgram?->name,
            ],
            'stats' => [
                'open' => StudentEvent::query()
                    ->visibleToStudents()
                    ->where(fn ($sub) => $sub->whereNull('registration_deadline')->orWhere('registration_deadline', '>=', now()))
                    ->count(),
                'registered' => StudentEventRegistration::query()
                    ->where('user_id', $user->id)
                    ->where('status', EventRegistrationStatus::REGISTERED->value)
                    ->count(),
                'attended' => StudentEventRegistration::query()
                    ->where('user_id', $user->id)
                    ->where('status', EventRegistrationStatus::ATTENDED->value)
                    ->count(),
                'points' => app(StudentPointService::class)->totalFor($user),
            ],
            'events' => $mapped,
            'data' => [
                'currentPage' => $events->currentPage(),
                'lastPage' => $events->lastPage(),
                'perPage' => $events->perPage(),
                'total' => $events->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
            ],
            'options' => [
                'categories' => StudentEventCategory::options(),
            ],
            'urls' => [
                'index' => route('student.student-affairs.events'),
                'leaderboard' => route('student.student-affairs.leaderboard'),
            ],
        ]);
    }

    public function registerEvent(Request $request, int $id)
    {
        $user = $request->user();
        $event = StudentEvent::visibleToStudents()->findOrFail($id);

        if (! $event->isRegistrationOpen()) {
            return back()->with('error', 'Pendaftaran event ini sudah ditutup.');
        }

        $registration = StudentEventRegistration::withTrashed()
            ->where('student_event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($registration && $registration->status !== EventRegistrationStatus::CANCELLED) {
            return back()->with('info', 'Anda sudah terdaftar pada event ini.');
        }

        if ($registration) {
            if ($registration->trashed()) {
                $registration->restore();
            }

            $registration->update([
                'status' => EventRegistrationStatus::REGISTERED->value,
                'registered_at' => now(),
                'attended_at' => null,
                'points_awarded' => 0,
                'updated_by' => $user->id,
            ]);
        } else {
            StudentEventRegistration::create([
                'student_event_id' => $event->id,
                'user_id' => $user->id,
                'status' => EventRegistrationStatus::REGISTERED->value,
                'registered_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        return back()->with('success', 'Pendaftaran event "'.$event->title.'" berhasil.');
    }

    public function cancelEventRegistration(Request $request, int $id)
    {
        $registration = StudentEventRegistration::query()
            ->where('student_event_id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($registration->status === EventRegistrationStatus::ATTENDED) {
            return back()->with('error', 'Kehadiran sudah dicatat, pendaftaran tidak bisa dibatalkan.');
        }

        if ($registration->status === EventRegistrationStatus::CANCELLED) {
            return back()->with('info', 'Pendaftaran event ini sudah dibatalkan.');
        }

        $registration->update([
            'status' => EventRegistrationStatus::CANCELLED->value,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Pendaftaran event dibatalkan.');
    }

    public function leaderboard(Request $request, StudentPointService $points): Response
    {
        $user = $request->user();

        $leaderboard = $points->leaderboard(limit: 20)
            ->map(fn (array $row) => [
                'rank' => $row['rank'],
                'userId' => $row['user']->id,
                'name' => $row['user']->name,
                'nim' => $row['user']->studentProfile?->nim,
                'program' => $row['user']->studentProfile?->studyProgram?->name,
                'total' => $row['total'],
                'isMe' => $row['user']->id === $user->id,
            ])
            ->values()
            ->all();

        $entries = StudentPointEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('awarded_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (StudentPointEntry $entry) => [
                'id' => $entry->id,
                'points' => (int) $entry->points,
                'category' => $entry->category->value,
                'categoryLabel' => $entry->category->label(),
                'categoryTone' => $entry->category->tone(),
                'description' => $entry->description,
                'awardedAt' => $entry->awarded_at?->format('d M Y'),
            ]);

        return Inertia::render('Student/StudentAffairs/Leaderboard', [
            'shell' => ShellProps::make($user, 'Kemahasiswaan', 'Poin & Leaderboard'),
            'student' => [
                'name' => $user->name,
                'nim' => $user->studentProfile?->nim,
                'program' => $user->studentProfile?->studyProgram?->name,
            ],
            'stats' => [
                'myPoints' => $points->totalFor($user),
                'myRank' => $points->rankFor($user),
                'participants' => StudentPointEntry::query()->distinct('user_id')->count('user_id'),
                'myEntries' => StudentPointEntry::query()->where('user_id', $user->id)->count(),
            ],
            'leaderboard' => $leaderboard,
            'entries' => $entries,
            'urls' => [
                'events' => route('student.student-affairs.events'),
            ],
        ]);
    }

    public function bookings(Request $request): Response
    {
        $user = $request->user();

        $organizations = StudentOrganizationMember::query()
            ->with('organization:id,name')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->map(fn (StudentOrganizationMember $member) => $member->organization)
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn (StudentOrganization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
            ]);

        $bookings = RoomReservation::query()
            ->with([
                'room:id,name,capacity,building_id',
                'room.building:id,name',
                'studentOrganization:id,name',
            ])
            ->where('user_id', $user->id)
            ->whereNotNull('student_organization_id')
            ->orderByDesc('reservation_date')
            ->orderByDesc('start_time')
            ->get()
            ->map(fn (RoomReservation $booking) => [
                'id' => $booking->id,
                'reservationNumber' => $booking->reservation_number,
                'title' => $booking->title,
                'purpose' => $booking->purpose,
                'organization' => $booking->studentOrganization?->name,
                'room' => $booking->room?->name,
                'building' => $booking->room?->building?->name,
                'capacity' => $booking->room?->capacity,
                'date' => $booking->reservation_date?->format('d M Y'),
                'startTime' => substr((string) $booking->start_time, 0, 5),
                'endTime' => substr((string) $booking->end_time, 0, 5),
                'attendeeCount' => (int) $booking->attendee_count,
                'status' => $booking->status,
                'statusLabel' => FacilityBookingStatus::tryFrom($booking->status)?->label() ?? $booking->status,
                'statusTone' => FacilityBookingStatus::tryFrom($booking->status)?->tone() ?? 'gray',
                'rejectionReason' => $booking->rejection_reason,
                'canCancel' => in_array($booking->status, [FacilityBookingStatus::PENDING->value, FacilityBookingStatus::APPROVED->value], true),
                'cancelUrl' => route('student.student-affairs.bookings.cancel', $booking->id),
            ]);

        $rooms = Room::query()
            ->where('is_active', true)
            ->with('building:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'building_id', 'capacity'])
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'building' => $room->building?->name,
                'capacity' => $room->capacity,
            ]);

        return Inertia::render('Student/StudentAffairs/Bookings', [
            'shell' => ShellProps::make($user, 'Kemahasiswaan', 'Booking Fasilitas'),
            'student' => [
                'name' => $user->name,
                'nim' => $user->studentProfile?->nim,
                'program' => $user->studentProfile?->studyProgram?->name,
            ],
            'stats' => [
                'total' => $bookings->count(),
                'pending' => $bookings->where('status', FacilityBookingStatus::PENDING->value)->count(),
                'approved' => $bookings->where('status', FacilityBookingStatus::APPROVED->value)->count(),
                'upcoming' => $bookings->filter(fn (array $booking) => $booking['status'] === FacilityBookingStatus::APPROVED->value)->count(),
            ],
            'bookings' => $bookings,
            'organizations' => $organizations,
            'rooms' => $rooms,
            'urls' => [
                'index' => route('student.student-affairs.bookings'),
                'store' => route('student.student-affairs.bookings.store'),
            ],
        ]);
    }

    public function storeBooking(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_organization_id' => ['required', 'integer', 'exists:student_organizations,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'attendee_count' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $isMember = StudentOrganizationMember::query()
            ->where('user_id', $user->id)
            ->where('student_organization_id', $validated['student_organization_id'])
            ->where('is_active', true)
            ->exists();

        if (! $isMember) {
            return back()->withErrors([
                'student_organization_id' => 'Anda bukan anggota aktif organisasi tersebut.',
            ])->withInput();
        }

        $room = Room::query()->where('is_active', true)->find($validated['room_id']);

        if (! $room) {
            return back()->withErrors(['room_id' => 'Ruangan tidak tersedia.'])->withInput();
        }

        if ($room->capacity && (int) $validated['attendee_count'] > (int) $room->capacity) {
            return back()->withErrors([
                'attendee_count' => 'Peserta ('.$validated['attendee_count'].') melebihi kapasitas ruangan ('.$room->capacity.').',
            ])->withInput();
        }

        if ($conflict = RoomReservation::getConflict(
            (int) $validated['room_id'],
            $validated['reservation_date'],
            $validated['start_time'],
            $validated['end_time']
        )) {
            return back()->withErrors(['start_time' => $conflict])->withInput();
        }

        $booking = RoomReservation::create([
            ...$validated,
            'reservation_number' => $this->generateReservationNumber(),
            'status' => FacilityBookingStatus::PENDING->value,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return back()->with('success', 'Booking "'.$booking->reservation_number.'" diajukan dan menunggu persetujuan.');
    }

    public function cancelBooking(Request $request, int $id)
    {
        $booking = RoomReservation::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('student_organization_id')
            ->findOrFail($id);

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

    private function generateReservationNumber(): string
    {
        do {
            $number = 'RES-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (RoomReservation::withTrashed()->where('reservation_number', $number)->exists());

        return $number;
    }

    public function downloadCertificate(Request $request, int $id)
    {
        $achievement = StudentAchievement::where('user_id', $request->user()->id)->findOrFail($id);

        if (blank($achievement->certificate_path) || ! Storage::disk('public')->exists($achievement->certificate_path)) {
            abort(404);
        }

        return Storage::disk('public')->download(
            $achievement->certificate_path,
            $achievement->certificate_original_name ?: 'sertifikat-prestasi'
        );
    }
}
