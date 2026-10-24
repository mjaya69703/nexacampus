<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function academicLeaderOversightSetup(): array
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
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $facultyId = DB::table('faculties')->insertGetId([
        'name' => 'Fakultas Teknik', 'code' => 'FT-OV', 'is_active' => true,
    ]);
    $programId = DB::table('study_programs')->insertGetId([
        'faculty_id' => $facultyId, 'name' => 'Teknik Informatika', 'code' => 'TI-OV',
        'degree' => 'S1', 'is_active' => true,
    ]);
    $outsideFacultyId = DB::table('faculties')->insertGetId([
        'name' => 'Fakultas Sastra', 'code' => 'FS-OV', 'is_active' => true,
    ]);
    $outsideProgramId = DB::table('study_programs')->insertGetId([
        'faculty_id' => $outsideFacultyId, 'name' => 'Sastra Indonesia', 'code' => 'SI-OV',
        'degree' => 'S1', 'is_active' => true,
    ]);
    $yearId = DB::table('academic_years')->insertGetId([
        'name' => '2026/2027', 'code' => '2026', 'semester' => 'Ganjil',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'is_active' => true,
    ]);

    $leader = User::factory()->create(['first_name' => 'Pimpinan', 'last_name' => 'Oversight']);
    $leader->assignRole('academic-leader');
    $leaderEmployeeId = DB::table('employee_profiles')->insertGetId([
        'user_id' => $leader->id, 'is_active' => true,
    ]);
    DB::table('employee_position_assignments')->insert([
        'employee_profile_id' => $leaderEmployeeId,
        'organizational_position_id' => DB::table('organizational_positions')->where('code', 'KAPRODI')->value('id'),
        'study_program_id' => $programId,
        'is_active' => true,
    ]);

    $makeLecturer = function (string $first, string $last, string $nidn, int $faculty, int $program): array {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $user->assignRole('lecturer');
        $profileId = DB::table('lecturer_profiles')->insertGetId([
            'user_id' => $user->id,
            'faculty_id' => $faculty,
            'study_program_id' => $program,
            'nidn' => $nidn,
            'is_active' => true,
        ]);

        return [$user, $profileId];
    };

    [$lecturerA, $profileAId] = $makeLecturer('Andi', 'Saputra', '0404040404', $facultyId, $programId);
    [$lecturerB, $profileBId] = $makeLecturer('Budi', 'Hartono', '0505050505', $facultyId, $programId);
    [, $outsideProfileId] = $makeLecturer('Citra', 'Luar', '0606060606', $outsideFacultyId, $outsideProgramId);

    $courseId = DB::table('courses')->insertGetId([
        'code' => 'TI101', 'name' => 'Pemrograman Dasar', 'credits' => 3,
    ]);

    $makeOffering = function (string $label, int $totalMeetings) use ($yearId, $programId, $courseId): int {
        return DB::table('course_offerings')->insertGetId([
            'academic_year_id' => $yearId,
            'study_program_id' => $programId,
            'course_id' => $courseId,
            'label' => $label,
            'code' => 'TI101-'.$label,
            'status' => 'Open',
            'total_meetings' => $totalMeetings,
        ]);
    };

    $offeringAId = $makeOffering('A', 2);
    $offeringBId = $makeOffering('B', 1);
    $offeringCId = $makeOffering('C', 0);

    foreach ([$profileAId, $profileBId] as $profileId) {
        DB::table('course_offering_lecturers')->insert([
            'course_offering_id' => $offeringAId,
            'lecturer_profile_id' => $profileId,
            'role' => 'Primary',
            'is_active' => true,
        ]);
    }
    DB::table('course_offering_lecturers')->insert([
        'course_offering_id' => $offeringBId,
        'lecturer_profile_id' => $profileAId,
        'role' => 'Primary',
        'is_active' => true,
    ]);

    $makeStudent = function (string $first, string $last, string $nim) use ($programId): array {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $user->assignRole('student');
        $profileId = DB::table('student_profiles')->insertGetId([
            'user_id' => $user->id,
            'study_program_id' => $programId,
            'nim' => $nim,
            'entry_year' => 2026,
            'is_active' => true,
        ]);

        return [$user, $profileId];
    };

    [, $studentProfile1] = $makeStudent('Siti', 'Aminah', '20260001');
    [, $studentProfile2] = $makeStudent('Rudi', 'Pratama', '20260002');

    foreach ([$studentProfile1, $studentProfile2] as $studentProfile) {
        $studyPlanId = DB::table('study_plans')->insertGetId([
            'student_profile_id' => $studentProfile,
            'academic_year_id' => $yearId,
            'semester_no' => 1,
            'status' => 'Approved',
        ]);
        DB::table('study_plan_details')->insert([
            'study_plan_id' => $studyPlanId,
            'course_offering_id' => $offeringAId,
            'credits' => 3,
            'status' => 'Taken',
        ]);
    }

    $closedSessionId = DB::table('attendance_sessions')->insertGetId([
        'course_offering_id' => $offeringAId,
        'lecturer_profile_id' => $profileAId,
        'meeting_no' => 1,
        'meeting_date' => now()->subDays(2)->toDateString(),
        'status' => 'Closed',
        'opened_at' => now()->subDays(2)->subHour(),
        'closed_at' => now()->subDays(2),
    ]);
    $cancelledSessionId = DB::table('attendance_sessions')->insertGetId([
        'course_offering_id' => $offeringAId,
        'lecturer_profile_id' => $profileAId,
        'meeting_no' => 2,
        'meeting_date' => now()->subDay()->toDateString(),
        'status' => 'Cancelled',
        'opened_at' => now()->subDay()->subHour(),
        'closed_at' => now()->subDay(),
    ]);
    DB::table('attendance_sessions')->insert([
        'course_offering_id' => $offeringAId,
        'lecturer_profile_id' => $profileAId,
        'meeting_no' => 3,
        'meeting_date' => now()->toDateString(),
        'status' => 'Draft',
    ]);

    DB::table('attendance_records')->insert([
        [
            'attendance_session_id' => $closedSessionId,
            'student_profile_id' => $studentProfile1,
            'status' => 'Present',
            'recorded_at' => now()->subDays(2),
        ],
        [
            'attendance_session_id' => $closedSessionId,
            'student_profile_id' => $studentProfile2,
            'status' => 'Absent',
            'recorded_at' => now()->subDays(2),
        ],
        [
            'attendance_session_id' => $cancelledSessionId,
            'student_profile_id' => $studentProfile1,
            'status' => 'Present',
            'recorded_at' => now()->subDay(),
        ],
        [
            'attendance_session_id' => $cancelledSessionId,
            'student_profile_id' => $studentProfile2,
            'status' => 'Present',
            'recorded_at' => now()->subDay(),
        ],
    ]);

    $workloadPeriodId = DB::table('lecturer_workload_periods')->insertGetId([
        'name' => 'BKD 2026', 'code' => 'BKD-OV', 'status' => 'open',
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
    ]);
    DB::table('lecturer_workload_submissions')->insert([
        [
            'lecturer_workload_period_id' => $workloadPeriodId,
            'user_id' => $lecturerA->id,
            'lecturer_profile_id' => $profileAId,
            'status' => 'approved',
            'teaching_sks' => 10,
            'structural_sks' => 2,
            'tridharma_sks' => 2,
            'total_sks' => 14,
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDays(2),
        ],
        [
            'lecturer_workload_period_id' => $workloadPeriodId,
            'user_id' => $lecturerB->id,
            'lecturer_profile_id' => $profileBId,
            'status' => 'in_approval',
            'teaching_sks' => 8,
            'structural_sks' => 0,
            'tridharma_sks' => 2,
            'total_sks' => 10,
            'submitted_at' => now()->subDay(),
            'approved_at' => null,
        ],
    ]);

    $edomPeriod1 = DB::table('edom_periods')->insertGetId([
        'name' => 'EDOM Ganjil 2026/2027', 'code' => 'EDOM-OV-1', 'status' => 'closed',
        'starts_at' => now()->subMonths(2)->toDateString(),
        'ends_at' => now()->subMonth()->toDateString(),
    ]);
    $edomPeriod2 = DB::table('edom_periods')->insertGetId([
        'name' => 'EDOM Genap 2026/2027', 'code' => 'EDOM-OV-2', 'status' => 'closed',
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->toDateString(),
    ]);

    DB::table('lecturer_performance_reviews')->insert([
        [
            'edom_period_id' => $edomPeriod1,
            'user_id' => $lecturerA->id,
            'lecturer_profile_id' => $profileAId,
            'edom_score' => 80,
            'edom_response_count' => 10,
            'teaching_compliance_score' => 90,
            'attendance_compliance_score' => 85,
            'workload_total_sks' => 14,
            'final_score' => 85,
            'status' => 'published',
            'calculated_at' => now()->subDays(2),
        ],
        [
            'edom_period_id' => $edomPeriod2,
            'user_id' => $lecturerA->id,
            'lecturer_profile_id' => $profileAId,
            'edom_score' => 60,
            'edom_response_count' => 4,
            'teaching_compliance_score' => 70,
            'attendance_compliance_score' => 65,
            'workload_total_sks' => 14,
            'final_score' => 55,
            'status' => 'published',
            'calculated_at' => now()->subDay(),
        ],
        [
            'edom_period_id' => $edomPeriod1,
            'user_id' => $lecturerB->id,
            'lecturer_profile_id' => $profileBId,
            'edom_score' => 75,
            'edom_response_count' => 6,
            'teaching_compliance_score' => 80,
            'attendance_compliance_score' => 75,
            'workload_total_sks' => 10,
            'final_score' => 70,
            'status' => 'published',
            'calculated_at' => now(),
        ],
    ]);

    return compact(
        'leader',
        'profileAId',
        'profileBId',
        'outsideProfileId',
        'offeringAId',
        'offeringBId',
        'offeringCId',
        'edomPeriod1',
        'edomPeriod2',
    );
}

it('menghitung statistik dosen dari seluruh scope meski daftar difilter', function () {
    $setup = academicLeaderOversightSetup();

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/lecturers?q=Andi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Lecturers/Index')
            ->has('rows', 1)
            ->where('rows.0.name', 'Andi Saputra')
            ->where('stats.lecturers', 2)
            ->where('stats.pending', 1)
            ->where('stats.avgScore', 62.5));

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/lecturers?status=in_approval')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Budi Hartono'));
});

it('memfilter BKD tanpa mengubah statistik scope', function () {
    $setup = academicLeaderOversightSetup();

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/workloads?q=Andi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Workloads/Index')
            ->has('data.rows', 1)
            ->where('data.rows.0.name', 'Andi Saputra')
            ->where('data.total', 1)
            ->where('stats.total', 2)
            ->where('stats.approval', 1)
            ->where('stats.approved', 1)
            ->where('stats.avgSks', 12));
});

it('mengabaikan sesi batal dan sesi tanpa rekaman pada tingkat kehadiran', function () {
    $setup = academicLeaderOversightSetup();

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/classes?q=Andi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Classes/Index')
            ->has('rows', 2)
            ->where('rows.0.label', 'B')
            ->where('rows.1.label', 'A')
            ->where('rows.1.attendanceRate', 50)
            ->where('rows.1.cancelledSessions', 1)
            ->where('rows.1.riskLevel', 'danger')
            ->where('stats.classes', 3)
            ->where('stats.problem', 3)
            ->where('stats.lowAttendance', 1));
});

it('menghitung dosen EDOM unik dan memfilter periode tanpa mengubah statistik', function () {
    $setup = academicLeaderOversightSetup();

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/edom')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Edom/Index')
            ->where('stats.lecturers', 2)
            ->where('stats.reviews', 3)
            ->where('stats.responses', 20)
            ->where('stats.avgEdom', 71.67)
            ->where('data.total', 3)
            ->has('periods', 2));

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/edom?period='.$setup['edomPeriod1'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.total', 2)
            ->where('stats.reviews', 3));

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/edom?q=Budi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.total', 1)
            ->where('data.rows.0.name', 'Budi Hartono')
            ->where('stats.lecturers', 2));
});

it('merender detail dosen dengan benchmark prodi dan fakultas', function () {
    $setup = academicLeaderOversightSetup();

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get(route('academic-leader.lecturers.show', $setup['profileAId']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Lecturers/Show')
            ->where('profile.id', $setup['profileAId'])
            ->where('stats.approvedBkd', 1)
            ->where('stats.latestSks', 14)
            ->where('stats.latestScore', 55)
            ->where('stats.avgScore', 70)
            ->where('stats.programBenchmark', 70)
            ->has('workloads', 1)
            ->has('reviews', 2));
});

it('menolak pimpinan tanpa scope dan mengosongkan daftar dosen', function () {
    $setup = academicLeaderOversightSetup();

    $outsider = User::factory()->create();
    $outsider->assignRole('academic-leader');

    $this->actingAs($outsider)
        ->withSession(['active_role' => 'academic-leader'])
        ->get(route('academic-leader.lecturers.show', $setup['profileAId']))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/lecturers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 0)
            ->where('stats.lecturers', 0));
});

it('mengurutkan alert laporan dari level paling berat', function () {
    $setup = academicLeaderOversightSetup();

    $this->actingAs($setup['leader'])
        ->withSession(['active_role' => 'academic-leader'])
        ->get('/academic-leader/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AcademicLeader/Reports/Index')
            ->where('stats.alerts', 7)
            ->has('alerts', 7)
            ->where('alerts.0.level', 'danger')
            ->where('alerts.1.level', 'danger')
            ->where('alerts.2.level', 'danger')
            ->where('alerts.6.level', 'info')
            ->where('alerts', function ($alerts): bool {
                $weights = ['danger' => 0, 'warning' => 1, 'info' => 2];
                $levels = collect($alerts)
                    ->map(fn (array $alert) => $weights[$alert['level']] ?? 3)
                    ->values()
                    ->all();
                $sorted = $levels;
                sort($sorted);

                return $levels === $sorted;
            }));
});
