<?php

namespace App\Http\Controllers\Admin\Publication;

use App\Http\Controllers\Controller;
use App\Models\Publication\Agenda;
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
 * CRUD Agenda — memakai kit CRUD shared (pola menu academic).
 *
 * Disamakan dengan Berita: scope published ketat (flag + published_at),
 * toggle pertahankan published_at, slug auto-client. Tanpa file upload
 * (kolom file memang tidak ada di tabel agendas).
 */
class AgendaController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|integer|exists:publication_categories,id',
            'event_date' => 'nullable|date',
            'is_published' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,title,event_date,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Agenda::query()
            ->with(['creator:id,first_name,last_name', 'category:id,name']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('location', 'like', "%{$q}%"));
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category_id', $validated['category']);
        }

        if (filled($validated['event_date'] ?? null)) {
            $query->whereDate('event_date', $validated['event_date']);
        }

        if (isset($validated['is_published']) && $validated['is_published'] !== '') {
            $query->where('is_published', (bool) $validated['is_published']);
        }

        $sort = $validated['sort'] ?? 'event_date';
        $direction = $validated['direction'] ?? ($sort === 'event_date' ? 'asc' : 'desc');

        if ($sort === 'event_date') {
            $query->orderBy('event_date', $direction)->orderBy('event_time', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $agendas = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Publication/Agenda/Index', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Daftar Agenda'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('agenda.create'),
                'update' => ActivePermission::check('agenda.update'),
                'delete' => ActivePermission::check('agenda.delete'),
                'restore' => ActivePermission::any(['agenda.update', 'agenda.delete']),
                'toggle' => ActivePermission::check('agenda.update'),
                'view' => ActivePermission::check('agenda.view'),
            ],
            'stats' => [
                'total' => Agenda::count(),
                'published' => Agenda::where('is_published', true)->count(),
                'upcoming' => Agenda::where('event_date', '>=', now()->startOfDay())->count(),
                'past' => Agenda::where('event_date', '<', now()->startOfDay())->count(),
                'trashed' => Agenda::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($agendas->items())->values()->map(fn ($a, $i) => [
                    'id' => $a->id,
                    'no' => ($agendas->firstItem() ?? 0) + $i,
                    'title' => $a->title,
                    'slug' => $a->slug,
                    'category' => $a->category?->name,
                    'eventDate' => $a->event_date?->format('d M Y'),
                    'eventTime' => $a->event_time ? substr((string) $a->event_time, 0, 5) : null,
                    'location' => $a->location,
                    'isUpcoming' => $a->event_date && $a->event_date->gte(now()->startOfDay()),
                    'isPublished' => (bool) $a->is_published,
                    'publishedAt' => $a->published_at?->format('d M Y H:i'),
                    'createdAt' => $a->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.publication.agendas.edit', $a->id),
                    'showUrl' => route('admin.publication.agendas.show', $a->id),
                    'deleteUrl' => route('admin.publication.agendas.destroy', $a->id),
                    'restoreUrl' => route('admin.publication.agendas.restore', $a->id),
                    'forceUrl' => route('admin.publication.agendas.force-destroy', $a->id),
                    'toggleUrl' => route('admin.publication.agendas.toggle', $a->id),
                ])->all(),
                'currentPage' => $agendas->currentPage(),
                'lastPage' => $agendas->lastPage(),
                'perPage' => $agendas->perPage(),
                'total' => $agendas->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'event_date' => $validated['event_date'] ?? '',
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
                'index' => route('admin.publication.agendas.index'),
                'create' => route('admin.publication.agendas.create'),
                'export' => route('admin.publication.agendas.export'),
                'exportPdf' => route('admin.publication.exports.pdf', ['resource' => 'agendas']),
                'importTemplate' => route('admin.publication.agendas.import-template'),
                'importSubmit' => route('admin.publication.agendas.import'),
                'bulkDestroy' => route('admin.publication.agendas.bulk-destroy'),
                'bulkRestore' => route('admin.publication.agendas.bulk-restore'),
                'bulkForceDestroy' => route('admin.publication.agendas.bulk-force-destroy'),
            ],
        ]);
    }

    public function show(Request $request, Agenda $agenda): Response
    {
        $agenda->loadMissing(['creator:id,first_name,last_name', 'category:id,name']);

        return Inertia::render('Admin/Publication/Agenda/Show', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Detail Agenda'),
            'agenda' => [
                'id' => $agenda->id,
                'title' => $agenda->title,
                'slug' => $agenda->slug,
                'description' => $agenda->description,
                'category' => $agenda->category?->name,
                'location' => $agenda->location,
                'eventDate' => $agenda->event_date?->format('d M Y'),
                'eventTime' => $agenda->event_time ? substr((string) $agenda->event_time, 0, 5) : null,
                'eventEndDate' => $agenda->event_end_date?->format('d M Y'),
                'isUpcoming' => $agenda->event_date && $agenda->event_date->gte(now()->startOfDay()),
                'isPublished' => (bool) $agenda->is_published,
                'publishedAt' => $agenda->published_at?->format('d M Y H:i'),
                'creatorName' => $agenda->creator?->name ?? '-',
                'createdAt' => $agenda->created_at?->format('d M Y H:i'),
                'updatedAt' => $agenda->updated_at?->format('d M Y H:i'),
            ],
            'can' => [
                'update' => ActivePermission::check('agenda.update'),
                'delete' => ActivePermission::check('agenda.delete'),
            ],
            'urls' => [
                'index' => route('admin.publication.agendas.index'),
                'edit' => route('admin.publication.agendas.edit', $agenda),
                'destroy' => route('admin.publication.agendas.destroy', $agenda),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Publication/Agenda/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Buat Agenda'),
            'mode' => 'create',
            'agenda' => [
                'title' => '', 'slug' => '', 'category_id' => null,
                'description' => '', 'location' => '',
                'event_date' => '', 'event_time' => '', 'event_end_date' => '',
                'is_published' => false, 'published_at' => now()->format('Y-m-d\TH:i'),
            ],
            'categoryOptions' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.publication.agendas.index'),
                'submit' => route('admin.publication.agendas.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:agendas,slug',
            'category_id' => 'nullable|integer|exists:publication_categories,id',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'event_date' => 'required|date',
            'event_time' => 'nullable|date_format:H:i',
            'event_end_date' => 'nullable|date|after_or_equal:event_date',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $item = Agenda::create([
            'title' => $validated['title'],
            'slug' => filled($validated['slug'] ?? null) ? Str::slug($validated['slug']) : Str::slug($validated['title']),
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'event_date' => $validated['event_date'],
            'event_time' => ! empty($validated['event_time']) ? $validated['event_time'].':00' : null,
            'event_end_date' => $validated['event_end_date'] ?? null,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? ($validated['published_at'] ?? now()) : null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.publication.agendas.index')
            ->with('success', 'Agenda "'.$item->title.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Agenda $agenda): Response
    {
        return Inertia::render('Admin/Publication/Agenda/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Edit Agenda'),
            'mode' => 'edit',
            'agenda' => [
                'id' => $agenda->id,
                'title' => $agenda->title,
                'slug' => $agenda->slug,
                'category_id' => $agenda->category_id,
                'description' => $agenda->description ?? '',
                'location' => $agenda->location ?? '',
                'event_date' => $agenda->event_date?->format('Y-m-d') ?? '',
                'event_time' => $agenda->event_time ? substr((string) $agenda->event_time, 0, 5) : '',
                'event_end_date' => $agenda->event_end_date?->format('Y-m-d') ?? '',
                'is_published' => (bool) $agenda->is_published,
                'published_at' => $agenda->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i'),
            ],
            'categoryOptions' => $this->categoryOptions(),
            'urls' => [
                'index' => route('admin.publication.agendas.index'),
                'submit' => route('admin.publication.agendas.update', $agenda),
            ],
        ]);
    }

    public function update(Request $request, Agenda $agenda)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('agendas', 'slug')->ignore($agenda->id)],
            'category_id' => 'nullable|integer|exists:publication_categories,id',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'event_date' => 'required|date',
            'event_time' => 'nullable|date_format:H:i',
            'event_end_date' => 'nullable|date|after_or_equal:event_date',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $agenda->update([
            'title' => $validated['title'],
            'slug' => filled($validated['slug'] ?? null) ? Str::slug($validated['slug']) : Str::slug($validated['title']),
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'event_date' => $validated['event_date'],
            'event_time' => ! empty($validated['event_time']) ? $validated['event_time'].':00' : null,
            'event_end_date' => $validated['event_end_date'] ?? null,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? ($validated['published_at'] ?? now()) : null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.publication.agendas.index')
            ->with('success', 'Agenda "'.$agenda->title.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, Agenda $agenda)
    {
        $validated = $request->validate(['is_published' => 'required|boolean']);

        $agenda->update([
            'is_published' => $validated['is_published'],
            'published_at' => $validated['is_published'] ? ($agenda->published_at ?? now()) : $agenda->published_at,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status agenda berhasil diperbarui.');
    }

    public function destroy(Agenda $agenda)
    {
        $title = $agenda->title;
        $agenda->delete();

        return redirect()->route('admin.publication.agendas.index')
            ->with('success', 'Agenda "'.$title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:agendas,id',
        ]);

        $count = Agenda::whereIn('id', $validated['ids'])->count();
        Agenda::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' agenda berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $agenda = Agenda::withTrashed()->findOrFail($id);
        $agenda->restore();

        return back()->with('success', 'Agenda "'.$agenda->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $agenda = Agenda::withTrashed()->findOrFail($id);

        $title = $agenda->title;
        $agenda->forceDelete();

        return back()->with('success', 'Agenda "'.$title.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:agendas,id',
        ]);

        $count = 0;

        foreach (Agenda::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $item) {
            $item->restore();
            $count++;
        }

        return back()->with('success', $count.' agenda berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:agendas,id',
        ]);

        $count = Agenda::withTrashed()->whereIn('id', $validated['ids'])->count();
        Agenda::withTrashed()->whereIn('id', $validated['ids'])->forceDelete();

        return back()->with('success', $count.' agenda dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Agenda::query()->with('category:id,name');

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
                ->orWhere('location', 'like', "%{$q}%"));
        }

        if (filled($request->query('category'))) {
            $query->where('category_id', $request->query('category'));
        }

        $rows = $query->orderBy('event_date')->orderBy('event_time')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Judul', 'Slug', 'Kategori', 'Tanggal', 'Jam', 'Lokasi', 'Status', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $item) {
            $sheet->fromArray([
                $item->title,
                $item->slug,
                $item->category?->name,
                $item->event_date?->format('Y-m-d'),
                $item->event_time ? substr((string) $item->event_time, 0, 5) : null,
                $item->location,
                $item->is_published ? 'Published' : 'Draft',
                $item->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'agendas-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['title', 'slug', 'category', 'location', 'event_date', 'event_time', 'event_end_date', 'is_published']], null, 'A1');
        $sheet->fromArray([['Seminar Nasional', 'seminar-nasional', 'Seminar', 'Aula Utama', '2026-10-01', '09:00', '', '1']], null, 'A2');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-agendas.xlsx',
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
        $missing = array_diff(['title', 'event_date'], $header);

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
            } elseif (Agenda::where('slug', $slug)->exists()) {
                $rowErrors[] = 'slug sudah terdaftar';
            } else {
                $seenSlugs[] = $slug;
            }

            $eventDate = $this->parseImportDate($data['event_date'] ?? null);

            if (! $eventDate) {
                $rowErrors[] = 'event_date wajib diisi (YYYY-MM-DD)';
            }

            $eventTime = null;

            if (isset($data['event_time']) && $data['event_time'] !== '') {
                $eventTime = $this->parseImportTime($data['event_time']);

                if (! $eventTime) {
                    $rowErrors[] = 'event_time tidak valid (HH:MM)';
                }
            }

            $eventEndDate = null;

            if (isset($data['event_end_date']) && $data['event_end_date'] !== '') {
                $eventEndDate = $this->parseImportDate($data['event_end_date']);

                if (! $eventEndDate) {
                    $rowErrors[] = 'event_end_date tidak valid (YYYY-MM-DD)';
                } elseif ($eventDate && $eventEndDate < $eventDate) {
                    $rowErrors[] = 'event_end_date harus >= event_date';
                }
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
                'location' => $data['location'] ?? null,
                'event_date' => $eventDate,
                'event_time' => $eventTime,
                'event_end_date' => $eventEndDate,
                'is_published' => $isPublished,
                'published_at' => $isPublished ? now() : null,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.publication.agendas.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        Agenda::insert($valid);

        return redirect()->route('admin.publication.agendas.index')
            ->with('success', count($valid).' agenda berhasil diimpor.')
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

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }

    private function parseImportDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseImportTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:00');
        }

        $value = trim((string) $value);

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('H:i:00');
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2].':'.($m[3] ?? '00');
        }

        return null;
    }
}
