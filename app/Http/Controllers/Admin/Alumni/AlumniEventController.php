<?php

namespace App\Http\Controllers\Admin\Alumni;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniEventParticipant;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Event / Agenda Alumni — kit CRUD shared (Inertia React).
 *
 * Koreksi dari Blade lama:
 * - kolom opsional benar-benar bisa dikosongkan saat edit (dulu nilai kosong
 *   dibuang sehingga data lama tetap menempel);
 * - event online wajib memiliki tautan meeting, dan batas pendaftaran tidak
 *   boleh melewati waktu mulai acara;
 * - kuota peserta tidak boleh lebih kecil dari jumlah peserta yang sudah
 *   terdaftar;
 * - poster lama dibersihkan saat diganti atau saat data dihapus permanen.
 */
class AlumniEventController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $this->validateIndex($request);
        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $sort = $validated['sort'] ?? 'event_date';
        $direction = $validated['direction'] ?? 'desc';
        $query = $this->filteredQuery($validated, applySort: false);
        $this->applySort($query, $sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $events = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Alumni/Event/Index', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Agenda & Event Alumni'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('alumni-event.create'),
                'update' => ActivePermission::check('alumni-event.update'),
                'delete' => ActivePermission::check('alumni-event.delete'),
                'restore' => ActivePermission::any(['alumni-event.update', 'alumni-event.delete']),
                'toggle' => ActivePermission::check('alumni-event.update'),
                'view' => ActivePermission::check('alumni-event.view'),
            ],
            'stats' => [
                'total' => AlumniEvent::count(),
                'published' => AlumniEvent::where('is_published', true)->count(),
                'online' => AlumniEvent::where('is_online', true)->count(),
                'upcoming' => AlumniEvent::where('event_date', '>=', now())->count(),
                'participants' => AlumniEventParticipant::query()->count(),
                'trashed' => AlumniEvent::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($events->items())->values()->map(fn (AlumniEvent $event, int $i) => [
                    'id' => $event->id,
                    'no' => ($events->firstItem() ?? 0) + $i,
                    'title' => $event->title,
                    'eventType' => $event->event_type,
                    'eventTypeLabel' => EventType::tryFrom($event->event_type)?->label() ?? $event->event_type,
                    'eventDate' => $event->event_date?->toIso8601String(),
                    'eventDateLabel' => $event->event_date?->format('d M Y H:i'),
                    'endDateLabel' => $event->end_date?->format('d M Y H:i'),
                    'registrationDeadlineLabel' => $event->registration_deadline?->format('d M Y H:i'),
                    'registrationOpen' => $this->registrationOpen($event, (int) ($event->participants_count ?? 0)),
                    'location' => $event->location,
                    'isOnline' => (bool) $event->is_online,
                    'meetingUrl' => $event->meeting_url,
                    'maxParticipants' => $event->max_participants,
                    'participantsCount' => (int) ($event->participants_count ?? 0),
                    'slotsLeft' => $event->max_participants
                        ? max(0, (int) $event->max_participants - (int) ($event->participants_count ?? 0))
                        : null,
                    'posterUrl' => $this->publicUrl($event->poster_path),
                    'isPublished' => (bool) $event->is_published,
                    'createdAt' => $event->created_at?->format('d M Y H:i'),
                    'showUrl' => $isTrash ? null : route('admin.alumni.alumni-events.show', $event),
                    'editUrl' => $isTrash ? null : route('admin.alumni.alumni-events.edit', $event),
                    'deleteUrl' => route('admin.alumni.alumni-events.destroy', $event),
                    'restoreUrl' => route('admin.alumni.alumni-events.restore', $event->id),
                    'forceUrl' => route('admin.alumni.alumni-events.force-destroy', $event->id),
                    'toggleUrl' => route('admin.alumni.alumni-events.toggle', $event),
                ])->all(),
                'currentPage' => $events->currentPage(),
                'lastPage' => $events->lastPage(),
                'perPage' => $events->perPage(),
                'total' => $events->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'event_type' => $validated['event_type'] ?? '',
                'is_online' => $validated['is_online'] ?? '',
                'is_published' => $validated['is_published'] ?? '',
                'period' => $validated['period'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'eventTypeOptions' => EventType::options(),
            'urls' => [
                'index' => route('admin.alumni.alumni-events.index'),
                'create' => route('admin.alumni.alumni-events.create'),
                'export' => route('admin.alumni.alumni-events.export'),
                'bulkDestroy' => route('admin.alumni.alumni-events.bulk-destroy'),
                'bulkRestore' => route('admin.alumni.alumni-events.bulk-restore'),
                'bulkForceDestroy' => route('admin.alumni.alumni-events.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Alumni/Event/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Tambah Event Alumni'),
            'mode' => 'create',
            'event' => $this->defaultEvent(),
            'eventTypeOptions' => EventType::options(),
            'urls' => [
                'index' => route('admin.alumni.alumni-events.index'),
                'submit' => route('admin.alumni.alumni-events.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateEvent($request);

        $data = [
            ...$this->eventPayload($validated),
            'created_by' => $request->user()->id,
        ];

        if ($request->hasFile('poster')) {
            $data['poster_path'] = $request->file('poster')->store('alumni-events/posters', 'public');
        }

        $event = AlumniEvent::create($data);

        return redirect()->route('admin.alumni.alumni-events.index')
            ->with('success', 'Event alumni "'.$event->title.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, AlumniEvent $alumniEvent): Response
    {
        $alumniEvent->loadCount('participants');

        return Inertia::render('Admin/Alumni/Event/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Edit Event Alumni'),
            'mode' => 'edit',
            'event' => [
                'id' => $alumniEvent->id,
                ...$this->defaultEvent(),
                'title' => $alumniEvent->title,
                'description' => $alumniEvent->description ?? '',
                'event_type' => $alumniEvent->event_type,
                'event_date' => $alumniEvent->event_date?->format('Y-m-d\TH:i') ?? '',
                'end_date' => $alumniEvent->end_date?->format('Y-m-d\TH:i') ?? '',
                'location' => $alumniEvent->location ?? '',
                'is_online' => (bool) $alumniEvent->is_online,
                'meeting_url' => $alumniEvent->meeting_url ?? '',
                'max_participants' => $alumniEvent->max_participants,
                'registration_deadline' => $alumniEvent->registration_deadline?->format('Y-m-d\TH:i') ?? '',
                'is_published' => (bool) $alumniEvent->is_published,
                'poster_url' => $this->publicUrl($alumniEvent->poster_path),
                'participants_count' => (int) $alumniEvent->participants_count,
            ],
            'eventTypeOptions' => EventType::options(),
            'urls' => [
                'index' => route('admin.alumni.alumni-events.index'),
                'submit' => route('admin.alumni.alumni-events.update', $alumniEvent),
            ],
        ]);
    }

    public function update(Request $request, AlumniEvent $alumniEvent)
    {
        $validated = $this->validateEvent($request, $alumniEvent);

        $data = [
            ...$this->eventPayload($validated),
            'updated_by' => $request->user()->id,
        ];

        if (($validated['remove_poster'] ?? false) && $alumniEvent->poster_path) {
            $this->deletePoster($alumniEvent->poster_path);
            $data['poster_path'] = null;
        }

        if ($request->hasFile('poster')) {
            if ($alumniEvent->poster_path) {
                $this->deletePoster($alumniEvent->poster_path);
            }

            $data['poster_path'] = $request->file('poster')->store('alumni-events/posters', 'public');
        }

        $alumniEvent->update($data);

        return redirect()->route('admin.alumni.alumni-events.index')
            ->with('success', 'Event alumni "'.$alumniEvent->title.'" berhasil diperbarui.');
    }

    public function show(Request $request, AlumniEvent $alumniEvent): Response
    {
        $alumniEvent->loadCount('participants');
        $alumniEvent->load(['createdByUser:id,first_name,last_name', 'updatedByUser:id,first_name,last_name']);

        $participants = $alumniEvent->participants()
            ->with(['alumniProfile:id,full_name,nim,study_program_id', 'alumniProfile.studyProgram:id,name'])
            ->orderByDesc('registered_at')
            ->get();

        return Inertia::render('Admin/Alumni/Event/Show', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Detail Event Alumni'),
            'can' => [
                'update' => ActivePermission::check('alumni-event.update'),
                'delete' => ActivePermission::check('alumni-event.delete'),
            ],
            'event' => [
                'id' => $alumniEvent->id,
                'title' => $alumniEvent->title,
                'description' => $alumniEvent->description,
                'eventType' => $alumniEvent->event_type,
                'eventTypeLabel' => EventType::tryFrom($alumniEvent->event_type)?->label() ?? $alumniEvent->event_type,
                'eventDate' => $alumniEvent->event_date?->format('d M Y, H:i'),
                'endDate' => $alumniEvent->end_date?->format('d M Y, H:i'),
                'registrationDeadline' => $alumniEvent->registration_deadline?->format('d M Y, H:i'),
                'registrationOpen' => $this->registrationOpen($alumniEvent),
                'location' => $alumniEvent->location,
                'isOnline' => (bool) $alumniEvent->is_online,
                'meetingUrl' => $alumniEvent->meeting_url,
                'maxParticipants' => $alumniEvent->max_participants,
                'participantsCount' => (int) $alumniEvent->participants_count,
                'slotsLeft' => $alumniEvent->availableSlots(),
                'posterUrl' => $this->publicUrl($alumniEvent->poster_path),
                'isPublished' => (bool) $alumniEvent->is_published,
                'createdAt' => $alumniEvent->created_at?->format('d M Y H:i'),
                'updatedAt' => $alumniEvent->updated_at?->format('d M Y H:i'),
                'createdBy' => $alumniEvent->createdByUser?->name,
                'updatedBy' => $alumniEvent->updatedByUser?->name,
            ],
            'participants' => $participants->map(fn ($participant) => [
                'id' => $participant->id,
                'name' => $participant->alumniProfile?->full_name ?? '-',
                'nim' => $participant->alumniProfile?->nim ?? '-',
                'studyProgram' => $participant->alumniProfile?->studyProgram?->name ?? '-',
                'registeredAt' => $participant->registered_at?->format('d M Y, H:i'),
                'attendedAt' => $participant->attended_at?->format('d M Y, H:i'),
                'attended' => (bool) $participant->attended_at,
                'notes' => $participant->notes,
            ])->all(),
            'urls' => [
                'index' => route('admin.alumni.alumni-events.index'),
                'edit' => ActivePermission::check('alumni-event.update')
                    ? route('admin.alumni.alumni-events.edit', $alumniEvent)
                    : null,
                'destroy' => route('admin.alumni.alumni-events.destroy', $alumniEvent),
            ],
        ]);
    }

    public function toggle(Request $request, AlumniEvent $alumniEvent)
    {
        $validated = $request->validate([
            'is_published' => 'required|boolean',
        ]);

        $alumniEvent->update([
            'is_published' => (bool) $validated['is_published'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status publikasi event berhasil diperbarui.');
    }

    public function destroy(Request $request, AlumniEvent $alumniEvent)
    {
        $title = $alumniEvent->title;
        $alumniEvent->update(['updated_by' => $request->user()->id]);
        $alumniEvent->delete();

        return redirect()->route('admin.alumni.alumni-events.index')
            ->with('success', 'Event alumni "'.$title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:alumni_events,id',
        ]);

        $events = AlumniEvent::whereIn('id', $validated['ids'])->get();

        foreach ($events as $event) {
            $event->update(['updated_by' => $request->user()->id]);
            $event->delete();
        }

        return back()->with('success', $events->count().' event alumni berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $event = AlumniEvent::withTrashed()->findOrFail($id);
        $event->restore();

        return back()->with('success', 'Event alumni "'.$event->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $event = AlumniEvent::withTrashed()->findOrFail($id);
        $title = $event->title;

        $this->deletePoster($event->poster_path);
        $event->forceDelete();

        return back()->with('success', 'Event alumni "'.$title.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:alumni_events,id',
        ]);

        $count = 0;

        foreach (AlumniEvent::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $event) {
            $event->restore();
            $count++;
        }

        return back()->with('success', $count.' event alumni berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:alumni_events,id',
        ]);

        $count = 0;

        foreach (AlumniEvent::withTrashed()->whereIn('id', $validated['ids'])->get() as $event) {
            $this->deletePoster($event->poster_path);
            $event->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' event alumni dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = $this->filteredQuery($request->all(), applySort: false);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $events = $query->withCount('participants')->orderByDesc('event_date')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Judul', 'Tipe', 'Mulai', 'Selesai', 'Batas Pendaftaran', 'Lokasi', 'Mode', 'URL Meeting', 'Kuota', 'Peserta', 'Publikasi', 'Dibuat'],
        ], null, 'A1');

        foreach ($events as $i => $event) {
            $sheet->fromArray([
                $event->title,
                EventType::tryFrom($event->event_type)?->label() ?? $event->event_type,
                $event->event_date?->format('Y-m-d H:i'),
                $event->end_date?->format('Y-m-d H:i'),
                $event->registration_deadline?->format('Y-m-d H:i'),
                $event->location,
                $event->is_online ? 'Online' : 'Offline',
                $event->meeting_url,
                $event->max_participants ?? 'Tanpa batas',
                (int) ($event->participants_count ?? 0),
                $event->is_published ? 'Ya' : 'Tidak',
                $event->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'event-alumni-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIndex(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:100',
            'event_type' => ['nullable', Rule::in(array_keys(EventType::options()))],
            'is_online' => 'nullable|in:0,1',
            'is_published' => 'nullable|in:0,1',
            'period' => 'nullable|in:upcoming,past',
            'sort' => 'nullable|in:id,title,event_type,event_date,is_published,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function filteredQuery(array $validated, bool $applySort = true): Builder
    {
        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $query = AlumniEvent::query()->withCount('participants');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        foreach (['event_type', 'is_online', 'is_published'] as $field) {
            if (isset($validated[$field]) && $validated[$field] !== '') {
                $query->where($field, $field === 'event_type' ? $validated[$field] : (bool) $validated[$field]);
            }
        }

        if (($validated['period'] ?? '') === 'upcoming') {
            $query->where('event_date', '>=', now());
        } elseif (($validated['period'] ?? '') === 'past') {
            $query->where('event_date', '<', now());
        }

        if ($applySort) {
            $this->applySort($query, $validated['sort'] ?? 'event_date', $validated['direction'] ?? 'desc');
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEvent(Request $request, ?AlumniEvent $event = null): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'event_type' => ['required', Rule::in(array_keys(EventType::options()))],
            'event_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'location' => 'nullable|string|max:255',
            'is_online' => 'boolean',
            'meeting_url' => 'nullable|required_if:is_online,true|url|max:500',
            'max_participants' => 'nullable|integer|min:1|max:100000',
            'registration_deadline' => 'nullable|date|before_or_equal:event_date',
            'is_published' => 'boolean',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'remove_poster' => 'boolean',
        ]);

        if ($event && filled($validated['max_participants'] ?? null)) {
            $registered = $event->participants()->count();

            if ((int) $validated['max_participants'] < $registered) {
                throw ValidationException::withMessages([
                    'max_participants' => "Kuota tidak boleh lebih kecil dari {$registered} peserta yang sudah terdaftar.",
                ]);
            }
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function eventPayload(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_type' => $validated['event_type'],
            'event_date' => $validated['event_date'],
            'end_date' => $validated['end_date'] ?? null,
            'location' => $validated['location'] ?? null,
            'is_online' => (bool) ($validated['is_online'] ?? false),
            'meeting_url' => $validated['meeting_url'] ?? null,
            'max_participants' => $validated['max_participants'] ?? null,
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultEvent(): array
    {
        return [
            'title' => '',
            'description' => '',
            'event_type' => EventType::Webinar->value,
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'end_date' => '',
            'location' => '',
            'is_online' => false,
            'meeting_url' => '',
            'max_participants' => '',
            'registration_deadline' => '',
            'is_published' => false,
            'poster_url' => null,
            'participants_count' => 0,
        ];
    }

    private function registrationOpen(AlumniEvent $event, ?int $participantsCount = null): bool
    {
        if (! $event->is_published) {
            return false;
        }

        if ($event->event_date && $event->event_date->isPast()) {
            return false;
        }

        if ($event->registration_deadline && $event->registration_deadline->isPast()) {
            return false;
        }

        return ! $event->max_participants
            || ($participantsCount ?? $event->participants()->count()) < $event->max_participants;
    }

    private function deletePoster(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }
}
