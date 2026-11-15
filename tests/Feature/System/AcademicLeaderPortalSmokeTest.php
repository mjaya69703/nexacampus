<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function academicLeaderPortalSmokeSetup(): array
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
    Role::firstOrCreate(['name' => 'lecturer', 'guard_name' => 'web']);

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

    $leader = User::factory()->create(['first_name' => 'Pimpinan', 'last_name' => 'Portal']);
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

    $lecturer = User::factory()->create(['first_name' => 'Dosen', 'last_name' => 'Portal']);
    $lecturer->assignRole('lecturer');
    $lecturerProfileId = DB::table('lecturer_profiles')->insertGetId([
        'user_id' => $lecturer->id,
        'study_program_id' => $programId,
        'nidn' => '1111222233',
        'is_active' => true,
    ]);

    return compact('leader', 'lecturerProfileId');
}

it('merender seluruh halaman portal pimpinan akademik sebagai Inertia', function (string $routeName, string $component) {
    ['leader' => $user] = academicLeaderPortalSmokeSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'academic-leader'])
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    'kelas' => ['academic-leader.classes.index', 'AcademicLeader/Classes/Index'],
    'edom' => ['academic-leader.edom.index', 'AcademicLeader/Edom/Index'],
    'dosen' => ['academic-leader.lecturers.index', 'AcademicLeader/Lecturers/Index'],
    'laporan' => ['academic-leader.reports.index', 'AcademicLeader/Reports/Index'],
    'beban kerja' => ['academic-leader.workloads.index', 'AcademicLeader/Workloads/Index'],
]);

it('merender detail dosen dalam scope sebagai Inertia', function () {
    ['leader' => $user, 'lecturerProfileId' => $profileId] = academicLeaderPortalSmokeSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'academic-leader'])
        ->get(route('academic-leader.lecturers.show', $profileId))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Lecturers/Show')
            ->where('profile.id', $profileId));
});

it('menolak detail dosen di luar scope pimpinan', function () {
    ['lecturerProfileId' => $profileId] = academicLeaderPortalSmokeSetup();

    $outsider = User::factory()->create();
    $outsider->assignRole('academic-leader');

    $this->actingAs($outsider)
        ->withSession(['active_role' => 'academic-leader'])
        ->get(route('academic-leader.lecturers.show', $profileId))
        ->assertForbidden();
});
