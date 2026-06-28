<?php

use App\Livewire\Academic\StudentGradeTable;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\TranscriptEntry;
use App\Models\Settings\Campus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function ensureStudentGradePublishInstalled(): void
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

function createStudentGradePublishContext(): array
{
    ensureStudentGradePublishInstalled();

    $role = Role::findOrCreate('admin', 'web');
    $permission = Permission::findOrCreate('student-grade.update', 'web');
    $role->givePermissionTo($permission);

    $admin = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Publish']);
    $admin->assignRole($role);

    $suffix = (string) random_int(1000, 9999);
    $faculty = Faculty::create(['name' => 'Fakultas Publish '.$suffix, 'code' => 'FP'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Program Publish', 'code' => 'PP'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027 Publish', 'code' => 'PUB'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $studentUser = User::factory()->create(['first_name' => 'Siti', 'last_name' => 'Nilai']);
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $year->id,
        'nim' => 'PUB-'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'current_semester' => 3,
        'class_type' => 'Reguler',
        'is_active' => true,
    ]);
    $studyPlan = StudyPlan::create(['student_profile_id' => $student->id, 'academic_year_id' => $year->id, 'semester_no' => 3, 'status' => 'Approved']);

    $courseOne = Course::create(['code' => 'PUB101'.$suffix, 'name' => 'Kalkulus Publish', 'credits' => 3, 'is_active' => true]);
    $offeringOne = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $courseOne->id, 'label' => 'A', 'code' => 'PUB101-A'.$suffix, 'semester_no' => 3, 'credits' => 3, 'status' => 'Open']);
    $detailOne = StudyPlanDetail::create(['study_plan_id' => $studyPlan->id, 'course_offering_id' => $offeringOne->id, 'credits' => 3, 'status' => 'Taken']);
    $finalizedGrade = StudentGrade::create([
        'study_plan_detail_id' => $detailOne->id,
        'final_score' => 92.00,
        'letter_grade' => 'A',
        'grade_point' => 3.50,
        'result_status' => 'Passed',
        'grade_status' => 'Finalized',
        'graded_at' => now(),
    ]);

    $courseTwo = Course::create(['code' => 'PUB102'.$suffix, 'name' => 'Statistika Draft', 'credits' => 3, 'is_active' => true]);
    $offeringTwo = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $courseTwo->id, 'label' => 'A', 'code' => 'PUB102-A'.$suffix, 'semester_no' => 3, 'credits' => 3, 'status' => 'Open']);
    $detailTwo = StudyPlanDetail::create(['study_plan_id' => $studyPlan->id, 'course_offering_id' => $offeringTwo->id, 'credits' => 3, 'status' => 'Taken']);
    $draftGrade = StudentGrade::create([
        'study_plan_detail_id' => $detailTwo->id,
        'final_score' => 70.00,
        'letter_grade' => 'B',
        'grade_point' => 2.50,
        'result_status' => 'Passed',
        'grade_status' => 'Draft',
        'graded_at' => now(),
    ]);

    return compact('admin', 'student', 'finalizedGrade', 'draftGrade');
}

it('publishes a finalized grade from the admin edit page and keeps transcript synced', function () {
    ['admin' => $admin, 'student' => $student, 'finalizedGrade' => $finalizedGrade] = createStudentGradePublishContext();

    $this->actingAs($admin)->withSession(['active_role' => 'admin']);

    Livewire::test('admin.academic.student-grades.edit', ['id' => $finalizedGrade->id])
        ->call('publishGrade')
        ->assertHasNoErrors();

    expect($finalizedGrade->fresh()->grade_status)->toBe('Published')
        ->and(TranscriptEntry::query()->where('student_profile_id', $student->id)->where('student_grade_id', $finalizedGrade->id)->exists())->toBeTrue();
});

it('bulk publishes only finalized grades from the student grade table', function () {
    ['admin' => $admin, 'finalizedGrade' => $finalizedGrade, 'draftGrade' => $draftGrade] = createStudentGradePublishContext();

    $this->actingAs($admin)->withSession(['active_role' => 'admin']);

    Livewire::test(StudentGradeTable::class)
        ->set('checkboxValues', [$finalizedGrade->id, $draftGrade->id])
        ->call('runCustomBulkAction');

    expect($finalizedGrade->fresh()->grade_status)->toBe('Published')
        ->and($draftGrade->fresh()->grade_status)->toBe('Draft');
});
