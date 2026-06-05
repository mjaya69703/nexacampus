<?php

namespace App\Support\Organization;

use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\EmployeeLeaveType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeLeaveRequestService
{
    public function create(EmployeeProfile $employee, EmployeeLeaveType $type, array $payload, ?int $userId = null): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($employee, $type, $payload, $userId) {
            $totalDays = $this->calculateDays($payload['starts_at'], $payload['ends_at']);

            if ($totalDays < 1) {
                throw ValidationException::withMessages(['ends_at' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.']);
            }

            $balance = $this->ensureBalance($employee, $type, (int) Carbon::parse($payload['starts_at'])->year, $userId);

            if ((float) $type->default_days_per_year > 0 && $balance->availableDays() < $totalDays) {
                throw ValidationException::withMessages(['balance' => 'Saldo cuti pegawai tidak mencukupi.']);
            }

            $request = EmployeeLeaveRequest::create([
                'employee_profile_id' => $employee->id,
                'employee_leave_type_id' => $type->id,
                'request_number' => $this->nextNumber(),
                'starts_at' => $payload['starts_at'],
                'ends_at' => $payload['ends_at'],
                'total_days' => $totalDays,
                'status' => 'draft',
                'reason' => $payload['reason'],
                'employee_notes' => $payload['employee_notes'] ?? null,
                'created_by' => $userId,
            ]);

            return $request->refresh();
        });
    }

    public function submit(EmployeeLeaveRequest $request, ?int $userId = null): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($request, $userId) {
            $request->loadMissing(['employeeProfile.user', 'leaveType.approvalTemplate']);

            if (! in_array($request->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['status' => 'Status pengajuan cuti tidak bisa disubmit.']);
            }

            $balance = $this->ensureBalance(
                $request->employeeProfile,
                $request->leaveType,
                (int) $request->starts_at->year,
                $userId,
            );

            if ((float) $request->leaveType->default_days_per_year > 0 && $balance->availableDays() < (float) $request->total_days) {
                throw ValidationException::withMessages(['balance' => 'Saldo cuti pegawai tidak mencukupi.']);
            }

            if ($request->leaveType->requires_approval && $request->leaveType->approvalTemplate) {
                $approval = app(ApprovalEngine::class)->submitFromTemplate(
                    template: $request->leaveType->approvalTemplate,
                    subject: 'Pengajuan cuti '.$request->employeeProfile->user?->name.' '.$request->starts_at->format('d M Y').' - '.$request->ends_at->format('d M Y'),
                    requester: $request->employeeProfile->user,
                    approvable: $request,
                    payload: [
                        'employee_profile_id' => $request->employee_profile_id,
                        'leave_type_id' => $request->employee_leave_type_id,
                        'total_days' => (float) $request->total_days,
                    ],
                    reference: $request->request_number,
                    notes: $request->reason,
                    createdBy: $userId,
                );

                $request->update([
                    'approval_request_id' => $approval->id,
                    'status' => 'in_approval',
                    'updated_by' => $userId,
                ]);
                $balance->increment('pending_days', (float) $request->total_days);
            } else {
                $this->approveFromApproval($request, $userId, 'Auto approved: leave type does not require approval.');
            }

            return $request->refresh();
        });
    }

    public function approveFromApproval(EmployeeLeaveRequest $request, ?int $userId = null, ?string $notes = null): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($request, $userId, $notes) {
            $request = $request->fresh(['employeeProfile', 'leaveType']);

            if ($request->status === 'approved') {
                return $request;
            }

            $balance = $this->ensureBalance($request->employeeProfile, $request->leaveType, (int) $request->starts_at->year, $userId);
            $pending = max(0, (float) $balance->pending_days - (float) $request->total_days);

            $balance->update([
                'pending_days' => $pending,
                'used_days' => (float) $balance->used_days + (float) $request->total_days,
                'updated_by' => $userId,
            ]);

            $request->update([
                'status' => 'approved',
                'admin_notes' => $notes ?: $request->admin_notes,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
                'updated_by' => $userId,
            ]);

            return $request->refresh();
        });
    }

    public function rejectFromApproval(EmployeeLeaveRequest $request, ?int $userId = null, ?string $notes = null): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($request, $userId, $notes) {
            $request = $request->fresh(['employeeProfile', 'leaveType']);
            $balance = $this->ensureBalance($request->employeeProfile, $request->leaveType, (int) $request->starts_at->year, $userId);

            $balance->update([
                'pending_days' => max(0, (float) $balance->pending_days - (float) $request->total_days),
                'updated_by' => $userId,
            ]);

            $request->update([
                'status' => 'rejected',
                'admin_notes' => $notes ?: $request->admin_notes,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'rejected_by' => $userId,
                'rejected_at' => now(),
                'updated_by' => $userId,
            ]);

            return $request->refresh();
        });
    }

    public function cancel(EmployeeLeaveRequest $request, ?int $userId = null, ?string $notes = null): EmployeeLeaveRequest
    {
        return DB::transaction(function () use ($request, $userId, $notes) {
            $request = $request->fresh(['employeeProfile', 'leaveType']);

            if (in_array($request->status, ['approved', 'rejected', 'cancelled'], true)) {
                return $request;
            }

            $balance = $this->ensureBalance($request->employeeProfile, $request->leaveType, (int) $request->starts_at->year, $userId);
            $balance->update([
                'pending_days' => max(0, (float) $balance->pending_days - (float) $request->total_days),
                'updated_by' => $userId,
            ]);

            $request->update([
                'status' => 'cancelled',
                'admin_notes' => $notes ?: $request->admin_notes,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'updated_by' => $userId,
            ]);

            return $request->refresh();
        });
    }

    public function ensureBalance(EmployeeProfile $employee, EmployeeLeaveType $type, int $year, ?int $userId = null): EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::firstOrCreate(
            [
                'employee_profile_id' => $employee->id,
                'employee_leave_type_id' => $type->id,
                'year' => $year,
            ],
            [
                'allocated_days' => $type->default_days_per_year,
                'used_days' => 0,
                'pending_days' => 0,
                'carried_over_days' => 0,
                'created_by' => $userId,
            ],
        );
    }

    public function calculateDays(string $startsAt, string $endsAt): float
    {
        $start = Carbon::parse($startsAt)->startOfDay();
        $end = Carbon::parse($endsAt)->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        return $start->diffInDays($end) + 1;
    }

    private function nextNumber(): string
    {
        $prefix = 'ELV-'.now()->format('Ymd').'-';
        $count = EmployeeLeaveRequest::where('request_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
