<?php

namespace Database\Seeders;

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = ['superuser', 'admin', 'lecturer', 'student'];
        $permissions = [
            'user.viewAny',
            'user.create',
            'user.update',
            'user.delete',
            'role.viewAny',
            'role.create',
            'role.update',
            'role.delete',
            'menu.viewAny',
            'menu.create',
            'menu.update',
            'menu.delete',
            'permission.viewAny',
            'permission.create',
            'permission.update',
            'permission.delete',
            'setting.viewAny',
            'activity-log.viewAny',
            'activity-log.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        Role::findByName('superuser', 'web')->syncPermissions($permissions);
        Role::findByName('admin', 'web')->syncPermissions([]);
        Role::findByName('lecturer', 'web')->syncPermissions([]);
        Role::findByName('student', 'web')->syncPermissions([]);

        $user = User::firstOrCreate([
            'email' => 'superuser@example.com',
        ], [
            'first_name' => 'SuperUser',
            'last_name' => 'Neco Siakad',
            'photo' => 'default.jpg',
            'username' => 'superuser',
            'phone' => '0800000001',
            'code' => Str::random(6),
            'password' => Hash::make('admin123'),
        ]);

        $user->syncRoles(['superuser', 'admin', 'lecturer', 'student']);

        // Generate 250 dummy users
        // $this->command->info('Creating 250 dummy users...');

        // User::factory(250)->create()->each(function ($user) use ($roles) {
        //     // Random role assignment (1-2 roles per user)
        //     $randomRoles = fake()->randomElements($roles, rand(1, 2));
        //     $user->syncRoles($randomRoles);
        // });

        // $this->command->info('250 dummy users created successfully!');
    }
}
