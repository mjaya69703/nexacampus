<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Settings\Campus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function ensureStudyPlanComparisonInstalled(): void
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

function createComparisonStudent(): array
{
    ensureStudyPlanComparisonInstalled();

    $role = Role::findOrCreate('student', 'web');
    $user = User::factory()->create(['first_name' => 'Ari', 'last_name' => 'KRS']);
    $user->assignRole($role);

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FTK', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Sistem Informasi', 'code' => 'SI', 'degree' => 'S1', 'is_active' => true]);
    $yearOne = AcademicYear::create(['name' => '2025/2026', 'code' => '2025', 'semester' => 'Ganjil', 'start_date' => '2025-08-01', 'end_date' => '2026-01-31', 'is_active' => false]);
    $yearTwo = AcademicYear::create(['name' => '2026/2027', 'code' => '2026', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $yearOne->id,
        'nim' => 'KRS-2026-001',
        'entry_year' => 2025,
        'academic_status' => 'Aktif',
        'current_semester' => 3,
        'class_type' => 'Reguler',
        'is_active' => true,
    ]);

    $sharedCourse = Course::create(['code' => 'IF101', 'name' => 'Algoritma', 'credits' => 3, 'semester_recommendation' => 1, 'is_active' => true]);
    $oldCourse = Course::create(['code' => 'IF102', 'name' => 'Matematika Diskrit', 'credits' => 3, 'semester_recommendation' => 1, 'is_active' => true]);
    $newCourse = Course::create(['code' => 'IF201', 'name' => 'Basis Data', 'credits' => 4, 'semester_recommendation' => 3, 'is_active' => true]);

    $oldPlan = StudyPlan::create(['student_profile_id' => $student->id, 'academic_year_id' => $yearOne->id, 'semester_no' => 1, 'status' => 'Approved']);
    $newPlan = StudyPlan::create(['student_profile_id' => $student->id, 'academic_year_id' => $yearTwo->id, 'semester_no' => 3, 'status' => 'Submitted']);

    foreach ([[$oldPlan, $yearOne, $sharedCourse], [$oldPlan, $yearOne, $oldCourse], [$newPlan, $yearTwo, $sharedCourse], [$newPlan, $yearTwo, $newCourse]] as [$plan, $year, $course]) {
        $offering = CourseOffering::create([
            'academic_year_id' => $year->id,
            'study_program_id' => $program->id,
            'course_id' => $course->id,
            'label' => 'Reguler A',
            'code' => $course->code.'-'.$year->code,
            'semester_no' => $course->semester_recommendation,
            'credits' => $course->credits,
            'status' => 'Open',
        ]);

        StudyPlanDetail::create([
            'study_plan_id' => $plan->id,
            'course_offering_id' => $offering->id,
            'credits' => $course->credits,
            'status' => 'Taken',
        ]);
    }

    return compact('user', 'oldPlan', 'newPlan');
}

it('renders study plan comparison for a student', function () {
    ['user' => $user] = createComparisonStudent();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.study-plan.comparison'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/StudyPlan/Comparison')
            ->where('hasProfile', true)
            ->has('plans', 2)
            ->where('left.title', '2025/2026')
            ->where('right.title', '2026/2027')
            ->where('comparison.sameCount', 1)
            ->where('comparison.addedCount', 1)
            ->where('comparison.removedCount', 1)
            ->where('comparison.creditDelta', 1)
            ->where('comparison.added.0.name', 'Basis Data')
            ->where('comparison.removed.0.name', 'Matematika Diskrit'));
});

it('compares the plans explicitly requested through the query string', function () {
    ['user' => $user, 'oldPlan' => $oldPlan, 'newPlan' => $newPlan] = createComparisonStudent();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.study-plan.comparison', [
            'left' => $newPlan->id,
            'right' => $oldPlan->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('left.id', $newPlan->id)
            ->where('right.id', $oldPlan->id)
            ->where('comparison.addedCount', 1)
            ->where('comparison.removedCount', 1)
            ->where('comparison.creditDelta', -1)
            ->where('comparison.added.0.name', 'Matematika Diskrit')
            ->where('comparison.removed.0.name', 'Basis Data'));
});

it('falls back to the default plans when the requested plan belongs to another student', function () {
    ['user' => $user, 'oldPlan' => $oldPlan] = createComparisonStudent();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.study-plan.comparison', ['left' => 99999, 'right' => $oldPlan->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('left.title', '2025/2026')
            ->where('right.id', $oldPlan->id));
});
