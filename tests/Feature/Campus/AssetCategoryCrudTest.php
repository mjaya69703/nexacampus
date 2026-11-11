<?php

use App\Models\Campus\AssetCategory;
use App\Models\Campus\CampusAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function assetCategoryCrudSetup(): array
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

function giveAssetCategoryPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAssetCategoryOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar kategori bawaan migrasi', function () {
    $setup = assetCategoryCrudSetup();
    giveAssetCategoryPermissionsToOperator(['asset-category.viewAny']);

    actingAssetCategoryOperator($setup['operator'])
        ->get('/admin/campus/asset-categories')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/AssetCategory/Index')
            ->where('stats.total', 5)
            ->has('data.rows', 5));
});

it('menyimpan kategori dan menolak kode duplikat', function () {
    $setup = assetCategoryCrudSetup();
    giveAssetCategoryPermissionsToOperator(['asset-category.create']);

    actingAssetCategoryOperator($setup['operator'])
        ->post('/admin/campus/asset-categories', [
            'name' => 'Alat Musik', 'code' => 'alat-musik', 'is_active' => true,
        ])
        ->assertRedirect('/admin/campus/asset-categories')
        ->assertSessionHas('success');

    expect(AssetCategory::where('code', 'alat-musik')->firstOrFail()->name)->toBe('Alat Musik');

    actingAssetCategoryOperator($setup['operator'])
        ->post('/admin/campus/asset-categories', ['name' => 'Lain', 'code' => 'alat-musik'])
        ->assertSessionHasErrors('code');
});

it('menolak hapus kategori yang masih dipakai aset', function () {
    $setup = assetCategoryCrudSetup();
    giveAssetCategoryPermissionsToOperator(['asset-category.delete']);

    $category = AssetCategory::where('code', 'electronics')->firstOrFail();
    CampusAsset::create([
        'asset_code' => 'AST-1', 'name' => 'Proyektor', 'category_id' => $category->id,
        'condition' => 'good', 'status' => 'active',
    ]);

    actingAssetCategoryOperator($setup['operator'])
        ->delete("/admin/campus/asset-categories/{$category->id}")
        ->assertSessionHas('error');

    expect(AssetCategory::find($category->id))->not->toBeNull();
});

it('mengelola sampah kategori dan mengekspornya', function () {
    $setup = assetCategoryCrudSetup();
    giveAssetCategoryPermissionsToOperator(['asset-category.viewAny', 'asset-category.delete']);

    $category = AssetCategory::create(['name' => 'Sementara', 'code' => 'sementara', 'is_active' => true]);

    actingAssetCategoryOperator($setup['operator'])
        ->delete("/admin/campus/asset-categories/{$category->id}")
        ->assertRedirect('/admin/campus/asset-categories');

    actingAssetCategoryOperator($setup['operator'])
        ->get('/admin/campus/asset-categories?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingAssetCategoryOperator($setup['operator'])
        ->post("/admin/campus/asset-categories/{$category->id}/restore")
        ->assertSessionHas('success');

    expect(AssetCategory::find($category->id))->not->toBeNull();

    $response = actingAssetCategoryOperator($setup['operator'])
        ->get('/admin/campus/asset-categories/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = assetCategoryCrudSetup();
    giveAssetCategoryPermissionsToOperator(['asset-category.viewAny']);

    $response = actingAssetCategoryOperator($setup['operator'])
        ->get('/admin/campus/asset-categories/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeAssetCategoryImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['name', 'code', 'desc', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-asset-categories').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'categories.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor kategori valid', function () {
    $setup = assetCategoryCrudSetup();
    giveAssetCategoryPermissionsToOperator(['asset-category.create']);

    $response = actingAssetCategoryOperator($setup['operator'])
        ->get('/admin/campus/asset-categories/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-asset-categories.xlsx');

    actingAssetCategoryOperator($setup['operator'])
        ->post('/admin/campus/asset-categories/import', ['file' => makeAssetCategoryImportFile([
            ['Alat Musik', 'alat-musik', '', '1'],
        ])])
        ->assertRedirect('/admin/campus/asset-categories')
        ->assertSessionHas('success');

    expect(AssetCategory::where('code', 'alat-musik')->firstOrFail()->name)->toBe('Alat Musik');
});
