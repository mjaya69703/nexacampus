<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Menyiapkan satu mahasiswa dengan KRS, nilai, banding nilai, hasil studi,
 * dan transkrip agar seluruh halaman rekap akademik bisa diuji dari data
 * yang sama.
 *
 * @return array<string, mixed>
 */
function studentAcademicRecordsSetup(): array
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
        'name' => 'Fakultas Teknik', 'code' => 'FT-AR', 'is_active' => true,
    ]);
    $programId = DB::table('study_programs')->insertGetId([
        'faculty_id' => $facultyId, 'name' => 'Teknik Informatika', 'code' => 'TI-AR',
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
        'name' => 'Registrasi Semester Ganjil 2026/2027',
        'code' => 'REG-AR',
        'type' => 'Student Registration',
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'is_active' => true,
    ]);

    $user = User::factory()->create(['first_name' => 'Maya', 'last_name' => 'Akademik']);
    $user->assignRole('student');

    $profileId = DB::table('student_profiles')->insertGetId([
        'user_id' => $user->id,
        'study_program_id' => $programId,
        'nim' => '2026TI0002',
        'academic_status' => 'Aktif',
        'entry_date' => now()->subYears(1)->toDateString(),
        'current_semester' => 3,
        'is_active' => true,
    ]);

    $courseAId = DB::table('courses')->insertGetId([
        'code' => 'TI201', 'name' => 'Struktur Data', 'credits' => 3,
        'semester_recommendation' => 3,
    ]);
    $courseBId = DB::table('courses')->insertGetId([
        'code' => 'TI202', 'name' => 'Basis Data', 'credits' => 3,
        'semester_recommendation' => 3,
    ]);
    $courseCId = DB::table('courses')->insertGetId([
        'code' => 'TI203', 'name' => 'Jaringan Komputer', 'credits' => 3,
        'semester_recommendation' => 3,
    ]);

    $offeringAId = DB::table('course_offerings')->insertGetId([
        'academic_year_id' => $yearId, 'study_program_id' => $programId,
        'course_id' => $courseAId, 'label' => 'A', 'code' => 'TI201-A',
        'semester_no' => 3, 'credits' => 3, 'status' => 'Open',
    ]);
    $offeringBId = DB::table('course_offerings')->insertGetId([
        'academic_year_id' => $yearId, 'study_program_id' => $programId,
        'course_id' => $courseBId, 'label' => 'A', 'code' => 'TI202-A',
        'semester_no' => 3, 'credits' => 3, 'status' => 'Open',
    ]);
    $offeringCId = DB::table('course_offerings')->insertGetId([
        'academic_year_id' => $yearId, 'study_program_id' => $programId,
        'course_id' => $courseCId, 'label' => 'A', 'code' => 'TI203-A',
        'semester_no' => 3, 'credits' => 3, 'status' => 'Open',
    ]);

    $planId = DB::table('study_plans')->insertGetId([
        'student_profile_id' => $profileId,
        'academic_year_id' => $yearId,
        'semester_no' => 3,
        'status' => 'Approved',
        'submitted_at' => now()->subDays(5),
        'approved_at' => now()->subDays(4),
    ]);

    $detailAId = DB::table('study_plan_details')->insertGetId([
        'study_plan_id' => $planId, 'course_offering_id' => $offeringAId,
        'credits' => 3, 'status' => 'Taken',
    ]);
    $detailBId = DB::table('study_plan_details')->insertGetId([
        'study_plan_id' => $planId, 'course_offering_id' => $offeringBId,
        'credits' => 3, 'status' => 'Taken',
    ]);
    $detailDroppedId = DB::table('study_plan_details')->insertGetId([
        'study_plan_id' => $planId, 'course_offering_id' => $offeringCId,
        'credits' => 3, 'status' => 'Dropped',
    ]);

    $gradeAId = DB::table('student_grades')->insertGetId([
        'study_plan_detail_id' => $detailAId,
        'final_score' => 88.00, 'letter_grade' => 'A', 'grade_point' => 4.00,
        'result_status' => 'Passed', 'grade_status' => 'Published',
        'graded_at' => now(), 'graded_by' => $user->id,
    ]);
    $gradeBId = DB::table('student_grades')->insertGetId([
        'study_plan_detail_id' => $detailBId,
        'final_score' => 65.00, 'letter_grade' => 'D', 'grade_point' => 1.00,
        'result_status' => 'Failed', 'grade_status' => 'Published',
        'graded_at' => now()->subDay(), 'graded_by' => $user->id,
    ]);
    DB::table('student_grades')->insert([
        'study_plan_detail_id' => $detailDroppedId,
        'final_score' => 50.00, 'letter_grade' => 'E', 'grade_point' => 0.00,
        'result_status' => 'Failed', 'grade_status' => 'Draft',
        'graded_at' => null,
    ]);

    DB::table('student_grade_components')->insert([
        [
            'student_grade_id' => $gradeAId, 'name' => 'Tugas', 'weight_percentage' => 30,
            'score' => 90, 'sort_order' => 1,
        ],
        [
            'student_grade_id' => $gradeAId, 'name' => 'Ujian Akhir', 'weight_percentage' => 70,
            'score' => 87.14, 'sort_order' => 2,
        ],
    ]);

    DB::table('grade_appeals')->insert([
        'student_grade_id' => $gradeBId,
        'student_profile_id' => $profileId,
        'course_offering_id' => $offeringBId,
        'student_user_id' => $user->id,
        'status' => 'submitted',
        'reason_category' => 'Scoring Error',
        'reason' => 'Nilai ujian akhir tidak sesuai rekap.',
        'submitted_at' => now(),
    ]);

    DB::table('study_results')->insert([
        'student_profile_id' => $profileId,
        'academic_year_id' => $yearId,
        'study_plan_id' => $planId,
        'semester_no' => 3,
        'total_courses' => 2,
        'total_credits_taken' => 6,
        'total_credits_passed' => 3,
        'semester_gpa' => 3.10,
        'cumulative_gpa' => 3.25,
        'status' => 'Published',
        'finalized_at' => now(),
        'published_at' => now(),
    ]);

    DB::table('transcript_entries')->insert([
        [
            'student_profile_id' => $profileId,
            'course_id' => $courseAId,
            'student_grade_id' => $gradeAId,
            'academic_year_id' => $yearId,
            'semester_no' => 3,
            'credits' => 3,
            'final_score' => 88.00,
            'letter_grade' => 'A',
            'grade_point' => 4.00,
            'result_status' => 'Passed',
            'is_counted_in_gpa' => true,
            'is_best_grade' => true,
        ],
        [
            'student_profile_id' => $profileId,
            'course_id' => $courseBId,
            'student_grade_id' => $gradeBId,
            'academic_year_id' => $yearId,
            'semester_no' => 3,
            'credits' => 3,
            'final_score' => 65.00,
            'letter_grade' => 'D',
            'grade_point' => 1.00,
            'result_status' => 'Failed',
            'is_counted_in_gpa' => true,
            'is_best_grade' => true,
        ],
    ]);

    return compact(
        'user',
        'profileId',
        'programId',
        'yearId',
        'periodId',
        'courseAId',
        'courseBId',
        'offeringBId',
        'planId',
        'detailAId',
        'detailBId',
        'gradeAId',
        'gradeBId',
    );
}

it('menampilkan halaman registrasi dengan periode aktif', function () {
    $setup = studentAcademicRecordsSetup();

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.registration.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Registration/Index')
            ->where('hasProfile', true)
            ->where('hasActiveYear', true)
            ->where('student.nim', '2026TI0002')
            ->where('period.canRegister', true)
            ->where('year.name', '2026/2027')
            ->where('current', null)
            ->has('history', 0));
});

it('menyimpan draf lalu mengajukan registrasi semester', function () {
    $setup = studentAcademicRecordsSetup();

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->post(route('student.registration.draft'), [
            'semester_no' => 3,
            'academic_status' => 'Aktif',
            'notes' => 'Draf awal',
        ])
        ->assertRedirect(route('student.registration.index'));

    $this->assertDatabaseHas('student_registrations', [
        'student_profile_id' => $setup['profileId'],
        'academic_year_id' => $setup['yearId'],
        'registration_status' => 'Draft',
        'submitted_at' => null,
        'notes' => 'Draf awal',
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->post(route('student.registration.submit'), [
            'semester_no' => 3,
            'academic_status' => 'Aktif',
            'notes' => 'Mohon disetujui',
        ])
        ->assertRedirect(route('student.registration.index'));

    $registration = DB::table('student_registrations')
        ->where('student_profile_id', $setup['profileId'])
        ->first();

    expect($registration->registration_status)->toBe('Submitted');
    expect($registration->submitted_at)->not->toBeNull();
});

it('menolak pengajuan saat periode registrasi tidak aktif tanpa menghapus draf', function () {
    $setup = studentAcademicRecordsSetup();

    DB::table('student_registrations')->insert([
        'student_profile_id' => $setup['profileId'],
        'academic_year_id' => $setup['yearId'],
        'semester_no' => 3,
        'registration_status' => 'Draft',
        'academic_status' => 'Aktif',
        'is_active' => true,
    ]);
    DB::table('academic_periods')->where('id', $setup['periodId'])->update(['is_active' => false]);

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->from(route('student.registration.index'))
        ->post(route('student.registration.submit'), [
            'semester_no' => 3,
            'academic_status' => 'Aktif',
        ])
        ->assertRedirect(route('student.registration.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('student_registrations', [
        'student_profile_id' => $setup['profileId'],
        'registration_status' => 'Draft',
    ]);
});

it('mengizinkan registrasi baru setelah registrasi sebelumnya dibatalkan', function () {
    $setup = studentAcademicRecordsSetup();

    $registrationId = DB::table('student_registrations')->insertGetId([
        'student_profile_id' => $setup['profileId'],
        'academic_year_id' => $setup['yearId'],
        'semester_no' => 3,
        'registration_status' => 'Submitted',
        'academic_status' => 'Aktif',
        'submitted_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->post(route('student.registration.cancel'))
        ->assertRedirect(route('student.registration.index'));

    $this->assertDatabaseHas('student_registrations', [
        'id' => $registrationId,
        'registration_status' => 'Cancelled',
        'is_active' => false,
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.registration.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current', null)
            ->where('period.canRegister', true));

    // Baris Cancelled menempati slot unik; pengajuan baru harus memakai ulang
    // baris tersebut, bukan membuat baris kedua yang melanggar constraint.
    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->post(route('student.registration.submit'), [
            'semester_no' => 4,
            'academic_status' => 'Aktif',
        ])
        ->assertRedirect(route('student.registration.index'));

    expect(DB::table('student_registrations')->where('student_profile_id', $setup['profileId'])->count())->toBe(1);

    $this->assertDatabaseHas('student_registrations', [
        'id' => $registrationId,
        'semester_no' => 4,
        'registration_status' => 'Submitted',
        'is_active' => true,
    ]);
});

it('meringkas progres akademik dan mengabaikan kredit yang dibatalkan', function () {
    $setup = studentAcademicRecordsSetup();

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.progress.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Progress/Index')
            ->where('hasProfile', true)
            ->where('progress.credits.current', 6)
            ->where('progress.credits.current_status', 'Approved')
            ->where('progress.gpa.cumulative', 3.25)
            ->where('progress.attendance.total', 0)
            ->has('progress.trend', 1)
            ->has('progress.failed_courses', 1)
            ->where('progress.failed_courses.0.code', 'TI202'));
});

it('memakai IPK berbobot transkrip saat hasil studi belum tersedia', function () {
    $setup = studentAcademicRecordsSetup();

    DB::table('study_results')->where('student_profile_id', $setup['profileId'])->delete();

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.progress.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('progress.gpa.cumulative', 2.5));

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.transcript.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Transcript/Index')
            ->where('summary.cumulativeGpa', 2.5));
});

it('menampilkan nilai terpublikasi beserta komponen dan banding nilai', function () {
    $setup = studentAcademicRecordsSetup();

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.grades.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Grades/Index')
            ->where('hasProfile', true)
            ->has('rows', 2)
            ->where('rows.0.courseCode', 'TI201')
            ->where('rows.0.letterGrade', 'A')
            ->where('rows.0.totalWeight', 100)
            ->has('rows.0.components', 2)
            ->where('rows.1.courseCode', 'TI202')
            ->where('rows.1.appealStatus', 'submitted')
            ->where('rows.1.hasActiveAppeal', true)
            ->where('stats.courses', 2)
            ->where('stats.credits', 6)
            ->where('stats.avgPoint', 2.5)
            ->where('stats.activeAppeals', 1));
});

it('meringkas transkrip dan memakai IPK hasil studi terbaru', function () {
    $setup = studentAcademicRecordsSetup();

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.transcript.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Transcript/Index')
            ->where('hasProfile', true)
            ->where('student.nim', '2026TI0002')
            ->where('summary.totalCourses', 2)
            ->where('summary.totalCredits', 6)
            ->where('summary.passedCredits', 3)
            ->where('summary.cumulativeGpa', 3.25)
            ->has('results', 1)
            ->has('entries', 2));
});

it('menampilkan keadaan kosong saat profil mahasiswa belum tersedia', function (string $routeName, string $component) {
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

    $user = User::factory()->create();
    $user->assignRole('student');

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('hasProfile', false));
})->with([
    'registrasi' => ['student.registration.index', 'Student/Registration/Index'],
    'progres' => ['student.progress.index', 'Student/Progress/Index'],
    'nilai' => ['student.grades.index', 'Student/Grades/Index'],
    'transkrip' => ['student.transcript.index', 'Student/Transcript/Index'],
]);
