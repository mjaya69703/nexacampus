<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\Faculty;
use App\Models\Academic\GradeAppeal;
use App\Models\Academic\GradeAppealAttachment;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Settings\Campus;
use App\Models\User;
use App\Support\TranscriptSyncService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function ensureGradeAppealInstalled(): void
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

function createGradeAppealContext(): array
{
    ensureGradeAppealInstalled();

    $studentRole = Role::findOrCreate('student', 'web');
    $lecturerRole = Role::findOrCreate('lecturer', 'web');

    $studentUser = User::factory()->create(['first_name' => 'Ari', 'last_name' => 'Appeal']);
    $lecturerUser = User::factory()->create(['first_name' => 'Dina', 'last_name' => 'Reviewer']);
    $otherLecturerUser = User::factory()->create(['first_name' => 'Bima', 'last_name' => 'Lain']);
    $studentUser->assignRole($studentRole);
    $lecturerUser->assignRole($lecturerRole);
    $otherLecturerUser->assignRole($lecturerRole);

    $suffix = (string) random_int(1000, 9999);
    $faculty = Faculty::create(['name' => 'Fakultas Teknologi Appeal', 'code' => 'FTA'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Sistem Informasi Appeal', 'code' => 'SIA'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027 Appeal', 'code' => 'GAP'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $course = Course::create(['code' => 'IF-GAP'.$suffix, 'name' => 'Struktur Data Appeal', 'credits' => 3, 'semester_recommendation' => 3, 'is_active' => true]);
    $offering = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $course->id, 'label' => 'Reguler A', 'code' => 'IF-GAP-A'.$suffix, 'semester_no' => 3, 'credits' => 3, 'status' => 'Open']);

    $studentProfile = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $year->id,
        'nim' => 'GAP-'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'current_semester' => 3,
        'class_type' => 'Reguler',
        'is_active' => true,
    ]);

    $lecturerProfile = LecturerProfile::create(['user_id' => $lecturerUser->id, 'faculty_id' => $faculty->id, 'study_program_id' => $program->id, 'nidn' => 'GAP-'.$suffix, 'is_active' => true]);
    $otherLecturerProfile = LecturerProfile::create(['user_id' => $otherLecturerUser->id, 'faculty_id' => $faculty->id, 'study_program_id' => $program->id, 'nidn' => 'GAP-X'.$suffix, 'is_active' => true]);

    CourseOfferingLecturer::create(['course_offering_id' => $offering->id, 'lecturer_profile_id' => $lecturerProfile->id, 'role' => 'Primary', 'sort_order' => 1, 'is_active' => true]);

    $studyPlan = StudyPlan::create(['student_profile_id' => $studentProfile->id, 'academic_year_id' => $year->id, 'semester_no' => 3, 'status' => 'Approved']);
    $detail = StudyPlanDetail::create(['study_plan_id' => $studyPlan->id, 'course_offering_id' => $offering->id, 'credits' => 3, 'status' => 'Taken']);
    $grade = StudentGrade::create([
        'study_plan_detail_id' => $detail->id,
        'final_score' => 78.00,
        'letter_grade' => 'B',
        'grade_point' => 2.50,
        'result_status' => 'Passed',
        'grade_status' => 'Published',
        'graded_at' => now(),
        'graded_by' => $lecturerUser->id,
    ]);

    $appeal = GradeAppeal::create([
        'student_grade_id' => $grade->id,
        'student_profile_id' => $studentProfile->id,
        'course_offering_id' => $offering->id,
        'lecturer_profile_id' => $lecturerProfile->id,
        'student_user_id' => $studentUser->id,
        'status' => 'submitted',
        'reason_category' => 'calculation',
        'reason' => 'Komponen tugas akhir sepertinya belum masuk ke nilai akhir.',
        'expected_outcome' => 'Mohon dicek ulang perhitungan nilai akhir.',
        'original_score' => 78.00,
        'requested_score' => 91.00,
        'submitted_at' => now(),
        'created_by' => $studentUser->id,
    ]);

    return compact('studentUser', 'lecturerUser', 'otherLecturerUser', 'studentProfile', 'lecturerProfile', 'otherLecturerProfile', 'grade', 'appeal');
}

it('renders grade appeal history for the student', function () {
    ['studentUser' => $studentUser] = createGradeAppealContext();

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.grade-appeals.index'))
        ->assertOk()
        ->assertSee('Keberatan Nilai')
        ->assertSee('Struktur Data Appeal')
        ->assertSee('Terkirim');
});

it('shows grade appeal entry points on the student grades page', function () {
    ['studentUser' => $studentUser] = createGradeAppealContext();

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.grades.index'))
        ->assertOk()
        ->assertSee('Nilai Terpublikasi')
        ->assertSee('Keberatan Diajukan')
        ->assertSee('Pantau Keberatan')
        ->assertSee('Keberatan Nilai');
});

it('renders transcript entries for published student grades', function () {
    ['studentUser' => $studentUser, 'studentProfile' => $studentProfile] = createGradeAppealContext();

    app(TranscriptSyncService::class)->syncStudent($studentProfile->id);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.transcript.index'))
        ->assertOk()
        ->assertSee('Transkrip Akademik')
        ->assertSee('Struktur Data Appeal')
        ->assertSee('IPK');
});

it('lets an assigned lecturer approve an appeal and correct the score', function () {
    ['lecturerUser' => $lecturerUser, 'appeal' => $appeal, 'grade' => $grade] = createGradeAppealContext();

    $this->actingAs($lecturerUser)->withSession(['active_role' => 'lecturer']);

    Livewire::test('lecturer.grade-appeals.index')
        ->call('openResolution', $appeal->id, 'approved')
        ->set('lecturerResponse', 'Nilai disetujui setelah pengecekan ulang komponen akhir.')
        ->set('resolvedScore', '91')
        ->call('resolveAppeal')
        ->assertHasNoErrors();

    expect($appeal->fresh()->status)->toBe('approved')
        ->and($appeal->fresh()->resolved_score)->toBe('91.00')
        ->and($grade->fresh()->final_score)->toBe('91.00')
        ->and($grade->fresh()->letter_grade)->toBe('A');
});

it('opens the correction panel when a lecturer starts reviewing an appeal', function () {
    ['lecturerUser' => $lecturerUser, 'appeal' => $appeal] = createGradeAppealContext();

    $this->actingAs($lecturerUser)->withSession(['active_role' => 'lecturer']);

    Livewire::test('lecturer.grade-appeals.index')
        ->call('startReview', $appeal->id)
        ->assertSet('selectedAppealId', $appeal->id)
        ->assertSet('decision', 'approved');

    expect($appeal->fresh()->status)->toBe('under_review');
});

it('locks published grades from manual lecturer edits', function () {
    ['lecturerUser' => $lecturerUser, 'grade' => $grade] = createGradeAppealContext();

    $this->actingAs($lecturerUser)->withSession(['active_role' => 'lecturer']);

    Livewire::test('lecturer.student-grades.edit', ['id' => $grade->study_plan_detail_id])
        ->set('gradeForm.notes', 'Catatan manual setelah nilai published')
        ->call('saveGrade')
        ->assertHasErrors(['locked']);

    expect($grade->fresh()->notes)->toBeNull()
        ->and($grade->fresh()->grade_status)->toBe('Published');
});

it('lets a student submit an appeal with supporting attachment', function () {
    Storage::fake('local');
    ['studentUser' => $studentUser, 'appeal' => $appeal, 'grade' => $grade] = createGradeAppealContext();
    $appeal->delete();

    $this->actingAs($studentUser)->withSession(['active_role' => 'student']);

    Livewire::test('student.grade-appeals.index')
        ->set('studentGradeId', $grade->id)
        ->set('reasonCategory', 'input_error')
        ->set('reason', 'Nilai tugas akhir saya belum masuk padahal sudah dikumpulkan tepat waktu.')
        ->set('expectedOutcome', 'Mohon cek ulang bukti pengumpulan tugas akhir.')
        ->set('requestedScore', '88')
        ->set('attachments', [UploadedFile::fake()->image('bukti-nilai.png')])
        ->call('submitAppeal')
        ->assertHasNoErrors();

    $newAppeal = GradeAppeal::query()->where('student_grade_id', $grade->id)->latest('id')->first();

    expect($newAppeal)->not->toBeNull()
        ->and($newAppeal->attachments)->toHaveCount(1)
        ->and(Storage::exists($newAppeal->attachments->first()->file_path))->toBeTrue();
});

it('protects grade appeal attachments by student and assigned lecturer scope', function () {
    Storage::fake('local');
    ['studentUser' => $studentUser, 'lecturerUser' => $lecturerUser, 'otherLecturerUser' => $otherLecturerUser, 'appeal' => $appeal] = createGradeAppealContext();
    Storage::put('private/grade-appeals/'.$appeal->id.'/evidence.pdf', 'grade evidence');

    $attachment = GradeAppealAttachment::create([
        'grade_appeal_id' => $appeal->id,
        'file_path' => 'private/grade-appeals/'.$appeal->id.'/evidence.pdf',
        'file_name' => 'evidence.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 14,
    ]);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.grade-appeal-attachments.preview', $attachment))
        ->assertOk();

    $this->actingAs($lecturerUser)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.grade-appeal-attachments.preview', $attachment))
        ->assertOk();

    $this->actingAs($otherLecturerUser)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.grade-appeal-attachments.preview', $attachment))
        ->assertNotFound();
});

it('does not show grade appeals to unrelated lecturers', function () {
    ['otherLecturerUser' => $otherLecturerUser] = createGradeAppealContext();

    $this->actingAs($otherLecturerUser)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.grade-appeals.index'))
        ->assertOk()
        ->assertSee('Keberatan Nilai')
        ->assertDontSee('Ari Appeal')
        ->assertDontSee('Struktur Data Appeal');
});
