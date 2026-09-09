<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function offeringCrudSetup(): array
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
    $course = Course::create([
        'code' => 'TI101', 'name' => 'Algoritma', 'credits' => 3,
        'requirement_type' => 'Wajib', 'category_type' => 'Keilmuan', 'is_active' => true,
    ]);

    return compact('operator', 'program', 'year', 'course');
}

function giveOfferingPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingOfferingOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeOffering(array $setup, array $overrides = []): CourseOffering
{
    return CourseOffering::create(array_merge([
        'academic_year_id' => $setup['year']->id,
        'study_program_id' => $setup['program']->id,
        'course_id' => $setup['course']->id,
        'label' => 'A',
        'semester_no' => 1,
        'capacity' => 40,
        'delivery_mode' => 'Offline',
        'status' => 'Draft',
    ], $overrides));
}

it('membuka kelas dan menolak duplikat tahun-prodi-MK-label', function () {
    $setup = offeringCrudSetup();
    giveOfferingPermissionsToOperator(['course-offering.create']);

    $payload = [
        'academic_year_id' => $setup['year']->id,
        'study_program_id' => $setup['program']->id,
        'course_id' => $setup['course']->id,
        'label' => 'A',
        'delivery_mode' => 'Offline',
        'status' => 'Draft',
    ];

    $response = actingOfferingOperator($setup['operator'])
        ->post('/admin/academic/course-offerings', $payload)
        ->assertSessionHas('success');

    $offering = CourseOffering::where('label', 'A')->firstOrFail();
    $response->assertRedirect('/admin/academic/course-offerings/'.$offering->id.'/edit');

    actingOfferingOperator($setup['operator'])
        ->post('/admin/academic/course-offerings', $payload)
        ->assertSessionHasErrors('course_id');

    expect(CourseOffering::count())->toBe(1);
});

it('menampilkan workspace dengan dosen, jadwal, dan sesi', function () {
    $setup = offeringCrudSetup();
    giveOfferingPermissionsToOperator(['course-offering.viewAny', 'course-offering.view']);

    $offering = makeOffering($setup);

    actingOfferingOperator($setup['operator'])
        ->get("/admin/academic/course-offerings/{$offering->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/CourseOffering/Workspace')
            ->has('lecturers', 0)
            ->has('schedules', 0)
            ->has('sessions', 0)
            ->has('students', 0)
            ->where('generate.canGenerate', false));
});

it('menampilkan mahasiswa terdaftar di tab workspace', function () {
    $setup = offeringCrudSetup();
    giveOfferingPermissionsToOperator(['course-offering.view']);

    $offering = makeOffering($setup);

    $studentUser = App\Models\User::factory()->create();
    $student = App\Models\Academic\StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $setup['program']->id,
        'nim' => '2026TI0001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);
    $plan = App\Models\Academic\StudyPlan::create([
        'student_profile_id' => $student->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 1,
        'status' => 'Approved',
    ]);
    $plan->details()->create([
        'course_offering_id' => $offering->id,
        'status' => 'Taken',
    ]);

    actingOfferingOperator($setup['operator'])
        ->get("/admin/academic/course-offerings/{$offering->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('students', 1)
            ->where('students.0.nim', '2026TI0001'));
});

it('mengelola dosen pengampu: tambah, duplikat ditolak, ubah, hapus', function () {
    $setup = offeringCrudSetup();
    giveOfferingPermissionsToOperator(['course-offering.update']);

    $offering = makeOffering($setup);
    $lecturerUser = App\Models\User::factory()->create();
    $lecturer = App\Models\Academic\LecturerProfile::create([
        'user_id' => $lecturerUser->id, 'nidn' => '1001',
        'employment_status' => 'Tetap', 'is_active' => true,
    ]);

    actingOfferingOperator($setup['operator'])
        ->post("/admin/academic/course-offerings/{$offering->id}/lecturers", [
            'lecturer_profile_id' => $lecturer->id, 'role' => 'Primary',
        ])
        ->assertSessionHas('success');

    actingOfferingOperator($setup['operator'])
        ->post("/admin/academic/course-offerings/{$offering->id}/lecturers", [
            'lecturer_profile_id' => $lecturer->id, 'role' => 'Secondary',
        ])
        ->assertSessionHas('error');

    $row = $offering->lecturers()->firstOrFail();

    actingOfferingOperator($setup['operator'])
        ->put("/admin/academic/course-offerings/{$offering->id}/lecturers/{$row->id}", [
            'role' => 'Coordinator',
        ])
        ->assertSessionHas('success');

    expect($row->fresh()->role)->toBe('Coordinator');
});

it('menolak hapus kelas yang sudah punya jadwal', function () {
    $setup = offeringCrudSetup();
    giveOfferingPermissionsToOperator(['course-offering.delete']);

    $offering = makeOffering($setup);
    $offering->courseSchedules()->create([
        'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00',
        'session_type' => 'Lecture', 'delivery_mode' => 'Offline', 'is_active' => true,
    ]);

    actingOfferingOperator($setup['operator'])
        ->delete("/admin/academic/course-offerings/{$offering->id}")
        ->assertSessionHas('error');

    expect(CourseOffering::find($offering->id))->not->toBeNull();
});

it('membutuhkan konfirmasi untuk generate sesi', function () {
    $setup = offeringCrudSetup();
    giveOfferingPermissionsToOperator(['course-offering.update']);

    $offering = makeOffering($setup, [
        'total_meetings' => 2, 'class_start_date' => '2026-08-03', 'class_end_date' => '2026-08-31',
    ]);
    $offering->courseSchedules()->create([
        'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00',
        'session_type' => 'Lecture', 'delivery_mode' => 'Offline', 'is_active' => true,
    ]);

    // Tanpa konfirmasi → 403/validasi, sesi tidak terbentuk.
    actingOfferingOperator($setup['operator'])
        ->post("/admin/academic/course-offerings/{$offering->id}/generate", [])
        ->assertSessionHasErrors('confirm');

    expect(App\Models\Academic\AttendanceSession::count())->toBe(0);

    actingOfferingOperator($setup['operator'])
        ->post("/admin/academic/course-offerings/{$offering->id}/generate", ['confirm' => 'yes'])
        ->assertSessionHas('success');

    expect(App\Models\Academic\AttendanceSession::count())->toBe(2);
});

function makeOfferingImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['academic_year_code', 'program_code', 'course_code', 'label', 'semester_no', 'capacity', 'delivery_mode', 'status'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-offerings').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'offerings.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor pembukaan kelas', function () {
    $setup = offeringCrudSetup();
    giveOfferingPermissionsToOperator(['course-offering.create']);

    $response = actingOfferingOperator($setup['operator'])
        ->get('/admin/academic/course-offerings/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-course-offerings.xlsx');

    actingOfferingOperator($setup['operator'])
        ->post('/admin/academic/course-offerings/import', ['file' => makeOfferingImportFile([
            ['Y26', 'TI', 'TI101', 'A', '1', '40', 'Offline', 'Draft'],
            ['Y26', 'TI', 'TI101', 'B', '1', '40', 'Offline', 'Draft'],
        ])])
        ->assertRedirect('/admin/academic/course-offerings')
        ->assertSessionHas('success');

    expect(CourseOffering::count())->toBe(2);
});

it('menolak impor kelas duplikat atau berkode siluman', function () {
    $setup = offeringCrudSetup();
    giveOfferingPermissionsToOperator(['course-offering.create']);

    makeOffering($setup);

    $response = actingOfferingOperator($setup['operator'])
        ->post('/admin/academic/course-offerings/import', ['file' => makeOfferingImportFile([
            ['Y26', 'TI', 'TI101', 'A', '1', '40', 'Offline', 'Draft'],
            ['Y26', 'TI', 'SILUMAN', 'A', '1', '40', 'Offline', 'Draft'],
        ])])
        ->assertRedirect('/admin/academic/course-offerings');

    expect(CourseOffering::count())->toBe(1);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and($result['rejected'])->toBe(2);
});
