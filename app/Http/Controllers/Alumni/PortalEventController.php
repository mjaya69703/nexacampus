<?php

namespace App\Http\Controllers\Alumni;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniEventParticipant;
use App\Models\Alumni\AlumniProfile;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Event alumni (portal) — daftar, detail, dan pendaftaran.
 *
 * Koreksi dari Blade lama: guard pendaftaran (event selesai, tenggat,
 * kuota, duplikat) diperiksa ulang saat POST di dalam transaksi, bukan
 * hanya saat halaman dibuka; pendaftaran ganda tidak lagi mungkin terjadi
 * walau dua tab dibuka bersamaan.
 */
class PortalEventController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'event_type' => ['nullable', Rule::in(array_keys(EventType::options()))],
            'period' => 'nullable|in:upcoming,past',
        ]);

        $period = $validated['period'] ?? '';

        $events = AlumniEvent::query()
            ->where('is_published', true)
            ->withCount('participants')
            ->when($validated['event_type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->when($period === 'upcoming', fn ($query) => $query->where('event_date', '>=', now()))
            ->when($period === 'past', fn ($query) => $query->where('event_date', '<', now()))
            ->when($period === 'upcoming', fn ($query) => $query->orderBy('event_date'), fn ($query) => $query->orderByDesc('event_date'))
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Alumni/Events/Index', [
            'shell' => ShellProps::make($request->user(), 'Event Alumni', 'Daftar Event'),
            'data' => [
                'rows' => collect($events->items())->values()->map(fn (AlumniEvent $event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'eventType' => $event->event_type,
                    'eventTypeLabel' => EventType::tryFrom((string) $event->event_type)?->label() ?? $event->event_type,
                    'dateLabel' => $event->event_date?->format('d M Y'),
                    'dayLabel' => $event->event_date?->format('d'),
                    'monthLabel' => $event->event_date?->format('M Y'),
                    'timeLabel' => $event->event_date?->format('H:i'),
                    'location' => $event->location,
                    'isOnline' => (bool) $event->is_online,
                    'isPast' => $event->event_date !== null && $event->event_date->isPast(),
                    'maxParticipants' => $event->max_participants,
                    'participantsCount' => (int) $event->participants_count,
                    'slotsLeft' => $event->max_participants
                        ? max(0, (int) $event->max_participants - (int) $event->participants_count)
                        : null,
                    'url' => route('alumni.events.show', $event),
                ])->all(),
                'currentPage' => $events->currentPage(),
                'lastPage' => $events->lastPage(),
                'perPage' => $events->perPage(),
                'total' => $events->total(),
            ],
            'filters' => [
                'event_type' => $validated['event_type'] ?? '',
                'period' => $period,
            ],
            'eventTypeOptions' => EventType::options(),
            'urls' => [
                'index' => route('alumni.events.index'),
            ],
        ]);
    }

    public function show(Request $request, AlumniEvent $alumniEvent): Response
    {
        abort_unless($alumniEvent->is_published, 404);

        $alumniEvent->loadCount('participants');
        $profile = $request->user()->alumniProfile()->first();

        $isRegistered = $profile
            && $alumniEvent->participants()->where('alumni_profile_id', $profile->id)->exists();

        $slotsLeft = $alumniEvent->max_participants
            ? max(0, (int) $alumniEvent->max_participants - (int) $alumniEvent->participants_count)
            : null;

        $blocker = $profile ? $this->registrationBlocker($alumniEvent, $profile) : null;

        return Inertia::render('Alumni/Events/Show', [
            'shell' => ShellProps::make($request->user(), 'Event Alumni', $alumniEvent->title),
            'event' => [
                'id' => $alumniEvent->id,
                'title' => $alumniEvent->title,
                'description' => $alumniEvent->description,
                'eventType' => $alumniEvent->event_type,
                'eventTypeLabel' => EventType::tryFrom((string) $alumniEvent->event_type)?->label() ?? $alumniEvent->event_type,
                'eventDate' => $alumniEvent->event_date?->format('d M Y'),
                'startTime' => $alumniEvent->event_date?->format('H:i'),
                'endTime' => $alumniEvent->end_date?->format('H:i'),
                'location' => $alumniEvent->location,
                'isOnline' => (bool) $alumniEvent->is_online,
                'meetingUrl' => $alumniEvent->meeting_url,
                'maxParticipants' => $alumniEvent->max_participants,
                'participantsCount' => (int) $alumniEvent->participants_count,
                'slotsLeft' => $slotsLeft,
                'registrationDeadline' => $alumniEvent->registration_deadline?->format('d M Y, H:i'),
                'isPast' => $alumniEvent->event_date !== null && $alumniEvent->event_date->isPast(),
            ],
            'hasProfile' => (bool) $profile,
            'isRegistered' => (bool) $isRegistered,
            'canRegister' => (bool) $profile && $blocker === null,
            'registrationBlocker' => $blocker[1] ?? null,
            'urls' => [
                'index' => route('alumni.events.index'),
                'register' => route('alumni.events.register', $alumniEvent),
                'profile' => route('alumni.profile.index'),
            ],
        ]);
    }

    public function registerForm(Request $request, AlumniEvent $alumniEvent): Response|RedirectResponse
    {
        abort_unless($alumniEvent->is_published, 404);

        $profile = $request->user()->alumniProfile()->first();
        abort_unless($profile, 403, 'Profil alumni belum tersedia.');

        $blocker = $this->registrationBlocker($alumniEvent, $profile);

        if ($blocker) {
            return redirect()
                ->route('alumni.events.show', $alumniEvent)
                ->with($blocker[0], $blocker[1]);
        }

        return Inertia::render('Alumni/Events/Register', [
            'shell' => ShellProps::make($request->user(), 'Event Alumni', 'Daftar Event: '.$alumniEvent->title),
            'event' => [
                'id' => $alumniEvent->id,
                'title' => $alumniEvent->title,
                'eventTypeLabel' => EventType::tryFrom((string) $alumniEvent->event_type)?->label() ?? $alumniEvent->event_type,
                'eventDate' => $alumniEvent->event_date?->format('d M Y, H:i'),
                'location' => $alumniEvent->location,
                'isOnline' => (bool) $alumniEvent->is_online,
            ],
            'urls' => [
                'show' => route('alumni.events.show', $alumniEvent),
                'store' => route('alumni.events.register.store', $alumniEvent),
            ],
        ]);
    }

    public function register(Request $request, AlumniEvent $alumniEvent): RedirectResponse
    {
        abort_unless($alumniEvent->is_published, 404);

        $profile = $request->user()->alumniProfile()->first();
        abort_unless($profile, 403, 'Profil alumni belum tersedia.');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $blocker = DB::transaction(function () use ($alumniEvent, $profile, $validated): ?array {
            $locked = AlumniEvent::query()
                ->whereKey($alumniEvent->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Guard diperiksa ulang di dalam transaksi: event bisa dibatalkan,
            // tenggat lewat, atau kuota terisi di antara halaman dibuka dan submit.
            if (! $locked->is_published) {
                return ['error', 'Event ini sudah tidak tersedia.'];
            }

            $blocker = $this->registrationBlocker($locked, $profile);

            if ($blocker) {
                return $blocker;
            }

            AlumniEventParticipant::create([
                'alumni_event_id' => $locked->id,
                'alumni_profile_id' => $profile->id,
                'registered_at' => now(),
                'notes' => filled($validated['notes'] ?? null) ? $validated['notes'] : null,
            ]);

            return null;
        });

        if ($blocker) {
            return redirect()
                ->route('alumni.events.show', $alumniEvent)
                ->with($blocker[0], $blocker[1]);
        }

        return redirect()
            ->route('alumni.events.show', $alumniEvent)
            ->with('success', 'Pendaftaran event berhasil!');
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function registrationBlocker(AlumniEvent $event, AlumniProfile $profile): ?array
    {
        if ($event->participants()->where('alumni_profile_id', $profile->id)->exists()) {
            return ['info', 'Kamu sudah terdaftar di event ini.'];
        }

        if ($event->event_date && $event->event_date->isPast()) {
            return ['error', 'Event ini sudah selesai.'];
        }

        if ($event->registration_deadline && $event->registration_deadline->isPast()) {
            return ['error', 'Pendaftaran event sudah ditutup.'];
        }

        $slots = $event->availableSlots();

        if ($slots !== null && $slots <= 0) {
            return ['error', 'Kuota event sudah penuh.'];
        }

        return null;
    }
}
