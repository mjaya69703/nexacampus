<?php

use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyProgram;
use App\Support\AcademicAdvisorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function advisorApprovalSetup(): array
{
    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $faculty = Faculty::create(['name' => 'FT', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'TI', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);
    $year = AcademicYear::create([
        'name' => '2026/2027', 'code' => 'Y26', 'semester' => 'Ganjil',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
    ]);

    $lecturerUser = App\Models\User::factory()->create();
    $lecturer = LecturerProfile::create([
        'user_id' => $lecturerUser->id, 'nidn' => '1001',
        'employment_status' => 'Tetap', 'is_active' => true,
    ]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => '2026TI0001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);

    $assignment = AcademicAdvisorAssignment::create([
        'student_profile_id' => $student->id,
        'lecturer_profile_id' => $lecturer->id,
        'academic_year_id' => $year->id,
        'is_active' => true,
    ]);

    $plan = StudyPlan::create([
        'student_profile_id' => $student->id,
        'academic_year_id' => $year->id,
        'semester_no' => 1,
        'status' => 'Submitted',
        'submitted_at' => now(),
    ]);

    return compact('lecturer', 'lecturerUser', 'student', 'assignment', 'plan', 'year');
}

it('memperbolehkan DPA menyetujui KRS mahasiswa bimbingannya', function () {
    $setup = advisorApprovalSetup();

    $result = app(AcademicAdvisorService::class)->approveStudyPlanAsAdvisor(
        $setup['lecturer']->id, $setup['plan'], 'Silakan lanjut.', $setup['lecturerUser']->id
    );

    expect($result->status)->toBe('Approved')
        ->and($result->approved_by)->toBe($setup['lecturerUser']->id)
        ->and($result->approved_at)->not->toBeNull()
        ->and($result->notes)->toBe('Silakan lanjut.');
});

it('menolak dosen yang bukan DPA mahasiswa tersebut', function () {
    $setup = advisorApprovalSetup();

    $strangerUser = App\Models\User::factory()->create();
    $stranger = LecturerProfile::create([
        'user_id' => $strangerUser->id, 'nidn' => '9999',
        'employment_status' => 'Tetap', 'is_active' => true,
    ]);

    expect(fn () => app(AcademicAdvisorService::class)->approveStudyPlanAsAdvisor(
        $stranger->id, $setup['plan'], null, $strangerUser->id
    ))->toThrow(ValidationException::class);

    expect($setup['plan']->fresh()->status)->toBe('Submitted');
});

it('menolak keputusan atas KRS yang tidak berstatus Diajukan', function () {
    $setup = advisorApprovalSetup();
    $setup['plan']->update(['status' => 'Draft']);

    expect(fn () => app(AcademicAdvisorService::class)->approveStudyPlanAsAdvisor(
        $setup['lecturer']->id, $setup['plan']->fresh(), null, $setup['lecturerUser']->id
    ))->toThrow(ValidationException::class);
});

it('mengakui penugasan umum untuk semua tahun dan bisa menolak', function () {
    $setup = advisorApprovalSetup();
    $setup['assignment']->update(['academic_year_id' => null]);

    $result = app(AcademicAdvisorService::class)->rejectStudyPlanAsAdvisor(
        $setup['lecturer']->id, $setup['plan'], 'Kurangi SKS.', $setup['lecturerUser']->id
    );

    expect($result->status)->toBe('Rejected')
        ->and($result->approved_at)->toBeNull()
        ->and($result->notes)->toBe('Kurangi SKS.');
});

it('menampilkan KRS menunggu di halaman bimbingan dosen', function () {
    $setup = advisorApprovalSetup();

    Spatie\Permission\Models\Role::firstOrCreate(['name' => 'lecturer', 'guard_name' => 'web']);
    $setup['lecturerUser']->assignRole('lecturer');

    $html = $this->actingAs($setup['lecturerUser'])
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.academic-advising.show', ['assignmentId' => $setup['assignment']->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('KRS Menunggu Persetujuan');
});
