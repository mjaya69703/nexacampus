<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function academicYearCrudSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = App\Models\User::factory()->create();
    $operator->assignRole('operator');

    return compact('operator');
}

function giveAcademicYearPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAcademicYearOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeAcademicYear(array $overrides = []): AcademicYear
{
    return AcademicYear::create(array_merge([
        'name' => '2026/2027 Ganjil',
        'code' => '2627G'.fake()->unique()->numerify('##'),
        'semester' => 'Ganjil',
        'start_date' => '2026-08-01',
        'end_date' => '2027-01-31',
        'is_active' => false,
    ], $overrides));
}

it('menampilkan daftar tahun dengan statistik', function () {
    $setup = academicYearCrudSetup();
    giveAcademicYearPermissionsToOperator(['academic-year.viewAny']);

    makeAcademicYear(['is_active' => true]);

    actingAcademicYearOperator($setup['operator'])
        ->get('/admin/academic/academic-years')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/AcademicYear/Index')
            ->where('stats.total', 1)
            ->where('stats.active', 1)
            ->where('stats.ganjil', 1)
            ->has('data.rows', 1));
});

it('mengaktifkan satu tahun menonaktifkan sisanya', function () {
    $setup = academicYearCrudSetup();
    giveAcademicYearPermissionsToOperator(['academic-year.create', 'academic-year.update']);

    $first = makeAcademicYear(['code' => 'Y1', 'is_active' => true]);

    // Tambah aktif baru → lama mati.
    actingAcademicYearOperator($setup['operator'])
        ->post('/admin/academic/academic-years', [
            'name' => '2026/2027 Genap', 'code' => 'Y2', 'semester' => 'Genap',
            'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => true,
        ])
        ->assertRedirect('/admin/academic/academic-years');

    expect((bool) $first->fresh()->is_active)->toBeFalse()
        ->and(AcademicYear::where('is_active', true)->count())->toBe(1);

    // Toggle lama kembali → baru mati.
    actingAcademicYearOperator($setup['operator'])
        ->post("/admin/academic/academic-years/{$first->id}/toggle", ['is_active' => true])
        ->assertSessionHas('success');

    expect((bool) $first->fresh()->is_active)->toBeTrue()
        ->and(AcademicYear::where('is_active', true)->count())->toBe(1);
});

it('memvalidasi rentang tanggal tahun', function () {
    $setup = academicYearCrudSetup();
    giveAcademicYearPermissionsToOperator(['academic-year.create']);

    actingAcademicYearOperator($setup['operator'])
        ->post('/admin/academic/academic-years', [
            'name' => 'Rusak', 'code' => 'RX', 'semester' => 'Ganjil',
            'start_date' => '2027-01-31', 'end_date' => '2026-08-01',
        ])
        ->assertSessionHasErrors('end_date');

    expect(AcademicYear::where('code', 'RX')->count())->toBe(0);
});

it('menolak hapus tahun yang terikat periode', function () {
    $setup = academicYearCrudSetup();
    giveAcademicYearPermissionsToOperator(['academic-year.delete']);

    $year = makeAcademicYear();
    AcademicPeriod::create([
        'academic_year_id' => $year->id, 'name' => 'KRS Ganjil',
        'type' => 'Study Plan', 'start_at' => '2026-08-01 00:00:00',
        'end_at' => '2026-08-31 23:59:59', 'is_active' => true,
    ]);

    actingAcademicYearOperator($setup['operator'])
        ->delete("/admin/academic/academic-years/{$year->id}")
        ->assertSessionHas('error');

    expect(AcademicYear::find($year->id))->not->toBeNull();
});

it('mengimpor tahun dan menjaga satu tahun aktif', function () {
    $setup = academicYearCrudSetup();
    giveAcademicYearPermissionsToOperator(['academic-year.create']);

    $active = makeAcademicYear(['code' => 'AKTIF', 'is_active' => true]);

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['name', 'code', 'semester', 'start_date', 'end_date', 'is_active'],
        ['2026/2027 Genap', 'IMP1', 'Genap', '2027-02-01', '2027-07-31', '1'],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'import-years').'.xlsx';
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
    $file = new Illuminate\Http\UploadedFile($path, 'years.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    actingAcademicYearOperator($setup['operator'])
        ->post('/admin/academic/academic-years/import', ['file' => $file])
        ->assertRedirect('/admin/academic/academic-years')
        ->assertSessionHas('success');

    // Baris impor bertanda aktif menjadi satu-satunya tahun aktif.
    expect((bool) AcademicYear::where('code', 'IMP1')->firstOrFail()->is_active)->toBeTrue()
        ->and((bool) $active->fresh()->is_active)->toBeFalse()
        ->and(AcademicYear::where('is_active', true)->count())->toBe(1);
});
