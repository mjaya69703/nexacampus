<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionExamParticipant;
use App\Models\Admission\AdmissionExamSchedule;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionSelectionService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Jadwal Ujian & Seleksi PMB — memakai kit CRUD shared.
 *
 * Paritas Blade lama: sesi ujian per gelombang, kuota kursi, tautan daring,
 * serta pengelolaan peserta (pendaftaran tunggal/massal, daftar hadir,
 * komponen nilai, dan penghapusan peserta).
 */
class AdmissionExamScheduleController extends Controller
{
    public const EXAM_TYPES = [
        'written_test' => 'Tes Tertulis (Written Test)',
        'interview' => 'Wawancara (Interview)',
        'practical' => 'Ujian Praktik (Practical)',
        'portfolio' => 'Penilaian Portofolio',
        'other' => 'Lainnya (Other)',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'admission_period_id' => 'nullable|integer|exists:admission_periods,id',
            'exam_type' => ['nullable', Rule::in(array_keys(self::EXAM_TYPES))],
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,title,exam_type,exam_date,exam_time,venue,quota,registered_count,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = AdmissionExamSchedule::query()
            ->with('period:id,name,code')
            ->withCount('participants');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('venue', 'like', "%{$q}%")
                ->orWhereHas('period', fn ($period) => $period
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")));
        }

        foreach (['admission_period_id', 'exam_type'] as $filter) {
            if (filled($validated[$filter] ?? null)) {
                $query->where($filter, $validated[$filter]);
            }
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'exam_date';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'exam_time') {
            $query->orderBy('exam_time', 'desc');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $schedules = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Admission/ExamSchedule/Index', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Jadwal Ujian & Seleksi'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('admission-exam-schedule.create'),
                'update' => ActivePermission::check('admission-exam-schedule.update'),
                'delete' => ActivePermission::check('admission-exam-schedule.delete'),
                'view' => ActivePermission::check('admission-exam-schedule.view'),
                'restore' => ActivePermission::any(['admission-exam-schedule.update', 'admission-exam-schedule.delete']),
                'toggle' => ActivePermission::check('admission-exam-schedule.update'),
            ],
            'stats' => [
                'total' => AdmissionExamSchedule::count(),
                'active' => AdmissionExamSchedule::where('is_active', true)->count(),
                'participants' => AdmissionExamParticipant::count(),
                'trashed' => AdmissionExamSchedule::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($schedules->items())->values()->map(fn ($schedule, $i) => [
                    'id' => $schedule->id,
                    'no' => ($schedules->firstItem() ?? 0) + $i,
                    'title' => $schedule->title,
                    'examType' => $schedule->exam_type,
                    'examTypeLabel' => self::EXAM_TYPES[$schedule->exam_type] ?? ucfirst((string) $schedule->exam_type),
                    'period' => $schedule->period?->name,
                    'examDate' => $schedule->exam_date?->format('d M Y'),
                    'examDateRaw' => $schedule->exam_date?->toDateString(),
                    'examTime' => $schedule->exam_time?->format('H:i'),
                    'venue' => $schedule->venue,
                    'quota' => (int) $schedule->quota,
                    'registeredCount' => (int) $schedule->registered_count,
                    'participantsCount' => (int) $schedule->participants_count,
                    'isActive' => (bool) $schedule->is_active,
                    'createdAt' => $schedule->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.admission.admission-exam-schedules.show', $schedule),
                    'editUrl' => $isTrash ? null : route('admin.admission.admission-exam-schedules.edit', $schedule),
                    'deleteUrl' => route('admin.admission.admission-exam-schedules.destroy', $schedule),
                    'restoreUrl' => route('admin.admission.admission-exam-schedules.restore', $schedule->id),
                    'forceUrl' => route('admin.admission.admission-exam-schedules.force-destroy', $schedule->id),
                    'toggleUrl' => route('admin.admission.admission-exam-schedules.toggle', $schedule),
                ])->all(),
                'currentPage' => $schedules->currentPage(),
                'lastPage' => $schedules->lastPage(),
                'perPage' => $schedules->perPage(),
                'total' => $schedules->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'admission_period_id' => $validated['admission_period_id'] ?? '',
                'exam_type' => $validated['exam_type'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'periodOptions' => $this->periodOptions(),
            'examTypeOptions' => $this->examTypeOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-exam-schedules.index'),
                'create' => route('admin.admission.admission-exam-schedules.create'),
                'export' => route('admin.admission.admission-exam-schedules.export'),
                'bulkDestroy' => route('admin.admission.admission-exam-schedules.bulk-destroy'),
                'bulkRestore' => route('admin.admission.admission-exam-schedules.bulk-restore'),
                'bulkForceDestroy' => route('admin.admission.admission-exam-schedules.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Admission/ExamSchedule/Form', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Tambah Jadwal Ujian'),
            'mode' => 'create',
            'schedule' => [
                'admission_period_id' => '',
                'exam_type' => 'written_test',
                'title' => '',
                'exam_date' => now()->toDateString(),
                'exam_time' => '08:00',
                'venue' => '',
                'meeting_link' => '',
                'quota' => 0,
                'notes' => '',
                'is_active' => true,
            ],
            'periodOptions' => $this->periodOptions(),
            'examTypeOptions' => $this->examTypeOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-exam-schedules.index'),
                'submit' => route('admin.admission.admission-exam-schedules.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $schedule = AdmissionExamSchedule::create([
            ...$this->payload($validated),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.admission.admission-exam-schedules.index')
            ->with('success', 'Jadwal "'.$schedule->title.'" berhasil ditambahkan.');
    }

    public function show(Request $request, AdmissionExamSchedule $schedule): Response
    {
        $schedule->load([
            'period:id,name,code',
            'participants.application.studyProgram:id,name',
            'participants.application.scores',
        ]);

        $assignedIds = $schedule->participants->pluck('admission_application_id');

        $eligible = AdmissionApplication::query()
            ->where('admission_period_id', $schedule->admission_period_id)
            ->whereIn('status', ['submitted', 'under_review', 'waitlisted'])
            ->whereNotIn('id', $assignedIds)
            ->orderBy('full_name')
            ->get(['id', 'application_number', 'full_name'])
            ->map(fn (AdmissionApplication $application) => [
                'id' => $application->id,
                'label' => $application->application_number.' • '.$application->full_name,
            ])
            ->all();

        return Inertia::render('Admin/Admission/ExamSchedule/Show', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Detail Jadwal Ujian'),
            'can' => [
                'update' => ActivePermission::check('admission-exam-schedule.update'),
                'delete' => ActivePermission::check('admission-exam-schedule.delete'),
            ],
            'schedule' => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'examType' => $schedule->exam_type,
                'examTypeLabel' => self::EXAM_TYPES[$schedule->exam_type] ?? ucfirst((string) $schedule->exam_type),
                'period' => $schedule->period?->name,
                'examDate' => $schedule->exam_date?->format('d M Y'),
                'examTime' => $schedule->exam_time?->format('H:i'),
                'venue' => $schedule->venue,
                'meetingLink' => $schedule->meeting_link,
                'quota' => (int) $schedule->quota,
                'registeredCount' => (int) $schedule->registered_count,
                'notes' => $schedule->notes,
                'isActive' => (bool) $schedule->is_active,
                'participants' => $schedule->participants->map(function (AdmissionExamParticipant $participant) use ($schedule) {
                    $score = $participant->application?->scores
                        ->where('admission_exam_schedule_id', $schedule->id)
                        ->first();

                    return [
                        'id' => $participant->id,
                        'applicationId' => $participant->admission_application_id,
                        'name' => $participant->application?->full_name,
                        'applicationNumber' => $participant->application?->application_number,
                        'studyProgram' => $participant->application?->studyProgram?->name,
                        'attendanceStatus' => $participant->attendance_status,
                        'notes' => $participant->notes,
                        'finalScore' => $participant->application?->final_score,
                        'scoreType' => $score?->score_type ?? $schedule->exam_type,
                        'score' => $score?->score,
                        'weight' => $score?->weight ?? 100,
                        'scoreNotes' => $score?->notes,
                        'attendanceUrl' => route('admin.admission.admission-exam-schedules.participants.attendance', [$schedule, $participant]),
                        'scoreUrl' => route('admin.admission.admission-exam-schedules.participants.score', [$schedule, $participant]),
                        'removeUrl' => route('admin.admission.admission-exam-schedules.participants.destroy', [$schedule, $participant]),
                    ];
                })->values()->all(),
            ],
            'eligibleApplications' => $eligible,
            'attendanceOptions' => [
                ['id' => 'registered', 'label' => 'Terdaftar (Registered)'],
                ['id' => 'present', 'label' => 'Hadir (Present)'],
                ['id' => 'absent', 'label' => 'Tidak Hadir (Absent)'],
            ],
            'urls' => [
                'index' => route('admin.admission.admission-exam-schedules.index'),
                'edit' => route('admin.admission.admission-exam-schedules.edit', $schedule),
                'assign' => route('admin.admission.admission-exam-schedules.participants.store', $schedule),
                'assignAll' => route('admin.admission.admission-exam-schedules.participants.assign-all', $schedule),
            ],
        ]);
    }

    public function edit(Request $request, AdmissionExamSchedule $schedule): Response
    {
        return Inertia::render('Admin/Admission/ExamSchedule/Form', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Edit Jadwal Ujian'),
            'mode' => 'edit',
            'schedule' => [
                'id' => $schedule->id,
                'admission_period_id' => $schedule->admission_period_id,
                'exam_type' => $schedule->exam_type,
                'title' => $schedule->title,
                'exam_date' => $schedule->exam_date?->toDateString(),
                'exam_time' => $schedule->exam_time?->format('H:i'),
                'venue' => $schedule->venue,
                'meeting_link' => $schedule->meeting_link,
                'quota' => (int) $schedule->quota,
                'notes' => $schedule->notes,
                'is_active' => (bool) $schedule->is_active,
            ],
            'periodOptions' => $this->periodOptions(),
            'examTypeOptions' => $this->examTypeOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-exam-schedules.index'),
                'submit' => route('admin.admission.admission-exam-schedules.update', $schedule),
            ],
        ]);
    }

    public function update(Request $request, AdmissionExamSchedule $schedule)
    {
        $validated = $request->validate($this->rules());

        $schedule->update([
            ...$this->payload($validated),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.admission.admission-exam-schedules.index')
            ->with('success', 'Jadwal "'.$schedule->title.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, AdmissionExamSchedule $schedule)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $schedule->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status jadwal ujian berhasil diperbarui.');
    }

    public function destroy(AdmissionExamSchedule $schedule)
    {
        $schedule->update(['deleted_by' => auth()->id()]);
        $schedule->delete();

        return redirect()->route('admin.admission.admission-exam-schedules.index')
            ->with('success', 'Jadwal "'.$schedule->title.'" berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $schedule = AdmissionExamSchedule::withTrashed()->findOrFail($id);
        $schedule->update(['deleted_by' => null]);
        $schedule->restore();

        return back()->with('success', 'Jadwal "'.$schedule->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $schedule = AdmissionExamSchedule::withTrashed()->findOrFail($id);
        $title = $schedule->title;
        $schedule->forceDelete();

        return back()->with('success', 'Jadwal "'.$title.'" dihapus permanen.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_exam_schedules,id',
        ]);

        $count = 0;

        foreach (AdmissionExamSchedule::whereIn('id', $validated['ids'])->get() as $schedule) {
            $schedule->update(['deleted_by' => $request->user()->id]);
            $schedule->delete();
            $count++;
        }

        return back()->with('success', $count.' jadwal ujian berhasil dihapus.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_exam_schedules,id',
        ]);

        $count = 0;

        foreach (AdmissionExamSchedule::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $schedule) {
            $schedule->update(['deleted_by' => null]);
            $schedule->restore();
            $count++;
        }

        return back()->with('success', $count.' jadwal ujian berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_exam_schedules,id',
        ]);

        $count = 0;

        foreach (AdmissionExamSchedule::withTrashed()->whereIn('id', $validated['ids'])->get() as $schedule) {
            $schedule->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' jadwal ujian dihapus permanen.');
    }

    public function assignParticipant(Request $request, AdmissionExamSchedule $schedule)
    {
        $validated = $request->validate([
            'application_id' => 'required|integer|exists:admission_applications,id',
        ]);

        $application = AdmissionApplication::findOrFail($validated['application_id']);

        if ((int) $application->admission_period_id !== (int) $schedule->admission_period_id) {
            return back()->with('error', 'Pendaftar berasal dari gelombang yang berbeda dengan jadwal ujian ini.');
        }

        $exists = $schedule->participants()
            ->where('admission_application_id', $application->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Pendaftar ini sudah terdaftar pada jadwal ujian yang dipilih.');
        }

        if ($schedule->quota > 0 && $schedule->participants()->count() >= $schedule->quota) {
            return back()->with('error', 'Kuota maksimal sesi ujian ini sudah penuh.');
        }

        $schedule->participants()->create([
            'admission_application_id' => $application->id,
            'created_by' => $request->user()->id,
        ]);

        $this->syncRegisteredCount($schedule);

        return back()->with('success', 'Peserta baru berhasil ditambahkan ke jadwal ujian.');
    }

    public function assignAllFromPeriod(Request $request, AdmissionExamSchedule $schedule)
    {
        $assignedIds = $schedule->participants()->pluck('admission_application_id');

        $remainingSlots = $schedule->quota > 0
            ? max(0, $schedule->quota - $schedule->participants()->count())
            : null;

        if ($remainingSlots === 0) {
            return back()->with('error', 'Kuota maksimal sesi ujian ini sudah penuh.');
        }

        $applications = AdmissionApplication::query()
            ->where('admission_period_id', $schedule->admission_period_id)
            ->whereIn('status', ['submitted', 'under_review', 'waitlisted'])
            ->whereNotIn('id', $assignedIds)
            ->orderBy('submitted_at')
            ->orderBy('full_name')
            ->when($remainingSlots !== null, fn ($query) => $query->limit($remainingSlots))
            ->get(['id']);

        if ($applications->isEmpty()) {
            return back()->with('error', 'Tidak ada pendaftar baru dari gelombang ini yang siap ditambahkan.');
        }

        $now = now();
        $rows = $applications->map(fn (AdmissionApplication $application) => [
            'admission_exam_schedule_id' => $schedule->id,
            'admission_application_id' => $application->id,
            'attendance_status' => 'registered',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        AdmissionExamParticipant::insert($rows);
        $this->syncRegisteredCount($schedule);

        return back()->with('success', $applications->count().' peserta berhasil didaftarkan secara massal dari gelombang yang sama.');
    }

    public function updateAttendance(Request $request, AdmissionExamSchedule $schedule, AdmissionExamParticipant $participant)
    {
        abort_unless((int) $participant->admission_exam_schedule_id === (int) $schedule->id, 404);

        $validated = $request->validate([
            'attendance_status' => 'required|in:registered,present,absent',
            'notes' => 'nullable|string',
        ]);

        $participant->update([
            'attendance_status' => $validated['attendance_status'],
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Rekap kehadiran peserta berhasil diperbarui.');
    }

    public function saveScore(
        Request $request,
        AdmissionExamSchedule $schedule,
        AdmissionExamParticipant $participant,
        AdmissionSelectionService $selectionService,
    ) {
        abort_unless((int) $participant->admission_exam_schedule_id === (int) $schedule->id, 404);

        $validated = $request->validate([
            'score_type' => 'required|string|max:100',
            'score' => 'required|numeric|min:0|max:100',
            'weight' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $participant->loadMissing('application');

        $participant->application->scores()->updateOrCreate(
            [
                'admission_exam_schedule_id' => $schedule->id,
                'score_type' => $validated['score_type'],
            ],
            [
                'score' => $validated['score'],
                'weight' => $validated['weight'],
                'notes' => $validated['notes'] ?? null,
                'scored_by' => $request->user()->id,
            ],
        );

        $selectionService->recalculateFinalScore($participant->application);

        return back()->with('success', 'Skor peserta berhasil disimpan dan nilai akhir seleksi diperbarui.');
    }

    public function removeParticipant(AdmissionExamSchedule $schedule, AdmissionExamParticipant $participant)
    {
        abort_unless((int) $participant->admission_exam_schedule_id === (int) $schedule->id, 404);

        $participant->delete();
        $this->syncRegisteredCount($schedule);

        return back()->with('success', 'Peserta berhasil dihapus dari jadwal ujian ini.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = AdmissionExamSchedule::query()
            ->with('period:id,name,code')
            ->withCount('participants');

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('venue', 'like', "%{$q}%")
                ->orWhereHas('period', fn ($period) => $period
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")));
        }

        foreach (['admission_period_id', 'exam_type'] as $filter) {
            if (filled($request->query($filter))) {
                $query->where($filter, $request->query($filter));
            }
        }

        if (filled($request->query('is_active'))) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }

        $rows = $query->orderByDesc('exam_date')->orderByDesc('exam_time')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Judul Ujian', 'Tipe Ujian', 'Periode', 'Tanggal', 'Waktu', 'Lokasi / Ruangan', 'Kuota', 'Peserta', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $schedule) {
            $sheet->fromArray([
                $schedule->title,
                self::EXAM_TYPES[$schedule->exam_type] ?? $schedule->exam_type,
                $schedule->period?->name,
                $schedule->exam_date?->format('Y-m-d'),
                $schedule->exam_time?->format('H:i'),
                $schedule->venue,
                (int) $schedule->quota,
                (int) $schedule->participants_count,
                $schedule->is_active ? 'Ya' : 'Tidak',
                $schedule->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'jadwal-ujian-pmb-'.now()->format('Ymd-His').'.'.$format;
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
    private function rules(): array
    {
        return [
            'admission_period_id' => 'required|integer|exists:admission_periods,id',
            'exam_type' => ['required', Rule::in(array_keys(self::EXAM_TYPES))],
            'title' => 'required|string|max:255',
            'exam_date' => 'required|date',
            'exam_time' => 'required|date_format:H:i',
            'venue' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:255',
            'quota' => 'required|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'admission_period_id' => $validated['admission_period_id'],
            'exam_type' => $validated['exam_type'],
            'title' => $validated['title'],
            'exam_date' => $validated['exam_date'],
            'exam_time' => $validated['exam_time'],
            'venue' => $validated['venue'] ?? null,
            'meeting_link' => $validated['meeting_link'] ?? null,
            'quota' => $validated['quota'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    private function syncRegisteredCount(AdmissionExamSchedule $schedule): void
    {
        $schedule->update([
            'registered_count' => $schedule->participants()->count(),
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function periodOptions(): array
    {
        return AdmissionPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn (AdmissionPeriod $period) => [
                'id' => $period->id,
                'label' => $period->name.' ('.$period->code.')',
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function examTypeOptions(): array
    {
        return collect(self::EXAM_TYPES)
            ->map(fn (string $label, string $value) => ['id' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
