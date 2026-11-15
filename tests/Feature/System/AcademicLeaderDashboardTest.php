<?php

use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function academicLeaderSetup(): array
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
    Role::firstOrCreate(['name' => 'academic-leader', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'lecturer', 'guard_name' => 'web']);

    $facultyId = DB::table('faculties')->insertGetId([
        'name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true,
    ]);
    $programId = DB::table('study_programs')->insertGetId([
        'faculty_id' => $facultyId, 'name' => 'Teknik Informatika', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);
    $courseId = DB::table('courses')->insertGetId([
        'code' => 'TI101', 'name' => 'Pemrograman Dasar', 'credits' => 3,
    ]);
    $yearId = DB::table('academic_years')->insertGetId([
        'name' => '2026/2027', 'code' => '2026', 'semester' => 'Ganjil',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'is_active' => true,
    ]);

    $leader = User::factory()->create();
    $leader->assignRole('academic-leader');
    $leaderEmployeeId = DB::table('employee_profiles')->insertGetId([
        'user_id' => $leader->id, 'is_active' => true,
    ]);
    $kaprodiId = DB::table('organizational_positions')->where('code', 'KAPRODI')->value('id');
    DB::table('employee_position_assignments')->insert([
        'employee_profile_id' => $leaderEmployeeId,
        'organizational_position_id' => $kaprodiId,
        'study_program_id' => $programId,
        'is_active' => true,
    ]);

    $lecturer = User::factory()->create();
    $lecturer->assignRole('lecturer');
    $lecturerProfileId = DB::table('lecturer_profiles')->insertGetId([
        'user_id' => $lecturer->id, 'study_program_id' => $programId, 'nidn' => '0404040404',
    ]);

    $offeringId = DB::table('course_offerings')->insertGetId([
        'academic_year_id' => $yearId, 'study_program_id' => $programId,
        'course_id' => $courseId, 'label' => 'A',
    ]);
    DB::table('course_offering_lecturers')->insert([
        'course_offering_id' => $offeringId, 'lecturer_profile_id' => $lecturerProfileId,
        'role' => 'Primary', 'is_active' => true,
    ]);
    // Kelas tanpa dosen → alert K1.
    DB::table('course_offerings')->insert([
        'academic_year_id' => $yearId, 'study_program_id' => $programId,
        'course_id' => $courseId, 'label' => 'B',
    ]);

    return compact('leader', 'lecturer', 'lecturerProfileId', 'programId', 'offeringId');
}

function academicLeaderWorkload(array $setup): array
{
    $periodId = DB::table('lecturer_workload_periods')->insertGetId([
        'name' => 'BKD 2026', 'code' => 'BKD2026', 'status' => 'open',
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
    ]);
    $submissionId = DB::table('lecturer_workload_submissions')->insertGetId([
        'lecturer_workload_period_id' => $periodId,
        'user_id' => $setup['lecturer']->id,
        'lecturer_profile_id' => $setup['lecturerProfileId'],
        'status' => 'in_approval',
        'total_sks' => 14,
        'submitted_at' => now(),
    ]);
    $templateId = DB::table('approval_templates')->insertGetId([
        'name' => 'Review BKD', 'code' => 'LECTURER_WORKLOAD_REVIEW',
        'module' => 'organization', 'is_active' => true,
    ]);
    $requestId = DB::table('approval_requests')->insertGetId([
        'approval_template_id' => $templateId,
        'approvable_type' => LecturerWorkloadSubmission::class,
        'approvable_id' => $submissionId,
        'requester_user_id' => $setup['lecturer']->id,
        'subject' => 'Pengajuan BKD',
        'reference' => 'BKD-1',
        'status' => 'in_progress',
        'current_step_order' => 1,
        'submitted_at' => now(),
    ]);
    DB::table('lecturer_workload_submissions')->where('id', $submissionId)->update([
        'approval_request_id' => $requestId,
    ]);
    $kaprodiId = DB::table('organizational_positions')->where('code', 'KAPRODI')->value('id');
    $stepId = DB::table('approval_steps')->insertGetId([
        'approval_request_id' => $requestId,
        'step_order' => 1,
        'name' => 'Review Kaprodi',
        'approver_type' => 'position',
        'organizational_position_id' => $kaprodiId,
        'status' => 'current',
        'is_required' => true,
        'can_reject' => true,
        'due_at' => now()->addDay(),
    ]);

    return compact('submissionId', 'requestId', 'stepId');
}

it('redirects guests away from the leader dashboard to login', function () {
    academicLeaderSetup();

    $this->get('/academic-leader/dashboard')->assertRedirect('/auth/login');
});

it('renders the no-scope warning without an active position', function () {
    academicLeaderSetup();

    $plain = User::factory()->create();
    $plain->assignRole('academic-leader');

    $this->actingAs($plain)
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Dashboard')
            ->where('hasScope', false)
            ->has('pendingApprovals', 0));
});

it('shows in-approval submissions at other steps as waiting on others', function () {
    $setup = academicLeaderSetup();

    // Pengajuan kedua: tahap current bertipe permission yang tidak dimiliki kaprodi.
    $periodId = DB::table('lecturer_workload_periods')->insertGetId([
        'name' => 'BKD 2026 B', 'code' => 'BKD2026B', 'status' => 'open',
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
    ]);
    $submissionId = DB::table('lecturer_workload_submissions')->insertGetId([
        'lecturer_workload_period_id' => $periodId,
        'user_id' => $setup['lecturer']->id,
        'lecturer_profile_id' => $setup['lecturerProfileId'],
        'status' => 'in_approval',
        'total_sks' => 12,
        'submitted_at' => now(),
    ]);
    $templateId = DB::table('approval_templates')->insertGetId([
        'name' => 'Review Khusus', 'code' => 'REVIEW_KHUSUS',
        'module' => 'organization', 'is_active' => true,
    ]);
    $requestId = DB::table('approval_requests')->insertGetId([
        'approval_template_id' => $templateId,
        'approvable_type' => LecturerWorkloadSubmission::class,
        'approvable_id' => $submissionId,
        'requester_user_id' => $setup['lecturer']->id,
        'subject' => 'Pengajuan BKD Khusus',
        'reference' => 'BKD-2',
        'status' => 'in_progress',
        'current_step_order' => 1,
        'submitted_at' => now(),
    ]);
    DB::table('lecturer_workload_submissions')->where('id', $submissionId)->update([
        'approval_request_id' => $requestId,
    ]);
    DB::table('approval_steps')->insert([
        'approval_request_id' => $requestId,
        'step_order' => 1,
        'name' => 'Verifikasi Berkas',
        'approver_type' => 'permission',
        'approver_permission' => 'berkas.verify',
        'status' => 'current',
        'is_required' => true,
        'can_reject' => true,
    ]);
    Permission::firstOrCreate(['name' => 'berkas.verify', 'guard_name' => 'web']);

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Dashboard')
            ->has('pendingApprovals', 0)
            ->has('waitingOnOthers', 1)
            ->where('waitingOnOthers.0.stepName', 'Verifikasi Berkas'));
});

it('renders the decision cockpit with scoped data', function () {
    $setup = academicLeaderSetup();
    academicLeaderWorkload($setup);

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Dashboard')
            ->where('hasScope', true)
            ->has('pendingApprovals', 1)
            ->has('problemClasses')
            ->where('stats.bkdApproval', 1)
            ->where('stats.classes', 2));
});

it('approves a workload step as kaprodi', function () {
    $setup = academicLeaderSetup();
    $ids = academicLeaderWorkload($setup);

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->post(route('academic-leader.approvals.steps.approve', $ids['stepId']), ['notes' => 'Sesuai.'])
        ->assertRedirect(route('academic-leader.dashboard.index'))
        ->assertSessionHas('success');

    expect(LecturerWorkloadSubmission::find($ids['submissionId'])->status)->toBe('approved');
    expect(DB::table('approval_steps')->where('id', $ids['stepId'])->value('status'))->toBe('approved');
});

it('rejects a workload step with mandatory notes', function () {
    $setup = academicLeaderSetup();
    $ids = academicLeaderWorkload($setup);

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->post(route('academic-leader.approvals.steps.reject', $ids['stepId']), [])
        ->assertSessionHasErrors('notes');

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->post(route('academic-leader.approvals.steps.reject', $ids['stepId']), ['notes' => 'SKS kurang.'])
        ->assertRedirect(route('academic-leader.dashboard.index'));

    expect(LecturerWorkloadSubmission::find($ids['submissionId'])->status)->toBe('rejected');
});

it('forbids approval decisions from leaders outside the scope', function () {
    $setup = academicLeaderSetup();
    $ids = academicLeaderWorkload($setup);

    $outsider = User::factory()->create();
    $outsider->assignRole('academic-leader');

    $this->actingAs($outsider)
        ->withSession(['active_role' => 'academic-leader'])
        ->post(route('academic-leader.approvals.steps.approve', $ids['stepId']))
        ->assertForbidden();

    expect(LecturerWorkloadSubmission::find($ids['submissionId'])->status)->toBe('in_approval');
});
