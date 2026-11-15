<?php

use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function scheduleCrudSetup(): array
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

    $faculty = App\Models\Academic\Faculty::create(['name' => 'FT', 'code' => 'FT', 'is_active' => true]);
    $program = App\Models\Academic\StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'TI', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);
    $year = App\Models\Academic\AcademicYear::create([
        'name' => '2026/2027', 'code' => 'Y26', 'semester' => 'Ganjil',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
    ]);
    $course = App\Models\Academic\Course::create([
        'code' => 'TI101', 'name' => 'Algoritma', 'credits' => 3,
        'requirement_type' => 'Wajib', 'category_type' => 'Keilmuan', 'is_active' => true,
    ]);
    $offering = CourseOffering::create([
        'academic_year_id' => $year->id, 'study_program_id' => $program->id,
        'course_id' => $course->id, 'label' => 'A',
        'delivery_mode' => 'Offline', 'status' => 'Open',
    ]);
    $lecturerUser = App\Models\User::factory()->create();
    $lecturer = App\Models\Academic\LecturerProfile::create([
        'user_id' => $lecturerUser->id, 'nidn' => '1001',
        'employment_status' => 'Tetap', 'is_active' => true,
    ]);
    $offering->lecturers()->create([
        'lecturer_profile_id' => $lecturer->id, 'role' => 'Primary', 'is_active' => true,
    ]);

    $building = App\Models\Campus\Building::create(['name' => 'Gedung A', 'code' => 'GA', 'is_active' => true]);
    $room = App\Models\Campus\Room::create([
        'building_id' => $building->id, 'name' => 'R101', 'code' => 'R101', 'is_active' => true,
    ]);

    return compact('operator', 'offering', 'lecturer', 'room');
}

function giveSchedulePermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingScheduleOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function schedulePayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'course_offering_id' => $setup['offering']->id,
        'lecturer_profile_id' => $setup['lecturer']->id,
        'room_id' => $setup['room']->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'session_type' => 'Lecture',
        'delivery_mode' => 'Offline',
        'is_active' => true,
    ], $overrides);
}

it('menyimpan jadwal dan menolak dosen luar kelas', function () {
    $setup = scheduleCrudSetup();
    giveSchedulePermissionsToOperator(['course-schedule.create']);

    actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules', schedulePayload($setup))
        ->assertRedirect('/admin/academic/course-schedules')
        ->assertSessionHas('success');

    $outsiderUser = App\Models\User::factory()->create();
    $outsider = App\Models\Academic\LecturerProfile::create([
        'user_id' => $outsiderUser->id, 'nidn' => '9999',
        'employment_status' => 'Tetap', 'is_active' => true,
    ]);

    actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules', schedulePayload($setup, [
            'lecturer_profile_id' => $outsider->id,
            'day_of_week' => 'Tuesday',
        ]))
        ->assertSessionHasErrors('lecturer_profile_id');

    expect(CourseSchedule::count())->toBe(1);
});

it('menolak jadwal yang bentrok ruang dan dosen', function () {
    $setup = scheduleCrudSetup();
    giveSchedulePermissionsToOperator(['course-schedule.create']);

    actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules', schedulePayload($setup))
        ->assertSessionHas('success');

    // Ruang sama, jam irisan → tolak.
    actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules', schedulePayload($setup, [
            'start_time' => '09:00', 'end_time' => '11:00',
        ]))
        ->assertSessionHas('error');

    // Dosen sama ruang beda, jam irisan → tolak.
    $building = App\Models\Campus\Building::create(['name' => 'Gedung B', 'code' => 'GB', 'is_active' => true]);
    $other = App\Models\Campus\Room::create([
        'building_id' => $building->id, 'name' => 'R201', 'code' => 'R201', 'is_active' => true,
    ]);

    actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules', schedulePayload($setup, [
            'room_id' => $other->id, 'start_time' => '09:30', 'end_time' => '11:30',
        ]))
        ->assertSessionHas('error');

    // Menempel (10:00–12:00) boleh.
    actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules', schedulePayload($setup, [
            'start_time' => '10:00', 'end_time' => '12:00',
        ]))
        ->assertSessionHas('success');

    expect(CourseSchedule::count())->toBe(2);
});

it('menampilkan detail jadwal dan workspace sesi absensi', function () {
    $setup = scheduleCrudSetup();
    giveSchedulePermissionsToOperator(['course-schedule.viewAny', 'course-schedule.view', 'course-offering.view']);

    $schedule = CourseSchedule::create(array_merge(
        schedulePayload($setup),
        ['created_by' => $setup['operator']->id]
    ));

    $scheduleResponse = actingScheduleOperator($setup['operator'])
        ->get("/admin/academic/course-schedules/{$schedule->id}");

    $scheduleResponse
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/CourseSchedule/Show')
            ->where('schedule.day', 'Monday'));

    $session = AttendanceSession::create([
        'course_offering_id' => $setup['offering']->id,
        'meeting_no' => 1,
        'meeting_date' => '2026-08-03',
        'status' => 'Draft',
    ]);

    $sessionResponse = actingScheduleOperator($setup['operator'])
        ->get("/admin/academic/course-offerings/{$setup['offering']->id}/attendance-sessions/{$session->id}");

    $sessionResponse
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/AttendanceSession/Show')
            ->where('session.meetingNo', 1)
            ->has('roster', 0));
});

it('memperbarui sesi dan menyimpan roster kehadiran', function () {
    $setup = scheduleCrudSetup();
    giveSchedulePermissionsToOperator(['course-offering.update']);

    $session = AttendanceSession::create([
        'course_offering_id' => $setup['offering']->id,
        'meeting_no' => 1,
        'meeting_date' => '2026-08-03',
        'status' => 'Draft',
    ]);

    actingScheduleOperator($setup['operator'])
        ->put("/admin/academic/course-offerings/{$setup['offering']->id}/attendance-sessions/{$session->id}", [
            'meeting_date' => '2026-08-04',
            'status' => 'Opened',
            'topic' => 'Kontrak kuliah',
        ])
        ->assertSessionHas('success');

    expect($session->fresh()->topic)->toBe('Kontrak kuliah');

    $studentUser = App\Models\User::factory()->create();
    $student = App\Models\Academic\StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $setup['offering']->study_program_id,
        'nim' => '2026TI0001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);
    $plan = App\Models\Academic\StudyPlan::create([
        'student_profile_id' => $student->id,
        'academic_year_id' => $setup['offering']->academic_year_id,
        'semester_no' => 1,
        'status' => 'Approved',
    ]);
    $plan->details()->create([
        'course_offering_id' => $setup['offering']->id,
        'status' => 'Taken',
    ]);

    actingScheduleOperator($setup['operator'])
        ->post("/admin/academic/course-offerings/{$setup['offering']->id}/attendance-sessions/{$session->id}/records", [
            'records' => [
                ['student_profile_id' => $student->id, 'status' => 'Present'],
            ],
        ])
        ->assertSessionHas('success');

    expect(App\Models\Academic\AttendanceRecord::where('attendance_session_id', $session->id)->count())->toBe(1);
});

function makeScheduleImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['offering_id', 'day_of_week', 'start_time', 'end_time', 'room_code', 'lecturer_nidn', 'session_type', 'delivery_mode', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-schedules').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'schedules.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor jadwal', function () {
    $setup = scheduleCrudSetup();
    giveSchedulePermissionsToOperator(['course-schedule.create']);

    $response = actingScheduleOperator($setup['operator'])
        ->get('/admin/academic/course-schedules/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-course-schedules.xlsx');

    actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules/import', ['file' => makeScheduleImportFile([
            [$setup['offering']->id, 'Monday', '08:00', '10:00', 'R101', '1001', 'Lecture', 'Offline', '1'],
        ])])
        ->assertRedirect('/admin/academic/course-schedules')
        ->assertSessionHas('success');

    expect(CourseSchedule::count())->toBe(1);
});

it('menolak impor jadwal yang bentrok ke database maupun antar-baris', function () {
    $setup = scheduleCrudSetup();
    giveSchedulePermissionsToOperator(['course-schedule.create']);

    CourseSchedule::create(array_merge(
        schedulePayload($setup),
        ['created_by' => $setup['operator']->id]
    ));

    // Bentrok ke database.
    $response = actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules/import', ['file' => makeScheduleImportFile([
            [$setup['offering']->id, 'Monday', '09:00', '11:00', 'R101', '', 'Lecture', 'Offline', '1'],
        ])])
        ->assertRedirect('/admin/academic/course-schedules');

    expect(CourseSchedule::count())->toBe(1);
    expect($response->getSession()->get('import_result')['success'])->toBeFalse();

    // Bentrok antar-baris dalam file yang sama.
    $response = actingScheduleOperator($setup['operator'])
        ->post('/admin/academic/course-schedules/import', ['file' => makeScheduleImportFile([
            [$setup['offering']->id, 'Tuesday', '08:00', '10:00', 'R101', '', 'Lecture', 'Offline', '1'],
            [$setup['offering']->id, 'Tuesday', '09:00', '11:00', 'R101', '', 'Lecture', 'Offline', '1'],
        ])])
        ->assertRedirect('/admin/academic/course-schedules');

    expect(CourseSchedule::count())->toBe(1);
    expect($response->getSession()->get('import_result')['rejected'])->toBe(1);
});
