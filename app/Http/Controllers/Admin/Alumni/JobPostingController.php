<?php

namespace App\Http\Controllers\Admin\Alumni;

use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Lowongan Kerja Alumni — kit CRUD shared.
 *
 * Koreksi dari Blade lama:
 * - tenggat memakai kolom `deadline_date` (dulu `application_deadline`,
 *   sehingga kolom deadline selalu "-");
 * - nama perusahaan memakai kolom `company_name` milik lowongan, bukan
 *   nama mitra, dan otomatis diambil dari mitra bila dikosongkan;
 * - kolom opsional dapat dikosongkan saat edit.
 */
class JobPostingController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'employer_partner_id' => 'nullable|integer|exists:employer_partners,id',
            'job_type' => ['nullable', Rule::in(array_keys(JobType::options()))],
            'validity' => 'nullable|in:open,expired',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,title,company_name,job_type,location,posted_date,deadline_date,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = $this->filteredQuery($validated, applySort: false);
        $sort = $validated['sort'] ?? 'posted_date';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $jobs = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Alumni/Job/Index', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Lowongan Kerja'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('job-posting.create'),
                'update' => ActivePermission::check('job-posting.update'),
                'delete' => ActivePermission::check('job-posting.delete'),
                'restore' => ActivePermission::any(['job-posting.update', 'job-posting.delete']),
                'toggle' => ActivePermission::check('job-posting.update'),
                'view' => ActivePermission::check('job-posting.view'),
            ],
            'stats' => [
                'total' => JobPosting::count(),
                'active' => JobPosting::where('is_active', true)->count(),
                'fullTime' => JobPosting::where('job_type', JobType::FullTime->value)->count(),
                'internship' => JobPosting::where('job_type', JobType::Internship->value)->count(),
                'expired' => JobPosting::whereDate('deadline_date', '<', now()->toDateString())->count(),
                'trashed' => JobPosting::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($jobs->items())->values()->map(fn (JobPosting $job, int $i) => [
                    'id' => $job->id,
                    'no' => ($jobs->firstItem() ?? 0) + $i,
                    'title' => $job->title,
                    'companyName' => $job->company_name,
                    'partnerName' => $job->employerPartner?->name,
                    'industry' => $job->industry,
                    'jobType' => $job->job_type,
                    'jobTypeLabel' => JobType::tryFrom((string) $job->job_type)?->label() ?? $job->job_type,
                    'location' => $job->location,
                    'salaryRange' => $job->salary_range,
                    'postedDate' => $job->posted_date?->format('d M Y'),
                    'deadlineDate' => $job->deadline_date?->format('d M Y'),
                    'isExpired' => $job->deadline_date !== null && $job->deadline_date->lt(now()->startOfDay()),
                    'isActive' => (bool) $job->is_active,
                    'createdAt' => $job->created_at?->format('d M Y H:i'),
                    'showUrl' => $isTrash ? null : route('admin.alumni.job-postings.show', $job),
                    'editUrl' => $isTrash ? null : route('admin.alumni.job-postings.edit', $job),
                    'deleteUrl' => route('admin.alumni.job-postings.destroy', $job),
                    'restoreUrl' => route('admin.alumni.job-postings.restore', $job->id),
                    'forceUrl' => route('admin.alumni.job-postings.force-destroy', $job->id),
                    'toggleUrl' => route('admin.alumni.job-postings.toggle', $job),
                ])->all(),
                'currentPage' => $jobs->currentPage(),
                'lastPage' => $jobs->lastPage(),
                'perPage' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'employer_partner_id' => $validated['employer_partner_id'] ?? '',
                'job_type' => $validated['job_type'] ?? '',
                'validity' => $validated['validity'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'partnerOptions' => $this->partnerOptions(),
            'jobTypeOptions' => JobType::options(),
            'urls' => [
                'index' => route('admin.alumni.job-postings.index'),
                'create' => route('admin.alumni.job-postings.create'),
                'export' => route('admin.alumni.job-postings.export'),
                'bulkDestroy' => route('admin.alumni.job-postings.bulk-destroy'),
                'bulkRestore' => route('admin.alumni.job-postings.bulk-restore'),
                'bulkForceDestroy' => route('admin.alumni.job-postings.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Alumni/Job/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Tambah Lowongan Kerja'),
            'mode' => 'create',
            'job' => $this->defaultJob(),
            'partnerOptions' => $this->partnerOptions(),
            'jobTypeOptions' => JobType::options(),
            'urls' => [
                'index' => route('admin.alumni.job-postings.index'),
                'submit' => route('admin.alumni.job-postings.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateJob($request);
        $data = [
            ...$this->jobPayload($validated),
            'created_by' => $request->user()->id,
        ];

        $job = JobPosting::create($data);

        return redirect()->route('admin.alumni.job-postings.index')
            ->with('success', 'Lowongan "'.$job->title.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, JobPosting $jobPosting): Response
    {
        return Inertia::render('Admin/Alumni/Job/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Edit Lowongan Kerja'),
            'mode' => 'edit',
            'job' => [
                'id' => $jobPosting->id,
                ...$this->defaultJob(),
                'employer_partner_id' => $jobPosting->employer_partner_id ?? '',
                'title' => $jobPosting->title,
                'company_name' => $jobPosting->company_name,
                'industry' => $jobPosting->industry ?? '',
                'description' => $jobPosting->description,
                'requirements' => $jobPosting->requirements ?? '',
                'location' => $jobPosting->location ?? '',
                'job_type' => $jobPosting->job_type ?? '',
                'salary_range' => $jobPosting->salary_range ?? '',
                'apply_url' => $jobPosting->apply_url ?? '',
                'contact_email' => $jobPosting->contact_email ?? '',
                'posted_date' => $jobPosting->posted_date?->toDateString() ?? '',
                'deadline_date' => $jobPosting->deadline_date?->toDateString() ?? '',
                'is_active' => (bool) $jobPosting->is_active,
            ],
            'partnerOptions' => $this->partnerOptions(),
            'jobTypeOptions' => JobType::options(),
            'urls' => [
                'index' => route('admin.alumni.job-postings.index'),
                'submit' => route('admin.alumni.job-postings.update', $jobPosting),
            ],
        ]);
    }

    public function update(Request $request, JobPosting $jobPosting)
    {
        $validated = $this->validateJob($request);
        $jobPosting->update([
            ...$this->jobPayload($validated),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.alumni.job-postings.index')
            ->with('success', 'Lowongan "'.$jobPosting->title.'" berhasil diperbarui.');
    }

    public function show(Request $request, JobPosting $jobPosting): Response
    {
        $jobPosting->loadMissing(['employerPartner:id,name', 'createdByUser:id,first_name,last_name', 'updatedByUser:id,first_name,last_name']);

        return Inertia::render('Admin/Alumni/Job/Show', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Detail Lowongan Kerja'),
            'can' => [
                'update' => ActivePermission::check('job-posting.update'),
                'delete' => ActivePermission::check('job-posting.delete'),
            ],
            'job' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title,
                'companyName' => $jobPosting->company_name,
                'partnerName' => $jobPosting->employerPartner?->name,
                'industry' => $jobPosting->industry,
                'description' => $jobPosting->description,
                'requirements' => $jobPosting->requirements,
                'location' => $jobPosting->location,
                'jobType' => $jobPosting->job_type,
                'jobTypeLabel' => JobType::tryFrom((string) $jobPosting->job_type)?->label() ?? $jobPosting->job_type,
                'salaryRange' => $jobPosting->salary_range,
                'applyUrl' => $jobPosting->apply_url,
                'contactEmail' => $jobPosting->contact_email,
                'postedDate' => $jobPosting->posted_date?->format('d M Y'),
                'deadlineDate' => $jobPosting->deadline_date?->format('d M Y'),
                'isExpired' => $jobPosting->deadline_date !== null && $jobPosting->deadline_date->lt(now()->startOfDay()),
                'isActive' => (bool) $jobPosting->is_active,
                'createdAt' => $jobPosting->created_at?->format('d M Y H:i'),
                'updatedAt' => $jobPosting->updated_at?->format('d M Y H:i'),
                'createdBy' => $jobPosting->createdByUser?->name,
                'updatedBy' => $jobPosting->updatedByUser?->name,
            ],
            'urls' => [
                'index' => route('admin.alumni.job-postings.index'),
                'edit' => ActivePermission::check('job-posting.update')
                    ? route('admin.alumni.job-postings.edit', $jobPosting)
                    : null,
                'destroy' => route('admin.alumni.job-postings.destroy', $jobPosting),
            ],
        ]);
    }

    public function toggle(Request $request, JobPosting $jobPosting)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $jobPosting->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status lowongan berhasil diperbarui.');
    }

    public function destroy(Request $request, JobPosting $jobPosting)
    {
        $title = $jobPosting->title;
        $jobPosting->update(['updated_by' => $request->user()->id]);
        $jobPosting->delete();

        return redirect()->route('admin.alumni.job-postings.index')
            ->with('success', 'Lowongan "'.$title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:job_postings,id',
        ]);

        $jobs = JobPosting::whereIn('id', $validated['ids'])->get();

        foreach ($jobs as $job) {
            $job->update(['updated_by' => $request->user()->id]);
            $job->delete();
        }

        return back()->with('success', $jobs->count().' lowongan berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $job = JobPosting::withTrashed()->findOrFail($id);
        $job->restore();

        return back()->with('success', 'Lowongan "'.$job->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $job = JobPosting::withTrashed()->findOrFail($id);
        $title = $job->title;
        $job->forceDelete();

        return back()->with('success', 'Lowongan "'.$title.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:job_postings,id',
        ]);

        $count = 0;

        foreach (JobPosting::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $job) {
            $job->restore();
            $count++;
        }

        return back()->with('success', $count.' lowongan berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:job_postings,id',
        ]);

        $jobs = JobPosting::withTrashed()->whereIn('id', $validated['ids'])->get();

        foreach ($jobs as $job) {
            $job->forceDelete();
        }

        return back()->with('success', $jobs->count().' lowongan dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = $this->filteredQuery($request->all(), applySort: false);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('posted_date')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Judul', 'Perusahaan', 'Mitra', 'Industri', 'Tipe', 'Lokasi', 'Rentang Gaji', 'Diposting', 'Deadline', 'Aktif', 'Dibuat'],
        ], null, 'A1');

        foreach ($rows as $i => $job) {
            $sheet->fromArray([
                $job->title,
                $job->company_name,
                $job->employerPartner?->name,
                $job->industry,
                JobType::tryFrom((string) $job->job_type)?->label() ?? $job->job_type,
                $job->location,
                $job->salary_range,
                $job->posted_date?->format('Y-m-d'),
                $job->deadline_date?->format('Y-m-d'),
                $job->is_active ? 'Ya' : 'Tidak',
                $job->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'lowongan-kerja-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function filteredQuery(array $validated, bool $applySort = true): Builder
    {
        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $query = JobPosting::query()->with('employerPartner:id,name');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%")
                    ->orWhere('industry', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('salary_range', 'like', "%{$q}%")
                    ->orWhereHas('employerPartner', fn (Builder $partner) => $partner->where('name', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['employer_partner_id'] ?? null)) {
            $query->where('employer_partner_id', $validated['employer_partner_id']);
        }

        if (filled($validated['job_type'] ?? null)) {
            $query->where('job_type', $validated['job_type']);
        }

        if (($validated['validity'] ?? '') === 'open') {
            $query->whereDate('deadline_date', '>=', now()->toDateString());
        } elseif (($validated['validity'] ?? '') === 'expired') {
            $query->whereDate('deadline_date', '<', now()->toDateString());
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if ($applySort) {
            $query->orderBy($validated['sort'] ?? 'posted_date', $validated['direction'] ?? 'desc');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateJob(Request $request): array
    {
        $validated = $request->validate([
            'employer_partner_id' => 'nullable|integer|exists:employer_partners,id',
            'title' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'description' => 'required|string',
            'requirements' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'job_type' => ['nullable', Rule::in(array_keys(JobType::options()))],
            'salary_range' => 'nullable|string|max:255',
            'apply_url' => 'nullable|url|max:500',
            'contact_email' => 'nullable|email|max:255',
            'posted_date' => 'required|date',
            'deadline_date' => 'required|date|after_or_equal:posted_date',
            'is_active' => 'boolean',
        ]);

        $partner = filled($validated['employer_partner_id'] ?? null)
            ? EmployerPartner::find($validated['employer_partner_id'])
            : null;

        $companyName = trim((string) ($validated['company_name'] ?? '')) ?: $partner?->name;

        if (blank($companyName)) {
            throw ValidationException::withMessages([
                'company_name' => 'Nama perusahaan wajib diisi atau pilih mitra terdaftar.',
            ]);
        }

        return [
            ...$validated,
            'company_name' => $companyName,
            'industry' => $validated['industry'] ?? $partner?->industry,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function jobPayload(array $validated): array
    {
        return [
            'employer_partner_id' => $validated['employer_partner_id'] ?? null,
            'title' => $validated['title'],
            'company_name' => $validated['company_name'],
            'industry' => $validated['industry'] ?? null,
            'description' => $validated['description'],
            'requirements' => $validated['requirements'] ?? null,
            'location' => $validated['location'] ?? null,
            'job_type' => $validated['job_type'] ?? null,
            'salary_range' => $validated['salary_range'] ?? null,
            'apply_url' => $validated['apply_url'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'posted_date' => $validated['posted_date'],
            'deadline_date' => $validated['deadline_date'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultJob(): array
    {
        return [
            'employer_partner_id' => '',
            'title' => '',
            'company_name' => '',
            'industry' => '',
            'description' => '',
            'requirements' => '',
            'location' => '',
            'job_type' => JobType::FullTime->value,
            'salary_range' => '',
            'apply_url' => '',
            'contact_email' => '',
            'posted_date' => now()->toDateString(),
            'deadline_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, industry: string|null, isActive: bool}>
     */
    private function partnerOptions(): array
    {
        return EmployerPartner::query()
            ->orderBy('name')
            ->get(['id', 'name', 'industry', 'is_active'])
            ->map(fn (EmployerPartner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'industry' => $partner->industry,
                'isActive' => (bool) $partner->is_active,
            ])
            ->all();
    }
}
