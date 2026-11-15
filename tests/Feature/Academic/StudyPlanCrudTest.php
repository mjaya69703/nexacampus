<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function studyPlanCrudSetup(): array
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
    $course = Course::create([
        'code' => 'TI101', 'name' => 'Algoritma', 'credits' => 3,
        'requirement_type' => 'Wajib', 'category_type' => 'Keilmuan', 'is_active' => true,
    ]);
    $offering = CourseOffering::create([
        'academic_year_id' => $year->id, 'study_program_id' => $program->id,
        'course_id' => $course->id, 'label' => 'A', 'semester_no' => 1,
        'delivery_mode' => 'Offline', 'status' => 'Open',
    ]);

    return compact('operator', 'program', 'year', 'student', 'offering');
}

function giveStudyPlanPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingStudyPlanOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('membuat header KRS lalu redirect ke edit', function () {
    $setup = studyPlanCrudSetup();
    giveStudyPlanPermissionsToOperator(['study-plan.create']);

    $response = actingStudyPlanOperator($setup['operator'])
        ->post('/admin/academic/study-plans', [
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => $setup['year']->id,
            'semester_no' => 1,
            'status' => 'Draft',
        ])
        ->assertSessionHas('success');

    $plan = StudyPlan::where('student_profile_id', $setup['student']->id)->firstOrFail();
    $response->assertRedirect('/admin/academic/study-plans/'.$plan->id.'/edit');

    // Duplikat mahasiswa-tahun ditolak.
    actingStudyPlanOperator($setup['operator'])
        ->post('/admin/academic/study-plans', [
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => $setup['year']->id,
            'status' => 'Draft',
        ])
        ->assertSessionHasErrors('student_profile_id');
});

it('mencatat stempel transisi status dengan benar', function () {
    $setup = studyPlanCrudSetup();
    giveStudyPlanPermissionsToOperator(['study-plan.update']);

    $plan = StudyPlan::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 1,
        'status' => 'Draft',
    ]);
    $plan->details()->create([
        'course_offering_id' => $setup['offering']->id,
        'status' => 'Draft',
    ]);

    actingStudyPlanOperator($setup['operator'])
        ->put("/admin/academic/study-plans/{$plan->id}", [
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => $setup['year']->id,
            'semester_no' => 1,
            'status' => 'Submitted',
        ])
        ->assertRedirect('/admin/academic/study-plans');

    expect($plan->fresh()->submitted_at)->not->toBeNull()
        ->and($plan->fresh()->approved_at)->toBeNull();

    actingStudyPlanOperator($setup['operator'])
        ->put("/admin/academic/study-plans/{$plan->id}", [
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => $setup['year']->id,
            'semester_no' => 1,
            'status' => 'Approved',
        ])
        ->assertRedirect('/admin/academic/study-plans');

    expect($plan->fresh()->approved_by)->toBe($setup['operator']->id)
        ->and($plan->details()->firstOrFail()->fresh()->status)->toBe('Taken');

    actingStudyPlanOperator($setup['operator'])
        ->put("/admin/academic/study-plans/{$plan->id}", [
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => $setup['year']->id,
            'semester_no' => 1,
            'status' => 'Draft',
        ])
        ->assertRedirect('/admin/academic/study-plans');

    expect($plan->fresh()->approved_at)->toBeNull()
        ->and($plan->fresh()->approved_by)->toBeNull()
        ->and($plan->details()->firstOrFail()->fresh()->status)->toBe('Draft');
});

it('mengelola detail MK: tambah, duplikat ditolak, ubah, hapus', function () {
    $setup = studyPlanCrudSetup();
    giveStudyPlanPermissionsToOperator(['study-plan.update']);

    $plan = StudyPlan::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 1,
        'status' => 'Draft',
    ]);

    actingStudyPlanOperator($setup['operator'])
        ->post("/admin/academic/study-plans/{$plan->id}/details", [
            'course_offering_id' => $setup['offering']->id,
            'credits' => 3,
            'status' => 'Taken',
        ])
        ->assertSessionHas('success');

    actingStudyPlanOperator($setup['operator'])
        ->post("/admin/academic/study-plans/{$plan->id}/details", [
            'course_offering_id' => $setup['offering']->id,
            'status' => 'Taken',
        ])
        ->assertSessionHasErrors('course_offering_id');

    $detail = StudyPlanDetail::where('study_plan_id', $plan->id)->firstOrFail();

    actingStudyPlanOperator($setup['operator'])
        ->put("/admin/academic/study-plans/{$plan->id}/details/{$detail->id}", [
            'course_offering_id' => $setup['offering']->id,
            'credits' => 4,
            'is_repeat' => true,
            'status' => 'Taken',
        ])
        ->assertSessionHas('success');

    expect($detail->fresh()->credits)->toBe(4);

    actingStudyPlanOperator($setup['operator'])
        ->delete("/admin/academic/study-plans/{$plan->id}/details/{$detail->id}")
        ->assertSessionHas('success');

    expect(StudyPlanDetail::find($detail->id))->toBeNull();
});

it('menghapus permanen KRS beserta detailnya', function () {
    $setup = studyPlanCrudSetup();
    giveStudyPlanPermissionsToOperator(['study-plan.delete']);

    $plan = StudyPlan::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 1,
        'status' => 'Approved',
    ]);
    $plan->details()->create([
        'course_offering_id' => $setup['offering']->id,
        'status' => 'Taken',
    ]);

    actingStudyPlanOperator($setup['operator'])
        ->delete("/admin/academic/study-plans/{$plan->id}")
        ->assertRedirect('/admin/academic/study-plans');

    expect(StudyPlan::withTrashed()->find($plan->id))->toBeNull()
        ->and(StudyPlanDetail::where('study_plan_id', $plan->id)->count())->toBe(0);
});

it('menampilkan daftar dan detail KRS dengan total SKS', function () {
    $setup = studyPlanCrudSetup();
    giveStudyPlanPermissionsToOperator(['study-plan.viewAny', 'study-plan.view']);

    $plan = StudyPlan::create([
        'student_profile_id' => $setup['student']->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 1,
        'status' => 'Approved',
    ]);
    $plan->details()->create([
        'course_offering_id' => $setup['offering']->id,
        'credits' => 3,
        'status' => 'Taken',
    ]);

    actingStudyPlanOperator($setup['operator'])
        ->get('/admin/academic/study-plans')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/StudyPlan/Index')
            ->where('stats.approved', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.credits', 3));

    actingStudyPlanOperator($setup['operator'])
        ->get("/admin/academic/study-plans/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/StudyPlan/Show')
            ->has('details', 1)
            ->where('plan.status', 'Approved'));
});
