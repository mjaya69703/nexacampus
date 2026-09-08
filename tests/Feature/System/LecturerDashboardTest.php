<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function lecturerDashboardSetup(): array
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
    $courseId = DB::table('courses')->insertGetId([
        'code' => 'TI101', 'name' => 'Pemrograman Dasar', 'credits' => 3,
    ]);
    $yearId = DB::table('academic_years')->insertGetId([
        'name' => '2026/2027', 'code' => '2026', 'semester' => 'Ganjil',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'is_active' => true,
    ]);

    $lecturerId = DB::table('lecturer_profiles')->insertGetId([
        'user_id' => $user->id, 'faculty_id' => $facultyId,
        'study_program_id' => $programId, 'nidn' => '0404040404',
    ]);

    $offeringId = DB::table('course_offerings')->insertGetId([
        'academic_year_id' => $yearId, 'study_program_id' => $programId,
        'course_id' => $courseId, 'label' => 'A', 'credits' => 3,
    ]);
    DB::table('course_offering_lecturers')->insert([
        'course_offering_id' => $offeringId, 'lecturer_profile_id' => $lecturerId,
        'role' => 'Primary', 'is_active' => true,
    ]);

    return compact('user', 'lecturerId', 'offeringId', 'programId', 'yearId');
}

function lecturerDashboardStudent(array $setup): int
{
    $student = User::factory()->create();

    return DB::table('student_profiles')->insertGetId([
        'user_id' => $student->id,
        'study_program_id' => $setup['programId'],
        'nim' => '2026TI0001',
        'academic_status' => 'Aktif',
        'is_active' => true,
    ]);
}

it('redirects guests away from the lecturer dashboard to login', function () {
    lecturerDashboardSetup();

    $this->get('/lecturer/dashboard')->assertRedirect('/auth/login');
});

it('renders the empty state without a lecturer profile', function () {
    lecturerDashboardSetup();

    $plain = User::factory()->create();
    $plain->assignRole('lecturer');

    $this->actingAs($plain)
        ->withSession(['active_role' => 'lecturer'])
        ->get('/lecturer/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lecturer/Dashboard')
            ->where('hasProfile', false));
});

it('renders the action inbox from real lecturer queues', function () {
    $setup = lecturerDashboardSetup();
    $user = $setup['user'];
    $studentId = lecturerDashboardStudent($setup);

    // Sesi hari ini belum dibuka.
    DB::table('attendance_sessions')->insert([
        'course_offering_id' => $setup['offeringId'],
        'meeting_no' => 3,
        'meeting_date' => now()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'status' => 'Draft',
    ]);

    // Tugas dengan 1 submission belum dinilai.
    $assignmentId = DB::table('assignments')->insertGetId([
        'course_offering_id' => $setup['offeringId'],
        'title' => 'Tugas Praktikum 1',
        'due_at' => now()->addDays(2),
        'is_published' => true,
    ]);
    DB::table('assignment_submissions')->insert([
        'assignment_id' => $assignmentId,
        'student_profile_id' => $studentId,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    // Nilai draft (detail tanpa grade Finalized).
    $planId = DB::table('study_plans')->insertGetId([
        'student_profile_id' => $studentId,
        'academic_year_id' => $setup['yearId'],
        'semester_no' => 1,
        'status' => 'Approved',
    ]);
    $detailId = DB::table('study_plan_details')->insertGetId([
        'study_plan_id' => $planId,
        'course_offering_id' => $setup['offeringId'],
    ]);
    $gradeId = DB::table('student_grades')->insertGetId([
        'study_plan_detail_id' => $detailId,
        'grade_status' => 'Draft',
    ]);

    // Sanggahan menunggu keputusan.
    DB::table('grade_appeals')->insert([
        'student_grade_id' => $gradeId,
        'student_profile_id' => $studentId,
        'course_offering_id' => $setup['offeringId'],
        'lecturer_profile_id' => $setup['lecturerId'],
        'status' => 'submitted',
        'reason_category' => 'recheck',
        'reason' => 'Mohon periksa kembali soal nomor 3.',
        'submitted_at' => now(),
    ]);

    // Konsultasi: 1 permintaan baru + 1 terkonfirmasi besok.
    $slotId = DB::table('consultation_slots')->insertGetId([
        'lecturer_profile_id' => $setup['lecturerId'],
        'weekday' => 1,
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'is_active' => true,
    ]);
    DB::table('consultation_appointments')->insert([
        'consultation_slot_id' => $slotId,
        'lecturer_profile_id' => $setup['lecturerId'],
        'student_profile_id' => $studentId,
        'appointment_date' => now()->toDateString(),
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'status' => 'requested',
        'topic' => 'Bimbingan KRS',
    ]);
    DB::table('consultation_appointments')->insert([
        'consultation_slot_id' => $slotId,
        'lecturer_profile_id' => $setup['lecturerId'],
        'student_profile_id' => $studentId,
        'appointment_date' => now()->addDay()->toDateString(),
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'status' => 'confirmed',
        'topic' => 'Revisi proposal',
    ]);

    // Pengumuman global belum dibaca.
    DB::table('announcements')->insert([
        'target_type' => 'global',
        'target_id' => null,
        'created_by' => $user->id,
        'title' => 'Kalender akademik terbaru',
        'content' => 'Isi pengumuman.',
        'priority' => 'normal',
        'is_published' => true,
        'published_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_role' => 'lecturer'])
        ->get('/lecturer/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lecturer/Dashboard')
            ->where('hasProfile', true)
            ->where('lecturer.name', $user->name)
            ->has('inbox')
            ->has('summary')
            ->where('summary.classes', 1)
            ->where('summary.sks', 3)
            ->where('summary.students', 1)
            ->where('summary.sessionsWeek', 1)
            ->where('inbox', function ($inbox) {
                $keys = collect($inbox)->pluck('key')->all();

                return collect(['sessions', 'grading', 'appeals', 'consultations', 'drafts'])
                    ->every(fn ($key) => in_array($key, $keys, true));
            })
            ->has('week')
            ->has('classes', 1)
            ->where('announcements.unread', 1));
});

it('returns 404 for the removed lecturer students page', function () {
    $setup = lecturerDashboardSetup();

    $this->actingAs($setup['user'])
        ->withSession(['active_role' => 'lecturer'])
        ->get('/lecturer/students')
        ->assertNotFound();
});
