<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\InvoiceAdjustment;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function paymentFlowSetup(): array
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
    foreach ([
        'payment.viewAny', 'payment.view', 'payment.update',
        'invoice-adjustment.viewAny', 'student-invoice.view',
    ] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FP'.$suffix, 'code' => 'FP'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PP'.$suffix, 'code' => 'PP'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YP'.$suffix, 'code' => 'YP'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => 'P'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);

    $invoice = app(App\Support\Financial\InvoiceGenerationService::class)->createCustomInvoice(
        $student,
        [['item_type' => 'fee', 'description' => 'SPP', 'amount' => 2000000]],
        'custom',
        now()->addMonth()->format('Y-m-d'),
        $year,
        1,
        null,
        $admin->id,
        true,
    );

    $payment = app(App\Support\Financial\PaymentProcessingService::class)->submitProof(
        $invoice->fresh(),
        1000000,
        'bukti/test.jpg',
        'bank_transfer',
        'REF123',
        null,
        $studentUser->id,
    );

    return compact('admin', 'student', 'invoice', 'payment');
}

function paymentFlowActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

it('menampilkan daftar pembayaran dengan statistik', function () {
    $setup = paymentFlowSetup();

    paymentFlowActingAs($setup['admin'])
        ->get(route('admin.financial.payments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/Payment/Index')
            ->where('stats.total', 1)
            ->where('stats.pending', 1));
});

it('menampilkan detail pembayaran', function () {
    $setup = paymentFlowSetup();

    paymentFlowActingAs($setup['admin'])
        ->get(route('admin.financial.payments.show', $setup['payment']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/Payment/Show'));
});

it('memverifikasi pembayaran dan melunasi sebagian invoice', function () {
    $setup = paymentFlowSetup();

    paymentFlowActingAs($setup['admin'])
        ->post(route('admin.financial.payments.verify', $setup['payment']), [
            'verification_notes' => 'Bukti valid',
        ])
        ->assertRedirect();

    expect($setup['payment']->fresh()->status)->toBe('verified')
        ->and($setup['invoice']->fresh()->status)->toBe('partially_paid')
        ->and((float) $setup['invoice']->fresh()->paid_amount)->toBe(1000000.0);
});

it('menolak pembayaran', function () {
    $setup = paymentFlowSetup();

    paymentFlowActingAs($setup['admin'])
        ->post(route('admin.financial.payments.reject', $setup['payment']), [
            'verification_notes' => 'Bukti buram',
        ])
        ->assertRedirect();

    expect($setup['payment']->fresh()->status)->toBe('rejected');
});

it('mengunduh kwitansi pembayaran terverifikasi', function () {
    $setup = paymentFlowSetup();
    app(App\Support\Financial\PaymentProcessingService::class)->verify($setup['payment'], $setup['admin']->id);

    paymentFlowActingAs($setup['admin'])
        ->get(route('admin.financial.payments.receipt', $setup['payment']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('mencatat log penyesuaian dari detail invoice', function () {
    $setup = paymentFlowSetup();

    app(App\Support\Financial\InvoiceAdjustmentService::class)->apply(
        $setup['invoice']->fresh(),
        'penalty',
        100000,
        'Denda telat',
        $setup['admin']->id,
    );

    paymentFlowActingAs($setup['admin'])
        ->get(route('admin.financial.invoice-adjustments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/InvoiceAdjustment/Index')
            ->where('stats.total', 1)
            ->has('data.rows', 1));
});

it('mengekspor pembayaran dan penyesuaian', function () {
    $setup = paymentFlowSetup();

    paymentFlowActingAs($setup['admin'])
        ->get(route('admin.financial.payments.export', ['format' => 'xlsx']))
        ->assertOk();

    paymentFlowActingAs($setup['admin'])
        ->get(route('admin.financial.invoice-adjustments.export', ['format' => 'csv']))
        ->assertOk();
});
