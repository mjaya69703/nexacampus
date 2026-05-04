<?php

namespace Database\Seeders;

use App\Models\Settings\Campus;
use App\Models\Settings\System;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default system settings
        $systemSettings = [
            'app_name' => 'NexaCampus',
            'app_version' => 'v1.0 - development',
            'app_description' => 'Sistem Informasi Akademik',
            'app_url' => 'https://neco-siakad.idev-fun.org',
            'app_email' => 'info@idev-fun.org',
            'is_installed' => true,
        ];

        System::create($systemSettings);

        // Create default campus settings
        $campusSettings = [
            'name' => 'Nusantara Academy',
            'phone' => '081200000001',
            'whatsapp' => '081200000003',
            'email_info' => 'info@idev-fun.org',
            'email_humas' => 'humas@idev-fun.org',
            'domain' => 'idev-fun.org',
        ];

        Campus::create($campusSettings);
    }
}
