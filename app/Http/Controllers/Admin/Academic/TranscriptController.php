<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\TranscriptSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Transkrip — read-only + sinkronisasi (paritas Blade lama).
 *
 * Tidak ada create/edit/delete: entri transkrip + snapshot semester murni
 * hasil agregasi nilai Finalized/Published via TranscriptSyncService.
 * Aksi yang tersedia: sync per mahasiswa, bulk sync, export.
 */
class TranscriptController extends Controller
{
    public function __construct(
        protected TranscriptSyncService $sync = new TranscriptSyncService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'program' => 'nullable|integer|exists:study_programs,id',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentProfile::query()
            ->with(['user:id,first_name,last_name', 'studyProgram:id,name'])
            ->withCount('studyResults as results_count')
            ->withCount('transcriptEntries as entries_count')
            ->withMax('studyResults as ipk', 'cumulative_gpa');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->whereHas('user', fn ($u) => $u
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        if (filled($validated['nim'] ?? null)) {
            $query->where('nim', 'like', '%'.$validated['nim'].'%');
        }

        if (filled($validated['program'] ?? null)) {
            $query->where('study_program_id', $validated['program']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $students = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/Transcript/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Transkrip'),
            'can' => [
                'view' => ActivePermission::check('transcript.view'),
                'sync' => ActivePermission::check('transcript.update'),
            ],
            'stats' => [
                'students' => StudentProfile::count(),
                'withEntries' => StudentProfile::has('transcriptEntries')->count(),
                'entries' => \App\Models\Academic\TranscriptEntry::count(),
            ],
            'data' => [
                'rows' => collect($students->items())->values()->map(fn ($s, $i) => [
                    'id' => $s->id,
                    'no' => ($students->firstItem() ?? 0) + $i,
                    'student' => $s->user?->name ?? '-',
                    'nim' => $s->nim,
                    'program' => $s->studyProgram?->name,
                    'results' => (int) $s->results_count,
                    'entries' => (int) $s->entries_count,
                    'ipk' => $s->ipk !== null ? number_format((float) $s->ipk, 2) : null,
                    'showUrl' => route('admin.academic.transcripts.show', $s->id),
                    'syncUrl' => route('admin.academic.transcripts.sync', $s->id),
                ])->all(),
                'currentPage' => $students->currentPage(),
                'lastPage' => $students->lastPage(),
                'perPage' => $students->perPage(),
                'total' => $students->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'program' => $validated['program'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'programOptions' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'urls' => [
                'index' => route('admin.academic.transcripts.index'),
                'export' => route('admin.academic.transcripts.export'),
                'bulkSync' => route('admin.academic.transcripts.bulk-sync'),
            ],
        ]);
    }

    public function show(Request $request, StudentProfile $transcript): Response
    {
        $transcript->load([
            'user',
            'studyProgram',
            'studyResults.academicYear',
            'studyResults.studyPlan',
            'transcriptEntries.course',
            'transcriptEntries.academicYear',
        ]);

        return Inertia::render('Admin/Academic/Transcript/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Transkrip Mahasiswa'),
            'can' => [
                'sync' => ActivePermission::check('transcript.update'),
            ],
            'student' => [
                'id' => $transcript->id,
                'name' => $transcript->user?->name ?? '-',
                'nim' => $transcript->nim,
                'program' => $transcript->studyProgram?->name,
            ],
            'results' => $transcript->studyResults
                ->sortByDesc(fn ($r) => [$r->academicYear?->start_date ?? '', $r->id])
                ->values()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'year' => $r->academicYear?->name ?? '-',
                    'semester' => $r->semester_no,
                    'courses' => (int) $r->total_courses,
                    'taken' => (int) $r->total_credits_taken,
                    'passed' => (int) $r->total_credits_passed,
                    'ips' => $r->semester_gpa !== null ? number_format((float) $r->semester_gpa, 2) : null,
                    'ipk' => $r->cumulative_gpa !== null ? number_format((float) $r->cumulative_gpa, 2) : null,
                    'status' => $r->status,
                ])
                ->all(),
            'entries' => $transcript->transcriptEntries
                ->sortBy(fn ($e) => [$e->course?->code ?? '', $e->id])
                ->values()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'code' => $e->course?->code ?? '-',
                    'name' => $e->course?->name ?? '-',
                    'year' => $e->academicYear?->name ?? '-',
                    'semester' => $e->semester_no,
                    'credits' => (int) $e->credits,
                    'score' => $e->final_score !== null ? (float) $e->final_score : null,
                    'letter' => $e->letter_grade,
                    'point' => $e->grade_point !== null ? (float) $e->grade_point : null,
                    'result' => $e->result_status,
                ])
                ->all(),
            'urls' => [
                'index' => route('admin.academic.transcripts.index'),
                'sync' => route('admin.academic.transcripts.sync', $transcript),
            ],
        ]);
    }

    public function sync(Request $request, StudentProfile $transcript)
    {
        $result = $this->sync->syncStudent((int) $transcript->id);

        return back()->with('success',
            "Transkrip disinkron. Semester: {$result['study_results']}, entri nilai terbaik: {$result['transcript_entries']}.");
    }

    public function bulkSync(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:student_profiles,id',
        ]);

        $synced = 0;

        foreach (StudentProfile::whereIn('id', $validated['ids'])->get() as $student) {
            $this->sync->syncStudent((int) $student->id);
            $synced++;
        }

        return back()->with('success', "{$synced} transkrip mahasiswa disinkronkan.");
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->withCount('studyResults as results_count')
            ->withCount('transcriptEntries as entries_count')
            ->withMax('studyResults as ipk', 'cumulative_gpa');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Mahasiswa', 'NIM', 'Prodi', 'Jml Semester', 'Jml Entri', 'IPK']], null, 'A1');

        foreach ($rows as $i => $student) {
            $sheet->fromArray([
                $student->user?->name,
                $student->nim,
                $student->studyProgram?->name,
                (int) $student->results_count,
                (int) $student->entries_count,
                $student->ipk !== null ? (float) $student->ipk : null,
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'transcripts-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }
}
