<?php

use App\Models\Academic\Course;
use App\Models\Academic\Curriculum;
use App\Models\Academic\CurriculumCourse;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function curriculumCrudSetup(): array
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

    $faculty = Faculty::create(['name' => 'FT', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'TI', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);

    return compact('operator', 'program');
}

function giveCurriculumPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingCurriculumOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeCurriculum($program, array $overrides = []): Curriculum
{
    return Curriculum::create(array_merge([
        'study_program_id' => $program->id,
        'name' => 'Kurikulum 2026',
        'code' => 'K26'.fake()->unique()->numerify('##'),
        'is_active' => true,
    ], $overrides));
}

function makeCurriculumCourse(array $overrides = []): Course
{
    return Course::create(array_merge([
        'code' => 'MK'.fake()->unique()->numerify('###'),
        'name' => 'MK Uji',
        'credits' => 3,
        'requirement_type' => 'Wajib',
        'category_type' => 'Keilmuan',
        'is_active' => true,
    ], $overrides));
}

it('membuat kurikulum lalu redirect ke edit untuk susun MK', function () {
    $setup = curriculumCrudSetup();
    giveCurriculumPermissionsToOperator(['curriculum.create']);

    $response = actingCurriculumOperator($setup['operator'])
        ->post('/admin/academic/curriculums', [
            'study_program_id' => $setup['program']->id,
            'name' => 'Kurikulum 2026',
            'code' => 'K26',
            'is_active' => true,
        ])
        ->assertSessionHas('success');

    $curriculum = Curriculum::where('code', 'K26')->firstOrFail();
    $response->assertRedirect('/admin/academic/curriculums/'.$curriculum->id.'/edit');
});

it('menolak nama duplikat dalam prodi yang sama', function () {
    $setup = curriculumCrudSetup();
    giveCurriculumPermissionsToOperator(['curriculum.create']);

    makeCurriculum($setup['program'], ['name' => 'Sama', 'code' => 'A1']);

    actingCurriculumOperator($setup['operator'])
        ->post('/admin/academic/curriculums', [
            'study_program_id' => $setup['program']->id,
            'name' => 'Sama',
        ])
        ->assertSessionHasErrors('name');
});

it('menduplikat kurikulum beserta seluruh baris MK', function () {
    $setup = curriculumCrudSetup();
    giveCurriculumPermissionsToOperator(['curriculum.create']);

    $curriculum = makeCurriculum($setup['program'], ['name' => 'K2024', 'code' => 'K24']);
    $course = makeCurriculumCourse(['code' => 'TI101']);
    $curriculum->curriculumCourses()->create([
        'course_id' => $course->id, 'semester_no' => 1,
        'is_required' => true, 'sort_order' => 0, 'is_active' => true,
    ]);

    actingCurriculumOperator($setup['operator'])
        ->post("/admin/academic/curriculums/{$curriculum->id}/duplicate", [
            'name' => 'K2025', 'code' => 'K25', 'start_year' => 2025,
        ])
        ->assertSessionHas('success');

    $copy = Curriculum::where('code', 'K25')->firstOrFail();

    expect($copy->is_active)->toBeFalse()
        ->and($copy->curriculumCourses()->count())->toBe(1)
        ->and($copy->curriculumCourses()->first()->course_id)->toBe($course->id);
});

it('mengelola baris MK: tambah, ubah, hapus', function () {
    $setup = curriculumCrudSetup();
    giveCurriculumPermissionsToOperator(['curriculum.update']);

    $curriculum = makeCurriculum($setup['program']);
    $course = makeCurriculumCourse(['code' => 'TI101']);

    actingCurriculumOperator($setup['operator'])
        ->post("/admin/academic/curriculums/{$curriculum->id}/courses", [
            'course_id' => $course->id, 'semester_no' => 1, 'is_required' => true,
        ])
        ->assertSessionHas('success');

    // Duplikat MK sama ditolak.
    actingCurriculumOperator($setup['operator'])
        ->post("/admin/academic/curriculums/{$curriculum->id}/courses", [
            'course_id' => $course->id, 'semester_no' => 2,
        ])
        ->assertSessionHasErrors('course_id');

    $row = CurriculumCourse::where('curriculum_id', $curriculum->id)->firstOrFail();

    actingCurriculumOperator($setup['operator'])
        ->put("/admin/academic/curriculums/{$curriculum->id}/courses/{$row->id}", [
            'course_id' => $course->id, 'semester_no' => 2,
            'credits_override' => 4, 'is_active' => true,
        ])
        ->assertSessionHas('success');

    expect($row->fresh()->semester_no)->toBe(2)
        ->and($row->fresh()->credits_override)->toBe(4);

    actingCurriculumOperator($setup['operator'])
        ->delete("/admin/academic/curriculums/{$curriculum->id}/courses/{$row->id}")
        ->assertSessionHas('success');

    expect(CurriculumCourse::find($row->id))->toBeNull();
});

it('menolak hapus kurikulum yang masih punya MK termasuk via bulk', function () {
    $setup = curriculumCrudSetup();
    giveCurriculumPermissionsToOperator(['curriculum.delete']);

    $curriculum = makeCurriculum($setup['program']);
    $curriculum->curriculumCourses()->create([
        'course_id' => makeCurriculumCourse()->id, 'semester_no' => 1, 'is_active' => true,
    ]);

    actingCurriculumOperator($setup['operator'])
        ->delete("/admin/academic/curriculums/{$curriculum->id}")
        ->assertSessionHas('error');

    actingCurriculumOperator($setup['operator'])
        ->post('/admin/academic/curriculums/bulk-destroy', ['ids' => [$curriculum->id]])
        ->assertSessionHas('error');

    expect(Curriculum::find($curriculum->id))->not->toBeNull();
});

it('menampilkan detail semester dengan total SKS', function () {
    $setup = curriculumCrudSetup();
    giveCurriculumPermissionsToOperator(['curriculum.viewAny', 'curriculum.view']);

    $curriculum = makeCurriculum($setup['program']);
    $curriculum->curriculumCourses()->create([
        'course_id' => makeCurriculumCourse(['code' => 'TI101', 'credits' => 3])->id,
        'semester_no' => 1, 'is_required' => true, 'is_active' => true,
    ]);

    actingCurriculumOperator($setup['operator'])
        ->get("/admin/academic/curriculums/{$curriculum->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/Curriculum/Show')
            ->has('groups', 1)
            ->where('groups.0.semester', 'Semester 1')
            ->where('groups.0.sks', 3));
});

function makeCurriculumImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['program_code', 'name', 'code', 'start_year', 'end_year', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-curriculums').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'curriculums.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor header kurikulum', function () {
    $setup = curriculumCrudSetup();
    giveCurriculumPermissionsToOperator(['curriculum.create']);

    $response = actingCurriculumOperator($setup['operator'])
        ->get('/admin/academic/curriculums/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-curriculums.xlsx');

    actingCurriculumOperator($setup['operator'])
        ->post('/admin/academic/curriculums/import', ['file' => makeCurriculumImportFile([
            ['TI', 'Kurikulum 2024', 'K24', '2024', '2028', '1'],
        ])])
        ->assertRedirect('/admin/academic/curriculums')
        ->assertSessionHas('success');

    expect(Curriculum::where('code', 'K24')->firstOrFail()->study_program_id)->toBe($setup['program']->id);
});

it('menolak impor kurikulum berprodi siluman atau duplikat', function () {
    $setup = curriculumCrudSetup();
    giveCurriculumPermissionsToOperator(['curriculum.create']);

    makeCurriculum($setup['program'], ['name' => 'Lama', 'code' => 'KL']);

    $response = actingCurriculumOperator($setup['operator'])
        ->post('/admin/academic/curriculums/import', ['file' => makeCurriculumImportFile([
            ['TI', 'Baru', 'KB', '2024', '', '1'],
            ['XX', 'Prodi Siluman', 'KS', '', '', '1'],
            ['TI', 'Lama', 'KL2', '', '', '1'],
        ])])
        ->assertRedirect('/admin/academic/curriculums');

    expect(Curriculum::where('code', 'KB')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and($result['rejected'])->toBe(2);
});
