<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function lecturerPortalSmokeSetup(): User
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
    Role::firstOrCreate(['name' => 'lecturer', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('lecturer');

    $facultyId = DB::table('faculties')->insertGetId([
        'name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true,
    ]);
    $programId = DB::table('study_programs')->insertGetId([
        'faculty_id' => $facultyId, 'name' => 'Teknik Informatika', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);
    DB::table('academic_years')->insert([
        'name' => '2026/2027', 'code' => '2026', 'semester' => 'Ganjil',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'is_active' => true,
    ]);
    $yearId = DB::table('academic_years')->where('code', '2026')->value('id');
    $lecturerId = DB::table('lecturer_profiles')->insertGetId([
        'user_id' => $user->id, 'faculty_id' => $facultyId,
        'study_program_id' => $programId, 'nidn' => '0404040404',
    ]);

    $student = User::factory()->create();
    $studentId = DB::table('student_profiles')->insertGetId([
        'user_id' => $student->id, 'study_program_id' => $programId,
        'nim' => '2026TI0001', 'academic_status' => 'Aktif', 'is_active' => true,
    ]);
    DB::table('academic_advisor_assignments')->insert([
        'student_profile_id' => $studentId,
        'lecturer_profile_id' => $lecturerId,
        'academic_year_id' => $yearId,
        'start_date' => now()->subMonth()->toDateString(),
        'is_active' => true,
    ]);

    return $user;
}

it('merender seluruh halaman portal dosen', function (string $routeName) {
    $user = lecturerPortalSmokeSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    'bimbingan akademik' => ['lecturer.academic-advising.index'],
    'pengumuman' => ['lecturer.announcements.index'],
    'tugas' => ['lecturer.assignments.index'],
    'kalender' => ['lecturer.calendar.index'],
    'konsultasi' => ['lecturer.consultations.index'],
    'materi' => ['lecturer.course-materials.list'],
    'kelas' => ['lecturer.course-offerings.index'],
    'edom' => ['lecturer.edom.index'],
    'banding nilai' => ['lecturer.grade-appeals.index'],
    'nilai mahasiswa' => ['lecturer.student-grades.index'],
    'buku nilai' => ['lecturer.student-grades.grade-book'],
    'tridharma' => ['lecturer.tridharma.index'],
    'tambah tridharma' => ['lecturer.tridharma.create'],
    'beban kerja' => ['lecturer.workloads.index'],
]);
