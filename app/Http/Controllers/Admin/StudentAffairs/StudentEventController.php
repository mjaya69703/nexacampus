<?php

namespace App\Http\Controllers\Admin\StudentAffairs;

use App\Enums\EventBudgetCategory;
use App\Enums\EventDocumentCategory;
use App\Enums\EventRegistrationStatus;
use App\Enums\StudentEventCategory;
use App\Enums\StudentEventStatus;
use App\Http\Controllers\Controller;
use App\Models\StudentAffairs\StudentEvent;
use App\Models\StudentAffairs\StudentEventBudget;
use App\Models\StudentAffairs\StudentEventDocument;
use App\Models\StudentAffairs\StudentEventEvaluation;
use App\Models\StudentAffairs\StudentEventRegistration;
use App\Models\StudentAffairs\StudentOrganization;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\StudentAffairs\StudentPointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentEventController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:30',
            'status' => 'nullable|string|max:20',
            'organization' => 'nullable|integer|exists:student_organizations,id',
            'sort' => 'nullable|in:title,starts_at,status,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentEvent::query()->with(['organization:id,name'])->withCount([
            'registrations as registered_count' => fn ($sub) => $sub->where('status', '!=', EventRegistrationStatus::CANCELLED->value),
            'registrations as attended_count' => fn ($sub) => $sub->where('status', EventRegistrationStatus::ATTENDED->value),
        ]);

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

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['organization'] ?? null)) {
            $query->where('student_organization_id', $validated['organization']);
        }

        $sort = $validated['sort'] ?? 'starts_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $events = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentAffairs/Events/Index', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Event Mahasiswa'),
            'can' => [
                'create' => ActivePermission::check('student-event.create'),
                'update' => ActivePermission::check('student-event.update'),
                'delete' => ActivePermission::check('student-event.delete'),
                'publish' => ActivePermission::check('student-event.publish'),
            ],
            'stats' => [
                'total' => StudentEvent::count(),
                'published' => StudentEvent::where('status', StudentEventStatus::PUBLISHED->value)->count(),
                'upcoming' => StudentEvent::whereIn('status', [StudentEventStatus::PUBLISHED->value, StudentEventStatus::ONGOING->value])
                    ->where('starts_at', '>=', now())->count(),
                'completed' => StudentEvent::where('status', StudentEventStatus::COMPLETED->value)->count(),
            ],
            'data' => [
                'rows' => collect($events->items())->values()->map(fn (StudentEvent $event, int $i) => [
                    'id' => $event->id,
                    'no' => ($events->firstItem() ?? 0) + $i,
                    'title' => $event->title,
                    'category' => $event->category->value,
                    'categoryLabel' => $event->category->label(),
                    'categoryTone' => $event->category->tone(),
                    'organization' => $event->organization?->name,
                    'startsAt' => $event->starts_at?->format('d M Y H:i'),
                    'location' => $event->location,
                    'points' => $event->points,
                    'quota' => $event->quota,
                    'registered' => (int) $event->registered_count,
                    'attended' => (int) $event->attended_count,
                    'status' => $event->status->value,
                    'statusLabel' => $event->status->label(),
                    'statusTone' => $event->status->tone(),
                    'isFeatured' => (bool) $event->is_featured,
                    'showUrl' => route('admin.student-affairs.events.show', $event->id),
                    'editUrl' => route('admin.student-affairs.events.edit', $event->id),
                    'deleteUrl' => route('admin.student-affairs.events.destroy', $event->id),
                ])->all(),
                'currentPage' => $events->currentPage(),
                'lastPage' => $events->lastPage(),
                'perPage' => $events->perPage(),
                'total' => $events->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'status' => $validated['status'] ?? '',
                'organization' => $validated['organization'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'categories' => StudentEventCategory::options(),
                'statuses' => StudentEventStatus::options(),
                'organizations' => StudentOrganization::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($organization) => ['id' => $organization->id, 'name' => $organization->name])->all(),
            ],
            'urls' => [
                'index' => route('admin.student-affairs.events.index'),
                'create' => route('admin.student-affairs.events.create'),
                'bulkDestroy' => route('admin.student-affairs.events.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/StudentAffairs/Events/Form', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Tambah Event'),
            'mode' => 'create',
            'event' => [
                'id' => null,
                'title' => '',
                'category' => StudentEventCategory::SEMINAR->value,
                'student_organization_id' => '',
                'organizer' => '',
                'location' => '',
                'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
                'ends_at' => '',
                'registration_deadline' => now()->addDays(5)->format('Y-m-d\TH:i'),
                'quota' => '',
                'points' => 10,
                'status' => StudentEventStatus::DRAFT->value,
                'is_featured' => false,
                'description' => '',
                'cover_url' => null,
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.student-affairs.events.index'),
                'submit' => route('admin.student-affairs.events.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $coverPath = $this->storeCover($request);

        StudentEvent::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title']),
            'cover_path' => $coverPath,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.student-affairs.events.index')
            ->with('success', 'Event berhasil ditambahkan.');
    }

    public function edit(Request $request, int $id): Response
    {
        $event = StudentEvent::findOrFail($id);

        return Inertia::render('Admin/StudentAffairs/Events/Form', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Edit Event'),
            'mode' => 'edit',
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'category' => $event->category->value,
                'student_organization_id' => $event->student_organization_id ? (string) $event->student_organization_id : '',
                'organizer' => $event->organizer ?? '',
                'location' => $event->location ?? '',
                'starts_at' => $event->starts_at?->format('Y-m-d\TH:i') ?? '',
                'ends_at' => $event->ends_at?->format('Y-m-d\TH:i') ?? '',
                'registration_deadline' => $event->registration_deadline?->format('Y-m-d\TH:i') ?? '',
                'quota' => $event->quota ?? '',
                'points' => $event->points,
                'status' => $event->status->value,
                'is_featured' => (bool) $event->is_featured,
                'description' => $event->description ?? '',
                'cover_url' => $event->cover_path ? Storage::disk('public')->url($event->cover_path) : null,
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.student-affairs.events.index'),
                'submit' => route('admin.student-affairs.events.update', $event->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $event = StudentEvent::findOrFail($id);
        $validated = $this->validatePayload($request);

        if ($request->boolean('remove_cover') && $event->cover_path) {
            Storage::disk('public')->delete($event->cover_path);
            $validated['cover_path'] = null;
        }

        if ($newCover = $this->storeCover($request)) {
            if ($event->cover_path) {
                Storage::disk('public')->delete($event->cover_path);
            }

            $validated['cover_path'] = $newCover;
        }

        $event->update([
            ...$validated,
            'slug' => $event->title === $validated['title'] ? $event->slug : $this->uniqueSlug($validated['title'], $event->id),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.student-affairs.events.index')
            ->with('success', 'Event berhasil diperbarui.');
    }

    public function show(Request $request, int $id): Response
    {
        $event = StudentEvent::with([
            'organization:id,name',
            'creator:id,first_name,last_name',
            'documents' => fn ($query) => $query->orderByDesc('created_at'),
            'documents.uploader:id,first_name,last_name',
            'budgets' => fn ($query) => $query->orderBy('category')->orderBy('id'),
            'evaluations' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
            'evaluations.user:id,first_name,last_name',
        ])
            ->withCount([
                'registrations as registered_count' => fn ($sub) => $sub->where('status', '!=', EventRegistrationStatus::CANCELLED->value),
                'registrations as attended_count' => fn ($sub) => $sub->where('status', EventRegistrationStatus::ATTENDED->value),
                'registrations as cancelled_count' => fn ($sub) => $sub->where('status', EventRegistrationStatus::CANCELLED->value),
            ])
            ->findOrFail($id);

        $registrations = StudentEventRegistration::with([
            'user:id,first_name,last_name',
            'user.studentProfile:id,user_id,study_program_id,nim',
            'user.studentProfile.studyProgram:id,name',
        ])
            ->where('student_event_id', $event->id)
            ->orderByDesc('registered_at')
            ->get()
            ->map(fn (StudentEventRegistration $registration) => [
                'id' => $registration->id,
                'student' => $registration->user?->name ?? '-',
                'nim' => $registration->user?->studentProfile?->nim,
                'program' => $registration->user?->studentProfile?->studyProgram?->name,
                'status' => $registration->status->value,
                'statusLabel' => $registration->status->label(),
                'statusTone' => $registration->status->tone(),
                'registeredAt' => $registration->registered_at?->format('d M Y H:i'),
                'attendedAt' => $registration->attended_at?->format('d M Y H:i'),
                'pointsAwarded' => (int) $registration->points_awarded,
                'attendanceUrl' => route('admin.student-affairs.events.attendance', [$event->id, $registration->id]),
            ]);

        $documents = $event->documents->map(fn (StudentEventDocument $document) => [
            'id' => $document->id,
            'title' => $document->title,
            'category' => $document->category->value,
            'categoryLabel' => $document->category->label(),
            'categoryTone' => $document->category->tone(),
            'description' => $document->description,
            'originalName' => $document->original_name,
            'fileSize' => $document->fileSizeLabel(),
            'isImage' => $document->isImage(),
            'fileUrl' => $document->isImage() ? Storage::disk('public')->url($document->file_path) : null,
            'uploader' => $document->uploader?->name,
            'createdAt' => $document->created_at?->format('d M Y H:i'),
            'downloadUrl' => route('admin.student-affairs.events.documents.download', [$event->id, $document->id]),
            'deleteUrl' => route('admin.student-affairs.events.documents.destroy', [$event->id, $document->id]),
        ])->values();

        $budgets = $event->budgets->map(fn (StudentEventBudget $budget) => [
            'id' => $budget->id,
            'itemName' => $budget->item_name,
            'category' => $budget->category->value,
            'categoryLabel' => $budget->category->label(),
            'categoryTone' => $budget->category->tone(),
            'plannedAmount' => (float) $budget->planned_amount,
            'realizedAmount' => (float) $budget->realized_amount,
            'variance' => $budget->variance(),
            'notes' => $budget->notes,
            'updateUrl' => route('admin.student-affairs.events.budgets.update', [$event->id, $budget->id]),
            'deleteUrl' => route('admin.student-affairs.events.budgets.destroy', [$event->id, $budget->id]),
        ])->values();

        $plannedTotal = (float) $event->budgets->sum('planned_amount');
        $realizedTotal = (float) $event->budgets->sum('realized_amount');

        $evaluations = $event->evaluations->map(fn (StudentEventEvaluation $evaluation) => [
            'id' => $evaluation->id,
            'student' => $evaluation->is_anonymous ? 'Anonim' : ($evaluation->user?->name ?? 'Mahasiswa'),
            'rating' => (int) $evaluation->rating,
            'organizationRating' => $evaluation->organization_rating !== null ? (int) $evaluation->organization_rating : null,
            'benefitRating' => $evaluation->benefit_rating !== null ? (int) $evaluation->benefit_rating : null,
            'feedback' => $evaluation->feedback,
            'suggestions' => $evaluation->suggestions,
            'isAnonymous' => (bool) $evaluation->is_anonymous,
            'createdAt' => $evaluation->created_at?->format('d M Y H:i'),
        ])->values();

        $evaluationCount = $evaluations->count();
        $attendedCount = (int) $event->attended_count;

        $distribution = collect([1, 2, 3, 4, 5])
            ->mapWithKeys(fn (int $rating) => [$rating => $evaluations->where('rating', $rating)->count()])
            ->all();

        return Inertia::render('Admin/StudentAffairs/Events/Show', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Detail Event'),
            'can' => [
                'update' => ActivePermission::check('student-event.update'),
                'delete' => ActivePermission::check('student-event.delete'),
                'publish' => ActivePermission::check('student-event.publish'),
            ],
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'categoryLabel' => $event->category->label(),
                'categoryTone' => $event->category->tone(),
                'description' => $event->description,
                'organization' => $event->organization?->name,
                'organizer' => $event->organizer,
                'location' => $event->location,
                'startsAt' => $event->starts_at?->format('d M Y H:i'),
                'endsAt' => $event->ends_at?->format('d M Y H:i'),
                'registrationDeadline' => $event->registration_deadline?->format('d M Y H:i'),
                'quota' => $event->quota,
                'points' => $event->points,
                'status' => $event->status->value,
                'statusLabel' => $event->status->label(),
                'statusTone' => $event->status->tone(),
                'isFeatured' => (bool) $event->is_featured,
                'coverUrl' => $event->cover_path ? Storage::disk('public')->url($event->cover_path) : null,
                'creator' => $event->creator?->name,
                'createdAt' => $event->created_at?->format('d M Y H:i'),
                'updatedAt' => $event->updated_at?->format('d M Y H:i'),
            ],
            'stats' => [
                'registered' => (int) $event->registered_count,
                'attended' => $attendedCount,
                'cancelled' => (int) $event->cancelled_count,
                'quota' => $event->quota,
            ],
            'registrations' => $registrations,
            'documents' => $documents,
            'budgets' => $budgets,
            'budgetSummary' => [
                'planned' => $plannedTotal,
                'realized' => $realizedTotal,
                'variance' => $plannedTotal - $realizedTotal,
                'absorption' => $plannedTotal > 0 ? round($realizedTotal / $plannedTotal * 100, 1) : 0.0,
                'items' => $budgets->count(),
            ],
            'evaluations' => $evaluations,
            'evaluationSummary' => [
                'count' => $evaluationCount,
                'average' => $evaluationCount > 0 ? round($evaluations->avg('rating'), 2) : null,
                'averageOrganization' => $evaluationCount > 0 ? round($evaluations->whereNotNull('organizationRating')->avg('organizationRating'), 2) : null,
                'averageBenefit' => $evaluationCount > 0 ? round($evaluations->whereNotNull('benefitRating')->avg('benefitRating'), 2) : null,
                'distribution' => $distribution,
                'responseRate' => $attendedCount > 0 ? (int) round($evaluationCount / $attendedCount * 100) : 0,
                'attended' => $attendedCount,
            ],
            'options' => [
                'registrationStatuses' => EventRegistrationStatus::options(),
                'documentCategories' => EventDocumentCategory::options(),
                'budgetCategories' => EventBudgetCategory::options(),
            ],
            'urls' => [
                'index' => route('admin.student-affairs.events.index'),
                'edit' => route('admin.student-affairs.events.edit', $event->id),
                'delete' => route('admin.student-affairs.events.destroy', $event->id),
                'publish' => route('admin.student-affairs.events.publish', $event->id),
                'complete' => route('admin.student-affairs.events.complete', $event->id),
                'cancel' => route('admin.student-affairs.events.cancel', $event->id),
                'documentsStore' => route('admin.student-affairs.events.documents.store', $event->id),
                'budgetsStore' => route('admin.student-affairs.events.budgets.store', $event->id),
            ],
        ]);
    }

    public function publish(Request $request, int $id)
    {
        $event = StudentEvent::findOrFail($id);

        $event->update([
            'status' => StudentEventStatus::PUBLISHED->value,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Event "'.$event->title.'" dipublikasikan.');
    }

    public function complete(Request $request, int $id)
    {
        $event = StudentEvent::findOrFail($id);

        $event->update([
            'status' => StudentEventStatus::COMPLETED->value,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Event "'.$event->title.'" ditandai selesai.');
    }

    public function cancel(Request $request, int $id)
    {
        $event = StudentEvent::findOrFail($id);

        $event->update([
            'status' => StudentEventStatus::CANCELLED->value,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Event "'.$event->title.'" dibatalkan.');
    }

    public function attendance(Request $request, int $id, int $registrationId, StudentPointService $points)
    {
        $event = StudentEvent::findOrFail($id);
        $registration = StudentEventRegistration::where('student_event_id', $event->id)->findOrFail($registrationId);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(EventRegistrationStatus::options()))],
        ]);

        $status = EventRegistrationStatus::from($validated['status']);

        $registration->update([
            'status' => $status->value,
            'attended_at' => $status === EventRegistrationStatus::ATTENDED ? ($registration->attended_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ]);

        if ($status === EventRegistrationStatus::ATTENDED) {
            $points->awardEventAttendance($registration->refresh());
        } else {
            $points->revokeEventAttendance($registration);
        }

        return back()->with('success', 'Kehadiran '.$registration->user?->name.' diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $event = StudentEvent::findOrFail($id);

        if ($event->cover_path) {
            Storage::disk('public')->delete($event->cover_path);
        }

        foreach ($event->documents()->get() as $document) {
            Storage::disk('public')->delete($document->file_path);
        }

        $event->update(['deleted_by' => $request->user()->id]);
        $event->delete();

        return redirect()->route('admin.student-affairs.events.index')
            ->with('success', 'Event berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:student_events,id',
        ]);

        $events = StudentEvent::whereIn('id', $validated['ids'])->get();

        foreach ($events as $event) {
            if ($event->cover_path) {
                Storage::disk('public')->delete($event->cover_path);
            }

            foreach ($event->documents()->get() as $document) {
                Storage::disk('public')->delete($document->file_path);
            }

            $event->update(['deleted_by' => $request->user()->id]);
            $event->delete();
        }

        return back()->with('success', $events->count().' event berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(StudentEventCategory::options()))],
            'student_organization_id' => ['nullable', 'integer', 'exists:student_organizations,id'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'points' => ['required', 'integer', 'min:0', 'max:1000'],
            'status' => ['required', Rule::in(array_keys(StudentEventStatus::options()))],
            'is_featured' => ['boolean'],
            'description' => ['nullable', 'string'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'remove_cover' => ['boolean'],
        ]);

        $validated['student_organization_id'] = $validated['student_organization_id'] ?? null;
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        $validated['points'] = (int) $validated['points'];
        unset($validated['cover'], $validated['remove_cover']);

        return $validated;
    }

    private function storeCover(Request $request): ?string
    {
        if (! $request->hasFile('cover')) {
            return null;
        }

        return $request->file('cover')->store('student-affairs/events', 'public');
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $counter = 2;

        while (StudentEvent::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => StudentEventCategory::options(),
            'statuses' => StudentEventStatus::options(),
            'organizations' => StudentOrganization::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($organization) => ['id' => $organization->id, 'name' => $organization->name])->all(),
        ];
    }
}
