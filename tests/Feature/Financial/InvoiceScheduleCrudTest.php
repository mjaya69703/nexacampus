<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\InvoiceSchedule;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\TuitionFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function invoiceScheduleSetup(): array
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
    foreach (['invoice-schedule.viewAny', 'invoice-schedule.create', 'invoice-schedule.update', 'invoice-schedule.delete'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FS'.$suffix, 'code' => 'FS'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PS'.$suffix, 'code' => 'PS'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YS'.$suffix, 'code' => 'YS'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => 'S'.$suffix,
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
        'payment_deadline' => '2026-09-30',
        'is_active' => true,
    ]);

    return compact('admin', 'student', 'program', 'year');
}

function invoiceScheduleActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function invoiceSchedulePayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'name' => 'Tagihan SPP Ganjil',
        'invoice_kind' => 'tuition',
        'generation_mode' => 'active_students',
        'academic_year_id' => $setup['year']->id,
        'semester' => 1,
        'invoice_type' => 'tuition',
        'publish_at' => now()->addHour()->format('Y-m-d\TH:i'),
        'due_date' => now()->addMonth()->format('Y-m-d'),
        'issue_immediately' => true,
        'is_active' => true,
    ], $overrides);
}

it('menampilkan daftar jadwal', function () {
    $setup = invoiceScheduleSetup();
    InvoiceSchedule::create(array_merge(invoiceSchedulePayload($setup), [
        'due_date' => now()->addMonth()->format('Y-m-d'),
        'publish_at' => now()->addHour()->format('Y-m-d H:i:s'),
        'status' => 'pending',
    ]));

    invoiceScheduleActingAs($setup['admin'])
        ->get(route('admin.financial.invoice-schedules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/InvoiceSchedule/Index')
            ->where('stats.total', 1));
});

it('membuat jadwal tuition dan custom', function () {
    $setup = invoiceScheduleSetup();

    invoiceScheduleActingAs($setup['admin'])
        ->post(route('admin.financial.invoice-schedules.store'), invoiceSchedulePayload($setup))
        ->assertRedirect(route('admin.financial.invoice-schedules.index'));

    invoiceScheduleActingAs($setup['admin'])
        ->post(route('admin.financial.invoice-schedules.store'), invoiceSchedulePayload($setup, [
            'name' => 'Denda Perpustakaan',
            'invoice_kind' => 'custom',
            'generation_mode' => 'single',
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => null,
            'semester' => null,
            'invoice_type' => 'library_fine',
            'items' => [['item_type' => 'penalty', 'description' => 'Denda telat', 'amount' => 50000]],
        ]))
        ->assertRedirect();

    expect(InvoiceSchedule::count())->toBe(2);
});

it('jadwal custom wajib punya item', function () {
    $setup = invoiceScheduleSetup();

    invoiceScheduleActingAs($setup['admin'])
        ->post(route('admin.financial.invoice-schedules.store'), invoiceSchedulePayload($setup, [
            'invoice_kind' => 'custom',
            'invoice_type' => 'other',
            'items' => [],
        ]))
        ->assertSessionHasErrors('items');
});

it('eksekusi jadwal menerbitkan invoice', function () {
    $setup = invoiceScheduleSetup();
    $schedule = InvoiceSchedule::create(array_merge(invoiceSchedulePayload($setup), [
        'due_date' => now()->addMonth()->format('Y-m-d'),
        'publish_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'status' => 'pending',
    ]));

    invoiceScheduleActingAs($setup['admin'])
        ->post(route('admin.financial.invoice-schedules.run', $schedule))
        ->assertRedirect();

    expect($schedule->fresh()->status)->toBe('completed')
        ->and(StudentInvoice::where('invoice_schedule_id', $schedule->id)->count())->toBe(1);
});

it('jadwal selesai terkunci dari ubahan', function () {
    $setup = invoiceScheduleSetup();
    $schedule = InvoiceSchedule::create(array_merge(invoiceSchedulePayload($setup), [
        'due_date' => now()->addMonth()->format('Y-m-d'),
        'publish_at' => now()->format('Y-m-d H:i:s'),
        'status' => 'completed',
    ]));

    invoiceScheduleActingAs($setup['admin'])
        ->put(route('admin.financial.invoice-schedules.update', $schedule), invoiceSchedulePayload($setup, ['name' => 'Diubah']))
        ->assertRedirect();

    expect($schedule->fresh()->name)->toBe('Tagihan SPP Ganjil');
});

it('menghapus jadwal', function () {
    $setup = invoiceScheduleSetup();
    $schedule = InvoiceSchedule::create(array_merge(invoiceSchedulePayload($setup), [
        'due_date' => now()->addMonth()->format('Y-m-d'),
        'publish_at' => now()->format('Y-m-d H:i:s'),
        'status' => 'pending',
    ]));

    invoiceScheduleActingAs($setup['admin'])
        ->delete(route('admin.financial.invoice-schedules.destroy', $schedule))
        ->assertRedirect();

    expect(InvoiceSchedule::count())->toBe(0);
});
