<?php

use App\Models\Campus\CampusAsset;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Models\Campus\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function maintenanceCrudSetup(): array
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

function giveMaintenancePermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingMaintenanceOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeTicket($operator, array $overrides = []): FacilityMaintenanceTicket
{
    return FacilityMaintenanceTicket::create(array_merge([
        'ticket_number' => 'TCK-'.strtoupper(Str::random(8)),
        'title' => 'Proyektor mati',
        'description' => 'Tidak menyala sama sekali.',
        'reported_by_user_id' => $operator->id,
        'priority' => 'high',
        'status' => 'open',
    ], $overrides));
}

it('menampilkan daftar tiket dengan prioritas dan status', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.viewAny']);

    makeTicket($setup['operator']);

    actingMaintenanceOperator($setup['operator'])
        ->get('/admin/campus/facility-maintenances')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Maintenance/Index')
            ->where('stats.total', 1)
            ->where('stats.open', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.priority', 'high'));
});

it('membuat tiket selalu open dengan nomor anti-duplikat', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.create']);

    actingMaintenanceOperator($setup['operator'])
        ->post('/admin/campus/facility-maintenances', [
            'title' => 'AC bocor',
            'description' => 'Air menetes.',
            'reported_by_user_id' => $setup['operator']->id,
            'priority' => 'urgent',
            'status' => 'resolved',
        ])
        ->assertRedirect('/admin/campus/facility-maintenances')
        ->assertSessionHas('success');

    $ticket = FacilityMaintenanceTicket::firstOrFail();

    expect($ticket->status)->toBe('open')
        ->and($ticket->ticket_number)->toStartWith('TCK-');
});

it('menyelesaikan tiket lewat izin resolve dan mengembalikan aset', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.resolve']);

    $asset = CampusAsset::create([
        'asset_code' => 'AST-1', 'name' => 'Proyektor',
        'category' => 'electronics', 'condition' => 'major_damage', 'status' => 'maintenance',
    ]);
    $ticket = makeTicket($setup['operator'], ['campus_asset_id' => $asset->id]);

    actingMaintenanceOperator($setup['operator'])
        ->post("/admin/campus/facility-maintenances/{$ticket->id}/resolve", [
            'resolution_notes' => 'Ganti lampu.',
            'repair_cost' => 500000,
        ])
        ->assertSessionHas('success');

    $fresh = $ticket->fresh();

    expect($fresh->status)->toBe('resolved')
        ->and($fresh->resolved_at)->not->toBeNull()
        ->and($asset->fresh()->condition)->toBe('good')
        ->and($asset->fresh()->status)->toBe('active');
});

it('menolak resolve tanpa izin resolve dan tanpa catatan', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.update']);

    $ticket = makeTicket($setup['operator']);

    actingMaintenanceOperator($setup['operator'])
        ->post("/admin/campus/facility-maintenances/{$ticket->id}/resolve", [
            'resolution_notes' => 'Selesai.',
        ])
        ->assertForbidden();

    expect($ticket->fresh()->status)->toBe('open');

    giveMaintenancePermissionsToOperator(['facility-maintenance.resolve']);

    actingMaintenanceOperator($setup['operator'])
        ->post("/admin/campus/facility-maintenances/{$ticket->id}/resolve", [])
        ->assertSessionHasErrors('resolution_notes');

    expect($ticket->fresh()->status)->toBe('open');
});

it('mengosongkan resolved_at saat status keluar dari resolved', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.update']);

    $ticket = makeTicket($setup['operator'], ['status' => 'resolved', 'resolved_at' => now()]);

    actingMaintenanceOperator($setup['operator'])
        ->put("/admin/campus/facility-maintenances/{$ticket->id}", [
            'title' => $ticket->title,
            'description' => $ticket->description,
            'reported_by_user_id' => $setup['operator']->id,
            'priority' => 'high',
            'status' => 'in_progress',
        ])
        ->assertRedirect('/admin/campus/facility-maintenances');

    expect($ticket->fresh()->resolved_at)->toBeNull();
});

it('mencari ruangan dan aset lewat search endpoint', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.create']);

    $room = \App\Models\Campus\Room::create(['name' => 'Ruang 101', 'type' => 'Classroom', 'is_active' => true]);
    \App\Models\Campus\CampusAsset::create([
        'asset_code' => 'AST-1', 'name' => 'Proyektor',
        'category' => 'electronics', 'room_id' => $room->id,
        'condition' => 'good', 'status' => 'active',
    ]);

    actingMaintenanceOperator($setup['operator'])
        ->get('/admin/campus/facility-maintenances/search-rooms?q=Ruang')
        ->assertOk()
        ->assertJsonCount(1, 'options')
        ->assertJsonPath('options.0.label', 'Ruang 101');

    actingMaintenanceOperator($setup['operator'])
        ->get('/admin/campus/facility-maintenances/search-assets?q=Proyektor')
        ->assertOk()
        ->assertJsonCount(1, 'options')
        ->assertJsonPath('options.0.meta.room_id', $room->id)
        ->assertJsonPath('options.0.meta.room_label', 'Ruang 101');
});

it('mencari teknisi lewat search-users', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.create']);

    actingMaintenanceOperator($setup['operator'])
        ->get('/admin/campus/facility-maintenances/search-users?q='.$setup['operator']->first_name)
        ->assertOk()
        ->assertJsonCount(1, 'options');
});

it('mengelola sampah tiket dan mengekspornya', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.viewAny', 'facility-maintenance.delete']);

    $ticket = makeTicket($setup['operator']);

    actingMaintenanceOperator($setup['operator'])
        ->delete("/admin/campus/facility-maintenances/{$ticket->id}")
        ->assertRedirect('/admin/campus/facility-maintenances');

    actingMaintenanceOperator($setup['operator'])
        ->get('/admin/campus/facility-maintenances?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingMaintenanceOperator($setup['operator'])
        ->post("/admin/campus/facility-maintenances/{$ticket->id}/restore")
        ->assertSessionHas('success');

    expect(FacilityMaintenanceTicket::find($ticket->id))->not->toBeNull();

    $response = actingMaintenanceOperator($setup['operator'])
        ->get('/admin/campus/facility-maintenances/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = maintenanceCrudSetup();
    giveMaintenancePermissionsToOperator(['facility-maintenance.viewAny']);

    makeTicket($setup['operator']);

    $response = actingMaintenanceOperator($setup['operator'])
        ->get('/admin/campus/facility-maintenances/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});
