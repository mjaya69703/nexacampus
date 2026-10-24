<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function studentPortalSmokeSetup(): User
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
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('student');

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
    DB::table('student_profiles')->insert([
        'user_id' => $user->id,
        'study_program_id' => $programId,
        'nim' => '2026TI0001',
        'academic_status' => 'Aktif',
        'entry_date' => now()->toDateString(),
        'current_semester' => 3,
        'is_active' => true,
    ]);

    return $user;
}

it('merender seluruh halaman portal mahasiswa', function (string $routeName) {
    $user = studentPortalSmokeSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    'pengumuman' => ['student.announcements.index'],
    'tugas' => ['student.assignments.index'],
    'konsultasi' => ['student.consultations.index'],
    'digital id' => ['student.digital-id.index'],
    'edom' => ['student.edom.index'],
    'tagihan' => ['student.financial.invoices'],
    'banding nilai' => ['student.grade-appeals.index'],
    'materi' => ['student.course-materials.index'],
    'jadwal' => ['student.schedule.index'],
    'pengaduan' => ['student.student-services.complaints'],
    'buat pengaduan' => ['student.student-services.complaints.create'],
    'yudisium' => ['student.student-services.graduations'],
    'ajukan yudisium' => ['student.student-services.graduations.create'],
    'cuti' => ['student.student-services.leaves'],
    'ajukan cuti' => ['student.student-services.leaves.create'],
    'surat' => ['student.student-services.letters'],
    'ajukan surat' => ['student.student-services.letters.create'],
    'pindah' => ['student.student-services.transfers'],
    'ajukan pindah' => ['student.student-services.transfers.create'],
]);
