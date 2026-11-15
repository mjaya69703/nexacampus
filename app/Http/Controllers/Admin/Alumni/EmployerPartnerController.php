<?php

namespace App\Http\Controllers\Admin\Alumni;

use App\Http\Controllers\Controller;
use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Mitra Perusahaan (employer partner) — kit CRUD shared.
 *
 * Koreksi dari Blade lama: kolom opsional dapat dikosongkan saat edit,
 * logo lama dibersihkan saat diganti/dihapus permanen, dan jumlah lowongan
 * per mitra ditampilkan agar relasi rekrutmen terlihat.
 */
class EmployerPartnerController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:255',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,industry,city,contact_person,job_postings_count,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = $this->filteredQuery($validated, applySort: false);
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $partners = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Alumni/Employer/Index', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Mitra Perusahaan'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('employer-partner.create'),
                'update' => ActivePermission::check('employer-partner.update'),
                'delete' => ActivePermission::check('employer-partner.delete'),
                'restore' => ActivePermission::any(['employer-partner.update', 'employer-partner.delete']),
                'toggle' => ActivePermission::check('employer-partner.update'),
            ],
            'stats' => [
                'total' => EmployerPartner::count(),
                'active' => EmployerPartner::where('is_active', true)->count(),
                'withJobs' => EmployerPartner::whereHas('jobPostings')->count(),
                'jobs' => JobPosting::count(),
                'trashed' => EmployerPartner::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($partners->items())->values()->map(fn (EmployerPartner $partner, int $i) => [
                    'id' => $partner->id,
                    'no' => ($partners->firstItem() ?? 0) + $i,
                    'name' => $partner->name,
                    'industry' => $partner->industry,
                    'city' => $partner->city,
                    'province' => $partner->province,
                    'website' => $partner->website,
                    'contactPerson' => $partner->contact_person,
                    'contactEmail' => $partner->contact_email,
                    'contactPhone' => $partner->contact_phone,
                    'logoUrl' => $this->publicUrl($partner->logo_path),
                    'jobsCount' => (int) $partner->job_postings_count,
                    'isActive' => (bool) $partner->is_active,
                    'createdAt' => $partner->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.alumni.employer-partners.edit', $partner),
                    'jobsUrl' => route('admin.alumni.job-postings.index', ['employer_partner_id' => $partner->id]),
                    'deleteUrl' => route('admin.alumni.employer-partners.destroy', $partner),
                    'restoreUrl' => route('admin.alumni.employer-partners.restore', $partner->id),
                    'forceUrl' => route('admin.alumni.employer-partners.force-destroy', $partner->id),
                    'toggleUrl' => route('admin.alumni.employer-partners.toggle', $partner),
                ])->all(),
                'currentPage' => $partners->currentPage(),
                'lastPage' => $partners->lastPage(),
                'perPage' => $partners->perPage(),
                'total' => $partners->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'industry' => $validated['industry'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'industryOptions' => $this->industryOptions(),
            'urls' => [
                'index' => route('admin.alumni.employer-partners.index'),
                'create' => route('admin.alumni.employer-partners.create'),
                'export' => route('admin.alumni.employer-partners.export'),
                'bulkDestroy' => route('admin.alumni.employer-partners.bulk-destroy'),
                'bulkRestore' => route('admin.alumni.employer-partners.bulk-restore'),
                'bulkForceDestroy' => route('admin.alumni.employer-partners.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Alumni/Employer/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Tambah Mitra Perusahaan'),
            'mode' => 'create',
            'partner' => $this->defaultPartner(),
            'urls' => [
                'index' => route('admin.alumni.employer-partners.index'),
                'submit' => route('admin.alumni.employer-partners.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePartner($request);
        $data = [
            ...$this->partnerPayload($validated),
            'created_by' => $request->user()->id,
        ];

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('employer-partners/logos', 'public');
        }

        $partner = EmployerPartner::create($data);

        return redirect()->route('admin.alumni.employer-partners.index')
            ->with('success', 'Mitra perusahaan "'.$partner->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, EmployerPartner $employerPartner): Response
    {
        return Inertia::render('Admin/Alumni/Employer/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Edit Mitra Perusahaan'),
            'mode' => 'edit',
            'partner' => [
                'id' => $employerPartner->id,
                ...$this->defaultPartner(),
                'name' => $employerPartner->name,
                'industry' => $employerPartner->industry ?? '',
                'website' => $employerPartner->website ?? '',
                'contact_person' => $employerPartner->contact_person ?? '',
                'contact_email' => $employerPartner->contact_email ?? '',
                'contact_phone' => $employerPartner->contact_phone ?? '',
                'address' => $employerPartner->address ?? '',
                'city' => $employerPartner->city ?? '',
                'province' => $employerPartner->province ?? '',
                'description' => $employerPartner->description ?? '',
                'is_active' => (bool) $employerPartner->is_active,
                'logo_url' => $this->publicUrl($employerPartner->logo_path),
                'jobs_count' => $employerPartner->jobPostings()->count(),
            ],
            'urls' => [
                'index' => route('admin.alumni.employer-partners.index'),
                'submit' => route('admin.alumni.employer-partners.update', $employerPartner),
            ],
        ]);
    }

    public function update(Request $request, EmployerPartner $employerPartner)
    {
        $validated = $this->validatePartner($request);
        $data = [
            ...$this->partnerPayload($validated),
            'updated_by' => $request->user()->id,
        ];

        if (($validated['remove_logo'] ?? false) && $employerPartner->logo_path) {
            $this->deleteLogo($employerPartner->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($employerPartner->logo_path) {
                $this->deleteLogo($employerPartner->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('employer-partners/logos', 'public');
        }

        $employerPartner->update($data);

        return redirect()->route('admin.alumni.employer-partners.index')
            ->with('success', 'Mitra perusahaan "'.$employerPartner->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, EmployerPartner $employerPartner)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $employerPartner->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status mitra perusahaan berhasil diperbarui.');
    }

    public function destroy(Request $request, EmployerPartner $employerPartner)
    {
        $name = $employerPartner->name;
        $employerPartner->update(['updated_by' => $request->user()->id]);
        $employerPartner->delete();

        return redirect()->route('admin.alumni.employer-partners.index')
            ->with('success', 'Mitra perusahaan "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:employer_partners,id',
        ]);

        $partners = EmployerPartner::whereIn('id', $validated['ids'])->get();

        foreach ($partners as $partner) {
            $partner->update(['updated_by' => $request->user()->id]);
            $partner->delete();
        }

        return back()->with('success', $partners->count().' mitra perusahaan berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $partner = EmployerPartner::withTrashed()->findOrFail($id);
        $partner->restore();

        return back()->with('success', 'Mitra perusahaan "'.$partner->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $partner = EmployerPartner::withTrashed()->findOrFail($id);
        $name = $partner->name;

        $this->deleteLogo($partner->logo_path);
        $partner->forceDelete();

        return back()->with('success', 'Mitra perusahaan "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:employer_partners,id',
        ]);

        $count = 0;

        foreach (EmployerPartner::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $partner) {
            $partner->restore();
            $count++;
        }

        return back()->with('success', $count.' mitra perusahaan berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:employer_partners,id',
        ]);

        $count = 0;

        foreach (EmployerPartner::withTrashed()->whereIn('id', $validated['ids'])->get() as $partner) {
            $this->deleteLogo($partner->logo_path);
            $partner->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' mitra perusahaan dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = $this->filteredQuery($request->all(), applySort: false);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Nama Mitra', 'Industri', 'Kota', 'Provinsi', 'Website', 'Kontak', 'Email', 'Telepon', 'Jumlah Lowongan', 'Aktif', 'Dibuat'],
        ], null, 'A1');

        foreach ($rows as $i => $partner) {
            $sheet->fromArray([
                $partner->name,
                $partner->industry,
                $partner->city,
                $partner->province,
                $partner->website,
                $partner->contact_person,
                $partner->contact_email,
                $partner->contact_phone,
                (int) $partner->job_postings_count,
                $partner->is_active ? 'Ya' : 'Tidak',
                $partner->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'mitra-perusahaan-'.now()->format('Ymd-His').'.'.$format;
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

        $query = EmployerPartner::query()->withCount('jobPostings');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('industry', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('province', 'like', "%{$q}%")
                    ->orWhere('contact_person', 'like', "%{$q}%")
                    ->orWhere('contact_email', 'like', "%{$q}%");
            });
        }

        if (filled($validated['industry'] ?? null)) {
            $query->where('industry', $validated['industry']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if ($applySort) {
            $query->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePartner(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:500',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'is_active' => 'boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'remove_logo' => 'boolean',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function partnerPayload(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'industry' => $validated['industry'] ?? null,
            'website' => $validated['website'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'province' => $validated['province'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPartner(): array
    {
        return [
            'name' => '',
            'industry' => '',
            'website' => '',
            'contact_person' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'address' => '',
            'city' => '',
            'province' => '',
            'description' => '',
            'is_active' => true,
            'logo_url' => null,
            'jobs_count' => 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function industryOptions(): array
    {
        return EmployerPartner::query()
            ->whereNotNull('industry')
            ->where('industry', '!=', '')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry')
            ->all();
    }

    private function deleteLogo(?string $path): void
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
