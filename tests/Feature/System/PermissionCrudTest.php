<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function permissionCrudSetup(): array
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

function givePermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar permission dengan statistik dan baris terpetakan', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.viewAny']);

    Permission::create(['name' => 'user.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'user.create', 'guard_name' => 'api']);

    // Catatan: givePermissionsToOperator ikut membuat baris permission.viewAny.
    actingOperator($setup['operator'])
        ->get('/admin/access/permissions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Access/Permission/Index')
            ->has('shell')
            ->where('can.create', false)
            ->where('stats.total', 3)
            ->where('stats.web', 2)
            ->where('stats.api', 1)
            ->where('stats.attached', 1)
            ->has('data.rows', 3)
            ->where('data.rows.0.name', 'user.create')
            ->where('data.total', 3)
            ->has('urls'));
});

it('memfilter, mengurutkan, dan memaginasi daftar permission', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.viewAny']);

    foreach (range(1, 12) as $i) {
        Permission::create(['name' => sprintf('modul.aksi%02d', $i), 'guard_name' => 'web']);
    }

    // Halaman 2 berisi 3 baris (12 dibuat + 1 permission gate).
    actingOperator($setup['operator'])
        ->get('/admin/access/permissions?page=2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 3)
            ->where('data.currentPage', 2)
            ->where('data.total', 13));

    // Filter nama.
    actingOperator($setup['operator'])
        ->get('/admin/access/permissions?q=aksi01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.name', 'modul.aksi01'));

    // Filter guard menghasilkan kosong.
    actingOperator($setup['operator'])
        ->get('/admin/access/permissions?guard=api')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 0));
});

it('mendukung jumlah baris per halaman dan nomor urut kontinu', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.viewAny']);

    foreach (range(1, 20) as $i) {
        Permission::create(['name' => sprintf('modul.aksi%02d', $i), 'guard_name' => 'web']);
    }

    actingOperator($setup['operator'])
        ->get('/admin/access/permissions?perPage=15')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 15)
            ->where('data.rows.0.no', 1)
            ->where('data.rows.14.no', 15));

    actingOperator($setup['operator'])
        ->get('/admin/access/permissions?perPage=15&page=2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.rows.0.no', 16)
            ->where('filters.perPage', 15));
});

it('menolak akses tanpa permission yang sesuai', function () {
    $setup = permissionCrudSetup();

    actingOperator($setup['operator'])
        ->get('/admin/access/permissions')
        ->assertForbidden();
});

it('menyimpan permission baru beserta mapping role', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.viewAny', 'permission.create']);

    $role = Role::create(['name' => 'petugas', 'guard_name' => 'web']);

    actingOperator($setup['operator'])
        ->post('/admin/access/permissions', [
            'name' => 'user.viewAny',
            'guard_name' => 'web',
            'role_ids' => [$role->id],
        ])
        ->assertRedirect('/admin/access/permissions')
        ->assertSessionHas('success');

    $permission = Permission::where('name', 'user.viewAny')->firstOrFail();

    expect($permission->roles->pluck('id')->all())->toBe([$role->id]);
});

it('memvalidasi nama unik per guard', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.create']);

    Permission::create(['name' => 'user.viewAny', 'guard_name' => 'web']);

    // Duplikat guard sama ditolak.
    actingOperator($setup['operator'])
        ->post('/admin/access/permissions', ['name' => 'user.viewAny', 'guard_name' => 'web'])
        ->assertSessionHasErrors('name');

    // Guard beda diterima.
    actingOperator($setup['operator'])
        ->post('/admin/access/permissions', ['name' => 'user.viewAny', 'guard_name' => 'api'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/access/permissions');

    expect(Permission::where('name', 'user.viewAny')->count())->toBe(2);
});

it('memperbarui permission dan me-reset mapping role yang dikosongkan', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.update']);

    $role = Role::create(['name' => 'petugas', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'user.lama', 'guard_name' => 'web']);
    $permission->syncRoles([$role]);

    actingOperator($setup['operator'])
        ->put("/admin/access/permissions/{$permission->id}", [
            'name' => 'user.baru',
            'guard_name' => 'web',
            'role_ids' => [],
        ])
        ->assertRedirect('/admin/access/permissions')
        ->assertSessionHas('success');

    expect($permission->fresh()->name)->toBe('user.baru')
        ->and($permission->fresh()->roles)->toHaveCount(0);
});

it('menghapus permission yang tidak dipakai dan menolak yang dipakai role', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.delete']);

    $free = Permission::create(['name' => 'bebas.hapus', 'guard_name' => 'web']);
    $used = Permission::create(['name' => 'dipakai.role', 'guard_name' => 'web']);
    $used->syncRoles(Role::create(['name' => 'petugas', 'guard_name' => 'web']));

    actingOperator($setup['operator'])
        ->delete("/admin/access/permissions/{$free->id}")
        ->assertRedirect('/admin/access/permissions')
        ->assertSessionHas('success');

    expect(Permission::find($free->id))->toBeNull();

    actingOperator($setup['operator'])
        ->delete("/admin/access/permissions/{$used->id}")
        ->assertSessionHas('error');

    expect(Permission::find($used->id))->not->toBeNull();
});

it('menghapus massal dengan melewati permission yang dipakai role', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.delete']);

    $free = Permission::create(['name' => 'bebas.hapus', 'guard_name' => 'web']);
    $used = Permission::create(['name' => 'dipakai.role', 'guard_name' => 'web']);
    $used->syncRoles(Role::create(['name' => 'petugas', 'guard_name' => 'web']));

    actingOperator($setup['operator'])
        ->post('/admin/access/permissions/bulk-destroy', ['ids' => [$free->id, $used->id]])
        ->assertSessionHas('warning');

    expect(Permission::find($free->id))->toBeNull()
        ->and(Permission::find($used->id))->not->toBeNull();
});

it('menampilkan mode sampah dan memulihkan permission', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.viewAny', 'permission.delete']);

    $trashed = Permission::create(['name' => 'sampah.pulih', 'guard_name' => 'web']);
    $trashed->delete();
    Permission::create(['name' => 'aktif.biasa', 'guard_name' => 'web']);

    actingOperator($setup['operator'])
        ->get('/admin/access/permissions?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Access/Permission/Index')
            ->where('filters.mode', 'trash')
            ->where('stats.trashed', 1)
            ->where('can.restore', true)
            ->has('data.rows', 1)
            ->where('data.rows.0.name', 'sampah.pulih')
            ->where('data.rows.0.editUrl', null));

    actingOperator($setup['operator'])
        ->post("/admin/access/permissions/{$trashed->id}/restore")
        ->assertSessionHas('success');

    expect(Permission::find($trashed->id))->not->toBeNull();
});

it('menolak restore tanpa permission update maupun delete', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.viewAny']);

    $trashed = Permission::create(['name' => 'sampah.tolak', 'guard_name' => 'web']);
    $trashed->delete();

    actingOperator($setup['operator'])
        ->post("/admin/access/permissions/{$trashed->id}/restore")
        ->assertForbidden();

    expect(Permission::onlyTrashed()->find($trashed->id))->not->toBeNull();
});

it('menghapus permanen permission bebas dan menolak yang dipakai role', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.delete']);

    $free = Permission::create(['name' => 'bebas.musnah', 'guard_name' => 'web']);
    $free->delete();
    $used = Permission::create(['name' => 'dipakai.musnah', 'guard_name' => 'web']);
    $used->syncRoles(Role::create(['name' => 'petugas', 'guard_name' => 'web']));
    $used->delete();

    actingOperator($setup['operator'])
        ->delete("/admin/access/permissions/{$free->id}/force")
        ->assertSessionHas('success');

    expect(Permission::withTrashed()->find($free->id))->toBeNull();

    actingOperator($setup['operator'])
        ->delete("/admin/access/permissions/{$used->id}/force")
        ->assertSessionHas('error');

    expect(Permission::onlyTrashed()->find($used->id))->not->toBeNull();
});

it('memulihkan massal permission sampah', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.update']);

    $first = Permission::create(['name' => 'massal.satu', 'guard_name' => 'web']);
    $first->delete();
    $second = Permission::create(['name' => 'massal.dua', 'guard_name' => 'web']);
    $second->delete();

    actingOperator($setup['operator'])
        ->post('/admin/access/permissions/bulk-restore', ['ids' => [$first->id, $second->id]])
        ->assertSessionHas('success');

    expect(Permission::onlyTrashed()->count())->toBe(0);
});

it('mengekspor permission tersaring ke xlsx', function () {
    $setup = permissionCrudSetup();
    givePermissionsToOperator(['permission.viewAny']);

    Permission::create(['name' => 'user.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'lain.akses', 'guard_name' => 'api']);

    $response = actingOperator($setup['operator'])
        ->get('/admin/access/permissions/export?format=xlsx&q=user')
        ->assertOk();

    expect($response->headers->get('Content-Type'))
        ->toContain('spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toContain('.xlsx');

    $csv = actingOperator($setup['operator'])
        ->get('/admin/access/permissions/export?format=csv')
        ->assertOk();

    expect($csv->headers->get('Content-Type'))->toContain('text/csv');
});
