<?php

namespace App\Console\Commands;

use App\Models\Access\Role;
use App\Support\ResourceRegistry;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--prune : Hapus permission web yang tidak terdaftar di config}';

    protected $description = 'Sync application permissions from config/resources.php';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $expectedPermissions = collect();

        foreach (ResourceRegistry::all() as $resource) {
            $permissions = ResourceRegistry::permissionsFor($resource);

            foreach ($permissions as $permissionName) {
                Permission::findOrCreate($permissionName, 'web');
                $expectedPermissions->push($permissionName);

                $this->line("Synced: {$permissionName}");
            }
        }

        if ($this->option('prune')) {
            $deleted = Permission::query()
                ->where('guard_name', 'web')
                ->whereNotIn('name', $expectedPermissions->unique()->values()->all())
                ->delete();

            $this->warn("Pruned {$deleted} permission(s).");
        }

        $superuserRole = Role::firstOrCreate([
            'name' => 'superuser',
            'guard_name' => 'web',
        ]);

        $superuserRole->syncPermissions(
            Permission::where('guard_name', 'web')->get()
        );

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions(
            Permission::where('guard_name', 'web')->get()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Permission sync completed.');

        return self::SUCCESS;
    }
}
