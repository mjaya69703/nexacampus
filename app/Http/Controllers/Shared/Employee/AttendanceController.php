<?php

namespace App\Http\Controllers\Shared\Employee;

use App\Http\Controllers\Controller;
use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Models\Organization\EmployeeLeaveType;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\EmployeeAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $employee = $this->employeeProfile($request);
        abort_unless($employee?->is_active, 403);

        $todayRecord = EmployeeAttendanceRecord::query()
            ->with(['source', 'checkInLocation', 'checkOutLocation'])
            ->where('employee_profile_id', $employee->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->whereHas('source', fn ($query) => $query->where('code', 'EMPLOYEE_SELF'))
            ->first();

        $records = EmployeeAttendanceRecord::query()
            ->with(['source', 'workUnit', 'checkInLocation', 'checkOutLocation'])
            ->where('employee_profile_id', $employee->id)
            ->latest('attendance_date')
            ->latest('check_in_at')
            ->limit(12)
            ->get()
            ->map(fn ($record) => [
                'kind' => 'attendance',
                'sortKey' => $record->attendance_date->format('Y-m-d'),
                'id' => $record->id,
                'dateLabel' => $record->attendance_date->format('d M Y'),
                'status' => $record->location_status,
                'statusLabel' => ucwords(str_replace('_', ' ', (string) ($record->location_status ?: 'unverified'))),
                'checkInLabel' => $record->check_in_at?->format('H:i') ?? '-',
                'checkOutLabel' => $record->check_out_at?->format('H:i') ?? '-',
                'locationName' => $record->checkInLocation?->name ?? $record->checkOutLocation?->name ?? '-',
                'distanceLabel' => $record->check_in_distance_meters !== null ? $record->check_in_distance_meters.' m' : '-',
                'checkInPhoto' => $record->check_in_photo_url,
                'checkOutPhoto' => $record->check_out_photo_url,
                'checkInTime' => $record->check_in_at?->format('d M Y H:i'),
                'checkOutTime' => $record->check_out_at?->format('d M Y H:i'),
                'workMinutes' => $record->work_minutes,
            ])
            ->values()
            ->all();

        $records = collect($records)
            ->concat($this->leaveDayRows($employee))
            ->sortByDesc('sortKey')
            ->values()
            ->take(15)
            ->all();

        $locations = EmployeeAttendanceLocation::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($location) => [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'radius' => (int) $location->radius_meters,
            ])
            ->values()
            ->all();

        $balances = EmployeeLeaveBalance::query()
            ->with('leaveType')
            ->where('employee_profile_id', $employee->id)
            ->where('year', now()->year)
            ->get()
            ->map(fn ($balance) => [
                'id' => $balance->id,
                'typeName' => $balance->leaveType?->name ?? '-',
                'available' => round($balance->availableDays(), 1),
                'used' => round((float) $balance->used_days, 1),
            ])
            ->values()
            ->all();

        $leaveRequests = EmployeeLeaveRequest::query()
            ->with(['leaveType', 'approvalRequest'])
            ->where('employee_profile_id', $employee->id)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'requestNumber' => $item->request_number,
                'typeName' => $item->leaveType?->name ?? '-',
                'periodLabel' => $item->starts_at->format('d M Y').' - '.$item->ends_at->format('d M Y'),
                'totalDays' => round((float) $item->total_days, 1),
                'status' => $item->status,
            ])
            ->values()
            ->all();

        return Inertia::render('Shared/Employee/Attendance', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian Saya', 'Kehadiran Saya'),
            'initialTab' => $request->input('tab') === 'cuti' ? 'cuti' : 'absensi',
            'employee' => [
                'name' => $employee->user?->name ?? $request->user()->name,
                'unitName' => $employee->primaryWorkUnit?->name,
            ],
            'today' => $todayRecord ? [
                'dateLabel' => now()->format('d M Y'),
                'checkedIn' => (bool) $todayRecord->check_in_at,
                'checkedOut' => (bool) $todayRecord->check_out_at,
                'checkInLabel' => $todayRecord->check_in_at?->format('H:i') ?? 'Belum check-in',
                'checkOutLabel' => $todayRecord->check_out_at?->format('H:i') ?? 'Belum check-out',
            ] : [
                'dateLabel' => now()->format('d M Y'),
                'checkedIn' => false,
                'checkedOut' => false,
                'checkInLabel' => 'Belum check-in',
                'checkOutLabel' => 'Belum check-out',
            ],
            'locations' => $locations,
            'records' => $records,
            'leaveTypes' => EmployeeLeaveType::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values()
                ->all(),
            'balances' => $balances,
            'leaveRequests' => $leaveRequests,
        ]);
    }

    public function checkIn(Request $request, EmployeeAttendanceService $service): RedirectResponse
    {
        return $this->capture($request, $service, 'in');
    }

    public function checkOut(Request $request, EmployeeAttendanceService $service): RedirectResponse
    {
        return $this->capture($request, $service, 'out');
    }

    private function capture(Request $request, EmployeeAttendanceService $service, string $mode): RedirectResponse
    {
        $employee = $this->employeeProfile($request);
        abort_unless($employee?->is_active, 403);

        try {
            $validated = $request->validate([
                'photo' => ['required', 'image', 'max:2048'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'accuracy' => ['nullable', 'numeric', 'min:0'],
            ]);

            $path = $request->file('photo')->store('employee-attendance', 'public');

            $payload = [
                'photo_path' => $path,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy' => $validated['accuracy'] ?? null,
            ];

            if ($mode === 'in') {
                $service->checkInSelf($employee, $request->user()->id, $payload);
            } else {
                $service->checkOutSelf($employee, $request->user()->id, $payload);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('employee.attendance.index')
                ->with('error', 'Absensi gagal diproses. Coba ulangi setelah foto dan lokasi siap.');
        }

        $message = $mode === 'in' ? 'Check-in berhasil dicatat.' : 'Check-out berhasil dicatat.';

        return redirect()->route('employee.attendance.index')->with('success', $message);
    }

    private function employeeProfile(Request $request)
    {
        return $request->user()?->employeeProfile()->with(['primaryWorkUnit', 'user'])->first();
    }

    /**
     * Baris per-hari dari cuti yang disetujui/menunggu agar tampil
     * di riwayat kehadiran. Dibatasi 90 hari ke belakang s.d. hari ini.
     *
     * @return array<int, array<string, mixed>>
     */
    private function leaveDayRows($employee): array
    {
        $today = now()->startOfDay();
        $floor = now()->subDays(90)->startOfDay();

        $leaves = EmployeeLeaveRequest::query()
            ->with('leaveType')
            ->where('employee_profile_id', $employee->id)
            ->whereIn('status', ['approved', 'submitted', 'in_approval'])
            ->where('ends_at', '>=', $floor->toDateString())
            ->where('starts_at', '<=', $today->toDateString())
            ->orderByDesc('starts_at')
            ->limit(15)
            ->get();

        $rows = [];

        foreach ($leaves as $leave) {
            $start = $leave->starts_at->copy()->startOfDay();
            $end = $leave->ends_at->copy()->startOfDay();
            if ($start->lt($floor)) {
                $start = $floor->copy();
            }
            if ($end->gt($today)) {
                $end = $today->copy();
            }
            $pending = $leave->status !== 'approved';

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $rows[] = [
                    'kind' => 'leave',
                    'sortKey' => $day->format('Y-m-d'),
                    'id' => 'leave-'.$leave->id.'-'.$day->format('Ymd'),
                    'dateLabel' => $day->format('d M Y'),
                    'status' => $pending ? 'leave_pending' : 'leave_approved',
                    'statusLabel' => $pending ? 'Cuti · Menunggu' : 'Cuti',
                    'checkInLabel' => '-',
                    'checkOutLabel' => '-',
                    'locationName' => $leave->leaveType?->name ?? 'Cuti',
                    'distanceLabel' => $leave->request_number,
                    'checkInPhoto' => null,
                    'checkOutPhoto' => null,
                    'checkInTime' => null,
                    'checkOutTime' => null,
                    'workMinutes' => null,
                ];
            }
        }

        return $rows;
    }
}
