<?php

namespace App\Http\Controllers\Admin\Campus;

use App\Http\Controllers\Controller;
use App\Models\Campus\AssetCategory;
use App\Models\Campus\CampusAsset;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Kategori Aset — memakai kit CRUD shared (pola menu academic).
 *
 * Master untuk pengelompokan inventaris (dulu enum hardcoded).
 * Hapus ditolak bila masih dipakai aset.
 */
class AssetCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = AssetCategory::query()->withCount('assets as assets_count');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $categories = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Campus/AssetCategory/Index', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Daftar Kategori Aset'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('asset-category.create'),
                'update' => ActivePermission::check('asset-category.update'),
                'delete' => ActivePermission::check('asset-category.delete'),
                'restore' => ActivePermission::any(['asset-category.update', 'asset-category.delete']),
                'toggle' => ActivePermission::check('asset-category.update'),
            ],
            'stats' => [
                'total' => AssetCategory::count(),
                'active' => AssetCategory::where('is_active', true)->count(),
                'assets' => CampusAsset::count(),
                'trashed' => AssetCategory::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($categories->items())->values()->map(fn ($c, $i) => [
                    'id' => $c->id,
                    'no' => ($categories->firstItem() ?? 0) + $i,
                    'name' => $c->name,
                    'code' => $c->code,
                    'desc' => $c->desc ? Str::limit(strip_tags($c->desc), 70) : null,
                    'assetCount' => (int) $c->assets_count,
                    'isActive' => (bool) $c->is_active,
                    'createdAt' => $c->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.campus.asset-categories.edit', $c->id),
                    'deleteUrl' => route('admin.campus.asset-categories.destroy', $c->id),
                    'restoreUrl' => route('admin.campus.asset-categories.restore', $c->id),
                    'forceUrl' => route('admin.campus.asset-categories.force-destroy', $c->id),
                    'toggleUrl' => route('admin.campus.asset-categories.toggle', $c->id),
                ])->all(),
                'currentPage' => $categories->currentPage(),
                'lastPage' => $categories->lastPage(),
                'perPage' => $categories->perPage(),
                'total' => $categories->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.campus.asset-categories.index'),
                'create' => route('admin.campus.asset-categories.create'),
                'export' => route('admin.campus.asset-categories.export'),
                'exportPdf' => route('admin.campus.exports.pdf', ['resource' => 'asset-categories']),
                'importTemplate' => route('admin.campus.asset-categories.import-template'),
                'importSubmit' => route('admin.campus.asset-categories.import'),
                'bulkDestroy' => route('admin.campus.asset-categories.bulk-destroy'),
                'bulkRestore' => route('admin.campus.asset-categories.bulk-restore'),
                'bulkForceDestroy' => route('admin.campus.asset-categories.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Campus/AssetCategory/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Tambah Kategori Aset'),
            'mode' => 'create',
            'category' => ['name' => '', 'code' => '', 'is_active' => true, 'desc' => ''],
            'urls' => [
                'index' => route('admin.campus.asset-categories.index'),
                'submit' => route('admin.campus.asset-categories.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:asset_categories,code',
            'is_active' => 'boolean',
            'desc' => 'nullable|string|max:500',
        ]);

        $category = AssetCategory::create([
            'name' => $validated['name'],
            'code' => filled($validated['code'] ?? null) ? Str::slug($validated['code']) : Str::slug($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
            'desc' => $validated['desc'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.asset-categories.index')
            ->with('success', 'Kategori "'.$category->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, AssetCategory $category): Response
    {
        return Inertia::render('Admin/Campus/AssetCategory/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Edit Kategori Aset'),
            'mode' => 'edit',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'is_active' => (bool) $category->is_active,
                'desc' => $category->desc ?? '',
            ],
            'urls' => [
                'index' => route('admin.campus.asset-categories.index'),
                'submit' => route('admin.campus.asset-categories.update', $category),
            ],
        ]);
    }

    public function update(Request $request, AssetCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('asset_categories', 'code')->ignore($category->id)],
            'is_active' => 'boolean',
            'desc' => 'nullable|string|max:500',
        ]);

        $category->update([
            'name' => $validated['name'],
            'code' => filled($validated['code'] ?? null) ? Str::slug($validated['code']) : Str::slug($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
            'desc' => $validated['desc'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.asset-categories.index')
            ->with('success', 'Kategori "'.$category->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, AssetCategory $category)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $category->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status kategori berhasil diperbarui.');
    }

    public function destroy(AssetCategory $category)
    {
        if ($category->assets()->count() > 0) {
            return back()->with('error', 'Kategori "'.$category->name.'" masih dipakai aset. Pindahkan aset terlebih dahulu.');
        }

        $name = $category->name;
        $category->delete();

        return redirect()->route('admin.campus.asset-categories.index')
            ->with('success', 'Kategori "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:asset_categories,id',
        ]);

        $categories = AssetCategory::whereIn('id', $validated['ids'])->withCount('assets')->get();
        $blocked = $categories->where('assets_count', '>', 0);
        $deletable = $categories->where('assets_count', 0);

        AssetCategory::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' kategori dihapus. '.$blocked->count().' tidak bisa dihapus karena masih dipakai aset.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih dipakai aset.');
        }

        return back()->with('success', $deletable->count().' kategori berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $category = AssetCategory::withTrashed()->findOrFail($id);
        $category->restore();

        return back()->with('success', 'Kategori "'.$category->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $category = AssetCategory::withTrashed()->findOrFail($id);

        if ($category->assets()->withTrashed()->count() > 0) {
            return back()->with('error', 'Kategori "'.$category->name.'" masih dipakai aset.');
        }

        $name = $category->name;
        $category->forceDelete();

        return back()->with('success', 'Kategori "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:asset_categories,id',
        ]);

        $count = 0;

        foreach (AssetCategory::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $category) {
            $category->restore();
            $count++;
        }

        return back()->with('success', $count.' kategori berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:asset_categories,id',
        ]);

        $categories = AssetCategory::withTrashed()->whereIn('id', $validated['ids'])->withCount(['assets' => fn ($q) => $q->withTrashed()])->get();
        $blocked = $categories->where('assets_count', '>', 0);
        $deletable = $categories->where('assets_count', 0);

        foreach ($deletable as $category) {
            $category->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' kategori dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih dipakai aset.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih dipakai aset.');
        }

        return back()->with('success', $deletable->count().' kategori dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = AssetCategory::query()->withCount('assets');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        $rows = $query->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Kode', 'Jml Aset', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $category) {
            $sheet->fromArray([
                $category->name,
                $category->code,
                (int) $category->assets_count,
                $category->is_active ? 'Ya' : 'Tidak',
                $category->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'asset-categories-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['name', 'code', 'desc', 'is_active']], null, 'A1');
        $sheet->fromArray([['Elektronik & IT', 'electronics', '', '1']], null, 'A2');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-asset-categories.xlsx',
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
        $missing = array_diff(['name'], $header);

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

        $seenCodes = [];
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

            if (($data['name'] ?? null) === null || $data['name'] === '') {
                $rowErrors[] = 'name wajib diisi';
            } elseif (strlen((string) $data['name']) > 100) {
                $rowErrors[] = 'name maksimal 100 karakter';
            }

            $code = isset($data['code']) && $data['code'] !== '' ? Str::slug((string) $data['code']) : '';

            if ($code === '' && isset($data['name']) && $data['name'] !== '') {
                $code = Str::slug((string) $data['name']);
            }

            if ($code === '') {
                $rowErrors[] = 'code wajib diisi';
            } elseif (strlen($code) > 50) {
                $rowErrors[] = 'code maksimal 50 karakter';
            } elseif (in_array($code, $seenCodes, true)) {
                $rowErrors[] = 'code duplikat di dalam file';
            } elseif (AssetCategory::where('code', $code)->exists()) {
                $rowErrors[] = 'code sudah terdaftar';
            } else {
                $seenCodes[] = $code;
            }

            if (isset($data['desc']) && strlen((string) $data['desc']) > 500) {
                $rowErrors[] = 'desc maksimal 500 karakter';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $valid[] = [
                'name' => $data['name'],
                'code' => $code,
                'desc' => $data['desc'] ?? null,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.campus.asset-categories.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        AssetCategory::insert($valid);

        return redirect()->route('admin.campus.asset-categories.index')
            ->with('success', count($valid).' kategori berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }
}
