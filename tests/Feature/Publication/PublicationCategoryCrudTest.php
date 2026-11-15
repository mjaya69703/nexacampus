<?php

use App\Models\Publication\Agenda;
use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function publicationCategoryCrudSetup(): array
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

function givePublicationCategoryPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingPublicationCategoryOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar kategori dengan hitungan konten', function () {
    $setup = publicationCategoryCrudSetup();
    givePublicationCategoryPermissionsToOperator(['publication-category.viewAny']);

    $category = PublicationCategory::create(['name' => 'Berita Kampus', 'slug' => 'berita-kampus', 'is_active' => true]);
    News::create([
        'title' => 'Judul Berita', 'slug' => 'judul-berita', 'content' => 'Isi',
        'category_id' => $category->id, 'is_published' => true,
    ]);

    actingPublicationCategoryOperator($setup['operator'])
        ->get('/admin/publication/categories')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/Category/Index')
            ->where('stats.total', 1)
            ->where('stats.contents', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.contentCount', 1)
            ->where('data.rows.0.slug', 'berita-kampus'));
});

it('menyimpan kategori dan menolak slug duplikat', function () {
    $setup = publicationCategoryCrudSetup();
    givePublicationCategoryPermissionsToOperator(['publication-category.create']);

    actingPublicationCategoryOperator($setup['operator'])
        ->post('/admin/publication/categories', [
            'name' => 'Berita Kampus', 'slug' => 'berita-kampus',
            'sort_order' => 0, 'is_active' => true,
        ])
        ->assertRedirect('/admin/publication/categories')
        ->assertSessionHas('success');

    expect(PublicationCategory::where('slug', 'berita-kampus')->firstOrFail()->name)->toBe('Berita Kampus');

    actingPublicationCategoryOperator($setup['operator'])
        ->post('/admin/publication/categories', ['name' => 'Lain', 'slug' => 'berita-kampus'])
        ->assertSessionHasErrors('slug');
});

it('menolak hapus kategori yang masih dipakai konten', function () {
    $setup = publicationCategoryCrudSetup();
    givePublicationCategoryPermissionsToOperator(['publication-category.delete']);

    $category = PublicationCategory::create(['name' => 'Berita Kampus', 'slug' => 'berita-kampus', 'is_active' => true]);
    News::create([
        'title' => 'Judul Berita', 'slug' => 'judul-berita', 'content' => 'Isi',
        'category_id' => $category->id, 'is_published' => true,
    ]);

    actingPublicationCategoryOperator($setup['operator'])
        ->delete("/admin/publication/categories/{$category->id}")
        ->assertSessionHas('error');

    expect(PublicationCategory::find($category->id))->not->toBeNull();

    actingPublicationCategoryOperator($setup['operator'])
        ->post('/admin/publication/categories/bulk-destroy', ['ids' => [$category->id]])
        ->assertSessionHas('error');

    expect(PublicationCategory::find($category->id))->not->toBeNull();
});

it('mengelola sampah kategori dan mengekspornya', function () {
    $setup = publicationCategoryCrudSetup();
    givePublicationCategoryPermissionsToOperator(['publication-category.viewAny', 'publication-category.delete']);

    $category = PublicationCategory::create(['name' => 'Sementara', 'slug' => 'sementara', 'is_active' => true]);

    actingPublicationCategoryOperator($setup['operator'])
        ->delete("/admin/publication/categories/{$category->id}")
        ->assertRedirect('/admin/publication/categories');

    actingPublicationCategoryOperator($setup['operator'])
        ->get('/admin/publication/categories?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingPublicationCategoryOperator($setup['operator'])
        ->post("/admin/publication/categories/{$category->id}/restore")
        ->assertSessionHas('success');

    expect(PublicationCategory::find($category->id))->not->toBeNull();

    $response = actingPublicationCategoryOperator($setup['operator'])
        ->get('/admin/publication/categories/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = publicationCategoryCrudSetup();
    givePublicationCategoryPermissionsToOperator(['publication-category.viewAny']);

    PublicationCategory::create(['name' => 'Berita Kampus', 'slug' => 'berita-kampus', 'is_active' => true]);

    $response = actingPublicationCategoryOperator($setup['operator'])
        ->get('/admin/publication/categories/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makePublicationCategoryImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['name', 'slug', 'desc', 'sort_order', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-categories').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'categories.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor kategori valid', function () {
    $setup = publicationCategoryCrudSetup();
    givePublicationCategoryPermissionsToOperator(['publication-category.create']);

    $response = actingPublicationCategoryOperator($setup['operator'])
        ->get('/admin/publication/categories/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-categories.xlsx');

    actingPublicationCategoryOperator($setup['operator'])
        ->post('/admin/publication/categories/import', ['file' => makePublicationCategoryImportFile([
            ['Berita Kampus', 'berita-kampus', '', '0', '1'],
            ['Agenda Kampus', 'agenda-kampus', '', '1', '0'],
        ])])
        ->assertRedirect('/admin/publication/categories')
        ->assertSessionHas('success');

    expect(PublicationCategory::where('slug', 'berita-kampus')->firstOrFail()->name)->toBe('Berita Kampus')
        ->and((bool) PublicationCategory::where('slug', 'agenda-kampus')->firstOrFail()->is_active)->toBeFalse();
});

it('menolak seluruh file kategori bila ada baris tidak valid', function () {
    $setup = publicationCategoryCrudSetup();
    givePublicationCategoryPermissionsToOperator(['publication-category.create']);

    PublicationCategory::create(['name' => 'Lama', 'slug' => 'berita-kampus', 'is_active' => true]);

    $response = actingPublicationCategoryOperator($setup['operator'])
        ->post('/admin/publication/categories/import', ['file' => makePublicationCategoryImportFile([
            ['Baru', 'baru', '', '', ''],
            ['Duplikat', 'berita-kampus', '', '', ''],
        ])])
        ->assertRedirect('/admin/publication/categories');

    expect(PublicationCategory::where('slug', 'baru')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
