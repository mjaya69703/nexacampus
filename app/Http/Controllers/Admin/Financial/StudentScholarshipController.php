<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Financial\Scholarship;
use App\Models\Financial\StudentScholarship;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * CRUD penerima beasiswa — memakai kit CRUD shared.
 *
 * Paritas Blade lama: alokasi massal ke banyak mahasiswa sekaligus,
 * status active/completed/revoked, cakupan tahun/semester opsional.
 */
class StudentScholarshipController extends Controller
{
    public const STATUSES = ['active', 'completed', 'revoked'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'scholarship' => 'nullable|integer|exists:scholarships,id',
            'year' => 'nullable|integer|exists:academic_years,id',
            'status' => 'nullable|string|max:20',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentScholarship::query()
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile.studyProgram:id,name',
                'studentProfile:id,nim,user_id,study_program_id',
                'scholarship:id,name',
                'academicYear:id,name',
            ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->whereHas('studentProfile.user', fn ($u) => $u
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        if (filled($validated['nim'] ?? null)) {
            $query->whereHas('studentProfile', fn ($s) => $s->where('nim', 'like', '%'.$validated['nim'].'%'));
        }

        if (filled($validated['scholarship'] ?? null)) {
            $query->where('scholarship_id', $validated['scholarship']);
        }

        if (filled($validated['year'] ?? null)) {
            $query->where('academic_year_id', $validated['year']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $assignments = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/StudentScholarship/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Penerima Beasiswa'),
            'can' => [
                'create' => ActivePermission::check('student-scholarship.create'),
                'update' => ActivePermission::check('student-scholarship.update'),
                'delete' => ActivePermission::check('student-scholarship.delete'),
            ],
            'stats' => [
                'total' => StudentScholarship::count(),
                'active' => StudentScholarship::where('status', 'active')->count(),
                'revoked' => StudentScholarship::where('status', 'revoked')->count(),
            ],
            'data' => [
                'rows' => collect($assignments->items())->values()->map(fn ($a, $i) => [
                    'id' => $a->id,
                    'no' => ($assignments->firstItem() ?? 0) + $i,
                    'student' => $a->studentProfile?->user?->name ?? '-',
                    'nim' => $a->studentProfile?->nim,
                    'program' => $a->studentProfile?->studyProgram?->name,
                    'scholarship' => $a->scholarship?->name ?? '-',
                    'scope' => ($a->academicYear?->name ?? 'Semua tahun').' · '.($a->semester ? 'Smt '.$a->semester : 'Semua semester'),
                    'status' => $a->status,
                    'statusTone' => $this->statusTone($a->status),
                    'statusText' => $this->statusText($a->status),
                    'at' => $a->created_at?->format('d M Y'),
                    'editUrl' => route('admin.financial.student-scholarships.edit', $a->id),
                    'deleteUrl' => route('admin.financial.student-scholarships.destroy', $a->id),
                ])->all(),
                'currentPage' => $assignments->currentPage(),
                'lastPage' => $assignments->lastPage(),
                'perPage' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'scholarship' => $validated['scholarship'] ?? '',
                'year' => $validated['year'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'scholarshipOptions' => Scholarship::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all(),
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'statuses' => self::STATUSES,
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.financial.student-scholarships.index'),
                'create' => route('admin.financial.student-scholarships.create'),
                'store' => route('admin.financial.student-scholarships.store'),
                'export' => route('admin.financial.student-scholarships.export'),
                'exportPdf' => route('admin.financial.exports.pdf', ['resource' => 'student-scholarships']),
                'bulkDestroy' => route('admin.financial.student-scholarships.bulk-destroy'),
                'searchStudents' => route('admin.financial.student-scholarships.search-students'),
                'importTemplate' => route('admin.financial.student-scholarships.import-template'),
                'importSubmit' => route('admin.financial.student-scholarships.import'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Financial/StudentScholarship/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Alokasi Beasiswa'),
            'mode' => 'create',
            'assignment' => [
                'scholarship_id' => '', 'academic_year_id' => '', 'semester' => '',
                'start_date' => '', 'end_date' => '', 'status' => 'active', 'notes' => '',
            ],
            'selectedStudents' => [],
            'scholarships' => $this->scholarshipOptions(),
            'years' => $this->yearOptions(),
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.financial.student-scholarships.index'),
                'submit' => route('admin.financial.student-scholarships.store'),
                'searchStudents' => route('admin.financial.student-scholarships.search-students'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));

        $created = 0;
        $errors = [];

        foreach ($validated['student_profile_ids'] as $studentProfileId) {
            try {
                StudentScholarship::create([
                    'student_profile_id' => $studentProfileId,
                    'scholarship_id' => $validated['scholarship_id'],
                    'academic_year_id' => filled($validated['academic_year_id'] ?? null) ? $validated['academic_year_id'] : null,
                    'semester' => filled($validated['semester'] ?? null) ? (int) $validated['semester'] : null,
                    'start_date' => filled($validated['start_date'] ?? null) ? $validated['start_date'] : null,
                    'end_date' => filled($validated['end_date'] ?? null) ? $validated['end_date'] : null,
                    'status' => $validated['status'],
                    'notes' => ($validated['notes'] ?? null) ?: null,
                    'created_by' => $request->user()->id,
                ]);

                $created++;
            } catch (Throwable $exception) {
                $errors[] = $studentProfileId.': '.$exception->getMessage();
            }
        }

        if ($created > 0 && empty($errors)) {
            return redirect()->route('admin.financial.student-scholarships.index')
                ->with('success', $created.' mahasiswa dialokasikan beasiswa.');
        }

        $message = $created > 0 ? $created.' alokasi tersimpan. ' : '';
        $message .= implode(' ', array_slice($errors, 0, 5));

        return redirect()->route('admin.financial.student-scholarships.index')
            ->with($created > 0 ? 'success' : 'error', $message);
    }

    public function edit(Request $request, StudentScholarship $studentScholarship): Response
    {
        $studentScholarship->load(['studentProfile.user']);

        return Inertia::render('Admin/Financial/StudentScholarship/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Ubah Alokasi'),
            'mode' => 'edit',
            'assignment' => [
                'scholarship_id' => (string) $studentScholarship->scholarship_id,
                'academic_year_id' => $studentScholarship->academic_year_id ? (string) $studentScholarship->academic_year_id : '',
                'semester' => $studentScholarship->semester !== null ? (string) $studentScholarship->semester : '',
                'start_date' => $studentScholarship->start_date,
                'end_date' => $studentScholarship->end_date,
                'status' => $studentScholarship->status,
                'notes' => $studentScholarship->notes,
            ],
            'selectedStudents' => [[
                'id' => $studentScholarship->student_profile_id,
                'label' => ($studentScholarship->studentProfile?->nim ?? '-').' - '.$studentScholarship->studentProfile?->user?->name,
            ]],
            'scholarships' => $this->scholarshipOptions(),
            'years' => $this->yearOptions(),
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.financial.student-scholarships.index'),
                'submit' => route('admin.financial.student-scholarships.update', $studentScholarship),
                'searchStudents' => route('admin.financial.student-scholarships.search-students'),
            ],
        ]);
    }

    public function update(Request $request, StudentScholarship $studentScholarship)
    {
        $validated = $request->validate($this->rules($request, true));

        $studentScholarship->update([
            'scholarship_id' => $validated['scholarship_id'],
            'academic_year_id' => filled($validated['academic_year_id'] ?? null) ? $validated['academic_year_id'] : null,
            'semester' => filled($validated['semester'] ?? null) ? (int) $validated['semester'] : null,
            'start_date' => filled($validated['start_date'] ?? null) ? $validated['start_date'] : null,
            'end_date' => filled($validated['end_date'] ?? null) ? $validated['end_date'] : null,
            'status' => $validated['status'],
            'notes' => ($validated['notes'] ?? null) ?: null,
        ]);

        return redirect()->route('admin.financial.student-scholarships.index')
            ->with('success', 'Alokasi beasiswa diperbarui.');
    }

    public function destroy(StudentScholarship $studentScholarship)
    {
        $studentScholarship->delete();

        return back()->with('success', 'Alokasi beasiswa dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:student_scholarships,id',
        ]);

        StudentScholarship::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', count($validated['ids']).' alokasi dihapus.');
    }

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $students = StudentProfile::query()
            ->with(['user:id,first_name,last_name', 'studyProgram:id,name'])
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query
                ->where('nim', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('nim')
            ->limit(25)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => ($s->nim ?? '-').' - '.$s->user?->name.' ('.$s->studyProgram?->name.')',
            ])
            ->all();

        return response()->json(['options' => $students]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = StudentScholarship::query()
            ->with(['studentProfile.user', 'scholarship', 'academicYear']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Mahasiswa', 'NIM', 'Beasiswa', 'Tahun', 'Semester', 'Status', 'Mulai', 'Selesai']], null, 'A1');

        foreach ($rows as $i => $assignment) {
            $sheet->fromArray([
                $assignment->studentProfile?->user?->name,
                $assignment->studentProfile?->nim,
                $assignment->scholarship?->name,
                $assignment->academicYear?->name,
                $assignment->semester,
                $assignment->status,
                $assignment->start_date,
                $assignment->end_date,
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'student-scholarships-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['nim', 'scholarship_name', 'year_code', 'semester', 'start_date', 'end_date', 'status', 'notes']], null, 'A1');
        $sheet->fromArray([['20260001', 'Beasiswa Prestasi', '2026', '1', '', '', 'active', '']], null, 'A2');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-student-scholarships.xlsx',
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
        $missing = array_diff(['nim', 'scholarship_name', 'status'], $header);

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

        $studentMap = StudentProfile::query()->pluck('id', 'nim')->all();
        $scholarshipGroups = Scholarship::query()->pluck('id', 'name')->all();
        $yearMap = AcademicYear::query()->pluck('id', 'code')->all();
        $valid = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $data = array_map(
                fn ($v) => is_string($v) ? trim($v) : $v,
                array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null))
            );
            $rowErrors = [];

            $nim = (string) ($data['nim'] ?? '');

            if ($nim === '' || ! isset($studentMap[$nim])) {
                $rowErrors[] = "nim \"{$nim}\" tidak ditemukan";
            }

            $scholarshipName = (string) ($data['scholarship_name'] ?? '');
            $scholarshipMatches = array_keys(array_filter(
                $scholarshipGroups,
                fn ($id, $name) => mb_strtolower($name) === mb_strtolower($scholarshipName),
                ARRAY_FILTER_USE_BOTH
            ));

            if ($scholarshipName === '' || count($scholarshipMatches) === 0) {
                $rowErrors[] = "beasiswa \"{$scholarshipName}\" tidak ditemukan";
            } elseif (count($scholarshipMatches) > 1) {
                $rowErrors[] = "beasiswa \"{$scholarshipName}\" ambigu (duplikat nama)";
            }

            $yearCode = (string) ($data['year_code'] ?? '');

            if ($yearCode !== '' && ! isset($yearMap[$yearCode])) {
                $rowErrors[] = "tahun \"{$yearCode}\" tidak ditemukan";
            }

            $semester = $data['semester'] ?? null;

            if ($semester !== null && $semester !== '' && filter_var($semester, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 14]]) === false) {
                $rowErrors[] = 'semester harus 1-14';
            }

            $startDate = $this->parseImportDate($data['start_date'] ?? null);
            $endDate = $this->parseImportDate($data['end_date'] ?? null);

            if (($data['start_date'] ?? null) !== null && ($data['start_date'] ?? '') !== '' && $startDate === null) {
                $rowErrors[] = 'start_date tidak valid (Y-m-d)';
            }

            if (($data['end_date'] ?? null) !== null && ($data['end_date'] ?? '') !== '' && $endDate === null) {
                $rowErrors[] = 'end_date tidak valid (Y-m-d)';
            } elseif ($startDate !== null && $endDate !== null && $endDate < $startDate) {
                $rowErrors[] = 'end_date minimal sama dengan start_date';
            }

            if (! in_array($data['status'] ?? null, self::STATUSES, true)) {
                $rowErrors[] = 'status harus active/completed/revoked';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $valid[] = [
                'student_profile_id' => $studentMap[$nim],
                'scholarship_id' => $scholarshipGroups[$scholarshipMatches[0]],
                'academic_year_id' => $yearCode !== '' ? $yearMap[$yearCode] : null,
                'semester' => ($semester !== null && $semester !== '') ? (int) $semester : null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.financial.student-scholarships.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        StudentScholarship::insert($valid);

        return redirect()->route('admin.financial.student-scholarships.index')
            ->with('success', count($valid).' alokasi berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'scholarship_id' => 'required|exists:scholarships,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'semester' => 'nullable|integer|min:1|max:14',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => 'nullable|string|max:1000',
        ];

        if (! $isUpdate) {
            $rules['student_profile_ids'] = 'required|array|min:1';
            $rules['student_profile_ids.*'] = 'exists:student_profiles,id';
        }

        return $rules;
    }

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'active' => 'green',
            'completed' => 'blue',
            'revoked' => 'red',
            default => 'gray',
        };
    }

    private function statusText(?string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'revoked' => 'Dicabut',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function scholarshipOptions(): array
    {
        return Scholarship::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function yearOptions(): array
    {
        return AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
            ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])
            ->all();
    }

    private function parseImportDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
