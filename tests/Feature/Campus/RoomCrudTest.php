<?php

use App\Models\Academic\CourseSchedule;
use App\Models\Campus\Building;
use App\Models\Campus\CampusAsset;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function roomCrudSetup(): array
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

function giveRoomPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingRoomOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar ruangan dengan gedung', function () {
    $setup = roomCrudSetup();
    giveRoomPermissionsToOperator(['room.viewAny']);

    $building = Building::create(['name' => 'Gedung Kuliah', 'is_active' => true]);
    Room::create(['building_id' => $building->id, 'name' => 'Ruang 101', 'code' => 'R-101', 'capacity' => 30, 'type' => 'Classroom', 'is_active' => true]);

    actingRoomOperator($setup['operator'])
        ->get('/admin/campus/rooms')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Room/Index')
            ->where('stats.total', 1)
            ->where('stats.capacity', 30)
            ->has('data.rows', 1)
            ->where('data.rows.0.building', 'Gedung Kuliah')
            ->where('data.rows.0.usageCount', 0));
});

it('menyimpan ruangan dan menolak tipe tidak valid', function () {
    $setup = roomCrudSetup();
    giveRoomPermissionsToOperator(['room.create']);

    actingRoomOperator($setup['operator'])
        ->post('/admin/campus/rooms', [
            'name' => 'Ruang 101', 'code' => 'R-101',
            'capacity' => 30, 'type' => 'Classroom', 'is_active' => true,
        ])
        ->assertRedirect('/admin/campus/rooms')
        ->assertSessionHas('success');

    expect(Room::where('code', 'R-101')->firstOrFail()->capacity)->toBe(30);

    actingRoomOperator($setup['operator'])
        ->post('/admin/campus/rooms', ['name' => 'Salah', 'type' => 'Gudang'])
        ->assertSessionHasErrors('type');
});

it('menolak hapus ruangan yang dipakai jadwal atau reservasi', function () {
    $setup = roomCrudSetup();
    giveRoomPermissionsToOperator(['room.delete']);

    $room = Room::create(['name' => 'Ruang 101', 'type' => 'Classroom', 'is_active' => true]);
    RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $room->id,
        'user_id' => $setup['operator']->id, 'title' => 'Rapat',
        'reservation_date' => now()->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00',
        'status' => 'approved',
    ]);

    actingRoomOperator($setup['operator'])
        ->delete("/admin/campus/rooms/{$room->id}")
        ->assertSessionHas('error');

    expect(Room::find($room->id))->not->toBeNull();

    actingRoomOperator($setup['operator'])
        ->post('/admin/campus/rooms/bulk-destroy', ['ids' => [$room->id]])
        ->assertSessionHas('error');

    expect(Room::find($room->id))->not->toBeNull();
});

it('mengelola sampah ruangan dan mengekspornya', function () {
    $setup = roomCrudSetup();
    giveRoomPermissionsToOperator(['room.viewAny', 'room.delete']);

    $room = Room::create(['name' => 'Sementara', 'type' => 'Classroom', 'is_active' => true]);

    actingRoomOperator($setup['operator'])
        ->delete("/admin/campus/rooms/{$room->id}")
        ->assertRedirect('/admin/campus/rooms');

    actingRoomOperator($setup['operator'])
        ->get('/admin/campus/rooms?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingRoomOperator($setup['operator'])
        ->post("/admin/campus/rooms/{$room->id}/restore")
        ->assertSessionHas('success');

    expect(Room::find($room->id))->not->toBeNull();

    $response = actingRoomOperator($setup['operator'])
        ->get('/admin/campus/rooms/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = roomCrudSetup();
    giveRoomPermissionsToOperator(['room.viewAny']);

    Room::create(['name' => 'Ruang 101', 'type' => 'Classroom', 'is_active' => true]);

    $response = actingRoomOperator($setup['operator'])
        ->get('/admin/campus/rooms/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeRoomImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['name', 'code', 'building', 'floor', 'capacity', 'type', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-rooms').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'rooms.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor ruangan valid', function () {
    $setup = roomCrudSetup();
    giveRoomPermissionsToOperator(['room.create']);

    Building::create(['name' => 'Gedung Kuliah', 'is_active' => true]);

    $response = actingRoomOperator($setup['operator'])
        ->get('/admin/campus/rooms/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-rooms.xlsx');

    actingRoomOperator($setup['operator'])
        ->post('/admin/campus/rooms/import', ['file' => makeRoomImportFile([
            ['Ruang 101', 'R-101', 'Gedung Kuliah', '1', '30', 'Classroom', '1'],
            ['Lab Komputer', '', '', '', '', 'Laboratory', ''],
        ])])
        ->assertRedirect('/admin/campus/rooms')
        ->assertSessionHas('success');

    expect(Room::where('code', 'R-101')->firstOrFail()->capacity)->toBe(30)
        ->and(Room::where('name', 'Lab Komputer')->firstOrFail()->type)->toBe('Laboratory');
});

it('menolak seluruh file ruangan bila ada baris tidak valid', function () {
    $setup = roomCrudSetup();
    giveRoomPermissionsToOperator(['room.create']);

    $response = actingRoomOperator($setup['operator'])
        ->post('/admin/campus/rooms/import', ['file' => makeRoomImportFile([
            ['Valid', '', '', '', '', 'Classroom', ''],
            ['Gedung Salah', '', 'Tidak Ada', '', '', 'Classroom', ''],
        ])])
        ->assertRedirect('/admin/campus/rooms');

    expect(Room::where('name', 'Valid')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
