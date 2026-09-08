<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function studentDashboardSetup(): array
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

    $user = User::factory()->create();
    $user->assignRole('student');

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

    $studentId = DB::table('student_profiles')->insertGetId([
        'user_id' => $user->id,
        'study_program_id' => $programId,
        'nim' => '2026TI0001',
        'academic_status' => 'Aktif',
        'entry_date' => now()->toDateString(),
        'current_semester' => 3,
        'is_active' => true,
    ]);

    return compact('user', 'studentId', 'programId', 'courseId', 'yearId');
}

it('redirects guests away from the student dashboard to login', function () {
    studentDashboardSetup();

    $this->get('/student/dashboard')->assertRedirect('/auth/login');
});

it('renders the empty state without a student profile', function () {
    studentDashboardSetup();

    $plain = User::factory()->create();
    $plain->assignRole('student');

    $this->actingAs($plain)
        ->withSession(['active_role' => 'student'])
        ->get('/student/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Dashboard')
            ->where('hasProfile', false));
});

it('renders the full student dashboard from real academic data', function () {
    $setup = studentDashboardSetup();
    $user = $setup['user'];

    $offeringId = DB::table('course_offerings')->insertGetId([
        'academic_year_id' => $setup['yearId'], 'study_program_id' => $setup['programId'],
        'course_id' => $setup['courseId'], 'label' => 'A',
    ]);
    DB::table('course_schedules')->insert([
        'course_offering_id' => $offeringId,
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'is_active' => true,
    ]);

    $planId = DB::table('study_plans')->insertGetId([
        'student_profile_id' => $setup['studentId'],
        'academic_year_id' => $setup['yearId'],
        'semester_no' => 3,
        'status' => 'Approved',
    ]);
    $detailId = DB::table('study_plan_details')->insertGetId([
        'study_plan_id' => $planId,
        'course_offering_id' => $offeringId,
        'credits' => 3,
    ]);
    DB::table('student_grades')->insert([
        'study_plan_detail_id' => $detailId,
        'final_score' => 85,
        'letter_grade' => 'A',
        'grade_point' => 4.0,
        'grade_status' => 'Published',
        'graded_at' => now(),
    ]);
    DB::table('transcript_entries')->insert([
        'student_profile_id' => $setup['studentId'],
        'course_id' => $setup['courseId'],
        'student_grade_id' => DB::table('student_grades')->where('study_plan_detail_id', $detailId)->value('id'),
        'academic_year_id' => $setup['yearId'],
        'final_score' => 85,
        'letter_grade' => 'A',
        'grade_point' => 4.0,
        'result_status' => 'Passed',
    ]);

    $statuses = ['Present', 'Present', 'Present', 'Absent'];
    foreach ($statuses as $index => $status) {
        $sessionId = DB::table('attendance_sessions')->insertGetId([
            'course_offering_id' => $offeringId,
            'meeting_no' => $index + 1,
            'meeting_date' => now()->subDays(3 - $index)->toDateString(),
            'status' => 'Closed',
        ]);
        DB::table('attendance_records')->insert([
            'attendance_session_id' => $sessionId,
            'student_profile_id' => $setup['studentId'],
            'status' => $status,
        ]);
    }

    $invoiceId = DB::table('student_invoices')->insertGetId([
        'invoice_number' => 'INV-TEST-0001',
        'student_profile_id' => $setup['studentId'],
        'invoice_type' => 'tuition',
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'outstanding_amount' => 1000000,
        'status' => 'issued',
        'due_date' => now()->subDay()->toDateString(),
    ]);
    DB::table('invoice_items')->insert([
        'student_invoice_id' => $invoiceId,
        'item_type' => 'fee',
        'description' => 'UKT Semester 3',
        'amount' => 1000000,
    ]);
    DB::table('student_invoices')->insert([
        'invoice_number' => 'INV-TEST-0002',
        'student_profile_id' => $setup['studentId'],
        'invoice_type' => 'tuition',
        'total_amount' => 500000,
        'paid_amount' => 500000,
        'outstanding_amount' => 0,
        'status' => 'paid',
        'due_date' => now()->subMonth()->toDateString(),
    ]);

    DB::table('announcements')->insert([
        'target_type' => 'global',
        'target_id' => null,
        'created_by' => $user->id,
        'title' => 'Libur nasional',
        'content' => 'Kampus libur.',
        'priority' => 'normal',
        'is_published' => true,
        'published_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get('/student/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Dashboard')
            ->where('hasProfile', true)
            ->where('student.nim', '2026TI0001')
            ->where('stats.courses', 1)
            ->where('stats.credits', 3)
            ->where('stats.publishedGrades', 1)
            ->where('stats.passedCourses', 1)
            ->where('academic.ipk', '4.00')
            ->where('attendance.rate', 75)
            ->has('grades', 1)
            ->has('schedules', 1)
            ->where('schedules.0.hari', 'Senin')
            ->where('finance.overdue', 1)
            ->where('finance.paid', 1)
            ->where('finance.outstanding', 1000000)
            ->has('announcements.items', 1)
            ->where('announcements.unread', 1));
});
