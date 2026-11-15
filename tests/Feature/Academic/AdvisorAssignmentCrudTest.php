<?php

use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function advisorCrudSetup(): array
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
    $year = AcademicYear::create([
        'name' => '2026/2027', 'code' => 'Y26', 'semester' => 'Ganjil',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
    ]);

    return compact('operator', 'program', 'year');
}

function makeAdvisorStudent(array $setup, string $nim): StudentProfile
{
    $user = App\Models\User::factory()->create();

    return StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $setup['program']->id,
        'nim' => $nim,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);
}

function makeAdvisorLecturer(string $nidn): LecturerProfile
{
    $user = App\Models\User::factory()->create();

    return LecturerProfile::create([
        'user_id' => $user->id,
        'nidn' => $nidn,
        'employment_status' => 'Tetap',
        'is_active' => true,
    ]);
}

function giveAdvisorPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAdvisorOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar penugasan dengan relasi', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.viewAny']);

    $student = makeAdvisorStudent($setup, '2026TI0001');
    $lecturer = makeAdvisorLecturer('1001');

    AcademicAdvisorAssignment::create([
        'student_profile_id' => $student->id,
        'lecturer_profile_id' => $lecturer->id,
        'academic_year_id' => $setup['year']->id,
        'is_active' => true,
    ]);

    actingAdvisorOperator($setup['operator'])
        ->get('/admin/academic/academic-advisor-assignments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/AdvisorAssignment/Index')
            ->where('stats.total', 1)
            ->where('stats.students', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.nim', '2026TI0001'));
});

it('membuat penugasan tunggal dan menolak konflik aktif', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.create']);

    $student = makeAdvisorStudent($setup, '2026TI0001');
    $first = makeAdvisorLecturer('1001');
    $second = makeAdvisorLecturer('1002');

    actingAdvisorOperator($setup['operator'])
        ->post('/admin/academic/academic-advisor-assignments', [
            'assign_mode' => 'single',
            'lecturer_profile_id' => $first->id,
            'student_profile_id' => $student->id,
            'academic_year_id' => $setup['year']->id,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/academic/academic-advisor-assignments')
        ->assertSessionHas('success');

    // Dosen kedua untuk mahasiswa + tahun sama → konflik.
    actingAdvisorOperator($setup['operator'])
        ->post('/admin/academic/academic-advisor-assignments', [
            'assign_mode' => 'single',
            'lecturer_profile_id' => $second->id,
            'student_profile_id' => $student->id,
            'academic_year_id' => $setup['year']->id,
            'is_active' => true,
        ])
        ->assertSessionHas('error');

    expect(AcademicAdvisorAssignment::count())->toBe(1);
});

it('tidak memvalidasi checklist massal saat mode tunggal', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.create']);

    $student = makeAdvisorStudent($setup, '2026TI0001');
    $lecturer = makeAdvisorLecturer('1001');

    // Regresi: submit tunggal membawa selected_student_ids kosong —
    // tidak boleh memicu error min:1 milik mode massal.
    actingAdvisorOperator($setup['operator'])
        ->post('/admin/academic/academic-advisor-assignments', [
            'assign_mode' => 'single',
            'lecturer_profile_id' => $lecturer->id,
            'student_profile_id' => $student->id,
            'selected_student_ids' => [],
            'is_active' => true,
        ])
        ->assertRedirect('/admin/academic/academic-advisor-assignments')
        ->assertSessionHas('success');

    expect(AcademicAdvisorAssignment::count())->toBe(1);
});

it('melakukan bulk assign dengan laporan dilewati', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.create']);

    $free = makeAdvisorStudent($setup, '2026TI0001');
    $taken = makeAdvisorStudent($setup, '2026TI0002');
    $lecturer = makeAdvisorLecturer('1001');
    $other = makeAdvisorLecturer('1002');

    AcademicAdvisorAssignment::create([
        'student_profile_id' => $taken->id,
        'lecturer_profile_id' => $other->id,
        'academic_year_id' => $setup['year']->id,
        'is_active' => true,
    ]);

    actingAdvisorOperator($setup['operator'])
        ->post('/admin/academic/academic-advisor-assignments', [
            'assign_mode' => 'bulk',
            'lecturer_profile_id' => $lecturer->id,
            'selected_student_ids' => [$free->id, $taken->id],
            'academic_year_id' => $setup['year']->id,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/academic/academic-advisor-assignments')
        ->assertSessionHas('warning');

    expect(AcademicAdvisorAssignment::where('lecturer_profile_id', $lecturer->id)->count())->toBe(1);
});

it('mentransfer bimbingan ke dosen lain sekaligus', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.update']);

    $old = makeAdvisorLecturer('1001');
    $new = makeAdvisorLecturer('1002');
    $student = makeAdvisorStudent($setup, '2026TI0001');

    $assignment = AcademicAdvisorAssignment::create([
        'student_profile_id' => $student->id,
        'lecturer_profile_id' => $old->id,
        'academic_year_id' => $setup['year']->id,
        'is_active' => true,
    ]);

    actingAdvisorOperator($setup['operator'])
        ->post('/admin/academic/academic-advisor-assignments/transfer', [
            'ids' => [$assignment->id],
            'lecturer_profile_id' => $new->id,
        ])
        ->assertSessionHas('success');

    expect($assignment->fresh()->lecturer_profile_id)->toBe($new->id);
});

it('mencari mahasiswa dan dosen via endpoint async', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.viewAny']);

    makeAdvisorStudent($setup, '2026TI0001');
    makeAdvisorLecturer('1001');

    actingAdvisorOperator($setup['operator'])
        ->get('/admin/academic/academic-advisor-assignments/search-students?q=2026TI')
        ->assertOk()
        ->assertJsonCount(1, 'options');

    actingAdvisorOperator($setup['operator'])
        ->get('/admin/academic/academic-advisor-assignments/search-lecturers?q=1001')
        ->assertOk()
        ->assertJsonCount(1, 'options');
});

it('menampilkan detail penugasan', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.viewAny', 'academic-advisor-assignment.view']);

    $student = makeAdvisorStudent($setup, '2026TI0001');
    $lecturer = makeAdvisorLecturer('1001');

    $assignment = AcademicAdvisorAssignment::create([
        'student_profile_id' => $student->id,
        'lecturer_profile_id' => $lecturer->id,
        'academic_year_id' => $setup['year']->id,
        'is_active' => true,
    ]);

    actingAdvisorOperator($setup['operator'])
        ->get("/admin/academic/academic-advisor-assignments/{$assignment->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/AdvisorAssignment/Show')
            ->where('assignment.nim', '2026TI0001'));
});

function makeAdvisorImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['nim', 'lecturer_nidn', 'academic_year_code', 'start_date', 'end_date', 'is_active', 'notes'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-advisor').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'advisor.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor penugasan by NIM-NIDN', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.create']);

    makeAdvisorStudent($setup, '2026TI0001');
    makeAdvisorLecturer('1001');

    $response = actingAdvisorOperator($setup['operator'])
        ->get('/admin/academic/academic-advisor-assignments/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-advisor-assignments.xlsx');

    actingAdvisorOperator($setup['operator'])
        ->post('/admin/academic/academic-advisor-assignments/import', ['file' => makeAdvisorImportFile([
            ['2026TI0001', '1001', $setup['year']->code, '2026-08-01', '2027-01-31', '1', ''],
        ])])
        ->assertRedirect('/admin/academic/academic-advisor-assignments')
        ->assertSessionHas('success');

    expect(AcademicAdvisorAssignment::count())->toBe(1);
});

it('menolak impor penugasan yang bentrok atau tak dikenal', function () {
    $setup = advisorCrudSetup();
    giveAdvisorPermissionsToOperator(['academic-advisor-assignment.create']);

    $student = makeAdvisorStudent($setup, '2026TI0001');
    $lecturer = makeAdvisorLecturer('1001');

    AcademicAdvisorAssignment::create([
        'student_profile_id' => $student->id,
        'lecturer_profile_id' => $lecturer->id,
        'academic_year_id' => $setup['year']->id,
        'is_active' => true,
    ]);

    $response = actingAdvisorOperator($setup['operator'])
        ->post('/admin/academic/academic-advisor-assignments/import', ['file' => makeAdvisorImportFile([
            ['2026TI0001', '1001', $setup['year']->code, '', '', '1', ''],
            ['NIMSILUMAN', '1001', '', '', '', '1', ''],
        ])])
        ->assertRedirect('/admin/academic/academic-advisor-assignments');

    expect(AcademicAdvisorAssignment::count())->toBe(1);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and($result['rejected'])->toBe(2);
});
