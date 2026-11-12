<?php

use App\Enums\ThesisDefenseResult;
use App\Enums\ThesisDefenseStatus;
use App\Enums\ThesisDocumentCategory;
use App\Enums\ThesisMilestoneStatus;
use App\Enums\ThesisStatus;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\Thesis;
use App\Models\Academic\ThesisDefense;
use App\Models\Academic\ThesisDocument;
use App\Models\Settings\System;
use App\Models\User;
use App\Support\Academic\ThesisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function thesisTestSetup(): array
{
    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    foreach (['operator', 'student', 'lecturer'] as $role) {
        SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $operator = User::factory()->create(['first_name' => 'Opera', 'last_name' => 'Akademik']);
    $operator->assignRole('operator');

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT-TH', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'Teknik Informatika', 'code' => 'TI-TH',
        'degree' => 'S1', 'is_active' => true,
    ]);
    $year = AcademicYear::create([
        'name' => '2026/2027 Ganjil', 'code' => 'Y26-TH', 'semester' => 'Ganjil',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
    ]);

    $studentUser = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Senior']);
    $studentUser->assignRole('student');
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => '2026TI9001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 7,
        'is_active' => true,
    ]);

    $lecturerUser = User::factory()->create(['first_name' => 'Budi', 'last_name' => 'Pembimbing']);
    $lecturerUser->assignRole('lecturer');
    $lecturer = LecturerProfile::create([
        'user_id' => $lecturerUser->id,
        'nidn' => '0412345678',
        'employment_status' => 'Tetap',
        'is_active' => true,
    ]);

    $examinerUser = User::factory()->create(['first_name' => 'Sari', 'last_name' => 'Penguji']);
    $examinerUser->assignRole('lecturer');
    $examiner = LecturerProfile::create([
        'user_id' => $examinerUser->id,
        'nidn' => '0498765432',
        'employment_status' => 'Tetap',
        'is_active' => true,
    ]);

    return compact(
        'operator', 'student', 'studentUser', 'lecturer', 'lecturerUser', 'examiner', 'examinerUser', 'program', 'year',
    );
}

/**
 * @param  array<int, string>  $permissions
 */
function giveThesisPermissions(array $permissions): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($permissions as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingThesisAdmin(User $operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeThesisForTest(array $setup, array $overrides = []): Thesis
{
    return app(ThesisService::class)->createProposal($setup['student'], [
        'academic_year_id' => $setup['year']->id,
        'type' => Thesis::TYPE_SKRIPSI,
        'title' => $overrides['title'] ?? 'Analisis Sistem Informasi Akademik',
        'topic' => $overrides['topic'] ?? 'Sistem Informasi',
        'abstract' => $overrides['abstract'] ?? 'Abstrak penelitian.',
        'keywords' => $overrides['keywords'] ?? 'siakad, laravel',
    ], $setup['studentUser']);
}

it('menampilkan daftar skripsi admin dengan statistik dan permission', function () {
    $setup = thesisTestSetup();
    giveThesisPermissions(['thesis.viewAny']);
    $thesis = makeThesisForTest($setup);

    actingThesisAdmin($setup['operator'])
        ->get(route('admin.academic.theses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Academic/Theses/Index')
            ->where('stats.total', 1)
            ->where('stats.submitted', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.title', $thesis->title)
            ->where('data.rows.0.nim', '2026TI9001')
            ->where('can.approve', false)
            ->where('can.view', false));
});

it('membuat pengajuan skripsi dari admin beserta milestone default', function () {
    $setup = thesisTestSetup();
    giveThesisPermissions(['thesis.create']);

    actingThesisAdmin($setup['operator'])
        ->post(route('admin.academic.theses.store'), [
            'student_profile_id' => $setup['student']->id,
            'academic_year_id' => $setup['year']->id,
            'type' => Thesis::TYPE_SKRIPSI,
            'title' => 'Implementasi Sistem Rekomendasi',
            'topic' => 'Kecerdasan Buatan',
            'abstract' => 'Penelitian tentang sistem rekomendasi.',
            'keywords' => 'rekomendasi, ai',
        ])
        ->assertSessionHas('success');

    $thesis = Thesis::firstOrFail();

    expect($thesis->status)->toBe(ThesisStatus::SUBMITTED->value)
        ->and($thesis->milestones()->count())->toBe(5)
        ->and($thesis->proposal_submitted_at)->not->toBeNull();
});

it('menyetujui pengajuan dan menetapkan pembimbing utama', function () {
    $setup = thesisTestSetup();
    giveThesisPermissions(['thesis.approve']);
    $thesis = makeThesisForTest($setup);

    actingThesisAdmin($setup['operator'])
        ->post(route('admin.academic.theses.approve', $thesis), [
            'supervisor_ids' => [$setup['lecturer']->id, $setup['examiner']->id],
            'notes' => 'Lanjutkan bimbingan.',
        ])
        ->assertSessionHas('success');

    $thesis->refresh();

    expect($thesis->status)->toBe(ThesisStatus::IN_PROGRESS->value)
        ->and($thesis->approved_by)->toBe($setup['operator']->id)
        ->and($thesis->supervisors()->where('is_active', true)->count())->toBe(2)
        ->and($thesis->supervisors()->where('role', 'primary')->value('lecturer_profile_id'))->toBe($setup['lecturer']->id)
        ->and($thesis->supervisors()->where('role', 'secondary')->value('lecturer_profile_id'))->toBe($setup['examiner']->id);
});

it('menolak pengajuan dengan alasan yang tersimpan', function () {
    $setup = thesisTestSetup();
    giveThesisPermissions(['thesis.approve']);
    $thesis = makeThesisForTest($setup);

    actingThesisAdmin($setup['operator'])
        ->post(route('admin.academic.theses.reject', $thesis), [
            'reason' => 'Topik terlalu luas, persempit ruang lingkup.',
        ])
        ->assertSessionHas('success');

    $thesis->refresh();

    expect($thesis->status)->toBe(ThesisStatus::REJECTED->value)
        ->and($thesis->rejection_reason)->toContain('terlalu luas');
});

it('mahasiswa mengajukan proposal dan hanya boleh punya satu skripsi aktif', function () {
    Storage::fake('public');
    $setup = thesisTestSetup();

    $this->actingAs($setup['studentUser'])
        ->withSession(['active_role' => 'student'])
        ->post(route('student.thesis.store'), [
            'academic_year_id' => $setup['year']->id,
            'type' => Thesis::TYPE_SKRIPSI,
            'title' => 'Sistem Monitoring Tugas Akhir',
            'topic' => 'Rekayasa Perangkat Lunak',
            'abstract' => 'Pengembangan sistem monitoring.',
            'keywords' => 'monitoring, tugas akhir',
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('student.thesis.index'))
        ->assertSessionHas('success');

    $thesis = Thesis::firstOrFail();
    $document = ThesisDocument::firstOrFail();

    expect($thesis->status)->toBe(ThesisStatus::SUBMITTED->value)
        ->and($thesis->proposal_file_name)->toBe('proposal.pdf')
        ->and($document->category)->toBe(ThesisDocumentCategory::PROPOSAL->value)
        ->and(Storage::disk('public')->exists($document->file_path))->toBeTrue();

    $this->actingAs($setup['studentUser'])
        ->withSession(['active_role' => 'student'])
        ->post(route('student.thesis.store'), [
            'academic_year_id' => $setup['year']->id,
            'type' => Thesis::TYPE_SKRIPSI,
            'title' => 'Judul Kedua',
        ])
        ->assertSessionHasErrors('student_profile_id');

    expect(Thesis::count())->toBe(1);
});

it('mahasiswa mengirim milestone lalu pembimbing mereview hingga siap sidang', function () {
    $setup = thesisTestSetup();
    $thesis = makeThesisForTest($setup);
    app(ThesisService::class)->approve($thesis, $setup['operator'], [$setup['lecturer']->id]);

    $milestones = $thesis->milestones()->orderBy('sequence')->get();

    $this->actingAs($setup['studentUser'])
        ->withSession(['active_role' => 'student'])
        ->post(route('student.thesis.milestones.submit', $milestones->first()), [
            'note' => 'Proposal sudah selesai.',
        ])
        ->assertSessionHas('success');

    expect($milestones->first()->fresh()->status)->toBe(ThesisMilestoneStatus::SUBMITTED->value);

    $this->actingAs($setup['lecturerUser'])
        ->withSession(['active_role' => 'lecturer'])
        ->post(route('lecturer.theses.milestones.review', $milestones->first()), [
            'status' => ThesisMilestoneStatus::REVISION->value,
            'feedback' => 'Perbaiki rumusan masalah.',
        ])
        ->assertSessionHas('success');

    expect($milestones->first()->fresh()->status)->toBe(ThesisMilestoneStatus::REVISION->value)
        ->and($thesis->fresh()->status)->toBe(ThesisStatus::IN_PROGRESS->value);

    $this->actingAs($setup['studentUser'])
        ->withSession(['active_role' => 'student'])
        ->post(route('student.thesis.milestones.submit', $milestones->first()), ['note' => 'Sudah diperbaiki.'])
        ->assertSessionHas('success');

    foreach ($milestones as $milestone) {
        $this->actingAs($setup['lecturerUser'])
            ->withSession(['active_role' => 'lecturer'])
            ->post(route('lecturer.theses.milestones.review', $milestone), [
                'status' => ThesisMilestoneStatus::APPROVED->value,
                'feedback' => 'Disetujui.',
            ])
            ->assertSessionHas('success');
    }

    expect($thesis->fresh()->status)->toBe(ThesisStatus::DEFENSE_READY->value)
        ->and($thesis->fresh()->allMilestonesApproved())->toBeTrue();
});

it('admin menjadwalkan sidang, menyelesaikan penilaian, lalu finalisasi dan publikasi', function () {
    $setup = thesisTestSetup();
    giveThesisPermissions(['thesis.defense', 'thesis.grade']);
    $thesis = makeThesisForTest($setup);
    app(ThesisService::class)->approve($thesis, $setup['operator'], [$setup['lecturer']->id]);

    actingThesisAdmin($setup['operator'])
        ->post(route('admin.academic.theses.defenses.store', $thesis), [
            'type' => 'result',
            'scheduled_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'duration_minutes' => 90,
            'room' => 'Ruang Sidang A',
            'examiners' => [
                ['lecturer_profile_id' => $setup['lecturer']->id, 'role' => 'chair'],
                ['lecturer_profile_id' => $setup['examiner']->id, 'role' => 'examiner'],
            ],
        ])
        ->assertSessionHas('success');

    $defense = ThesisDefense::firstOrFail();

    expect($defense->status)->toBe(ThesisDefenseStatus::SCHEDULED->value)
        ->and($defense->examiners()->count())->toBe(2)
        ->and($thesis->fresh()->status)->toBe(ThesisStatus::DEFENSE_READY->value);

    actingThesisAdmin($setup['operator'])
        ->post(route('admin.academic.theses.defenses.complete', $defense), [
            'result' => ThesisDefenseResult::PASSED->value,
            'score' => 85,
            'notes' => 'Presentasi lancar.',
            'examiner_scores' => [
                $defense->examiners()->where('role', 'chair')->value('id') => 86,
                $defense->examiners()->where('role', 'examiner')->value('id') => 84,
            ],
        ])
        ->assertSessionHas('success');

    $defense->refresh();
    $thesis->refresh();

    expect($defense->status)->toBe(ThesisDefenseStatus::COMPLETED->value)
        ->and($defense->result)->toBe(ThesisDefenseResult::PASSED->value)
        ->and((float) $defense->score)->toBe(85.0)
        ->and($defense->grade)->toBe('B+')
        ->and($thesis->status)->toBe(ThesisStatus::DEFENDED->value)
        ->and((float) $thesis->final_score)->toBe(85.0)
        ->and($thesis->final_grade)->toBe('B+');

    actingThesisAdmin($setup['operator'])
        ->post(route('admin.academic.theses.finalize', $thesis), [
            'final_score' => 85,
            'publish' => true,
        ])
        ->assertSessionHas('success');

    $thesis->refresh();

    expect($thesis->status)->toBe(ThesisStatus::COMPLETED->value)
        ->and($thesis->completed_at)->not->toBeNull()
        ->and($thesis->repository_published)->toBeTrue()
        ->and($thesis->repository_published_at)->not->toBeNull();
});

it('membatasi unduhan dokumen ke pemilik, pembimbing, penguji, dan admin berizin', function () {
    Storage::fake('public');
    $setup = thesisTestSetup();
    $thesis = makeThesisForTest($setup);
    app(ThesisService::class)->approve($thesis, $setup['operator'], [$setup['lecturer']->id]);

    $document = app(ThesisService::class)->storeDocument(
        $thesis,
        $setup['studentUser'],
        UploadedFile::fake()->create('bab-1.pdf', 80, 'application/pdf'),
        ['category' => ThesisDocumentCategory::CHAPTER->value, 'title' => 'Bab I'],
    );

    $this->actingAs($setup['studentUser'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.thesis.documents.download', $document))
        ->assertOk();

    $otherUser = User::factory()->create();
    $otherUser->assignRole('student');
    StudentProfile::create([
        'user_id' => $otherUser->id,
        'study_program_id' => $setup['program']->id,
        'nim' => '2026TI9002',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 7,
        'is_active' => true,
    ]);

    $this->actingAs($otherUser)
        ->withSession(['active_role' => 'student'])
        ->get(route('student.thesis.documents.download', $document))
        ->assertNotFound();

    $this->actingAs($setup['lecturerUser'])
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.theses.documents.download', $document))
        ->assertOk();

    giveThesisPermissions(['thesis.view']);

    actingThesisAdmin($setup['operator'])
        ->get(route('admin.academic.theses.documents.download', $document))
        ->assertOk();

    $viewer = User::factory()->create();
    SpatieRole::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->withSession(['active_role' => 'viewer'])
        ->get(route('admin.academic.theses.documents.download', $document))
        ->assertForbidden();
});

it('menampilkan detail bimbingan dosen dan menolak dosen yang tidak terkait', function () {
    $setup = thesisTestSetup();
    $thesis = makeThesisForTest($setup);
    app(ThesisService::class)->approve($thesis, $setup['operator'], [$setup['lecturer']->id]);

    app(ThesisService::class)->scheduleDefense($thesis, [
        'type' => 'proposal',
        'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'duration_minutes' => 90,
        'room' => 'Ruang Seminar',
        'examiners' => [
            ['lecturer_profile_id' => $setup['lecturer']->id, 'role' => 'chair'],
            ['lecturer_profile_id' => $setup['examiner']->id, 'role' => 'examiner'],
        ],
    ], $setup['operator']);

    $this->actingAs($setup['lecturerUser'])
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.theses.show', $thesis))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lecturer/Theses/Show')
            ->where('thesis.id', $thesis->id)
            ->where('thesis.student.nim', '2026TI9001')
            ->has('thesis.milestones', 5)
            ->where('thesis.supervisors.0.isMe', true)
            ->has('thesis.defenses', 1)
            ->where('thesis.defenses.0.myExaminer.role', 'chair')
            ->where('thesis.defenses.0.myExaminer.canScore', true));

    $this->actingAs($setup['examinerUser'])
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.theses.show', $thesis))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lecturer/Theses/Show')
            ->where('thesis.defenses.0.myExaminer.role', 'examiner')
            ->where('thesis.defenses.0.myExaminer.canScore', true));

    $outsiderUser = User::factory()->create();
    $outsiderUser->assignRole('lecturer');
    LecturerProfile::create([
        'user_id' => $outsiderUser->id,
        'nidn' => '0400000001',
        'employment_status' => 'Tetap',
        'is_active' => true,
    ]);

    $this->actingAs($outsiderUser)
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.theses.show', $thesis))
        ->assertNotFound();
});

it('merender halaman admin create, edit, dan detail skripsi', function () {
    $setup = thesisTestSetup();
    giveThesisPermissions(['thesis.view', 'thesis.create', 'thesis.update']);
    $thesis = makeThesisForTest($setup);

    actingThesisAdmin($setup['operator'])
        ->get(route('admin.academic.theses.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Academic/Theses/Form')
            ->where('mode', 'create')
            ->has('years'));

    actingThesisAdmin($setup['operator'])
        ->get(route('admin.academic.theses.edit', $thesis))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Academic/Theses/Form')
            ->where('mode', 'edit')
            ->where('thesis.id', $thesis->id));

    actingThesisAdmin($setup['operator'])
        ->get(route('admin.academic.theses.show', $thesis))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Academic/Theses/Show')
            ->where('thesis.id', $thesis->id)
            ->where('thesis.student.nim', '2026TI9001')
            ->has('thesis.milestones', 5)
            ->has('options.defenseTypes'));
});

it('merender portal mahasiswa sebelum dan sesudah pengajuan', function () {
    $setup = thesisTestSetup();

    $this->actingAs($setup['studentUser'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.thesis.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Thesis/Index')
            ->where('hasProfile', true)
            ->where('thesis', null)
            ->has('years'));

    $thesis = makeThesisForTest($setup);

    $this->actingAs($setup['studentUser'])
        ->withSession(['active_role' => 'student'])
        ->get(route('student.thesis.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Thesis/Index')
            ->where('thesis.id', $thesis->id)
            ->where('thesis.status', ThesisStatus::SUBMITTED->value)
            ->has('thesis.milestones', 5)
            ->has('documentCategories'));
});

it('merender daftar bimbingan dosen dengan statistik', function () {
    $setup = thesisTestSetup();
    $thesis = makeThesisForTest($setup);
    app(ThesisService::class)->approve($thesis, $setup['operator'], [$setup['lecturer']->id]);

    $this->actingAs($setup['lecturerUser'])
        ->withSession(['active_role' => 'lecturer'])
        ->get(route('lecturer.theses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lecturer/Theses/Index')
            ->where('stats.total', 1)
            ->where('stats.active', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.nim', '2026TI9001'));
});
