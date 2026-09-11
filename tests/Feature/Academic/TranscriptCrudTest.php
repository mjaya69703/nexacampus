<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use App\Support\StudentGradeLifecycleService;
use App\Support\TranscriptSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function transcriptSetup(): array
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
    foreach (['transcript.viewAny', 'transcript.view', 'transcript.update'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FT'.$suffix, 'code' => 'FT'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PT'.$suffix, 'code' => 'PT'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YT'.$suffix, 'code' => 'YT'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => 'T'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);
    $plan = StudyPlan::create(['student_profile_id' => $student->id, 'academic_year_id' => $year->id, 'semester_no' => 1, 'status' => 'Approved']);

    $lecturerUser = App\Models\User::factory()->create();
    $course = Course::create(['code' => 'TC'.$suffix, 'name' => 'Matkul Transkrip', 'credits' => 3, 'is_active' => true]);
    $offering = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $course->id, 'label' => 'A', 'code' => 'TC-A'.$suffix, 'semester_no' => 1, 'credits' => 3, 'status' => 'Open']);
    $detail = StudyPlanDetail::create(['study_plan_id' => $plan->id, 'course_offering_id' => $offering->id, 'credits' => 3, 'status' => 'Taken']);

    $grade = StudentGrade::create(['study_plan_detail_id' => $detail->id, 'grade_status' => 'Draft', 'graded_by' => $lecturerUser->id]);
    StudentGradeComponent::create(['student_grade_id' => $grade->id, 'name' => 'UTS', 'weight_percentage' => 50, 'score' => 80, 'sort_order' => 1]);
    StudentGradeComponent::create(['student_grade_id' => $grade->id, 'name' => 'UAS', 'weight_percentage' => 50, 'score' => 90, 'sort_order' => 2]);

    return compact('admin', 'student', 'grade', 'lecturerUser', 'year');
}

function transcriptActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

it('menampilkan daftar transkrip dengan statistik', function () {
    $setup = transcriptSetup();

    transcriptActingAs($setup['admin'])
        ->get(route('admin.academic.transcripts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/Transcript/Index')
            ->where('stats.students', 1));
});

it('sync menghitung snapshot semester dan entri terbaik', function () {
    $setup = transcriptSetup();
    app(StudentGradeLifecycleService::class)->finalize($setup['grade'], $setup['lecturerUser']->id, $setup['admin']->id);

    transcriptActingAs($setup['admin'])
        ->post(route('admin.academic.transcripts.sync', $setup['student']->id))
        ->assertRedirect();

    expect(StudyResult::where('student_profile_id', $setup['student']->id)->count())->toBe(1)
        ->and(TranscriptEntry::where('student_profile_id', $setup['student']->id)->count())->toBe(1)
        ->and((float) StudyResult::where('student_profile_id', $setup['student']->id)->first()->semester_gpa)->toBe(3.0);
});

it('menampilkan detail transkrip mahasiswa', function () {
    $setup = transcriptSetup();
    app(StudentGradeLifecycleService::class)->finalize($setup['grade'], $setup['lecturerUser']->id, $setup['admin']->id);
    app(TranscriptSyncService::class)->syncStudent($setup['student']->id);

    transcriptActingAs($setup['admin'])
        ->get(route('admin.academic.transcripts.show', $setup['student']->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/Transcript/Show')
            ->has('results', 1)
            ->has('entries', 1));
});

it('membersihkan snapshot basi saat tahun tak lagi punya nilai final', function () {
    $setup = transcriptSetup();
    app(StudentGradeLifecycleService::class)->finalize($setup['grade'], $setup['lecturerUser']->id, $setup['admin']->id);
    app(TranscriptSyncService::class)->syncStudent($setup['student']->id);

    expect(StudyResult::where('student_profile_id', $setup['student']->id)->count())->toBe(1);

    // Seluruh nilai turun ke Draft → sync harus menghapus baris StudyResult basi.
    $setup['grade']->update(['grade_status' => 'Draft']);
    app(TranscriptSyncService::class)->syncStudent($setup['student']->id);

    expect(StudyResult::where('student_profile_id', $setup['student']->id)->count())->toBe(0)
        ->and(TranscriptEntry::where('student_profile_id', $setup['student']->id)->count())->toBe(0);
});

it('bulk sync memproses banyak mahasiswa', function () {
    $setup = transcriptSetup();
    app(StudentGradeLifecycleService::class)->finalize($setup['grade'], $setup['lecturerUser']->id, $setup['admin']->id);

    transcriptActingAs($setup['admin'])
        ->post(route('admin.academic.transcripts.bulk-sync'), ['ids' => [$setup['student']->id]])
        ->assertRedirect();

    expect(TranscriptEntry::where('student_profile_id', $setup['student']->id)->count())->toBe(1);
});

it('mengekspor xlsx ringkasan transkrip', function () {
    $setup = transcriptSetup();

    transcriptActingAs($setup['admin'])
        ->get(route('admin.academic.transcripts.export', ['format' => 'xlsx']))
        ->assertOk();
});
