<?php

use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function reservationCrudSetup(): array
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

    $room = Room::create(['name' => 'Ruang 101', 'capacity' => 30, 'type' => 'Meeting Room', 'is_active' => true]);

    return compact('operator', 'room');
}

function giveReservationPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingReservationOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function nextMonday(): string
{
    return now()->next('Monday')->format('Y-m-d');
}

it('menampilkan daftar reservasi dengan status', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.viewAny']);

    RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Rapat',
        'reservation_date' => nextMonday(), 'start_time' => '09:00', 'end_time' => '10:00',
        'status' => 'pending',
    ]);

    actingReservationOperator($setup['operator'])
        ->get('/admin/campus/room-reservations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Reservation/Index')
            ->where('stats.total', 1)
            ->where('stats.pending', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.status', 'pending'));
});

it('membuat reservasi selalu pending dan menolak peserta melebihi kapasitas', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.create']);

    actingReservationOperator($setup['operator'])
        ->post('/admin/campus/room-reservations', [
            'room_id' => $setup['room']->id, 'user_id' => $setup['operator']->id,
            'title' => 'Rapat', 'reservation_date' => nextMonday(),
            'start_time' => '09:00', 'end_time' => '10:00', 'attendee_count' => 10,
        ])
        ->assertRedirect('/admin/campus/room-reservations')
        ->assertSessionHas('success');

    $reservation = RoomReservation::firstOrFail();

    expect($reservation->status)->toBe('pending')
        ->and($reservation->reservation_number)->toStartWith('RES-');

    actingReservationOperator($setup['operator'])
        ->post('/admin/campus/room-reservations', [
            'room_id' => $setup['room']->id, 'user_id' => $setup['operator']->id,
            'title' => 'Kebanyakan', 'reservation_date' => nextMonday(),
            'start_time' => '11:00', 'end_time' => '12:00', 'attendee_count' => 100,
        ])
        ->assertSessionHasErrors('attendee_count');

    expect(RoomReservation::count())->toBe(1);
});

it('menolak jam selesai sebelum mulai dan tanggal lampau', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.create']);

    actingReservationOperator($setup['operator'])
        ->post('/admin/campus/room-reservations', [
            'room_id' => $setup['room']->id, 'user_id' => $setup['operator']->id,
            'title' => 'Jam Salah', 'reservation_date' => nextMonday(),
            'start_time' => '10:00', 'end_time' => '09:00', 'attendee_count' => 5,
        ])
        ->assertSessionHasErrors('end_time');

    actingReservationOperator($setup['operator'])
        ->post('/admin/campus/room-reservations', [
            'room_id' => $setup['room']->id, 'user_id' => $setup['operator']->id,
            'title' => 'Lampau', 'reservation_date' => now()->subDay()->format('Y-m-d'),
            'start_time' => '09:00', 'end_time' => '10:00', 'attendee_count' => 5,
        ])
        ->assertSessionHasErrors('reservation_date');

    expect(RoomReservation::count())->toBe(0);
});

it('menyetujui reservasi bebas bentrok dan menolak yang bentrok', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.approve']);

    $first = RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Pertama',
        'reservation_date' => nextMonday(), 'start_time' => '09:00', 'end_time' => '10:00',
        'status' => 'pending',
    ]);
    $second = RoomReservation::create([
        'reservation_number' => 'RES-2', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Kedua',
        'reservation_date' => nextMonday(), 'start_time' => '09:30', 'end_time' => '10:30',
        'status' => 'pending',
    ]);

    actingReservationOperator($setup['operator'])
        ->post("/admin/campus/room-reservations/{$first->id}/approve")
        ->assertSessionHas('success');

    expect($first->fresh()->status)->toBe('approved');
    expect($first->fresh()->approved_by)->toBe($setup['operator']->id);

    actingReservationOperator($setup['operator'])
        ->post("/admin/campus/room-reservations/{$second->id}/approve")
        ->assertSessionHas('error');

    expect($second->fresh()->status)->toBe('pending');
});

it('menolak approve tanpa izin approve', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.update']);

    $reservation = RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Rapat',
        'reservation_date' => nextMonday(), 'start_time' => '09:00', 'end_time' => '10:00',
        'status' => 'pending',
    ]);

    actingReservationOperator($setup['operator'])
        ->post("/admin/campus/room-reservations/{$reservation->id}/approve")
        ->assertForbidden();

    expect($reservation->fresh()->status)->toBe('pending');
});

it('menolak penolakan tanpa alasan dan membersihkan approval saat batal', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.approve', 'room-reservation.update']);

    $reservation = RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Rapat',
        'reservation_date' => nextMonday(), 'start_time' => '09:00', 'end_time' => '10:00',
        'status' => 'pending',
    ]);

    actingReservationOperator($setup['operator'])
        ->post("/admin/campus/room-reservations/{$reservation->id}/reject", [])
        ->assertSessionHasErrors('rejection_reason');

    actingReservationOperator($setup['operator'])
        ->post("/admin/campus/room-reservations/{$reservation->id}/reject", ['rejection_reason' => 'Bentrok acara rektorat'])
        ->assertSessionHas('success');

    expect($reservation->fresh()->status)->toBe('rejected');

    $approved = RoomReservation::create([
        'reservation_number' => 'RES-2', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'FIX',
        'reservation_date' => nextMonday(), 'start_time' => '13:00', 'end_time' => '14:00',
        'status' => 'approved', 'approved_by' => $setup['operator']->id, 'approved_at' => now(),
    ]);

    actingReservationOperator($setup['operator'])
        ->post("/admin/campus/room-reservations/{$approved->id}/cancel")
        ->assertSessionHas('success');

    $fresh = $approved->fresh();

    expect($fresh->status)->toBe('cancelled')
        ->and($fresh->approved_by)->toBeNull()
        ->and($fresh->approved_at)->toBeNull();
});

it('mencari pemohon lewat search-users', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.create']);

    actingReservationOperator($setup['operator'])
        ->get('/admin/campus/room-reservations/search-users?q='.$setup['operator']->first_name)
        ->assertOk()
        ->assertJsonCount(1, 'options');
});

it('menampilkan pratinjau reservasi beserta konfliknya', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.view']);

    $reservation = RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Rapat',
        'reservation_date' => nextMonday(), 'start_time' => '09:00', 'end_time' => '10:00',
        'status' => 'pending',
    ]);

    actingReservationOperator($setup['operator'])
        ->get("/admin/campus/room-reservations/{$reservation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campus/Reservation/Show')
            ->where('reservation.reservationNumber', 'RES-1'));
});

it('mengelola sampah reservasi dan mengekspornya', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.viewAny', 'room-reservation.delete']);

    $reservation = RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Sementara',
        'reservation_date' => nextMonday(), 'start_time' => '09:00', 'end_time' => '10:00',
        'status' => 'pending',
    ]);

    actingReservationOperator($setup['operator'])
        ->delete("/admin/campus/room-reservations/{$reservation->id}")
        ->assertRedirect('/admin/campus/room-reservations');

    actingReservationOperator($setup['operator'])
        ->get('/admin/campus/room-reservations?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingReservationOperator($setup['operator'])
        ->post("/admin/campus/room-reservations/{$reservation->id}/restore")
        ->assertSessionHas('success');

    expect(RoomReservation::find($reservation->id))->not->toBeNull();

    $response = actingReservationOperator($setup['operator'])
        ->get('/admin/campus/room-reservations/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = reservationCrudSetup();
    giveReservationPermissionsToOperator(['room-reservation.viewAny']);

    RoomReservation::create([
        'reservation_number' => 'RES-1', 'room_id' => $setup['room']->id,
        'user_id' => $setup['operator']->id, 'title' => 'Rapat',
        'reservation_date' => nextMonday(), 'start_time' => '09:00', 'end_time' => '10:00',
        'status' => 'pending',
    ]);

    $response = actingReservationOperator($setup['operator'])
        ->get('/admin/campus/room-reservations/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});
