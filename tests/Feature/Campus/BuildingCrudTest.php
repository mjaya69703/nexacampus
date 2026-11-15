<?php

use App\Models\Campus\Building;
use App\Models\Campus\CampusLocation;
use App\Models\Campus\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function buildingCrudSetup(): array
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

function giveBuildingPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingBuildingOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar gedung dengan lokasi dan hitungan ruangan', function () {
    $setup = buildingCrudSetup();
    giveBuildingPermissionsToOperator(['building.viewAny']);

    $location = CampusLocation::create(['name' => 'Kampus Utama', 'code' => 'KMP-A', 'is_active' => true]);
    $building = Building::create(['campus_location_id' => $location->id, 'name' => 'Gedung Rektorat', 'code' => 'BLD-RT', 'is_active' => true]);
    Room::create(['building_id' => $building->id, 'name' => 'Ruang Rapat', 'type' => 'Meeting Room', 'is_active' => true]);

    actingBuildingOperator($setup['operator'])
        ->get('/admin/campus/buildings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Building/Index')
            ->where('stats.total', 1)
            ->where('stats.rooms', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.roomCount', 1)
            ->where('data.rows.0.location', 'Kampus Utama'));
});

it('menyimpan gedung beserta lokasinya', function () {
    $setup = buildingCrudSetup();
    giveBuildingPermissionsToOperator(['building.create']);

    $location = CampusLocation::create(['name' => 'Kampus Utama', 'code' => 'KMP-A', 'is_active' => true]);

    actingBuildingOperator($setup['operator'])
        ->post('/admin/campus/buildings', [
            'name' => 'Gedung Rektorat', 'code' => 'BLD-RT',
            'campus_location_id' => $location->id, 'floor_count' => 5, 'is_active' => true,
        ])
        ->assertRedirect('/admin/campus/buildings')
        ->assertSessionHas('success');

    expect(Building::where('code', 'BLD-RT')->firstOrFail()->campus_location_id)->toBe($location->id);
});

it('menonaktifkan gedung ikut menonaktifkan ruangannya', function () {
    $setup = buildingCrudSetup();
    giveBuildingPermissionsToOperator(['building.update']);

    $building = Building::create(['name' => 'Gedung Rektorat', 'is_active' => true]);
    $room = Room::create(['building_id' => $building->id, 'name' => 'Ruang Rapat', 'type' => 'Meeting Room', 'is_active' => true]);

    actingBuildingOperator($setup['operator'])
        ->post("/admin/campus/buildings/{$building->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect((bool) $room->fresh()->is_active)->toBeFalse();

    $room->update(['is_active' => true]);

    actingBuildingOperator($setup['operator'])
        ->put("/admin/campus/buildings/{$building->id}", [
            'name' => 'Gedung Rektorat', 'is_active' => false,
        ])
        ->assertRedirect('/admin/campus/buildings');

    expect((bool) $room->fresh()->is_active)->toBeFalse();
});

it('menolak hapus gedung yang masih memiliki ruangan', function () {
    $setup = buildingCrudSetup();
    giveBuildingPermissionsToOperator(['building.delete']);

    $building = Building::create(['name' => 'Gedung Rektorat', 'is_active' => true]);
    Room::create(['building_id' => $building->id, 'name' => 'Ruang Rapat', 'type' => 'Meeting Room', 'is_active' => true]);

    actingBuildingOperator($setup['operator'])
        ->delete("/admin/campus/buildings/{$building->id}")
        ->assertSessionHas('error');

    expect(Building::find($building->id))->not->toBeNull();

    actingBuildingOperator($setup['operator'])
        ->post('/admin/campus/buildings/bulk-destroy', ['ids' => [$building->id]])
        ->assertSessionHas('error');

    expect(Building::find($building->id))->not->toBeNull();
});

it('mengelola sampah gedung dan mengekspornya', function () {
    $setup = buildingCrudSetup();
    giveBuildingPermissionsToOperator(['building.viewAny', 'building.delete']);

    $building = Building::create(['name' => 'Sementara', 'is_active' => true]);

    actingBuildingOperator($setup['operator'])
        ->delete("/admin/campus/buildings/{$building->id}")
        ->assertRedirect('/admin/campus/buildings');

    actingBuildingOperator($setup['operator'])
        ->get('/admin/campus/buildings?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingBuildingOperator($setup['operator'])
        ->post("/admin/campus/buildings/{$building->id}/restore")
        ->assertSessionHas('success');

    expect(Building::find($building->id))->not->toBeNull();

    $response = actingBuildingOperator($setup['operator'])
        ->get('/admin/campus/buildings/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = buildingCrudSetup();
    giveBuildingPermissionsToOperator(['building.viewAny']);

    Building::create(['name' => 'Gedung Rektorat', 'is_active' => true]);

    $response = actingBuildingOperator($setup['operator'])
        ->get('/admin/campus/buildings/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeBuildingImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['name', 'code', 'location', 'address', 'floor_count', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-buildings').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'buildings.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor gedung valid', function () {
    $setup = buildingCrudSetup();
    giveBuildingPermissionsToOperator(['building.create']);

    CampusLocation::create(['name' => 'Kampus Utama', 'code' => 'KMP-A', 'is_active' => true]);

    $response = actingBuildingOperator($setup['operator'])
        ->get('/admin/campus/buildings/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-buildings.xlsx');

    actingBuildingOperator($setup['operator'])
        ->post('/admin/campus/buildings/import', ['file' => makeBuildingImportFile([
            ['Gedung Rektorat', 'BLD-RT', 'Kampus Utama', '', '5', '1'],
            ['Gedung Kuliah', '', '', '', '', ''],
        ])])
        ->assertRedirect('/admin/campus/buildings')
        ->assertSessionHas('success');

    expect(Building::where('code', 'BLD-RT')->firstOrFail()->floor_count)->toBe(5)
        ->and(Building::where('name', 'Gedung Kuliah')->firstOrFail()->code)->toBeNull();
});

it('menolak seluruh file gedung bila ada baris tidak valid', function () {
    $setup = buildingCrudSetup();
    giveBuildingPermissionsToOperator(['building.create']);

    $response = actingBuildingOperator($setup['operator'])
        ->post('/admin/campus/buildings/import', ['file' => makeBuildingImportFile([
            ['Valid', '', '', '', '', ''],
            ['Lokasi Salah', '', 'Tidak Ada', '', '', ''],
        ])])
        ->assertRedirect('/admin/campus/buildings');

    expect(Building::where('name', 'Valid')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
