<?php

namespace App\Http\Controllers\Admin\Publication;

use App\Enums\FaqType;
use App\Http\Controllers\Controller;
use App\Models\Publication\Faq;
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
 * CRUD FAQ — memakai kit CRUD shared (pola menu academic).
 *
 * Paritas tabel & form Blade lama: tipe enum FaqType, kelompok bebas
 * (string, bukan FK ke Kategori Publikasi), jawaban rich-text.
 * Urutan tampil via sort_order; default admin newest-first seperti academic.
 */
class FaqController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|string',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,question,category,sort_order,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        if (filled($validated['type'] ?? null)) {
            $request->validate(['type' => Rule::enum(FaqType::class)]);
        }

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Faq::query();

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('question', 'like', "%{$q}%")
                ->orWhere('category', 'like', "%{$q}%"));
        }

        if (filled($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $faqs = $query->paginate($perPage)->withQueryString();

        $typeLabels = collect(FaqType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();

        return Inertia::render('Admin/Publication/Faq/Index', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Daftar FAQ'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('faq.create'),
                'update' => ActivePermission::check('faq.update'),
                'delete' => ActivePermission::check('faq.delete'),
                'restore' => ActivePermission::any(['faq.update', 'faq.delete']),
                'toggle' => ActivePermission::check('faq.update'),
            ],
            'stats' => [
                'total' => Faq::count(),
                'active' => Faq::where('is_active', true)->count(),
                'groups' => Faq::query()->distinct()->count('category'),
                'trashed' => Faq::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($faqs->items())->values()->map(fn ($f, $i) => [
                    'id' => $f->id,
                    'no' => ($faqs->firstItem() ?? 0) + $i,
                    'type' => $f->type instanceof FaqType ? $f->type->value : (string) $f->type,
                    'typeLabel' => $f->type instanceof FaqType ? $f->type->label() : (string) $f->type,
                    'category' => $f->category,
                    'question' => Str::limit(strip_tags($f->question), 70),
                    'sortOrder' => (int) $f->sort_order,
                    'isActive' => (bool) $f->is_active,
                    'createdAt' => $f->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.publication.faqs.edit', $f->id),
                    'deleteUrl' => route('admin.publication.faqs.destroy', $f->id),
                    'restoreUrl' => route('admin.publication.faqs.restore', $f->id),
                    'forceUrl' => route('admin.publication.faqs.force-destroy', $f->id),
                    'toggleUrl' => route('admin.publication.faqs.toggle', $f->id),
                ])->all(),
                'currentPage' => $faqs->currentPage(),
                'lastPage' => $faqs->lastPage(),
                'perPage' => $faqs->perPage(),
                'total' => $faqs->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'type' => $validated['type'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'typeOptions' => collect(FaqType::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'typeLabels' => $typeLabels,
            'categoryOptions' => Faq::query()->distinct()->orderBy('category')->pluck('category')->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.publication.faqs.index'),
                'create' => route('admin.publication.faqs.create'),
                'export' => route('admin.publication.faqs.export'),
                'exportPdf' => route('admin.publication.exports.pdf', ['resource' => 'faqs']),
                'importTemplate' => route('admin.publication.faqs.import-template'),
                'importSubmit' => route('admin.publication.faqs.import'),
                'bulkDestroy' => route('admin.publication.faqs.bulk-destroy'),
                'bulkRestore' => route('admin.publication.faqs.bulk-restore'),
                'bulkForceDestroy' => route('admin.publication.faqs.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Publication/Faq/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Tambah FAQ'),
            'mode' => 'create',
            'faq' => ['type' => FaqType::GENERAL->value, 'category' => 'Umum', 'question' => '', 'answer' => '', 'sort_order' => 0, 'is_active' => true],
            'typeOptions' => collect(FaqType::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'categoryOptions' => Faq::query()->distinct()->orderBy('category')->pluck('category')->all(),
            'urls' => [
                'index' => route('admin.publication.faqs.index'),
                'submit' => route('admin.publication.faqs.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(FaqType::class)],
            'category' => 'required|string|max:100',
            'question' => 'required|string|max:1000',
            'answer' => 'required|string',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $faq = Faq::create([
            'type' => $validated['type'] instanceof FaqType ? $validated['type']->value : $validated['type'],
            'category' => $validated['category'],
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.publication.faqs.index')
            ->with('success', 'FAQ "'.Str::limit($faq->question, 60).'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Faq $faq): Response
    {
        return Inertia::render('Admin/Publication/Faq/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Edit FAQ'),
            'mode' => 'edit',
            'faq' => [
                'id' => $faq->id,
                'type' => $faq->type instanceof FaqType ? $faq->type->value : (string) $faq->type,
                'category' => $faq->category,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => (int) $faq->sort_order,
                'is_active' => (bool) $faq->is_active,
            ],
            'typeOptions' => collect(FaqType::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'categoryOptions' => Faq::query()->distinct()->orderBy('category')->pluck('category')->all(),
            'urls' => [
                'index' => route('admin.publication.faqs.index'),
                'submit' => route('admin.publication.faqs.update', $faq),
            ],
        ]);
    }

    public function update(Request $request, Faq $faq)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(FaqType::class)],
            'category' => 'required|string|max:100',
            'question' => 'required|string|max:1000',
            'answer' => 'required|string',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $faq->update([
            'type' => $validated['type'] instanceof FaqType ? $validated['type']->value : $validated['type'],
            'category' => $validated['category'],
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.publication.faqs.index')
            ->with('success', 'FAQ berhasil diperbarui.');
    }

    public function toggle(Request $request, Faq $faq)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $faq->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status FAQ berhasil diperbarui.');
    }

    public function destroy(Faq $faq)
    {
        $question = Str::limit($faq->question, 60);
        $faq->delete();

        return redirect()->route('admin.publication.faqs.index')
            ->with('success', 'FAQ "'.$question.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:faqs,id',
        ]);

        $count = Faq::whereIn('id', $validated['ids'])->count();
        Faq::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' FAQ berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $faq = Faq::withTrashed()->findOrFail($id);
        $faq->restore();

        return back()->with('success', 'FAQ berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $faq = Faq::withTrashed()->findOrFail($id);
        $faq->forceDelete();

        return back()->with('success', 'FAQ dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:faqs,id',
        ]);

        $count = 0;

        foreach (Faq::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $faq) {
            $faq->restore();
            $count++;
        }

        return back()->with('success', $count.' FAQ berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:faqs,id',
        ]);

        $count = 0;

        foreach (Faq::withTrashed()->whereIn('id', $validated['ids'])->get() as $faq) {
            $faq->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' FAQ dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Faq::query();

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('question', 'like', "%{$q}%")
                ->orWhere('category', 'like', "%{$q}%"));
        }

        if (filled($request->query('type'))) {
            $query->where('type', $request->query('type'));
        }

        $rows = $query->orderBy('sort_order')->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Tipe', 'Kelompok', 'Pertanyaan', 'Jawaban', 'Urutan', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $faq) {
            $sheet->fromArray([
                $faq->type instanceof FaqType ? $faq->type->label() : (string) $faq->type,
                $faq->category,
                strip_tags($faq->question),
                Str::limit(strip_tags($faq->answer), 500),
                (int) $faq->sort_order,
                $faq->is_active ? 'Ya' : 'Tidak',
                $faq->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'faqs-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['type', 'category', 'question', 'answer', 'sort_order', 'is_active']], null, 'A1');
        $sheet->fromArray([['general', 'Umum', 'Bagaimana cara mendaftar?', 'Pendaftaran melalui laman PMB kampus.', '0', '1']], null, 'A2');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-faqs.xlsx',
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
        $missing = array_diff(['type', 'category', 'question', 'answer'], $header);

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

        $allowedTypes = collect(FaqType::cases())->map(fn ($c) => $c->value)->all();
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

            if (($data['type'] ?? null) === null || $data['type'] === '') {
                $rowErrors[] = 'type wajib diisi';
            } elseif (! in_array($data['type'], $allowedTypes, true)) {
                $rowErrors[] = 'type tidak valid ('.implode('/', $allowedTypes).')';
            }

            if (($data['category'] ?? null) === null || $data['category'] === '') {
                $rowErrors[] = 'category wajib diisi';
            } elseif (strlen((string) $data['category']) > 100) {
                $rowErrors[] = 'category maksimal 100 karakter';
            }

            if (($data['question'] ?? null) === null || $data['question'] === '') {
                $rowErrors[] = 'question wajib diisi';
            } elseif (strlen((string) $data['question']) > 1000) {
                $rowErrors[] = 'question maksimal 1000 karakter';
            }

            if (($data['answer'] ?? null) === null || $data['answer'] === '') {
                $rowErrors[] = 'answer wajib diisi';
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
                'type' => $data['type'],
                'category' => $data['category'],
                'question' => $data['question'],
                'answer' => $data['answer'],
                'sort_order' => isset($data['sort_order']) && $data['sort_order'] !== '' ? (int) $data['sort_order'] : 0,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.publication.faqs.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        Faq::insert($valid);

        return redirect()->route('admin.publication.faqs.index')
            ->with('success', count($valid).' FAQ berhasil diimpor.')
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
