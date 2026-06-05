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
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\LecturerPerformanceRubric;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadRule;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use App\Support\Organization\AcademicLeaderContext;
use App\Support\Organization\ApprovalEngine;
use App\Support\Organization\EmployeePositionAssignmentService;
use App\Support\Organization\EdomService;
use App\Support\Organization\LecturerWorkloadService;
use Illuminate\Validation\ValidationException;

function workloadEdomAcademicSet(): array
{
    $faculty = Faculty::create(['name' => 'Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Informatika', 'code' => 'IF', 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027 Ganjil', 'code' => '2026-GANJIL', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $course = Course::create(['code' => 'IF101', 'name' => 'Algoritma', 'credits' => 3, 'is_active' => true]);
    $offering = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $course->id, 'label' => 'A', 'code' => 'IF101-A', 'credits' => 3, 'total_meetings' => 14, 'status' => 'Open']);

    return compact('faculty', 'program', 'year', 'course', 'offering');
}

it('generates lecturer workload from teaching position and tridharma sources', function () {
    $set = workloadEdomAcademicSet();
    $lecturer = User::factory()->create();
    $profile = LecturerProfile::create(['user_id' => $lecturer->id, 'faculty_id' => $set['faculty']->id, 'study_program_id' => $set['program']->id, 'nidn' => '001', 'is_active' => true]);
    $employee = EmployeeProfile::create(['user_id' => $lecturer->id, 'employee_number' => 'EMP-BKD-001', 'employment_type' => 'lecturer', 'employment_status' => 'active', 'is_active' => true]);

    CourseOfferingLecturer::create(['course_offering_id' => $set['offering']->id, 'lecturer_profile_id' => $profile->id, 'role' => 'Primary', 'is_active' => true]);
    app(EmployeePositionAssignmentService::class)->create(['employee_profile_id' => $employee->id, 'organizational_position_id' => OrganizationalPosition::where('code', 'KAPRODI')->value('id'), 'study_program_id' => $set['program']->id, 'is_active' => true]);
    LecturerWorkloadRule::create(['category' => 'structural', 'source_code' => 'KAPRODI', 'name' => 'Kaprodi', 'sks_value' => 3, 'maximum_sks' => 3, 'is_active' => true]);
    LecturerWorkloadRule::create(['category' => 'tridharma', 'source_code' => 'RESEARCH', 'name' => 'Penelitian', 'sks_value' => 3, 'maximum_sks' => 6, 'is_active' => true]);
    TridharmaRecord::create(['user_id' => $lecturer->id, 'lecturer_profile_id' => $profile->id, 'employee_profile_id' => $employee->id, 'type' => 'research', 'title' => 'Riset Pembelajaran', 'status' => 'approved', 'is_verified' => true]);

    $period = LecturerWorkloadPeriod::create(['academic_year_id' => $set['year']->id, 'name' => 'BKD Test', 'code' => 'BKD-TEST', 'status' => 'open', 'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addMonth()->toDateString()]);
    $submission = app(LecturerWorkloadService::class)->generateFor($lecturer->fresh(['lecturerProfile', 'employeeProfile']), $period);

    expect($submission->teaching_sks)->toBe('3.00')
        ->and($submission->structural_sks)->toBe('3.00')
        ->and($submission->tridharma_sks)->toBe('3.00')
        ->and($submission->total_sks)->toBe('9.00');
});

it('allows a lecturer to submit only their own workload', function () {
    $set = workloadEdomAcademicSet();
    $lecturer = User::factory()->create();
    $other = User::factory()->create();
    $profile = LecturerProfile::create(['user_id' => $lecturer->id, 'faculty_id' => $set['faculty']->id, 'study_program_id' => $set['program']->id, 'nidn' => '002', 'is_active' => true]);
    EmployeeProfile::create(['user_id' => $lecturer->id, 'employee_number' => 'EMP-BKD-002', 'employment_type' => 'lecturer', 'employment_status' => 'active', 'is_active' => true]);
    CourseOfferingLecturer::create(['course_offering_id' => $set['offering']->id, 'lecturer_profile_id' => $profile->id, 'role' => 'Primary', 'is_active' => true]);
    $period = LecturerWorkloadPeriod::create(['academic_year_id' => $set['year']->id, 'name' => 'BKD Submit', 'code' => 'BKD-SUBMIT', 'status' => 'open']);
    $submission = app(LecturerWorkloadService::class)->generateFor($lecturer->fresh(['lecturerProfile', 'employeeProfile']), $period);

    ApprovalTemplate::create(['name' => 'Review BKD', 'code' => 'LECTURER_WORKLOAD_REVIEW', 'module' => 'organization', 'is_active' => true])
        ->steps()
        ->create(['step_order' => 1, 'name' => 'Review', 'approver_type' => 'user', 'approver_user_id' => $other->id]);

    expect(fn () => app(LecturerWorkloadService::class)->submit($submission, $other))->toThrow(ValidationException::class);

    $submitted = app(LecturerWorkloadService::class)->submit($submission, $lecturer);
    expect($submitted->status)->toBe('in_approval')
        ->and($submitted->approval_request_id)->not->toBeNull();
});

it('allows a lecturer to regenerate and resubmit a workload after revision is requested', function () {
    $set = workloadEdomAcademicSet();
    $lecturer = User::factory()->create();
    $reviewer = User::factory()->create();
    $profile = LecturerProfile::create(['user_id' => $lecturer->id, 'faculty_id' => $set['faculty']->id, 'study_program_id' => $set['program']->id, 'nidn' => '002-R', 'is_active' => true]);
    EmployeeProfile::create(['user_id' => $lecturer->id, 'employee_number' => 'EMP-BKD-002-R', 'employment_type' => 'lecturer', 'employment_status' => 'active', 'is_active' => true]);
    CourseOfferingLecturer::create(['course_offering_id' => $set['offering']->id, 'lecturer_profile_id' => $profile->id, 'role' => 'Primary', 'is_active' => true]);

    $period = LecturerWorkloadPeriod::create(['academic_year_id' => $set['year']->id, 'name' => 'BKD Revision', 'code' => 'BKD-REVISION', 'status' => 'open']);
    $submission = app(LecturerWorkloadService::class)->generateFor($lecturer->fresh(['lecturerProfile', 'employeeProfile']), $period);

    ApprovalTemplate::create(['name' => 'Review BKD Revisi', 'code' => 'LECTURER_WORKLOAD_REVIEW', 'module' => 'organization', 'is_active' => true])
        ->steps()
        ->create(['step_order' => 1, 'name' => 'Review', 'approver_type' => 'user', 'approver_user_id' => $reviewer->id]);

    $submitted = app(LecturerWorkloadService::class)->submit($submission, $lecturer, 'Pengajuan awal');
    $submitted->update([
        'status' => 'revision',
        'approval_request_id' => null,
        'review_notes' => 'Perbaiki sumber SKS mengajar.',
    ]);

    $set['offering']->update(['credits' => 4]);
    $regenerated = app(LecturerWorkloadService::class)->generateFor($lecturer->fresh(['lecturerProfile', 'employeeProfile']), $period);

    expect($regenerated->status)->toBe('draft')
        ->and($regenerated->teaching_sks)->toBe('4.00')
        ->and($regenerated->submitted_at)->toBeNull()
        ->and($regenerated->review_notes)->toBe('Perbaiki sumber SKS mengajar.');

    $resubmitted = app(LecturerWorkloadService::class)->submit($regenerated, $lecturer, 'Sudah diperbaiki.');

    expect($resubmitted->status)->toBe('in_approval')
        ->and($resubmitted->lecturer_notes)->toBe('Sudah diperbaiki.')
        ->and($resubmitted->approval_request_id)->not->toBeNull();
});

it('lets students submit EDOM once for an eligible enrolled class', function () {
    $set = workloadEdomAcademicSet();
    $lecturer = User::factory()->create();
    $student = User::factory()->create();
    $lecturerProfile = LecturerProfile::create(['user_id' => $lecturer->id, 'faculty_id' => $set['faculty']->id, 'study_program_id' => $set['program']->id, 'nidn' => '003', 'is_active' => true]);
    $studentProfile = StudentProfile::create(['user_id' => $student->id, 'study_program_id' => $set['program']->id, 'entry_academic_year_id' => $set['year']->id, 'nim' => '20260001', 'is_active' => true]);
    CourseOfferingLecturer::create(['course_offering_id' => $set['offering']->id, 'lecturer_profile_id' => $lecturerProfile->id, 'role' => 'Primary', 'is_active' => true]);
    $plan = StudyPlan::create(['student_profile_id' => $studentProfile->id, 'academic_year_id' => $set['year']->id, 'semester_no' => 1, 'status' => 'Approved']);
    StudyPlanDetail::create(['study_plan_id' => $plan->id, 'course_offering_id' => $set['offering']->id, 'credits' => 3, 'status' => 'Taken']);
    $period = EdomPeriod::create(['academic_year_id' => $set['year']->id, 'name' => 'EDOM Test', 'code' => 'EDOM-TEST', 'status' => 'open', 'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(), 'minimum_responses' => 1]);
    $question = EdomQuestion::create(['category' => 'teaching', 'question_text' => 'Dosen jelas?', 'answer_type' => 'scale', 'sort_order' => 1, 'is_required' => true, 'is_active' => true]);

    $response = app(EdomService::class)->submit($student->fresh('studentProfile'), $period, $set['offering']->id, $lecturerProfile->id, [$question->id => 5]);

    expect($response->answers)->toHaveCount(1);
    expect(fn () => app(EdomService::class)->submit($student->fresh('studentProfile'), $period, $set['offering']->id, $lecturerProfile->id, [$question->id => 4]))->toThrow(ValidationException::class);
});

it('resolves academic leader faculty and study program scope without title auth roles', function () {
    $set = workloadEdomAcademicSet();
    $leader = User::factory()->create();
    $employee = EmployeeProfile::create(['user_id' => $leader->id, 'employee_number' => 'LEAD-001', 'employment_type' => 'staff', 'employment_status' => 'active', 'is_active' => true]);

    app(EmployeePositionAssignmentService::class)->create(['employee_profile_id' => $employee->id, 'organizational_position_id' => OrganizationalPosition::where('code', 'DEKAN')->value('id'), 'faculty_id' => $set['faculty']->id, 'is_active' => true]);
    app(EmployeePositionAssignmentService::class)->create(['employee_profile_id' => $employee->id, 'organizational_position_id' => OrganizationalPosition::where('code', 'KAPRODI')->value('id'), 'study_program_id' => $set['program']->id, 'is_active' => true]);

    $context = app(AcademicLeaderContext::class);

    expect($context->hasScope($leader))->toBeTrue()
        ->and($context->facultyIds($leader))->toContain($set['faculty']->id)
        ->and($context->studyProgramIds($leader))->toContain($set['program']->id);
});

it('calculates lecturer performance with active weighted rubric', function () {
    $set = workloadEdomAcademicSet();
    $lecturer = User::factory()->create();
    $student = User::factory()->create();
    $lecturerProfile = LecturerProfile::create(['user_id' => $lecturer->id, 'faculty_id' => $set['faculty']->id, 'study_program_id' => $set['program']->id, 'nidn' => '004', 'is_active' => true]);
    EmployeeProfile::create(['user_id' => $lecturer->id, 'employee_number' => 'EMP-PERF-004', 'employment_type' => 'lecturer', 'employment_status' => 'active', 'is_active' => true]);
    $studentProfile = StudentProfile::create(['user_id' => $student->id, 'study_program_id' => $set['program']->id, 'entry_academic_year_id' => $set['year']->id, 'nim' => '20260004', 'is_active' => true]);
    CourseOfferingLecturer::create(['course_offering_id' => $set['offering']->id, 'lecturer_profile_id' => $lecturerProfile->id, 'role' => 'Primary', 'is_active' => true]);
    $plan = StudyPlan::create(['student_profile_id' => $studentProfile->id, 'academic_year_id' => $set['year']->id, 'semester_no' => 1, 'status' => 'Approved']);
    StudyPlanDetail::create(['study_plan_id' => $plan->id, 'course_offering_id' => $set['offering']->id, 'credits' => 3, 'status' => 'Taken']);
    $edomPeriod = EdomPeriod::create(['academic_year_id' => $set['year']->id, 'name' => 'EDOM Weighted', 'code' => 'EDOM-WEIGHTED', 'status' => 'open', 'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(), 'minimum_responses' => 1]);
    $question = EdomQuestion::create(['category' => 'teaching', 'question_text' => 'Dosen jelas sekali?', 'answer_type' => 'scale', 'sort_order' => 1, 'is_required' => true, 'is_active' => true]);
    app(EdomService::class)->submit($student->fresh('studentProfile'), $edomPeriod, $set['offering']->id, $lecturerProfile->id, [$question->id => 5]);
    $workloadPeriod = LecturerWorkloadPeriod::create(['academic_year_id' => $set['year']->id, 'name' => 'BKD Weighted', 'code' => 'BKD-WEIGHTED', 'status' => 'open']);
    LecturerWorkloadSubmission::create(['lecturer_workload_period_id' => $workloadPeriod->id, 'user_id' => $lecturer->id, 'lecturer_profile_id' => $lecturerProfile->id, 'status' => 'approved', 'total_sks' => 12]);
    LecturerPerformanceRubric::create(['code' => 'TEST', 'name' => 'Rubrik Test', 'edom_weight' => 50, 'teaching_weight' => 0, 'attendance_weight' => 0, 'workload_weight' => 50, 'minimum_responses' => 1, 'target_workload_sks' => 12, 'is_active' => true]);

    $review = app(EdomService::class)->calculatePerformance($lecturer->fresh(['lecturerProfile', 'employeeProfile']), $edomPeriod, $workloadPeriod);

    expect($review->final_score)->toBe('100.00')
        ->and(data_get($review->snapshot, 'rubric.code'))->toBe('TEST')
        ->and((float) data_get($review->snapshot, 'workload_component_score'))->toBe(100.0);
});

it('requires position approvers to match lecturer workload scope', function () {
    $set = workloadEdomAcademicSet();
    $lecturer = User::factory()->create();
    $matchingLeader = User::factory()->create();
    $otherLeader = User::factory()->create();
    $reviewer = User::factory()->create();
    $lecturerProfile = LecturerProfile::create(['user_id' => $lecturer->id, 'faculty_id' => $set['faculty']->id, 'study_program_id' => $set['program']->id, 'nidn' => '005', 'is_active' => true]);
    EmployeeProfile::create(['user_id' => $lecturer->id, 'employee_number' => 'EMP-APP-005', 'employment_type' => 'lecturer', 'employment_status' => 'active', 'is_active' => true]);
    $matchingEmployee = EmployeeProfile::create(['user_id' => $matchingLeader->id, 'employee_number' => 'LEAD-APP-001', 'employment_type' => 'staff', 'employment_status' => 'active', 'is_active' => true]);
    $otherEmployee = EmployeeProfile::create(['user_id' => $otherLeader->id, 'employee_number' => 'LEAD-APP-002', 'employment_type' => 'staff', 'employment_status' => 'active', 'is_active' => true]);
    $otherFaculty = Faculty::create(['name' => 'Ekonomi', 'code' => 'FE', 'is_active' => true]);
    $otherProgram = StudyProgram::create(['faculty_id' => $otherFaculty->id, 'name' => 'Manajemen', 'code' => 'MJ', 'degree' => 'S1', 'is_active' => true]);
    app(EmployeePositionAssignmentService::class)->create(['employee_profile_id' => $matchingEmployee->id, 'organizational_position_id' => OrganizationalPosition::where('code', 'KAPRODI')->value('id'), 'study_program_id' => $set['program']->id, 'is_active' => true]);
    app(EmployeePositionAssignmentService::class)->create(['employee_profile_id' => $otherEmployee->id, 'organizational_position_id' => OrganizationalPosition::where('code', 'KAPRODI')->value('id'), 'study_program_id' => $otherProgram->id, 'is_active' => true]);

    $period = LecturerWorkloadPeriod::create(['academic_year_id' => $set['year']->id, 'name' => 'BKD Approval Scope', 'code' => 'BKD-APP-SCOPE', 'status' => 'open']);
    $submission = LecturerWorkloadSubmission::create(['lecturer_workload_period_id' => $period->id, 'user_id' => $lecturer->id, 'lecturer_profile_id' => $lecturerProfile->id, 'status' => 'draft', 'total_sks' => 12]);
    ApprovalTemplate::create(['name' => 'Review BKD Scope', 'code' => 'LECTURER_WORKLOAD_REVIEW', 'module' => 'organization', 'is_active' => true])
        ->steps()
        ->create(['step_order' => 1, 'name' => 'Review Kaprodi', 'approver_type' => 'position', 'organizational_position_id' => OrganizationalPosition::where('code', 'KAPRODI')->value('id')]);

    $submitted = app(LecturerWorkloadService::class)->submit($submission, $lecturer);
    $step = $submitted->approvalRequest->steps->first();

    expect(app(ApprovalEngine::class)->canUserActOnStep($otherLeader->fresh('employeeProfile'), $step))->toBeFalse()
        ->and(app(ApprovalEngine::class)->canUserActOnStep($matchingLeader->fresh('employeeProfile'), $step))->toBeTrue();
});
