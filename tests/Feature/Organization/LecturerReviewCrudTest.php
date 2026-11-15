<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\LecturerPerformanceReview;
use App\Models\Organization\LecturerWorkloadItem;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function workloadReviewSetup(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function workloadReviewPermission(string ...$names): void
{
    $role = Role::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
}

function workloadReviewActing(User $user)
{
    return test()->actingAs($user)->withSession(['active_role' => 'admin']);
}

function workloadReviewAcademic(): array
{
    $faculty = Faculty::create(['name' => 'Fakultas Review', 'code' => 'FRV', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Prodi Review',
        'code' => 'PRV',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    return [$faculty, $program];
}

function workloadReviewLecturer(Faculty $faculty, StudyProgram $program, string $nidn = 'REV-001'): array
{
    $user = User::factory()->create();
    $profile = LecturerProfile::create([
        'user_id' => $user->id,
        'faculty_id' => $faculty->id,
        'study_program_id' => $program->id,
        'nidn' => $nidn,
        'is_active' => true,
    ]);

    return [$user, $profile];
}

function workloadReviewPeriod(array $attributes = []): LecturerWorkloadPeriod
{
    return LecturerWorkloadPeriod::create(array_merge([
        'name' => 'BKD Review 2025/2026',
        'code' => 'BKD-REV-'.uniqid(),
        'starts_at' => now()->subWeek()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'open',
        'minimum_sks' => 12,
        'maximum_sks' => 16,
    ], $attributes));
}

function workloadReviewSubmission(LecturerWorkloadPeriod $period, User $user, LecturerProfile $profile, array $attributes = []): LecturerWorkloadSubmission
{
    return LecturerWorkloadSubmission::create(array_merge([
        'lecturer_workload_period_id' => $period->id,
        'user_id' => $user->id,
        'lecturer_profile_id' => $profile->id,
        'status' => 'submitted',
        'teaching_sks' => 12,
        'structural_sks' => 2,
        'tridharma_sks' => 0.5,
        'total_sks' => 14.5,
        'submitted_at' => now(),
    ], $attributes));
}

function workloadReviewEdomPeriod(array $attributes = []): EdomPeriod
{
    return EdomPeriod::create(array_merge([
        'name' => 'EDOM Review 2025/2026',
        'code' => 'EDOM-REV-'.uniqid(),
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
        'status' => 'closed',
        'minimum_responses' => 1,
    ], $attributes));
}

it('menampilkan daftar laporan BKD dengan statistik dan filter status', function () {
    $user = workloadReviewSetup();
    workloadReviewPermission('lecturer-workload-submission.viewAny');

    [$faculty, $program] = workloadReviewAcademic();
    [$firstUser, $firstProfile] = workloadReviewLecturer($faculty, $program, 'REV-001');
    [$secondUser, $secondProfile] = workloadReviewLecturer($faculty, $program, 'REV-002');

    $period = workloadReviewPeriod();

    workloadReviewSubmission($period, $firstUser, $firstProfile, [
        'status' => 'in_approval',
        'total_sks' => 14.5,
    ]);

    workloadReviewSubmission($period, $secondUser, $secondProfile, [
        'status' => 'approved',
        'total_sks' => 16,
    ]);

    workloadReviewActing($user)
        ->get('/admin/organization/lecturer-workload-submissions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/LecturerWorkloadSubmissions/Index')
            ->where('stats.total', 2)
            ->where('stats.inApproval', 1)
            ->where('stats.approved', 1)
            ->where('stats.averageSks', 15.3)
            ->has('data.rows', 2)
            ->has('periods', 1));

    workloadReviewActing($user)
        ->get('/admin/organization/lecturer-workload-submissions?status=approved')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.total', 1)
            ->where('data.rows.0.status', 'approved'));

    workloadReviewActing($user)
        ->get('/admin/organization/lecturer-workload-submissions?q='.urlencode($firstUser->email))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('data.total', 1));
});

it('menampilkan detail laporan BKD dan mengembalikannya untuk revisi', function () {
    $user = workloadReviewSetup();
    workloadReviewPermission('lecturer-workload-submission.view', 'lecturer-workload-submission.update');

    [$faculty, $program] = workloadReviewAcademic();
    [$lecturer, $profile] = workloadReviewLecturer($faculty, $program);

    $period = workloadReviewPeriod();
    $submission = workloadReviewSubmission($period, $lecturer, $profile);

    LecturerWorkloadItem::create([
        'lecturer_workload_submission_id' => $submission->id,
        'category' => 'teaching',
        'source_code' => 'TEACH-01',
        'title' => 'Pengajaran Mata Kuliah Pemrograman',
        'sks' => 12,
    ]);

    workloadReviewActing($user)
        ->get("/admin/organization/lecturer-workload-submissions/{$submission->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/LecturerWorkloadSubmissions/Show')
            ->where('submission.status', 'submitted')
            ->where('submission.canRequestRevision', true)
            ->has('items', 1));

    workloadReviewActing($user)
        ->from("/admin/organization/lecturer-workload-submissions/{$submission->id}")
        ->post("/admin/organization/lecturer-workload-submissions/{$submission->id}/request-revision", [
            'review_notes' => 'Lengkapi bukti pengajaran.',
        ])
        ->assertRedirect("/admin/organization/lecturer-workload-submissions/{$submission->id}")
        ->assertSessionHas('success');

    $submission->refresh();

    expect($submission->status)->toBe('revision')
        ->and($submission->review_notes)->toBe('Lengkapi bukti pengajaran.');

    workloadReviewActing($user)
        ->from("/admin/organization/lecturer-workload-submissions/{$submission->id}")
        ->post("/admin/organization/lecturer-workload-submissions/{$submission->id}/request-revision", [
            'review_notes' => 'Ulangi.',
        ])
        ->assertSessionHas('error');

    $closedPeriod = workloadReviewPeriod([
        'name' => 'BKD Ditutup',
        'code' => 'BKD-CLOSED-'.uniqid(),
        'status' => 'closed',
    ]);
    $closedSubmission = workloadReviewSubmission($closedPeriod, $lecturer, $profile);

    workloadReviewActing($user)
        ->from("/admin/organization/lecturer-workload-submissions/{$closedSubmission->id}")
        ->post("/admin/organization/lecturer-workload-submissions/{$closedSubmission->id}/request-revision", [
            'review_notes' => 'Tidak boleh.',
        ])
        ->assertSessionHas('error');

    expect($closedSubmission->refresh()->status)->toBe('submitted');
});

it('menjaga hapus laporan BKD sesuai status dan menghapus rincian item', function () {
    $user = workloadReviewSetup();
    workloadReviewPermission('lecturer-workload-submission.delete');

    [$faculty, $program] = workloadReviewAcademic();
    [$lecturer, $profile] = workloadReviewLecturer($faculty, $program);
    $period = workloadReviewPeriod();

    $draft = workloadReviewSubmission($period, $lecturer, $profile, ['status' => 'draft']);
    LecturerWorkloadItem::create([
        'lecturer_workload_submission_id' => $draft->id,
        'category' => 'teaching',
        'title' => 'Butir Draf',
        'sks' => 3,
    ]);

    workloadReviewActing($user)
        ->delete("/admin/organization/lecturer-workload-submissions/{$draft->id}")
        ->assertRedirect('/admin/organization/lecturer-workload-submissions')
        ->assertSessionHas('success');

    expect(LecturerWorkloadSubmission::find($draft->id))->toBeNull()
        ->and(LecturerWorkloadItem::where('lecturer_workload_submission_id', $draft->id)->count())->toBe(0);

    $approved = workloadReviewSubmission($period, $lecturer, $profile, ['status' => 'approved', 'total_sks' => 14]);

    workloadReviewActing($user)
        ->from('/admin/organization/lecturer-workload-submissions')
        ->delete("/admin/organization/lecturer-workload-submissions/{$approved->id}")
        ->assertSessionHas('error');

    expect(LecturerWorkloadSubmission::find($approved->id))->not->toBeNull();
});

it('menampilkan daftar review performa dosen dengan statistik', function () {
    $user = workloadReviewSetup();
    workloadReviewPermission('lecturer-performance-review.viewAny');

    [$faculty, $program] = workloadReviewAcademic();
    [$firstUser, $firstProfile] = workloadReviewLecturer($faculty, $program, 'PERF-001');
    [$secondUser, $secondProfile] = workloadReviewLecturer($faculty, $program, 'PERF-002');

    $period = workloadReviewEdomPeriod();

    LecturerPerformanceReview::create([
        'edom_period_id' => $period->id,
        'user_id' => $firstUser->id,
        'lecturer_profile_id' => $firstProfile->id,
        'edom_score' => 4.5,
        'edom_response_count' => 12,
        'final_score' => 85.5,
        'status' => 'calculated',
        'calculated_at' => now(),
    ]);

    LecturerPerformanceReview::create([
        'edom_period_id' => $period->id,
        'user_id' => $secondUser->id,
        'lecturer_profile_id' => $secondProfile->id,
        'edom_score' => 4.8,
        'edom_response_count' => 15,
        'final_score' => 90,
        'status' => 'published',
        'calculated_at' => now(),
        'published_at' => now(),
    ]);

    workloadReviewActing($user)
        ->get('/admin/organization/lecturer-performance-reviews')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/LecturerPerformanceReviews/Index')
            ->where('stats.total', 2)
            ->where('stats.published', 1)
            ->where('stats.averageScore', 87.8)
            ->has('data.rows', 2)
            ->has('periods', 1));

    workloadReviewActing($user)
        ->get('/admin/organization/lecturer-performance-reviews?status=published')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.total', 1)
            ->where('data.rows.0.status', 'published'));
});

it('menghitung ulang performa dosen, mempublikasikan, dan menjaga hapus review', function () {
    $user = workloadReviewSetup();
    workloadReviewPermission(
        'lecturer-performance-review.view',
        'lecturer-performance-review.update',
        'lecturer-performance-review.delete',
    );

    [$faculty, $program] = workloadReviewAcademic();
    [$lecturer, $profile] = workloadReviewLecturer($faculty, $program);

    $period = workloadReviewEdomPeriod();

    workloadReviewActing($user)
        ->from('/admin/organization/lecturer-performance-reviews')
        ->post('/admin/organization/lecturer-performance-reviews/calculate-all')
        ->assertSessionHas('success');

    $review = LecturerPerformanceReview::where('user_id', $lecturer->id)
        ->where('edom_period_id', $period->id)
        ->firstOrFail();

    expect($review->status)->toBe('calculated');

    workloadReviewActing($user)
        ->get("/admin/organization/lecturer-performance-reviews/{$review->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/LecturerPerformanceReviews/Show')
            ->where('review.status', 'calculated')
            ->where('review.canPublish', true));

    workloadReviewActing($user)
        ->from("/admin/organization/lecturer-performance-reviews/{$review->id}")
        ->post("/admin/organization/lecturer-performance-reviews/{$review->id}/publish")
        ->assertSessionHas('success');

    $review->refresh();

    expect($review->status)->toBe('published')
        ->and($review->published_at)->not->toBeNull();

    workloadReviewActing($user)
        ->from('/admin/organization/lecturer-performance-reviews')
        ->delete("/admin/organization/lecturer-performance-reviews/{$review->id}")
        ->assertSessionHas('error');

    expect(LecturerPerformanceReview::find($review->id))->not->toBeNull();

    $draftReview = LecturerPerformanceReview::create([
        'edom_period_id' => $period->id,
        'user_id' => $lecturer->id,
        'lecturer_profile_id' => $profile->id,
        'status' => 'calculated',
        'final_score' => 80,
        'calculated_at' => now(),
    ]);

    workloadReviewActing($user)
        ->delete("/admin/organization/lecturer-performance-reviews/{$draftReview->id}")
        ->assertRedirect('/admin/organization/lecturer-performance-reviews')
        ->assertSessionHas('success');

    expect(LecturerPerformanceReview::find($draftReview->id))->toBeNull();});
