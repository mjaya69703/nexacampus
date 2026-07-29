<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            SettingsSeeder::class,
            NotificationTemplateSeeder::class,
            UserSeeder::class,
            MenuSeeder::class,
            AcademicSeeder::class,
            OrganizationSeeder::class,
            AdmissionSeeder::class,
            FinancialSeeder::class,
            StudentServiceSeeder::class,
            AlumniSeeder::class,
            FaqSeeder::class,
            PublicationCategorySeeder::class,
            NewsSeeder::class,
            AgendaSeeder::class,
            GalleryAlbumSeeder::class,
        ]);
    }
}
