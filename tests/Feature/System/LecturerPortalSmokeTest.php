<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function lecturerPortalSmokeSetup(): User
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
    Role::firstOrCreate(['name' => 'lecturer', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('lecturer');

    $facultyId = DB::table('faculties')->insertGetId([
        'name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true,
    ]);
    $programId = DB::table('study_programs')->insertGetId([
        'faculty_id' => $facultyId, 'name' => 'Teknik Informatika', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);
    DB::table('academic_years')->insert([
        'name' => '2026/2027', 'code' => '2026', 'semester' => 'Ganjil',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'is_active' => true,
    ]);
    $yearId = DB::table('academic_years')->where('code', '2026')->value('id');
    $lecturerId = DB::table('lecturer_profiles')->insertGetId([
        'user_id' => $user->id, 'faculty_id' => $facultyId,
        'study_program_id' => $programId, 'nidn' => '0404040404',
    ]);

    $student = User::factory()->create();
    $studentId = DB::table('student_profiles')->insertGetId([
        'user_id' => $student->id, 'study_program_id' => $programId,
        'nim' => '2026TI0001', 'academic_status' => 'Aktif', 'is_active' => true,
    ]);
    DB::table('academic_advisor_assignments')->insert([
        'student_profile_id' => $studentId,
        'lecturer_profile_id' => $lecturerId,
        'academic_year_id' => $yearId,
        'start_date' => now()->subMonth()->toDateString(),
        'is_active' => true,
    ]);

    return $user;
}

it('merender seluruh halaman portal dosen', function (string $routeName) {
    $user = lecturerPortalSmokeSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    'bimbingan akademik' => ['lecturer.academic-advising.index'],
    'pengumuman' => ['lecturer.announcements.index'],
    'tugas' => ['lecturer.assignments.index'],
    'kalender' => ['lecturer.calendar.index'],
    'konsultasi' => ['lecturer.consultations.index'],
    'materi' => ['lecturer.course-materials.list'],
    'kelas' => ['lecturer.course-offerings.index'],
    'edom' => ['lecturer.edom.index'],
    'banding nilai' => ['lecturer.grade-appeals.index'],
    'nilai mahasiswa' => ['lecturer.student-grades.index'],
    'buku nilai' => ['lecturer.student-grades.grade-book'],
    'tridharma' => ['lecturer.tridharma.index'],
    'tambah tridharma' => ['lecturer.tridharma.create'],
    'beban kerja' => ['lecturer.workloads.index'],
]);

/**
 * Fixture lengkap untuk halaman detail & aksi portal dosen.
 *
 * @return array<string, int|User>
 */
function lecturerPortalFixture(): array
{
    $user = lecturerPortalSmokeSetup();

    $facultyId = (int) DB::table('faculties')->where('code', 'FT')->value('id');
    $programId = (int) DB::table('study_programs')->where('code', 'TI')->value('id');
    $yearId = (int) DB::table('academic_years')->where('code', '2026')->value('id');
    $lecturerId = (int) DB::table('lecturer_profiles')->where('user_id', $user->id)->value('id');
    $studentProfileId = (int) DB::table('student_profiles')->where('nim', '2026TI0001')->value('id');
    $studentUserId = (int) DB::table('student_profiles')->where('nim', '2026TI0001')->value('user_id');
    $advisorAssignmentId = (int) DB::table('academic_advisor_assignments')->where('lecturer_profile_id', $lecturerId)->value('id');

    $courseId = (int) DB::table('courses')->insertGetId([
        'code' => 'TI101', 'name' => 'Pemrograman Dasar', 'credits' => 3,
        'requirement_type' => 'Wajib', 'category_type' => 'Keilmuan', 'is_active' => true,
    ]);

    $offeringId = (int) DB::table('course_offerings')->insertGetId([
        'academic_year_id' => $yearId, 'study_program_id' => $programId,
        'course_id' => $courseId, 'label' => 'A', 'code' => 'TI101-A',
        'semester_no' => 1, 'capacity' => 40, 'credits' => 3,
        'delivery_mode' => 'Offline', 'status' => 'Open',
    ]);

    DB::table('course_offering_lecturers')->insert([
        'course_offering_id' => $offeringId, 'lecturer_profile_id' => $lecturerId,
        'role' => 'Primary', 'is_active' => true,
    ]);

    DB::table('course_schedules')->insert([
        'course_offering_id' => $offeringId, 'lecturer_profile_id' => $lecturerId,
        'room_id' => null, 'day_of_week' => 'Monday',
        'start_time' => '08:00:00', 'end_time' => '10:00:00',
        'session_type' => 'Lecture', 'delivery_mode' => 'Offline', 'is_active' => true,
    ]);

    $planId = (int) DB::table('study_plans')->insertGetId([
        'student_profile_id' => $studentProfileId, 'academic_year_id' => $yearId,
        'semester_no' => 1, 'status' => 'Approved', 'submitted_at' => now(),
    ]);
    $detailId = (int) DB::table('study_plan_details')->insertGetId([
        'study_plan_id' => $planId, 'course_offering_id' => $offeringId,
        'credits' => 3, 'status' => 'Taken',
    ]);
    $gradeId = (int) DB::table('student_grades')->insertGetId([
        'study_plan_detail_id' => $detailId, 'grade_status' => 'Draft',
    ]);

    $materialId = (int) DB::table('course_materials')->insertGetId([
        'course_offering_id' => $offeringId, 'uploaded_by' => $user->id,
        'title' => 'Modul Pertemuan 1', 'description' => 'Pengantar pemrograman.',
        'file_path' => 'course-materials/modul-1.pdf', 'file_name' => 'modul-1.pdf',
        'file_type' => 'pdf', 'file_size' => 1024, 'category' => 'lecture_notes',
        'meeting_number' => 1, 'is_published' => true, 'download_count' => 0,
    ]);

    $assignmentId = (int) DB::table('assignments')->insertGetId([
        'course_offering_id' => $offeringId, 'created_by' => $user->id,
        'title' => 'Tugas Praktikum 1', 'description' => 'Kerjakan latihan bab 1.',
        'due_at' => now()->addDays(2), 'max_score' => 100,
        'allow_text_submission' => true, 'allow_file_submission' => true,
        'allow_resubmission' => true, 'accept_late_submission' => true,
        'is_published' => true, 'published_at' => now(),
    ]);
    DB::table('assignment_submissions')->insert([
        'assignment_id' => $assignmentId, 'student_profile_id' => $studentProfileId,
        'content' => 'Jawaban saya.', 'status' => 'submitted', 'submitted_at' => now(),
    ]);

    $sessionId = (int) DB::table('attendance_sessions')->insertGetId([
        'course_offering_id' => $offeringId, 'lecturer_profile_id' => $lecturerId,
        'meeting_no' => 1, 'meeting_date' => now()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00',
        'topic' => 'Pengenalan', 'status' => 'Draft',
    ]);
    DB::table('attendance_records')->insert([
        'attendance_session_id' => $sessionId, 'student_profile_id' => $studentProfileId,
        'status' => 'Present', 'recorded_at' => now(), 'recorded_by' => $user->id,
    ]);

    $appealId = (int) DB::table('grade_appeals')->insertGetId([
        'student_grade_id' => $gradeId, 'student_profile_id' => $studentProfileId,
        'course_offering_id' => $offeringId, 'lecturer_profile_id' => $lecturerId,
        'student_user_id' => $studentUserId, 'status' => 'submitted',
        'reason_category' => 'recheck', 'reason' => 'Mohon periksa kembali nilai UTS.',
        'expected_outcome' => 'Nilai diperbaiki.', 'submitted_at' => now(),
    ]);

    $announcementId = (int) DB::table('announcements')->insertGetId([
        'target_type' => 'global', 'target_id' => null, 'created_by' => $user->id,
        'title' => 'Pengumuman Dosen', 'content' => 'Isi pengumuman dosen.',
        'priority' => 'important', 'is_pinned' => false, 'is_published' => true,
        'published_at' => now()->subHour(),
    ]);

    $slotId = (int) DB::table('consultation_slots')->insertGetId([
        'lecturer_profile_id' => $lecturerId, 'title' => 'Konsultasi Akademik',
        'weekday' => 1, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
        'slot_minutes' => 30, 'capacity' => 2, 'consultation_mode' => 'in_person',
        'location' => 'Ruang Dosen', 'is_active' => true,
    ]);
    $appointmentId = (int) DB::table('consultation_appointments')->insertGetId([
        'consultation_slot_id' => $slotId, 'lecturer_profile_id' => $lecturerId,
        'student_profile_id' => $studentProfileId, 'appointment_date' => now()->toDateString(),
        'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2),
        'status' => 'requested', 'topic' => 'Bimbingan KRS', 'student_notes' => 'Mohon arahan.',
    ]);

    $periodId = (int) DB::table('lecturer_workload_periods')->insertGetId([
        'academic_year_id' => $yearId, 'name' => 'BKD Ganjil 2026/2027',
        'code' => 'BKD-2026-1', 'starts_at' => now()->subDay()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(), 'status' => 'open',
        'minimum_sks' => 12, 'maximum_sks' => 16,
    ]);
    $workloadId = (int) DB::table('lecturer_workload_submissions')->insertGetId([
        'lecturer_workload_period_id' => $periodId, 'user_id' => $user->id,
        'lecturer_profile_id' => $lecturerId, 'status' => 'draft',
        'teaching_sks' => 3, 'structural_sks' => 0, 'tridharma_sks' => 0,
        'total_sks' => 3, 'generated_at' => now(),
    ]);
    DB::table('lecturer_workload_items')->insert([
        'lecturer_workload_submission_id' => $workloadId,
        'category' => 'teaching', 'source_code' => 'COURSE_OFFERING',
        'title' => 'TI101 - Pemrograman Dasar (A)', 'sks' => 3,
    ]);

    return compact(
        'user', 'facultyId', 'programId', 'yearId', 'lecturerId', 'studentProfileId',
        'advisorAssignmentId', 'courseId', 'offeringId', 'planId', 'detailId', 'gradeId',
        'materialId', 'assignmentId', 'sessionId', 'appealId', 'announcementId',
        'slotId', 'appointmentId', 'periodId', 'workloadId',
    );
}

it('menampilkan dashboard dosen tanpa profil tanpa halaman kosong', function () {
    lecturerPortalSmokeSetup();

    $plain = User::factory()->create();
    $plain->assignRole('lecturer');

    $this->actingAs($plain)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lecturer/Dashboard')
            ->where('hasProfile', false)
            ->where('summary.classes', 0)
            ->where('summary.yearName', null)
            ->where('announcements.allUrl', route('lecturer.announcements.index'))
            ->has('classes', 0)
            ->has('week', 0));
});

it('menampilkan konsultasi kosong tanpa profil dosen', function () {
    lecturerPortalSmokeSetup();

    $plain = User::factory()->create();
    $plain->assignRole('lecturer');

    $this->actingAs($plain)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.consultations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lecturer/Consultations/Index')
            ->where('hasProfile', false)
            ->where('stats.activeSlots', 0)
            ->has('slots', 0)
            ->has('appointments', 0));
});

it('merender halaman detail portal dosen dengan data nyata', function () {
    $f = lecturerPortalFixture();

    $pages = [
        ['lecturer.course-offerings.show', ['id' => $f['offeringId']], 'Lecturer/CourseOfferings/Show'],
        ['lecturer.course-offerings.students', ['offeringId' => $f['offeringId']], 'Lecturer/CourseOfferings/Students'],
        ['lecturer.course-offerings.attendance', ['offeringId' => $f['offeringId']], 'Lecturer/CourseOfferings/Attendance'],
        ['lecturer.course-offerings.grades', ['offeringId' => $f['offeringId']], 'Lecturer/CourseOfferings/Grades'],
        ['lecturer.course-materials.list', [], 'Lecturer/CourseMaterials/List'],
        ['lecturer.course-materials.index', ['offeringId' => $f['offeringId']], 'Lecturer/CourseMaterials/Index'],
        ['lecturer.course-materials.show', ['material' => $f['materialId']], 'Lecturer/CourseMaterials/Show'],
        ['lecturer.assignments.create', ['offeringId' => $f['offeringId']], 'Lecturer/Assignments/Create'],
        ['lecturer.assignments.edit', ['id' => $f['assignmentId']], 'Lecturer/Assignments/Edit'],
        ['lecturer.assignments.show', ['id' => $f['assignmentId']], 'Lecturer/Assignments/Show'],
        ['lecturer.attendance-sessions.edit', ['sessionId' => $f['sessionId']], 'Lecturer/AttendanceSessions/Edit'],
        ['lecturer.student-grades.edit', ['id' => $f['detailId']], 'Lecturer/StudentGrades/Edit'],
        ['lecturer.student-grades.grade-book', [], 'Lecturer/StudentGrades/GradeBook'],
        ['lecturer.academic-advising.show', ['assignmentId' => $f['advisorAssignmentId']], 'Lecturer/AcademicAdvising/Show'],
        ['lecturer.announcements.show', ['id' => $f['announcementId']], 'Lecturer/Announcements/Show'],
        ['lecturer.announcements.edit', ['id' => $f['announcementId']], 'Lecturer/Announcements/Edit'],
        ['lecturer.workloads.show', ['id' => $f['workloadId']], 'Lecturer/Workloads/Show'],
    ];

    foreach ($pages as [$routeName, $params, $component]) {
        $this->actingAs($f['user'])
            ->withSession(['active_role' => 'lecturer'])
            ->get(route($routeName, $params))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component($component));
    }
});

it('menyimpan absensi manual dari halaman detail sesi', function () {
    $f = lecturerPortalFixture();

    $this->actingAs($f['user'])
        ->withSession(['active_role' => 'lecturer'])
        ->post(route('lecturer.attendance-sessions.save', ['sessionId' => $f['sessionId']]), [
            'topic' => 'Pertemuan lanjutan',
            'status' => 'Closed',
            'statuses' => [(string) $f['studentProfileId'] => 'Late'],
        ])
        ->assertRedirect(route('lecturer.course-offerings.attendance', ['offeringId' => $f['offeringId']]));

    expect(DB::table('attendance_records')
        ->where('attendance_session_id', $f['sessionId'])
        ->where('student_profile_id', $f['studentProfileId'])
        ->value('status'))->toBe('Late');
    expect(DB::table('attendance_sessions')->where('id', $f['sessionId'])->value('status'))->toBe('Closed');
});

it('menyimpan komponen dan snapshot nilai mahasiswa', function () {
    $f = lecturerPortalFixture();

    $this->actingAs($f['user'])
        ->withSession(['active_role' => 'lecturer'])
        ->post(route('lecturer.student-grades.save', ['id' => $f['detailId']]), [
            'notes' => 'Nilai sementara.',
            'gradeStatus' => 'Draft',
            'components' => [
                ['name' => 'Tugas', 'weightPercentage' => 40, 'score' => 80, 'sortOrder' => 1, 'notes' => ''],
                ['name' => 'UTS', 'weightPercentage' => 60, 'score' => 70, 'sortOrder' => 2, 'notes' => ''],
            ],
        ])
        ->assertRedirect(route('lecturer.student-grades.edit', ['id' => $f['detailId']]));

    $grade = DB::table('student_grades')->where('study_plan_detail_id', $f['detailId'])->first();
    expect((float) $grade->final_score)->toBe(74.0)
        ->and($grade->grade_status)->toBe('Draft')
        ->and(DB::table('student_grade_components')->where('student_grade_id', $grade->id)->count())->toBe(2);
});

it('menyiapkan draf BKD dari periode yang dibuka', function () {
    $f = lecturerPortalFixture();

    DB::table('lecturer_workload_submissions')->where('id', $f['workloadId'])->delete();

    $this->actingAs($f['user'])
        ->withSession(['active_role' => 'lecturer'])
        ->post(route('lecturer.workloads.generate'), ['periodId' => $f['periodId']])
        ->assertRedirect();

    expect(DB::table('lecturer_workload_submissions')
        ->where('lecturer_workload_period_id', $f['periodId'])
        ->where('user_id', $f['user']->id)
        ->exists())->toBeTrue();
});

it('menyimpan catatan bimbingan akademik', function () {
    $f = lecturerPortalFixture();

    $this->actingAs($f['user'])
        ->withSession(['active_role' => 'lecturer'])
        ->post(route('lecturer.academic-advising.notes.store', ['assignmentId' => $f['advisorAssignmentId']]), [
            'topic' => 'Evaluasi semester',
            'notes' => 'Diskusi hasil studi dan rencana perbaikan.',
            'recommendation' => 'Kurangi beban SKS.',
            'visibleToStudent' => true,
        ])
        ->assertRedirect();

    expect(DB::table('student_advisor_notes')
        ->where('academic_advisor_assignment_id', $f['advisorAssignmentId'])
        ->where('topic', 'Evaluasi semester')
        ->exists())->toBeTrue();
});

it('menambahkan jadwal konsultasi baru', function () {
    $f = lecturerPortalFixture();

    $this->actingAs($f['user'])
        ->withSession(['active_role' => 'lecturer'])
        ->post(route('lecturer.consultations.slots.store'), [
            'title' => 'Konsultasi Tambahan',
            'weekday' => 2,
            'startTime' => '13:00',
            'endTime' => '15:00',
            'slotMinutes' => 30,
            'capacity' => 3,
            'consultationMode' => 'hybrid',
            'location' => 'Ruang 2',
            'meetingLink' => '',
            'startsOn' => '',
            'endsOn' => '',
            'notes' => '',
            'isActive' => true,
        ])
        ->assertRedirect();

    expect(DB::table('consultation_slots')
        ->where('lecturer_profile_id', $f['lecturerId'])
        ->where('title', 'Konsultasi Tambahan')
        ->exists())->toBeTrue();
});
