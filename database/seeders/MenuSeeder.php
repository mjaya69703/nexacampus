<?php

namespace Database\Seeders;

use App\Models\Settings\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // Menu::query()->delete();

        // Menu::create([
        //     'type' => 'link',
        //     'title' => 'Dashboard',
        //     'route_name' => 'root.home-index',
        //     'icon' => 'fa fa-home',
        //     'permission_name' => null,
        //     'sort_order' => 1,
        //     'is_active' => true,
        // ]);

        // Menu::create([
        //     'type' => 'link',
        //     'title' => 'Profile',
        //     'route_name' => 'home.profile-index',
        //     'icon' => 'fa fa-user',
        //     'permission_name' => null,
        //     'sort_order' => 2,
        //     'is_active' => true,
        // ]);

        // // Academic Management Submenus
        // $academicManagement = Menu::create([
        //     'type' => 'group',
        //     'title' => 'Academic Management',
        //     'icon' => 'fa fa-graduation-cap',
        //     'permission_name' => null,
        //     'sort_order' => 3,
        //     'is_active' => true,
        // ]);

        // // Access Management Submenus
        // $accessManagement = Menu::create([
        //     'type' => 'group',
        //     'title' => 'Access Management',
        //     'icon' => 'fa fa-user-shield',
        //     'permission_name' => null,
        //     'sort_order' => 4,
        //     'is_active' => true,
        // ]);

        // // System Management Submenus
        // $systemManagement = Menu::create([
        //     'type' => 'group',
        //     'title' => 'System Management',
        //     'icon' => 'fa fa-cogs',
        //     'permission_name' => null,
        //     'sort_order' => 5,
        //     'is_active' => true,
        // ]);

    }
}
