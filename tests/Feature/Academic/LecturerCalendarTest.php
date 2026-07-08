<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Campus\Building;
use App\Models\Campus\Room;
use App\Models\Settings\Campus;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function ensureLecturerCalendarInstalled(): void
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

    Campus::query()->updateOrCreate(['id' => 1], [
        'name' => 'NexaCampus',
        'phone' => '021',
        'whatsapp' => '021',
        'email_info' => 'info@example.test',
        'email_humas' => 'humas@example.test',
        'domain' => 'example.test',
    ]);
}

function createLecturerCalendarContext(): array
{
    ensureLecturerCalendarInstalled();

    $role = Role::findOrCreate('lecturer', 'web');
    $user = \App\Models\User::factory()->create(['first_name' => 'Dina', 'last_name' => 'Dosen']);
    $user->assignRole($role);

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT-CAL', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Informatika', 'code' => 'IF-CAL', 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027 Ganjil', 'code' => '2026-CAL', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $course = Course::create(['code' => 'IF301', 'name' => 'Rekayasa Perangkat Lunak', 'credits' => 3, 'is_active' => true]);
    $offering = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $course->id, 'label' => 'Reguler A', 'code' => 'IF301-A', 'semester_no' => 5, 'credits' => 3, 'delivery_mode' => 'Hybrid', 'status' => 'Open']);
    $lecturerProfile = LecturerProfile::create(['user_id' => $user->id, 'faculty_id' => $faculty->id, 'study_program_id' => $program->id, 'nidn' => 'CAL-001', 'is_active' => true]);
    $building = Building::create(['name' => 'Gedung Utama', 'code' => 'GDU-CAL', 'is_active' => true]);
    $room = Room::create(['building_id' => $building->id, 'name' => 'Ruang 301', 'code' => 'R301-CAL', 'type' => 'Classroom', 'is_active' => true]);

    CourseOfferingLecturer::create(['course_offering_id' => $offering->id, 'lecturer_profile_id' => $lecturerProfile->id, 'role' => 'Primary', 'is_active' => true]);
    CourseSchedule::create([
        'course_offering_id' => $offering->id,
        'lecturer_profile_id' => $lecturerProfile->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:40:00',
        'session_type' => 'Lecture',
        'delivery_mode' => 'Hybrid',
        'meeting_link' => 'https://meet.example.test/rpl',
        'is_active' => true,
    ]);

    return compact('user');
}

it('renders lecturer teaching calendar with assigned schedules', function () {
    ['user' => $user] = createLecturerCalendarContext();

    $this->actingAs($user)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.calendar.index'))
        ->assertOk()
        ->assertSee('Kalender Mengajar')
        ->assertSee('Rekayasa Perangkat Lunak')
        ->assertSee('Ruang 301')
        ->assertSee('08:00 - 09:40');
});
