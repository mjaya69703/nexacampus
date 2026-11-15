<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\InvoiceInstallmentRequest;
use App\Models\Financial\StudentInvoice;
use App\Models\Organization\ApprovalTemplate;
use App\Support\Organization\ApprovalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function installmentSetup(): array
{
    DB::table('systems')->updateOrInsert(['id' => 1], [
        'app_name' => 'NexaCampus Test',
        'app_version' => 'test',
        'app_description' => 'NexaCampus Test',
        'app_url' => 'https://example.test',
        'app_email' => 'campus@example.test',
        'is_installed' => true,
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    $role = Role::findOrCreate('admin', 'web');
    foreach (['installment-request.viewAny', 'installment-request.view', 'installment-request.update'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FC'.$suffix, 'code' => 'FC'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PC'.$suffix, 'code' => 'PC'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YC'.$suffix, 'code' => 'YC'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => 'C'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);

    $invoice = app(App\Support\Financial\InvoiceGenerationService::class)->createCustomInvoice(
        $student,
        [['item_type' => 'fee', 'description' => 'SPP', 'amount' => 3000000]],
        'custom',
        now()->addMonth()->format('Y-m-d'),
        $year,
        1,
        null,
        $admin->id,
        true,
    );

    $installmentRequest = $invoice->installmentRequests()->create([
        'student_profile_id' => $student->id,
        'requested_tenor' => 3,
        'requested_fee_amount' => 0,
        'simulated_total_amount' => 3000000,
        'simulation_snapshot' => ['installments' => [
            ['installment_no' => 1, 'amount' => 1000000, 'fee_amount' => 0, 'due_date' => now()->addMonth()->format('Y-m-d')],
            ['installment_no' => 2, 'amount' => 1000000, 'fee_amount' => 0, 'due_date' => now()->addMonths(2)->format('Y-m-d')],
            ['installment_no' => 3, 'amount' => 1000000, 'fee_amount' => 0, 'due_date' => now()->addMonths(3)->format('Y-m-d')],
        ]],
        'status' => 'submitted',
        'student_reason' => 'Butuh keringanan',
    ]);

    return compact('admin', 'student', 'invoice', 'installmentRequest');
}

function installmentActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function installmentApprovalChain(array $setup): void
{
    $template = ApprovalTemplate::create([
        'code' => 'INSTALLMENT_REQUEST_REVIEW',
        'name' => 'Review Cicilan',
        'is_active' => true,
    ]);
    $template->steps()->create([
        'name' => 'Keuangan',
        'step_order' => 1,
        'approver_type' => 'user',
        'approver_user_id' => $setup['admin']->id,
    ]);

    $approval = app(ApprovalEngine::class)->submitFromTemplate(
        $template,
        'Pengajuan cicilan test',
        $setup['student']->user,
        $setup['installmentRequest'],
    );

    $setup['installmentRequest']->update([
        'approval_request_id' => $approval->id,
        'status' => 'submitted',
    ]);
}

it('menampilkan daftar pengajuan cicilan', function () {
    $setup = installmentSetup();

    installmentActingAs($setup['admin'])
        ->get(route('admin.financial.installment-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/InstallmentRequest/Index')
            ->where('stats.total', 1)
            ->where('stats.submitted', 1));
});

it('menampilkan detail pengajuan', function () {
    $setup = installmentSetup();

    installmentActingAs($setup['admin'])
        ->get(route('admin.financial.installment-requests.show', $setup['installmentRequest']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/InstallmentRequest/Show'));
});

it('menyetujui pengajuan dan membentuk termin', function () {
    $setup = installmentSetup();
    installmentApprovalChain($setup);

    installmentActingAs($setup['admin'])
        ->post(route('admin.financial.installment-requests.approve', $setup['installmentRequest']), [
            'finance_notes' => 'Disetujui',
        ])
        ->assertRedirect();

    expect($setup['installmentRequest']->fresh()->status)->toBe('approved')
        ->and($setup['invoice']->installments()->count())->toBe(3);
});

it('menolak pengajuan tanpa rantai approval', function () {
    $setup = installmentSetup();

    installmentActingAs($setup['admin'])
        ->post(route('admin.financial.installment-requests.reject', $setup['installmentRequest']), [
            'finance_notes' => 'Belum memenuhi syarat',
        ])
        ->assertRedirect();

    expect($setup['installmentRequest']->fresh()->status)->toBe('rejected');
});

it('menolak putusan atas pengajuan yang sudah final', function () {
    $setup = installmentSetup();
    $setup['installmentRequest']->update(['status' => 'approved']);

    installmentActingAs($setup['admin'])
        ->post(route('admin.financial.installment-requests.approve', $setup['installmentRequest']))
        ->assertRedirect();

    expect($setup['installmentRequest']->fresh()->status)->toBe('approved')
        ->and($setup['invoice']->installments()->count())->toBe(0);
});
