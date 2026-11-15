<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\WorkUnit;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\EmployeeAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD catatan absensi pegawai — paritas halaman Livewire lama: daftar
 * presensi, input manual admin, dan detail geofence + bukti foto.
 */
class EmployeeAttendanceRecordController extends Controller
{
    private const STATUSES = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'absent' => 'Tidak Hadir',
        'leave' => 'Cuti',
        'sick' => 'Sakit',
        'remote' => 'Remote',
    ];

    private const LOCATION_STATUSES = [
        'inside_radius' => 'Di Dalam Radius',
        'outside_radius' => 'Di Luar Radius',
        'gps_missing' => 'GPS Tidak Tersedia',
        'partial' => 'Sebagian',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'date' => 'nullable|date',
            'work_unit_id' => 'nullable|integer|exists:work_units,id',
            'sort' => 'nullable|in:attendance_date,status,work_minutes,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = EmployeeAttendanceRecord::query()
            ->with(['employeeProfile.user', 'workUnit', 'source', 'checkInLocation']);

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->whereHas('employeeProfile.user', function ($user) use ($term) {
                    $user->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('code', 'like', $term);
                })
                    ->orWhereHas('employeeProfile', fn ($profile) => $profile->where('employee_number', 'like', $term))
                    ->orWhereHas('workUnit', function ($unit) use ($term) {
                        $unit->where('name', 'like', $term)->orWhere('code', 'like', $term);
                    })
                    ->orWhereHas('checkInLocation', fn ($location) => $location->where('name', 'like', $term))
                    ->orWhere('notes', 'like', $term);
            });
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['date'] ?? null)) {
            $query->whereDate('attendance_date', $validated['date']);
        }

        if (filled($validated['work_unit_id'] ?? null)) {
            $query->where('work_unit_id', $validated['work_unit_id']);
        }

        $sort = $validated['sort'] ?? 'attendance_date';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $records = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/EmployeeAttendanceRecords/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Absensi Pegawai'),
            'can' => [
                'create' => ActivePermission::check('employee-attendance-record.create'),
                'view' => ActivePermission::check('employee-attendance-record.view'),
                'delete' => ActivePermission::check('employee-attendance-record.delete'),
            ],
            'stats' => [
                'total' => EmployeeAttendanceRecord::count(),
                'today' => EmployeeAttendanceRecord::whereDate('attendance_date', today())->count(),
                'present_today' => EmployeeAttendanceRecord::whereDate('attendance_date', today())->where('status', 'present')->count(),
                'late_today' => EmployeeAttendanceRecord::whereDate('attendance_date', today())->where('status', 'late')->count(),
            ],
            'data' => [
                'rows' => collect($records->items())->values()->map(fn (EmployeeAttendanceRecord $record, int $i) => [
                    'id' => $record->id,
                    'no' => ($records->firstItem() ?? 0) + $i,
                    'employeeName' => $record->employeeProfile?->user?->name ?? '-',
                    'employeeNumber' => $record->employeeProfile?->employee_number,
                    'attendanceDate' => $record->attendance_date?->format('d M Y'),
                    'status' => $record->status,
                    'statusLabel' => self::STATUSES[$record->status] ?? ucwords(str_replace('_', ' ', (string) $record->status)),
                    'checkIn' => $record->check_in_at?->format('H:i'),
                    'checkOut' => $record->check_out_at?->format('H:i'),
                    'workMinutes' => (int) $record->work_minutes,
                    'workUnit' => $record->workUnit?->name ?? $record->employeeProfile?->primaryWorkUnit?->name,
                    'location' => $record->checkInLocation?->name,
                    'locationStatus' => $record->location_status,
                    'locationStatusLabel' => $record->location_status
                        ? (self::LOCATION_STATUSES[$record->location_status] ?? ucwords(str_replace('_', ' ', $record->location_status)))
                        : 'Belum Diverifikasi',
                    'photoCount' => collect([$record->check_in_photo_path, $record->check_out_photo_path])->filter()->count(),
                    'source' => $record->source?->name ?? 'Manual/System',
                    'createdAt' => $record->created_at?->format('d M Y'),
                    'showUrl' => route('admin.organization.employee-attendance-records.show', $record->id),
                    'deleteUrl' => route('admin.organization.employee-attendance-records.destroy', $record->id),
                ])->all(),
                'currentPage' => $records->currentPage(),
                'lastPage' => $records->lastPage(),
                'perPage' => $records->perPage(),
                'total' => $records->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'date' => $validated['date'] ?? '',
                'work_unit_id' => $validated['work_unit_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'statuses' => $this->statusOptions(),
                'workUnits' => $this->workUnitOptions(),
            ],
            'urls' => [
                'index' => route('admin.organization.employee-attendance-records.index'),
                'create' => route('admin.organization.employee-attendance-records.create'),
                'bulkDestroy' => route('admin.organization.employee-attendance-records.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/EmployeeAttendanceRecords/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Input Absensi Pegawai'),
            'defaults' => [
                'attendance_date' => now()->toDateString(),
                'status' => 'present',
                'check_in_at' => now()->format('Y-m-d\TH:i'),
                'check_out_at' => '',
            ],
            'options' => [
                'statuses' => $this->statusOptions(),
                'workUnits' => $this->workUnitOptions(),
            ],
            'urls' => [
                'index' => route('admin.organization.employee-attendance-records.index'),
                'submit' => route('admin.organization.employee-attendance-records.store'),
                'searchEmployees' => route('admin.organization.employee-attendance-records.search-employees'),
            ],
        ]);
    }

    public function store(Request $request, EmployeeAttendanceService $service)
    {
        $validated = $request->validate([
            'employee_profile_id' => ['required', 'integer', 'exists:employee_profiles,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date', 'after_or_equal:check_in_at'],
            'work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $employee = EmployeeProfile::findOrFail($validated['employee_profile_id']);

        try {
            $service->recordManual($employee, [
                'attendance_date' => $validated['attendance_date'],
                'status' => $validated['status'],
                'check_in_at' => ($validated['check_in_at'] ?? null) ?: null,
                'check_out_at' => ($validated['check_out_at'] ?? null) ?: null,
                'work_unit_id' => ($validated['work_unit_id'] ?? null) ?: null,
                'notes' => ($validated['notes'] ?? null) ?: null,
            ], $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.organization.employee-attendance-records.index')
            ->with('success', 'Absensi pegawai berhasil disimpan.');
    }

    public function show(Request $request, int $id): Response
    {
        $record = EmployeeAttendanceRecord::with([
            'employeeProfile.user',
            'employeeProfile.primaryWorkUnit',
            'workUnit',
            'source',
            'checkInLocation',
            'checkOutLocation',
        ])->findOrFail($id);

        return Inertia::render('Admin/Organization/EmployeeAttendanceRecords/Show', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Detail Absensi Pegawai'),
            'can' => [
                'delete' => ActivePermission::check('employee-attendance-record.delete'),
            ],
            'record' => [
                'id' => $record->id,
                'employeeName' => $record->employeeProfile?->user?->name ?? '-',
                'employeeNumber' => $record->employeeProfile?->employee_number,
                'employeeEmail' => $record->employeeProfile?->user?->email,
                'attendanceDate' => $record->attendance_date?->translatedFormat('l, d M Y'),
                'status' => $record->status,
                'statusLabel' => self::STATUSES[$record->status] ?? ucwords(str_replace('_', ' ', (string) $record->status)),
                'workUnit' => $record->workUnit?->name ?? $record->employeeProfile?->primaryWorkUnit?->name,
                'checkInAt' => $record->check_in_at?->format('H:i:s (d M Y)'),
                'checkOutAt' => $record->check_out_at?->format('H:i:s (d M Y)'),
                'workMinutes' => (int) $record->work_minutes,
                'source' => $record->source?->name ?? 'Manual/System',
                'notes' => $record->notes,
                'locationStatus' => $record->location_status,
                'locationStatusLabel' => $record->location_status
                    ? (self::LOCATION_STATUSES[$record->location_status] ?? ucwords(str_replace('_', ' ', $record->location_status)))
                    : 'Belum Diverifikasi',
                'checkIn' => [
                    'locationName' => $record->checkInLocation?->name,
                    'latitude' => $record->check_in_latitude !== null ? (float) $record->check_in_latitude : null,
                    'longitude' => $record->check_in_longitude !== null ? (float) $record->check_in_longitude : null,
                    'distance' => $record->check_in_distance_meters,
                    'accuracy' => $record->check_in_accuracy_meters,
                    'photoUrl' => $record->check_in_photo_url,
                    'time' => $record->check_in_at?->format('H:i:s'),
                    'location' => $record->checkInLocation ? [
                        'name' => $record->checkInLocation->name,
                        'latitude' => (float) $record->checkInLocation->latitude,
                        'longitude' => (float) $record->checkInLocation->longitude,
                        'radius' => (int) $record->checkInLocation->radius_meters,
                    ] : null,
                ],
                'checkOut' => [
                    'locationName' => $record->checkOutLocation?->name,
                    'latitude' => $record->check_out_latitude !== null ? (float) $record->check_out_latitude : null,
                    'longitude' => $record->check_out_longitude !== null ? (float) $record->check_out_longitude : null,
                    'distance' => $record->check_out_distance_meters,
                    'accuracy' => $record->check_out_accuracy_meters,
                    'photoUrl' => $record->check_out_photo_url,
                    'time' => $record->check_out_at?->format('H:i:s'),
                    'location' => $record->checkOutLocation ? [
                        'name' => $record->checkOutLocation->name,
                        'latitude' => (float) $record->checkOutLocation->latitude,
                        'longitude' => (float) $record->checkOutLocation->longitude,
                        'radius' => (int) $record->checkOutLocation->radius_meters,
                    ] : null,
                ],
            ],
            'urls' => [
                'index' => route('admin.organization.employee-attendance-records.index'),
                'delete' => route('admin.organization.employee-attendance-records.destroy', $record->id),
            ],
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $record = EmployeeAttendanceRecord::findOrFail($id);
        $record->update(['deleted_by' => $request->user()->id]);
        $record->delete();

        return redirect()->route('admin.organization.employee-attendance-records.index')
            ->with('success', 'Catatan absensi pegawai berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:employee_attendance_records,id',
        ]);

        $records = EmployeeAttendanceRecord::whereIn('id', $validated['ids'])->get();

        foreach ($records as $record) {
            $record->update(['deleted_by' => $request->user()->id]);
            $record->delete();
        }

        return back()->with('success', $records->count().' catatan absensi berhasil dihapus.');
    }

    /**
     * Endpoint pencarian pegawai aktif untuk input absensi manual.
     */
    public function searchEmployees(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'exclude' => 'nullable|string|max:2000',
        ]);

        $exclude = collect(explode(',', (string) ($validated['exclude'] ?? '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $q = $validated['q'] ?? '';

        $options = EmployeeProfile::query()
            ->with(['user', 'primaryWorkUnit'])
            ->where('is_active', true)
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->when(filled($q), function ($query) use ($q) {
                $search = '%'.$q.'%';

                $query->where(function ($sub) use ($search) {
                    $sub->where('employee_number', 'like', $search)
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('first_name', 'like', $search)
                                ->orWhere('last_name', 'like', $search)
                                ->orWhere('email', 'like', $search)
                                ->orWhere('username', 'like', $search)
                                ->orWhere('code', 'like', $search);
                        });
                });
            })
            ->limit(12)
            ->get()
            ->map(fn (EmployeeProfile $profile) => [
                'id' => $profile->id,
                'label' => $profile->user?->name ?? '-',
                'meta' => [
                    'email' => $profile->user?->email,
                    'employee_number' => $profile->employee_number,
                    'work_unit' => $profile->primaryWorkUnit?->name,
                ],
            ])
            ->values()
            ->all();

        return response()->json(['options' => $options]);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(self::STATUSES)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function workUnitOptions(): array
    {
        return WorkUnit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (WorkUnit $unit) => [
                'id' => (string) $unit->id,
                'label' => $unit->name.($unit->code ? ' ('.$unit->code.')' : ''),
            ])
            ->values()
            ->all();
    }
}
