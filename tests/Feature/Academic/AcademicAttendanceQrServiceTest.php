<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\User;
use App\Support\Academic\AcademicAttendanceQrService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

function academicAttendanceQrSet(): array
{
    $faculty = Faculty::create(['name' => 'Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Informatika', 'code' => 'IF', 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027 Ganjil', 'code' => '2026-GANJIL', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $course = Course::create(['code' => 'IF201', 'name' => 'Basis Data', 'credits' => 3, 'is_active' => true]);
    $offering = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $course->id, 'label' => 'A', 'code' => 'IF201-A', 'credits' => 3, 'total_meetings' => 14, 'status' => 'Open']);
    $session = AttendanceSession::create(['course_offering_id' => $offering->id, 'meeting_no' => 1, 'meeting_date' => '2026-08-10', 'start_time' => '08:00', 'end_time' => '09:40', 'topic' => 'Relasi Data']);

    return compact('faculty', 'program', 'year', 'course', 'offering', 'session');
}

it('records a student qr scan only while the lecturer session is opened', function () {
    $set = academicAttendanceQrSet();
    $lecturer = User::factory()->create();
    $student = User::factory()->create();
    $studentProfile = StudentProfile::create(['user_id' => $student->id, 'study_program_id' => $set['program']->id, 'entry_academic_year_id' => $set['year']->id, 'nim' => '20260011', 'is_active' => true]);
    $plan = StudyPlan::create(['student_profile_id' => $studentProfile->id, 'academic_year_id' => $set['year']->id, 'semester_no' => 1, 'status' => 'Approved']);
    StudyPlanDetail::create(['study_plan_id' => $plan->id, 'course_offering_id' => $set['offering']->id, 'credits' => 3, 'status' => 'Taken']);

    $service = app(AcademicAttendanceQrService::class);
    $this->travelTo(Carbon::parse('2026-08-10 08:05:00'));
    $session = $service->openSession($set['session'], $lecturer->id);
    $payload = $service->qrPayload($session);

    $record = $service->recordScan($session, $studentProfile, $payload['token'], $payload['slot'], $student->id, [
        'latitude' => -6.2000000,
        'longitude' => 106.8166660,
        'accuracy' => 12.5,
    ]);

    expect($record->status)->toBe('Present')
        ->and($record->source)->toBe('student_qr')
        ->and($record->verification_status)->toBe('verified')
        ->and($record->token_slot)->toBe($payload['slot']);

    $closed = $service->closeSession($session->fresh(), $lecturer->id);

    expect(fn () => $service->recordScan($closed, $studentProfile, $payload['token'], $payload['slot'], $student->id))
        ->toThrow(ValidationException::class);
});

it('rotates qr tokens every configured two second slot', function () {
    $set = academicAttendanceQrSet();
    $lecturer = User::factory()->create();
    $service = app(AcademicAttendanceQrService::class);
    $session = $service->openSession($set['session'], $lecturer->id);

    $first = $service->qrPayload($session, Carbon::parse('2026-08-10 08:05:00')->timestamp);
    $second = $service->qrPayload($session, Carbon::parse('2026-08-10 08:05:02')->timestamp);

    expect($first['interval'])->toBe(2)
        ->and($first['slot'])->not->toBe($second['slot'])
        ->and($first['token'])->not->toBe($second['token']);
});

it('accepts a recently rotated qr token for slow student devices', function () {
    $set = academicAttendanceQrSet();
    $lecturer = User::factory()->create();
    $student = User::factory()->create();
    $studentProfile = StudentProfile::create(['user_id' => $student->id, 'study_program_id' => $set['program']->id, 'entry_academic_year_id' => $set['year']->id, 'nim' => '20260012', 'is_active' => true]);
    $plan = StudyPlan::create(['student_profile_id' => $studentProfile->id, 'academic_year_id' => $set['year']->id, 'semester_no' => 1, 'status' => 'Approved']);
    StudyPlanDetail::create(['study_plan_id' => $plan->id, 'course_offering_id' => $set['offering']->id, 'credits' => 3, 'status' => 'Taken']);

    $service = app(AcademicAttendanceQrService::class);
    $this->travelTo(Carbon::parse('2026-08-10 08:05:00'));
    $session = $service->openSession($set['session'], $lecturer->id);
    $payload = $service->qrPayload($session);

    $this->travelTo(Carbon::parse('2026-08-10 08:05:08'));
    $record = $service->recordScan($session->fresh(), $studentProfile, $payload['token'], $payload['slot'], $student->id);

    expect($record->status)->toBe('Present')
        ->and($record->source)->toBe('student_qr');
});

it('rejects a qr token outside the slow device grace window', function () {
    $set = academicAttendanceQrSet();
    $lecturer = User::factory()->create();
    $student = User::factory()->create();
    $studentProfile = StudentProfile::create(['user_id' => $student->id, 'study_program_id' => $set['program']->id, 'entry_academic_year_id' => $set['year']->id, 'nim' => '20260013', 'is_active' => true]);
    $plan = StudyPlan::create(['student_profile_id' => $studentProfile->id, 'academic_year_id' => $set['year']->id, 'semester_no' => 1, 'status' => 'Approved']);
    StudyPlanDetail::create(['study_plan_id' => $plan->id, 'course_offering_id' => $set['offering']->id, 'credits' => 3, 'status' => 'Taken']);

    $service = app(AcademicAttendanceQrService::class);
    $this->travelTo(Carbon::parse('2026-08-10 08:05:00'));
    $session = $service->openSession($set['session'], $lecturer->id);
    $payload = $service->qrPayload($session);

    $this->travelTo(Carbon::parse('2026-08-10 08:05:20'));

    expect(fn () => $service->recordScan($session->fresh(), $studentProfile, $payload['token'], $payload['slot'], $student->id))
        ->toThrow(ValidationException::class);
});
