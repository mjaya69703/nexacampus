<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function academicLeaderPortalSmokeSetup(): User
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
    Role::firstOrCreate(['name' => 'academic-leader', 'guard_name' => 'web']);

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

    $leader = User::factory()->create();
    $leader->assignRole('academic-leader');
    $employeeId = DB::table('employee_profiles')->insertGetId([
        'user_id' => $leader->id, 'is_active' => true,
    ]);
    DB::table('employee_position_assignments')->insert([
        'employee_profile_id' => $employeeId,
        'organizational_position_id' => DB::table('organizational_positions')->where('code', 'KAPRODI')->value('id'),
        'study_program_id' => $programId,
        'is_active' => true,
    ]);

    return $leader;
}

it('merender seluruh halaman portal pimpinan akademik', function (string $routeName) {
    $user = academicLeaderPortalSmokeSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'academic-leader'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    'kelas' => ['academic-leader.classes.index'],
    'edom' => ['academic-leader.edom.index'],
    'dosen' => ['academic-leader.lecturers.index'],
    'laporan' => ['academic-leader.reports.index'],
    'beban kerja' => ['academic-leader.workloads.index'],
]);
