<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\Scholarship;
use App\Models\Financial\StudentScholarship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function studentScholarshipSetup(): array
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

    $role = Role::findOrCreate('admin', 'web');
    foreach (['student-scholarship.viewAny', 'student-scholarship.create', 'student-scholarship.update', 'student-scholarship.delete'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FB'.$suffix, 'code' => 'FB'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PB'.$suffix, 'code' => 'PB'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YB'.$suffix, 'code' => 'YB'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $scholarship = Scholarship::create([
        'name' => 'Beasiswa Test',
        'type' => 'merit',
        'discount_type' => 'percentage',
        'discount_percentage' => 50,
        'duration_semesters' => 8,
        'is_active' => true,
    ]);

    $students = collect();
    foreach ([1, 2] as $i) {
        $user = App\Models\User::factory()->create();
        $students->push(StudentProfile::create([
            'user_id' => $user->id,
            'study_program_id' => $program->id,
            'nim' => 'B'.$suffix.$i,
            'entry_year' => 2026,
            'academic_status' => 'Aktif',
            'entry_date' => '2026-08-01',
            'current_semester' => 1,
            'is_active' => true,
        ]));
    }

    return compact('admin', 'scholarship', 'students', 'year');
}

function studentScholarshipActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

it('menampilkan daftar penerima', function () {
    $setup = studentScholarshipSetup();
    StudentScholarship::create([
        'student_profile_id' => $setup['students'][0]->id,
        'scholarship_id' => $setup['scholarship']->id,
        'status' => 'active',
    ]);

    studentScholarshipActingAs($setup['admin'])
        ->get(route('admin.financial.student-scholarships.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/StudentScholarship/Index')
            ->where('stats.total', 1)
            ->where('stats.active', 1));
});

it('mengalokasikan beasiswa ke banyak mahasiswa sekaligus', function () {
    $setup = studentScholarshipSetup();

    studentScholarshipActingAs($setup['admin'])
        ->post(route('admin.financial.student-scholarships.store'), [
            'scholarship_id' => $setup['scholarship']->id,
            'academic_year_id' => $setup['year']->id,
            'semester' => 1,
            'status' => 'active',
            'student_profile_ids' => $setup['students']->pluck('id')->all(),
        ])
        ->assertRedirect(route('admin.financial.student-scholarships.index'));

    expect(StudentScholarship::count())->toBe(2);
});

it('mengubah status alokasi', function () {
    $setup = studentScholarshipSetup();
    $assignment = StudentScholarship::create([
        'student_profile_id' => $setup['students'][0]->id,
        'scholarship_id' => $setup['scholarship']->id,
        'status' => 'active',
    ]);

    studentScholarshipActingAs($setup['admin'])
        ->put(route('admin.financial.student-scholarships.update', $assignment), [
            'scholarship_id' => $setup['scholarship']->id,
            'status' => 'revoked',
        ])
        ->assertRedirect();

    expect($assignment->fresh()->status)->toBe('revoked');
});

it('menghapus alokasi dan bulk', function () {
    $setup = studentScholarshipSetup();
    $ids = [];
    foreach ($setup['students'] as $student) {
        $ids[] = StudentScholarship::create([
            'student_profile_id' => $student->id,
            'scholarship_id' => $setup['scholarship']->id,
            'status' => 'active',
        ])->id;
    }

    studentScholarshipActingAs($setup['admin'])
        ->post(route('admin.financial.student-scholarships.bulk-destroy'), ['ids' => $ids])
        ->assertRedirect();

    expect(StudentScholarship::count())->toBe(0);
});

it('alokasi cocok dengan invoice yang sesuai', function () {
    $setup = studentScholarshipSetup();
    $assignment = StudentScholarship::create([
        'student_profile_id' => $setup['students'][0]->id,
        'scholarship_id' => $setup['scholarship']->id,
        'academic_year_id' => $setup['year']->id,
        'semester' => 1,
        'status' => 'active',
    ]);

    $matching = new App\Models\Financial\StudentInvoice([
        'student_profile_id' => $setup['students'][0]->id,
        'academic_year_id' => $setup['year']->id,
        'semester' => 1,
    ]);
    $other = new App\Models\Financial\StudentInvoice([
        'student_profile_id' => $setup['students'][1]->id,
        'academic_year_id' => $setup['year']->id,
        'semester' => 1,
    ]);

    expect($assignment->matchesInvoice($matching))->toBeTrue()
        ->and($assignment->matchesInvoice($other))->toBeFalse();
});
