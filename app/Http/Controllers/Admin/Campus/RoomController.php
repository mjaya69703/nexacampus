<?php

namespace App\Http\Controllers\Admin\Campus;

use App\Http\Controllers\Controller;
use App\Models\Academic\CourseSchedule;
use App\Models\Campus\Building;
use App\Models\Campus\CampusAsset;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
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
 * CRUD Ruangan — memakai kit CRUD shared (pola menu academic).
 *
 * Paritas Blade lama + koreksi: hapus ditolak bila ruangan masih dipakai
 * jadwal kuliah, reservasi, aset, atau tiket maintenance (Blade lama
 * hapus langsung: jadwal/aset/tiket jadi null, riwayat reservasi ikut
 * terhapus permanen via cascade). Dropdown gedung hanya yang aktif.
 */
class RoomController extends Controller
{
    public const TYPES = ['Classroom', 'Laboratory', 'Auditorium', 'Office', 'Meeting Room', 'Library', 'Other'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'building' => 'nullable|integer|exists:buildings,id',
            'type' => 'nullable|string|max:50',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Room::query()->with('building:id,name');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($validated['building'] ?? null)) {
            $query->where('building_id', $validated['building']);
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
        $rooms = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Campus/Room/Index', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Daftar Ruangan'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('room.create'),
                'update' => ActivePermission::check('room.update'),
                'delete' => ActivePermission::check('room.delete'),
                'restore' => ActivePermission::any(['room.update', 'room.delete']),
                'toggle' => ActivePermission::check('room.update'),
            ],
            'stats' => [
                'total' => Room::count(),
                'active' => Room::where('is_active', true)->count(),
                'capacity' => (int) Room::query()->sum('capacity'),
                'trashed' => Room::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($rooms->items())->values()->map(fn ($r, $i) => [
                    'id' => $r->id,
                    'no' => ($rooms->firstItem() ?? 0) + $i,
                    'name' => $r->name,
                    'code' => $r->code,
                    'building' => $r->building?->name,
                    'floor' => $r->floor,
                    'capacity' => $r->capacity,
                    'type' => $r->type,
                    'usageCount' => $this->usageCount($r->id),
                    'isActive' => (bool) $r->is_active,
                    'createdAt' => $r->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.campus.rooms.edit', $r->id),
                    'deleteUrl' => route('admin.campus.rooms.destroy', $r->id),
                    'restoreUrl' => route('admin.campus.rooms.restore', $r->id),
                    'forceUrl' => route('admin.campus.rooms.force-destroy', $r->id),
                    'toggleUrl' => route('admin.campus.rooms.toggle', $r->id),
                ])->all(),
                'currentPage' => $rooms->currentPage(),
                'lastPage' => $rooms->lastPage(),
                'perPage' => $rooms->perPage(),
                'total' => $rooms->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'building' => $validated['building'] ?? '',
                'type' => $validated['type'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'buildingOptions' => Building::query()->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->all(),
            'typeOptions' => self::TYPES,
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.campus.rooms.index'),
                'create' => route('admin.campus.rooms.create'),
                'export' => route('admin.campus.rooms.export'),
                'exportPdf' => route('admin.campus.exports.pdf', ['resource' => 'rooms']),
                'importTemplate' => route('admin.campus.rooms.import-template'),
                'importSubmit' => route('admin.campus.rooms.import'),
                'bulkDestroy' => route('admin.campus.rooms.bulk-destroy'),
                'bulkRestore' => route('admin.campus.rooms.bulk-restore'),
                'bulkForceDestroy' => route('admin.campus.rooms.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Campus/Room/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Tambah Ruangan'),
            'mode' => 'create',
            'room' => [
                'building_id' => null, 'name' => '', 'code' => '', 'floor' => '',
                'capacity' => '', 'type' => 'Classroom', 'is_active' => true, 'desc' => '',
            ],
            'buildingOptions' => $this->buildingOptions(),
            'typeOptions' => self::TYPES,
            'urls' => [
                'index' => route('admin.campus.rooms.index'),
                'submit' => route('admin.campus.rooms.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'building_id' => 'nullable|integer|exists:buildings,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:rooms,code',
            'floor' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:100000',
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $room = Room::create([
            'building_id' => $validated['building_id'] ?? null,
            'name' => $validated['name'],
            'code' => ($validated['code'] ?? null) ?: null,
            'floor' => ($validated['floor'] ?? null) ?: null,
            'capacity' => ($validated['capacity'] ?? null) ?: null,
            'type' => $validated['type'],
            'is_active' => $validated['is_active'] ?? true,
            'desc' => ($validated['desc'] ?? null) ?: null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.rooms.index')
            ->with('success', 'Ruangan "'.$room->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Room $room): Response
    {
        return Inertia::render('Admin/Campus/Room/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Edit Ruangan'),
            'mode' => 'edit',
            'room' => [
                'id' => $room->id,
                'building_id' => $room->building_id,
                'name' => $room->name,
                'code' => $room->code ?? '',
                'floor' => $room->floor ?? '',
                'capacity' => $room->capacity !== null ? (string) $room->capacity : '',
                'type' => $room->type,
                'is_active' => (bool) $room->is_active,
                'desc' => $room->desc ?? '',
            ],
            'buildingOptions' => $this->buildingOptions(),
            'typeOptions' => self::TYPES,
            'urls' => [
                'index' => route('admin.campus.rooms.index'),
                'submit' => route('admin.campus.rooms.update', $room),
            ],
        ]);
    }

    public function update(Request $request, Room $room)
    {
        $validated = $request->validate([
            'building_id' => 'nullable|integer|exists:buildings,id',
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('rooms', 'code')->ignore($room->id)],
            'floor' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:100000',
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $room->update([
            'building_id' => $validated['building_id'] ?? null,
            'name' => $validated['name'],
            'code' => ($validated['code'] ?? null) ?: null,
            'floor' => ($validated['floor'] ?? null) ?: null,
            'capacity' => ($validated['capacity'] ?? null) ?: null,
            'type' => $validated['type'],
            'is_active' => $validated['is_active'] ?? true,
            'desc' => ($validated['desc'] ?? null) ?: null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campus.rooms.index')
            ->with('success', 'Ruangan "'.$room->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, Room $room)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $room->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status ruangan berhasil diperbarui.');
    }

    public function destroy(Room $room)
    {
        if ($used = $this->usageLabel($room->id)) {
            return back()->with('error', 'Ruangan "'.$room->name.'" masih dipakai '.$used.'. Pindahkan terlebih dahulu.');
        }

        $name = $room->name;
        $room->delete();

        return redirect()->route('admin.campus.rooms.index')
            ->with('success', 'Ruangan "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:rooms,id',
        ]);

        $rooms = Room::whereIn('id', $validated['ids'])->get();
        $blocked = $rooms->filter(fn ($r) => $this->usageCount($r->id) > 0);
        $deletable = $rooms->filter(fn ($r) => $this->usageCount($r->id) === 0);

        Room::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' ruangan dihapus. '.$blocked->count().' tidak bisa dihapus karena masih dipakai.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Ruangan tidak bisa dihapus karena masih dipakai jadwal, reservasi, aset, atau tiket.');
        }

        return back()->with('success', $deletable->count().' ruangan berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $room = Room::withTrashed()->findOrFail($id);
        $room->restore();

        return back()->with('success', 'Ruangan "'.$room->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $room = Room::withTrashed()->findOrFail($id);

        if ($used = $this->usageLabel($room->id, true)) {
            return back()->with('error', 'Ruangan "'.$room->name.'" masih dipakai '.$used.'. Pindahkan terlebih dahulu.');
        }

        $name = $room->name;
        $room->forceDelete();

        return back()->with('success', 'Ruangan "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:rooms,id',
        ]);

        $count = 0;

        foreach (Room::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $room) {
            $room->restore();
            $count++;
        }

        return back()->with('success', $count.' ruangan berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:rooms,id',
        ]);

        $rooms = Room::withTrashed()->whereIn('id', $validated['ids'])->get();
        $blocked = $rooms->filter(fn ($r) => $this->usageCount($r->id, true) > 0);
        $deletable = $rooms->filter(fn ($r) => $this->usageCount($r->id, true) === 0);

        foreach ($deletable as $room) {
            $room->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' ruangan dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih dipakai.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Ruangan tidak bisa dihapus karena masih dipakai.');
        }

        return back()->with('success', $deletable->count().' ruangan dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Room::query()->with('building:id,name');

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
        $sheet->fromArray([['Nama', 'Kode', 'Gedung', 'Lantai', 'Kapasitas', 'Tipe', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $room) {
            $sheet->fromArray([
                $room->name,
                $room->code,
                $room->building?->name,
                $room->floor,
                $room->capacity,
                $room->type,
                $room->is_active ? 'Ya' : 'Tidak',
                $room->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'rooms-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['name', 'code', 'building', 'floor', 'capacity', 'type', 'is_active']], null, 'A1');
        $sheet->fromArray([['Ruang 101', 'R-101', 'Gedung Kuliah', '1', '30', 'Classroom', '1']], null, 'A2');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-rooms.xlsx',
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
                } elseif (Room::where('code', $code)->exists()) {
                    $rowErrors[] = 'code sudah terdaftar';
                } else {
                    $seenCodes[] = $code;
                }
            }

            $buildingId = null;

            if (isset($data['building']) && $data['building'] !== '') {
                $buildingId = Building::where('name', $data['building'])->value('id');

                if (! $buildingId) {
                    $rowErrors[] = 'building tidak ditemukan';
                }
            }

            if (isset($data['capacity']) && $data['capacity'] !== '' && $data['capacity'] !== null) {
                if (! is_numeric($data['capacity']) || (int) $data['capacity'] < 1) {
                    $rowErrors[] = 'capacity minimal 1';
                }
            }

            $type = $data['type'] ?? 'Classroom';

            if (! in_array($type, self::TYPES, true)) {
                $rowErrors[] = 'type tidak valid ('.implode('/', self::TYPES).')';
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
                'building_id' => $buildingId,
                'floor' => $data['floor'] ?? null,
                'capacity' => isset($data['capacity']) && $data['capacity'] !== '' ? (int) $data['capacity'] : null,
                'type' => $type,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.campus.rooms.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        Room::insert($valid);

        return redirect()->route('admin.campus.rooms.index')
            ->with('success', count($valid).' ruangan berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function buildingOptions(): array
    {
        return Building::query()->where('is_active', true)
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->all();
    }

    /**
     * Jumlah pemakaian ruangan (jadwal + reservasi + aset + tiket).
     */
    private function usageCount(int $roomId, bool $withTrashed = false): int
    {
        $schedules = CourseSchedule::where('room_id', $roomId);
        $reservations = RoomReservation::where('room_id', $roomId);
        $assets = CampusAsset::where('room_id', $roomId);
        $tickets = FacilityMaintenanceTicket::where('room_id', $roomId);

        if ($withTrashed) {
            $schedules->withTrashed();
            $reservations->withTrashed();
            $assets->withTrashed();
            $tickets->withTrashed();
        }

        return $schedules->count() + $reservations->count() + $assets->count() + $tickets->count();
    }

    private function usageLabel(int $roomId, bool $withTrashed = false): ?string
    {
        $parts = [];

        $count = fn ($query) => $withTrashed ? $query->withTrashed()->where('room_id', $roomId)->count() : $query->where('room_id', $roomId)->count();

        if ($count(CourseSchedule::query()) > 0) {
            $parts[] = 'jadwal kuliah';
        }

        if ($count(RoomReservation::query()) > 0) {
            $parts[] = 'reservasi';
        }

        if ($count(CampusAsset::query()) > 0) {
            $parts[] = 'aset';
        }

        if ($count(FacilityMaintenanceTicket::query()) > 0) {
            $parts[] = 'tiket maintenance';
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }
}
