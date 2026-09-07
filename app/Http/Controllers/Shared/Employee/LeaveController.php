<?php

namespace App\Http\Controllers\Shared\Employee;

use App\Http\Controllers\Controller;
use App\Models\Organization\EmployeeLeaveType;
use App\Support\Organization\EmployeeLeaveRequestService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LeaveController extends Controller
{
    public function store(Request $request, EmployeeLeaveRequestService $service): RedirectResponse
    {
        $employee = $this->employeeProfile($request);
        abort_unless($employee?->is_active, 403);

        $validated = $request->validate([
            'employee_leave_type_id' => ['required', 'exists:employee_leave_types,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'reason' => ['required', 'string', 'max:2000'],
            'employee_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = EmployeeLeaveType::findOrFail($validated['employee_leave_type_id']);
        $leave = $service->create($employee, $type, $validated, $request->user()->id);
        $service->submit($leave, $request->user()->id);

        return redirect()->route('employee.leaves.index')->with('success', 'Pengajuan cuti berhasil dikirim.');
    }

    private function employeeProfile(Request $request)
    {
        return $request->user()?->employeeProfile()->with(['primaryWorkUnit', 'user'])->first();
    }
}
