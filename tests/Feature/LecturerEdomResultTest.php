<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Models\Organization\EdomResponse;
use App\Models\Organization\LecturerPerformanceReview;
use App\Models\Settings\Campus;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function ensureLecturerEdomInstalled(): void
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

function createLecturerEdomContext(): array
{
    ensureLecturerEdomInstalled();

    $lecturerRole = Role::findOrCreate('lecturer', 'web');
    $lecturer = \App\Models\User::factory()->create(['first_name' => 'Raka', 'last_name' => 'EDOM']);
    $lecturer->assignRole($lecturerRole);

    $faculty = Faculty::create(['name' => 'Fakultas Teknologi', 'code' => 'FT-EDOM', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Sistem Informasi', 'code' => 'SI-EDOM', 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027 Genap', 'code' => '2026-EDOM', 'semester' => 'Genap', 'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => true]);
    $course = Course::create(['code' => 'SI401', 'name' => 'Manajemen Proyek TI', 'credits' => 3, 'is_active' => true]);
    $offering = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $course->id, 'label' => 'Reguler A', 'code' => 'SI401-A', 'credits' => 3, 'status' => 'Open']);
    $lecturerProfile = LecturerProfile::create(['user_id' => $lecturer->id, 'faculty_id' => $faculty->id, 'study_program_id' => $program->id, 'nidn' => 'EDOM-001', 'is_active' => true]);

    CourseOfferingLecturer::create(['course_offering_id' => $offering->id, 'lecturer_profile_id' => $lecturerProfile->id, 'role' => 'Primary', 'is_active' => true]);

    $period = EdomPeriod::create([
        'academic_year_id' => $year->id,
        'name' => 'EDOM Genap 2026/2027',
        'code' => 'EDOM-GENAP-2026',
        'status' => 'closed',
        'starts_at' => now()->subMonths(2)->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
        'minimum_responses' => 1,
    ]);

    $scaleQuestion = EdomQuestion::create(['category' => 'teaching', 'question_text' => 'Penyampaian materi jelas?', 'answer_type' => 'scale', 'sort_order' => 1, 'is_required' => true, 'is_active' => true]);
    $commentQuestion = EdomQuestion::create(['category' => 'teaching', 'question_text' => 'Masukan untuk dosen', 'answer_type' => 'text', 'sort_order' => 2, 'is_required' => false, 'is_active' => true]);

    foreach ([['Ayu', 'EDOM-2026-001', 5, 'Penjelasan terstruktur dan mudah diikuti.'], ['Bima', 'EDOM-2026-002', 4, null]] as [$name, $nim, $score, $comment]) {
        $studentUser = \App\Models\User::factory()->create(['first_name' => $name, 'last_name' => 'Mahasiswa']);
        $student = StudentProfile::create(['user_id' => $studentUser->id, 'study_program_id' => $program->id, 'entry_academic_year_id' => $year->id, 'nim' => $nim, 'is_active' => true]);
        $plan = StudyPlan::create(['student_profile_id' => $student->id, 'academic_year_id' => $year->id, 'semester_no' => 4, 'status' => 'Approved']);
        StudyPlanDetail::create(['study_plan_id' => $plan->id, 'course_offering_id' => $offering->id, 'credits' => 3, 'status' => 'Taken']);

        $response = EdomResponse::create([
            'edom_period_id' => $period->id,
            'course_offering_id' => $offering->id,
            'lecturer_profile_id' => $lecturerProfile->id,
            'student_profile_id' => $student->id,
            'submitted_at' => now(),
        ]);

        $response->answers()->create(['edom_question_id' => $scaleQuestion->id, 'score' => $score]);
        $response->answers()->create(['edom_question_id' => $commentQuestion->id, 'text_answer' => $comment]);
    }

    LecturerPerformanceReview::create([
        'edom_period_id' => $period->id,
        'user_id' => $lecturer->id,
        'lecturer_profile_id' => $lecturerProfile->id,
        'edom_score' => 4.50,
        'edom_response_count' => 2,
        'teaching_compliance_score' => 95,
        'attendance_compliance_score' => 90,
        'workload_total_sks' => 12,
        'final_score' => 92.50,
        'status' => 'calculated',
        'calculated_at' => now(),
    ]);

    return compact('lecturer');
}

it('renders anonymous EDOM results for the authenticated lecturer', function () {
    ['lecturer' => $lecturer] = createLecturerEdomContext();

    $this->actingAs($lecturer)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.edom.index'))
        ->assertOk()
        ->assertSee('Hasil EDOM Saya')
        ->assertSee('Manajemen Proyek TI')
        ->assertSee('4.50')
        ->assertSee('Penjelasan terstruktur dan mudah diikuti.')
        ->assertDontSee('EDOM-2026-001');
});
