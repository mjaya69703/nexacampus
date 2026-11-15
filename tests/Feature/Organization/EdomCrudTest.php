<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\EdomAnswer;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Models\Organization\EdomResponse;
use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function edomCrudSetup(): User
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

function edomCrudPermission(string ...$names): void
{
    $role = Role::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
}

function edomCrudActing(User $user)
{
    return test()->actingAs($user)->withSession(['active_role' => 'admin']);
}

/**
 * Bangun konteks akademik minimal untuk membuat satu respons EDOM.
 */
function edomCrudResponse(EdomPeriod $period): EdomResponse
{
    $faculty = Faculty::create(['name' => 'Fakultas EDOM', 'code' => 'FT-EDOM-CRUD', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Prodi EDOM', 'code' => 'SI-EDOM-CRUD', 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027 Ganjil', 'code' => '2026-EDOM-CRUD', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $course = Course::create(['code' => 'EDM401', 'name' => 'Evaluasi Pembelajaran', 'credits' => 3, 'is_active' => true]);
    $offering = CourseOffering::create(['academic_year_id' => $year->id, 'study_program_id' => $program->id, 'course_id' => $course->id, 'label' => 'Reguler A', 'code' => 'EDM401-A', 'credits' => 3, 'status' => 'Open']);
    $lecturer = LecturerProfile::create(['user_id' => User::factory()->create()->id, 'faculty_id' => $faculty->id, 'study_program_id' => $program->id, 'nidn' => 'EDOM-CRUD-001', 'is_active' => true]);
    $student = StudentProfile::create(['user_id' => User::factory()->create()->id, 'study_program_id' => $program->id, 'entry_academic_year_id' => $year->id, 'nim' => 'EDOM-CRUD-001', 'is_active' => true]);

    return EdomResponse::create([
        'edom_period_id' => $period->id,
        'course_offering_id' => $offering->id,
        'lecturer_profile_id' => $lecturer->id,
        'student_profile_id' => $student->id,
        'submitted_at' => now(),
    ]);
}

it('menampilkan daftar periode EDOM', function () {
    $user = edomCrudSetup();
    edomCrudPermission('edom-period.viewAny');

    edomCrudActing($user)
        ->get('/admin/organization/edom-periods')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EdomPeriods/Index')
            ->has('data.rows'));
});

it('membuat dan memperbarui periode EDOM', function () {
    $user = edomCrudSetup();
    edomCrudPermission('edom-period.create', 'edom-period.update');

    edomCrudActing($user)
        ->post('/admin/organization/edom-periods', [
            'name' => 'EDOM Ganjil 2025/2026',
            'code' => 'EDOM_20251',
            'status' => 'open',
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
            'minimum_responses' => 5,
            'notes' => 'Periode uji.',
        ])
        ->assertRedirect('/admin/organization/edom-periods')
        ->assertSessionHas('success');

    $period = EdomPeriod::where('code', 'EDOM_20251')->firstOrFail();

    edomCrudActing($user)
        ->put("/admin/organization/edom-periods/{$period->id}", [
            'name' => 'EDOM Ganjil 2025/2026 (Revisi)',
            'code' => 'EDOM_20251',
            'status' => 'closed',
            'minimum_responses' => 3,
        ])
        ->assertRedirect('/admin/organization/edom-periods')
        ->assertSessionHas('success');

    expect($period->fresh())
        ->name->toBe('EDOM Ganjil 2025/2026 (Revisi)')
        ->status->toBe('closed')
        ->minimum_responses->toBe(3);
});

it('menolak kode periode EDOM duplikat dan tanggal selesai sebelum mulai', function () {
    $user = edomCrudSetup();
    edomCrudPermission('edom-period.create');

    EdomPeriod::create([
        'name' => 'EDOM Eksisting', 'code' => 'EDOM_EXIST', 'status' => 'draft', 'minimum_responses' => 3,
    ]);

    edomCrudActing($user)
        ->post('/admin/organization/edom-periods', [
            'name' => 'EDOM Duplikat', 'code' => 'EDOM_EXIST', 'status' => 'draft', 'minimum_responses' => 3,
        ])
        ->assertSessionHasErrors('code');

    edomCrudActing($user)
        ->post('/admin/organization/edom-periods', [
            'name' => 'EDOM Rentang Salah', 'code' => 'EDOM_RANGE', 'status' => 'draft', 'minimum_responses' => 3,
            'starts_at' => '2026-09-10', 'ends_at' => '2026-09-01',
        ])
        ->assertSessionHasErrors('ends_at');
});

it('menolak hapus periode EDOM yang sudah memiliki respons', function () {
    $user = edomCrudSetup();
    edomCrudPermission('edom-period.delete');

    $period = EdomPeriod::create([
        'name' => 'EDOM Aktif', 'code' => 'EDOM_ACTIVE', 'status' => 'open', 'minimum_responses' => 3,
    ]);

    edomCrudResponse($period);

    edomCrudActing($user)
        ->delete("/admin/organization/edom-periods/{$period->id}")
        ->assertSessionHas('error');

    expect(EdomPeriod::find($period->id))->not->toBeNull();
});

it('menampilkan daftar pertanyaan EDOM', function () {
    $user = edomCrudSetup();
    edomCrudPermission('edom-question.viewAny');

    edomCrudActing($user)
        ->get('/admin/organization/edom-questions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EdomQuestions/Index')
            ->has('data.rows'));
});

it('membuat, memperbarui, dan menonaktifkan pertanyaan EDOM', function () {
    $user = edomCrudSetup();
    edomCrudPermission('edom-question.create', 'edom-question.update');

    edomCrudActing($user)
        ->post('/admin/organization/edom-questions', [
            'category' => 'teaching',
            'question_text' => 'Dosen menyampaikan materi dengan jelas.',
            'answer_type' => 'scale',
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/organization/edom-questions')
        ->assertSessionHas('success');

    $question = EdomQuestion::where('question_text', 'Dosen menyampaikan materi dengan jelas.')->firstOrFail();

    edomCrudActing($user)
        ->put("/admin/organization/edom-questions/{$question->id}", [
            'category' => 'material',
            'question_text' => 'Materi perkuliahan relevan dengan capaian pembelajaran.',
            'answer_type' => 'scale',
            'sort_order' => 2,
            'is_required' => true,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/organization/edom-questions')
        ->assertSessionHas('success');

    expect($question->fresh())
        ->category->toBe('material')
        ->sort_order->toBe(2);

    edomCrudActing($user)
        ->post("/admin/organization/edom-questions/{$question->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect($question->fresh()->is_active)->toBeFalse();
});

it('menolak hapus pertanyaan EDOM yang sudah memiliki jawaban', function () {
    $user = edomCrudSetup();
    edomCrudPermission('edom-question.delete');

    $period = EdomPeriod::create([
        'name' => 'EDOM Jawaban', 'code' => 'EDOM_ANSWER', 'status' => 'open', 'minimum_responses' => 1,
    ]);
    $question = EdomQuestion::create([
        'category' => 'general', 'question_text' => 'Pertanyaan dengan jawaban.', 'answer_type' => 'scale',
        'sort_order' => 1, 'is_required' => true, 'is_active' => true,
    ]);
    $response = edomCrudResponse($period);

    EdomAnswer::create([
        'edom_response_id' => $response->id,
        'edom_question_id' => $question->id,
        'score' => 5,
    ]);

    edomCrudActing($user)
        ->delete("/admin/organization/edom-questions/{$question->id}")
        ->assertSessionHas('error');

    expect(EdomQuestion::find($question->id))->not->toBeNull();
});
