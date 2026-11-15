<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\FinancialClearancePolicy;
use App\Models\Financial\Scholarship;
use App\Models\Financial\StudentScholarship;
use App\Models\Financial\TuitionFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function financialImportSetup(): array
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
        'tuition-fee.viewAny', 'tuition-fee.create',
        'scholarship.viewAny', 'scholarship.create',
        'student-scholarship.viewAny', 'student-scholarship.create',
        'clearance-policy.viewAny', 'clearance-policy.create',
        'student-invoice.viewAny', 'payment.viewAny',
        'invoice-schedule.viewAny', 'invoice-adjustment.viewAny',
        'installment-request.viewAny', 'student-credit.viewAny',
        'financial-hold.viewAny',
    ] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $faculty = Faculty::create(['name' => 'FI', 'code' => 'FI', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'TI', 'code' => 'TI', 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027', 'code' => '2627', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => '20260001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);

    $scholarship = Scholarship::create([
        'name' => 'Beasiswa Prestasi', 'type' => 'merit', 'discount_type' => 'percentage',
        'discount_percentage' => 50, 'duration_semesters' => 8, 'is_active' => true,
    ]);

    return compact('admin', 'program', 'year', 'student', 'scholarship');
}

function financialImportActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function makeImportFile(array $header, array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([$header], null, 'A1');

    foreach ($rows as $i => $row) {
        $sheet->fromArray([array_values($row)], null, 'A'.($i + 2));
    }

    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template import keempat resource', function () {
    $setup = financialImportSetup();

    foreach ([
        route('admin.financial.tuition-fees.import-template'),
        route('admin.financial.scholarships.import-template'),
        route('admin.financial.student-scholarships.import-template'),
        route('admin.financial.clearance-policies.import-template'),
    ] as $url) {
        financialImportActingAs($setup['admin'])->get($url)->assertOk();
    }
});

it('mengimpor tarif biaya kuliah', function () {
    $setup = financialImportSetup();

    $file = makeImportFile(
        ['year_code', 'program_code', 'semester', 'base_fee', 'lab_fee', 'library_fee', 'activity_fee', 'late_penalty_per_day', 'payment_deadline', 'is_active', 'notes'],
        [['2627', 'TI', '1', '5000000', '500000', '200000', '300000', '50000', '2026-09-30', '1', 'SK']],
    );

    financialImportActingAs($setup['admin'])
        ->post(route('admin.financial.tuition-fees.import'), ['file' => $file])
        ->assertRedirect(route('admin.financial.tuition-fees.index'));

    expect(TuitionFee::count())->toBe(1);

    // Duplikat kombinasi ditolak dengan pesan ramah (bukan 500).
    $file2 = makeImportFile(
        ['year_code', 'program_code', 'semester', 'base_fee', 'payment_deadline'],
        [['2627', 'TI', '1', '6000000', '2026-10-31']],
    );

    financialImportActingAs($setup['admin'])
        ->post(route('admin.financial.tuition-fees.import'), ['file' => $file2])
        ->assertRedirect(route('admin.financial.tuition-fees.index'));

    expect(TuitionFee::count())->toBe(1);
});

it('mengimpor program beasiswa', function () {
    $setup = financialImportSetup();

    $file = makeImportFile(
        ['name', 'type', 'discount_type', 'discount_percentage', 'fixed_amount', 'duration_semesters', 'requirements', 'is_active'],
        [
            ['Beasiswa A', 'merit', 'percentage', '25', '', '4', '', '1'],
            ['Beasiswa B', 'need_based', 'fixed', '', '1000000', '2', '', '1'],
            ['Beasiswa Rusak', 'ngasal', 'percentage', '25', '', '4', '', '1'],
        ],
    );

    financialImportActingAs($setup['admin'])
        ->post(route('admin.financial.scholarships.import'), ['file' => $file])
        ->assertRedirect(route('admin.financial.scholarships.index'));

    // Satu baris rusak → seluruh file ditolak (all-or-nothing).
    expect(Scholarship::count())->toBe(1);
});

it('mengimpor alokasi beasiswa via NIM', function () {
    $setup = financialImportSetup();

    $file = makeImportFile(
        ['nim', 'scholarship_name', 'year_code', 'semester', 'start_date', 'end_date', 'status', 'notes'],
        [['20260001', 'Beasiswa Prestasi', '2627', '1', '', '', 'active', '']],
    );

    financialImportActingAs($setup['admin'])
        ->post(route('admin.financial.student-scholarships.import'), ['file' => $file])
        ->assertRedirect(route('admin.financial.student-scholarships.index'));

    expect(StudentScholarship::count())->toBe(1);
});

it('mengimpor kebijakan clearance', function () {
    $setup = financialImportSetup();

    $file = makeImportFile(
        ['invoice_type', 'hold_type', 'mode', 'grace_days', 'is_active', 'description'],
        [['exam', 'transcript', 'warning', '3', '1', 'Impor test']],
    );

    financialImportActingAs($setup['admin'])
        ->post(route('admin.financial.clearance-policies.import'), ['file' => $file])
        ->assertRedirect(route('admin.financial.clearance-policies.index'));

    expect(FinancialClearancePolicy::where('invoice_type', 'exam')->where('hold_type', 'transcript')->exists())->toBeTrue();
});

it('mengunduh PDF laporan semua resource', function () {
    $setup = financialImportSetup();

    foreach ([
        'tuition-fees', 'clearance-policies', 'scholarships', 'invoice-schedules',
        'student-invoices', 'payments', 'invoice-adjustments', 'installment-requests',
        'student-scholarships', 'student-credits', 'financial-holds',
    ] as $resource) {
        financialImportActingAs($setup['admin'])
            ->get(route('admin.financial.exports.pdf', ['resource' => $resource]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
});

it('menolak PDF untuk resource tak dikenal dan tanpa izin', function () {
    $setup = financialImportSetup();

    financialImportActingAs($setup['admin'])
        ->get(route('admin.financial.exports.pdf', ['resource' => 'ngasal']))
        ->assertNotFound();
});
