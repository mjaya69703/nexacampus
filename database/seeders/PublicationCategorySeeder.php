<?php

namespace Database\Seeders;

use App\Models\Publication\PublicationCategory;
use Illuminate\Database\Seeder;

class PublicationCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Berita Kampus',
                'slug' => 'berita-kampus',
                'desc' => 'Informasi dan berita terbaru seputar kegiatan kampus NexaCampus.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Pengumuman Akademik',
                'slug' => 'pengumuman-akademik',
                'desc' => 'Pengumuman resmi terkait kegiatan akademik dan perkuliahan.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Prestasi',
                'slug' => 'prestasi',
                'desc' => 'Pencapaian dan prestasi mahasiswa, dosen, dan alumni NexaCampus.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Kegiatan Mahasiswa',
                'slug' => 'kegiatan-mahasiswa',
                'desc' => 'Informasi kegiatan kemahasiswaan, organisasi, dan event kampus.',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Seminar & Workshop',
                'slug' => 'seminar-workshop',
                'desc' => 'Informasi seminar, workshop, pelatihan, dan webinar yang diselenggarakan kampus.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Fasilitas Kampus',
                'slug' => 'fasilitas-kampus',
                'desc' => 'Informasi mengenai fasilitas dan layanan yang tersedia di lingkungan kampus.',
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $categoryData) {
            PublicationCategory::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }
    }
}
