<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\TuitionFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function studentInvoiceSetup(): array
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
    foreach (['student-invoice.viewAny', 'student-invoice.view', 'student-invoice.create', 'student-invoice.update', 'student-invoice.delete'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FI'.$suffix, 'code' => 'FI'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PI'.$suffix, 'code' => 'PI'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YI'.$suffix, 'code' => 'YI'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => 'I'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);
    StudentRegistration::create([
        'student_profile_id' => $student->id,
        'academic_year_id' => $year->id,
        'semester_no' => 1,
        'registration_status' => 'Approved',
        'academic_status' => 'Aktif',
        'is_active' => true,
    ]);

    TuitionFee::create([
        'academic_year_id' => $year->id,
        'study_program_id' => $program->id,
        'semester' => 1,
        'base_fee' => 5000000,
        'lab_fee' => 500000,
        'payment_deadline' => now()->addMonth()->format('Y-m-d'),
        'is_active' => true,
    ]);

    return compact('admin', 'student', 'program', 'year');
}

function studentInvoiceActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function makeDraftInvoice(array $setup): StudentInvoice
{
    return app(App\Support\Financial\InvoiceGenerationService::class)->createCustomInvoice(
        $setup['student'],
        [['item_type' => 'fee', 'description' => 'SPP', 'amount' => 1000000]],
        'custom',
        now()->addMonth()->format('Y-m-d'),
        $setup['year'],
        1,
        null,
        $setup['admin']->id,
        false,
    );
}

it('menampilkan daftar tagihan', function () {
    $setup = studentInvoiceSetup();
    makeDraftInvoice($setup);

    studentInvoiceActingAs($setup['admin'])
        ->get(route('admin.financial.student-invoices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/StudentInvoice/Index')
            ->where('stats.total', 1));
});

it('menerbitkan tagihan SPP tunggal', function () {
    $setup = studentInvoiceSetup();

    studentInvoiceActingAs($setup['admin'])
        ->post(route('admin.financial.student-invoices.store'), [
            'invoice_kind' => 'tuition',
            'generation_mode' => 'single',
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => $setup['year']->id,
            'semester' => 1,
            'invoice_type' => 'tuition',
            'issue_immediately' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.financial.student-invoices.index'));

    $invoice = StudentInvoice::first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe('issued')
        ->and((float) $invoice->total_amount)->toBe(5500000.0);
});

it('menerbitkan tagihan kustom dengan item', function () {
    $setup = studentInvoiceSetup();

    studentInvoiceActingAs($setup['admin'])
        ->post(route('admin.financial.student-invoices.store'), [
            'invoice_kind' => 'custom',
            'generation_mode' => 'single',
            'student_profile_id' => $setup['student']->id,
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'invoice_type' => 'library_fine',
            'issue_immediately' => false,
            'items' => [['item_type' => 'penalty', 'description' => 'Denda', 'amount' => 75000]],
        ])
        ->assertRedirect(route('admin.financial.student-invoices.index'));

    expect(StudentInvoice::where('status', 'draft')->count())->toBe(1);
});

it('menerbitkan draft menjadi issued', function () {
    $setup = studentInvoiceSetup();
    $invoice = makeDraftInvoice($setup);

    studentInvoiceActingAs($setup['admin'])
        ->post(route('admin.financial.student-invoices.issue', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe('issued');
});

it('menerapkan penyesuaian dan menghitung ulang total', function () {
    $setup = studentInvoiceSetup();
    $invoice = makeDraftInvoice($setup);
    app(App\Support\Financial\InvoicePublishingService::class)->issue($invoice, $setup['admin']->id);

    studentInvoiceActingAs($setup['admin'])
        ->post(route('admin.financial.student-invoices.adjust', $invoice), [
            'adjustment_type' => 'discount',
            'amount' => 200000,
            'reason' => 'Diskon khusus',
        ])
        ->assertRedirect();

    expect((float) $invoice->fresh()->total_amount)->toBe(800000.0);
});

it('membatalkan tagihan', function () {
    $setup = studentInvoiceSetup();
    $invoice = makeDraftInvoice($setup);

    studentInvoiceActingAs($setup['admin'])
        ->post(route('admin.financial.student-invoices.cancel', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe('cancelled');
});

it('menolak ubah tagihan yang sudah lunas', function () {
    $setup = studentInvoiceSetup();
    $invoice = makeDraftInvoice($setup);
    $invoice->update(['status' => 'paid', 'paid_amount' => 1000000, 'outstanding_amount' => 0, 'paid_at' => now()]);

    studentInvoiceActingAs($setup['admin'])
        ->get(route('admin.financial.student-invoices.edit', $invoice))
        ->assertForbidden();
});

it('menampilkan detail dan mengunduh PDF', function () {
    $setup = studentInvoiceSetup();
    $invoice = makeDraftInvoice($setup);

    studentInvoiceActingAs($setup['admin'])
        ->get(route('admin.financial.student-invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/StudentInvoice/Show'));

    studentInvoiceActingAs($setup['admin'])
        ->get(route('admin.financial.student-invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
