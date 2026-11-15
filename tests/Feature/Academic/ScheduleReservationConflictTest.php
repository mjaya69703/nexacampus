<?php

use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseSchedule;
use App\Models\Campus\RoomReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function reservationConflictSetup(): array
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
    $building = App\Models\Campus\Building::create(['name' => 'Gedung A', 'code' => 'GA', 'is_active' => true]);
    $room = App\Models\Campus\Room::create([
        'building_id' => $building->id, 'name' => 'R101', 'code' => 'R101', 'is_active' => true,
    ]);

    return compact('operator', 'offering', 'room');
}

function giveReservationConflictPermissions(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingReservationConflictOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menolak jadwal kuliah yang menimpa reservasi approved', function () {
    $setup = reservationConflictSetup();
    giveReservationConflictPermissions(['course-schedule.create']);

    $monday = now()->next('Monday')->format('Y-m-d');

    RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Seminar',
        'reservation_date' => $monday, 'start_time' => '09:00', 'end_time' => '11:00',
        'status' => 'approved',
    ]);

    actingReservationConflictOperator($setup['operator'])
        ->post('/admin/academic/course-schedules', [
            'course_offering_id' => $setup['offering']->id,
            'room_id' => $setup['room']->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'session_type' => 'Lecture',
            'delivery_mode' => 'Offline',
            'is_active' => true,
        ])
        ->assertSessionHas('error');

    expect(CourseSchedule::count())->toBe(0);

    // Jam tidak irisan tetap lolos.
    actingReservationConflictOperator($setup['operator'])
        ->post('/admin/academic/course-schedules', [
            'course_offering_id' => $setup['offering']->id,
            'room_id' => $setup['room']->id,
            'day_of_week' => 'Monday',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'session_type' => 'Lecture',
            'delivery_mode' => 'Offline',
            'is_active' => true,
        ])
        ->assertSessionHas('success');

    expect(CourseSchedule::count())->toBe(1);
});
