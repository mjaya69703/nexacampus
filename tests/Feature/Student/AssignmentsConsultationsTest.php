<?php

use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentFile;
use App\Models\Academic\AssignmentGrade;
use App\Models\Academic\AssignmentSubmission;
use App\Models\Academic\AssignmentSubmissionFile;
use App\Models\Academic\ConsultationAppointment;
use App\Models\Academic\ConsultationSlot;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Menyiapkan mahasiswa dengan kelas aktif, kelas yang sudah dibatalkan, dan
 * kelas milik program studi lain, lengkap dengan tugas dan slot konsultasi.
 *
 * @return array<string, mixed>
 */
function assignmentConsultationSetup(): array
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

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT-ASG', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'code' => 'TI-ASG',
        'degree' => 'S1',
        'is_active' => true,
    ]);
    $otherProgram = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Sistem Informasi',
        'code' => 'SI-ASG',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    $year = AcademicYear::create([
        'name' => '2026/2027',
        'code' => '2026-ASG',
        'semester' => 'Ganjil',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'is_active' => true,
    ]);

    $user = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Tugas']);
    $user->assignRole('student');

    $profile = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $year->id,
        'nim' => '2026TI0301',
        'academic_status' => 'Aktif',
        'entry_date' => now()->subYear()->toDateString(),
        'current_semester' => 3,
        'is_active' => true,
    ]);

    $registration = StudentRegistration::create([
        'student_profile_id' => $profile->id,
        'academic_year_id' => $year->id,
        'semester_no' => 3,
        'registration_status' => 'Approved',
        'academic_status' => 'Aktif',
        'registration_date' => now()->subDays(5)->toDateString(),
        'submitted_at' => now()->subDays(4),
        'approved_at' => now()->subDays(3),
        'is_active' => true,
    ]);

    $plan = StudyPlan::create([
        'student_profile_id' => $profile->id,
        'academic_year_id' => $year->id,
        'student_registration_id' => $registration->id,
        'semester_no' => 3,
        'status' => 'Draft',
        'created_by' => $user->id,
    ]);

    $definitions = [
        'active' => ['TI401', 'Rekayasa Perangkat Lunak', 'A', 'Taken', $program],
        'dropped' => ['TI402', 'Sistem Terdistribusi', 'B', 'Dropped', $program],
        'foreign' => ['SI401', 'Enterprise Architecture', 'X', null, $otherProgram],
    ];

    $offerings = [];

    foreach ($definitions as $key => [$code, $name, $label, $detailStatus, $owner]) {
        $course = Course::create([
            'code' => $code,
            'name' => $name,
            'credits' => 3,
            'semester_recommendation' => 3,
            'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan',
        ]);

        $offering = CourseOffering::create([
            'academic_year_id' => $year->id,
            'study_program_id' => $owner->id,
            'course_id' => $course->id,
            'label' => $label,
            'code' => $code.'-'.$label,
            'semester_no' => 3,
            'capacity' => 40,
            'credits' => 3,
            'is_required' => true,
            'delivery_mode' => 'Offline',
            'status' => 'Open',
        ]);

        if ($detailStatus !== null) {
            StudyPlanDetail::create([
                'study_plan_id' => $plan->id,
                'course_offering_id' => $offering->id,
                'credits' => 3,
                'status' => $detailStatus,
            ]);
        }

        $offerings[$key] = $offering;
    }

    $activeAssignment = Assignment::create([
        'course_offering_id' => $offerings['active']->id,
        'created_by' => $user->id,
        'title' => 'Tugas Aktif',
        'description' => '<p>Kerjakan analisis kebutuhan.</p>',
        'due_at' => now()->addDays(3),
        'max_score' => 100,
        'allowed_file_types' => ['pdf', 'docx'],
        'max_file_size_kb' => 2048,
        'allow_text_submission' => true,
        'allow_file_submission' => true,
        'allow_resubmission' => true,
        'accept_late_submission' => false,
        'is_published' => true,
    ]);

    AssignmentFile::create([
        'assignment_id' => $activeAssignment->id,
        'file_path' => 'assignments/instruksi.pdf',
        'file_name' => 'instruksi.pdf',
        'file_type' => 'pdf',
        'file_size' => 1024,
    ]);

    $missingAssignment = Assignment::create([
        'course_offering_id' => $offerings['active']->id,
        'created_by' => $user->id,
        'title' => 'Tugas Terlambat',
        'description' => '<p>Tugas yang sudah lewat deadline.</p>',
        'due_at' => now()->subDays(2),
        'max_score' => 100,
        'allowed_file_types' => ['pdf'],
        'max_file_size_kb' => 2048,
        'allow_text_submission' => true,
        'allow_file_submission' => true,
        'allow_resubmission' => true,
        'accept_late_submission' => false,
        'is_published' => true,
    ]);

    $droppedAssignment = Assignment::create([
        'course_offering_id' => $offerings['dropped']->id,
        'created_by' => $user->id,
        'title' => 'Tugas Dibatalkan',
        'due_at' => now()->addDays(5),
        'max_score' => 100,
        'allow_text_submission' => true,
        'allow_file_submission' => true,
        'allow_resubmission' => true,
        'accept_late_submission' => false,
        'is_published' => true,
    ]);

    $foreignAssignment = Assignment::create([
        'course_offering_id' => $offerings['foreign']->id,
        'created_by' => $user->id,
        'title' => 'Tugas Prodi Lain',
        'due_at' => now()->addDays(5),
        'max_score' => 100,
        'allow_text_submission' => true,
        'allow_file_submission' => true,
        'allow_resubmission' => true,
        'accept_late_submission' => false,
        'is_published' => true,
    ]);

    $lecturerUser = User::factory()->create(['first_name' => 'Dosen', 'last_name' => 'Pengampu']);
    $lecturer = LecturerProfile::create([
        'user_id' => $lecturerUser->id,
        'faculty_id' => $faculty->id,
        'study_program_id' => $program->id,
        'nidn' => '0401018501',
        'employment_status' => 'Tetap',
        'is_active' => true,
    ]);

    CourseOfferingLecturer::create([
        'course_offering_id' => $offerings['active']->id,
        'lecturer_profile_id' => $lecturer->id,
        'role' => 'Primary',
        'is_active' => true,
    ]);

    $advisorUser = User::factory()->create(['first_name' => 'Dosen', 'last_name' => 'Pembimbing']);
    $advisor = LecturerProfile::create([
        'user_id' => $advisorUser->id,
        'faculty_id' => $faculty->id,
        'study_program_id' => $program->id,
        'nidn' => '0401018502',
        'employment_status' => 'Tetap',
        'is_active' => true,
    ]);

    AcademicAdvisorAssignment::create([
        'student_profile_id' => $profile->id,
        'lecturer_profile_id' => $advisor->id,
        'academic_year_id' => $year->id,
        'start_date' => now()->subMonth()->toDateString(),
        'is_active' => true,
    ]);

    $otherUser = User::factory()->create(['first_name' => 'Dosen', 'last_name' => 'Lain']);
    $otherLecturer = LecturerProfile::create([
        'user_id' => $otherUser->id,
        'faculty_id' => $faculty->id,
        'study_program_id' => $program->id,
        'nidn' => '0401018503',
        'employment_status' => 'Tetap',
        'is_active' => true,
    ]);

    $tomorrow = now()->addDay()->startOfDay();
    $weekday = (int) $tomorrow->isoWeekday();

    $slots = [
        'course' => ConsultationSlot::create([
            'lecturer_profile_id' => $lecturer->id,
            'title' => 'Konsultasi Tugas',
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'slot_minutes' => 30,
            'capacity' => 2,
            'consultation_mode' => 'in_person',
            'location' => 'Ruang 201',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(21)->toDateString(),
            'is_active' => true,
        ]),
        'advisor' => ConsultationSlot::create([
            'lecturer_profile_id' => $advisor->id,
            'title' => 'Bimbingan Akademik',
            'weekday' => $weekday,
            'start_time' => '13:00',
            'end_time' => '14:00',
            'slot_minutes' => 30,
            'capacity' => 2,
            'consultation_mode' => 'online',
            'meeting_link' => 'https://meet.example.test/advisor',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(21)->toDateString(),
            'is_active' => true,
        ]),
        'other' => ConsultationSlot::create([
            'lecturer_profile_id' => $otherLecturer->id,
            'title' => 'Konsultasi Dosen Lain',
            'weekday' => $weekday,
            'start_time' => '15:00',
            'end_time' => '16:00',
            'slot_minutes' => 30,
            'capacity' => 2,
            'consultation_mode' => 'in_person',
            'location' => 'Ruang 305',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(21)->toDateString(),
            'is_active' => true,
        ]),
    ];

    return compact(
        'user',
        'profile',
        'program',
        'otherProgram',
        'year',
        'plan',
        'offerings',
        'activeAssignment',
        'missingAssignment',
        'droppedAssignment',
        'foreignAssignment',
        'lecturer',
        'advisor',
        'otherLecturer',
        'slots',
        'tomorrow',
    );
}

function acGet(User $user, string $routeName, array $params = [], array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get(route($routeName, $params + $query));
}

function acPost(User $user, string $routeName, array $params = [], array $payload = []): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->post(route($routeName, $params), $payload);
}

function acSecondStudent(array $setup): array
{
    $user = User::factory()->create(['first_name' => 'Budi', 'last_name' => 'Peserta']);
    $user->assignRole('student');

    $profile = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $setup['program']->id,
        'entry_academic_year_id' => $setup['year']->id,
        'nim' => '2026TI0399',
        'academic_status' => 'Aktif',
        'entry_date' => now()->subYear()->toDateString(),
        'current_semester' => 3,
        'is_active' => true,
    ]);

    $registration = StudentRegistration::create([
        'student_profile_id' => $profile->id,
        'academic_year_id' => $setup['year']->id,
        'semester_no' => 3,
        'registration_status' => 'Approved',
        'academic_status' => 'Aktif',
        'registration_date' => now()->subDays(5)->toDateString(),
        'submitted_at' => now()->subDays(4),
        'approved_at' => now()->subDays(3),
        'is_active' => true,
    ]);

    $plan = StudyPlan::create([
        'student_profile_id' => $profile->id,
        'academic_year_id' => $setup['year']->id,
        'student_registration_id' => $registration->id,
        'semester_no' => 3,
        'status' => 'Draft',
        'created_by' => $user->id,
    ]);

    StudyPlanDetail::create([
        'study_plan_id' => $plan->id,
        'course_offering_id' => $setup['offerings']['active']->id,
        'credits' => 3,
        'status' => 'Taken',
    ]);

    return [$user, $profile];
}

it('hanya menampilkan tugas dari kelas yang aktif di KRS', function () {
    $setup = assignmentConsultationSetup();

    acGet($setup['user'], 'student.assignments.index')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Assignments/Index')
            ->where('hasProfile', true)
            ->has('assignments', 2)
            ->where('stats.total', 2)
            ->where('assignments', function ($rows) {
                return collect($rows)->pluck('title')->sort()->values()->all() === [
                    'Tugas Aktif',
                    'Tugas Terlambat',
                ];
            }));

    acGet($setup['user'], 'student.assignments.index', [], ['search' => 'Dibatalkan'])
        ->assertInertia(fn (Assert $page) => $page->has('assignments', 0));

    acGet($setup['user'], 'student.assignments.index', [], ['status' => 'bogus'])
        ->assertSessionHasErrors('status');
});

it('menghitung statistik dari seluruh tugas sebelum filter diterapkan', function () {
    $setup = assignmentConsultationSetup();

    acGet($setup['user'], 'student.assignments.index', [], ['status' => 'open'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 1)
            ->where('assignments.0.title', 'Tugas Aktif')
            ->where('stats.total', 2)
            ->where('stats.open', 1)
            ->where('stats.late', 1)
            ->where('stats.returned', 0));
});

it('mengirim submission beserta berkas dan menyaring HTML berbahaya', function () {
    Storage::fake('public');
    $setup = assignmentConsultationSetup();

    acPost($setup['user'], 'student.assignments.submission.store', ['id' => $setup['activeAssignment']->id], [
        'content' => '<p>Jawaban <script>alert("xss")</script> lengkap.</p>',
        'files' => [UploadedFile::fake()->create('jawaban.pdf', 32, 'application/pdf')],
    ])->assertSessionHas('success');

    $submission = AssignmentSubmission::query()->first();

    expect($submission)->not->toBeNull()
        ->and($submission->student_profile_id)->toBe($setup['profile']->id)
        ->and($submission->content)->toContain('Jawaban')
        ->and($submission->content)->not->toContain('<script')
        ->and($submission->files)->toHaveCount(1)
        ->and($submission->status)->toBe('submitted');

    Storage::disk('public')->assertExists($submission->files->first()->file_path);

    acPost($setup['user'], 'student.assignments.submission.store', ['id' => $setup['foreignAssignment']->id], [
        'content' => '<p>Mencoba mengumpulkan di kelas lain.</p>',
    ])->assertForbidden();

    acPost($setup['user'], 'student.assignments.submission.store', ['id' => $setup['droppedAssignment']->id], [
        'content' => '<p>Mencoba mengumpulkan di kelas yang dibatalkan.</p>',
    ])->assertForbidden();
});

it('menolak submission setelah dinilai dan membuka kembali setelah revisi', function () {
    Storage::fake('public');
    $setup = assignmentConsultationSetup();
    $assignment = $setup['activeAssignment'];

    acPost($setup['user'], 'student.assignments.submission.store', ['id' => $assignment->id], [
        'content' => '<p>Jawaban pertama.</p>',
    ])->assertSessionHas('success');

    $submission = AssignmentSubmission::query()->firstOrFail();

    AssignmentGrade::create([
        'assignment_submission_id' => $submission->id,
        'score' => 85,
        'feedback' => '<p>Bagus.</p>',
        'status' => 'graded',
        'graded_at' => now(),
    ]);

    acPost($setup['user'], 'student.assignments.submission.store', ['id' => $assignment->id], [
        'content' => '<p>Mengubah setelah dinilai.</p>',
    ])->assertSessionHasErrors('content');

    $submission->grade->update(['status' => 'returned', 'return_note' => 'Perbaiki analisis.']);

    acPost($setup['user'], 'student.assignments.submission.store', ['id' => $assignment->id], [
        'content' => '<p>Jawaban revisi.</p>',
    ])->assertSessionHas('success');

    $submission->refresh();

    expect($submission->content)->toContain('Jawaban revisi')
        ->and($submission->grade->status)->toBe('resubmitted')
        ->and($submission->grade->score)->toBeNull();
});

it('membatasi hapus lampiran submission hanya untuk pemilik dan saat masih bisa submit', function () {
    Storage::fake('public');
    $setup = assignmentConsultationSetup();

    acPost($setup['user'], 'student.assignments.submission.store', ['id' => $setup['activeAssignment']->id], [
        'files' => [UploadedFile::fake()->create('lampiran.pdf', 16, 'application/pdf')],
    ])->assertSessionHas('success');

    $file = AssignmentSubmissionFile::query()->firstOrFail();

    [$otherUser] = acSecondStudent($setup);

    test()->actingAs($otherUser)
        ->withSession(['active_role' => 'student'])
        ->delete(route('student.assignments.submissions.files.destroy', $file->id))
        ->assertForbidden();

    test()->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->delete(route('student.assignments.submissions.files.destroy', $file->id))
        ->assertSessionHas('success');

    expect(AssignmentSubmissionFile::query()->count())->toBe(0);

    // Setelah dinilai, lampiran tidak boleh dihapus lagi.
    $submission = AssignmentSubmission::query()->firstOrFail();
    $file = AssignmentSubmissionFile::create([
        'assignment_submission_id' => $submission->id,
        'file_path' => 'assignments/submissions/locked.pdf',
        'file_name' => 'locked.pdf',
        'file_type' => 'pdf',
        'file_size' => 128,
    ]);

    AssignmentGrade::create([
        'assignment_submission_id' => $submission->id,
        'score' => 90,
        'status' => 'graded',
        'graded_at' => now(),
    ]);

    test()->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->delete(route('student.assignments.submissions.files.destroy', $file->id))
        ->assertSessionHasErrors('files');
});

it('menampilkan detail tugas beserta instruksi dan menolak kelas asing', function () {
    $setup = assignmentConsultationSetup();

    acGet($setup['user'], 'student.assignments.show', ['id' => $setup['activeAssignment']->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Assignments/Show')
            ->where('assignment.title', 'Tugas Aktif')
            ->where('assignment.canSubmit', true)
            ->where('assignment.statusLabel', 'Belum Submit')
            ->has('instructionFiles', 1)
            ->where('instructionFiles.0.fileName', 'instruksi.pdf')
            ->where('submission', null));

    acGet($setup['user'], 'student.assignments.show', ['id' => $setup['foreignAssignment']->id])
        ->assertForbidden();

    acGet($setup['user'], 'student.assignments.show', ['id' => $setup['droppedAssignment']->id])
        ->assertForbidden();
});

it('hanya menampilkan slot dari dosen pengampu aktif dan pembimbing', function () {
    $setup = assignmentConsultationSetup();

    acGet($setup['user'], 'student.consultations.index')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Consultations/Index')
            // 3 kemunculan tanggal (besok, +8, +15 hari) x 2 jam x 2 dosen.
            ->has('slots', 12)
            ->where('stats.availableSlots', 12)
            ->where('slots', function ($slots) {
                $lecturers = collect($slots)->pluck('lecturer')->unique()->sort()->values()->all();

                return $lecturers === ['Dosen Pembimbing', 'Dosen Pengampu'];
            }));
});

it('mengajukan konsultasi dan menolak jadwal yang tidak valid', function () {
    $setup = assignmentConsultationSetup();
    $date = $setup['tomorrow']->toDateString();

    acPost($setup['user'], 'student.consultations.book', ['slot' => $setup['slots']['course']->id], [
        'date' => $date,
        'time' => '09:00',
        'topic' => 'Konsultasi rencana studi',
        'notes' => 'Ingin membahas KRS.',
    ])->assertSessionHas('success');

    $appointment = ConsultationAppointment::query()->first();

    expect($appointment)->not->toBeNull()
        ->and($appointment->status)->toBe('requested')
        ->and($appointment->student_profile_id)->toBe($setup['profile']->id)
        ->and($appointment->lecturer_profile_id)->toBe($setup['lecturer']->id);

    // Request aktif pada slot yang sama tidak boleh ditimpa.
    acPost($setup['user'], 'student.consultations.book', ['slot' => $setup['slots']['course']->id], [
        'date' => $date,
        'time' => '09:00',
        'topic' => 'Mengubah topik',
    ])->assertSessionHasErrors('topic');

    expect(ConsultationAppointment::query()->count())->toBe(1)
        ->and($appointment->fresh()->topic)->toBe('Konsultasi rencana studi');

    // Mahasiswa lain tidak boleh mengambil sesi yang sudah dibooking.
    [$otherUser, $otherProfile] = acSecondStudent($setup);

    acPost($otherUser, 'student.consultations.book', ['slot' => $setup['slots']['course']->id], [
        'date' => $date,
        'time' => '09:00',
        'topic' => 'Mencoba sesi yang sama',
    ])->assertSessionHasErrors('topic');

    expect(ConsultationAppointment::query()->where('student_profile_id', $otherProfile->id)->count())->toBe(0);

    // Waktu harus selaras dengan kelipatan slot_minutes.
    acPost($setup['user'], 'student.consultations.book', ['slot' => $setup['slots']['course']->id], [
        'date' => $date,
        'time' => '09:15',
        'topic' => 'Waktu tidak selaras',
    ])->assertSessionHasErrors('time');

    // Dosen yang tidak terhubung dengan mahasiswa ditolak.
    acPost($setup['user'], 'student.consultations.book', ['slot' => $setup['slots']['other']->id], [
        'date' => $date,
        'time' => '15:00',
        'topic' => 'Dosen asing',
    ])->assertForbidden();

    // Tanggal yang tidak sesuai jadwal mingguan ditolak.
    acPost($setup['user'], 'student.consultations.book', ['slot' => $setup['slots']['course']->id], [
        'date' => $setup['tomorrow']->copy()->addDay()->toDateString(),
        'time' => '09:00',
        'topic' => 'Tanggal salah',
    ])->assertStatus(422);
});

it('membatalkan appointment hanya milik sendiri', function () {
    $setup = assignmentConsultationSetup();

    $appointment = ConsultationAppointment::create([
        'consultation_slot_id' => $setup['slots']['course']->id,
        'lecturer_profile_id' => $setup['lecturer']->id,
        'student_profile_id' => $setup['profile']->id,
        'appointment_date' => $setup['tomorrow']->toDateString(),
        'starts_at' => $setup['tomorrow']->copy()->setTime(9, 0),
        'ends_at' => $setup['tomorrow']->copy()->setTime(9, 30),
        'status' => 'requested',
        'topic' => 'Konsultasi awal',
        'requested_at' => now(),
    ]);

    [$otherUser] = acSecondStudent($setup);

    acPost($otherUser, 'student.consultations.cancel', ['appointment' => $appointment->id])
        ->assertForbidden();

    acPost($setup['user'], 'student.consultations.cancel', ['appointment' => $appointment->id])
        ->assertSessionHas('success');

    $appointment->refresh();

    expect($appointment->status)->toBe('cancelled')
        ->and($appointment->cancelled_at)->not->toBeNull();

    // Appointment yang sudah dibatalkan tidak bisa dibatalkan dua kali.
    acPost($setup['user'], 'student.consultations.cancel', ['appointment' => $appointment->id])
        ->assertSessionHas('error');
});
