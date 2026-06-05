<?php

use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use App\Models\User;
use App\Support\Organization\ApprovalEngine;
use App\Support\Organization\EmployeePositionAssignmentService;
use Illuminate\Validation\ValidationException;

it('submits approval requests and advances sequential steps', function () {
    $requester = User::factory()->create();
    $firstApprover = User::factory()->create();
    $secondApprover = User::factory()->create();

    $template = ApprovalTemplate::create([
        'name' => 'Sequential Approval',
        'code' => 'SEQ_APPROVAL',
        'module' => 'organization',
        'is_active' => true,
    ]);
    $template->steps()->create([
        'step_order' => 1,
        'name' => 'First Approval',
        'approver_type' => 'user',
        'approver_user_id' => $firstApprover->id,
    ]);
    $template->steps()->create([
        'step_order' => 2,
        'name' => 'Second Approval',
        'approver_type' => 'user',
        'approver_user_id' => $secondApprover->id,
    ]);

    $engine = app(ApprovalEngine::class);
    $request = $engine->submitFromTemplate($template, 'Test approval', $requester);

    expect($request->status)->toBe('in_progress')
        ->and($request->current_step_order)->toBe(1)
        ->and($request->steps()->where('status', 'current')->first()->name)->toBe('First Approval');

    $request = $engine->approve($request, $firstApprover, 'OK first');

    expect($request->status)->toBe('in_progress')
        ->and($request->current_step_order)->toBe(2)
        ->and($request->steps()->where('step_order', 1)->first()->status)->toBe('approved')
        ->and($request->steps()->where('step_order', 2)->first()->status)->toBe('current');

    $request = $engine->approve($request, $secondApprover, 'OK final');

    expect($request->status)->toBe('approved')
        ->and($request->current_step_order)->toBeNull()
        ->and($request->completed_at)->not->toBeNull()
        ->and($request->actions()->where('action', 'approved')->count())->toBe(2);
});

it('rejects approval requests and skips pending steps', function () {
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $secondApprover = User::factory()->create();

    $template = ApprovalTemplate::create([
        'name' => 'Rejectable Approval',
        'code' => 'REJECTABLE_APPROVAL',
        'module' => 'organization',
        'is_active' => true,
    ]);
    $template->steps()->create([
        'step_order' => 1,
        'name' => 'Reviewer',
        'approver_type' => 'user',
        'approver_user_id' => $approver->id,
    ]);
    $template->steps()->create([
        'step_order' => 2,
        'name' => 'Final Reviewer',
        'approver_type' => 'user',
        'approver_user_id' => $secondApprover->id,
    ]);

    $engine = app(ApprovalEngine::class);
    $request = $engine->submitFromTemplate($template, 'Rejected request', $requester);
    $request = $engine->reject($request, $approver, 'Not eligible');

    expect($request->status)->toBe('rejected')
        ->and($request->current_step_order)->toBeNull()
        ->and($request->steps()->where('step_order', 1)->first()->status)->toBe('rejected')
        ->and($request->steps()->where('step_order', 2)->first()->status)->toBe('skipped')
        ->and($request->actions()->where('action', 'rejected')->exists())->toBeTrue();
});

it('authorizes approval by organizational position and work unit scope', function () {
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $otherUser = User::factory()->create();
    $unit = WorkUnit::create(['name' => 'Human Capital', 'code' => 'HC', 'is_active' => true]);
    $employee = EmployeeProfile::create([
        'user_id' => $approver->id,
        'employee_number' => 'APP-001',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $position = OrganizationalPosition::where('code', 'KEPALA_UNIT')->first();

    app(EmployeePositionAssignmentService::class)->create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => $position->id,
        'work_unit_id' => $unit->id,
        'is_active' => true,
    ]);

    $template = ApprovalTemplate::create([
        'name' => 'Unit Head Approval',
        'code' => 'UNIT_HEAD_APPROVAL',
        'module' => 'organization',
        'is_active' => true,
    ]);
    $template->steps()->create([
        'step_order' => 1,
        'name' => 'Kepala Unit',
        'approver_type' => 'position',
        'organizational_position_id' => $position->id,
        'work_unit_id' => $unit->id,
    ]);

    $engine = app(ApprovalEngine::class);
    $request = $engine->submitFromTemplate($template, 'Scoped approval', $requester);
    $step = $request->steps()->first();

    expect($engine->canUserActOnStep($approver, $step))->toBeTrue()
        ->and($engine->canUserActOnStep($otherUser, $step))->toBeFalse();

    expect(fn () => $engine->approve($request, $otherUser))
        ->toThrow(ValidationException::class);

    $request = $engine->approve($request, $approver);

    expect($request->status)->toBe('approved');
});
