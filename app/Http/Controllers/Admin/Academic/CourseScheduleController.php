<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\LecturerProfile;
use App\Models\Campus\Room;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Jadwal Kuliah — memakai kit CRUD shared.
 *
 * Paritas Blade lama + 2 pengaman baru: dosen jadwal wajib terdaftar
 * di offering (dulu error pasca-submit), bentrok ruang & dosen
 * (hari + jam irisan) ditolak dengan pesan jelas.
 */
class CourseScheduleController extends Controller
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    public const SESSION_TYPES = ['Lecture', 'Practicum', 'Tutorial', 'Exam', 'Custom'];
    public const DELIVERY_MODES = ['Offline', 'Online', 'Hybrid'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'offering' => 'nullable|integer|exists:course_offerings,id',
            'day' => 'nullable|string|max:10',
            'room' => 'nullable|integer|exists:rooms,id',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = CourseSchedule::query()
            ->with(['courseOffering.course:id,code,name', 'lecturerProfile.user:id,first_name,last_name', 'room.building:id,name', 'room:id,name']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->whereHas('courseOffering.course', fn ($c) => $c
                    ->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%"))
                ->orWhereHas('lecturerProfile.user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")));
        }

        if (filled($validated['offering'] ?? null)) {
            $query->where('course_offering_id', $validated['offering']);
        }

        if (filled($validated['day'] ?? null)) {
            $query->where('day_of_week', $validated['day']);
        }

        if (filled($validated['room'] ?? null)) {
            $query->where('room_id', $validated['room']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $schedules = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/CourseSchedule/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Jadwal Kuliah'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('course-schedule.create'),
                'update' => ActivePermission::check('course-schedule.update'),
                'delete' => ActivePermission::check('course-schedule.delete'),
                'view' => ActivePermission::check('course-schedule.view'),
                'restore' => ActivePermission::any(['course-schedule.update', 'course-schedule.delete']),
                'toggle' => ActivePermission::check('course-schedule.update'),
            ],
            'stats' => [
                'total' => CourseSchedule::count(),
                'active' => CourseSchedule::where('is_active', true)->count(),
                'offline' => CourseSchedule::where('delivery_mode', 'Offline')->count(),
                'online' => CourseSchedule::where('delivery_mode', 'Online')->count(),
                'trashed' => CourseSchedule::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($schedules->items())->values()->map(fn ($s, $i) => [
                    'id' => $s->id,
                    'no' => ($schedules->firstItem() ?? 0) + $i,
                    'course' => $s->courseOffering?->course ? "{$s->courseOffering->course->code} - {$s->courseOffering->course->name}" : '-',
                    'offeringId' => $s->course_offering_id,
                    'offeringUrl' => route('admin.academic.course-offerings.show', $s->course_offering_id),
                    'lecturer' => $s->lecturerProfile ? trim(($s->lecturerProfile->user?->first_name ?? '').' '.($s->lecturerProfile->user?->last_name ?? '')) : 'Jadwal Umum',
                    'day' => $s->day_of_week,
                    'time' => ($s->start_time?->format('H:i') ?? '?').'–'.($s->end_time?->format('H:i') ?? '?'),
                    'room' => $s->room ? (($s->room->building?->name ? $s->room->building->name.' · ' : '').$s->room->name) : '-',
                    'type' => $s->session_type,
                    'mode' => $s->delivery_mode,
                    'isActive' => (bool) $s->is_active,
                    'createdAt' => $s->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.academic.course-schedules.show', $s->id),
                    'editUrl' => $isTrash ? null : route('admin.academic.course-schedules.edit', $s->id),
                    'deleteUrl' => route('admin.academic.course-schedules.destroy', $s->id),
                    'restoreUrl' => route('admin.academic.course-schedules.restore', $s->id),
                    'forceUrl' => route('admin.academic.course-schedules.force-destroy', $s->id),
                    'toggleUrl' => route('admin.academic.course-schedules.toggle', $s->id),
                ])->all(),
                'currentPage' => $schedules->currentPage(),
                'lastPage' => $schedules->lastPage(),
                'perPage' => $schedules->perPage(),
                'total' => $schedules->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'offering' => $validated['offering'] ?? '',
                'day' => $validated['day'] ?? '',
                'room' => $validated['room'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'dayOptions' => self::DAYS,
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.academic.course-schedules.index'),
                'create' => route('admin.academic.course-schedules.create'),
                'export' => route('admin.academic.course-schedules.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'course-schedules']),
                'importTemplate' => route('admin.academic.course-schedules.import-template'),
                'importSubmit' => route('admin.academic.course-schedules.import'),
                'bulkDestroy' => route('admin.academic.course-schedules.bulk-destroy'),
                'bulkRestore' => route('admin.academic.course-schedules.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.course-schedules.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $presetId = (int) $request->query('offering');
        $preset = $presetId > 0 ? CourseOffering::query()->with('course:id,code,name')->find($presetId) : null;

        return Inertia::render('Admin/Academic/CourseSchedule/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Jadwal Kuliah'),
            'mode' => 'create',
            'schedule' => [
                'course_offering_id' => $preset?->id ?? '',
                'lecturer_profile_id' => '', 'room_id' => '', 'day_of_week' => 'Monday',
                'start_time' => '', 'end_time' => '', 'session_type' => 'Lecture',
                'delivery_mode' => 'Offline', 'meeting_link' => '', 'notes' => '',
                'is_active' => true,
            ],
            'options' => $this->formOptions($preset?->id),
            'presetOffering' => $preset ? [
                'id' => $preset->id,
                'label' => trim(($preset->course ? "{$preset->course->code} - {$preset->course->name}" : 'Kelas')." ({$preset->label})"),
            ] : null,
            'days' => self::DAYS,
            'sessionTypes' => self::SESSION_TYPES,
            'deliveryModes' => self::DELIVERY_MODES,
            'return' => $request->query('return', ''),
            'urls' => [
                'index' => route('admin.academic.course-schedules.index'),
                'submit' => route('admin.academic.course-schedules.store'),
                'searchOfferings' => route('admin.academic.course-schedules.search-offerings'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        if ($conflict = $this->findConflict(null, $validated)) {
            return back()->with('error', $conflict)->withInput();
        }

        $schedule = CourseSchedule::create(array_merge($validated, ['created_by' => $request->user()->id]));

        return $this->afterSaveRedirect($request, 'Jadwal berhasil ditambahkan.', $schedule);
    }

    public function show(Request $request, int $id): Response
    {
        $schedule = CourseSchedule::query()
            ->with(['courseOffering.course:id,code,name', 'courseOffering.academicYear:id,name', 'lecturerProfile.user', 'room.building'])
            ->findOrFail($id);

        return Inertia::render('Admin/Academic/CourseSchedule/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Detail Jadwal Kuliah'),
            'schedule' => [
                'id' => $schedule->id,
                'course' => $schedule->courseOffering?->course ? "{$schedule->courseOffering->course->code} - {$schedule->courseOffering->course->name}" : '-',
                'offering' => $schedule->courseOffering?->label,
                'year' => $schedule->courseOffering?->academicYear?->name,
                'lecturer' => $schedule->lecturerProfile ? trim(($schedule->lecturerProfile->user?->first_name ?? '').' '.($schedule->lecturerProfile->user?->last_name ?? '')) : 'Jadwal Umum',
                'day' => $schedule->day_of_week,
                'time' => ($schedule->start_time?->format('H:i') ?? '?').'–'.($schedule->end_time?->format('H:i') ?? '?'),
                'room' => $schedule->room ? (($schedule->room->building?->name ? $schedule->room->building->name.' · ' : '').$schedule->room->name) : '-',
                'type' => $schedule->session_type,
                'mode' => $schedule->delivery_mode,
                'meetingLink' => $schedule->meeting_link,
                'notes' => $schedule->notes,
                'isActive' => (bool) $schedule->is_active,
            ],
            'canUpdate' => ActivePermission::check('course-schedule.update'),
            'urls' => [
                'index' => route('admin.academic.course-schedules.index'),
                'edit' => route('admin.academic.course-schedules.edit', $schedule),
                'offering' => route('admin.academic.course-offerings.show', $schedule->course_offering_id),
            ],
        ]);
    }

    public function edit(Request $request, CourseSchedule $courseSchedule): Response
    {
        $offering = $courseSchedule->courseOffering()->with('course:id,code,name')->first();

        return Inertia::render('Admin/Academic/CourseSchedule/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Jadwal Kuliah'),
            'mode' => 'edit',
            'schedule' => [
                'id' => $courseSchedule->id,
                'course_offering_id' => $courseSchedule->course_offering_id,
                'lecturer_profile_id' => $courseSchedule->lecturer_profile_id ?? '',
                'room_id' => $courseSchedule->room_id ?? '',
                'day_of_week' => $courseSchedule->day_of_week,
                'start_time' => $courseSchedule->start_time?->format('H:i') ?? '',
                'end_time' => $courseSchedule->end_time?->format('H:i') ?? '',
                'session_type' => $courseSchedule->session_type,
                'delivery_mode' => $courseSchedule->delivery_mode,
                'meeting_link' => $courseSchedule->meeting_link ?? '',
                'notes' => $courseSchedule->notes ?? '',
                'is_active' => (bool) $courseSchedule->is_active,
            ],
            'options' => $this->formOptions($courseSchedule->course_offering_id),
            'presetOffering' => $offering ? [
                'id' => $offering->id,
                'label' => trim(($offering->course ? "{$offering->course->code} - {$offering->course->name}" : 'Kelas')." ({$offering->label})"),
            ] : null,
            'days' => self::DAYS,
            'sessionTypes' => self::SESSION_TYPES,
            'deliveryModes' => self::DELIVERY_MODES,
            'return' => $request->query('return', ''),
            'urls' => [
                'index' => route('admin.academic.course-schedules.index'),
                'submit' => route('admin.academic.course-schedules.update', $courseSchedule),
                'searchOfferings' => route('admin.academic.course-schedules.search-offerings'),
            ],
        ]);
    }

    public function update(Request $request, CourseSchedule $courseSchedule)
    {
        $validated = $this->validatePayload($request, $courseSchedule->course_offering_id);

        if ($conflict = $this->findConflict($courseSchedule->id, $validated)) {
            return back()->with('error', $conflict)->withInput();
        }

        $courseSchedule->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return $this->afterSaveRedirect($request, 'Jadwal berhasil diperbarui.', $courseSchedule);
    }

    public function toggle(Request $request, CourseSchedule $courseSchedule)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $courseSchedule->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status jadwal berhasil diperbarui.');
    }

    public function destroy(CourseSchedule $courseSchedule)
    {
        $courseSchedule->update(['deleted_by' => auth()->id()]);
        $courseSchedule->delete();

        return redirect()->route('admin.academic.course-schedules.index')
            ->with('success', 'Jadwal berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:course_schedules,id',
        ]);

        $count = CourseSchedule::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' jadwal berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $schedule = CourseSchedule::withTrashed()->findOrFail($id);
        $schedule->restore();

        return back()->with('success', 'Jadwal berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $schedule = CourseSchedule::withTrashed()->findOrFail($id);
        $schedule->forceDelete();

        return back()->with('success', 'Jadwal dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:course_schedules,id',
        ]);

        $count = 0;

        foreach (CourseSchedule::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $schedule) {
            $schedule->restore();
            $count++;
        }

        return back()->with('success', $count.' jadwal berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:course_schedules,id',
        ]);

        $count = 0;

        foreach (CourseSchedule::withTrashed()->whereIn('id', $validated['ids'])->get() as $schedule) {
            $schedule->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' jadwal dihapus permanen.');
    }

    /**
     * Pencarian async penawaran kelas (ganti dropdown tak terbatas).
     */
    public function searchOfferings(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $offerings = CourseOffering::query()
            ->with(['course:id,code,name', 'academicYear:id,name'])
            ->when($q !== '', fn ($query) => $query
                ->where('label', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhereHas('course', fn ($c) => $c
                    ->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")))
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'label' => trim(($o->course ? "{$o->course->code} - {$o->course->name}" : 'Kelas')." ({$o->label}) ".($o->academicYear?->name ?? '')),
            ])
            ->all();

        return response()->json(['options' => $offerings]);
    }

    /**
     * Dosen suatu offering untuk dropdown terkendali (ganti validasi pasca-submit).
     */
    public function offeringLecturers(CourseOffering $courseOffering)
    {
        $lecturers = $courseOffering->lecturers()
            ->with('lecturerProfile.user:id,first_name,last_name')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->lecturer_profile_id,
                'label' => trim(($l->lecturerProfile?->user?->name ?? '-')." ({$l->role})"),
            ])
            ->all();

        return response()->json(['options' => $lecturers]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = CourseSchedule::query()
            ->with(['courseOffering.course:id,code,name', 'lecturerProfile.user', 'room.building']);

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('course_schedules.id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Kelas', 'Dosen', 'Hari', 'Mulai', 'Selesai', 'Ruang', 'Tipe', 'Mode', 'Aktif']], null, 'A1');

        foreach ($rows as $i => $schedule) {
            $sheet->fromArray([
                $schedule->courseOffering?->course ? "{$schedule->courseOffering->course->code} - {$schedule->courseOffering->course->name}" : null,
                $schedule->lecturerProfile?->user?->name,
                $schedule->day_of_week,
                $schedule->start_time?->format('H:i'),
                $schedule->end_time?->format('H:i'),
                $schedule->room?->name,
                $schedule->session_type,
                $schedule->delivery_mode,
                $schedule->is_active ? 'Ya' : 'Tidak',
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'course-schedules-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['offering_id', 'day_of_week', 'start_time', 'end_time', 'room_code', 'lecturer_nidn', 'session_type', 'delivery_mode', 'is_active']], null, 'A1');
        $sheet->fromArray([[1, 'Monday', '08:00', '10:00', 'R101', '1001', 'Lecture', 'Offline', '1']], null, 'A2');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-course-schedules.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable) {
            return back()->with('error', 'File tidak bisa dibaca. Gunakan template yang disediakan.');
        }

        $rows = $spreadsheet->getActiveSheet()->toArray();
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows ? array_shift($rows) : []);
        $missing = array_diff(['offering_id', 'day_of_week', 'start_time', 'end_time'], $header);

        if (! empty($missing)) {
            return back()->with('error', 'Header wajib hilang: '.implode(', ', $missing).'. Unduh template terbaru.');
        }

        $rows = array_values(array_filter(
            $rows,
            fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        ));

        if (empty($rows)) {
            return back()->with('error', 'File tidak berisi data.');
        }

        if (count($rows) > 500) {
            return back()->with('error', 'Maksimal 500 baris per import.');
        }

        $roomMap = Room::pluck('id', 'code')->all();
        $lecturerMap = LecturerProfile::pluck('id', 'nidn')->all();
        $accepted = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $data = array_combine(
                $header,
                array_pad(array_slice($row, 0, count($header)), count($header), null)
            );
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
            $rowErrors = [];

            $offering = ! empty($data['offering_id']) ? CourseOffering::find($data['offering_id']) : null;

            if (! $offering) {
                $rowErrors[] = 'offering_id tidak ditemukan';
            }

            $day = $data['day_of_week'] ?? null;

            if (! in_array($day, self::DAYS, true)) {
                $rowErrors[] = 'day_of_week harus nama hari Inggris (Monday–Sunday)';
            }

            $start = $this->parseImportTime($data['start_time'] ?? null);
            $end = $this->parseImportTime($data['end_time'] ?? null);

            if ($start === null) {
                $rowErrors[] = 'start_time tidak valid (H:i)';
            }

            if ($end === null) {
                $rowErrors[] = 'end_time tidak valid (H:i)';
            } elseif ($start !== null && $end <= $start) {
                $rowErrors[] = 'end_time harus setelah start_time';
            }

            $roomId = null;
            $roomCode = $data['room_code'] ?? null;

            if ($roomCode !== null && $roomCode !== '') {
                if (! isset($roomMap[$roomCode])) {
                    $rowErrors[] = "ruang \"{$roomCode}\" tidak ditemukan";
                } else {
                    $roomId = $roomMap[$roomCode];
                }
            }

            $lecturerId = null;
            $lecturerNidn = $data['lecturer_nidn'] ?? null;

            if ($lecturerNidn !== null && $lecturerNidn !== '') {
                if (! isset($lecturerMap[$lecturerNidn])) {
                    $rowErrors[] = "dosen NIDN \"{$lecturerNidn}\" tidak ditemukan";
                } elseif ($offering && ! $offering->lecturers()->where('lecturer_profile_id', $lecturerMap[$lecturerNidn])->exists()) {
                    $rowErrors[] = 'dosen belum terdaftar di kelas ini';
                } else {
                    $lecturerId = $lecturerMap[$lecturerNidn];
                }
            }

            $sessionType = ($data['session_type'] ?? null) === '' || ($data['session_type'] ?? null) === null ? 'Lecture' : $data['session_type'];

            if (! in_array($sessionType, self::SESSION_TYPES, true)) {
                $rowErrors[] = 'session_type tidak valid';
            }

            $mode = ($data['delivery_mode'] ?? null) === '' || ($data['delivery_mode'] ?? null) === null ? 'Offline' : $data['delivery_mode'];

            if (! in_array($mode, self::DELIVERY_MODES, true)) {
                $rowErrors[] = 'delivery_mode harus Offline/Online/Hybrid';
            }

            if (empty($rowErrors) && $start !== null && $end !== null) {
                $conflict = $this->findConflict(null, [
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'room_id' => $roomId,
                    'lecturer_profile_id' => $lecturerId,
                ]) ?? $this->findFileConflict($accepted, $day, $start, $end, $roomId, $lecturerId);

                if ($conflict) {
                    $rowErrors[] = $conflict;
                }
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $accepted[] = [
                'day_of_week' => $day, 'start_time' => $start, 'end_time' => $end,
                'room_id' => $roomId, 'lecturer_profile_id' => $lecturerId,
            ];
            $valid[] = [
                'course_offering_id' => $offering->id,
                'lecturer_profile_id' => $lecturerId,
                'room_id' => $roomId,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'session_type' => $sessionType,
                'delivery_mode' => $mode,
                'meeting_link' => $data['meeting_link'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.course-schedules.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        CourseSchedule::insert($valid);

        return redirect()->route('admin.academic.course-schedules.index')
            ->with('success', count($valid).' jadwal berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    /**
     * Bentrok antar-baris dalam file yang sama.
     */
    private function findFileConflict(array $accepted, string $day, string $start, string $end, ?int $roomId, ?int $lecturerId): ?string
    {
        foreach ($accepted as $row) {
            if ($row['day_of_week'] !== $day) {
                continue;
            }

            if (! ($start < $row['end_time'] && $end > $row['start_time'])) {
                continue;
            }

            if ($roomId && $row['room_id'] && $roomId === $row['room_id']) {
                return 'bentrok ruang dengan baris lain di file ini';
            }

            if ($lecturerId && $row['lecturer_profile_id'] && $lecturerId === $row['lecturer_profile_id']) {
                return 'bentrok dosen dengan baris lain di file ini';
            }
        }

        return null;
    }

    private function parseImportTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i');
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim((string) $value), $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];

            if ($h <= 23 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        return null;
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function validatePayload(Request $request, ?int $offeringId = null): array
    {
        $offeringId ??= (int) $request->input('course_offering_id');

        return $request->validate([
            'course_offering_id' => 'required|exists:course_offerings,id',
            'lecturer_profile_id' => [
                'nullable',
                Rule::exists('course_offering_lecturers', 'lecturer_profile_id')->where('course_offering_id', $offeringId),
            ],
            'room_id' => 'nullable|exists:rooms,id',
            'day_of_week' => 'required|in:'.implode(',', self::DAYS),
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'session_type' => 'required|in:'.implode(',', self::SESSION_TYPES),
            'delivery_mode' => 'required|in:'.implode(',', self::DELIVERY_MODES),
            'meeting_link' => 'nullable|url',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ], [
            'lecturer_profile_id.exists' => 'Dosen yang dipilih harus terdaftar di kelas ini.',
        ]);
    }

    /**
     * Bentrok ruang & dosen pada hari + jam irisan (fitur baru).
     */
    private function findConflict(?int $ignoreId, array $data): ?string
    {
        // Normalisasi ke H:i:s agar perbandingan whereTime konsisten
        // di semua driver (SQLite membandingkan sebagai string).
        $start = strlen((string) $data['start_time']) === 5 ? $data['start_time'].':00' : $data['start_time'];
        $end = strlen((string) $data['end_time']) === 5 ? $data['end_time'].':00' : $data['end_time'];

        $overlap = fn ($query) => $query
            ->where('day_of_week', $data['day_of_week'])
            ->whereTime('start_time', '<', $end)
            ->whereTime('end_time', '>', $start)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));

        if (! empty($data['room_id'])) {
            $clash = (clone $overlap(CourseSchedule::query()))
                ->where('room_id', $data['room_id'])
                ->with('courseOffering.course:id,code')
                ->first();

            if ($clash) {
                $label = $clash->courseOffering?->course?->code ?? 'kelas lain';

                return "Ruang bentrok dengan {$label} ({$clash->start_time?->format('H:i')}–{$clash->end_time?->format('H:i')}).";
            }
        }

        if (! empty($data['lecturer_profile_id'])) {
            $clash = (clone $overlap(CourseSchedule::query()))
                ->where('lecturer_profile_id', $data['lecturer_profile_id'])
                ->with('courseOffering.course:id,code')
                ->first();

            if ($clash) {
                $label = $clash->courseOffering?->course?->code ?? 'kelas lain';

                return "Dosen bentrok dengan {$label} ({$clash->start_time?->format('H:i')}–{$clash->end_time?->format('H:i')}).";
            }
        }

        return null;
    }

    private function afterSaveRedirect(Request $request, string $message, CourseSchedule $schedule)
    {
        if ($request->query('return') === 'workspace') {
            return redirect()->route('admin.academic.course-offerings.show', $schedule->course_offering_id)
                ->with('success', $message);
        }

        return redirect()->route('admin.academic.course-schedules.index')
            ->with('success', $message);
    }

    private function formOptions(?int $offeringId = null): array
    {
        return [
            'rooms' => Room::query()->with('building:id,name')->orderBy('name')
                ->get(['id', 'name', 'code', 'building_id'])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'label' => ($r->building?->name ? $r->building->name.' · ' : '').$r->name.($r->code ? " ({$r->code})" : ''),
                ])->all(),
            'offeringLecturers' => $offeringId
                ? CourseOfferingLecturer::where('course_offering_id', $offeringId)
                    ->with('lecturerProfile.user:id,first_name,last_name')
                    ->orderBy('sort_order')->get()
                    ->map(fn ($l) => [
                        'id' => $l->lecturer_profile_id,
                        'label' => trim(($l->lecturerProfile?->user?->name ?? '-')." ({$l->role})"),
                    ])->all()
                : [],
        ];
    }
}
