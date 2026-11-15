<?php

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Models\Academic\Faculty;
use App\Models\Publication\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function announcementCrudSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = App\Models\User::factory()->create();
    $operator->assignRole('operator');

    return compact('operator');
}

function giveAnnouncementPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAnnouncementOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeAnnouncement(array $overrides = []): Announcement
{
    $creatorId = $overrides['created_by'] ?? App\Models\User::factory()->create()->id;
    unset($overrides['created_by']);

    return Announcement::create(array_merge([
        'title' => 'Pengumuman Libur',
        'content' => '<p>Libur semester.</p>',
        'target_type' => AnnouncementTargetType::GLOBAL->value,
        'target_id' => null,
        'priority' => AnnouncementPriority::NORMAL->value,
        'is_pinned' => false,
        'is_published' => false,
        'created_by' => $creatorId,
    ], $overrides));
}

it('menampilkan daftar pengumuman dengan hitungan dibaca', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.viewAny']);

    makeAnnouncement(['is_published' => true, 'published_at' => now()]);

    actingAnnouncementOperator($setup['operator'])
        ->get('/admin/publication/announcements')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/Announcement/Index')
            ->where('stats.total', 1)
            ->where('stats.published', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.title', 'Pengumuman Libur')
            ->where('data.rows.0.targetType', 'global'));
});

it('menampilkan pratinjau pengumuman', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.view']);

    $announcement = makeAnnouncement();

    actingAnnouncementOperator($setup['operator'])
        ->get("/admin/publication/announcements/{$announcement->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/Announcement/Show')
            ->where('announcement.title', 'Pengumuman Libur'));
});

it('menyajikan URL lampiran relatif di pratinjau', function () {
    Storage::fake('public');

    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.view']);

    $announcement = makeAnnouncement();
    $path = UploadedFile::fake()->image('foto.jpg')->store('announcements', 'public');
    $announcement->update([
        'attachment_path' => $path, 'attachment_name' => 'foto.jpg', 'attachment_type' => 'jpg',
    ]);

    actingAnnouncementOperator($setup['operator'])
        ->get("/admin/publication/announcements/{$announcement->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('announcement.attachmentUrl', '/storage/'.$path)
            ->where('announcement.attachmentType', 'jpg'));
});

it('menyimpan pengumuman global dan menolak target kosong untuk tipe ber-target', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.create']);

    actingAnnouncementOperator($setup['operator'])
        ->post('/admin/publication/announcements', [
            'title' => 'Pengumuman Libur',
            'content' => '<p>Libur.</p>',
            'target_type' => 'global',
            'priority' => 'normal',
            'is_pinned' => false,
            'is_published' => true,
            'published_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect('/admin/publication/announcements')
        ->assertSessionHas('success');

    expect(Announcement::where('title', 'Pengumuman Libur')->firstOrFail()->is_published)->toBeTrue();

    actingAnnouncementOperator($setup['operator'])
        ->post('/admin/publication/announcements', [
            'title' => 'Khusus Fakultas',
            'content' => '<p>Isi.</p>',
            'target_type' => 'faculty',
            'priority' => 'normal',
        ])
        ->assertSessionHasErrors('target_id');

    expect(Announcement::where('title', 'Khusus Fakultas')->count())->toBe(0);
});

it('menyimpan pengumuman ber-target fakultas yang valid', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.create']);

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);

    actingAnnouncementOperator($setup['operator'])
        ->post('/admin/publication/announcements', [
            'title' => 'Khusus FT',
            'content' => '<p>Isi.</p>',
            'target_type' => 'faculty',
            'target_id' => $faculty->id,
            'priority' => 'important',
        ])
        ->assertRedirect('/admin/publication/announcements')
        ->assertSessionHas('success');

    expect(Announcement::where('title', 'Khusus FT')->firstOrFail()->target_id)->toBe($faculty->id);
});

it('mengunggah dan menghapus lampiran', function () {
    Storage::fake('public');

    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.create', 'announcement.update']);

    $file = UploadedFile::fake()->create('info.pdf', 100, 'application/pdf');

    actingAnnouncementOperator($setup['operator'])
        ->post('/admin/publication/announcements', [
            'title' => 'Dengan Lampiran',
            'content' => '<p>Isi.</p>',
            'target_type' => 'global',
            'priority' => 'normal',
            'attachment' => $file,
        ])
        ->assertRedirect('/admin/publication/announcements');

    $announcement = Announcement::where('title', 'Dengan Lampiran')->firstOrFail();

    expect($announcement->attachment_name)->toBe('info.pdf');
    Storage::disk('public')->assertExists($announcement->attachment_path);

    actingAnnouncementOperator($setup['operator'])
        ->post("/admin/publication/announcements/{$announcement->id}", array_merge([
            'title' => 'Dengan Lampiran',
            'content' => '<p>Isi.</p>',
            'target_type' => 'global',
            'priority' => 'normal',
            '_method' => 'PUT',
            'remove_attachment' => true,
        ]))
        ->assertRedirect('/admin/publication/announcements');

    expect($announcement->fresh()->attachment_path)->toBeNull();
    Storage::disk('public')->assertMissing($announcement->attachment_path);
});

it('mempublish dan membatalkan publish lewat izin publish', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.publish']);

    $announcement = makeAnnouncement();

    actingAnnouncementOperator($setup['operator'])
        ->post("/admin/publication/announcements/{$announcement->id}/publish")
        ->assertSessionHas('success');

    expect((bool) $announcement->fresh()->is_published)->toBeTrue();

    actingAnnouncementOperator($setup['operator'])
        ->post("/admin/publication/announcements/{$announcement->id}/unpublish")
        ->assertSessionHas('success');

    expect((bool) $announcement->fresh()->is_published)->toBeFalse();
});

it('menolak publish tanpa izin publish', function () {
    $setup = announcementCrudSetup();

    $announcement = makeAnnouncement();

    actingAnnouncementOperator($setup['operator'])
        ->post("/admin/publication/announcements/{$announcement->id}/publish")
        ->assertForbidden();

    expect((bool) $announcement->fresh()->is_published)->toBeFalse();
});

it('menghormati scheduled_at pada scope published', function () {
    announcementCrudSetup();

    $future = makeAnnouncement([
        'is_published' => true, 'published_at' => now()->subHour(),
        'scheduled_at' => now()->addDay(),
    ]);
    $past = makeAnnouncement([
        'title' => 'Terjadwal Lewat', 'is_published' => true,
        'published_at' => now()->subHour(), 'scheduled_at' => now()->subHour(),
    ]);

    expect(Announcement::published()->whereKey($future->id)->count())->toBe(0)
        ->and(Announcement::published()->whereKey($past->id)->count())->toBe(1);
});

it('mencari target fakultas lewat search-targets', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.create']);

    Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);

    actingAnnouncementOperator($setup['operator'])
        ->get('/admin/publication/announcements/search-targets/faculty?q=Teknik')
        ->assertOk()
        ->assertJsonCount(1, 'options')
        ->assertJsonPath('options.0.label', 'Fakultas Teknik');
});

it('mengelola sampah pengumuman dan mengekspornya', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.viewAny', 'announcement.delete']);

    $announcement = makeAnnouncement(['title' => 'Sementara']);

    actingAnnouncementOperator($setup['operator'])
        ->delete("/admin/publication/announcements/{$announcement->id}")
        ->assertRedirect('/admin/publication/announcements');

    actingAnnouncementOperator($setup['operator'])
        ->get('/admin/publication/announcements?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingAnnouncementOperator($setup['operator'])
        ->post("/admin/publication/announcements/{$announcement->id}/restore")
        ->assertSessionHas('success');

    expect(Announcement::find($announcement->id))->not->toBeNull();

    $response = actingAnnouncementOperator($setup['operator'])
        ->get('/admin/publication/announcements/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.viewAny']);

    makeAnnouncement();

    $response = actingAnnouncementOperator($setup['operator'])
        ->get('/admin/publication/announcements/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeAnnouncementImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['title', 'content', 'target_type', 'target_id', 'priority', 'is_published'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-announcements').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'announcements.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor pengumuman valid', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.create']);

    $response = actingAnnouncementOperator($setup['operator'])
        ->get('/admin/publication/announcements/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-announcements.xlsx');

    actingAnnouncementOperator($setup['operator'])
        ->post('/admin/publication/announcements/import', ['file' => makeAnnouncementImportFile([
            ['Libur Semester', 'Libur mulai...', 'global', '', 'normal', '1'],
            ['Info Beasiswa', 'Info...', 'global', '', 'important', '0'],
        ])])
        ->assertRedirect('/admin/publication/announcements')
        ->assertSessionHas('success');

    expect(Announcement::where('title', 'Libur Semester')->firstOrFail()->is_published)->toBeTrue()
        ->and(Announcement::where('title', 'Info Beasiswa')->firstOrFail()->priority->value)->toBe('important');
});

it('menolak seluruh file pengumuman bila ada baris tidak valid', function () {
    $setup = announcementCrudSetup();
    giveAnnouncementPermissionsToOperator(['announcement.create']);

    $response = actingAnnouncementOperator($setup['operator'])
        ->post('/admin/publication/announcements/import', ['file' => makeAnnouncementImportFile([
            ['Valid', 'Isi.', 'global', '', 'normal', ''],
            ['Salah Tipe', 'Isi.', 'fakultas', '', 'normal', ''],
        ])])
        ->assertRedirect('/admin/publication/announcements');

    expect(Announcement::where('title', 'Valid')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
