<?php

namespace App\Http\Controllers\Admin\Campus;

use App\Http\Controllers\Controller;
use App\Models\Campus\Building;
use App\Models\Campus\CampusLocation;
use App\Models\Campus\Room;
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

/**
 * CRUD Gedung — memakai kit CRUD shared (pola menu academic).
 *
 * Paritas Blade lama + koreksi: gedung terhubung ke lokasi kampus
 * (field mati di Blade lama), nonaktif cascade ke ruangan (klaim Blade
 * yang tidak pernah diimplementasikan), hapus ditolak bila masih
 * punya ruangan.
 */
class BuildingController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'location' => 'nullable|integer|exists:campus_locations,id',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Building::query()
            ->with('campusLocation:id,name')
            ->withCount('rooms as rooms_count');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('address', 'like', "%{$q}%"));
        }

        if (filled($validated['location'] ?? null)) {
            $query->where('campus_location_id', $validated['location']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $buildings = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Campus/Building/Index', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Daftar Gedung'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('building.create'),
                'update' => ActivePermission::check('building.update'),
                'delete' => ActivePermission::check('building.delete'),
                'restore' => ActivePermission::any(['building.update', 'building.delete']),
                'toggle' => ActivePermission::check('building.update'),
            ],
            'stats' => [
                'total' => Building::count(),
                'active' => Building::where('is_active', true)->count(),
                'rooms' => Room::count(),
                'trashed' => Building::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($buildings->items())->values()->map(fn ($b, $i) => [
                    'id' => $b->id,
                    'no' => ($buildings->firstItem() ?? 0) + $i,
                    'name' => $b->name,
                    'code' => $b->code,
                    'location' => $b->campusLocation?->name,
                    'floorCount' => $b->floor_count,
                    'roomCount' => (int) $b->rooms_count,
                    'isActive' => (bool) $b->is_active,
                    'createdAt' => $b->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.campus.buildings.edit', $b->id),
                    'deleteUrl' => route('admin.campus.buildings.destroy', $b->id),
                    'restoreUrl' => route('admin.campus.buildings.restore', $b->id),
                    'forceUrl' => route('admin.campus.buildings.force-destroy', $b->id),
                    'toggleUrl' => route('admin.campus.buildings.toggle', $b->id),
                ])->all(),
                'currentPage' => $buildings->currentPage(),
                'lastPage' => $buildings->lastPage(),
                'perPage' => $buildings->perPage(),
                'total' => $buildings->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'location' => $validated['location'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'locationOptions' => CampusLocation::query()->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.campus.buildings.index'),
                'create' => route('admin.campus.buildings.create'),
                'export' => route('admin.campus.buildings.export'),
                'exportPdf' => route('admin.campus.exports.pdf', ['resource' => 'buildings']),
                'importTemplate' => route('admin.campus.buildings.import-template'),
                'importSubmit' => route('admin.campus.buildings.import'),
                'bulkDestroy' => route('admin.campus.buildings.bulk-destroy'),
                'bulkRestore' => route('admin.campus.buildings.bulk-restore'),
                'bulkForceDestroy' => route('admin.campus.buildings.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Campus/Building/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Tambah Gedung'),
            'mode' => 'create',
            'building' => [
                'name' => '', 'code' => '', 'campus_location_id' => null,
                'address' => '', 'floor_count' => '', 'is_active' => true, 'desc' => '',
            ],
            'locationOptions' => $this->locationOptions(),
            'urls' => [
                'index' => route('admin.campus.buildings.index'),
                'submit' => route('admin.campus.buildings.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:buildings,code',
            'campus_location_id' => 'nullable|integer|exists:campus_locations,id',
            'address' => 'nullable|string',
            'floor_count' => 'nullable|integer|min:1|max:200',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $building = Building::create([
            'name' => $validated['name'],
            'code' => ($validated['code'] ?? null) ?: null,
            'campus_location_id' => $validated['campus_location_id'] ?? null,
            'address' => ($validated['address'] ?? null) ?: null,
            'floor_count' => ($validated['floor_count'] ?? null) ?: null,
            'is_active' => $validated['is_active'] ?? true,
            'desc' => ($validated['desc'] ?? null) ?: null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.buildings.index')
            ->with('success', 'Gedung "'.$building->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Building $building): Response
    {
        return Inertia::render('Admin/Campus/Building/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Edit Gedung'),
            'mode' => 'edit',
            'building' => [
                'id' => $building->id,
                'name' => $building->name,
                'code' => $building->code ?? '',
                'campus_location_id' => $building->campus_location_id,
                'address' => $building->address ?? '',
                'floor_count' => $building->floor_count !== null ? (string) $building->floor_count : '',
                'is_active' => (bool) $building->is_active,
                'desc' => $building->desc ?? '',
            ],
            'locationOptions' => $this->locationOptions(),
            'urls' => [
                'index' => route('admin.campus.buildings.index'),
                'submit' => route('admin.campus.buildings.update', $building),
            ],
        ]);
    }

    public function update(Request $request, Building $building)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('buildings', 'code')->ignore($building->id)],
            'campus_location_id' => 'nullable|integer|exists:campus_locations,id',
            'address' => 'nullable|string',
            'floor_count' => 'nullable|integer|min:1|max:200',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $building->update([
            'name' => $validated['name'],
            'code' => ($validated['code'] ?? null) ?: null,
            'campus_location_id' => $validated['campus_location_id'] ?? null,
            'address' => ($validated['address'] ?? null) ?: null,
            'floor_count' => ($validated['floor_count'] ?? null) ?: null,
            'is_active' => $validated['is_active'] ?? true,
            'desc' => ($validated['desc'] ?? null) ?: null,
            'updated_by' => $request->user()->id,
        ]);

        if (! $building->is_active) {
            $building->rooms()->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        }

        return redirect()->route('admin.campus.buildings.index')
            ->with('success', 'Gedung "'.$building->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, Building $building)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);
        $isActive = $validated['is_active'];

        $building->update(['is_active' => $isActive, 'updated_by' => $request->user()->id]);

        if (! $isActive) {
            $building->rooms()->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        }

        return back()->with('success', 'Status gedung berhasil diperbarui.');
    }

    public function destroy(Building $building)
    {
        if ($building->rooms()->count() > 0) {
            return back()->with('error', 'Gedung "'.$building->name.'" masih memiliki ruangan. Hapus atau pindahkan ruangan terlebih dahulu.');
        }

        $name = $building->name;
        $building->delete();

        return redirect()->route('admin.campus.buildings.index')
            ->with('success', 'Gedung "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:buildings,id',
        ]);

        $buildings = Building::whereIn('id', $validated['ids'])->withCount('rooms')->get();
        $blocked = $buildings->where('rooms_count', '>', 0);
        $deletable = $buildings->where('rooms_count', 0);

        Building::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' gedung dihapus. '.$blocked->count().' tidak bisa dihapus karena masih memiliki ruangan.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Gedung tidak bisa dihapus karena masih memiliki ruangan.');
        }

        return back()->with('success', $deletable->count().' gedung berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $building = Building::withTrashed()->findOrFail($id);
        $building->restore();

        return back()->with('success', 'Gedung "'.$building->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $building = Building::withTrashed()->findOrFail($id);

        if ($building->rooms()->withTrashed()->count() > 0) {
            return back()->with('error', 'Gedung "'.$building->name.'" masih memiliki ruangan. Hapus atau pindahkan ruangan terlebih dahulu.');
        }

        $name = $building->name;
        $building->forceDelete();

        return back()->with('success', 'Gedung "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:buildings,id',
        ]);

        $count = 0;

        foreach (Building::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $building) {
            $building->restore();
            $count++;
        }

        return back()->with('success', $count.' gedung berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:buildings,id',
        ]);

        $buildings = Building::withTrashed()->whereIn('id', $validated['ids'])->withCount(['rooms' => fn ($q) => $q->withTrashed()])->get();
        $blocked = $buildings->where('rooms_count', '>', 0);
        $deletable = $buildings->where('rooms_count', 0);

        foreach ($deletable as $building) {
            $building->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' gedung dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih memiliki ruangan.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Gedung tidak bisa dihapus karena masih memiliki ruangan.');
        }

        return back()->with('success', $deletable->count().' gedung dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Building::query()->with('campusLocation:id,name')->withCount('rooms');

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
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('address', 'like', "%{$q}%"));
        }

        $rows = $query->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Kode', 'Lokasi', 'Lantai', 'Jml Ruangan', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $building) {
            $sheet->fromArray([
                $building->name,
                $building->code,
                $building->campusLocation?->name,
                $building->floor_count,
                (int) $building->rooms_count,
                $building->is_active ? 'Ya' : 'Tidak',
                $building->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'buildings-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['name', 'code', 'location', 'address', 'floor_count', 'is_active']], null, 'A1');
        $sheet->fromArray([['Gedung Rektorat', 'BLD-RT', 'Kampus Utama', 'Jl. Contoh No. 1', '5', '1']], null, 'A2');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-buildings.xlsx',
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
            } elseif (strlen((string) $data['name']) > 255) {
                $rowErrors[] = 'name maksimal 255 karakter';
            }

            $code = $data['code'] ?? null;

            if ($code !== null && $code !== '') {
                if (strlen((string) $code) > 50) {
                    $rowErrors[] = 'code maksimal 50 karakter';
                } elseif (in_array($code, $seenCodes, true)) {
                    $rowErrors[] = 'code duplikat di dalam file';
                } elseif (Building::where('code', $code)->exists()) {
                    $rowErrors[] = 'code sudah terdaftar';
                } else {
                    $seenCodes[] = $code;
                }
            }

            $locationId = null;

            if (isset($data['location']) && $data['location'] !== '') {
                $locationId = CampusLocation::where('name', $data['location'])->value('id');

                if (! $locationId) {
                    $rowErrors[] = 'location tidak ditemukan';
                }
            }

            if (isset($data['floor_count']) && $data['floor_count'] !== '' && $data['floor_count'] !== null) {
                if (! is_numeric($data['floor_count']) || (int) $data['floor_count'] < 1) {
                    $rowErrors[] = 'floor_count minimal 1';
                }
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
                'code' => $code ?: null,
                'campus_location_id' => $locationId,
                'address' => $data['address'] ?? null,
                'floor_count' => isset($data['floor_count']) && $data['floor_count'] !== '' ? (int) $data['floor_count'] : null,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.campus.buildings.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        Building::insert($valid);

        return redirect()->route('admin.campus.buildings.index')
            ->with('success', count($valid).' gedung berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function locationOptions(): array
    {
        return CampusLocation::query()->where('is_active', true)
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all();
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }
}
