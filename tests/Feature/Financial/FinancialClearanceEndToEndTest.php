<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Access\Role;
use App\Models\Financial\FinancialClearancePolicy;
use App\Models\Financial\FinancialHold;
use App\Models\Financial\InvoiceItem;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use App\Models\User;
use App\Support\Financial\FinancialClearanceService;
use App\Support\Financial\PaymentProcessingService;

function financialTestSetup(): array
{
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('finance');

    $faculty = Faculty::create(['name' => 'Fakultas Ekonomi', 'code' => 'FE', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Akuntansi', 'code' => 'AK', 'degree' => 'S1', 'is_active' => true]);
    $academicYear = AcademicYear::create(['name' => '2026/2027 Ganjil', 'code' => '2026G', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true]);

    $user = User::factory()->create();
    $user->assignRole('student');

    $student = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $academicYear->id,
        'nim' => '2026AK0001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => now()->toDateString(),
        'current_semester' => 1,
        'class_type' => 'regular',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $policy = FinancialClearancePolicy::create([
        'invoice_type' => 'tuition',
        'hold_type' => 'krs',
        'mode' => 'blocking',
        'grace_days' => 0, // blocks immediately when overdue
        'is_active' => true,
        'description' => 'Blokir pengisian KRS jika SPP/Tuition menunggak',
    ]);

    return compact('admin', 'faculty', 'program', 'academicYear', 'student', 'policy');
}

function createTestInvoice(array $setup, float $amount, int $overdueDays = 5): StudentInvoice
{
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-' . fake()->unique()->numerify('#####'),
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['academicYear']->id,
        'semester' => 1,
        'invoice_type' => 'tuition',
        'total_amount' => $amount,
        'paid_amount' => 0,
        'outstanding_amount' => $amount,
        'status' => $overdueDays > 0 ? 'overdue' : 'issued',
        'due_date' => $overdueDays > 0 ? now()->subDays($overdueDays)->toDateString() : now()->addDays(10)->toDateString(),
        'issued_at' => now()->subDays(20),
        'issued_by' => $setup['admin']->id,
    ]);

    InvoiceItem::create([
        'student_invoice_id' => $invoice->id,
        'item_type' => 'tuition',
        'description' => 'SPP Semester 1',
        'amount' => $amount,
    ]);

    return $invoice;
}

it('evaluates overdue invoice and creates blocking financial hold', function () {
    $setup = financialTestSetup();
    $invoice = createTestInvoice($setup, 5000000, 5); // 5 days overdue

    $clearanceService = app(FinancialClearanceService::class);
    $summary = $clearanceService->evaluate($setup['student']);

    expect($summary['has_blocking_hold'])->toBeTrue()
        ->and($clearanceService->hasBlockingHold($setup['student'], ['krs']))->toBeTrue();

    $hold = FinancialHold::query()
        ->where('student_profile_id', $setup['student']->id)
        ->where('student_invoice_id', $invoice->id)
        ->where('hold_type', 'krs')
        ->first();

    expect($hold)->not->toBeNull()
        ->and($hold->status)->toBe('active')
        ->and($hold->hold_type)->toBe('krs')
        ->and($hold->isBlocking())->toBeTrue();
});

it('automatically releases financial hold when finance verifies full payment', function () {
    $setup = financialTestSetup();
    $invoice = createTestInvoice($setup, 5000000, 10); // 10 days overdue

    $clearanceService = app(FinancialClearanceService::class);
    $clearanceService->evaluate($setup['student']);

    expect($clearanceService->hasBlockingHold($setup['student'], ['krs']))->toBeTrue();

    // Student submits proof of payment
    $paymentService = app(PaymentProcessingService::class);
    $payment = $paymentService->submitProof(
        invoice: $invoice,
        amount: 5000000,
        proofPath: 'proofs/INV-2026-002.jpg',
        paymentMethod: 'bank_transfer',
        transactionReference: 'TRX-12345'
    );

    expect($payment->status)->toBe('pending');

    // Finance verifies payment
    $verifiedPayment = $paymentService->verify($payment, $setup['admin']->id, 'Pembayaran lengkap verified.');

    expect($verifiedPayment->status)->toBe('verified')
        ->and((float) $invoice->fresh()->paid_amount)->toBe(5000000.0)
        ->and((float) $invoice->fresh()->outstanding_amount)->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and($clearanceService->hasBlockingHold($setup['student'], ['krs']))->toBeFalse();

    $hold = FinancialHold::query()
        ->where('student_profile_id', $setup['student']->id)
        ->where('student_invoice_id', $invoice->id)
        ->where('hold_type', 'krs')
        ->first();

    expect($hold->status)->toBe('released')
        ->and($hold->release_notes)->toContain('Auto-released');
});

it('prevents over-allocating payment pending beyond invoice outstanding amount', function () {
    $setup = financialTestSetup();
    $invoice = createTestInvoice($setup, 3000000, 0); // future due date

    $paymentService = app(PaymentProcessingService::class);
    $paymentService->submitProof($invoice, 3000000, 'proofs/1.jpg');

    expect(fn () => $paymentService->submitProof($invoice->fresh(), 1000000, 'proofs/2.jpg'))
        ->toThrow(\RuntimeException::class, 'Total payment pending akan melebihi outstanding invoice.');
});
