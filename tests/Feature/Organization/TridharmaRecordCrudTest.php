<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\TridharmaAttachment;
use App\Models\Organization\TridharmaBudget;
use App\Models\Organization\TridharmaMember;
use App\Models\Organization\TridharmaMilestone;
use App\Models\Organization\TridharmaOutput;
use App\Models\Organization\TridharmaRecord;
use App\Models\Settings\System;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function tridharmaCrudSetup(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('admin');

    return $user;
}

function tridharmaCrudPermission(string ...$names): void
{
    $role = Role::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
}

function tridharmaCrudActing(User $user)
{
    return test()->actingAs($user)->withSession(['active_role' => 'admin']);
}

function tridharmaCrudLecturer(string $nidn = 'TRI-001'): array
{
    $faculty = Faculty::create(['name' => 'Fakultas Tridharma', 'code' => 'FTRI', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Prodi Tridharma',
        'code' => 'PTRI',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $profile = LecturerProfile::create([
        'user_id' => $user->id,
        'faculty_id' => $faculty->id,
        'study_program_id' => $program->id,
        'nidn' => $nidn,
        'is_active' => true,
    ]);

    return [$user, $profile];
}

function tridharmaCrudRecord(User $owner, array $attributes = []): TridharmaRecord
{
    return TridharmaRecord::create(array_merge([
        'user_id' => $owner->id,
        'type' => 'research',
        'title' => 'Penelitian Uji Coba',
        'scheme' => 'Hibah Internal',
        'status' => 'draft',
        'funding_amount' => 5000000,
        'funding_source' => 'LPPM',
    ], $attributes));
}

it('menampilkan daftar record Tridharma dengan statistik dan filter', function () {
    $user = tridharmaCrudSetup();
    tridharmaCrudPermission('tridharma-record.viewAny');

    $owner = User::factory()->create(['is_active' => true]);

    tridharmaCrudRecord($owner, ['title' => 'Penelitian Dasar', 'type' => 'research', 'status' => 'draft']);
    tridharmaCrudRecord($owner, ['title' => 'Publikasi Terindeks', 'type' => 'publication', 'status' => 'in_approval', 'is_verified' => true]);
    tridharmaCrudRecord($owner, ['title' => 'Pengabdian Desa', 'type' => 'community_service', 'status' => 'completed', 'is_verified' => true]);

    tridharmaCrudActing($user)
        ->get('/admin/organization/tridharma-records')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/TridharmaRecords/Index')
            ->where('stats.total', 3)
            ->where('stats.inApproval', 1)
            ->where('stats.verified', 2)
            ->where('stats.completed', 1)
            ->has('data.rows', 3)
            ->has('types', 4)
            ->has('statuses', 8));

    tridharmaCrudActing($user)
        ->get('/admin/organization/tridharma-records?type=publication')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.total', 1)
            ->where('data.rows.0.type', 'publication'));

    tridharmaCrudActing($user)
        ->get('/admin/organization/tridharma-records?status=completed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.total', 1)
            ->where('data.rows.0.status', 'completed'));

    tridharmaCrudActing($user)
        ->get('/admin/organization/tridharma-records?is_verified=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('data.total', 2));

    tridharmaCrudActing($user)
        ->get('/admin/organization/tridharma-records?q='.urlencode('Publikasi'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.total', 1)
            ->where('data.rows.0.title', 'Publikasi Terindeks'));
});

it('membuat record Tridharma dengan owner lalu memperbarui status siklusnya', function () {
    $user = tridharmaCrudSetup();
    tridharmaCrudPermission('tridharma-record.create', 'tridharma-record.update', 'tridharma-record.view');

    [$owner, $profile] = tridharmaCrudLecturer();

    tridharmaCrudActing($user)
        ->post('/admin/organization/tridharma-records', [
            'user_id' => $owner->id,
            'type' => 'research',
            'title' => 'Riset Komputasi Awan',
            'scheme' => 'Hibah Internal 2026',
            'abstract' => 'Ringkasan riset komputasi awan.',
            'starts_at' => '2026-01-10',
            'ends_at' => '2026-11-10',
            'status' => 'approved',
            'funding_amount' => 12500000,
            'funding_source' => 'LPPM',
            'admin_notes' => 'Catatan admin.',
        ])
        ->assertRedirect('/admin/organization/tridharma-records')
        ->assertSessionHas('success');

    $record = TridharmaRecord::where('title', 'Riset Komputasi Awan')->firstOrFail();

    expect($record->user_id)->toBe($owner->id)
        ->and($record->lecturer_profile_id)->toBe($profile->id)
        ->and($record->status)->toBe('approved')
        ->and((float) $record->funding_amount)->toBe(12500000.0)
        ->and($record->created_by)->toBe($user->id)
        ->and($record->approved_by)->toBe($user->id)
        ->and($record->approved_at)->not->toBeNull();

    tridharmaCrudActing($user)
        ->get("/admin/organization/tridharma-records/{$record->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/TridharmaRecords/Form')
            ->where('mode', 'edit')
            ->where('record.title', 'Riset Komputasi Awan')
            ->where('owner.id', $owner->id)
            ->has('statuses', 7));

    tridharmaCrudActing($user)
        ->put("/admin/organization/tridharma-records/{$record->id}", [
            'type' => 'community_service',
            'title' => 'Riset Komputasi Awan (Revisi)',
            'scheme' => 'Hibah Internal 2026',
            'abstract' => 'Ringkasan diperbarui.',
            'starts_at' => '2026-01-10',
            'ends_at' => '2026-12-10',
            'status' => 'completed',
            'funding_amount' => 15000000,
            'funding_source' => 'LPPM',
            'admin_notes' => 'Catatan admin diperbarui.',
        ])
        ->assertRedirect("/admin/organization/tridharma-records/{$record->id}")
        ->assertSessionHas('success');

    $record->refresh();

    expect($record->type)->toBe('community_service')
        ->and($record->title)->toBe('Riset Komputasi Awan (Revisi)')
        ->and($record->status)->toBe('completed')
        ->and($record->completed_by)->toBe($user->id)
        ->and($record->completed_at)->not->toBeNull()
        ->and($record->updated_by)->toBe($user->id);
});

it('menampilkan detail record dan menjalankan aksi verifikasi, selesai, arsip, dan approval', function () {
    $user = tridharmaCrudSetup();
    tridharmaCrudPermission(
        'tridharma-record.view',
        'tridharma-record.update',
        'tridharma-record.verify',
        'tridharma-record.complete',
    );

    [$owner] = tridharmaCrudLecturer();
    $record = tridharmaCrudRecord($owner, ['status' => 'draft']);

    TridharmaMilestone::create([
        'tridharma_record_id' => $record->id,
        'title' => 'Proposal disetujui',
        'status' => 'completed',
        'progress_percentage' => 100,
    ]);

    tridharmaCrudActing($user)
        ->get("/admin/organization/tridharma-records/{$record->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/TridharmaRecords/Show')
            ->where('record.status', 'draft')
            ->where('record.canSubmit', true)
            ->where('record.canVerify', true)
            ->where('record.canComplete', true)
            ->has('milestones', 1)
            ->has('urls.verify'));

    tridharmaCrudActing($user)
        ->from("/admin/organization/tridharma-records/{$record->id}")
        ->post("/admin/organization/tridharma-records/{$record->id}/submit-approval")
        ->assertSessionHas('success');

    $record->refresh();

    expect($record->status)->toBe('in_approval')
        ->and($record->approval_request_id)->not->toBeNull()
        ->and($record->submitted_at)->not->toBeNull();

    tridharmaCrudActing($user)
        ->from("/admin/organization/tridharma-records/{$record->id}")
        ->post("/admin/organization/tridharma-records/{$record->id}/verify", [
            'verification_notes' => 'Berkas lengkap.',
        ])
        ->assertSessionHas('success');

    $record->refresh();

    expect($record->is_verified)->toBeTrue()
        ->and($record->verified_by)->toBe($user->id)
        ->and($record->verification_notes)->toBe('Berkas lengkap.');

    tridharmaCrudActing($user)
        ->from("/admin/organization/tridharma-records/{$record->id}")
        ->post("/admin/organization/tridharma-records/{$record->id}/complete")
        ->assertSessionHas('success');

    $record->refresh();

    expect($record->status)->toBe('completed')
        ->and($record->completed_by)->toBe($user->id)
        ->and($record->completed_at)->not->toBeNull();

    tridharmaCrudActing($user)
        ->from("/admin/organization/tridharma-records/{$record->id}")
        ->post("/admin/organization/tridharma-records/{$record->id}/archive")
        ->assertSessionHas('success');

    expect($record->refresh()->status)->toBe('archived');

    // Submit approval tidak boleh diulang dari status arsip.
    tridharmaCrudActing($user)
        ->from("/admin/organization/tridharma-records/{$record->id}")
        ->post("/admin/organization/tridharma-records/{$record->id}/submit-approval")
        ->assertSessionHas('error');
});

it('menambahkan anggota, milestone, anggaran, luaran, dan lampiran record Tridharma', function () {
    Storage::fake('local');

    $user = tridharmaCrudSetup();
    tridharmaCrudPermission(
        'tridharma-record.view',
        'tridharma-record.update',
        'tridharma-record.create',
    );

    [$owner] = tridharmaCrudLecturer();
    $internal = User::factory()->create(['is_active' => true]);
    $record = tridharmaCrudRecord($owner);
    $base = "/admin/organization/tridharma-records/{$record->id}";

    tridharmaCrudActing($user)
        ->from($base)
        ->post("{$base}/members", [
            'member_name' => 'Mitra Eksternal',
            'institution' => 'Universitas Mitra',
            'email' => 'mitra@example.test',
            'role' => 'partner',
        ])
        ->assertSessionHas('success');

    tridharmaCrudActing($user)
        ->from($base)
        ->post("{$base}/members", [
            'user_id' => $internal->id,
            'role' => 'member',
        ])
        ->assertSessionHas('success');

    tridharmaCrudActing($user)
        ->from($base)
        ->post("{$base}/members", [
            'role' => 'member',
        ])
        ->assertSessionHasErrors('member_name');

    tridharmaCrudActing($user)
        ->from($base)
        ->post("{$base}/milestones", [
            'title' => 'Laporan akhir',
            'due_date' => '2026-12-01',
            'status' => 'in_progress',
            'progress_percentage' => 45,
            'description' => 'Penyusunan laporan akhir.',
        ])
        ->assertSessionHas('success');

    tridharmaCrudActing($user)
        ->from($base)
        ->post("{$base}/budgets", [
            'category' => 'Bahan habis pakai',
            'description' => 'Kertas dan tinta',
            'planned_amount' => 1500000,
            'realized_amount' => 1250000,
            'notes' => 'Sesuai nota.',
        ])
        ->assertSessionHas('success');

    tridharmaCrudActing($user)
        ->from($base)
        ->post("{$base}/outputs", [
            'output_type' => 'article',
            'title' => 'Artikel Komputasi Awan',
            'publisher' => 'Jurnal Teknologi',
            'indexing' => 'Sinta 2',
            'doi' => '10.1234/tri.2026',
            'url' => 'https://example.test/artikel',
            'published_at' => '2026-10-01',
            'status' => 'published',
            'notes' => 'Luaran utama.',
        ])
        ->assertSessionHas('success');

    tridharmaCrudActing($user)
        ->from($base)
        ->post("{$base}/attachments", [
            'document_type' => 'proposal',
            'attachment' => UploadedFile::fake()->create('proposal.pdf', 512, 'application/pdf'),
        ])
        ->assertSessionHas('success');

    expect(TridharmaMember::where('tridharma_record_id', $record->id)->count())->toBe(2)
        ->and(TridharmaMember::where('tridharma_record_id', $record->id)->where('is_external', true)->count())->toBe(1)
        ->and(TridharmaMilestone::where('tridharma_record_id', $record->id)->where('progress_percentage', 45)->count())->toBe(1)
        ->and(TridharmaBudget::where('tridharma_record_id', $record->id)->count())->toBe(1)
        ->and(TridharmaOutput::where('tridharma_record_id', $record->id)->where('doi', '10.1234/tri.2026')->count())->toBe(1);

    $attachment = TridharmaAttachment::where('tridharma_record_id', $record->id)->firstOrFail();

    expect($attachment->document_type)->toBe('proposal')
        ->and($attachment->uploaded_by)->toBe($user->id);

    Storage::disk('local')->assertExists($attachment->file_path);
});

it('mencari pengguna untuk owner dan anggota tim internal', function () {
    $user = tridharmaCrudSetup();
    tridharmaCrudPermission('tridharma-record.create');

    [$owner] = tridharmaCrudLecturer();
    $owner->update(['first_name' => 'Pemilik', 'last_name' => 'Tridharma']);

    tridharmaCrudActing($user)
        ->get('/admin/organization/tridharma-records/search-users?q=Pemilik&context=owner')
        ->assertOk()
        ->assertJsonPath('options.0.id', $owner->id);

    tridharmaCrudActing($user)
        ->get('/admin/organization/tridharma-records/search-users?q=P&context=member')
        ->assertOk()
        ->assertJsonCount(0, 'options');
});

it('menghapus satu record dan menghapus massal record Tridharma terpilih', function () {
    $user = tridharmaCrudSetup();
    tridharmaCrudPermission('tridharma-record.viewAny', 'tridharma-record.delete');

    $owner = User::factory()->create(['is_active' => true]);

    $single = tridharmaCrudRecord($owner, ['title' => 'Record Tunggal']);
    $bulkA = tridharmaCrudRecord($owner, ['title' => 'Record Massal A']);
    $bulkB = tridharmaCrudRecord($owner, ['title' => 'Record Massal B']);

    tridharmaCrudActing($user)
        ->delete("/admin/organization/tridharma-records/{$single->id}")
        ->assertRedirect('/admin/organization/tridharma-records')
        ->assertSessionHas('success');

    expect(TridharmaRecord::find($single->id))->toBeNull()
        ->and(TridharmaRecord::withTrashed()->find($single->id))->not->toBeNull();

    tridharmaCrudActing($user)
        ->from('/admin/organization/tridharma-records')
        ->post('/admin/organization/tridharma-records/bulk-destroy', [
            'ids' => [$bulkA->id, $bulkB->id],
        ])
        ->assertSessionHas('success');

    expect(TridharmaRecord::find($bulkA->id))->toBeNull()
        ->and(TridharmaRecord::find($bulkB->id))->toBeNull();

    tridharmaCrudActing($user)
        ->get('/admin/organization/tridharma-records')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('data.total', 0));
});
