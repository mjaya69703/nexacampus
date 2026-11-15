<?php

namespace App\Http\Controllers\Admin\Campus;

use App\Http\Controllers\Controller;
use App\Models\Campus\Building;
use App\Models\Campus\CampusLocation;
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
 * CRUD Lokasi Kampus — memakai kit CRUD shared (pola menu academic).
 *
 * Khusus menu ini: hanya SATU lokasi boleh jadi kampus utama
 * (pola satu-aktif AcademicYear) agar peta publik deterministik,
 * hapus ditolak bila masih punya gedung, GPS dibatasi rentang valid.
 */
class CampusLocationController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_main' => 'nullable|in:0,1',
            'is_attendance' => 'nullable|in:0,1',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = CampusLocation::query()->withCount('buildings as buildings_count');

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

        foreach (['is_main', 'is_active'] as $field) {
            if (isset($validated[$field]) && $validated[$field] !== '') {
                $query->where($field, (bool) $validated[$field]);
            }
        }

        if (isset($validated['is_attendance']) && $validated['is_attendance'] !== '') {
            $query->where('is_attendance_point', (bool) $validated['is_attendance']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $locations = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Campus/Location/Index', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Daftar Lokasi Kampus'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('campus-location.create'),
                'update' => ActivePermission::check('campus-location.update'),
                'delete' => ActivePermission::check('campus-location.delete'),
                'restore' => ActivePermission::any(['campus-location.update', 'campus-location.delete']),
                'toggle' => ActivePermission::check('campus-location.update'),
            ],
            'stats' => [
                'total' => CampusLocation::count(),
                'active' => CampusLocation::where('is_active', true)->count(),
                'attendance' => CampusLocation::attendancePoints()->count(),
                'buildings' => Building::count(),
                'trashed' => CampusLocation::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($locations->items())->values()->map(fn ($l, $i) => [
                    'id' => $l->id,
                    'no' => ($locations->firstItem() ?? 0) + $i,
                    'name' => $l->name,
                    'code' => $l->code,
                    'address' => $l->address,
                    'buildingCount' => (int) $l->buildings_count,
                    'isMain' => (bool) $l->is_main,
                    'isAttendancePoint' => (bool) $l->is_attendance_point,
                    'isActive' => (bool) $l->is_active,
                    'createdAt' => $l->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.campus.locations.edit', $l->id),
                    'deleteUrl' => route('admin.campus.locations.destroy', $l->id),
                    'restoreUrl' => route('admin.campus.locations.restore', $l->id),
                    'forceUrl' => route('admin.campus.locations.force-destroy', $l->id),
                    'toggleUrl' => route('admin.campus.locations.toggle', $l->id),
                    'mainUrl' => route('admin.campus.locations.main', $l->id),
                ])->all(),
                'currentPage' => $locations->currentPage(),
                'lastPage' => $locations->lastPage(),
                'perPage' => $locations->perPage(),
                'total' => $locations->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_main' => $validated['is_main'] ?? '',
                'is_attendance' => $validated['is_attendance'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.campus.locations.index'),
                'create' => route('admin.campus.locations.create'),
                'export' => route('admin.campus.locations.export'),
                'exportPdf' => route('admin.campus.exports.pdf', ['resource' => 'campus-locations']),
                'importTemplate' => route('admin.campus.locations.import-template'),
                'importSubmit' => route('admin.campus.locations.import'),
                'bulkDestroy' => route('admin.campus.locations.bulk-destroy'),
                'bulkRestore' => route('admin.campus.locations.bulk-restore'),
                'bulkForceDestroy' => route('admin.campus.locations.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Campus/Location/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Tambah Lokasi Kampus'),
            'mode' => 'create',
            'location' => [
                'name' => '', 'code' => '', 'address' => '', 'phone' => '', 'email' => '',
                'latitude' => '', 'longitude' => '', 'radius_meters' => 100,
                'is_main' => false, 'is_attendance_point' => false, 'is_active' => true, 'desc' => '',
            ],
            'urls' => [
                'index' => route('admin.campus.locations.index'),
                'submit' => route('admin.campus.locations.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:campus_locations,code',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:10000',
            'is_main' => 'boolean',
            'is_attendance_point' => 'boolean',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $location = CampusLocation::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'radius_meters' => $validated['radius_meters'] ?? 100,
            'is_main' => $validated['is_main'] ?? false,
            'is_attendance_point' => $validated['is_attendance_point'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'desc' => $validated['desc'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if ($location->is_main) {
            $this->deactivateMainOthers($location->id, $request->user()->id);
        }

        return redirect()->route('admin.campus.locations.index')
            ->with('success', 'Lokasi "'.$location->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, CampusLocation $location): Response
    {
        return Inertia::render('Admin/Campus/Location/Form', [
            'shell' => ShellProps::make($request->user(), 'Kampus', 'Edit Lokasi Kampus'),
            'mode' => 'edit',
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'address' => $location->address ?? '',
                'phone' => $location->phone ?? '',
                'email' => $location->email ?? '',
                'latitude' => $location->latitude !== null ? (string) $location->latitude : '',
                'longitude' => $location->longitude !== null ? (string) $location->longitude : '',
                'radius_meters' => (int) $location->radius_meters,
                'is_main' => (bool) $location->is_main,
                'is_attendance_point' => (bool) $location->is_attendance_point,
                'is_active' => (bool) $location->is_active,
                'desc' => $location->desc ?? '',
            ],
            'urls' => [
                'index' => route('admin.campus.locations.index'),
                'submit' => route('admin.campus.locations.update', $location),
            ],
        ]);
    }

    public function update(Request $request, CampusLocation $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('campus_locations', 'code')->ignore($location->id)],
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:10000',
            'is_main' => 'boolean',
            'is_attendance_point' => 'boolean',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $location->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'radius_meters' => $validated['radius_meters'] ?? 100,
            'is_main' => $validated['is_main'] ?? false,
            'is_attendance_point' => $validated['is_attendance_point'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'desc' => $validated['desc'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        if ($location->is_main) {
            $this->deactivateMainOthers($location->id, $request->user()->id);
        }

        return redirect()->route('admin.campus.locations.index')
            ->with('success', 'Lokasi "'.$location->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, CampusLocation $location)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $location->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status lokasi berhasil diperbarui.');
    }

    public function setMain(Request $request, CampusLocation $location)
    {
        $location->update(['is_main' => true, 'updated_by' => $request->user()->id]);
        $this->deactivateMainOthers($location->id, $request->user()->id);

        return back()->with('success', '"'.$location->name.'" ditetapkan sebagai kampus utama.');
    }

    public function destroy(CampusLocation $location)
    {
        if ($location->buildings()->count() > 0) {
            return back()->with('error', 'Lokasi "'.$location->name.'" masih memiliki gedung. Hapus atau pindahkan gedung terlebih dahulu.');
        }

        if ($this->attendanceUsage($location->id) > 0) {
            return back()->with('error', 'Lokasi "'.$location->name.'" masih dipakai riwayat absensi pegawai.');
        }

        $name = $location->name;
        $location->delete();

        return redirect()->route('admin.campus.locations.index')
            ->with('success', 'Lokasi "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:campus_locations,id',
        ]);

        $locations = CampusLocation::whereIn('id', $validated['ids'])->withCount('buildings')->get();
        $blocked = $locations->filter(fn ($l) => $l->buildings_count > 0 || $this->attendanceUsage($l->id) > 0);
        $deletable = $locations->filter(fn ($l) => $l->buildings_count === 0 && $this->attendanceUsage($l->id) === 0);

        CampusLocation::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' lokasi dihapus. '.$blocked->count().' tidak bisa dihapus karena masih memiliki gedung atau dipakai absensi.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Lokasi tidak bisa dihapus karena masih memiliki gedung atau dipakai absensi.');
        }

        return back()->with('success', $deletable->count().' lokasi berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $location = CampusLocation::withTrashed()->findOrFail($id);
        $location->restore();

        return back()->with('success', 'Lokasi "'.$location->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $location = CampusLocation::withTrashed()->findOrFail($id);

        if ($location->buildings()->withTrashed()->count() > 0) {
            return back()->with('error', 'Lokasi "'.$location->name.'" masih memiliki gedung. Hapus atau pindahkan gedung terlebih dahulu.');
        }

        if ($this->attendanceUsage($location->id, true) > 0) {
            return back()->with('error', 'Lokasi "'.$location->name.'" masih dipakai riwayat absensi pegawai.');
        }

        $name = $location->name;
        $location->forceDelete();

        return back()->with('success', 'Lokasi "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:campus_locations,id',
        ]);

        $count = 0;

        foreach (CampusLocation::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $location) {
            $location->restore();
            $count++;
        }

        return back()->with('success', $count.' lokasi berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:campus_locations,id',
        ]);

        $locations = CampusLocation::withTrashed()->whereIn('id', $validated['ids'])->withCount(['buildings' => fn ($q) => $q->withTrashed()])->get();
        $blocked = $locations->filter(fn ($l) => $l->buildings_count > 0 || $this->attendanceUsage($l->id, true) > 0);
        $deletable = $locations->filter(fn ($l) => $l->buildings_count === 0 && $this->attendanceUsage($l->id, true) === 0);

        foreach ($deletable as $location) {
            $location->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' lokasi dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih memiliki gedung atau dipakai absensi.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Lokasi tidak bisa dihapus karena masih memiliki gedung atau dipakai absensi.');
        }

        return back()->with('success', $deletable->count().' lokasi dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = CampusLocation::query()->withCount('buildings');

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
        $sheet->fromArray([['Nama', 'Kode', 'Alamat', 'Telepon', 'Utama', 'Jml Gedung', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $location) {
            $sheet->fromArray([
                $location->name,
                $location->code,
                $location->address,
                $location->phone,
                $location->is_main ? 'Ya' : 'Tidak',
                (int) $location->buildings_count,
                $location->is_active ? 'Ya' : 'Tidak',
                $location->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'campus-locations-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['name', 'code', 'address', 'phone', 'email', 'latitude', 'longitude', 'radius_meters', 'is_main', 'is_attendance_point', 'is_active']], null, 'A1');
        $sheet->fromArray([['Kampus Utama', 'KMP-A', 'Jl. Contoh No. 1', '021-123456', 'info@kampus.ac.id', '-6.2', '106.8', '100', '1', '1', '1']], null, 'A2');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-campus-locations.xlsx',
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
        $missing = array_diff(['name', 'code'], $header);

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
        $hasMain = false;

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

            if ($code === null || $code === '') {
                $rowErrors[] = 'code wajib diisi';
            } else {
                if (strlen((string) $code) > 50) {
                    $rowErrors[] = 'code maksimal 50 karakter';
                } elseif (in_array($code, $seenCodes, true)) {
                    $rowErrors[] = 'code duplikat di dalam file';
                } elseif (CampusLocation::where('code', $code)->exists()) {
                    $rowErrors[] = 'code sudah terdaftar';
                } else {
                    $seenCodes[] = $code;
                }
            }

            foreach (['latitude' => [-90, 90], 'longitude' => [-180, 180]] as $field => [$min, $max]) {
                if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                    if (! is_numeric($data[$field]) || $data[$field] < $min || $data[$field] > $max) {
                        $rowErrors[] = $field.' harus angka '.$min.' sampai '.$max;
                    }
                }
            }

            if (isset($data['radius_meters']) && $data['radius_meters'] !== '' && $data['radius_meters'] !== null) {
                if (! is_numeric($data['radius_meters']) || (int) $data['radius_meters'] < 10 || (int) $data['radius_meters'] > 10000) {
                    $rowErrors[] = 'radius_meters 10 sampai 10000';
                }
            }

            if (isset($data['email']) && $data['email'] !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'email tidak valid';
            }

            $isMain = $this->parseImportBool($data['is_main'] ?? null, false);

            if ($isMain && $hasMain) {
                $rowErrors[] = 'is_main hanya boleh satu baris (satu kampus utama)';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            if ($isMain) {
                $hasMain = true;
            }

            $valid[] = [
                'name' => $data['name'],
                'code' => $code,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? $data['latitude'] : null,
                'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? $data['longitude'] : null,
                'radius_meters' => isset($data['radius_meters']) && $data['radius_meters'] !== '' ? (int) $data['radius_meters'] : 100,
                'is_main' => $isMain,
                'is_attendance_point' => $this->parseImportBool($data['is_attendance_point'] ?? null, false),
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.campus.locations.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        CampusLocation::insert($valid);

        if ($hasMain) {
            $mainId = CampusLocation::whereIn('code', $seenCodes)->where('is_main', true)->orderByDesc('id')->value('id');
            $this->deactivateMainOthers($mainId, $request->user()->id);
        }

        return redirect()->route('admin.campus.locations.index')
            ->with('success', count($valid).' lokasi berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function deactivateMainOthers(?int $exceptId = null, ?int $by = null): void
    {
        CampusLocation::query()
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_main' => false, 'updated_by' => $by]);
    }

    /**
     * Jumlah riwayat absensi yang menunjuk ke lokasi ini.
     */
    private function attendanceUsage(int $locationId, bool $withTrashed = false): int
    {
        $checkIn = \App\Models\Organization\EmployeeAttendanceRecord::where('check_in_location_id', $locationId);
        $checkOut = \App\Models\Organization\EmployeeAttendanceRecord::where('check_out_location_id', $locationId);

        if ($withTrashed) {
            $checkIn->withTrashed();
            $checkOut->withTrashed();
        }

        return $checkIn->count() + $checkOut->count();
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }
}
