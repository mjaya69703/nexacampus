<?php

use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\GalleryImage;
use App\Models\Publication\PublicationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function galleryCrudSetup(): array
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

function giveGalleryPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingGalleryOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeAlbum(array $overrides = []): GalleryAlbum
{
    $creatorId = $overrides['created_by'] ?? App\Models\User::factory()->create()->id;
    unset($overrides['created_by']);

    return GalleryAlbum::create(array_merge([
        'title' => 'Wisuda 2026',
        'slug' => 'wisuda-2026-'.Str::random(6),
        'is_published' => false,
        'created_by' => $creatorId,
    ], $overrides));
}

it('menampilkan daftar album dengan hitungan foto', function () {
    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.viewAny']);

    $album = makeAlbum(['is_published' => true]);
    $album->images()->create(['image_path' => 'galleries/a.jpg', 'sort_order' => 1]);

    actingGalleryOperator($setup['operator'])
        ->get('/admin/publication/galleries')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/Gallery/Index')
            ->where('stats.total', 1)
            ->where('stats.images', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.imagesCount', 1));
});

it('menampilkan pratinjau album', function () {
    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.view']);

    $album = makeAlbum();

    actingGalleryOperator($setup['operator'])
        ->get("/admin/publication/galleries/{$album->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/Gallery/Show')
            ->where('album.title', 'Wisuda 2026'));
});

it('menyimpan album beserta sampul dan foto', function () {
    Storage::fake('public');

    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.create']);

    actingGalleryOperator($setup['operator'])
        ->post('/admin/publication/galleries', [
            'title' => 'Wisuda 2026',
            'slug' => 'wisuda-2026',
            'description' => 'Dokumentasi.',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            'new_images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
            'is_published' => true,
        ])
        ->assertRedirect('/admin/publication/galleries')
        ->assertSessionHas('success');

    $album = GalleryAlbum::where('slug', 'wisuda-2026')->firstOrFail();

    expect($album->cover_image_path)->not->toBeNull()
        ->and($album->images()->count())->toBe(2)
        ->and($album->images()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([1, 2]);

    Storage::disk('public')->assertExists($album->cover_image_path);
});

it('mengelola foto album lewat sub-endpoint', function () {
    Storage::fake('public');

    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.update', 'gallery.delete']);

    $album = makeAlbum();
    $path = UploadedFile::fake()->image('a.jpg')->store('galleries', 'public');
    $image = $album->images()->create(['image_path' => $path, 'sort_order' => 1]);

    actingGalleryOperator($setup['operator'])
        ->post("/admin/publication/galleries/{$album->id}/images", [
            'images' => [UploadedFile::fake()->image('b.jpg')],
        ])
        ->assertSessionHas('success');

    expect($album->images()->count())->toBe(2);

    actingGalleryOperator($setup['operator'])
        ->patch("/admin/publication/gallery-images/{$image->id}", ['caption' => 'Sambutan rektor'])
        ->assertSessionHas('success');

    expect($image->fresh()->caption)->toBe('Sambutan rektor');

    actingGalleryOperator($setup['operator'])
        ->post("/admin/publication/gallery-images/{$image->id}/cover")
        ->assertSessionHas('success');

    expect($album->fresh()->cover_image_path)->not->toBeNull()
        ->and($album->fresh()->cover_image_path)->not->toBe($path);

    actingGalleryOperator($setup['operator'])
        ->delete("/admin/publication/gallery-images/{$image->id}")
        ->assertSessionHas('success');

    expect(GalleryImage::find($image->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('menghapus album ikut menghapus baris dan file foto', function () {
    Storage::fake('public');

    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.delete']);

    $album = makeAlbum(['title' => 'Bersampul']);
    $cover = UploadedFile::fake()->image('cover.jpg')->store('galleries', 'public');
    $photo = UploadedFile::fake()->image('a.jpg')->store('galleries', 'public');
    $album->update(['cover_image_path' => $cover]);
    $album->images()->create(['image_path' => $photo, 'sort_order' => 1]);

    actingGalleryOperator($setup['operator'])
        ->delete("/admin/publication/galleries/{$album->id}")
        ->assertRedirect('/admin/publication/galleries');

    expect(GalleryImage::where('gallery_album_id', $album->id)->count())->toBe(0);
    Storage::disk('public')->assertMissing($cover);
    Storage::disk('public')->assertMissing($photo);
});

it('mengelola sampah album dan mengekspornya', function () {
    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.viewAny', 'gallery.delete']);

    $album = makeAlbum(['title' => 'Sementara']);

    actingGalleryOperator($setup['operator'])
        ->delete("/admin/publication/galleries/{$album->id}")
        ->assertRedirect('/admin/publication/galleries');

    actingGalleryOperator($setup['operator'])
        ->get('/admin/publication/galleries?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingGalleryOperator($setup['operator'])
        ->post("/admin/publication/galleries/{$album->id}/restore")
        ->assertSessionHas('success');

    expect(GalleryAlbum::find($album->id))->not->toBeNull();

    $response = actingGalleryOperator($setup['operator'])
        ->get('/admin/publication/galleries/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.viewAny']);

    makeAlbum();

    $response = actingGalleryOperator($setup['operator'])
        ->get('/admin/publication/galleries/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeGalleryImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['title', 'slug', 'category', 'description', 'is_published'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-galleries').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'galleries.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor album valid', function () {
    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.create']);

    PublicationCategory::create(['name' => 'Acara', 'slug' => 'acara', 'is_active' => true]);

    $response = actingGalleryOperator($setup['operator'])
        ->get('/admin/publication/galleries/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-galleries.xlsx');

    actingGalleryOperator($setup['operator'])
        ->post('/admin/publication/galleries/import', ['file' => makeGalleryImportFile([
            ['Wisuda', 'wisuda', 'Acara', 'Dokumentasi.', '1'],
            ['Dies Natalis', 'dies-natalis', '', '', '0'],
        ])])
        ->assertRedirect('/admin/publication/galleries')
        ->assertSessionHas('success');

    expect(GalleryAlbum::where('slug', 'wisuda')->firstOrFail()->is_published)->toBeTrue();
});

it('menolak seluruh file album bila ada baris tidak valid', function () {
    $setup = galleryCrudSetup();
    giveGalleryPermissionsToOperator(['gallery.create']);

    $response = actingGalleryOperator($setup['operator'])
        ->post('/admin/publication/galleries/import', ['file' => makeGalleryImportFile([
            ['Valid', 'valid', '', '', ''],
            ['Salah Kategori', 'salah-kategori', 'Tidak Ada', '', ''],
        ])])
        ->assertRedirect('/admin/publication/galleries');

    expect(GalleryAlbum::where('slug', 'valid')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
