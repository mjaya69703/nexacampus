<?php

use App\Models\Campus\Building;
use App\Models\Campus\CampusLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function campusLocationCrudSetup(): array
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

function giveCampusLocationPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingCampusLocationOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar lokasi dengan hitungan gedung', function () {
    // Catatan: DB fresh selalu berisi 1 baris bawaan MAIN_CAMPUS dari
    // migrasi unifikasi absensi (2026_09_18_150907), jadi total = 2.
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.viewAny']);

    $location = CampusLocation::create(['name' => 'Kampus Utama', 'code' => 'KMP-A', 'is_active' => true]);
    Building::create(['campus_location_id' => $location->id, 'name' => 'Gedung Rektorat', 'is_active' => true]);

    actingCampusLocationOperator($setup['operator'])
        ->get('/admin/campus/campus-locations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Location/Index')
            ->where('stats.total', 2)
            ->where('stats.buildings', 1)
            ->has('data.rows', 2)
            ->where('data.rows.0.buildingCount', 1)
            ->where('data.rows.0.code', 'KMP-A'));
});

it('menyimpan lokasi dan menolak kode duplikat', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.create']);

    actingCampusLocationOperator($setup['operator'])
        ->post('/admin/campus/campus-locations', [
            'name' => 'Kampus Utama', 'code' => 'KMP-A',
            'radius_meters' => 100, 'is_main' => true, 'is_active' => true,
        ])
        ->assertRedirect('/admin/campus/campus-locations')
        ->assertSessionHas('success');

    expect(CampusLocation::where('code', 'KMP-A')->firstOrFail()->name)->toBe('Kampus Utama');

    actingCampusLocationOperator($setup['operator'])
        ->post('/admin/campus/campus-locations', ['name' => 'Lain', 'code' => 'KMP-A'])
        ->assertSessionHasErrors('code');
});

it('menolak koordinat GPS di luar rentang valid', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.create']);

    actingCampusLocationOperator($setup['operator'])
        ->post('/admin/campus/campus-locations', [
            'name' => 'Kampus', 'code' => 'KMP-A', 'latitude' => 120, 'longitude' => 200,
        ])
        ->assertSessionHasErrors(['latitude', 'longitude']);

    expect(\App\Models\Campus\CampusLocation::where('code', 'KMP-A')->count())->toBe(0);
});

it('menegakkan satu kampus utama', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.create', 'campus-location.update']);

    $lama = CampusLocation::create(['name' => 'Lama', 'code' => 'KMP-L', 'is_main' => true, 'is_active' => true]);

    actingCampusLocationOperator($setup['operator'])
        ->post('/admin/campus/campus-locations', [
            'name' => 'Baru', 'code' => 'KMP-B', 'radius_meters' => 100, 'is_main' => true,
        ])
        ->assertRedirect('/admin/campus/campus-locations');

    expect((bool) $lama->fresh()->is_main)->toBeFalse();
    expect(CampusLocation::where('is_main', true)->count())->toBe(1);

    actingCampusLocationOperator($setup['operator'])
        ->post("/admin/campus/campus-locations/{$lama->id}/main")
        ->assertSessionHas('success');

    expect((bool) $lama->fresh()->is_main)->toBeTrue();
    expect(CampusLocation::where('is_main', true)->count())->toBe(1);
});

it('menandai lokasi sebagai titik absensi dan memfilternya', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.create', 'campus-location.viewAny']);

    actingCampusLocationOperator($setup['operator'])
        ->post('/admin/campus/campus-locations', [
            'name' => 'Kantor', 'code' => 'KTR',
            'latitude' => -6.2, 'longitude' => 106.8,
            'radius_meters' => 150, 'is_attendance_point' => true,
        ])
        ->assertRedirect('/admin/campus/campus-locations')
        ->assertSessionHas('success');

    expect(\App\Models\Campus\CampusLocation::where('code', 'KTR')->firstOrFail()->is_attendance_point)->toBeTrue();

    actingCampusLocationOperator($setup['operator'])
        ->get('/admin/campus/campus-locations?is_attendance=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 2)
            ->where('data.rows.0.code', 'KTR')
            ->where('data.rows.0.isAttendancePoint', true)
            ->where('stats.attendance', 2));
});

it('menolak hapus lokasi yang dipakai riwayat absensi', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.delete']);

    $location = \App\Models\Campus\CampusLocation::create([
        'name' => 'Kantor', 'code' => 'KTR', 'is_attendance_point' => true, 'is_active' => true,
    ]);
    $profileUser = \App\Models\User::factory()->create();
    $profile = \App\Models\Organization\EmployeeProfile::create(['user_id' => $profileUser->id, 'is_active' => true]);
    \App\Models\Organization\EmployeeAttendanceRecord::create([
        'employee_profile_id' => $profile->id,
        'attendance_date' => now()->format('Y-m-d'),
        'check_in_location_id' => $location->id,
        'status' => 'present',
    ]);

    actingCampusLocationOperator($setup['operator'])
        ->delete("/admin/campus/campus-locations/{$location->id}")
        ->assertSessionHas('error');

    expect(\App\Models\Campus\CampusLocation::find($location->id))->not->toBeNull();
});

it('menolak hapus lokasi yang masih memiliki gedung', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.delete']);

    $location = CampusLocation::create(['name' => 'Kampus Utama', 'code' => 'KMP-A', 'is_active' => true]);
    Building::create(['campus_location_id' => $location->id, 'name' => 'Gedung Rektorat', 'is_active' => true]);

    actingCampusLocationOperator($setup['operator'])
        ->delete("/admin/campus/campus-locations/{$location->id}")
        ->assertSessionHas('error');

    expect(CampusLocation::find($location->id))->not->toBeNull();

    actingCampusLocationOperator($setup['operator'])
        ->post('/admin/campus/campus-locations/bulk-destroy', ['ids' => [$location->id]])
        ->assertSessionHas('error');

    expect(CampusLocation::find($location->id))->not->toBeNull();
});

it('mengelola sampah lokasi dan mengekspornya', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.viewAny', 'campus-location.delete']);

    $location = CampusLocation::create(['name' => 'Sementara', 'code' => 'SM', 'is_active' => true]);

    actingCampusLocationOperator($setup['operator'])
        ->delete("/admin/campus/campus-locations/{$location->id}")
        ->assertRedirect('/admin/campus/campus-locations');

    actingCampusLocationOperator($setup['operator'])
        ->get('/admin/campus/campus-locations?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingCampusLocationOperator($setup['operator'])
        ->post("/admin/campus/campus-locations/{$location->id}/restore")
        ->assertSessionHas('success');

    expect(CampusLocation::find($location->id))->not->toBeNull();

    $response = actingCampusLocationOperator($setup['operator'])
        ->get('/admin/campus/campus-locations/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.viewAny']);

    CampusLocation::create(['name' => 'Kampus Utama', 'code' => 'KMP-A', 'is_active' => true]);

    $response = actingCampusLocationOperator($setup['operator'])
        ->get('/admin/campus/campus-locations/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeCampusLocationImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['name', 'code', 'address', 'phone', 'email', 'latitude', 'longitude', 'radius_meters', 'is_main', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-locations').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'locations.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor lokasi valid', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.create']);

    $response = actingCampusLocationOperator($setup['operator'])
        ->get('/admin/campus/campus-locations/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-campus-locations.xlsx');

    actingCampusLocationOperator($setup['operator'])
        ->post('/admin/campus/campus-locations/import', ['file' => makeCampusLocationImportFile([
            ['Kampus Utama', 'KMP-A', '', '', '', '-6.2', '106.8', '100', '1', '1'],
            ['Kampus Cabang', 'KMP-B', '', '', '', '', '', '', '0', '1'],
        ])])
        ->assertRedirect('/admin/campus/campus-locations')
        ->assertSessionHas('success');

    expect(CampusLocation::where('code', 'KMP-A')->firstOrFail()->is_main)->toBeTrue()
        ->and(CampusLocation::where('is_main', true)->count())->toBe(1);
});

it('menolak seluruh file lokasi bila ada baris tidak valid', function () {
    $setup = campusLocationCrudSetup();
    giveCampusLocationPermissionsToOperator(['campus-location.create']);

    CampusLocation::create(['name' => 'Lama', 'code' => 'KMP-A', 'is_active' => true]);

    $response = actingCampusLocationOperator($setup['operator'])
        ->post('/admin/campus/campus-locations/import', ['file' => makeCampusLocationImportFile([
            ['Baru', 'KMP-B', '', '', '', '', '', '', '', ''],
            ['Duplikat', 'KMP-A', '', '', '', '', '', '', '', ''],
        ])])
        ->assertRedirect('/admin/campus/campus-locations');

    expect(CampusLocation::where('code', 'KMP-B')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
