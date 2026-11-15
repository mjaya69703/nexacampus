<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function roleCrudSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    return compact('operator');
}

function giveRolePermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingRoleOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar peran dengan statistik', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.viewAny']);

    Role::create(['name' => 'petugas', 'guard_name' => 'web']);
    Role::create(['name' => 'viewer', 'guard_name' => 'api']);

    actingRoleOperator($setup['operator'])
        ->get('/admin/access/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Access/Role/Index')
            ->has('shell')
            ->where('can.create', false)
            ->where('stats.total', 3)
            ->where('stats.web', 2)
            ->where('stats.permissions', 1)
            ->has('data.rows', 3)
            ->where('data.rows.0.name', 'viewer'));
});

it('menyimpan peran dengan permission ID asli database', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.create']);

    $first = Permission::create(['name' => 'user.viewAny', 'guard_name' => 'web']);
    $second = Permission::create(['name' => 'user.create', 'guard_name' => 'web']);

    actingRoleOperator($setup['operator'])
        ->post('/admin/access/roles', [
            'name' => 'petugas',
            'guard_name' => 'web',
            'permission_ids' => [$first->id, $second->id],
        ])
        ->assertRedirect('/admin/access/roles')
        ->assertSessionHas('success');

    $role = Role::where('name', 'petugas')->firstOrFail();

    // Bukti bug values() Blade lama sudah hilang: relasi memakai ID asli.
    expect($role->permissions->pluck('id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

it('mengirim grup permission ber-ID asli ke form', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.create']);

    $permission = Permission::create(['name' => 'user.viewAny', 'guard_name' => 'web']);

    $response = actingRoleOperator($setup['operator'])
        ->get('/admin/access/roles/create')
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Access/Role/Form')
        ->where('mode', 'create')
        ->has('groups'));

    $groups = $response->viewData('page')['props']['groups'];
    $userGroup = collect($groups)->firstWhere('label', 'User');

    expect($userGroup['options'])->toContain(['id' => $permission->id, 'label' => 'user.viewAny']);
});

it('memvalidasi nama peran unik per guard', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.create']);

    Role::create(['name' => 'petugas', 'guard_name' => 'web']);

    actingRoleOperator($setup['operator'])
        ->post('/admin/access/roles', ['name' => 'petugas', 'guard_name' => 'web'])
        ->assertSessionHasErrors('name');

    actingRoleOperator($setup['operator'])
        ->post('/admin/access/roles', ['name' => 'petugas', 'guard_name' => 'api'])
        ->assertSessionHasNoErrors();
});

it('memperbarui peran dan me-reset permission yang dikosongkan', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.update']);

    $permission = Permission::create(['name' => 'user.viewAny', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'lama', 'guard_name' => 'web']);
    $role->syncPermissions([$permission]);

    actingRoleOperator($setup['operator'])
        ->put("/admin/access/roles/{$role->id}", [
            'name' => 'baru',
            'guard_name' => 'web',
            'permission_ids' => [],
        ])
        ->assertRedirect('/admin/access/roles')
        ->assertSessionHas('success');

    expect($role->fresh()->name)->toBe('baru')
        ->and($role->fresh()->permissions)->toHaveCount(0);
});

it('menghapus peran bebas dan menolak yang masih punya user', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.delete']);

    $free = Role::create(['name' => 'bebas', 'guard_name' => 'web']);
    $used = Role::create(['name' => 'dipakai', 'guard_name' => 'web']);
    User::factory()->create()->assignRole('dipakai');

    actingRoleOperator($setup['operator'])
        ->delete("/admin/access/roles/{$free->id}")
        ->assertRedirect('/admin/access/roles')
        ->assertSessionHas('success');

    expect(Role::find($free->id))->toBeNull();

    actingRoleOperator($setup['operator'])
        ->delete("/admin/access/roles/{$used->id}")
        ->assertSessionHas('error');

    expect(Role::find($used->id))->not->toBeNull();
});

it('mengelola sampah peran: pulihkan dan hapus permanen', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.viewAny', 'role.delete']);

    $restore = Role::create(['name' => 'pulih', 'guard_name' => 'web']);
    $restore->delete();
    $gone = Role::create(['name' => 'musnah', 'guard_name' => 'web']);
    $gone->delete();

    actingRoleOperator($setup['operator'])
        ->get('/admin/access/roles?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.mode', 'trash')
            ->has('data.rows', 2));

    actingRoleOperator($setup['operator'])
        ->post("/admin/access/roles/{$restore->id}/restore")
        ->assertSessionHas('success');

    actingRoleOperator($setup['operator'])
        ->delete("/admin/access/roles/{$gone->id}/force")
        ->assertSessionHas('success');

    expect(Role::find($restore->id))->not->toBeNull()
        ->and(Role::withTrashed()->find($gone->id))->toBeNull();
});

function makeRoleImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['name', 'guard_name', 'permission_names'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-roles').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'roles.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template impor peran', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.create']);

    $response = actingRoleOperator($setup['operator'])
        ->get('/admin/access/roles/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toContain('template-import-roles.xlsx');
});

it('mengimpor peran valid beserta permission-nya', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.create']);

    Permission::create(['name' => 'user.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'user.create', 'guard_name' => 'web']);

    $file = makeRoleImportFile([
        ['petugas', 'web', 'user.viewAny, user.create'],
        ['viewer', '', ''],
    ]);

    actingRoleOperator($setup['operator'])
        ->post('/admin/access/roles/import', ['file' => $file])
        ->assertRedirect('/admin/access/roles')
        ->assertSessionHas('success');

    expect(Role::where('name', 'petugas')->firstOrFail()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['user.create', 'user.viewAny'])
        ->and(Role::where('name', 'viewer')->firstOrFail()->guard_name)->toBe('web');
});

it('menolak seluruh file peran bila ada baris tidak valid', function () {
    $setup = roleCrudSetup();
    giveRolePermissionsToOperator(['role.create']);

    Role::create(['name' => 'petugas', 'guard_name' => 'web']);

    $file = makeRoleImportFile([
        ['baru', 'web', ''],
        ['petugas', 'web', 'permission-siluman'],
    ]);

    $response = actingRoleOperator($setup['operator'])
        ->post('/admin/access/roles/import', ['file' => $file])
        ->assertRedirect('/admin/access/roles');

    expect(Role::where('name', 'baru')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
