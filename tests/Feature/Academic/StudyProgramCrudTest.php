<?php

use App\Models\Academic\Curriculum;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function studyProgramCrudSetup(): array
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

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    $deadFaculty = Faculty::create(['name' => 'Fakultas Mati', 'code' => 'FM', 'is_active' => false]);

    return compact('operator', 'faculty', 'deadFaculty');
}

function giveStudyProgramPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingStudyProgramOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar prodi dengan nama fakultas', function () {
    $setup = studyProgramCrudSetup();
    giveStudyProgramPermissionsToOperator(['study-program.viewAny']);

    StudyProgram::create([
        'faculty_id' => $setup['faculty']->id, 'name' => 'Teknik Informatika',
        'code' => 'TI', 'degree' => 'S1', 'is_active' => true,
    ]);

    actingStudyProgramOperator($setup['operator'])
        ->get('/admin/academic/study-programs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/StudyProgram/Index')
            ->where('stats.total', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.faculty', 'Fakultas Teknik')
            ->where('data.rows.0.degree', 'S1'));
});

it('menolak prodi aktif berfakultas nonaktif', function () {
    $setup = studyProgramCrudSetup();
    giveStudyProgramPermissionsToOperator(['study-program.create', 'study-program.update']);

    // Tambah: fakultas nonaktif + aktif=true ditolak.
    actingStudyProgramOperator($setup['operator'])
        ->post('/admin/academic/study-programs', [
            'faculty_id' => $setup['deadFaculty']->id, 'name' => 'Prodi X',
            'code' => 'PX', 'degree' => 'S1', 'is_active' => true,
        ])
        ->assertSessionHas('error');

    expect(StudyProgram::where('code', 'PX')->count())->toBe(0);

    // Tambah nonaktif boleh, lalu aktifkan via update ditolak.
    $program = StudyProgram::create([
        'faculty_id' => $setup['deadFaculty']->id, 'name' => 'Prodi X',
        'code' => 'PX', 'degree' => 'S1', 'is_active' => false,
    ]);

    actingStudyProgramOperator($setup['operator'])
        ->put("/admin/academic/study-programs/{$program->id}", [
            'faculty_id' => $setup['deadFaculty']->id, 'name' => 'Prodi X',
            'code' => 'PX', 'degree' => 'S1', 'is_active' => true,
        ])
        ->assertSessionHas('error');

    // Toggle aktif juga ditolak.
    actingStudyProgramOperator($setup['operator'])
        ->post("/admin/academic/study-programs/{$program->id}/toggle", ['is_active' => true])
        ->assertSessionHas('error');

    expect((bool) $program->fresh()->is_active)->toBeFalse();
});

it('menyimpan dan memperbarui prodi valid', function () {
    $setup = studyProgramCrudSetup();
    giveStudyProgramPermissionsToOperator(['study-program.create', 'study-program.update']);

    actingStudyProgramOperator($setup['operator'])
        ->post('/admin/academic/study-programs', [
            'faculty_id' => $setup['faculty']->id, 'name' => 'Teknik Informatika',
            'code' => 'TI', 'degree' => 'S1', 'prefix_degree' => '', 'is_active' => true,
        ])
        ->assertRedirect('/admin/academic/study-programs')
        ->assertSessionHas('success');

    $program = StudyProgram::where('code', 'TI')->firstOrFail();

    actingStudyProgramOperator($setup['operator'])
        ->put("/admin/academic/study-programs/{$program->id}", [
            'faculty_id' => $setup['faculty']->id, 'name' => 'Informatika',
            'code' => 'TI', 'degree' => 'S1', 'is_active' => true,
        ])
        ->assertRedirect('/admin/academic/study-programs');

    expect($program->fresh()->name)->toBe('Informatika');
});

it('menolak hapus prodi yang terikat kurikulum', function () {
    $setup = studyProgramCrudSetup();
    giveStudyProgramPermissionsToOperator(['study-program.delete']);

    $program = StudyProgram::create([
        'faculty_id' => $setup['faculty']->id, 'name' => 'Teknik Informatika',
        'code' => 'TI', 'degree' => 'S1', 'is_active' => true,
    ]);

    Curriculum::create([
        'study_program_id' => $program->id,
        'name' => 'Kurikulum 2026',
        'code' => 'K26',
        'is_active' => true,
    ]);

    actingStudyProgramOperator($setup['operator'])
        ->delete("/admin/academic/study-programs/{$program->id}")
        ->assertSessionHas('error');

    expect(StudyProgram::find($program->id))->not->toBeNull();
});

function makeStudyProgramImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['faculty_code', 'name', 'code', 'degree', 'short_name', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-prodi').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'prodi.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor prodi by kode fakultas', function () {
    $setup = studyProgramCrudSetup();
    giveStudyProgramPermissionsToOperator(['study-program.create']);

    $response = actingStudyProgramOperator($setup['operator'])
        ->get('/admin/academic/study-programs/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-study-programs.xlsx');

    actingStudyProgramOperator($setup['operator'])
        ->post('/admin/academic/study-programs/import', ['file' => makeStudyProgramImportFile([
            ['FT', 'Teknik Informatika', 'TI', 'S1', '', '1'],
        ])])
        ->assertRedirect('/admin/academic/study-programs')
        ->assertSessionHas('success');

    expect(StudyProgram::where('code', 'TI')->firstOrFail()->faculty_id)->toBe($setup['faculty']->id);
});

it('menolak impor prodi berfakultas tak dikenal atau nonaktif', function () {
    $setup = studyProgramCrudSetup();
    giveStudyProgramPermissionsToOperator(['study-program.create']);

    $response = actingStudyProgramOperator($setup['operator'])
        ->post('/admin/academic/study-programs/import', ['file' => makeStudyProgramImportFile([
            ['FT', 'Valid', 'VD', 'S1', '', '1'],
            ['XX', 'Fakultas Siluman', 'FS', 'S1', '', '1'],
            ['FM', 'Aktif di Mati', 'AM', 'S1', '', '1'],
        ])])
        ->assertRedirect('/admin/academic/study-programs');

    expect(StudyProgram::where('code', 'VD')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and($result['rejected'])->toBe(2);
});
