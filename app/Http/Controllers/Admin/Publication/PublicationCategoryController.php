<?php

namespace App\Http\Controllers\Admin\Publication;

use App\Http\Controllers\Controller;
use App\Models\Publication\Agenda;
use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
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
 * CRUD Kategori Publikasi — memakai kit CRUD shared (pola menu academic).
 *
 * Paritas tabel & form Blade lama: hapus ditolak bila masih dipakai
 * berita/agenda/galeri — berlaku juga untuk bulk dan force-delete.
 * Total konten dihitung via withCount (tanpa N+1 seperti Blade lama).
 */
class PublicationCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,slug,sort_order,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = PublicationCategory::query()
            ->withCount(['news', 'agendas', 'galleries']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%"));
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $categories = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Publication/Category/Index', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Daftar Kategori Publikasi'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('publication-category.create'),
                'update' => ActivePermission::check('publication-category.update'),
                'delete' => ActivePermission::check('publication-category.delete'),
                'restore' => ActivePermission::any(['publication-category.update', 'publication-category.delete']),
                'toggle' => ActivePermission::check('publication-category.update'),
            ],
            'stats' => [
                'total' => PublicationCategory::count(),
                'active' => PublicationCategory::where('is_active', true)->count(),
                'contents' => News::count() + Agenda::count() + GalleryAlbum::count(),
                'trashed' => PublicationCategory::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($categories->items())->values()->map(fn ($c, $i) => [
                    'id' => $c->id,
                    'no' => ($categories->firstItem() ?? 0) + $i,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'desc' => $c->desc ? Str::limit(strip_tags($c->desc), 70) : null,
                    'sortOrder' => (int) $c->sort_order,
                    'newsCount' => (int) $c->news_count,
                    'agendaCount' => (int) $c->agendas_count,
                    'galleryCount' => (int) $c->galleries_count,
                    'contentCount' => (int) $c->news_count + (int) $c->agendas_count + (int) $c->galleries_count,
                    'isActive' => (bool) $c->is_active,
                    'createdAt' => $c->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.publication.categories.edit', $c->id),
                    'deleteUrl' => route('admin.publication.categories.destroy', $c->id),
                    'restoreUrl' => route('admin.publication.categories.restore', $c->id),
                    'forceUrl' => route('admin.publication.categories.force-destroy', $c->id),
                    'toggleUrl' => route('admin.publication.categories.toggle', $c->id),
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
                'index' => route('admin.publication.categories.index'),
                'create' => route('admin.publication.categories.create'),
                'export' => route('admin.publication.categories.export'),
                'exportPdf' => route('admin.publication.exports.pdf', ['resource' => 'categories']),
                'importTemplate' => route('admin.publication.categories.import-template'),
                'importSubmit' => route('admin.publication.categories.import'),
                'bulkDestroy' => route('admin.publication.categories.bulk-destroy'),
                'bulkRestore' => route('admin.publication.categories.bulk-restore'),
                'bulkForceDestroy' => route('admin.publication.categories.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Publication/Category/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Tambah Kategori Publikasi'),
            'mode' => 'create',
            'category' => ['name' => '', 'slug' => '', 'is_active' => true, 'sort_order' => 0, 'desc' => ''],
            'urls' => [
                'index' => route('admin.publication.categories.index'),
                'submit' => route('admin.publication.categories.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:publication_categories,slug',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer|min:0',
            'desc' => 'nullable|string|max:500',
        ]);

        $slug = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $category = PublicationCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'desc' => $validated['desc'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.publication.categories.index')
            ->with('success', 'Kategori "'.$category->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, PublicationCategory $category): Response
    {
        return Inertia::render('Admin/Publication/Category/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Edit Kategori Publikasi'),
            'mode' => 'edit',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_active' => (bool) $category->is_active,
                'sort_order' => (int) $category->sort_order,
                'desc' => $category->desc ?? '',
            ],
            'urls' => [
                'index' => route('admin.publication.categories.index'),
                'submit' => route('admin.publication.categories.update', $category),
            ],
        ]);
    }

    public function update(Request $request, PublicationCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('publication_categories', 'slug')->ignore($category->id)],
            'is_active' => 'boolean',
            'sort_order' => 'required|integer|min:0',
            'desc' => 'nullable|string|max:500',
        ]);

        $slug = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'desc' => $validated['desc'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.publication.categories.index')
            ->with('success', 'Kategori "'.$category->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, PublicationCategory $category)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $category->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status kategori berhasil diperbarui.');
    }

    public function destroy(PublicationCategory $category)
    {
        $used = $category->news()->count() + $category->agendas()->count() + $category->galleries()->count();

        if ($used > 0) {
            return back()->with('error', 'Kategori "'.$category->name.'" masih dipakai '.$used.' konten (berita/agenda/galeri). Pindahkan konten terlebih dahulu.');
        }

        $name = $category->name;
        $category->delete();

        return redirect()->route('admin.publication.categories.index')
            ->with('success', 'Kategori "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:publication_categories,id',
        ]);

        $categories = PublicationCategory::whereIn('id', $validated['ids'])
            ->withCount(['news', 'agendas', 'galleries'])->get();

        $blocked = $categories->filter(fn ($c) => ((int) $c->news_count + (int) $c->agendas_count + (int) $c->galleries_count) > 0);
        $deletable = $categories->filter(fn ($c) => ((int) $c->news_count + (int) $c->agendas_count + (int) $c->galleries_count) === 0);

        PublicationCategory::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' kategori dihapus. '.$blocked->count().' tidak bisa dihapus karena masih dipakai konten.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih dipakai konten (berita/agenda/galeri).');
        }

        return back()->with('success', $deletable->count().' kategori berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $category = PublicationCategory::withTrashed()->findOrFail($id);
        $category->restore();

        return back()->with('success', 'Kategori "'.$category->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $category = PublicationCategory::withTrashed()->findOrFail($id);

        $used = $category->news()->withTrashed()->count()
            + $category->agendas()->withTrashed()->count()
            + $category->galleries()->withTrashed()->count();

        if ($used > 0) {
            return back()->with('error', 'Kategori "'.$category->name.'" masih dipakai konten. Pindahkan konten terlebih dahulu.');
        }

        $name = $category->name;
        $category->forceDelete();

        return back()->with('success', 'Kategori "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:publication_categories,id',
        ]);

        $count = 0;

        foreach (PublicationCategory::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $category) {
            $category->restore();
            $count++;
        }

        return back()->with('success', $count.' kategori berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:publication_categories,id',
        ]);

        $categories = PublicationCategory::withTrashed()->whereIn('id', $validated['ids'])
            ->withCount([
                'news' => fn ($q) => $q->withTrashed(),
                'agendas' => fn ($q) => $q->withTrashed(),
                'galleries' => fn ($q) => $q->withTrashed(),
            ])->get();

        $blocked = $categories->filter(fn ($c) => ((int) $c->news_count + (int) $c->agendas_count + (int) $c->galleries_count) > 0);
        $deletable = $categories->filter(fn ($c) => ((int) $c->news_count + (int) $c->agendas_count + (int) $c->galleries_count) === 0);

        foreach ($deletable as $category) {
            $category->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' kategori dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih dipakai konten.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih dipakai konten.');
        }

        return back()->with('success', $deletable->count().' kategori dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = PublicationCategory::query()->withCount(['news', 'agendas', 'galleries']);

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
                ->orWhere('slug', 'like', "%{$q}%"));
        }

        $rows = $query->orderBy('sort_order')->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Slug', 'Deskripsi', 'Urutan', 'Berita', 'Agenda', 'Galeri', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $category) {
            $sheet->fromArray([
                $category->name,
                $category->slug,
                $category->desc,
                (int) $category->sort_order,
                (int) $category->news_count,
                (int) $category->agendas_count,
                (int) $category->galleries_count,
                $category->is_active ? 'Ya' : 'Tidak',
                $category->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'categories-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['name', 'slug', 'desc', 'sort_order', 'is_active']], null, 'A1');
        $sheet->fromArray([['Berita Kampus', 'berita-kampus', 'Berita seputar kampus', '0', '1']], null, 'A2');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-categories.xlsx',
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
        $missing = array_diff(['name', 'slug'], $header);

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

        $seenSlugs = [];
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

            $slug = isset($data['slug']) && $data['slug'] !== '' ? Str::slug((string) $data['slug']) : '';

            if ($slug === '') {
                $rowErrors[] = 'slug wajib diisi';
            } else {
                if (strlen($slug) > 100) {
                    $rowErrors[] = 'slug maksimal 100 karakter';
                } elseif (in_array($slug, $seenSlugs, true)) {
                    $rowErrors[] = 'slug duplikat di dalam file';
                } elseif (PublicationCategory::where('slug', $slug)->exists()) {
                    $rowErrors[] = 'slug sudah terdaftar';
                } else {
                    $seenSlugs[] = $slug;
                }
            }

            if (isset($data['sort_order']) && $data['sort_order'] !== '' && $data['sort_order'] !== null && ! is_numeric($data['sort_order'])) {
                $rowErrors[] = 'sort_order harus angka';
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
                'slug' => $slug,
                'desc' => $data['desc'] ?? null,
                'sort_order' => isset($data['sort_order']) && $data['sort_order'] !== '' ? (int) $data['sort_order'] : 0,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.publication.categories.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        PublicationCategory::insert($valid);

        return redirect()->route('admin.publication.categories.index')
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
