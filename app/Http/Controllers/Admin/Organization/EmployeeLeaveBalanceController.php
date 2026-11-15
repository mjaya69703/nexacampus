<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen saldo cuti pegawai — halaman tunggal dengan form penyesuaian
 * inline dan daftar saldo terbaru, paritas halaman Livewire lama.
 */
class EmployeeLeaveBalanceController extends Controller
{
    public function index(Request $request): Response
    {
        $currentYear = (int) now()->year;

        return Inertia::render('Admin/Organization/EmployeeLeaveBalances/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Saldo Cuti Pegawai'),
            'can' => [
                'create' => ActivePermission::check('employee-leave-balance.create'),
                'update' => ActivePermission::check('employee-leave-balance.update'),
            ],
            'stats' => [
                'totalEmployees' => EmployeeLeaveBalance::where('year', $currentYear)->distinct()->count('employee_profile_id'),
                'totalBalances' => EmployeeLeaveBalance::where('year', $currentYear)->count(),
                'totalAllocated' => (float) EmployeeLeaveBalance::where('year', $currentYear)->sum('allocated_days'),
                'totalUsed' => (float) EmployeeLeaveBalance::where('year', $currentYear)->sum('used_days'),
            ],
            'currentYear' => $currentYear,
            'defaults' => [
                'year' => $currentYear,
                'allocated_days' => 12,
                'carried_over_days' => 0,
            ],
            'leaveTypes' => EmployeeLeaveType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'default_days_per_year'])
                ->map(fn (EmployeeLeaveType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'defaultDaysPerYear' => (float) $type->default_days_per_year,
                ])
                ->values()
                ->all(),
            'employees' => $this->employeeOptions(),
            'balances' => EmployeeLeaveBalance::query()
                ->with(['employeeProfile.user', 'leaveType'])
                ->latest('year')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (EmployeeLeaveBalance $balance) => [
                    'id' => $balance->id,
                    'employeeName' => $balance->employeeProfile?->user?->name ?? '-',
                    'employeeNumber' => $balance->employeeProfile?->employee_number,
                    'leaveTypeName' => $balance->leaveType?->name ?? '-',
                    'year' => (int) $balance->year,
                    'allocatedDays' => (float) $balance->allocated_days,
                    'pendingDays' => (float) $balance->pending_days,
                    'usedDays' => (float) $balance->used_days,
                    'carriedOverDays' => (float) $balance->carried_over_days,
                    'availableDays' => round($balance->availableDays(), 2),
                    'notes' => $balance->notes,
                ])
                ->values()
                ->all(),
            'urls' => [
                'index' => route('admin.organization.employee-leave-balances.index'),
                'save' => route('admin.organization.employee-leave-balances.save'),
                'searchEmployees' => route('admin.organization.employee-leave-balances.search-employees'),
            ],
        ]);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'employee_profile_id' => ['required', 'integer', 'exists:employee_profiles,id'],
            'employee_leave_type_id' => ['required', 'integer', 'exists:employee_leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'allocated_days' => ['required', 'numeric', 'min:0'],
            'carried_over_days' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $balance = EmployeeLeaveBalance::firstOrNew([
            'employee_profile_id' => (int) $validated['employee_profile_id'],
            'employee_leave_type_id' => (int) $validated['employee_leave_type_id'],
            'year' => (int) $validated['year'],
        ]);

        $balance->fill([
            'allocated_days' => (float) $validated['allocated_days'],
            'carried_over_days' => (float) ($validated['carried_over_days'] ?? 0),
            'notes' => ($validated['notes'] ?? null) ?: null,
            'updated_by' => $request->user()->id,
        ]);

        if (! $balance->exists) {
            $balance->created_by = $request->user()->id;
        }

        $balance->save();

        return back()->with('success', 'Saldo cuti pegawai berhasil disimpan.');
    }

    /**
     * Endpoint pencarian pegawai untuk picker saldo cuti (server-side).
     */
    public function searchEmployees(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
        ]);

        return response()->json([
            'options' => $this->employeeOptions($validated['q'] ?? ''),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, employeeNumber: string|null, email: string|null}>
     */
    private function employeeOptions(string $search = ''): array
    {
        return EmployeeProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->when(filled($search), function ($query) use ($search) {
                $term = '%'.$search.'%';

                $query->whereHas('user', function ($user) use ($term) {
                    $user->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            })
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (EmployeeProfile $profile) => [
                'id' => $profile->id,
                'name' => $profile->user?->name ?? '-',
                'employeeNumber' => $profile->employee_number,
                'email' => $profile->user?->email,
            ])
            ->values()
            ->all();
    }
}
