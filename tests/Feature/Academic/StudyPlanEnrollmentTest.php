<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Support\Academic\StudyPlanEnrollmentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function enrollmentSetup(): array
{
    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = App\Models\Academic\StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'code' => 'TI',
        'degree' => 'S1',
        'is_active' => true,
    ]);
    $otherProgram = App\Models\Academic\StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Sistem Informasi',
        'code' => 'SI',
        'degree' => 'S1',
        'is_active' => true,
    ]);
    $year = AcademicYear::create([
        'name' => '2026/2027',
        'code' => 'Y26',
        'semester' => 'Ganjil',
        'start_date' => '2026-08-01',
        'end_date' => '2027-01-31',
        'is_active' => true,
    ]);
    $course = Course::create([
        'code' => 'TI101',
        'name' => 'Algoritma',
        'credits' => 3,
        'requirement_type' => 'Wajib',
        'category_type' => 'Keilmuan',
        'is_active' => true,
    ]);
    $otherCourse = Course::create([
        'code' => 'TI102',
        'name' => 'Basis Data',
        'credits' => 3,
        'requirement_type' => 'Wajib',
        'category_type' => 'Keilmuan',
        'is_active' => true,
    ]);
    $student = makeEnrollmentStudent($program, '2026TI0001');
    $plan = StudyPlan::create([
        'student_profile_id' => $student->id,
        'academic_year_id' => $year->id,
        'semester_no' => 1,
        'status' => 'Draft',
    ]);
    $offering = CourseOffering::create([
        'academic_year_id' => $year->id,
        'study_program_id' => $program->id,
        'course_id' => $course->id,
        'label' => 'A',
        'semester_no' => 1,
        'capacity' => 1,
        'delivery_mode' => 'Offline',
        'status' => 'Open',
    ]);

    return compact('faculty', 'program', 'otherProgram', 'year', 'course', 'otherCourse', 'student', 'plan', 'offering');
}

function makeEnrollmentStudent($program, string $nim): StudentProfile
{
    $user = User::factory()->create();

    return StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'nim' => $nim,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);
}

it('rejects an offering from another study program', function () {
    $setup = enrollmentSetup();
    $otherOffering = CourseOffering::create([
        'academic_year_id' => $setup['year']->id,
        'study_program_id' => $setup['otherProgram']->id,
        'course_id' => $setup['otherCourse']->id,
        'label' => 'A',
        'semester_no' => 1,
        'capacity' => 20,
        'delivery_mode' => 'Offline',
        'status' => 'Open',
    ]);

    $result = app(StudyPlanEnrollmentService::class)->addOffering(
        $setup['plan'],
        $setup['student'],
        $otherOffering->id,
        1,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('program studi');
});

it('reserves capacity and rejects the next student when the offering is full', function () {
    $setup = enrollmentSetup();
    $service = app(StudyPlanEnrollmentService::class);

    expect($service->addOffering($setup['plan'], $setup['student'], $setup['offering']->id, 1)['success'])->toBeTrue();

    $secondStudent = makeEnrollmentStudent($setup['program'], '2026TI0002');
    $secondPlan = StudyPlan::create([
        'student_profile_id' => $secondStudent->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 1,
        'status' => 'Draft',
    ]);

    $result = $service->addOffering($secondPlan, $secondStudent, $setup['offering']->id, 1);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('penuh');
});

it('rejects a course whose schedule overlaps an existing selected course', function () {
    $setup = enrollmentSetup();
    $service = app(StudyPlanEnrollmentService::class);
    $setup['offering']->update(['capacity' => 20]);
    $service->addOffering($setup['plan'], $setup['student'], $setup['offering']->id, 1);

    CourseSchedule::create([
        'course_offering_id' => $setup['offering']->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'session_type' => 'Lecture',
        'delivery_mode' => 'Offline',
        'is_active' => true,
    ]);

    $candidate = CourseOffering::create([
        'academic_year_id' => $setup['year']->id,
        'study_program_id' => $setup['program']->id,
        'course_id' => $setup['otherCourse']->id,
        'label' => 'A',
        'semester_no' => 1,
        'capacity' => 20,
        'delivery_mode' => 'Offline',
        'status' => 'Open',
    ]);
    CourseSchedule::create([
        'course_offering_id' => $candidate->id,
        'day_of_week' => 'Monday',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'session_type' => 'Lecture',
        'delivery_mode' => 'Offline',
        'is_active' => true,
    ]);

    $result = $service->addOffering($setup['plan'], $setup['student'], $candidate->id, 1);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Jadwal bentrok');
});
