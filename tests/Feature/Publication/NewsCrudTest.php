<?php

use App\Models\Publication\News;
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

function newsCrudSetup(): array
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

function giveNewsPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingNewsOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeNews(array $overrides = []): News
{
    $creatorId = $overrides['created_by'] ?? App\Models\User::factory()->create()->id;
    unset($overrides['created_by']);

    return News::create(array_merge([
        'title' => 'Judul Berita',
        'slug' => 'judul-berita-'.Str::random(6),
        'content' => '<p>Isi berita.</p>',
        'is_published' => false,
        'created_by' => $creatorId,
    ], $overrides));
}

it('menampilkan daftar berita dengan kategori', function () {
    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.viewAny']);

    $category = PublicationCategory::create(['name' => 'Berita Kampus', 'slug' => 'berita-kampus', 'is_active' => true]);
    makeNews(['category_id' => $category->id, 'is_published' => true, 'published_at' => now()]);

    actingNewsOperator($setup['operator'])
        ->get('/admin/publication/news')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/News/Index')
            ->where('stats.total', 1)
            ->where('stats.published', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.category', 'Berita Kampus'));
});

it('menampilkan pratinjau berita', function () {
    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.view']);

    $news = makeNews();

    actingNewsOperator($setup['operator'])
        ->get("/admin/publication/news/{$news->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/News/Show')
            ->where('news.title', 'Judul Berita'));
});

it('menyimpan berita dan menolak slug duplikat', function () {
    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.create']);

    actingNewsOperator($setup['operator'])
        ->post('/admin/publication/news', [
            'title' => 'Judul Berita',
            'slug' => 'judul-berita',
            'content' => '<p>Isi.</p>',
            'excerpt' => 'Ringkasan.',
            'is_published' => true,
            'published_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect('/admin/publication/news')
        ->assertSessionHas('success');

    expect(News::where('slug', 'judul-berita')->firstOrFail()->excerpt)->toBe('Ringkasan.');

    actingNewsOperator($setup['operator'])
        ->post('/admin/publication/news', [
            'title' => 'Lain',
            'slug' => 'judul-berita',
            'content' => '<p>Isi.</p>',
        ])
        ->assertSessionHasErrors('slug');
});

it('mengunggah dan membersihkan gambar sampul', function () {
    Storage::fake('public');

    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.create', 'news.update']);

    actingNewsOperator($setup['operator'])
        ->post('/admin/publication/news', [
            'title' => 'Dengan Sampul',
            'content' => '<p>Isi.</p>',
            'featured_image' => UploadedFile::fake()->image('sampul.jpg'),
        ])
        ->assertRedirect('/admin/publication/news');

    $news = News::where('title', 'Dengan Sampul')->firstOrFail();
    $oldPath = $news->featured_image;

    expect($oldPath)->not->toBeNull();
    Storage::disk('public')->assertExists($oldPath);

    actingNewsOperator($setup['operator'])
        ->post("/admin/publication/news/{$news->id}", array_merge([
            'title' => 'Dengan Sampul',
            'content' => '<p>Isi.</p>',
            '_method' => 'PUT',
            'remove_image' => true,
        ]))
        ->assertRedirect('/admin/publication/news');

    expect($news->fresh()->featured_image)->toBeNull();
    Storage::disk('public')->assertMissing($oldPath);
});

it('menghapus berita ikut menghapus file sampul', function () {
    Storage::fake('public');

    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.delete']);

    $news = makeNews(['title' => 'Bersampul']);
    $path = UploadedFile::fake()->image('x.jpg')->store('news', 'public');
    $news->update(['featured_image' => $path]);

    actingNewsOperator($setup['operator'])
        ->delete("/admin/publication/news/{$news->id}")
        ->assertRedirect('/admin/publication/news');

    Storage::disk('public')->assertMissing($path);
});

it('mengelola sampah berita dan mengekspornya', function () {
    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.viewAny', 'news.delete']);

    $news = makeNews(['title' => 'Sementara']);

    actingNewsOperator($setup['operator'])
        ->delete("/admin/publication/news/{$news->id}")
        ->assertRedirect('/admin/publication/news');

    actingNewsOperator($setup['operator'])
        ->get('/admin/publication/news?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingNewsOperator($setup['operator'])
        ->post("/admin/publication/news/{$news->id}/restore")
        ->assertSessionHas('success');

    expect(News::find($news->id))->not->toBeNull();

    $response = actingNewsOperator($setup['operator'])
        ->get('/admin/publication/news/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.viewAny']);

    makeNews();

    $response = actingNewsOperator($setup['operator'])
        ->get('/admin/publication/news/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeNewsImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['title', 'slug', 'category', 'excerpt', 'content', 'is_published'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-news').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'news.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor berita valid', function () {
    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.create']);

    PublicationCategory::create(['name' => 'Berita Kampus', 'slug' => 'berita-kampus', 'is_active' => true]);

    $response = actingNewsOperator($setup['operator'])
        ->get('/admin/publication/news/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-news.xlsx');

    actingNewsOperator($setup['operator'])
        ->post('/admin/publication/news/import', ['file' => makeNewsImportFile([
            ['Berita Satu', 'berita-satu', 'Berita Kampus', '', '<p>Isi.</p>', '1'],
            ['Berita Dua', 'berita-dua', '', '', '<p>Isi.</p>', '0'],
        ])])
        ->assertRedirect('/admin/publication/news')
        ->assertSessionHas('success');

    expect(News::where('slug', 'berita-satu')->firstOrFail()->is_published)->toBeTrue()
        ->and(News::where('slug', 'berita-dua')->firstOrFail()->category_id)->toBeNull();
});

it('menolak seluruh file berita bila ada baris tidak valid', function () {
    $setup = newsCrudSetup();
    giveNewsPermissionsToOperator(['news.create']);

    $response = actingNewsOperator($setup['operator'])
        ->post('/admin/publication/news/import', ['file' => makeNewsImportFile([
            ['Valid', 'valid', '', '', '<p>Isi.</p>', ''],
            ['Salah Kategori', 'salah-kategori', 'Tidak Ada', '', '<p>Isi.</p>', ''],
        ])])
        ->assertRedirect('/admin/publication/news');

    expect(News::where('slug', 'valid')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
