<?php

namespace App\Http\Controllers\Admin\Publication;

use App\Http\Controllers\Controller;
use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
 * CRUD Berita — memakai kit CRUD shared (pola menu academic).
 *
 * Paritas Blade lama + koreksi: hapus/ganti featured_image membersihkan
 * file storage (Blade lama bocor), slug auto-client, toggle pertahankan
 * published_at, kategori hanya yang aktif.
 */
class NewsController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|integer|exists:publication_categories,id',
            'is_published' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,title,published_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = News::query()
            ->with(['creator:id,first_name,last_name', 'category:id,name']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%"));
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category_id', $validated['category']);
        }

        if (isset($validated['is_published']) && $validated['is_published'] !== '') {
            $query->where('is_published', (bool) $validated['is_published']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $news = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Publication/News/Index', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Daftar Berita'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('news.create'),
                'update' => ActivePermission::check('news.update'),
                'delete' => ActivePermission::check('news.delete'),
                'restore' => ActivePermission::any(['news.update', 'news.delete']),
                'toggle' => ActivePermission::check('news.update'),
                'view' => ActivePermission::check('news.view'),
            ],
            'stats' => [
                'total' => News::count(),
                'published' => News::where('is_published', true)->count(),
                'draft' => News::where('is_published', false)->count(),
                'trashed' => News::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($news->items())->values()->map(fn ($n, $i) => [
                    'id' => $n->id,
                    'no' => ($news->firstItem() ?? 0) + $i,
                    'title' => Str::limit(strip_tags($n->title), 70),
                    'slug' => $n->slug,
                    'category' => $n->category?->name,
                    'isPublished' => (bool) $n->is_published,
                    'publishedAt' => $n->published_at?->format('d M Y H:i'),
                    'hasImage' => (bool) $n->featured_image,
                    'createdAt' => $n->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.publication.news.edit', $n->id),
                    'showUrl' => route('admin.publication.news.show', $n->id),
                    'deleteUrl' => route('admin.publication.news.destroy', $n->id),
                    'restoreUrl' => route('admin.publication.news.restore', $n->id),
                    'forceUrl' => route('admin.publication.news.force-destroy', $n->id),
                    'toggleUrl' => route('admin.publication.news.toggle', $n->id),
                ])->all(),
                'currentPage' => $news->currentPage(),
                'lastPage' => $news->lastPage(),
                'perPage' => $news->perPage(),
                'total' => $news->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'is_published' => $validated['is_published'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'categoryOptions' => PublicationCategory::query()->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.publication.news.index'),
                'create' => route('admin.publication.news.create'),
                'export' => route('admin.publication.news.export'),
                'exportPdf' => route('admin.publication.exports.pdf', ['resource' => 'news']),
                'importTemplate' => route('admin.publication.news.import-template'),
                'importSubmit' => route('admin.publication.news.import'),
                'bulkDestroy' => route('admin.publication.news.bulk-destroy'),
                'bulkRestore' => route('admin.publication.news.bulk-restore'),
                'bulkForceDestroy' => route('admin.publication.news.bulk-force-destroy'),
            ],
        ]);
    }

    public function show(Request $request, News $news): Response
    {
        $news->loadMissing(['creator:id,first_name,last_name', 'category:id,name']);

        return Inertia::render('Admin/Publication/News/Show', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Detail Berita'),
            'news' => [
                'id' => $news->id,
                'title' => $news->title,
                'slug' => $news->slug,
                'content' => $news->content,
                'excerpt' => $news->excerpt,
                'category' => $news->category?->name,
                'isPublished' => (bool) $news->is_published,
                'publishedAt' => $news->published_at?->format('d M Y H:i'),
                'imageUrl' => $news->featured_image ? $this->publicUrl($news->featured_image) : null,
                'creatorName' => $news->creator?->name ?? '-',
                'createdAt' => $news->created_at?->format('d M Y H:i'),
                'updatedAt' => $news->updated_at?->format('d M Y H:i'),
            ],
            'can' => [
                'update' => ActivePermission::check('news.update'),
                'delete' => ActivePermission::check('news.delete'),
            ],
            'urls' => [
                'index' => route('admin.publication.news.index'),
                'edit' => route('admin.publication.news.edit', $news),
                'destroy' => route('admin.publication.news.destroy', $news),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Publication/News/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Buat Berita'),
            'mode' => 'create',
            'news' => [
                'title' => '', 'slug' => '', 'category_id' => null,
                'excerpt' => '', 'content' => '',
                'is_published' => false, 'published_at' => now()->format('Y-m-d\TH:i'),
                'image_url' => null,
            ],
            'categoryOptions' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.publication.news.index'),
                'submit' => route('admin.publication.news.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:news,slug',
            'category_id' => 'nullable|integer|exists:publication_categories,id',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featured_image' => 'nullable|image|max:2048',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $data = [
            'title' => $validated['title'],
            'slug' => filled($validated['slug'] ?? null) ? Str::slug($validated['slug']) : Str::slug($validated['title']),
            'category_id' => $validated['category_id'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? ($validated['published_at'] ?? now()) : null,
            'created_by' => $request->user()->id,
        ];

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('news', 'public');
        }

        $item = News::create($data);

        return redirect()->route('admin.publication.news.index')
            ->with('success', 'Berita "'.$item->title.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, News $news): Response
    {
        return Inertia::render('Admin/Publication/News/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Edit Berita'),
            'mode' => 'edit',
            'news' => [
                'id' => $news->id,
                'title' => $news->title,
                'slug' => $news->slug,
                'category_id' => $news->category_id,
                'excerpt' => $news->excerpt ?? '',
                'content' => $news->content,
                'is_published' => (bool) $news->is_published,
                'published_at' => $news->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i'),
                'image_url' => $news->featured_image ? $this->publicUrl($news->featured_image) : null,
            ],
            'categoryOptions' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.publication.news.index'),
                'submit' => route('admin.publication.news.update', $news),
            ],
        ]);
    }

    public function update(Request $request, News $news)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('news', 'slug')->ignore($news->id)],
            'category_id' => 'nullable|integer|exists:publication_categories,id',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featured_image' => 'nullable|image|max:2048',
            'remove_image' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $data = [
            'title' => $validated['title'],
            'slug' => filled($validated['slug'] ?? null) ? Str::slug($validated['slug']) : Str::slug($validated['title']),
            'category_id' => $validated['category_id'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? ($validated['published_at'] ?? now()) : null,
            'updated_by' => $request->user()->id,
        ];

        if ($validated['remove_image'] ?? false) {
            $this->deleteImage($news);
            $data['featured_image'] = null;
        }

        if ($request->hasFile('featured_image')) {
            $this->deleteImage($news);
            $data['featured_image'] = $request->file('featured_image')->store('news', 'public');
        }

        $news->update($data);

        return redirect()->route('admin.publication.news.index')
            ->with('success', 'Berita "'.$news->title.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, News $news)
    {
        $validated = $request->validate(['is_published' => 'required|boolean']);

        $news->update([
            'is_published' => $validated['is_published'],
            'published_at' => $validated['is_published'] ? ($news->published_at ?? now()) : $news->published_at,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status berita berhasil diperbarui.');
    }

    public function destroy(News $news)
    {
        $this->deleteImage($news);

        $title = $news->title;
        $news->delete();

        return redirect()->route('admin.publication.news.index')
            ->with('success', 'Berita "'.$title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:news,id',
        ]);

        $items = News::whereIn('id', $validated['ids'])->get();

        foreach ($items as $item) {
            $this->deleteImage($item);
        }

        $count = $items->count();
        News::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' berita berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $news = News::withTrashed()->findOrFail($id);
        $news->restore();

        return back()->with('success', 'Berita "'.$news->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $news = News::withTrashed()->findOrFail($id);

        $this->deleteImage($news);

        $title = $news->title;
        $news->forceDelete();

        return back()->with('success', 'Berita "'.$title.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:news,id',
        ]);

        $count = 0;

        foreach (News::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $item) {
            $item->restore();
            $count++;
        }

        return back()->with('success', $count.' berita berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:news,id',
        ]);

        $items = News::withTrashed()->whereIn('id', $validated['ids'])->get();

        foreach ($items as $item) {
            $this->deleteImage($item);
            $item->forceDelete();
        }

        return back()->with('success', $items->count().' berita dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = News::query()->with('category:id,name');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%"));
        }

        if (filled($request->query('category'))) {
            $query->where('category_id', $request->query('category'));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Judul', 'Slug', 'Kategori', 'Status', 'Dipublikasi', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $item) {
            $sheet->fromArray([
                strip_tags($item->title),
                $item->slug,
                $item->category?->name,
                $item->is_published ? 'Published' : 'Draft',
                $item->published_at?->format('Y-m-d H:i:s'),
                $item->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'news-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['title', 'slug', 'category', 'excerpt', 'content', 'is_published']], null, 'A1');
        $sheet->fromArray([['Judul Berita', 'judul-berita', 'Berita Kampus', 'Ringkasan...', 'Isi lengkap...', '1']], null, 'A2');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-news.xlsx',
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
        $missing = array_diff(['title', 'content'], $header);

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

            if (($data['title'] ?? null) === null || $data['title'] === '') {
                $rowErrors[] = 'title wajib diisi';
            } elseif (strlen((string) $data['title']) > 255) {
                $rowErrors[] = 'title maksimal 255 karakter';
            }

            if (($data['content'] ?? null) === null || $data['content'] === '') {
                $rowErrors[] = 'content wajib diisi';
            }

            $slug = isset($data['slug']) && $data['slug'] !== '' ? Str::slug((string) $data['slug']) : '';

            if ($slug === '' && isset($data['title']) && $data['title'] !== '') {
                $slug = Str::slug((string) $data['title']);
            }

            if ($slug === '') {
                $rowErrors[] = 'slug wajib diisi';
            } elseif (strlen($slug) > 255) {
                $rowErrors[] = 'slug maksimal 255 karakter';
            } elseif (in_array($slug, $seenSlugs, true)) {
                $rowErrors[] = 'slug duplikat di dalam file';
            } elseif (News::where('slug', $slug)->exists()) {
                $rowErrors[] = 'slug sudah terdaftar';
            } else {
                $seenSlugs[] = $slug;
            }

            $categoryId = null;

            if (isset($data['category']) && $data['category'] !== '') {
                $categoryId = PublicationCategory::where('name', $data['category'])->value('id');

                if (! $categoryId) {
                    $rowErrors[] = 'category tidak ditemukan';
                }
            }

            if (isset($data['excerpt']) && strlen((string) $data['excerpt']) > 500) {
                $rowErrors[] = 'excerpt maksimal 500 karakter';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $isPublished = $this->parseImportBool($data['is_published'] ?? null, false);

            $valid[] = [
                'title' => $data['title'],
                'slug' => $slug,
                'category_id' => $categoryId,
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $data['content'],
                'is_published' => $isPublished,
                'published_at' => $isPublished ? now() : null,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.publication.news.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        News::insert($valid);

        return redirect()->route('admin.publication.news.index')
            ->with('success', count($valid).' berita berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function categoryOptions(): array
    {
        return PublicationCategory::query()->where('is_active', true)
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all();
    }

    private function deleteImage(News $news): void
    {
        if ($news->featured_image && Storage::disk('public')->exists($news->featured_image)) {
            Storage::disk('public')->delete($news->featured_image);
        }
    }

    /**
     * URL relatif ke file public storage — kebal beda APP_URL antar environment.
     */
    private function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/'.$path : null;
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }
}
