<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function registrationCrudSetup(): array
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
    $user = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'nim' => '2026TI0001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);

    return compact('operator', 'program', 'year', 'student');
}

function giveRegistrationPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingRegistrationOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar registrasi dengan statistik enum asli', function () {
    $setup = registrationCrudSetup();
    giveRegistrationPermissionsToOperator(['student-registration.viewAny']);

    StudentRegistration::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 1,
        'registration_status' => 'Submitted',
        'academic_status' => 'Aktif',
        'is_active' => true,
    ]);

    actingRegistrationOperator($setup['operator'])
        ->get('/admin/academic/student-registrations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/StudentRegistration/Index')
            ->where('stats.total', 1)
            ->where('stats.pending', 1)
            ->where('stats.approved', 0)
            ->has('data.rows', 1)
            ->where('data.rows.0.nim', '2026TI0001'));
});

it('menolak registrasi ganda mahasiswa-tahun yang sama', function () {
    $setup = registrationCrudSetup();
    giveRegistrationPermissionsToOperator(['student-registration.create']);

    StudentRegistration::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'registration_status' => 'Draft',
        'academic_status' => 'Aktif',
        'is_active' => true,
    ]);

    actingRegistrationOperator($setup['operator'])
        ->post('/admin/academic/student-registrations', [
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => $setup['year']->id,
            'academic_status' => 'Aktif',
            'registration_status' => 'Draft',
        ])
        ->assertSessionHasErrors('student_profile_id');

    expect(StudentRegistration::count())->toBe(1);
});

it('approval mendorong semester ke profil kecuali cuti', function () {
    $setup = registrationCrudSetup();
    giveRegistrationPermissionsToOperator(['student-registration.update']);

    $registration = StudentRegistration::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 3,
        'registration_status' => 'Submitted',
        'academic_status' => 'Aktif',
        'is_active' => true,
    ]);

    actingRegistrationOperator($setup['operator'])
        ->post("/admin/academic/student-registrations/{$registration->id}/approve", [
            'status' => 'Approved',
        ])
        ->assertSessionHas('success');

    expect($registration->fresh()->registration_status)->toBe('Approved')
        ->and($registration->fresh()->approved_by)->toBe($setup['operator']->id)
        ->and($setup['student']->fresh()->current_semester)->toBe(3);
});

it('approval cuti tidak mengubah semester berjalan', function () {
    $setup = registrationCrudSetup();
    giveRegistrationPermissionsToOperator(['student-registration.update']);

    $registration = StudentRegistration::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 3,
        'registration_status' => 'Submitted',
        'academic_status' => 'Cuti',
        'is_active' => true,
    ]);

    actingRegistrationOperator($setup['operator'])
        ->post("/admin/academic/student-registrations/{$registration->id}/approve", [
            'status' => 'Approved',
        ])
        ->assertSessionHas('success');

    expect($setup['student']->fresh()->current_semester)->toBe(1);
});

it('mengelola sampah registrasi dan mengekspornya', function () {
    $setup = registrationCrudSetup();
    giveRegistrationPermissionsToOperator(['student-registration.viewAny', 'student-registration.delete']);

    $registration = StudentRegistration::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'registration_status' => 'Draft',
        'academic_status' => 'Aktif',
        'is_active' => true,
    ]);

    actingRegistrationOperator($setup['operator'])
        ->delete("/admin/academic/student-registrations/{$registration->id}")
        ->assertRedirect('/admin/academic/student-registrations');

    actingRegistrationOperator($setup['operator'])
        ->get('/admin/academic/student-registrations?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingRegistrationOperator($setup['operator'])
        ->post("/admin/academic/student-registrations/{$registration->id}/restore")
        ->assertSessionHas('success');

    expect(StudentRegistration::find($registration->id))->not->toBeNull();
});
