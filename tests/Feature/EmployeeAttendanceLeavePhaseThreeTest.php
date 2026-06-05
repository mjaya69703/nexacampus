<?php

use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Models\User;
use App\Support\Organization\ApprovalEngine;
use App\Support\Organization\EmployeeAttendanceService;
use App\Support\Organization\EmployeeLeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('records manual employee attendance with calculated work minutes', function () {
    $user = User::factory()->create();
    $employee = EmployeeProfile::create([
        'user_id' => $user->id,
        'employee_number' => 'ATT-001',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    $record = app(EmployeeAttendanceService::class)->recordManual($employee, [
        'attendance_date' => '2026-05-28',
        'status' => 'present',
        'check_in_at' => '2026-05-28 08:00:00',
        'check_out_at' => '2026-05-28 16:30:00',
        'notes' => 'Manual input',
    ], $user->id);

    expect($record->status)->toBe('present')
        ->and($record->work_minutes)->toBe(510)
        ->and($record->source?->code)->toBe('MANUAL_ADMIN');
});

it('allows employees to check in and check out from self service attendance', function () {
    $user = User::factory()->create();
    $employee = EmployeeProfile::create([
        'user_id' => $user->id,
        'employee_number' => 'SELF-001',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    $service = app(EmployeeAttendanceService::class);
    $checkIn = $service->checkInSelf($employee, $user->id);
    $checkOut = $service->checkOutSelf($employee, $user->id);

    expect($checkIn->source?->code)->toBe('EMPLOYEE_SELF')
        ->and($checkOut->source?->code)->toBe('EMPLOYEE_SELF')
        ->and($checkOut->check_in_at)->not->toBeNull()
        ->and($checkOut->check_out_at)->not->toBeNull();
});

it('submits employee leave through approval engine and updates leave balance on approval', function () {
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $employee = EmployeeProfile::create([
        'user_id' => $requester->id,
        'employee_number' => 'ELV-001',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    $template = ApprovalTemplate::create([
        'name' => 'Employee Leave Approval',
        'code' => 'EMPLOYEE_LEAVE_APPROVAL',
        'module' => 'organization',
        'is_active' => true,
    ]);
    $template->steps()->create([
        'step_order' => 1,
        'name' => 'HR Review',
        'approver_type' => 'user',
        'approver_user_id' => $approver->id,
    ]);

    $type = EmployeeLeaveType::create([
        'approval_template_id' => $template->id,
        'name' => 'Cuti Tahunan Test',
        'code' => 'ANNUAL_TEST',
        'default_days_per_year' => 12,
        'requires_approval' => true,
        'is_paid' => true,
        'is_active' => true,
    ]);

    $service = app(EmployeeLeaveRequestService::class);
    $leave = $service->create($employee, $type, [
        'starts_at' => '2026-06-01',
        'ends_at' => '2026-06-03',
        'reason' => 'Family event',
    ], $requester->id);
    $leave = $service->submit($leave, $requester->id);

    expect($leave->status)->toBe('in_approval')
        ->and($leave->approval_request_id)->not->toBeNull()
        ->and($employee->leaveBalances()->first()->pending_days)->toBe('3.00');

    app(ApprovalEngine::class)->approve($leave->approvalRequest, $approver, 'Approved');
    $leave->refresh();
    $balance = $employee->leaveBalances()->first();

    expect($leave->status)->toBe('approved')
        ->and($balance->pending_days)->toBe('0.00')
        ->and($balance->used_days)->toBe('3.00');
});

it('prevents employee leave submission when balance is insufficient', function () {
    $user = User::factory()->create();
    $employee = EmployeeProfile::create([
        'user_id' => $user->id,
        'employee_number' => 'ELV-002',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $type = EmployeeLeaveType::create([
        'name' => 'Cuti Terbatas',
        'code' => 'LIMITED_LEAVE',
        'default_days_per_year' => 2,
        'requires_approval' => false,
        'is_paid' => true,
        'is_active' => true,
    ]);

    expect(fn () => app(EmployeeLeaveRequestService::class)->create($employee, $type, [
        'starts_at' => '2026-06-01',
        'ends_at' => '2026-06-05',
        'reason' => 'Too long',
    ], $user->id))->toThrow(ValidationException::class);
});
