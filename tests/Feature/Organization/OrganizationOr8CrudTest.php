<?php

use App\Models\Organization\ApprovalRequest;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeePositionAssignment;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function organizationOr8Setup(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('admin');

    return $user;
}

function organizationOr8Permission(string ...$names): void
{
    $role = Role::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
}

function organizationOr8Acting(User $user)
{
    return test()->actingAs($user)->withSession(['active_role' => 'admin']);
}

function organizationOr8WorkUnit(string $code = 'BRO-OR8'): WorkUnit
{
    return WorkUnit::create([
        'name' => 'Biro Organisasi '.$code,
        'code' => $code,
        'description' => 'Unit kerja pengujian OR-8.',
        'is_active' => true,
    ]);
}

function organizationOr8Employee(array $attributes = []): EmployeeProfile
{
    $user = User::factory()->create(['is_active' => true]);

    return EmployeeProfile::create(array_merge([
        'user_id' => $user->id,
        'employee_number' => 'EMP-OR8-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'join_date' => '2024-01-01',
        'is_active' => true,
    ], $attributes));
}

function organizationOr8Template(string $code = 'OR8_APPROVAL', string $permission = 'approval-request.update'): ApprovalTemplate
{
    $template = ApprovalTemplate::create([
        'name' => 'Template Uji '.$code,
        'code' => $code,
        'module' => 'organization',
        'description' => 'Template pengujian OR-8.',
        'is_active' => true,
    ]);

    $template->steps()->create([
        'step_order' => 1,
        'name' => 'Verifikasi Admin',
        'approver_type' => 'permission',
        'approver_permission' => $permission,
        'is_required' => true,
        'can_reject' => true,
    ]);

    return $template;
}

it('mengelola profil pegawai: daftar, tambah, ubah, toggle, pencarian user, dan hapus', function () {
    $user = organizationOr8Setup();
    organizationOr8Permission(
        'employee-profile.viewAny',
        'employee-profile.create',
        'employee-profile.update',
        'employee-profile.delete',
    );

    $unit = organizationOr8WorkUnit();
    $first = User::factory()->create(['is_active' => true, 'first_name' => 'Budi', 'last_name' => 'Pegawai']);
    $second = User::factory()->create(['is_active' => true, 'first_name' => 'Sari', 'last_name' => 'Dosen']);

    organizationOr8Acting($user)
        ->post('/admin/organization/employee-profiles', [
            'user_id' => $first->id,
            'employee_number' => 'NIP-OR8-001',
            'employment_type' => 'staff',
            'employment_status' => 'active',
            'join_date' => '2024-02-01',
            'primary_work_unit_id' => $unit->id,
            'notes' => 'Pegawai baru.',
            'is_active' => 1,
        ])
        ->assertRedirect('/admin/organization/employee-profiles')
        ->assertSessionHas('success');

    $profile = EmployeeProfile::where('user_id', $first->id)->firstOrFail();

    expect($profile->employee_number)->toBe('NIP-OR8-001')
        ->and($profile->created_by)->toBe($user->id)
        ->and((bool) $profile->is_active)->toBeTrue();

    organizationOr8Acting($user)
        ->get('/admin/organization/employee-profiles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EmployeeProfiles/Index')
            ->where('stats.total', 1)
            ->where('stats.active', 1)
            ->has('data.rows', 1));

    organizationOr8Acting($user)
        ->get('/admin/organization/employee-profiles?employment_type=staff&is_active=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('data.total', 1));

    organizationOr8Acting($user)
        ->put("/admin/organization/employee-profiles/{$profile->id}", [
            'employee_number' => 'NIP-OR8-001-R',
            'employment_type' => 'lecturer',
            'employment_status' => 'inactive',
            'join_date' => '2024-02-01',
            'end_date' => '2026-02-01',
            'primary_work_unit_id' => $unit->id,
            'notes' => 'Diperbarui.',
            'is_active' => 0,
        ])
        ->assertRedirect('/admin/organization/employee-profiles')
        ->assertSessionHas('success');

    $profile->refresh();

    expect($profile->employee_number)->toBe('NIP-OR8-001-R')
        ->and($profile->employment_type)->toBe('lecturer')
        ->and($profile->employment_status)->toBe('inactive')
        ->and((bool) $profile->is_active)->toBeFalse()
        ->and($profile->updated_by)->toBe($user->id);

    organizationOr8Acting($user)
        ->from('/admin/organization/employee-profiles')
        ->post("/admin/organization/employee-profiles/{$profile->id}/toggle", ['is_active' => 1])
        ->assertSessionHas('success');

    expect((bool) $profile->refresh()->is_active)->toBeTrue();

    organizationOr8Acting($user)
        ->get('/admin/organization/employee-profiles/search-users?q=Sari')
        ->assertOk()
        ->assertJsonCount(1, 'options')
        ->assertJsonPath('options.0.id', $second->id);

    // Profil yang masih memiliki penugasan jabatan tidak boleh dihapus.
    $position = OrganizationalPosition::create([
        'name' => 'Kepala Biro OR8',
        'code' => 'KEPALA_OR8',
        'category' => 'work_unit',
        'scope_type' => 'work_unit',
        'is_active' => true,
    ]);

    EmployeePositionAssignment::create([
        'employee_profile_id' => $profile->id,
        'organizational_position_id' => $position->id,
        'work_unit_id' => $unit->id,
        'is_active' => true,
    ]);

    organizationOr8Acting($user)
        ->from('/admin/organization/employee-profiles')
        ->delete("/admin/organization/employee-profiles/{$profile->id}")
        ->assertSessionHas('error');

    expect(EmployeeProfile::find($profile->id))->not->toBeNull();

    $free = organizationOr8Employee();
    $bulkA = organizationOr8Employee();
    $bulkB = organizationOr8Employee();

    organizationOr8Acting($user)
        ->delete("/admin/organization/employee-profiles/{$free->id}")
        ->assertRedirect('/admin/organization/employee-profiles')
        ->assertSessionHas('success');

    expect(EmployeeProfile::find($free->id))->toBeNull()
        ->and(EmployeeProfile::withTrashed()->find($free->id)->deleted_by)->toBe($user->id);

    organizationOr8Acting($user)
        ->from('/admin/organization/employee-profiles')
        ->post('/admin/organization/employee-profiles/bulk-destroy', ['ids' => [$bulkA->id, $bulkB->id]])
        ->assertSessionHas('success');

    expect(EmployeeProfile::find($bulkA->id))->toBeNull()
        ->and(EmployeeProfile::find($bulkB->id))->toBeNull();
});

it('mengelola penugasan jabatan pegawai beserta sinkronisasi unit kerja', function () {
    $user = organizationOr8Setup();
    organizationOr8Permission(
        'employee-position-assignment.viewAny',
        'employee-position-assignment.create',
        'employee-position-assignment.update',
        'employee-position-assignment.delete',
    );

    $unit = organizationOr8WorkUnit('BRO-POS');
    $employee = organizationOr8Employee();
    $position = OrganizationalPosition::create([
        'name' => 'Kepala Unit OR8',
        'code' => 'KEPALA_UNIT_OR8',
        'category' => 'work_unit',
        'scope_type' => 'work_unit',
        'is_active' => true,
    ]);

    organizationOr8Acting($user)
        ->post('/admin/organization/employee-position-assignments', [
            'employee_profile_id' => $employee->id,
            'organizational_position_id' => $position->id,
            'work_unit_id' => $unit->id,
            'starts_at' => '2026-01-01',
            'is_primary' => 1,
            'is_active' => 1,
            'notes' => 'SK 01/OR8/2026',
        ])
        ->assertRedirect('/admin/organization/employee-position-assignments')
        ->assertSessionHas('success');

    $assignment = EmployeePositionAssignment::where('employee_profile_id', $employee->id)->firstOrFail();

    expect($assignment->organizational_position_id)->toBe($position->id)
        ->and($assignment->work_unit_id)->toBe($unit->id)
        ->and((bool) $assignment->is_primary)->toBeTrue()
        ->and($assignment->created_by)->toBe($user->id)
        ->and($employee->user->workUnits()->where('work_units.id', $unit->id)->exists())->toBeTrue();

    organizationOr8Acting($user)
        ->get('/admin/organization/employee-position-assignments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EmployeePositionAssignments/Index')
            ->where('stats.total', 1)
            ->where('stats.active', 1)
            ->where('stats.primary', 1));

    organizationOr8Acting($user)
        ->from('/admin/organization/employee-position-assignments')
        ->post("/admin/organization/employee-position-assignments/{$assignment->id}/toggle", [
            'field' => 'is_active',
            'value' => 0,
        ])
        ->assertSessionHas('success');

    expect((bool) $assignment->refresh()->is_active)->toBeFalse();

    // Scope unit kerja wajib diisi untuk jabatan ber-scope work_unit.
    organizationOr8Acting($user)
        ->from("/admin/organization/employee-position-assignments/{$assignment->id}/edit")
        ->put("/admin/organization/employee-position-assignments/{$assignment->id}", [
            'employee_profile_id' => $employee->id,
            'organizational_position_id' => $position->id,
            'starts_at' => '2026-01-01',
            'is_primary' => 0,
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('work_unit_id');

    organizationOr8Acting($user)
        ->put("/admin/organization/employee-position-assignments/{$assignment->id}", [
            'employee_profile_id' => $employee->id,
            'organizational_position_id' => $position->id,
            'work_unit_id' => $unit->id,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'is_primary' => 1,
            'is_active' => 1,
            'notes' => 'Diperbarui.',
        ])
        ->assertRedirect('/admin/organization/employee-position-assignments')
        ->assertSessionHas('success');

    $assignment->refresh();

    expect($assignment->ends_at?->format('Y-m-d'))->toBe('2026-12-31')
        ->and((bool) $assignment->is_active)->toBeTrue()
        ->and($assignment->updated_by)->toBe($user->id);

    $second = EmployeePositionAssignment::create([
        'employee_profile_id' => organizationOr8Employee()->id,
        'organizational_position_id' => $position->id,
        'work_unit_id' => $unit->id,
        'is_active' => true,
    ]);

    organizationOr8Acting($user)
        ->delete("/admin/organization/employee-position-assignments/{$second->id}")
        ->assertRedirect('/admin/organization/employee-position-assignments')
        ->assertSessionHas('success');

    expect(EmployeePositionAssignment::find($second->id))->toBeNull();

    $third = EmployeePositionAssignment::create([
        'employee_profile_id' => organizationOr8Employee()->id,
        'organizational_position_id' => $position->id,
        'work_unit_id' => $unit->id,
        'is_active' => true,
    ]);

    organizationOr8Acting($user)
        ->from('/admin/organization/employee-position-assignments')
        ->post('/admin/organization/employee-position-assignments/bulk-destroy', ['ids' => [$third->id]])
        ->assertSessionHas('success');

    expect(EmployeePositionAssignment::find($third->id))->toBeNull();
});

it('mengelola template approval dinamis: tambah, ubah, toggle, dan penjagaan hapus', function () {
    $user = organizationOr8Setup();
    organizationOr8Permission(
        'approval-template.viewAny',
        'approval-template.create',
        'approval-template.update',
        'approval-template.delete',
    );

    organizationOr8Acting($user)
        ->post('/admin/organization/approval-templates', [
            'name' => 'Approval Cuti OR8',
            'code' => 'cuti_or8',
            'module' => 'organization',
            'description' => 'Alur cuti pegawai.',
            'is_active' => 1,
            'steps' => [
                [
                    'name' => 'Verifikasi Atasan',
                    'approver_type' => 'role',
                    'approver_role' => 'admin',
                    'is_required' => 1,
                    'can_reject' => 1,
                    'sla_hours' => 24,
                ],
                [
                    'name' => 'Persetujuan SDM',
                    'approver_type' => 'permission',
                    'approver_permission' => 'employee-leave-request.update',
                    'is_required' => 1,
                    'can_reject' => 0,
                    'sla_hours' => 48,
                ],
            ],
        ])
        ->assertRedirect('/admin/organization/approval-templates')
        ->assertSessionHas('success');

    $template = ApprovalTemplate::where('code', 'CUTI_OR8')->firstOrFail();

    expect($template->name)->toBe('Approval Cuti OR8')
        ->and($template->steps()->count())->toBe(2)
        ->and($template->created_by)->toBe($user->id);

    organizationOr8Acting($user)
        ->get('/admin/organization/approval-templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/ApprovalTemplates/Index')
            ->where('stats.total', 1)
            ->where('data.rows.0.stepsCount', 2)
            ->where('data.rows.0.canDelete', true));

    organizationOr8Acting($user)
        ->put("/admin/organization/approval-templates/{$template->id}", [
            'name' => 'Approval Cuti OR8 Revisi',
            'code' => 'CUTI_OR8',
            'module' => 'organization',
            'description' => 'Alur cuti pegawai revisi.',
            'is_active' => 0,
            'steps' => [
                [
                    'name' => 'Persetujuan SDM',
                    'approver_type' => 'permission',
                    'approver_permission' => 'employee-leave-request.update',
                    'is_required' => 1,
                    'can_reject' => 1,
                    'sla_hours' => 12,
                ],
            ],
        ])
        ->assertRedirect('/admin/organization/approval-templates')
        ->assertSessionHas('success');

    $template->refresh();

    expect($template->name)->toBe('Approval Cuti OR8 Revisi')
        ->and((bool) $template->is_active)->toBeFalse()
        ->and($template->steps()->count())->toBe(1)
        ->and($template->steps()->withTrashed()->count())->toBe(1)
        ->and($template->steps()->first()->sla_hours)->toBe(12);

    organizationOr8Acting($user)
        ->from('/admin/organization/approval-templates')
        ->post("/admin/organization/approval-templates/{$template->id}/toggle", ['is_active' => 1])
        ->assertSessionHas('success');

    expect((bool) $template->refresh()->is_active)->toBeTrue();

    // Template yang sudah dipakai request tidak boleh dihapus.
    ApprovalRequest::create([
        'approval_template_id' => $template->id,
        'requester_user_id' => $user->id,
        'subject' => 'Pengajuan uji template',
        'status' => 'draft',
    ]);

    organizationOr8Acting($user)
        ->from('/admin/organization/approval-templates')
        ->delete("/admin/organization/approval-templates/{$template->id}")
        ->assertSessionHas('error');

    expect(ApprovalTemplate::find($template->id))->not->toBeNull();

    $template->requests()->forceDelete();

    organizationOr8Acting($user)
        ->delete("/admin/organization/approval-templates/{$template->id}")
        ->assertRedirect('/admin/organization/approval-templates')
        ->assertSessionHas('success');

    expect(ApprovalTemplate::find($template->id))->toBeNull();

    $bulkA = organizationOr8Template('OR8_BULK_A');
    $bulkB = organizationOr8Template('OR8_BULK_B');

    organizationOr8Acting($user)
        ->from('/admin/organization/approval-templates')
        ->post('/admin/organization/approval-templates/bulk-destroy', ['ids' => [$bulkA->id, $bulkB->id]])
        ->assertSessionHas('success');

    expect(ApprovalTemplate::find($bulkA->id))->toBeNull()
        ->and(ApprovalTemplate::find($bulkB->id))->toBeNull();
});

it('membuat, menampilkan, dan memproses permohonan approval', function () {
    $user = organizationOr8Setup();
    organizationOr8Permission(
        'approval-request.viewAny',
        'approval-request.view',
        'approval-request.create',
        'approval-request.update',
        'approval-request.delete',
    );

    $template = organizationOr8Template('OR8_REQUEST', 'approval-request.update');

    organizationOr8Acting($user)
        ->post('/admin/organization/approval-requests', [
            'approval_template_id' => $template->id,
            'subject' => 'Permohonan Uji OR8',
            'reference' => 'REF/OR8/001',
            'notes' => 'Catatan permohonan.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $approval = ApprovalRequest::where('subject', 'Permohonan Uji OR8')->firstOrFail();

    expect($approval->status)->toBe('in_progress')
        ->and($approval->requester_user_id)->toBe($user->id)
        ->and($approval->current_step_order)->toBe(1)
        ->and($approval->steps()->count())->toBe(1)
        ->and($approval->steps()->first()->status)->toBe('current');

    organizationOr8Acting($user)
        ->get("/admin/organization/approval-requests/{$approval->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/ApprovalRequests/Show')
            ->where('approval.status', 'in_progress')
            ->where('review.canAct', true)
            ->has('approval.steps', 1)
            ->has('approval.actions', 1));

    organizationOr8Acting($user)
        ->get('/admin/organization/approval-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/ApprovalRequests/Index')
            ->where('stats.total', 1)
            ->where('stats.in_progress', 1));

    organizationOr8Acting($user)
        ->from("/admin/organization/approval-requests/{$approval->id}")
        ->post("/admin/organization/approval-requests/{$approval->id}/approve", ['notes' => 'Disetujui.'])
        ->assertSessionHas('success');

    $approval->refresh();

    expect($approval->status)->toBe('approved')
        ->and($approval->completed_at)->not->toBeNull()
        ->and($approval->steps()->first()->status)->toBe('approved')
        ->and($approval->actions()->where('action', 'approved')->count())->toBe(1);

    // Permohonan yang sudah selesai tidak dapat dihapus.
    organizationOr8Acting($user)
        ->from('/admin/organization/approval-requests')
        ->delete("/admin/organization/approval-requests/{$approval->id}")
        ->assertSessionHas('error');

    expect(ApprovalRequest::find($approval->id))->not->toBeNull();

    $draft = ApprovalRequest::create([
        'approval_template_id' => $template->id,
        'requester_user_id' => $user->id,
        'subject' => 'Draft OR8',
        'status' => 'draft',
    ]);

    $cancelled = ApprovalRequest::create([
        'approval_template_id' => $template->id,
        'requester_user_id' => $user->id,
        'subject' => 'Batal OR8',
        'status' => 'cancelled',
    ]);

    organizationOr8Acting($user)
        ->from('/admin/organization/approval-requests')
        ->post('/admin/organization/approval-requests/bulk-destroy', [
            'ids' => [$draft->id, $cancelled->id, $approval->id],
        ])
        ->assertSessionHas('success');

    expect(ApprovalRequest::find($draft->id))->toBeNull()
        ->and(ApprovalRequest::find($cancelled->id))->toBeNull()
        ->and(ApprovalRequest::find($approval->id))->not->toBeNull();
});

it('mengajukan cuti pegawai, menampilkan saldo, dan membatalkan pengajuan', function () {
    $user = organizationOr8Setup();
    organizationOr8Permission(
        'employee-leave-request.viewAny',
        'employee-leave-request.view',
        'employee-leave-request.create',
        'employee-leave-request.update',
        'employee-leave-request.delete',
    );

    $employee = organizationOr8Employee();
    $template = organizationOr8Template('OR8_LEAVE', 'employee-leave-request.update');
    $type = EmployeeLeaveType::create([
        'approval_template_id' => $template->id,
        'name' => 'Cuti Tahunan OR8',
        'code' => 'CUTI_OR8',
        'default_days_per_year' => 12,
        'requires_approval' => true,
        'is_paid' => true,
        'is_active' => true,
    ]);

    $start = now()->addDays(5)->toDateString();
    $end = now()->addDays(7)->toDateString();

    organizationOr8Acting($user)
        ->post('/admin/organization/employee-leave-requests', [
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $type->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'reason' => 'Keperluan keluarga.',
            'employee_notes' => 'Dapat dihubungi via telepon.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $leave = EmployeeLeaveRequest::where('employee_profile_id', $employee->id)->firstOrFail();

    expect($leave->status)->toBe('in_approval')
        ->and((float) $leave->total_days)->toBe(3.0)
        ->and($leave->approval_request_id)->not->toBeNull()
        ->and($leave->created_by)->toBe($user->id);

    $balance = EmployeeLeaveBalance::where('employee_profile_id', $employee->id)
        ->where('employee_leave_type_id', $type->id)
        ->where('year', now()->addDays(5)->year)
        ->firstOrFail();

    expect((float) $balance->pending_days)->toBe(3.0);

    organizationOr8Acting($user)
        ->get("/admin/organization/employee-leave-requests/{$leave->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EmployeeLeaveRequests/Show')
            ->where('leave.status', 'in_approval')
            ->where('balance.pendingDays', 3)
            ->where('canCancel', true)
            ->has('leave.approval'));

    organizationOr8Acting($user)
        ->get('/admin/organization/employee-leave-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EmployeeLeaveRequests/Index')
            ->where('stats.total', 1)
            ->where('stats.in_approval', 1));

    organizationOr8Acting($user)
        ->from("/admin/organization/employee-leave-requests/{$leave->id}")
        ->post("/admin/organization/employee-leave-requests/{$leave->id}/cancel", ['notes' => 'Dibatalkan pegawai.'])
        ->assertSessionHas('success');

    $leave->refresh();
    $balance->refresh();

    expect($leave->status)->toBe('cancelled')
        ->and($leave->cancelled_by)->toBe($user->id)
        ->and($leave->cancelled_at)->not->toBeNull()
        ->and((float) $balance->pending_days)->toBe(0.0);

    organizationOr8Acting($user)
        ->delete("/admin/organization/employee-leave-requests/{$leave->id}")
        ->assertRedirect('/admin/organization/employee-leave-requests')
        ->assertSessionHas('success');

    expect(EmployeeLeaveRequest::find($leave->id))->toBeNull();
});

it('mencatat absensi pegawai manual, menampilkan detail, dan menghapus catatan', function () {
    $user = organizationOr8Setup();
    organizationOr8Permission(
        'employee-attendance-record.viewAny',
        'employee-attendance-record.view',
        'employee-attendance-record.create',
        'employee-attendance-record.delete',
    );

    $unit = organizationOr8WorkUnit('BRO-ABS');
    $employee = organizationOr8Employee(['primary_work_unit_id' => $unit->id]);

    organizationOr8Acting($user)
        ->post('/admin/organization/employee-attendance-records', [
            'employee_profile_id' => $employee->id,
            'attendance_date' => '2026-11-09',
            'status' => 'late',
            'check_in_at' => '2026-11-09 08:15:00',
            'check_out_at' => '2026-11-09 16:45:00',
            'work_unit_id' => $unit->id,
            'notes' => 'Terlambat karena ban bocor.',
        ])
        ->assertRedirect('/admin/organization/employee-attendance-records')
        ->assertSessionHas('success');

    $record = EmployeeAttendanceRecord::where('employee_profile_id', $employee->id)->firstOrFail();

    expect($record->status)->toBe('late')
        ->and($record->work_minutes)->toBe(510)
        ->and($record->work_unit_id)->toBe($unit->id)
        ->and($record->created_by)->toBe($user->id)
        ->and($record->source?->code)->toBe('MANUAL_ADMIN');

    organizationOr8Acting($user)
        ->get('/admin/organization/employee-attendance-records')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EmployeeAttendanceRecords/Index')
            ->where('stats.total', 1)
            ->where('data.rows.0.status', 'late')
            ->where('data.rows.0.workMinutes', 510));

    organizationOr8Acting($user)
        ->get('/admin/organization/employee-attendance-records?status=late&date=2026-11-09&work_unit_id='.$unit->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('data.total', 1));

    organizationOr8Acting($user)
        ->get("/admin/organization/employee-attendance-records/{$record->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EmployeeAttendanceRecords/Show')
            ->where('record.status', 'late')
            ->where('record.workMinutes', 510)
            ->where('record.checkIn.time', '08:15:00')
            ->where('record.checkOut.time', '16:45:00'));

    organizationOr8Acting($user)
        ->delete("/admin/organization/employee-attendance-records/{$record->id}")
        ->assertRedirect('/admin/organization/employee-attendance-records')
        ->assertSessionHas('success');

    expect(EmployeeAttendanceRecord::find($record->id))->toBeNull();

    $bulk = EmployeeAttendanceRecord::create([
        'employee_profile_id' => $employee->id,
        'attendance_date' => '2026-11-10',
        'status' => 'present',
        'work_minutes' => 480,
    ]);

    organizationOr8Acting($user)
        ->from('/admin/organization/employee-attendance-records')
        ->post('/admin/organization/employee-attendance-records/bulk-destroy', ['ids' => [$bulk->id]])
        ->assertSessionHas('success');

    expect(EmployeeAttendanceRecord::find($bulk->id))->toBeNull();
});
