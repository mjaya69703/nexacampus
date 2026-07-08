<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyProgram;
use App\Models\Access\Role;
use App\Models\Financial\InvoiceItem;
use App\Models\Financial\StudentInvoice;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\ApprovalTemplateStep;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\User;
use App\Support\StudentService\StudentLeaveApplicationService;
use App\Support\Financial\PaymentProcessingService;

function studentServiceTestSetup(): array
{
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->assignRole('finance');

    $faculty = Faculty::create(['name' => 'Fakultas Ilmu Komputer', 'code' => 'FIK', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Teknik Informatika', 'code' => 'TI', 'degree' => 'S1', 'is_active' => true]);
    $academicYear = AcademicYear::create(['name' => '2026/2027 Ganjil', 'code' => '2026G', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true]);

    $user = User::factory()->create();
    $user->assignRole('student');

    $student = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $academicYear->id,
        'nim' => '2026TI0001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => now()->toDateString(),
        'current_semester' => 2,
        'class_type' => 'regular',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $template = ApprovalTemplate::create([
        'name' => 'Review Cuti Akademik',
        'code' => 'STUDENT_LEAVE_REVIEW',
        'module' => 'StudentService',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    ApprovalTemplateStep::create([
        'approval_template_id' => $template->id,
        'step_order' => 1,
        'name' => 'Review Kaprodi',
        'approver_type' => 'role',
        'approver_role' => 'admin',
        'is_required' => true,
        'can_reject' => true,
    ]);

    return compact('admin', 'faculty', 'program', 'academicYear', 'student', 'template');
}

it('creates student leave application and triggers approval workflow', function () {
    $setup = studentServiceTestSetup();

    $service = app(StudentLeaveApplicationService::class);
    $application = $service->create($setup['student'], [
        'academic_year_id' => $setup['academicYear']->id,
        'semester' => 2,
        'duration_semesters' => 1,
        'reason_category' => 'health',
        'reason' => 'Perawatan medis intensif selama 6 bulan.',
        'student_notes' => 'Surat dokter terlampir.',
    ]);

    expect($application)->toBeInstanceOf(StudentLeaveApplication::class)
        ->and($application->status)->toBe('in_approval')
        ->and($application->approval_request_id)->not->toBeNull()
        ->and($application->application_number)->toContain('LEV-');
});

it('approves leave application with fee invoice and activates leave after full payment', function () {
    $setup = studentServiceTestSetup();

    $service = app(StudentLeaveApplicationService::class);
    $application = $service->create($setup['student'], [
        'academic_year_id' => $setup['academicYear']->id,
        'semester' => 2,
        'duration_semesters' => 1,
        'reason_category' => 'personal',
        'reason' => 'Urusan keluarga darurat.',
    ]);

    // Admin sets leave fee and approves from approval engine
    $application->update([
        'leave_fee_amount' => 500000,
        'leave_fee_due_date' => now()->addDays(14),
    ]);

    $approvedApp = $service->approveFromApproval($application, $setup['admin']->id, 'Cuti disetujui, harap bayar biaya administrasi cuti.');

    expect($approvedApp->status)->toBe('approved_pending_payment')
        ->and($approvedApp->leave_fee_invoice_id)->not->toBeNull();

    $invoice = $approvedApp->leaveFeeInvoice;
    expect((float) $invoice->total_amount)->toBe(500000.0)
        ->and($invoice->status)->toBe('issued');

    // Attempting to activate before payment should throw RuntimeException
    expect(fn () => $service->activate($approvedApp, $setup['admin']->id))
        ->toThrow(\RuntimeException::class, 'Biaya cuti harus lunas sebelum cuti diaktifkan.');

    // Student submits payment and finance verifies
    $paymentService = app(PaymentProcessingService::class);
    $payment = $paymentService->submitProof($invoice, 500000, 'proofs/leave-fee.jpg');
    $paymentService->verify($payment, $setup['admin']->id, 'Lunas');

    expect($approvedApp->fresh()->status)->toBe('approved');

    // Admin activates leave
    $activatedApp = $service->activate($approvedApp->fresh(), $setup['admin']->id, 'Status cuti resmi diaktifkan.');

    expect($activatedApp->status)->toBe('activated')
        ->and($setup['student']->fresh()->academic_status)->toBe('Cuti')
        ->and($setup['student']->fresh()->is_active)->toBeFalse();

    $registration = StudentRegistration::where('student_profile_id', $setup['student']->id)
        ->where('academic_year_id', $setup['academicYear']->id)
        ->first();

    expect($registration->academic_status)->toBe('Cuti')
        ->and($registration->registration_status)->toBe('Approved');
});

it('returns student from active leave back to active academic status', function () {
    $setup = studentServiceTestSetup();

    $service = app(StudentLeaveApplicationService::class);
    $application = $service->create($setup['student'], [
        'academic_year_id' => $setup['academicYear']->id,
        'semester' => 2,
        'duration_semesters' => 1,
        'reason_category' => 'other',
        'reason' => 'Magang kerja',
    ]);

    $application->update(['leave_fee_amount' => 0]);
    $approvedApp = $service->approveFromApproval($application, $setup['admin']->id, 'Disetujui tanpa biaya.');
    $activatedApp = $service->activate($approvedApp, $setup['admin']->id);

    expect($setup['student']->fresh()->academic_status)->toBe('Cuti');

    // Return to active
    $returnedApp = $service->returnToActive($activatedApp, $setup['admin']->id, 'Masa cuti berakhir, mahasiswa aktif kembali.');

    expect($returnedApp->status)->toBe('returned')
        ->and($setup['student']->fresh()->academic_status)->toBe('Aktif')
        ->and($setup['student']->fresh()->is_active)->toBeTrue();
});
