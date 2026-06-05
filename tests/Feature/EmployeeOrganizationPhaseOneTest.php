<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use App\Models\User;
use App\Support\Organization\EmployeePositionAssignmentService;
use App\Support\Organization\PositionScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('seeds core campus positions', function () {
    expect(OrganizationalPosition::query()->whereIn('code', [
        'REKTOR',
        'WAKIL_REKTOR',
        'DEKAN',
        'WAKIL_DEKAN',
        'KAPRODI',
        'SEKPRODI',
        'KEPALA_UNIT',
        'STAFF_UNIT',
        'TENDIK',
        'DOSEN',
    ])->count())->toBe(10);
});

it('resolves faculty study program and work unit scopes from active position assignments', function () {
    $user = User::factory()->create();
    $employee = EmployeeProfile::create([
        'user_id' => $user->id,
        'employee_number' => 'EMP-001',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $faculty = Faculty::create(['name' => 'Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Informatika', 'code' => 'IF', 'degree' => 'S1', 'is_active' => true]);
    $unit = WorkUnit::create(['name' => 'Akademik', 'code' => 'BAAK', 'is_active' => true]);
    $service = app(EmployeePositionAssignmentService::class);

    $service->create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => OrganizationalPosition::where('code', 'DEKAN')->value('id'),
        'faculty_id' => $faculty->id,
        'is_active' => true,
    ]);
    $service->create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => OrganizationalPosition::where('code', 'KAPRODI')->value('id'),
        'study_program_id' => $program->id,
        'is_active' => true,
    ]);
    $service->create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => OrganizationalPosition::where('code', 'KEPALA_UNIT')->value('id'),
        'work_unit_id' => $unit->id,
        'is_active' => true,
    ]);

    $resolver = app(PositionScopeResolver::class);

    expect($resolver->canAccessFaculty($user, $faculty->id))->toBeTrue()
        ->and($resolver->canAccessStudyProgram($user, $program->id))->toBeTrue()
        ->and($resolver->canAccessWorkUnit($user, $unit->id))->toBeTrue();
});

it('syncs active work unit position assignment into work unit membership', function () {
    $user = User::factory()->create();
    $employee = EmployeeProfile::create([
        'user_id' => $user->id,
        'employee_number' => 'EMP-002',
        'employment_type' => 'tendik',
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $unit = WorkUnit::create(['name' => 'Keuangan', 'code' => 'FIN', 'is_active' => true]);

    app(EmployeePositionAssignmentService::class)->create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => OrganizationalPosition::where('code', 'KEPALA_UNIT')->value('id'),
        'work_unit_id' => $unit->id,
        'is_active' => true,
    ]);

    expect($user->workUnits()->where('work_units.id', $unit->id)->exists())->toBeTrue()
        ->and($user->workUnits()->where('work_units.id', $unit->id)->first()->pivot->position)->toBe('head');
});

it('requires the matching scope for scoped positions', function () {
    $user = User::factory()->create();
    $employee = EmployeeProfile::create([
        'user_id' => $user->id,
        'employee_number' => 'EMP-003',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $service = app(EmployeePositionAssignmentService::class);

    expect(fn () => $service->create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => OrganizationalPosition::where('code', 'DEKAN')->value('id'),
        'is_active' => true,
    ]))->toThrow(ValidationException::class);

    expect(fn () => $service->create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => OrganizationalPosition::where('code', 'KAPRODI')->value('id'),
        'is_active' => true,
    ]))->toThrow(ValidationException::class);

    expect(fn () => $service->create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => OrganizationalPosition::where('code', 'KEPALA_UNIT')->value('id'),
        'is_active' => true,
    ]))->toThrow(ValidationException::class);
});
