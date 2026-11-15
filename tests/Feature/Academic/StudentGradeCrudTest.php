<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\TranscriptEntry;
use App\Support\StudentGradeLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function gradeCrudSetup(): array
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
    foreach (['student-grade.viewAny', 'student-grade.view', 'student-grade.create', 'student-grade.update', 'student-grade.delete'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FG'.$suffix, 'code' => 'FG'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PG'.$suffix, 'code' => 'PG'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YG'.$suffix, 'code' => 'YG'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => 'G'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);
    $plan = StudyPlan::create(['student_profile_id' => $student->id, 'academic_year_id' => $year->id, 'semester_no' => 1, 'status' => 'Approved']);

    $lecturerUser = App\Models\User::factory()->create();
    $lecturer = LecturerProfile::create(['user_id' => $lecturerUser->id, 'nidn' => 'LG'.$suffix, 'employment_status' => 'Tetap', 'is_active' => true]);

    $course = Course::create(['code' => 'GC'.$suffix, 'name' => 'Matkul Grade', 'credits' => 3, 'is_active' => true]);
    $offering = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $course->id, 'label' => 'A', 'code' => 'GC-A'.$suffix, 'semester_no' => 1, 'credits' => 3, 'status' => 'Open']);
    CourseOfferingLecturer::create(['course_offering_id' => $offering->id, 'lecturer_profile_id' => $lecturer->id, 'role' => 'Primary', 'is_active' => true, 'sort_order' => 1]);

    $detail = StudyPlanDetail::create(['study_plan_id' => $plan->id, 'course_offering_id' => $offering->id, 'credits' => 3, 'status' => 'Taken']);

    return compact('admin', 'student', 'plan', 'lecturer', 'lecturerUser', 'offering', 'detail', 'year');
}

function gradeCrudActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function makeCompleteGrade(array $setup, string $status = 'Draft'): StudentGrade
{
    $grade = StudentGrade::create([
        'study_plan_detail_id' => $setup['detail']->id,
        'grade_status' => $status,
        'graded_by' => $setup['lecturerUser']->id,
    ]);
    StudentGradeComponent::create(['student_grade_id' => $grade->id, 'name' => 'UTS', 'weight_percentage' => 40, 'score' => 80, 'sort_order' => 1]);
    StudentGradeComponent::create(['student_grade_id' => $grade->id, 'name' => 'UAS', 'weight_percentage' => 60, 'score' => 90, 'sort_order' => 2]);

    return $grade->fresh();
}

it('menampilkan daftar nilai dengan filter huruf yang benar', function () {
    $setup = gradeCrudSetup();
    makeCompleteGrade($setup);

    gradeCrudActingAs($setup['admin'])
        ->get(route('admin.academic.student-grades.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/StudentGrade/Index')
            ->where('stats.total', 1)
            ->where('letters', ['A+', 'A', 'B+', 'B', 'C', 'D', 'E']));
});

it('membuat header nilai untuk detail KRS terdaftar resmi', function () {
    $setup = gradeCrudSetup();

    gradeCrudActingAs($setup['admin'])
        ->post(route('admin.academic.student-grades.store'), [
            'study_plan_detail_id' => $setup['detail']->id,
            'graded_by' => $setup['lecturerUser']->id,
        ])
        ->assertRedirect();

    expect(StudentGrade::where('study_plan_detail_id', $setup['detail']->id)->where('grade_status', 'Draft')->exists())->toBeTrue();
});

it('menolak duplikat nilai untuk detail KRS yang sama', function () {
    $setup = gradeCrudSetup();
    makeCompleteGrade($setup);

    gradeCrudActingAs($setup['admin'])
        ->post(route('admin.academic.student-grades.store'), [
            'study_plan_detail_id' => $setup['detail']->id,
        ])
        ->assertSessionHasErrors('study_plan_detail_id');
});

it('menolak finalisasi saat skor belum lengkap', function () {
    $setup = gradeCrudSetup();
    $grade = makeCompleteGrade($setup);
    $grade->components()->where('name', 'UAS')->update(['score' => null]);

    gradeCrudActingAs($setup['admin'])
        ->post(route('admin.academic.student-grades.finalize', $grade))
        ->assertRedirect();

    expect($grade->fresh()->grade_status)->toBe('Draft');
});

it('finalisasi menghitung snapshot dan menyinkron transkrip', function () {
    $setup = gradeCrudSetup();
    $grade = makeCompleteGrade($setup);

    gradeCrudActingAs($setup['admin'])
        ->post(route('admin.academic.student-grades.finalize', $grade))
        ->assertRedirect();

    $fresh = $grade->fresh();

    expect($fresh->grade_status)->toBe('Finalized')
        ->and((float) $fresh->final_score)->toBe(86.0)
        ->and($fresh->letter_grade)->toBe('B+')
        ->and($fresh->graded_at)->not->toBeNull()
        ->and(TranscriptEntry::where('student_profile_id', $setup['student']->id)->where('student_grade_id', $grade->id)->exists())->toBeTrue();
});

it('mengunci mutasi komponen saat nilai Published', function () {
    $setup = gradeCrudSetup();
    $grade = makeCompleteGrade($setup, 'Finalized');
    app(StudentGradeLifecycleService::class)->finalize($grade, $setup['lecturerUser']->id, $setup['admin']->id);
    app(App\Support\StudentGradePublicationService::class)->publish($grade->fresh(), $setup['admin']->id);

    expect($grade->fresh()->grade_status)->toBe('Published');

    gradeCrudActingAs($setup['admin'])
        ->post(route('admin.academic.student-grades.components.store', $grade), [
            'name' => 'Bonus', 'weight_percentage' => 0, 'score' => 100,
        ])
        ->assertRedirect();

    expect($grade->components()->where('name', 'Bonus')->exists())->toBeFalse();
});

it('unpublish mengembalikan ke Finalized sehingga hilang dari portal mahasiswa', function () {
    $setup = gradeCrudSetup();
    $grade = makeCompleteGrade($setup, 'Finalized');
    app(StudentGradeLifecycleService::class)->finalize($grade, $setup['lecturerUser']->id, $setup['admin']->id);
    app(App\Support\StudentGradePublicationService::class)->publish($grade->fresh(), $setup['admin']->id);

    gradeCrudActingAs($setup['admin'])
        ->post(route('admin.academic.student-grades.unpublish', $grade))
        ->assertRedirect();

    // Finalized tetap tercatat di transkrip akademik, tapi tidak lagi
    // tampil di portal mahasiswa (yang hanya membaca status Published).
    expect($grade->fresh()->grade_status)->toBe('Finalized')
        ->and(TranscriptEntry::where('student_grade_id', $grade->id)->exists())->toBeTrue()
        ->and(StudentGrade::where('id', $grade->id)->where('grade_status', 'Published')->exists())->toBeFalse();
});

it('mengunci ganti KRS saat komponen sudah ada', function () {
    $setup = gradeCrudSetup();
    $grade = makeCompleteGrade($setup);

    $courseTwo = Course::create(['code' => 'GC2'.$setup['detail']->id, 'name' => 'Matkul Kedua', 'credits' => 2, 'is_active' => true]);
    $offeringTwo = CourseOffering::create(['academic_year_id' => $setup['year']->id, 'study_program_id' => $setup['detail']->studyPlan->studentProfile->study_program_id, 'course_id' => $courseTwo->id, 'label' => 'B', 'code' => 'GC2-B'.$setup['detail']->id, 'semester_no' => 1, 'credits' => 2, 'status' => 'Open']);
    $otherDetail = StudyPlanDetail::create(['study_plan_id' => $setup['plan']->id, 'course_offering_id' => $offeringTwo->id, 'credits' => 2, 'status' => 'Taken']);

    gradeCrudActingAs($setup['admin'])
        ->put(route('admin.academic.student-grades.update', $grade), [
            'study_plan_detail_id' => $otherDetail->id,
        ])
        ->assertRedirect();

    expect($grade->fresh()->study_plan_detail_id)->toBe($setup['detail']->id);
});

it('menghapus nilai dan menyinkron ulang transkrip', function () {
    $setup = gradeCrudSetup();
    $grade = makeCompleteGrade($setup, 'Finalized');
    app(StudentGradeLifecycleService::class)->finalize($grade, $setup['lecturerUser']->id, $setup['admin']->id);

    expect(TranscriptEntry::where('student_grade_id', $grade->id)->exists())->toBeTrue();

    gradeCrudActingAs($setup['admin'])
        ->delete(route('admin.academic.student-grades.destroy', $grade))
        ->assertRedirect();

    expect(StudentGrade::find($grade->id))->toBeNull()
        ->and(TranscriptEntry::where('student_grade_id', $grade->id)->exists())->toBeFalse();
});

it('bulk publish hanya mempublish yang Finalized', function () {
    $setup = gradeCrudSetup();
    $finalized = makeCompleteGrade($setup);
    app(StudentGradeLifecycleService::class)->finalize($finalized, $setup['lecturerUser']->id, $setup['admin']->id);

    $courseTwo = Course::create(['code' => 'GC3'.$setup['detail']->id, 'name' => 'Matkul Ketiga', 'credits' => 2, 'is_active' => true]);
    $offeringTwo = CourseOffering::create(['academic_year_id' => $setup['year']->id, 'study_program_id' => $setup['detail']->studyPlan->studentProfile->study_program_id, 'course_id' => $courseTwo->id, 'label' => 'C', 'code' => 'GC3-C'.$setup['detail']->id, 'semester_no' => 1, 'credits' => 2, 'status' => 'Open']);
    $otherDetail = StudyPlanDetail::create(['study_plan_id' => $setup['plan']->id, 'course_offering_id' => $offeringTwo->id, 'credits' => 2, 'status' => 'Taken']);
    $draft = StudentGrade::create(['study_plan_detail_id' => $otherDetail->id, 'grade_status' => 'Draft']);

    gradeCrudActingAs($setup['admin'])
        ->post(route('admin.academic.student-grades.bulk-publish'), ['ids' => [$finalized->id, $draft->id]])
        ->assertRedirect();

    expect($finalized->fresh()->grade_status)->toBe('Published')
        ->and($draft->fresh()->grade_status)->toBe('Draft');
});

it('mengekspor xlsx ringkasan nilai', function () {
    $setup = gradeCrudSetup();
    makeCompleteGrade($setup);

    gradeCrudActingAs($setup['admin'])
        ->get(route('admin.academic.student-grades.export', ['format' => 'xlsx']))
        ->assertOk();
});
