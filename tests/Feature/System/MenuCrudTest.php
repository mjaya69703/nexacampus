<?php

use App\Models\Settings\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function menuCrudSetup(): array
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

function giveMenuPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingMenuOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar menu dengan statistik dan parent', function () {
    $setup = menuCrudSetup();
    giveMenuPermissionsToOperator(['menu.viewAny']);

    $parent = Menu::create([
        'type' => 'group', 'title' => 'Manajemen Akses', 'sort_order' => 80, 'is_active' => true,
    ]);
    Menu::create([
        'type' => 'link', 'parent_id' => $parent->id, 'title' => 'Pengguna',
        'route_name' => 'admin.access.users.index', 'icon' => 'fas fa-users',
        'permission_name' => 'user.viewAny', 'sort_order' => 1, 'is_active' => true,
    ]);

    actingMenuOperator($setup['operator'])
        ->get('/admin/system/menus')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/System/Menu/Index')
            ->where('stats.total', 2)
            ->where('stats.groups', 1)
            ->where('stats.links', 1)
            ->has('data.rows', 2)
            ->where('data.rows.0.parent', 'Manajemen Akses'));
});

it('memfilter menu berdasarkan tipe dan pencarian', function () {
    $setup = menuCrudSetup();
    giveMenuPermissionsToOperator(['menu.viewAny']);

    Menu::create(['type' => 'group', 'title' => 'Sistem', 'sort_order' => 90, 'is_active' => true]);
    Menu::create(['type' => 'link', 'title' => 'Pengguna', 'route_name' => 'admin.access.users.index', 'sort_order' => 1, 'is_active' => true]);

    actingMenuOperator($setup['operator'])
        ->get('/admin/system/menus?type=link')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.title', 'Pengguna'));

    actingMenuOperator($setup['operator'])
        ->get('/admin/system/menus?q=pengguna')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));
});

it('menyimpan menu baru dan menolak tipe invalid', function () {
    $setup = menuCrudSetup();
    giveMenuPermissionsToOperator(['menu.create']);

    actingMenuOperator($setup['operator'])
        ->post('/admin/system/menus', [
            'type' => 'link', 'title' => 'Pengguna',
            'route_name' => 'admin.access.users.index',
            'icon' => 'fas fa-users', 'permission_name' => 'user.viewAny',
            'sort_order' => 1, 'is_active' => true,
        ])
        ->assertRedirect('/admin/system/menus')
        ->assertSessionHas('success');

    expect(Menu::where('title', 'Pengguna')->firstOrFail()->route_name)
        ->toBe('admin.access.users.index');

    actingMenuOperator($setup['operator'])
        ->post('/admin/system/menus', ['type' => 'divider', 'title' => 'X', 'sort_order' => 0])
        ->assertSessionHasErrors('type');
});

it('menolak parent dirinya sendiri saat ubah', function () {
    $setup = menuCrudSetup();
    giveMenuPermissionsToOperator(['menu.update']);

    $menu = Menu::create(['type' => 'group', 'title' => 'Sistem', 'sort_order' => 90, 'is_active' => true]);

    actingMenuOperator($setup['operator'])
        ->put("/admin/system/menus/{$menu->id}", [
            'type' => 'group', 'parent_id' => $menu->id, 'title' => 'Sistem',
            'sort_order' => 90, 'is_active' => true,
        ])
        ->assertSessionHas('error');

    expect($menu->fresh()->parent_id)->toBeNull();
});

it('mengelola sampah menu: hapus, pulihkan, hapus permanen', function () {
    $setup = menuCrudSetup();
    giveMenuPermissionsToOperator(['menu.viewAny', 'menu.delete']);

    $menu = Menu::create(['type' => 'link', 'title' => 'Sementara', 'sort_order' => 1, 'is_active' => true]);

    actingMenuOperator($setup['operator'])
        ->delete("/admin/system/menus/{$menu->id}")
        ->assertRedirect('/admin/system/menus');

    expect(Menu::find($menu->id))->toBeNull();

    actingMenuOperator($setup['operator'])
        ->get('/admin/system/menus?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingMenuOperator($setup['operator'])
        ->post("/admin/system/menus/{$menu->id}/restore")
        ->assertSessionHas('success');

    expect(Menu::find($menu->id))->not->toBeNull();

    $menu->delete();

    actingMenuOperator($setup['operator'])
        ->delete("/admin/system/menus/{$menu->id}/force")
        ->assertSessionHas('success');

    expect(Menu::withTrashed()->find($menu->id))->toBeNull();
});
