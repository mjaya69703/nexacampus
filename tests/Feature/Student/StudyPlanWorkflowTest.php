<?php

use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Menyiapkan satu mahasiswa aktif dengan registrasi Approved, periode KRS
 * terbuka, tiga kelas semester 3, dan satu mata kuliah gagal semester 1
 * untuk skenario retake.
 *
 * @return array<string, mixed>
 */
function studyPlanWorkflowSetup(): array
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
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $facultyId = DB::table('faculties')->insertGetId([
        'name' => 'Fakultas Teknik', 'code' => 'FT-KRS', 'is_active' => true,
    ]);
    $programId = DB::table('study_programs')->insertGetId([
        'faculty_id' => $facultyId, 'name' => 'Teknik Informatika', 'code' => 'TI-KRS',
        'degree' => 'S1', 'is_active' => true,
    ]);
    $yearId = DB::table('academic_years')->insertGetId([
        'name' => '2026/2027', 'code' => '2026', 'semester' => 'Ganjil',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'is_active' => true,
    ]);
    $periodId = DB::table('academic_periods')->insertGetId([
        'academic_year_id' => $yearId,
        'name' => 'Pengisian KRS Ganjil 2026/2027',
        'code' => 'KRS-WF',
        'type' => 'Study Plan',
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'is_active' => true,
    ]);

    $user = User::factory()->create(['first_name' => 'Raka', 'last_name' => 'Prasetya']);
    $user->assignRole('student');

    $profileId = DB::table('student_profiles')->insertGetId([
        'user_id' => $user->id,
        'study_program_id' => $programId,
        'nim' => '2026TI0100',
        'academic_status' => 'Aktif',
        'entry_date' => now()->subYears(1)->toDateString(),
        'current_semester' => 3,
        'is_active' => true,
    ]);

    DB::table('student_registrations')->insert([
        'student_profile_id' => $profileId,
        'academic_year_id' => $yearId,
        'semester_no' => 3,
        'registration_status' => 'Approved',
        'academic_status' => 'Aktif',
        'registration_date' => now()->subDays(5)->toDateString(),
        'submitted_at' => now()->subDays(4),
        'approved_at' => now()->subDays(3),
        'is_active' => true,
    ]);

    $offeringIds = [];
    $courseIds = [];

    foreach ([
        ['TI301', 'Algoritma Lanjut', 3],
        ['TI302', 'Basis Data Lanjut', 3],
        ['TI303', 'Jaringan Komputer', 3],
    ] as [$code, $name, $credits]) {
        $courseId = DB::table('courses')->insertGetId([
            'code' => $code,
            'name' => $name,
            'credits' => $credits,
            'semester_recommendation' => 3,
            'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan',
        ]);
        $courseIds[] = $courseId;
        $offeringIds[] = DB::table('course_offerings')->insertGetId([
            'academic_year_id' => $yearId,
            'study_program_id' => $programId,
            'course_id' => $courseId,
            'label' => 'A',
            'code' => $code.'-A',
            'semester_no' => 3,
            'capacity' => 40,
            'credits' => $credits,
            'is_required' => true,
            'delivery_mode' => 'Offline',
            'status' => 'Open',
        ]);
    }

    // Mata kuliah semester 1 yang pernah gagal: satu-satunya offering yang
    // boleh muncul saat filter retake diaktifkan.
    $retakeCourseId = DB::table('courses')->insertGetId([
        'code' => 'TI101',
        'name' => 'Dasar Pemrograman',
        'credits' => 3,
        'semester_recommendation' => 1,
        'requirement_type' => 'Wajib',
        'category_type' => 'Keilmuan',
    ]);
    $retakeOfferingId = DB::table('course_offerings')->insertGetId([
        'academic_year_id' => $yearId,
        'study_program_id' => $programId,
        'course_id' => $retakeCourseId,
        'label' => 'A',
        'code' => 'TI101-A',
        'semester_no' => 1,
        'capacity' => 40,
        'credits' => 3,
        'is_required' => true,
        'delivery_mode' => 'Offline',
        'status' => 'Open',
    ]);
    DB::table('transcript_entries')->insert([
        'student_profile_id' => $profileId,
        'course_id' => $retakeCourseId,
        'academic_year_id' => $yearId,
        'semester_no' => 1,
        'credits' => 3,
        'final_score' => 40.00,
        'letter_grade' => 'E',
        'grade_point' => 0.00,
        'result_status' => 'Failed',
    ]);

    return compact(
        'user',
        'profileId',
        'programId',
        'yearId',
        'periodId',
        'courseIds',
        'offeringIds',
        'retakeCourseId',
        'retakeOfferingId',
    );
}

function studyPlanPost(User $user, string $routeName, array $payload = []): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->post(route($routeName), $payload);
}

function studyPlanGet(User $user, string $routeName, array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get(route($routeName, $query));
}

it('menampilkan halaman KRS dengan kelas yang tersedia', function () {
    $setup = studyPlanWorkflowSetup();

    studyPlanGet($setup['user'], 'student.study-plan.index')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/StudyPlan/Index')
            ->where('hasProfile', true)
            ->where('hasActiveYear', true)
            ->where('hasActiveRegistration', true)
            ->where('isRegistrationApproved', true)
            ->where('isAcademicallyActive', true)
            ->where('period.open', true)
            ->where('plan.status', '-')
            ->has('availableOfferings', 3)
            ->has('selectedCourses', 0));
});

it('menambah mata kuliah membuat draf KRS dan menghitung SKS', function () {
    $setup = studyPlanWorkflowSetup();

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['offeringIds'][0],
    ])->assertSessionHas('success');

    $plan = StudyPlan::query()->first();

    expect($plan)->not->toBeNull()
        ->and($plan->status)->toBe('Draft')
        ->and($plan->semester_no)->toBe(3);

    expect(StudyPlanDetail::query()->count())->toBe(1);

    studyPlanGet($setup['user'], 'student.study-plan.index')
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan.totalCredits', 3)
            ->where('plan.status', 'Draft')
            ->where('plan.canSubmit', true)
            ->has('selectedCourses', 1)
            ->where('selectedCourses.0.code', 'TI301'));
});

it('menghapus lalu menambah ulang mata kuliah memulihkan baris yang sama', function () {
    $setup = studyPlanWorkflowSetup();

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['offeringIds'][0],
    ])->assertSessionHas('success');

    $detailId = StudyPlanDetail::query()->value('id');

    test()->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->delete(route('student.study-plan.courses.destroy', $detailId))
        ->assertSessionHas('success');

    expect(StudyPlanDetail::query()->count())->toBe(0)
        ->and(StudyPlanDetail::withTrashed()->count())->toBe(1);

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['offeringIds'][0],
    ])->assertSessionHas('success');

    expect(StudyPlanDetail::query()->count())->toBe(1);
    expect(StudyPlanDetail::withTrashed()->count())->toBe(1);

    $detail = StudyPlanDetail::query()->first();

    expect((int) $detail->id)->toBe((int) $detailId)
        ->and($detail->status)->toBe('Draft')
        ->and($detail->deleted_at)->toBeNull();
});

it('menolak perubahan KRS saat periode belum aktif', function () {
    $setup = studyPlanWorkflowSetup();

    DB::table('academic_periods')->where('id', $setup['periodId'])->update(['is_active' => false]);

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['offeringIds'][0],
    ])->assertSessionHas('error');

    expect(StudyPlanDetail::query()->count())->toBe(0)
        ->and(StudyPlan::query()->count())->toBe(0);

    studyPlanGet($setup['user'], 'student.study-plan.index')
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.open', false)
            ->where('period.changeOpen', false)
            ->where('plan.canModify', false));
});

it('mengajukan KRS lalu mengembalikannya ke draf untuk revisi', function () {
    $setup = studyPlanWorkflowSetup();

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['offeringIds'][0],
    ])->assertSessionHas('success');

    studyPlanPost($setup['user'], 'student.study-plan.submit')->assertSessionHas('success');

    $plan = StudyPlan::query()->first();
    expect($plan->status)->toBe('Submitted')
        ->and($plan->submitted_at)->not->toBeNull();

    // KRS yang sudah diajukan tidak boleh diubah sebelum direvisi.
    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['offeringIds'][1],
    ])->assertSessionHas('error');

    expect(StudyPlanDetail::query()->count())->toBe(1);

    studyPlanPost($setup['user'], 'student.study-plan.revise')->assertSessionHas('success');

    $plan->refresh();
    expect($plan->status)->toBe('Draft')
        ->and($plan->submitted_at)->toBeNull();

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['offeringIds'][1],
    ])->assertSessionHas('success');

    expect(StudyPlanDetail::query()->count())->toBe(2);
});

it('menolak submit KRS tanpa mata kuliah', function () {
    $setup = studyPlanWorkflowSetup();

    studyPlanPost($setup['user'], 'student.study-plan.submit')->assertSessionHas('error');

    expect(StudyPlan::query()->count())->toBe(0);
});

it('menegakkan batas maksimum 24 SKS', function () {
    $setup = studyPlanWorkflowSetup();

    $heavyOfferingIds = [];

    foreach ([['TI401', 'Proyek Perangkat Lunak', 12], ['TI402', 'Kerja Praktik', 12]] as [$code, $name, $credits]) {
        $courseId = DB::table('courses')->insertGetId([
            'code' => $code,
            'name' => $name,
            'credits' => $credits,
            'semester_recommendation' => 3,
            'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan',
        ]);
        $heavyOfferingIds[] = DB::table('course_offerings')->insertGetId([
            'academic_year_id' => $setup['yearId'],
            'study_program_id' => $setup['programId'],
            'course_id' => $courseId,
            'label' => 'A',
            'code' => $code.'-A',
            'semester_no' => 3,
            'capacity' => 40,
            'credits' => $credits,
            'is_required' => true,
            'delivery_mode' => 'Offline',
            'status' => 'Open',
        ]);
    }

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $heavyOfferingIds[0],
    ])->assertSessionHas('success');

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $heavyOfferingIds[1],
    ])->assertSessionHas('success');

    $response = studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['offeringIds'][0],
    ]);

    $response->assertSessionHas('error', fn ($message) => str_contains($message, '24 SKS'));

    expect((int) StudyPlanDetail::query()->sum('credits'))->toBe(24);
});

it('menampilkan dan menerima mata kuliah retake yang pernah gagal', function () {
    $setup = studyPlanWorkflowSetup();

    studyPlanGet($setup['user'], 'student.study-plan.index')
        ->assertInertia(fn (Assert $page) => $page->has('availableOfferings', 3));

    studyPlanGet($setup['user'], 'student.study-plan.index', ['retake' => '1'])
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableOfferings', 4)
            ->where('availableOfferings.0.code', 'TI101')
            ->where('availableOfferings.0.isRetake', true));

    studyPlanPost($setup['user'], 'student.study-plan.courses.store', [
        'course_offering_id' => $setup['retakeOfferingId'],
    ])->assertSessionHas('success');

    $detail = StudyPlanDetail::query()->first();
    expect((bool) $detail->is_repeat)->toBeTrue();
});
