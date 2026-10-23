<?php

use App\Models\Campus\CampusAsset;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Models\Campus\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function campusAssetCrudSetup(): array
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

function giveCampusAssetPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingCampusAssetOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar produk grup dengan jumlah unit', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.viewAny']);

    $room = Room::create(['name' => 'Ruang 101', 'type' => 'Classroom', 'is_active' => true]);
    CampusAsset::create([
        'asset_code' => 'AST-2026-0001', 'name' => 'Proyektor',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'room_id' => $room->id,
        'condition' => 'good', 'status' => 'active',
    ]);
    CampusAsset::create([
        'asset_code' => 'AST-2026-0002', 'name' => 'Proyektor',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'room_id' => $room->id,
        'condition' => 'major_damage', 'status' => 'maintenance',
    ]);

    actingCampusAssetOperator($setup['operator'])
        ->get('/admin/campus/campus-assets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Asset/Index')
            ->where('stats.products', 1)
            ->where('stats.total', 2)
            ->where('stats.damaged', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.qty', 2)
            ->where('data.rows.0.good', 1)
            ->where('data.rows.0.damaged', 1)
            ->has('data.rows.0.showUrl'));
});

it('menampilkan halaman produk berisi daftar unit', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.view']);

    CampusAsset::create([
        'asset_code' => 'AST-2026-0012', 'name' => 'Proyektor', 'brand' => 'Epson',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'condition' => 'good', 'status' => 'active',
    ]);
    CampusAsset::create([
        'asset_code' => 'AST-2026-0018', 'name' => 'Proyektor', 'brand' => 'Epson',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'condition' => 'major_damage', 'status' => 'maintenance',
    ]);

    $electronicsId = App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id');

    actingCampusAssetOperator($setup['operator'])
        ->get("/admin/campus/products?name=Proyektor&brand=Epson&category={$electronicsId}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Asset/Product')
            ->where('product.qty', 2)
            ->where('product.damaged', 1)
            ->has('product.units', 2)
            ->where('product.units.0.assetCode', 'AST-2026-0012'));

    actingCampusAssetOperator($setup['operator'])
        ->get('/admin/campus/products?name=Tidak-Ada')
        ->assertNotFound();
});

it('membuat banyak unit bernomor urut sekaligus', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.create']);

    actingCampusAssetOperator($setup['operator'])
        ->post('/admin/campus/campus-assets/bulk-store', [
            'name' => 'Proyektor', 'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'),
            'brand' => 'Epson', 'condition' => 'good', 'status' => 'active',
            'funding_source' => 'hibah', 'funding_detail' => 'Hibah Kemendikbud 2025',
            'quantity' => 3,
        ])
        ->assertRedirect('/admin/campus/campus-assets')
        ->assertSessionHas('success');

    $codes = CampusAsset::where('name', 'Proyektor')->orderBy('asset_code')->pluck('asset_code')->all();

    expect($codes)->toHaveCount(3)
        ->and($codes[0])->toMatch('/^AST-\d{4}-\d{3}$/')
        ->and((int) substr($codes[1], -3) - (int) substr($codes[0], -3))->toBe(1)
        ->and(CampusAsset::where('name', 'Proyektor')->where('funding_source', 'hibah')->count())->toBe(3);
});

it('melaporkan kerusakan unit membuat tiket dan maintenance', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.update']);

    $asset = CampusAsset::create([
        'asset_code' => 'AST-2026-0012', 'name' => 'Proyektor',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'condition' => 'good', 'status' => 'active',
    ]);

    actingCampusAssetOperator($setup['operator'])
        ->post("/admin/campus/campus-assets/{$asset->id}/report-damage", [
            'condition' => 'major_damage', 'priority' => 'high', 'notes' => 'Lampu putus.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($asset->fresh()->condition)->toBe('major_damage')
        ->and($asset->fresh()->status)->toBe('maintenance');

    $ticket = \App\Models\Campus\FacilityMaintenanceTicket::firstOrFail();

    expect($ticket->campus_asset_id)->toBe($asset->id)
        ->and($ticket->status)->toBe('open');
});

it('menampilkan pratinjau aset beserta tiketnya', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.view']);

    $asset = CampusAsset::create([
        'asset_code' => 'AST-2026-AAAAA', 'name' => 'Proyektor',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'condition' => 'good', 'status' => 'active',
    ]);

    actingCampusAssetOperator($setup['operator'])
        ->get("/admin/campus/campus-assets/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Asset/Show')
            ->where('asset.assetCode', 'AST-2026-AAAAA'));
});

it('menyimpan aset dan menolak kategori tidak valid', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.create']);

    actingCampusAssetOperator($setup['operator'])
        ->post('/admin/campus/campus-assets', [
            'asset_code' => 'AST-2026-AAAAA', 'name' => 'Proyektor',
            'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'condition' => 'good', 'status' => 'active',
            'purchase_cost' => 8500000, 'is_borrowable' => true,
        ])
        ->assertRedirect('/admin/campus/campus-assets')
        ->assertSessionHas('success');

    expect(CampusAsset::where('asset_code', 'AST-2026-AAAAA')->firstOrFail()->purchase_cost)->toBe('8500000.00');

    actingCampusAssetOperator($setup['operator'])
        ->post('/admin/campus/campus-assets', [
            'asset_code' => 'AST-2026-BBBBB', 'name' => 'Salah',
            'category_id' => 999999, 'condition' => 'good', 'status' => 'active',
        ])
        ->assertSessionHasErrors('category_id');
});

it('menolak hapus aset yang masih memiliki tiket maintenance', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.delete']);

    $asset = CampusAsset::create([
        'asset_code' => 'AST-2026-AAAAA', 'name' => 'Proyektor',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'condition' => 'major_damage', 'status' => 'maintenance',
    ]);
    FacilityMaintenanceTicket::create([
        'ticket_number' => 'TCK-1', 'title' => 'Proyektor mati',
        'description' => 'Tidak menyala.', 'campus_asset_id' => $asset->id,
        'reported_by_user_id' => $setup['operator']->id,
        'priority' => 'high', 'status' => 'open',
    ]);

    actingCampusAssetOperator($setup['operator'])
        ->delete("/admin/campus/campus-assets/{$asset->id}")
        ->assertSessionHas('error');

    expect(CampusAsset::find($asset->id))->not->toBeNull();
});

it('mengelola sampah aset dan mengekspornya', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.viewAny', 'campus-asset.delete']);

    $asset = CampusAsset::create([
        'asset_code' => 'AST-2026-AAAAA', 'name' => 'Sementara',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'general')->value('id'), 'condition' => 'good', 'status' => 'active',
    ]);

    actingCampusAssetOperator($setup['operator'])
        ->delete("/admin/campus/campus-assets/{$asset->id}")
        ->assertRedirect('/admin/campus/campus-assets');

    actingCampusAssetOperator($setup['operator'])
        ->get('/admin/campus/campus-assets?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Asset/Index')
            ->where('filters.mode', 'trash')
            ->has('trash.rows', 1)
            ->where('trash.rows.0.assetCode', 'AST-2026-AAAAA')
            ->where('stats.trashed', 1));

    actingCampusAssetOperator($setup['operator'])
        ->post("/admin/campus/campus-assets/{$asset->id}/restore")
        ->assertSessionHas('success');

    expect(CampusAsset::find($asset->id))->not->toBeNull();

    $response = actingCampusAssetOperator($setup['operator'])
        ->get('/admin/campus/campus-assets/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.viewAny']);

    CampusAsset::create([
        'asset_code' => 'AST-2026-AAAAA', 'name' => 'Proyektor',
        'category_id' => App\Models\Campus\AssetCategory::where('code', 'electronics')->value('id'), 'condition' => 'good', 'status' => 'active',
    ]);

    $response = actingCampusAssetOperator($setup['operator'])
        ->get('/admin/campus/campus-assets/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeCampusAssetImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['asset_code', 'name', 'category', 'room', 'brand', 'condition', 'status', 'funding_source', 'funding_detail', 'is_borrowable'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-assets').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'assets.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor aset valid', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.create']);

    Room::create(['name' => 'Ruang 101', 'type' => 'Classroom', 'is_active' => true]);

    $response = actingCampusAssetOperator($setup['operator'])
        ->get('/admin/campus/campus-assets/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-campus-assets.xlsx');

    actingCampusAssetOperator($setup['operator'])
        ->post('/admin/campus/campus-assets/import', ['file' => makeCampusAssetImportFile([
            ['AST-2026-AAAAA', 'Proyektor', 'electronics', 'Ruang 101', 'Epson', 'good', 'active', 'hibah', 'Hibah 2025', '0'],
            ['AST-2026-BBBBB', 'Meja', 'furniture', '', '', 'good', 'active', '', '', '1'],
        ])])
        ->assertRedirect('/admin/campus/campus-assets')
        ->assertSessionHas('success');

    expect(CampusAsset::where('asset_code', 'AST-2026-AAAAA')->firstOrFail()->room->name)->toBe('Ruang 101')
        ->and(CampusAsset::where('asset_code', 'AST-2026-AAAAA')->firstOrFail()->funding_source)->toBe('hibah')
        ->and((bool) CampusAsset::where('asset_code', 'AST-2026-BBBBB')->firstOrFail()->is_borrowable)->toBeTrue();
});

it('menolak seluruh file aset bila ada baris tidak valid', function () {
    $setup = campusAssetCrudSetup();
    giveCampusAssetPermissionsToOperator(['campus-asset.create']);

    $response = actingCampusAssetOperator($setup['operator'])
        ->post('/admin/campus/campus-assets/import', ['file' => makeCampusAssetImportFile([
            ['AST-2026-AAAAA', 'Valid', 'electronics', '', '', 'good', 'active', ''],
            ['AST-2026-BBBBB', 'Salah Kondisi', 'electronics', '', '', 'hancur', 'active', ''],
        ])])
        ->assertRedirect('/admin/campus/campus-assets');

    expect(CampusAsset::where('asset_code', 'AST-2026-AAAAA')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
