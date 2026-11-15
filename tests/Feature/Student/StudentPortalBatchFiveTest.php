<?php

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\InvoiceItem;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Models\Organization\EdomResponse;
use App\Models\Publication\Announcement;
use App\Models\User;
use App\Support\Academic\AcademicAttendanceQrService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function studentBatchFiveInstall(): void
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
    Role::firstOrCreate(['name' => 'lecturer', 'guard_name' => 'web']);
}

/**
 * @return array<string, mixed>
 */
function studentBatchFiveContext(): array
{
    studentBatchFiveInstall();

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT5', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'code' => 'TI5',
        'degree' => 'S1',
        'is_active' => true,
    ]);
    $year = AcademicYear::create([
        'name' => '2026/2027 Ganjil',
        'code' => '2026-GANJIL-5',
        'semester' => 'Ganjil',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $studentUser = User::factory()->create();
    $studentUser->assignRole('student');

    $profile = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $year->id,
        'nim' => '2026TI5001',
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 3,
        'is_active' => true,
    ]);

    StudentRegistration::create([
        'student_profile_id' => $profile->id,
        'academic_year_id' => $year->id,
        'semester_no' => 3,
        'registration_status' => 'Approved',
        'academic_status' => 'Aktif',
        'registration_date' => '2026-08-01',
        'is_active' => true,
    ]);

    $course = Course::create(['code' => 'IF501', 'name' => 'Struktur Data', 'credits' => 3, 'is_active' => true]);
    $offering = CourseOffering::create([
        'academic_year_id' => $year->id,
        'study_program_id' => $program->id,
        'course_id' => $course->id,
        'label' => 'A',
        'code' => 'IF501-A',
        'credits' => 3,
        'total_meetings' => 14,
        'status' => 'Open',
    ]);

    $plan = StudyPlan::create([
        'student_profile_id' => $profile->id,
        'academic_year_id' => $year->id,
        'semester_no' => 3,
        'status' => 'Approved',
    ]);

    StudyPlanDetail::create([
        'study_plan_id' => $plan->id,
        'course_offering_id' => $offering->id,
        'credits' => 3,
        'status' => 'Taken',
    ]);

    $lecturerUser = User::factory()->create();
    $lecturerUser->assignRole('lecturer');
    $lecturerProfile = LecturerProfile::create([
        'user_id' => $lecturerUser->id,
        'faculty_id' => $faculty->id,
        'study_program_id' => $program->id,
        'nidn' => '0501005001',
        'is_active' => true,
    ]);

    CourseOfferingLecturer::create([
        'course_offering_id' => $offering->id,
        'lecturer_profile_id' => $lecturerProfile->id,
        'role' => 'Primary',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    CourseSchedule::create([
        'course_offering_id' => $offering->id,
        'lecturer_profile_id' => $lecturerProfile->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:40',
        'session_type' => 'Lecture',
        'delivery_mode' => 'Offline',
        'is_active' => true,
    ]);

    $attendanceSession = AttendanceSession::create([
        'course_offering_id' => $offering->id,
        'lecturer_profile_id' => $lecturerProfile->id,
        'meeting_no' => 1,
        'meeting_date' => '2026-08-10',
        'start_time' => '08:00',
        'end_time' => '09:40',
        'topic' => 'Pengenalan Struktur Data',
    ]);

    return compact(
        'faculty',
        'program',
        'year',
        'studentUser',
        'profile',
        'course',
        'offering',
        'plan',
        'lecturerUser',
        'lecturerProfile',
        'attendanceSession',
    );
}

it('menampilkan jadwal dari course_schedules meski belum ada sesi absensi', function () {
    ['studentUser' => $studentUser] = studentBatchFiveContext();

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.schedule.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Schedule/Index')
            ->where('state.hasSchedules', true)
            ->where('stats.courses', 1)
            ->where('stats.weeklyClasses', 1)
            ->has('days', 6)
            ->where('days.0.key', 'Monday')
            ->has('days.0.items', 1)
            ->where('days.0.items.0.courseName', 'Struktur Data')
            ->where('days.0.items.0.startTime', '08:00'));
});

it('membatasi rekap absensi pada kelas yang masih aktif di krs', function () {
    ['studentUser' => $studentUser, 'year' => $year, 'program' => $program] = studentBatchFiveContext();

    $otherCourse = Course::create(['code' => 'IF599', 'name' => 'Kelas Lain', 'credits' => 3, 'is_active' => true]);
    $otherOffering = CourseOffering::create([
        'academic_year_id' => $year->id,
        'study_program_id' => $program->id,
        'course_id' => $otherCourse->id,
        'label' => 'B',
        'code' => 'IF599-B',
        'credits' => 3,
        'total_meetings' => 14,
        'status' => 'Open',
    ]);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.schedule.attendance', ['offeringId' => $otherOffering->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Schedule/Attendance')
            ->where('hasAccess', false)
            ->has('items', 0));
});

it('mencatat scan qr mahasiswa dan menolak token yang kedaluwarsa', function () {
    ['studentUser' => $studentUser, 'profile' => $profile, 'attendanceSession' => $session, 'lecturerUser' => $lecturerUser] = studentBatchFiveContext();

    $service = app(AcademicAttendanceQrService::class);

    $this->travelTo(Carbon::parse('2026-08-10 08:05:00'));
    $opened = $service->openSession($session, $lecturerUser->id);
    $payload = $service->qrPayload($opened);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->post(route('student.schedule.attendance.record.scan', ['sessionId' => $opened->id]), [
            'payload' => $payload['url'],
            'latitude' => -6.2,
            'longitude' => 106.816666,
            'accuracy' => 12.5,
        ])
        ->assertRedirect(route('student.schedule.attendance.record', ['sessionId' => $opened->id]))
        ->assertSessionHas('success');

    $record = AttendanceRecord::query()
        ->where('attendance_session_id', $opened->id)
        ->where('student_profile_id', $profile->id)
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe('Present')
        ->and($record->source)->toBe('student_qr');

    $this->travelTo(Carbon::parse('2026-08-10 08:05:30'));

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->post(route('student.schedule.attendance.record.scan', ['sessionId' => $opened->id]), [
            'payload' => $payload['url'],
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(AttendanceRecord::query()
        ->where('attendance_session_id', $opened->id)
        ->where('student_profile_id', $profile->id)
        ->count())->toBe(1);
});

it('menolak scan qr dari mahasiswa yang tidak terdaftar pada kelas', function () {
    ['attendanceSession' => $session, 'lecturerUser' => $lecturerUser] = studentBatchFiveContext();

    $outsider = User::factory()->create();
    $outsider->assignRole('student');
    StudentProfile::create([
        'user_id' => $outsider->id,
        'study_program_id' => $session->courseOffering->study_program_id,
        'entry_academic_year_id' => $session->courseOffering->academic_year_id,
        'nim' => '2026TI5099',
        'is_active' => true,
    ]);

    $service = app(AcademicAttendanceQrService::class);
    $this->travelTo(Carbon::parse('2026-08-10 08:05:00'));
    $opened = $service->openSession($session, $lecturerUser->id);
    $payload = $service->qrPayload($opened);

    $this->actingAs($outsider)
        ->withSession(['active_role' => 'student'])
        ->post(route('student.schedule.attendance.record.scan', ['sessionId' => $opened->id]), [
            'payload' => $payload['url'],
        ])
        ->assertForbidden();
});

it('hanya menampilkan pengumuman untuk kelas yang masih aktif di krs', function () {
    ['studentUser' => $studentUser, 'faculty' => $faculty, 'program' => $program, 'year' => $year, 'offering' => $offering] = studentBatchFiveContext();

    $otherCourse = Course::create(['code' => 'IF598', 'name' => 'Kelas Dibatalkan', 'credits' => 3, 'is_active' => true]);
    $droppedOffering = CourseOffering::create([
        'academic_year_id' => $year->id,
        'study_program_id' => $program->id,
        'course_id' => $otherCourse->id,
        'label' => 'C',
        'code' => 'IF598-C',
        'credits' => 3,
        'total_meetings' => 14,
        'status' => 'Open',
    ]);

    $plan = StudyPlan::query()->firstOrFail();
    StudyPlanDetail::create([
        'study_plan_id' => $plan->id,
        'course_offering_id' => $droppedOffering->id,
        'credits' => 3,
        'status' => 'Dropped',
    ]);

    $make = function (string $title, AnnouncementTargetType $target, ?int $targetId) use ($studentUser) {
        return Announcement::create([
            'target_type' => $target,
            'target_id' => $targetId,
            'created_by' => $studentUser->id,
            'title' => $title,
            'content' => '<p>Isi pengumuman '.$title.'</p>',
            'priority' => AnnouncementPriority::NORMAL,
            'is_pinned' => false,
            'is_published' => true,
            'published_at' => now()->subHour(),
        ]);
    };

    $make('Pengumuman Global', AnnouncementTargetType::GLOBAL, null);
    $make('Pengumuman Fakultas', AnnouncementTargetType::FACULTY, $faculty->id);
    $make('Pengumuman Kelas Aktif', AnnouncementTargetType::COURSE_OFFERING, $offering->id);
    $make('Pengumuman Kelas Dibatalkan', AnnouncementTargetType::COURSE_OFFERING, $droppedOffering->id);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.announcements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Announcements/Index')
            ->has('announcements.rows', 3)
            ->where('announcements.total', 3)
            ->where('announcements.rows', function ($rows) {
                $titles = collect($rows)->pluck('title')->all();

                return count($titles) === 3
                    && in_array('Pengumuman Global', $titles, true)
                    && in_array('Pengumuman Fakultas', $titles, true)
                    && in_array('Pengumuman Kelas Aktif', $titles, true)
                    && ! in_array('Pengumuman Kelas Dibatalkan', $titles, true);
            }));
});

it('membatasi unduhan lampiran pengumuman pada pengumuman yang berhak dibaca', function () {
    Storage::fake('public');

    ['studentUser' => $studentUser, 'year' => $year, 'program' => $program, 'offering' => $offering] = studentBatchFiveContext();

    $otherCourse = Course::create(['code' => 'IF597', 'name' => 'Kelas Lain', 'credits' => 3, 'is_active' => true]);
    $otherOffering = CourseOffering::create([
        'academic_year_id' => $year->id,
        'study_program_id' => $program->id,
        'course_id' => $otherCourse->id,
        'label' => 'D',
        'code' => 'IF597-D',
        'credits' => 3,
        'total_meetings' => 14,
        'status' => 'Open',
    ]);

    Storage::disk('public')->put('announcements/privat.pdf', 'rahasia kelas lain');

    $base = [
        'created_by' => $studentUser->id,
        'content' => '<p>Lampiran</p>',
        'priority' => AnnouncementPriority::NORMAL,
        'is_pinned' => false,
        'is_published' => true,
        'published_at' => now()->subHour(),
        'attachment_path' => 'announcements/privat.pdf',
        'attachment_name' => 'privat.pdf',
        'attachment_type' => 'pdf',
        'attachment_size' => 1024,
    ];

    $own = Announcement::create($base + [
        'target_type' => AnnouncementTargetType::COURSE_OFFERING,
        'target_id' => $offering->id,
        'title' => 'Lampiran Kelas Sendiri',
    ]);

    $foreign = Announcement::create($base + [
        'target_type' => AnnouncementTargetType::COURSE_OFFERING,
        'target_id' => $otherOffering->id,
        'title' => 'Lampiran Kelas Lain',
    ]);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.announcements.attachment', ['id' => $own->id]))
        ->assertOk();

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.announcements.attachment', ['id' => $foreign->id]))
        ->assertNotFound();
});

it('menampilkan target edom hanya untuk kelas yang sudah diambil dan memvalidasi skala', function () {
    ['studentUser' => $studentUser, 'year' => $year, 'offering' => $offering, 'lecturerProfile' => $lecturerProfile, 'profile' => $profile] = studentBatchFiveContext();

    $period = EdomPeriod::create([
        'academic_year_id' => $year->id,
        'name' => 'EDOM Ganjil 2026/2027',
        'code' => 'EDOM-2026-GANJIL',
        'status' => 'open',
        'starts_at' => now()->subDay()->toDateString(),
        'ends_at' => now()->addMonth()->toDateString(),
    ]);

    $question = EdomQuestion::create([
        'category' => 'teaching',
        'question_text' => 'Dosen menjelaskan materi dengan jelas.',
        'answer_type' => 'scale',
        'is_required' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.edom.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Edom/Index')
            ->has('targets', 1)
            ->where('targets.0.offeringId', $offering->id)
            ->where('targets.0.lecturerId', $lecturerProfile->id)
            ->where('stats.pending', 1));

    $params = [
        'periodId' => $period->id,
        'courseOfferingId' => $offering->id,
        'lecturerProfileId' => $lecturerProfile->id,
    ];

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->post(route('student.edom.submit', $params), ['answers' => [$question->id => 9]])
        ->assertSessionHasErrors(["answers.{$question->id}"]);

    expect(EdomResponse::query()->count())->toBe(0);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->post(route('student.edom.submit', $params), ['answers' => [$question->id => 4]])
        ->assertRedirect(route('student.edom.index'))
        ->assertSessionHasNoErrors();

    $response = EdomResponse::query()->first();

    expect($response)->not->toBeNull()
        ->and($response->student_profile_id)->toBe($profile->id)
        ->and($response->answers()->first()->score)->toBe(4);
});

it('membatasi invoice pada milik mahasiswa dan menerima bukti pembayaran', function () {
    Storage::fake('public');

    ['studentUser' => $studentUser, 'profile' => $profile, 'year' => $year] = studentBatchFiveContext();

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-B5-0001',
        'student_profile_id' => $profile->id,
        'academic_year_id' => $year->id,
        'semester' => 'Ganjil',
        'invoice_type' => 'tuition',
        'total_amount' => 5000000,
        'paid_amount' => 0,
        'outstanding_amount' => 5000000,
        'status' => 'issued',
        'due_date' => now()->addMonth()->toDateString(),
        'issued_at' => now(),
    ]);

    InvoiceItem::create([
        'student_invoice_id' => $invoice->id,
        'item_type' => 'tuition',
        'description' => 'SPP Semester Ganjil',
        'amount' => 5000000,
        'sort_order' => 1,
    ]);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.financial.invoices'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Financial/Invoices')
            ->has('invoices', 1)
            ->where('invoices.0.number', 'INV-B5-0001')
            ->where('summary.outstanding', 5000000));

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.financial.invoices.show', ['id' => $invoice->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Financial/InvoiceDetail')
            ->where('invoice.number', 'INV-B5-0001')
            ->where('invoice.canPay', true)
            ->has('items', 1)
            ->has('simulations', 8));

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->post(route('student.financial.invoices.pay', ['id' => $invoice->id]), [
            'amount' => 1000000,
            'method' => 'bank_transfer',
            'reference' => 'TRX-B5-1',
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment = Payment::query()->where('student_invoice_id', $invoice->id)->first();

    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(1000000.0)
        ->and($payment->status)->toBe('pending')
        ->and(Storage::disk('public')->exists($payment->proof_file_path))->toBeTrue();
});

it('menolak akses invoice milik mahasiswa lain', function () {
    ['studentUser' => $studentUser, 'program' => $program, 'year' => $year] = studentBatchFiveContext();

    $otherUser = User::factory()->create();
    $otherUser->assignRole('student');

    $otherProfile = StudentProfile::create([
        'user_id' => $otherUser->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $year->id,
        'nim' => '2026TI5002',
        'is_active' => true,
    ]);

    $foreignInvoice = StudentInvoice::create([
        'invoice_number' => 'INV-B5-0002',
        'student_profile_id' => $otherProfile->id,
        'academic_year_id' => $year->id,
        'semester' => 'Ganjil',
        'invoice_type' => 'tuition',
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'outstanding_amount' => 1000000,
        'status' => 'issued',
        'due_date' => now()->addMonth()->toDateString(),
        'issued_at' => now(),
    ]);

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.financial.invoices.show', ['id' => $foreignInvoice->id]))
        ->assertNotFound();

    $this->actingAs($studentUser)
        ->withSession(['active_role' => 'student'])
        ->post(route('student.financial.invoices.pay', ['id' => $foreignInvoice->id]), [
            'amount' => 500000,
            'method' => 'bank_transfer',
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ])
        ->assertNotFound();
});
