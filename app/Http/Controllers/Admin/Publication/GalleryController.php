<?php

namespace App\Http\Controllers\Admin\Publication;

use App\Http\Controllers\Controller;
use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\GalleryImage;
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
 * CRUD Album Galeri — memakai kit CRUD shared (pola menu academic).
 *
 * Khusus menu ini: foto dikelola sebagai sub-resource (upload multi,
 * caption inline, hapus, jadikan sampul) dengan endpoint kecil terpisah
 * agar paritas aksi Blade lama (saveNewImages/updateImageCaption/
 * removeImage) — plus gate permission yang Blade lama lewatkan.
 * Hapus album membersihkan baris foto DB + semua file (Blade lama
 * menyisakan baris gallery_images yatim).
 */
class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|integer|exists:publication_categories,id',
            'is_published' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,title,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = GalleryAlbum::query()
            ->with(['creator:id,first_name,last_name', 'category:id,name'])
            ->withCount('images');

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
        $albums = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Publication/Gallery/Index', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Daftar Album Galeri'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('gallery.create'),
                'update' => ActivePermission::check('gallery.update'),
                'delete' => ActivePermission::check('gallery.delete'),
                'restore' => ActivePermission::any(['gallery.update', 'gallery.delete']),
                'toggle' => ActivePermission::check('gallery.update'),
                'view' => ActivePermission::check('gallery.view'),
            ],
            'stats' => [
                'total' => GalleryAlbum::count(),
                'published' => GalleryAlbum::where('is_published', true)->count(),
                'images' => GalleryImage::whereHas('album')->count(),
                'trashed' => GalleryAlbum::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($albums->items())->values()->map(fn ($a, $i) => [
                    'id' => $a->id,
                    'no' => ($albums->firstItem() ?? 0) + $i,
                    'title' => Str::limit($a->title, 50),
                    'slug' => $a->slug,
                    'category' => $a->category?->name,
                    'coverUrl' => $a->cover_image_path ? $this->publicUrl($a->cover_image_path) : null,
                    'imagesCount' => (int) $a->images_count,
                    'isPublished' => (bool) $a->is_published,
                    'createdAt' => $a->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.publication.galleries.edit', $a->id),
                    'showUrl' => route('admin.publication.galleries.show', $a->id),
                    'deleteUrl' => route('admin.publication.galleries.destroy', $a->id),
                    'restoreUrl' => route('admin.publication.galleries.restore', $a->id),
                    'forceUrl' => route('admin.publication.galleries.force-destroy', $a->id),
                    'toggleUrl' => route('admin.publication.galleries.toggle', $a->id),
                ])->all(),
                'currentPage' => $albums->currentPage(),
                'lastPage' => $albums->lastPage(),
                'perPage' => $albums->perPage(),
                'total' => $albums->total(),
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
                'index' => route('admin.publication.galleries.index'),
                'create' => route('admin.publication.galleries.create'),
                'export' => route('admin.publication.galleries.export'),
                'exportPdf' => route('admin.publication.exports.pdf', ['resource' => 'galleries']),
                'importTemplate' => route('admin.publication.galleries.import-template'),
                'importSubmit' => route('admin.publication.galleries.import'),
                'bulkDestroy' => route('admin.publication.galleries.bulk-destroy'),
                'bulkRestore' => route('admin.publication.galleries.bulk-restore'),
                'bulkForceDestroy' => route('admin.publication.galleries.bulk-force-destroy'),
            ],
        ]);
    }

    public function show(Request $request, GalleryAlbum $gallery): Response
    {
        $gallery->loadMissing(['creator:id,first_name,last_name', 'category:id,name', 'images']);

        return Inertia::render('Admin/Publication/Gallery/Show', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Detail Album Galeri'),
            'album' => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'description' => $gallery->description,
                'category' => $gallery->category?->name,
                'isPublished' => (bool) $gallery->is_published,
                'coverUrl' => $this->coverUrl($gallery),
                'creatorName' => $gallery->creator?->name ?? '-',
                'createdAt' => $gallery->created_at?->format('d M Y H:i'),
                'updatedAt' => $gallery->updated_at?->format('d M Y H:i'),
                'images' => $gallery->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $this->publicUrl($img->image_path),
                    'caption' => $img->caption,
                ])->all(),
            ],
            'can' => [
                'update' => ActivePermission::check('gallery.update'),
                'delete' => ActivePermission::check('gallery.delete'),
            ],
            'urls' => [
                'index' => route('admin.publication.galleries.index'),
                'edit' => route('admin.publication.galleries.edit', $gallery),
                'destroy' => route('admin.publication.galleries.destroy', $gallery),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Publication/Gallery/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Buat Album Galeri'),
            'mode' => 'create',
            'album' => [
                'title' => '', 'slug' => '', 'category_id' => null,
                'description' => '', 'is_published' => false,
                'cover_url' => null,
            ],
            'images' => [],
            'categoryOptions' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.publication.galleries.index'),
                'submit' => route('admin.publication.galleries.store'),
                'storeImages' => null,
                'updateCaption' => null,
                'destroyImage' => null,
                'setCover' => null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:gallery_albums,slug',
            'category_id' => 'nullable|integer|exists:publication_categories,id',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'new_images' => 'nullable|array|max:20',
            'new_images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_published' => 'boolean',
        ]);

        $album = GalleryAlbum::create([
            'title' => $validated['title'],
            'slug' => filled($validated['slug'] ?? null) ? Str::slug($validated['slug']) : Str::slug($validated['title']),
            'category_id' => $validated['category_id'] ?? null,
            'description' => ($validated['description'] ?? null) ?: null,
            'is_published' => $validated['is_published'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('cover_image')) {
            $album->update(['cover_image_path' => $request->file('cover_image')->store('galleries', 'public')]);
        }

        $this->storeImageFiles($album, $request->file('new_images', []), $request->user()->id);

        return redirect()->route('admin.publication.galleries.index')
            ->with('success', 'Album "'.$album->title.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, GalleryAlbum $gallery): Response
    {
        $gallery->loadMissing('images');

        return Inertia::render('Admin/Publication/Gallery/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Edit Album Galeri'),
            'mode' => 'edit',
            'album' => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'category_id' => $gallery->category_id,
                'description' => $gallery->description ?? '',
                'is_published' => (bool) $gallery->is_published,
                'cover_url' => $gallery->cover_image_path ? $this->publicUrl($gallery->cover_image_path) : null,
            ],
            'images' => $gallery->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $this->publicUrl($img->image_path),
                'caption' => $img->caption ?? '',
                'sortOrder' => (int) $img->sort_order,
                'updateUrl' => route('admin.publication.gallery-images.update', $img),
                'destroyUrl' => route('admin.publication.gallery-images.destroy', $img),
                'coverUrl' => route('admin.publication.gallery-images.cover', $img),
            ])->all(),
            'categoryOptions' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.publication.galleries.index'),
                'submit' => route('admin.publication.galleries.update', $gallery),
                'storeImages' => route('admin.publication.galleries.images.store', $gallery),
            ],
        ]);
    }

    public function update(Request $request, GalleryAlbum $gallery)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('gallery_albums', 'slug')->ignore($gallery->id)],
            'category_id' => 'nullable|integer|exists:publication_categories,id',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_cover' => 'boolean',
            'new_images' => 'nullable|array|max:20',
            'new_images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_published' => 'boolean',
        ]);

        if ($validated['remove_cover'] ?? false) {
            $this->deleteFile($gallery->cover_image_path);
            $gallery->cover_image_path = null;
        }

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('galleries', 'public');
            $this->deleteFile($gallery->cover_image_path);
            $gallery->cover_image_path = $path;
        }

        $gallery->update([
            'title' => $validated['title'],
            'slug' => filled($validated['slug'] ?? null) ? Str::slug($validated['slug']) : Str::slug($validated['title']),
            'category_id' => $validated['category_id'] ?? null,
            'description' => ($validated['description'] ?? null) ?: null,
            'cover_image_path' => $gallery->cover_image_path,
            'is_published' => $validated['is_published'] ?? false,
            'updated_by' => $request->user()->id,
        ]);

        $this->storeImageFiles($gallery, $request->file('new_images', []), $request->user()->id);

        return redirect()->route('admin.publication.galleries.index')
            ->with('success', 'Album "'.$gallery->title.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, GalleryAlbum $gallery)
    {
        $validated = $request->validate(['is_published' => 'required|boolean']);

        $gallery->update(['is_published' => $validated['is_published'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status album berhasil diperbarui.');
    }

    public function destroy(GalleryAlbum $gallery)
    {
        $this->deleteAlbumFiles($gallery);

        $title = $gallery->title;
        $gallery->images()->delete();
        $gallery->delete();

        return redirect()->route('admin.publication.galleries.index')
            ->with('success', 'Album "'.$title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:gallery_albums,id',
        ]);

        $albums = GalleryAlbum::whereIn('id', $validated['ids'])->get();

        foreach ($albums as $album) {
            $this->deleteAlbumFiles($album);
            $album->images()->delete();
        }

        $count = $albums->count();
        GalleryAlbum::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' album berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $album = GalleryAlbum::withTrashed()->findOrFail($id);
        $album->restore();

        return back()->with('success', 'Album "'.$album->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $album = GalleryAlbum::withTrashed()->findOrFail($id);

        $this->deleteAlbumFiles($album);
        $album->images()->delete();

        $title = $album->title;
        $album->forceDelete();

        return back()->with('success', 'Album "'.$title.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:gallery_albums,id',
        ]);

        $count = 0;

        foreach (GalleryAlbum::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $album) {
            $album->restore();
            $count++;
        }

        return back()->with('success', $count.' album berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:gallery_albums,id',
        ]);

        $albums = GalleryAlbum::withTrashed()->whereIn('id', $validated['ids'])->get();

        foreach ($albums as $album) {
            $this->deleteAlbumFiles($album);
            $album->images()->delete();
            $album->forceDelete();
        }

        return back()->with('success', $albums->count().' album dihapus permanen.');
    }

    public function storeImages(Request $request, GalleryAlbum $gallery)
    {
        $validated = $request->validate([
            'images' => 'required|array|min:1|max:20',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $count = $this->storeImageFiles($gallery, $validated['images'], $request->user()->id);

        return back()->with('success', $count.' foto berhasil ditambahkan.');
    }

    public function updateImage(Request $request, GalleryImage $image)
    {
        $validated = $request->validate(['caption' => 'nullable|string|max:255']);

        $image->update(['caption' => $validated['caption'] ?? null]);

        return back()->with('success', 'Keterangan foto diperbarui.');
    }

    public function destroyImage(GalleryImage $image)
    {
        $this->deleteFile($image->image_path);
        $image->delete();

        return back()->with('success', 'Foto dihapus.');
    }

    public function setCover(GalleryImage $image)
    {
        $album = $image->album;

        if (! Storage::disk('public')->exists($image->image_path)) {
            return back()->with('error', 'File foto tidak ditemukan di storage.');
        }

        $extension = pathinfo($image->image_path, PATHINFO_EXTENSION) ?: 'jpg';
        $newPath = 'galleries/cover-'.$album->id.'-'.$image->id.'-'.now()->format('YmdHis').'.'.$extension;

        Storage::disk('public')->copy($image->image_path, $newPath);
        $this->deleteFile($album->cover_image_path);

        $album->update(['cover_image_path' => $newPath, 'updated_by' => request()->user()->id]);

        return back()->with('success', 'Sampul album diperbarui dari foto.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = GalleryAlbum::query()->with('category:id,name')->withCount('images');

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
        $sheet->fromArray([['Judul', 'Slug', 'Kategori', 'Jml Foto', 'Status', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $album) {
            $sheet->fromArray([
                $album->title,
                $album->slug,
                $album->category?->name,
                (int) $album->images_count,
                $album->is_published ? 'Published' : 'Draft',
                $album->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'galleries-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['title', 'slug', 'category', 'description', 'is_published']], null, 'A1');
        $sheet->fromArray([['Wisuda 2026', 'wisuda-2026', 'Acara', 'Dokumentasi wisuda.', '1']], null, 'A2');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-galleries.xlsx',
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
        $missing = array_diff(['title'], $header);

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
            } elseif (GalleryAlbum::where('slug', $slug)->exists()) {
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
                'description' => $data['description'] ?? null,
                'is_published' => $isPublished,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.publication.galleries.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        GalleryAlbum::insert($valid);

        return redirect()->route('admin.publication.galleries.index')
            ->with('success', count($valid).' album berhasil diimpor.')
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

    private function coverUrl(GalleryAlbum $album): ?string
    {
        if ($album->cover_image_path) {
            return $this->publicUrl($album->cover_image_path);
        }

        $first = $album->images->first();

        return $first ? $this->publicUrl($first->image_path) : null;
    }

    private function storeImageFiles(GalleryAlbum $album, mixed $files, int $userId): int
    {
        $files = array_values(array_filter((array) $files));

        if (empty($files)) {
            return 0;
        }

        $order = (int) ($album->images()->max('sort_order') ?? 0);

        foreach ($files as $file) {
            if (! $file || is_string($file)) {
                continue;
            }

            $order++;

            $album->images()->create([
                'image_path' => $file->store('galleries', 'public'),
                'sort_order' => $order,
                'created_by' => $userId,
            ]);
        }

        return count($files);
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function deleteAlbumFiles(GalleryAlbum $album): void
    {
        foreach ($album->images()->pluck('image_path')->all() as $path) {
            $this->deleteFile($path);
        }

        $this->deleteFile($album->cover_image_path);
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
