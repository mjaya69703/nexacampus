<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\User;
use App\Support\Organization\ApprovalEngine;
use App\Support\Organization\EmployeePositionAssignmentService;
use App\Support\Financial\PaymentProcessingService;
use App\Support\StudentService\StudentLeaveApplicationService;
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

it('requires dedicated leave review before approval and issues the configured invoice', function () {
    $student = User::factory()->create();
    $approver = User::factory()->create();
    $faculty = Faculty::create([
        'name' => 'Teknik',
        'code' => 'FT-LEAVE',
        'is_active' => true,
    ]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Informatika',
        'code' => 'IF-LEAVE',
        'degree' => 'S1',
        'is_active' => true,
    ]);
    $studentProfile = StudentProfile::create([
        'user_id' => $student->id,
        'study_program_id' => $program->id,
        'nim' => 'LEAVE-2026-001',
        'is_active' => true,
    ]);

    $template = ApprovalTemplate::create([
        'name' => 'Student Leave Review',
        'code' => 'STUDENT_LEAVE_REVIEW',
        'module' => 'student-services',
        'is_active' => true,
    ]);
    $template->steps()->create([
        'step_order' => 1,
        'name' => 'Layanan Akademik',
        'approver_type' => 'user',
        'approver_user_id' => $approver->id,
    ]);

    $application = StudentLeaveApplication::create([
        'application_number' => 'LEV-TEST-001',
        'student_profile_id' => $studentProfile->id,
        'duration_semesters' => 1,
        'reason_category' => 'personal',
        'reason' => 'Keperluan keluarga.',
        'status' => 'in_approval',
    ]);

    $engine = app(ApprovalEngine::class);
    $approval = $engine->submitFromTemplate(
        template: $template,
        subject: 'Pengajuan cuti LEV-TEST-001',
        requester: $student,
        approvable: $application,
        reference: $application->application_number,
    );
    $application->update(['approval_request_id' => $approval->id]);

    expect(fn () => $engine->approve($approval, $approver))
        ->toThrow(ValidationException::class);

    $application = app(StudentLeaveApplicationService::class)->approve(
        application: $application->refresh(),
        notes: 'Disetujui dengan biaya administrasi.',
        userId: $approver->id,
        feeAmount: 150000,
        feeDueDate: '2026-06-20',
    );

    expect($approval->refresh()->status)->toBe('approved')
        ->and($application->status)->toBe('approved_pending_payment')
        ->and($application->leave_fee_invoice_id)->not->toBeNull()
        ->and($application->leaveFeeInvoice->invoice_type)->toBe('leave')
        ->and($application->leaveFeeInvoice->total_amount)->toBe('150000.00')
        ->and($application->leaveFeeInvoice->status)->toBe('issued');

    $payment = app(PaymentProcessingService::class)->submitProof(
        invoice: $application->leaveFeeInvoice,
        amount: 150000,
        proofPath: 'financial/payment-proofs/leave-test.pdf',
        submittedBy: $student->id,
    );
    app(PaymentProcessingService::class)->verify($payment, $approver->id);

    $application->refresh();

    expect($application->leaveFeeInvoice->refresh()->status)->toBe('paid')
        ->and($application->status)->toBe('approved')
        ->and($application->histories()->latest('id')->value('notes'))
        ->toBe('Pembayaran biaya cuti telah lunas. Pengajuan siap diaktifkan.');
});
