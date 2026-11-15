<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocument;
use App\Models\Admission\AdmissionExamParticipant;
use App\Models\Admission\AdmissionExamSchedule;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Admission\AdmissionQuota;
use App\Models\Admission\NimGenerationRule;
use App\Models\Settings\System;
use App\Models\User;
use App\Support\Admission\AdmissionSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function admissionBatchTwoSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);
    SpatieRole::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $year = AcademicYear::create([
        'name' => '2026/2027 Ganjil', 'code' => 'Y26G', 'semester' => 'Ganjil',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
    ]);

    $faculty = Faculty::create([
        'name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true,
    ]);

    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'Informatika', 'code' => 'IF',
        'degree' => 'S1', 'is_active' => true,
    ]);

    $period = AdmissionPeriod::create([
        'name' => 'PMB 2026 Gelombang 1', 'code' => 'ADM2026W1', 'academic_year_id' => $year->id,
        'academic_year' => 2026, 'wave' => 1, 'opens_at' => '2026-01-01', 'closes_at' => '2026-03-01',
        'is_active' => true, 'is_published' => true,
    ]);

    return compact('operator', 'year', 'faculty', 'program', 'period');
}

function giveAdmissionBatchTwoPermissions(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAdmissionBatchTwoOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function admissionBatchTwoApplication(AdmissionPeriod $period, string $number = 'APP-1', array $overrides = []): AdmissionApplication
{
    return AdmissionApplication::create(array_merge([
        'admission_period_id' => $period->id,
        'application_number' => $number,
        'access_token' => 'token-'.$number,
        'full_name' => 'Calon Mahasiswa '.$number,
        'email' => strtolower($number).'@example.test',
        'phone' => '08123456789',
        'birth_date' => '2008-05-01',
        'gender' => 'male',
        'address' => 'Jl. Contoh No. 1',
        'emergency_contact_name' => 'Orang Tua',
        'emergency_contact_phone' => '08129876543',
        'high_school_name' => 'SMA Negeri 1',
        'high_school_major' => 'IPA',
        'high_school_graduation_year' => 2026,
        'status' => 'submitted',
        'submitted_at' => now(),
    ], $overrides));
}

function admissionBatchTwoSchedule(AdmissionPeriod $period, array $overrides = []): AdmissionExamSchedule
{
    return AdmissionExamSchedule::create(array_merge([
        'admission_period_id' => $period->id,
        'exam_type' => 'written_test',
        'title' => 'Tes Tertulis Gelombang 1',
        'exam_date' => '2026-02-10',
        'exam_time' => '08:00',
        'venue' => 'Gedung A - Ruang 101',
        'quota' => 2,
        'notes' => 'Bawa alat tulis.',
        'is_active' => true,
    ], $overrides));
}

it('menampilkan, menyimpan, mengubah, dan mengekspor jadwal ujian', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions([
        'admission-exam-schedule.viewAny', 'admission-exam-schedule.create',
        'admission-exam-schedule.update', 'admission-exam-schedule.view',
    ]);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->get('/admin/admission/admission-exam-schedules')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Admission/ExamSchedule/Index')
            ->has('data.rows', 0));

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->get('/admin/admission/admission-exam-schedules/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Admission/ExamSchedule/Form'));

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post('/admin/admission/admission-exam-schedules', [
            'admission_period_id' => $setup['period']->id,
            'exam_type' => 'written_test',
            'title' => 'Tes Tertulis Gelombang 1',
            'exam_date' => '2026-02-10',
            'exam_time' => '08:00',
            'venue' => 'Gedung A - Ruang 101',
            'quota' => 2,
            'notes' => 'Bawa alat tulis.',
            'is_active' => true,
        ])
        ->assertRedirect('/admin/admission/admission-exam-schedules')
        ->assertSessionHas('success');

    $schedule = AdmissionExamSchedule::firstOrFail();

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->put("/admin/admission/admission-exam-schedules/{$schedule->id}", [
            'admission_period_id' => $setup['period']->id,
            'exam_type' => 'interview',
            'title' => 'Wawancara Gelombang 1',
            'exam_date' => '2026-02-11',
            'exam_time' => '09:30',
            'venue' => 'Gedung B',
            'quota' => 5,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/admission/admission-exam-schedules')
        ->assertSessionHas('success');

    $schedule->refresh();

    expect($schedule->title)->toBe('Wawancara Gelombang 1')
        ->and($schedule->exam_type)->toBe('interview')
        ->and($schedule->quota)->toBe(5);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect($schedule->refresh()->is_active)->toBeFalse();

    $response = actingAdmissionBatchTwoOperator($setup['operator'])
        ->get('/admin/admission/admission-exam-schedules/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengelola peserta ujian, kehadiran, dan nilai seleksi', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions([
        'admission-exam-schedule.view', 'admission-exam-schedule.update',
    ]);

    $schedule = admissionBatchTwoSchedule($setup['period']);
    $first = admissionBatchTwoApplication($setup['period'], 'APP-1');
    $second = admissionBatchTwoApplication($setup['period'], 'APP-2');

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->get("/admin/admission/admission-exam-schedules/{$schedule->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Admission/ExamSchedule/Show')
            ->has('schedule.participants', 0));

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/participants", [
            'application_id' => $first->id,
        ])
        ->assertSessionHas('success');

    expect($schedule->refresh()->registered_count)->toBe(1);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/participants", [
            'application_id' => $first->id,
        ])
        ->assertSessionHas('error');

    expect(AdmissionExamParticipant::count())->toBe(1);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/participants/assign-all")
        ->assertSessionHas('success');

    expect(AdmissionExamParticipant::count())->toBe(2)
        ->and($schedule->refresh()->registered_count)->toBe(2);

    $participant = $schedule->participants()->where('admission_application_id', $first->id)->firstOrFail();

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->patch("/admin/admission/admission-exam-schedules/{$schedule->id}/participants/{$participant->id}/attendance", [
            'attendance_status' => 'present',
            'notes' => 'Hadir tepat waktu.',
        ])
        ->assertSessionHas('success');

    expect($participant->refresh()->attendance_status)->toBe('present')
        ->and($participant->notes)->toBe('Hadir tepat waktu.');

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/participants/{$participant->id}/score", [
            'score_type' => 'written_test',
            'score' => 80,
            'weight' => 100,
            'notes' => 'Jawaban lengkap.',
        ])
        ->assertSessionHas('success');

    expect((float) $first->refresh()->final_score)->toBe(80.0)
        ->and($first->scores()->count())->toBe(1);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->delete("/admin/admission/admission-exam-schedules/{$schedule->id}/participants/{$participant->id}")
        ->assertSessionHas('success');

    expect(AdmissionExamParticipant::count())->toBe(1)
        ->and($schedule->refresh()->registered_count)->toBe(1);
});

it('menolak peserta dari gelombang lain dan kuota yang penuh', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions(['admission-exam-schedule.update']);

    $otherPeriod = AdmissionPeriod::create([
        'name' => 'PMB 2026 Gelombang 2', 'code' => 'ADM2026W2', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 2, 'opens_at' => '2026-04-01', 'closes_at' => '2026-05-01',
    ]);

    $schedule = admissionBatchTwoSchedule($setup['period'], ['quota' => 1]);
    $outsider = admissionBatchTwoApplication($otherPeriod, 'APP-X');
    $first = admissionBatchTwoApplication($setup['period'], 'APP-1');
    $second = admissionBatchTwoApplication($setup['period'], 'APP-2');

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/participants", [
            'application_id' => $outsider->id,
        ])
        ->assertSessionHas('error');

    expect(AdmissionExamParticipant::count())->toBe(0);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/participants", [
            'application_id' => $first->id,
        ])
        ->assertSessionHas('success');

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/participants", [
            'application_id' => $second->id,
        ])
        ->assertSessionHas('error');

    expect(AdmissionExamParticipant::count())->toBe(1);
});

it('mengelola sampah jadwal ujian dan menghapus permanen', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions([
        'admission-exam-schedule.viewAny', 'admission-exam-schedule.delete', 'admission-exam-schedule.update',
    ]);

    $schedule = admissionBatchTwoSchedule($setup['period']);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->delete("/admin/admission/admission-exam-schedules/{$schedule->id}")
        ->assertRedirect('/admin/admission/admission-exam-schedules');

    expect(AdmissionExamSchedule::find($schedule->id))->toBeNull()
        ->and(AdmissionExamSchedule::withTrashed()->find($schedule->id))->not->toBeNull();

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->get('/admin/admission/admission-exam-schedules?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-exam-schedules/{$schedule->id}/restore")
        ->assertSessionHas('success');

    expect(AdmissionExamSchedule::find($schedule->id))->not->toBeNull();

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->delete("/admin/admission/admission-exam-schedules/{$schedule->id}")
        ->assertRedirect('/admin/admission/admission-exam-schedules');

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->delete("/admin/admission/admission-exam-schedules/{$schedule->id}/force")
        ->assertSessionHas('success');

    expect(AdmissionExamSchedule::withTrashed()->find($schedule->id))->toBeNull();
});

it('menampilkan pendaftar, memperbarui review, dan memverifikasi dokumen', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions([
        'admission-application.viewAny', 'admission-application.view', 'admission-application.update',
    ]);

    $application = admissionBatchTwoApplication($setup['period'], 'APP-1', [
        'faculty_id' => $setup['faculty']->id,
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
    ]);

    $document = AdmissionDocument::create([
        'admission_application_id' => $application->id,
        'document_type' => 'id_card',
        'file_path' => 'admission/documents/id-card.pdf',
        'file_name' => 'id-card.pdf',
        'file_size' => 2048,
        'verification_status' => 'pending',
    ]);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->get('/admin/admission/admission-applications')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Admission/Application/Index')
            ->has('data.rows', 1));

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->get("/admin/admission/admission-applications/{$application->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Admission/Application/Show')
            ->where('application.applicationNumber', 'APP-1')
            ->has('application.documents', 1));

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->put("/admin/admission/admission-applications/{$application->id}/review", [
            'status' => 'under_review',
            'review_notes' => 'Dokumen utama lengkap, lanjut wawancara.',
            'final_score' => 78.5,
        ])
        ->assertSessionHas('success');

    $application->refresh();

    expect($application->status)->toBe('under_review')
        ->and((float) $application->final_score)->toBe(78.5)
        ->and($application->statusHistories()->count())->toBe(1);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->patch("/admin/admission/admission-applications/{$application->id}/documents/{$document->id}/verify", [
            'status' => 'verified',
            'notes' => 'Sesuai dengan data pendaftar.',
        ])
        ->assertSessionHas('success');

    $document->refresh();

    expect($document->verification_status)->toBe('verified')
        ->and($document->verified_by)->toBe($setup['operator']->id)
        ->and($document->verified_at)->not->toBeNull();

    $response = actingAdmissionBatchTwoOperator($setup['operator'])
        ->get('/admin/admission/admission-applications/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('menghapus, memulihkan, dan menghapus permanen pendaftar', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions([
        'admission-application.viewAny', 'admission-application.delete',
    ]);

    $application = admissionBatchTwoApplication($setup['period']);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->delete("/admin/admission/admission-applications/{$application->id}")
        ->assertRedirect('/admin/admission/admission-applications');

    expect(AdmissionApplication::find($application->id))->toBeNull();

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->get('/admin/admission/admission-applications?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-applications/{$application->id}/restore")
        ->assertSessionHas('success');

    expect(AdmissionApplication::find($application->id))->not->toBeNull();

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->delete("/admin/admission/admission-applications/{$application->id}")
        ->assertRedirect('/admin/admission/admission-applications');

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->delete("/admin/admission/admission-applications/{$application->id}/force")
        ->assertSessionHas('success');

    expect(AdmissionApplication::withTrashed()->find($application->id))->toBeNull();
});

it('meranking pendaftar dan menegakkan kuota saat keputusan seleksi', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions([
        'admission-selection.viewAny', 'admission-selection.update',
    ]);

    $quota = AdmissionQuota::create([
        'admission_period_id' => $setup['period']->id,
        'faculty_id' => $setup['faculty']->id,
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'quota' => 1,
    ]);

    $accepted = admissionBatchTwoApplication($setup['period'], 'APP-1', [
        'faculty_id' => $setup['faculty']->id,
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'status' => 'accepted',
        'final_score' => 90,
    ]);

    $candidate = admissionBatchTwoApplication($setup['period'], 'APP-2', [
        'faculty_id' => $setup['faculty']->id,
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'status' => 'under_review',
        'final_score' => 70,
    ]);

    app(AdmissionSelectionService::class)->refreshAcceptedCounts($setup['period']->id);

    expect($quota->refresh()->accepted_count)->toBe(1);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->get('/admin/admission/admission-selection')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Admission/Selection/Index')
            ->has('data.rows', 2)
            ->where('data.rows', fn ($rows) => collect($rows)->firstWhere('id', $candidate->id)['quotaFull'] === true));

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-selection/{$candidate->id}/decision", ['status' => 'accepted'])
        ->assertSessionHas('error');

    expect($candidate->refresh()->status)->toBe('under_review');

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-selection/{$candidate->id}/decision", ['status' => 'waitlisted'])
        ->assertSessionHas('success');

    expect($candidate->refresh()->status)->toBe('waitlisted');

    $accepted->update(['status' => 'rejected']);
    app(AdmissionSelectionService::class)->refreshAcceptedCounts($setup['period']->id);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-selection/{$candidate->id}/decision", ['status' => 'accepted'])
        ->assertSessionHas('success');

    expect($candidate->refresh()->status)->toBe('accepted');
});

it('menerapkan keputusan seleksi massal dan melewati kuota penuh', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions(['admission-selection.update']);

    AdmissionQuota::create([
        'admission_period_id' => $setup['period']->id,
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'quota' => 1,
    ]);

    $accepted = admissionBatchTwoApplication($setup['period'], 'APP-1', [
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'status' => 'accepted',
        'final_score' => 90,
    ]);

    $first = admissionBatchTwoApplication($setup['period'], 'APP-2', [
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'status' => 'under_review',
        'final_score' => 80,
    ]);

    $second = admissionBatchTwoApplication($setup['period'], 'APP-3', [
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'status' => 'under_review',
        'final_score' => 75,
    ]);

    app(AdmissionSelectionService::class)->refreshAcceptedCounts($setup['period']->id);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post('/admin/admission/admission-selection/bulk-decision', [
            'ids' => [$first->id, $second->id],
            'status' => 'accepted',
        ])
        ->assertSessionHas('error');

    expect($first->refresh()->status)->toBe('under_review')
        ->and($second->refresh()->status)->toBe('under_review');

    $accepted->update(['status' => 'rejected']);
    app(AdmissionSelectionService::class)->refreshAcceptedCounts($setup['period']->id);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post('/admin/admission/admission-selection/bulk-decision', [
            'ids' => [$first->id, $second->id],
            'status' => 'accepted',
        ])
        ->assertSessionHas('success');

    expect($first->refresh()->status)->toBe('accepted')
        ->and($second->refresh()->status)->toBe('accepted');
});

it('mengonversi pendaftar accepted menjadi mahasiswa dengan NIM', function () {
    $setup = admissionBatchTwoSetup();
    giveAdmissionBatchTwoPermissions(['admission-application.update']);

    NimGenerationRule::create([
        'name' => 'NIM 2026', 'pattern' => '{year}{program_code}{sequence}',
        'sequence_scope' => 'study_program_year', 'sequence_padding' => 4,
        'sequence_start' => 1, 'is_active' => true,
    ]);

    $application = admissionBatchTwoApplication($setup['period'], 'APP-CONV', [
        'faculty_id' => $setup['faculty']->id,
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'status' => 'accepted',
    ]);

    actingAdmissionBatchTwoOperator($setup['operator'])
        ->post("/admin/admission/admission-applications/{$application->id}/convert")
        ->assertSessionHas('success');

    $application->refresh();

    expect($application->converted_at)->not->toBeNull()
        ->and($application->user_id)->not->toBeNull()
        ->and($application->user->studentProfile->nim)->toBe('2026IF0001');
});
