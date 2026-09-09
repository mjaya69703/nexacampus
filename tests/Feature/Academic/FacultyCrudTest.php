<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function facultyCrudSetup(): array
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

function giveFacultyPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingFacultyOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar fakultas dengan hitungan prodi', function () {
    $setup = facultyCrudSetup();
    giveFacultyPermissionsToOperator(['faculty.viewAny']);

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'Teknik Informatika',
        'code' => 'TI', 'degree' => 'S1', 'is_active' => true,
    ]);

    actingFacultyOperator($setup['operator'])
        ->get('/admin/academic/faculties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/Faculty/Index')
            ->where('stats.total', 1)
            ->where('stats.programs', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.programCount', 1)
            ->where('data.rows.0.code', 'FT'));
});

it('menyimpan fakultas dan menolak kode duplikat', function () {
    $setup = facultyCrudSetup();
    giveFacultyPermissionsToOperator(['faculty.create']);

    actingFacultyOperator($setup['operator'])
        ->post('/admin/academic/faculties', [
            'name' => 'Fakultas Teknik', 'code' => 'FT',
            'short_name' => 'FTEK', 'is_active' => true,
        ])
        ->assertRedirect('/admin/academic/faculties')
        ->assertSessionHas('success');

    expect(Faculty::where('code', 'FT')->firstOrFail()->name)->toBe('Fakultas Teknik');

    actingFacultyOperator($setup['operator'])
        ->post('/admin/academic/faculties', ['name' => 'Lain', 'code' => 'FT'])
        ->assertSessionHasErrors('code');
});

it('menonaktifkan fakultas ikut menonaktifkan prodinya', function () {
    $setup = facultyCrudSetup();
    giveFacultyPermissionsToOperator(['faculty.update']);

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'Teknik Informatika',
        'code' => 'TI', 'degree' => 'S1', 'is_active' => true,
    ]);

    actingFacultyOperator($setup['operator'])
        ->post("/admin/academic/faculties/{$faculty->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect((bool) $program->fresh()->is_active)->toBeFalse();

    // Update dengan is_active=false juga cascade.
    $program->update(['is_active' => true]);

    actingFacultyOperator($setup['operator'])
        ->put("/admin/academic/faculties/{$faculty->id}", [
            'name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => false,
        ])
        ->assertRedirect('/admin/academic/faculties');

    expect((bool) $program->fresh()->is_active)->toBeFalse();
});

it('menolak hapus fakultas yang masih memiliki prodi', function () {
    $setup = facultyCrudSetup();
    giveFacultyPermissionsToOperator(['faculty.delete']);

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'Teknik Informatika',
        'code' => 'TI', 'degree' => 'S1', 'is_active' => true,
    ]);

    actingFacultyOperator($setup['operator'])
        ->delete("/admin/academic/faculties/{$faculty->id}")
        ->assertSessionHas('error');

    expect(Faculty::find($faculty->id))->not->toBeNull();

    // Bulk ikut menolak.
    actingFacultyOperator($setup['operator'])
        ->post('/admin/academic/faculties/bulk-destroy', ['ids' => [$faculty->id]])
        ->assertSessionHas('error');

    expect(Faculty::find($faculty->id))->not->toBeNull();
});

it('mengelola sampah fakultas dan mengekspornya', function () {
    $setup = facultyCrudSetup();
    giveFacultyPermissionsToOperator(['faculty.viewAny', 'faculty.delete']);

    $faculty = Faculty::create(['name' => 'Sementara', 'code' => 'SM', 'is_active' => true]);

    actingFacultyOperator($setup['operator'])
        ->delete("/admin/academic/faculties/{$faculty->id}")
        ->assertRedirect('/admin/academic/faculties');

    actingFacultyOperator($setup['operator'])
        ->get('/admin/academic/faculties?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingFacultyOperator($setup['operator'])
        ->post("/admin/academic/faculties/{$faculty->id}/restore")
        ->assertSessionHas('success');

    expect(Faculty::find($faculty->id))->not->toBeNull();

    $response = actingFacultyOperator($setup['operator'])
        ->get('/admin/academic/faculties/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = facultyCrudSetup();
    giveFacultyPermissionsToOperator(['faculty.viewAny']);

    Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);

    $response = actingFacultyOperator($setup['operator'])
        ->get('/admin/academic/faculties/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeFacultyImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['name', 'code', 'short_name', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-faculties').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'faculties.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor fakultas valid', function () {
    $setup = facultyCrudSetup();
    giveFacultyPermissionsToOperator(['faculty.create']);

    $response = actingFacultyOperator($setup['operator'])
        ->get('/admin/academic/faculties/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-faculties.xlsx');

    actingFacultyOperator($setup['operator'])
        ->post('/admin/academic/faculties/import', ['file' => makeFacultyImportFile([
            ['Fakultas Teknik', 'FT', 'FTEK', '1'],
            ['Fakultas Ekonomi', 'FE', '', '0'],
        ])])
        ->assertRedirect('/admin/academic/faculties')
        ->assertSessionHas('success');

    expect(Faculty::where('code', 'FT')->firstOrFail()->short_name)->toBe('FTEK')
        ->and((bool) Faculty::where('code', 'FE')->firstOrFail()->is_active)->toBeFalse();
});

it('menolak seluruh file fakultas bila ada baris tidak valid', function () {
    $setup = facultyCrudSetup();
    giveFacultyPermissionsToOperator(['faculty.create']);

    Faculty::create(['name' => 'Lama', 'code' => 'FT', 'is_active' => true]);

    $response = actingFacultyOperator($setup['operator'])
        ->post('/admin/academic/faculties/import', ['file' => makeFacultyImportFile([
            ['Baru', 'FB', '', ''],
            ['Duplikat', 'FT', '', ''],
        ])])
        ->assertRedirect('/admin/academic/faculties');

    expect(Faculty::where('code', 'FB')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
