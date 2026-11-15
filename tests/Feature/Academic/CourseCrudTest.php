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

function courseCrudSetup(): array
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

function giveCoursePermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingCourseOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeCourse(array $overrides = []): Course
{
    return Course::create(array_merge([
        'code' => 'MK'.fake()->unique()->numerify('###'),
        'name' => 'Mata Kuliah Uji',
        'credits' => 3,
        'requirement_type' => 'Wajib',
        'category_type' => 'Keilmuan',
        'is_active' => true,
    ], $overrides));
}

it('menyimpan MK global beserta prasyarat dalam satu transaksi', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.create']);

    $prereq = makeCourse(['code' => 'PRASYARAT1']);

    actingCourseOperator($setup['operator'])
        ->post('/admin/academic/courses', [
            'scope_type' => 'global',
            'code' => 'TI101',
            'name' => 'Algoritma',
            'credits' => 3,
            'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan',
            'is_active' => true,
            'prerequisite_ids' => [$prereq->id],
        ])
        ->assertRedirect('/admin/academic/courses')
        ->assertSessionHas('success');

    $course = Course::where('code', 'TI101')->firstOrFail();

    expect($course->prerequisites->pluck('id')->all())->toBe([$prereq->id])
        ->and($course->latestScope->scope_type)->toBe('global');
});

it('menolak kode duplikat dan scope nonaktif', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.create']);

    makeCourse(['code' => 'TI101']);

    actingCourseOperator($setup['operator'])
        ->post('/admin/academic/courses', [
            'scope_type' => 'global', 'code' => 'TI101', 'name' => 'Duplikat',
            'credits' => 2, 'requirement_type' => 'Wajib', 'category_type' => 'Keilmuan',
        ])
        ->assertSessionHasErrors('code');

    $deadFaculty = Faculty::create(['name' => 'Mati', 'code' => 'FM', 'is_active' => false]);

    actingCourseOperator($setup['operator'])
        ->post('/admin/academic/courses', [
            'scope_type' => 'faculty', 'scope_id' => $deadFaculty->id, 'code' => 'TI102',
            'name' => 'Scope Mati', 'credits' => 2, 'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan', 'is_active' => true,
        ])
        ->assertSessionHas('error');

    expect(Course::where('code', 'TI102')->count())->toBe(0);
});

it('menyimpan MK berscope prodi aktif dan menolak prasyarat diri sendiri', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.create', 'course.update']);

    $faculty = Faculty::create(['name' => 'FT', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'TI', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);

    $course = makeCourse(['code' => 'TI201']);

    actingCourseOperator($setup['operator'])
        ->put("/admin/academic/courses/{$course->id}", [
            'scope_type' => 'study_program', 'scope_id' => $program->id, 'code' => 'TI201',
            'name' => 'Basis Data', 'credits' => 3, 'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan', 'is_active' => true,
            'prerequisite_ids' => [$course->id],
        ])
        ->assertSessionHasErrors('prerequisite_ids');

    actingCourseOperator($setup['operator'])
        ->put("/admin/academic/courses/{$course->id}", [
            'scope_type' => 'study_program', 'scope_id' => $program->id, 'code' => 'TI201',
            'name' => 'Basis Data', 'credits' => 3, 'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan', 'is_active' => true,
            'prerequisite_ids' => [],
        ])
        ->assertRedirect('/admin/academic/courses');

    expect($course->fresh()->latestScope->scope_type)->toBe('study_program');
});

it('menolak hapus MK yang terikat kurikulum atau jadi prasyarat', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.delete']);

    $faculty = Faculty::create(['name' => 'FT', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'TI', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);
    $curriculum = Curriculum::create([
        'study_program_id' => $program->id, 'name' => 'K2026', 'code' => 'K26', 'is_active' => true,
    ]);

    $inCurriculum = makeCourse(['code' => 'KK001']);
    CurriculumCourse::create(['curriculum_id' => $curriculum->id, 'course_id' => $inCurriculum->id]);

    $required = makeCourse(['code' => 'PR001']);
    $dependent = makeCourse(['code' => 'DP001']);
    $dependent->prerequisites()->sync([$required->id]);

    actingCourseOperator($setup['operator'])
        ->delete("/admin/academic/courses/{$inCurriculum->id}")
        ->assertSessionHas('error');

    actingCourseOperator($setup['operator'])
        ->delete("/admin/academic/courses/{$required->id}")
        ->assertSessionHas('error');

    expect(Course::find($inCurriculum->id))->not->toBeNull()
        ->and(Course::find($required->id))->not->toBeNull();
});

it('menghapus, memulihkan beserta scope, dan memfilter MK', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.viewAny', 'course.delete']);

    $faculty = Faculty::create(['name' => 'FT', 'code' => 'FT', 'is_active' => true]);
    $course = makeCourse(['code' => 'TI301']);
    $course->scopes()->create(['scope_type' => 'faculty', 'scope_id' => $faculty->id]);

    actingCourseOperator($setup['operator'])
        ->delete("/admin/academic/courses/{$course->id}")
        ->assertRedirect('/admin/academic/courses');

    actingCourseOperator($setup['operator'])
        ->post("/admin/academic/courses/{$course->id}/restore")
        ->assertSessionHas('success');

    expect($course->fresh()->latestScope->scope_type)->toBe('faculty');

    actingCourseOperator($setup['operator'])
        ->get('/admin/academic/courses?scope_type=faculty&scope_faculty='.$faculty->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.code', 'TI301'));
});

it('menampilkan kolom semester dan memfilternya', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.viewAny']);

    makeCourse(['code' => 'SMT1', 'semester_recommendation' => 1]);
    makeCourse(['code' => 'SMT5', 'semester_recommendation' => 5]);

    actingCourseOperator($setup['operator'])
        ->get('/admin/academic/courses?semester=5')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.code', 'SMT5')
            ->where('data.rows.0.semester', 5));

    makeCourse(['code' => 'SMT10', 'semester_recommendation' => 10]);

    actingCourseOperator($setup['operator'])
        ->get('/admin/academic/courses')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('maxSemester', 10));
});

it('mengekspor hanya baris terpilih', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.viewAny']);

    $first = makeCourse(['code' => 'EXP001']);
    makeCourse(['code' => 'EXP002']);

    $response = actingCourseOperator($setup['operator'])
        ->get("/admin/academic/courses/export?format=csv&ids[]={$first->id}")
        ->assertOk();

    // StreamedResponse tidak menyimpan konten: tangkap output stream.
    ob_start();
    $response->baseResponse->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain('EXP001')
        ->and(str_contains($content, 'EXP002'))->toBeFalse();
});

function makeCourseImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['code', 'name', 'credits', 'scope', 'requirement_type', 'category_type', 'semester', 'prerequisite_codes', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-courses').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'courses.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function makeCourseImportProgram(): StudyProgram
{
    $faculty = Faculty::create(['name' => 'FT', 'code' => 'FT', 'is_active' => true]);

    return StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'TI', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);
}

it('mengunduh template dan mengimpor MK berscope prodi + prasyarat', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.create']);

    makeCourseImportProgram();
    makeCourse(['code' => 'DASAR1']);

    $response = actingCourseOperator($setup['operator'])
        ->get('/admin/academic/courses/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-courses.xlsx');

    actingCourseOperator($setup['operator'])
        ->post('/admin/academic/courses/import', ['file' => makeCourseImportFile([
            ['TI101', 'Algoritma', '3', 'P:TI', 'Wajib', 'Keilmuan', '1', 'DASAR1', '1'],
        ])])
        ->assertRedirect('/admin/academic/courses')
        ->assertSessionHas('success');

    $course = Course::where('code', 'TI101')->firstOrFail();

    expect($course->latestScope->scope_type)->toBe('study_program')
        ->and($course->prerequisites->pluck('code')->all())->toBe(['DASAR1']);
});

it('menolak impor MK berscope siluman atau SKS invalid', function () {
    $setup = courseCrudSetup();
    giveCoursePermissionsToOperator(['course.create']);

    $response = actingCourseOperator($setup['operator'])
        ->post('/admin/academic/courses/import', ['file' => makeCourseImportFile([
            ['OK001', 'Valid', '3', 'global', 'Wajib', 'Keilmuan', '', '', '1'],
            ['BAD001', 'Scope Siluman', '3', 'F:XX', 'Wajib', 'Keilmuan', '', '', '1'],
            ['BAD002', 'SKS Nol', '0', 'global', 'Wajib', 'Keilmuan', '', '', '1'],
        ])])
        ->assertRedirect('/admin/academic/courses');

    expect(Course::where('code', 'OK001')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and($result['rejected'])->toBe(2);
});
